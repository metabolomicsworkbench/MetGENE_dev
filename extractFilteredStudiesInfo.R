#!/usr/bin/env Rscript
# THis script extracts the studies information pertaining to the gene input
# Call syntax : Rscript extractFilteredStudiesInfo.R <species> <geneArray> <diseaseStr> <anatomyStr> <viewTypeStr>
# Input: species e.g. hsa, mmu
#        geneArray : comma separated list (no spaces) of ENTREZID of one or more genes e.g. 3098,6120 (ENTREZID arrary of genes)
#        diseaseStr: e.g. Diabetes, Cancer, NA (if not planning to use)
#        anatomyStr : e.g. Blood, Brain, NA (if not planning to use)
#        viewTypeStr : e.g. json, txt
# Output: A table in json or txt format comprising of study information
#         or a html table (if viewType is neither json or txt).
#         The table contains KEGG Rxn IDs, Rxn Description and Rxn equation.

################################################
# Restrictions due to the use of KEGG APIs (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html)
# * Using this code to provide user's own web service
# The code we provide is free for non-commercial use (see LICENSE). While it is our understanding that no KEGG license is required to run the web app on user's local computer for personal use (e.g., access as localhost:install_location_withrespectto_DocumentRoot/MetGENE, or, restrict its access to the IP addresses belonging to their own research group), the users must understand the KEGG license terms (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html) and decide for themselves. For example, if the user wishes to provide this tool (or their own tool based on a subset of MetGENE scripts with KEGG APIs) as a service (see LICENSE), they must obtain their own KEGG license with suitable rights.
# * Faster version of MetGENE
# If and only if the user has purchased license for KEGG FTP Data, they can activate a 'preCompute' mode to run faster version of MetGENE. To achieve this, please set preCompute = 1 in the file setPrecompute.R. Otherwise, please ensure that preCompute is set to 0 in the file setPrecompute.R. Further, to use the faster version, the user needs to run the R scripts in the 'data' folder first. Please see the respective R files in the 'data' folder for instructions to run them.
# Please see the files README.md and LICENSE for more details.
################################################

library(KEGGREST)
library(stringr)
library(rlang)
library(data.table)
library(xtable)
library(jsonlite)
library(httr)
library(rvest)
library(tictoc)
library(tidyverse)
# set flag for precompute tables
source("setPrecompute.R")
list_of_list_to_df <- function(jslist) {
    if (length(jslist) > 0) {
        # Convert character columns to numeric
        if (class(jslist[[1]]) == "list") {
            dt <- rbindlist(jslist, fill = TRUE)
            jsdf <- dt[, c("kegg_id", "refmet_name", "study", "study_title")]
            return(jsdf)
        } else {
            jsdf <- as.data.frame(t(as.data.frame(unlist(jslist))))
            jsdf <- jsdf[, c("kegg_id", "refmet_name", "study", "study_title")]
            return(jsdf)
        }
    } else {
        return(NULL)
    }
}
getCpdIDsFromKEGG <- function(queryStr) {
    kegg_data <- keggGet(queryStr)
    if (length(kegg_data) == 0 || is.null(kegg_data[[1]]$ORTHOLOGY)) {
        stop("Invalid KEGG entry or no ORTHOLOGY information found.")
    }

    enzyme <- kegg_data[[1]]$ORTHOLOGY[[1]]

    # Extract EC number
    ec_number <- regmatches(enzyme, regexpr("EC:\\d+\\.\\d+\\.\\d+\\.\\d+", enzyme))
    if (length(ec_number) == 0) {
        stop("No EC number found in ORTHOLOGY field.")
    }

    ec_number <- tolower(ec_number) # Convert to lowercase, e.g., ec:1.1.1.1


    # Get compound IDs
    cpds <- keggLink("compound", ec_number)
    cpd_vec <- unname(as.vector(cpds))

    # Create a dataframe for compounds
    compound_labels <- paste("compound", seq_along(cpd_vec))
    cpd_df <- data.frame(
        Type = compound_labels,
        ID = cpd_vec,
        stringsAsFactors = FALSE
    )

    return(cpd_df)
}

getMetaboliteStudiesForGene <- function(orgId, geneArray, diseaseId, anatomyId, viewType) {
    if (orgId %in% c("Human", "human", "hsa", "Homo sapiens")) {
        organism_name <- "Human"
    } else if (orgId %in% c("Mouse", "mouse", "mmu", "Mus musculus")) {
        organism_name <- "Mouse"
    } else if (orgStr %in% c("Rat", "rat", "rno", "Rattus norvegicus")) {
        organism_name <- "Rat"
    }
    studiesTableDF <- data.frame(matrix(ncol = 3, nrow = 0), stringsAsFactors = False)
    metabStudyDF <- data.frame(matrix(ncol = 7, nrow = 0), stringsAsFactors = False)
    colnames(metabStudyDF) <- c("refmet_name", "study", "study_urls", "select", "kegg_id", "refmetname_url", "keggid_url")
    metabStudyList <- list()
    metabList <- list()

    ## Get compounds and reactions for all genes.
    for (g in 1:length(geneArray)) {
        queryStr <- paste0(orgId, ":", geneArray[g])
        if (preCompute == 1) {
            ## Get studies, reactions pertaining to compounds from RDS file
            rdsFilename <- paste0("./data/", orgId, "_keggLink_mg.RDS")
            all_df <- readRDS(rdsFilename)

            df <- subset(all_df, org_ezid == queryStr)
        } else {
             # df <- keggLink(queryStr) # keggLink interface has changed
            df <- getCpdIDsFromKEGG(queryStr)
        }
        # All compounds pertaining to the gene are prefixed by cpd:
        cpds <- df[str_detect(df[, 2], "cpd:"), 2]
        # Prune the prefixes so that the list comprises of only the ids
        metabList <- append(metabList, gsub("cpd:", "", cpds))
    }

    metabList <- unique(metabList)

    anatomyQryStr <- anatomyId
    diseaseQryStr <- diseaseId
    # https://metabolomicsworkbench.org/rest/metstat/;;;human;Fibroblast%20cells;;C00031 works 
    # but https://metabolomicsworkbench.org/rest/metstat/;;;human;Fibroblast+cells;;C00031 does not
    # PHP encodes space to + so we have to replace it by %20
    pat_str = "\\+"; rep_str = "%20";
    #if (!is_empty(anatomyId) && length(anatomyId) > 0 && str_detect(anatomyId, "\\+")) {
    #    anatomyQryStr <- str_replace_all(anatomyId, "\\+", "%20")
    if (!is_empty(anatomyId) && length(anatomyId) > 0 && str_detect(anatomyId, pat_str)) {
        anatomyQryStr <- str_replace_all(anatomyId, pat_str, rep_str)
    }

    if (!is_empty(diseaseId) && length(diseaseId) > 0 && str_detect(diseaseId, pat_str)) {
        diseaseQryStr <- str_replace_all(diseaseId, pat_str, rep_str)
    }

    if (length(metabList) > 0) {
        for (m in 1:length(metabList)) {
            metabStr <- metabList[[m]]
            #     print(metabStr);

            ## Need this to get RefMet Names, study-ids, study titles
            #      tic("Time taken for studies info per compound")
            # /rest/metstat/<ANALYSIS_TYPE>;<POLARITY>;<CHROMATOGRAPHY>;<SPECIES>;<SAMPLE SOURCE>;<DISEASE>;<KEGG_ID>;<REFMET_NAME>
            #  metabStr is KEGG_ID
            #            path = paste0("https://www.metabolomicsworkbench.org/rest/metstat/;;", organism_name, ";", anatomyQryStr, ";", diseaseQryStr, ";", metabStr);
            path <- paste0("https://www.metabolomicsworkbench.org/rest/metstat/;;;", organism_name, ";", anatomyQryStr, ";", diseaseQryStr, ";", metabStr)
            #print(path)
            jslist <- read_json(path, simplifyVector = TRUE)
            #      toc()
            respDF <- list_of_list_to_df(jslist)
            mydf_studies <- respDF[, c("refmet_name", "kegg_id", "study", "study_title")]
            # print(nrow(mydf_studies))
            # SUMANA ADDED March 21 - remove duplicate studies
            if (!is.null(mydf_studies) && nrow(mydf_studies) > 0) {
                mydf_studies <- mydf_studies %>%
                    distinct(study, .keep_all = TRUE) %>%
                    arrange(study) %>%
                    select(refmet_name, kegg_id, study, study_title)
            }

            if (!is.null(mydf_studies)) {
                ##      multiple refmet IDs case, loop through and create an entry for each variant

                url_df <- mydf_studies %>% mutate(study_urls = paste0("<a href=\"https://www.metabolomicsworkbench.org/data/DRCCMetadata.php?Mode=Study&StudyID=", study, "\" target=\"blank\" title=\"", study_title, "\"> ", study, " </a>"))

                url_df <- url_df %>% mutate(keggid_url = paste0("<a href = \"https://www.genome.jp/entry/cpd:", kegg_id, "\">", kegg_id, "</a>"))

                url_df <- url_df %>% mutate(refmetname_url = paste0("<a href=\"https://www.metabolomicsworkbench.org/databases/refmet/refmet_details.php?REFMET_NAME=", str_replace_all(refmet_name, c("\\+" = "%2b", " " = "+")), "\" target=\"_blank\"> ", refmet_name, "</a>"))
                refmetnameURLDF <- unique(url_df$refmetname_url)
                keggidDF <- unique(url_df$kegg_id)
                keggidURLDF <- unique(url_df$keggid_url)
                result_df <- aggregate(cbind(study, study_urls) ~ refmet_name, data = url_df, FUN = function(x) c(paste(x, collapse = ", ")))

                result_df <- result_df %>% mutate(select = paste0("<input type=\"checkbox\"/>"))

                result_df <- cbind(result_df, kegg_id = keggidURLDF, refmetname_url = refmetnameURLDF, keggid_url = keggidURLDF)


                metabStudyDF <- rbind(metabStudyDF, result_df)
            } else {
                # get metabolite name from KeGG
                kegg_id <- metabStr
                keggid_url <- paste0("<a href = \"https://www.genome.jp/entry/cpd:", metabStr, "\">", metabStr, "</a>")
                refmet_name <- ""
                refmetname_url <- " "
                study <- ""

                study_urls <- "No studies found"
                select <- ""
                result_df <- data.frame(kegg_id = kegg_id, refmet_name = refmet_name, study = study, study_urls = study_urls, keggid_url = keggid_url, refmetname_url = refmetname_url, select = select)
                metabStudyDF <- rbind(metabStudyDF, result_df)
            }
        }
    }

    vtFlag <- tolower(viewType)

    if (vtFlag == "json") {
        studiesTableDF <- metabStudyDF[, c("kegg_id", "refmet_name", "study")]
        colnames(studiesTableDF) <- c("KEGG_COMPOUND_ID", "REFMET_NAME", "STUDY_ID")
        studyJson <- toJSON(x = studiesTableDF, pretty = T)
        return(cat(toString(studyJson)))
    } else if (vtFlag == "txt") {
        studiesTableDF <- metabStudyDF[, c("kegg_id", "refmet_name", "study")]
        colnames(studiesTableDF) <- c("KEGG_COMPOUND_ID", "REFMET_NAME", "STUDY_ID")
        return(cat(format_csv(studiesTableDF)))
    } else {
        if (nrow(metabStudyDF) > 0) {
            tableDF <- metabStudyDF[, c("select", "kegg_id", "refmetname_url", "study_urls")]
            tableDF$study_urls <- gsub(",", "", tableDF$study_urls)
            colnames(tableDF) <- c("SELECT", "KEGGMETABID", "REFMETNAME", "STUDIES")
            nprint <- nrow(tableDF)
            return(print(xtable(tableDF[1:nprint, ]), type = "html", include.rownames = FALSE, sanitize.text.function = function(x) {
                x
            }, html.table.attributes = "id='Table1' class='styled-table'"))
        } else {
            return(print(paste0(" Does not code for metabolites")))
        }
    }
}


args <- commandArgs(TRUE)
species <- args[1]
geneArray <- as.vector(strsplit(args[2], split = ",", fixed = TRUE)[[1]])
diseaseStr <- args[3]
anatomyStr <- args[4]
viewTypeStr <- args[5]
## geneArray <- c(3098,229);
if (diseaseStr == "NA") {
    diseaseStr <- ""
}
if (anatomyStr == "NA") {
    anatomyStr <- ""
}
#print(paste0("Anatomy Str = ", anatomyStr))
# tic("Time elapsed = ")
outhtml <- getMetaboliteStudiesForGene(species, geneArray, diseaseStr, anatomyStr, viewTypeStr)
# toc()
