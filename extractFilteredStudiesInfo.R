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
# Example: Rscript extractFilteredStudiesInfo.R hsa 3098 "Diabetes" "blood" json
################################################
# Restrictions due to the use of KEGG APIs (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html)
# * Using this code to provide user's own web service
# The code we provide is free for non-commercial use (see LICENSE). While it is our understanding that no KEGG license is required to run the web app on user's local computer for personal use (e.g., access as localhost:install_location_withrespectto_DocumentRoot/MetGENE, or, restrict its access to the IP addresses belonging to their own research group), the users must understand the KEGG license terms (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html) and decide for themselves. For example, if the user wishes to provide this tool (or their own tool based on a subset of MetGENE scripts with KEGG APIs) as a service (see LICENSE), they must obtain their own KEGG license with suitable rights.
# * Faster version of MetGENE
# If and only if the user has purchased license for KEGG FTP Data, they can activate a 'preCompute' mode to run faster version of MetGENE. To achieve this, please set preCompute = 1 in the file setPrecompute.R. Otherwise, please ensure that preCompute is set to 0 in the file setPrecompute.R. Further, to use the faster version, the user needs to run the R scripts in the 'data' folder first. Please see the respective R files in the 'data' folder for instructions to run them.
# Please see the files README.md and LICENSE for more details.
################################################

suppressPackageStartupMessages({
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
})

# ---------------------------------------------------
# Load validation helpers
# ---------------------------------------------------
source("common_functions.R")
source("setPrecompute.R")


# ---------------------------------------------------
# Utility: convert MW JSON list to a df
# ---------------------------------------------------
list_of_list_to_df <- function(jslist) {
    if (length(jslist) == 0 || is.null(jslist)) return(NULL)

    if (is.list(jslist[[1]])) {
        dt <- rbindlist(jslist, fill = TRUE)
        if (!all(c("kegg_id", "refmet_name", "study", "study_title") %in% colnames(dt)))
            return(NULL)
        return(dt[, c("kegg_id", "refmet_name", "study", "study_title")])
    }

    df <- as.data.frame(t(as.data.frame(unlist(jslist))))
    if (!all(c("kegg_id", "refmet_name", "study", "study_title") %in% colnames(df)))
        return(NULL)
    df[, c("kegg_id", "refmet_name", "study", "study_title")]
}


# ---------------------------------------------------
# Hardened: Get KEGG compound IDs
# ---------------------------------------------------
getCpdIDsFromKEGG <- function(queryStr) {
    kegg_data <- keggGet(queryStr)

    if (length(kegg_data) == 0 || is.null(kegg_data[[1]]$ORTHOLOGY)) {
        stop("Invalid KEGG entry or no ORTHOLOGY information found.")
    }

    enzyme <- kegg_data[[1]]$ORTHOLOGY[[1]]

    ec_number <- regmatches(enzyme, regexpr("EC:\\d+\\.\\d+\\.\\d+\\.\\d+", enzyme))
    if (length(ec_number) == 0) stop("No EC number found in ORTHOLOGY field.")

    ec_number <- tolower(ec_number)

    cpds <- keggLink("compound", ec_number)
    cpd_vec <- unname(as.vector(cpds))

    data.frame(
        Type = paste("compound", seq_along(cpd_vec)),
        ID   = cpd_vec,
        stringsAsFactors = FALSE
    )
}


# ---------------------------------------------------
# Hardened Study Extraction Function
# ---------------------------------------------------
getMetaboliteStudiesForGene <- function(species_raw, geneArray_raw,
                                        disease_raw, anatomy_raw,
                                        viewType_raw) {

    # -------------------------
    # Normalize species
    # -------------------------
    # -------------------------

sp <- normalize_species(species_raw)

species_code  <- sp$species_code        # hsa, mmu, rno
organism_name <- sp$species_label       # Human, Mouse, Rat

    

    # -------------------------
    # Validate disease & anatomy
    # -------------------------
    allowed_diseases <- load_allowed_diseases("disease_pulldown_menu_cascaded.json")
    allowed_anatomy  <- load_allowed_anatomy("ssdm_sample_source_pulldown_menu.html")

    diseaseStr <- validate_disease(disease_raw, allowed_diseases)
    anatomyStr <- validate_anatomy(anatomy_raw, allowed_anatomy)

    # prepare for MW query
    anatomyQryStr <- gsub("\\+", "%20", anatomyStr)
    diseaseQryStr <- gsub("\\+", "%20", diseaseStr)

    # -------------------------
    # Sanitize gene IDs
    # -------------------------
    cleanGenes <- sanitize_gene_ids(paste(geneArray_raw, collapse = ","))
    if (length(cleanGenes) == 0)
        stop("No valid gene IDs after sanitization.")

    geneArray <- cleanGenes


    # ---------------------------------------------------
    # For each gene, get KEGG metabolites
    # ---------------------------------------------------
    metabList <- list()

    for (g in seq_along(geneArray)) {
        queryStr <- paste0(species_code, ":", geneArray[g])

        if (preCompute == 1) {
            rdsFilename <- paste0("./data/", species_code, "_keggLink_mg.RDS")
            all_df <- readRDS(rdsFilename)
            df <- subset(all_df, org_ezid == queryStr)
        } else {
            df <- getCpdIDsFromKEGG(queryStr)
        }

        cpds <- df[str_detect(df[, 2], "cpd:"), 2]
        metabList <- append(metabList, gsub("cpd:", "", cpds))
    }

    metabList <- unique(unlist(metabList))


    # ---------------------------------------------------
    # Query Metabolomics Workbench METSTAT service
    # ---------------------------------------------------
    metabStudyDF <- data.frame(
        matrix(ncol = 7, nrow = 0),
        stringsAsFactors = FALSE
    )
    colnames(metabStudyDF) <- c("refmet_name", "study", "study_urls",
                                "select", "kegg_id",
                                "refmetname_url", "keggid_url")

    if (length(metabList) > 0) {

        for (m in seq_along(metabList)) {

            metabStr <- metabList[[m]]

            # MW REST endpoint
            path <- paste0(
                "https://www.metabolomicsworkbench.org/rest/metstat/;;;",
                organism_name, ";",
                anatomyQryStr, ";",
                diseaseQryStr, ";",
                metabStr
            )

            jslist <- tryCatch(
                read_json(path, simplifyVector = TRUE),
                error = function(e) NULL
            )
            respDF <- list_of_list_to_df(jslist)

            if (!is.null(respDF) && nrow(respDF) > 0) {

                df <- respDF %>%
                    distinct(study, .keep_all = TRUE) %>%
                    arrange(study) %>%
                    mutate(
                        study_urls = paste0(
                            "<a href=\"https://www.metabolomicsworkbench.org/data/DRCCMetadata.php?Mode=Study&StudyID=",
                            study,
                            "\" target=\"_blank\" title=\"", study_title, "\">",
                            study, "</a>"
                        ),
                        keggid_url = paste0(
                            "<a href=\"https://www.genome.jp/entry/cpd:",
                            kegg_id, "\">", kegg_id, "</a>"
                        ),
                        refmetname_url = paste0(
                            "<a href=\"https://www.metabolomicsworkbench.org/databases/refmet/refmet_details.php?REFMET_NAME=",
                            gsub("\\+", "%2b", gsub(" ", "+", refmet_name)),
                            "\" target=\"_blank\">", refmet_name, "</a>"
                        ),
                        select = "<input type=\"checkbox\"/>"
                    ) %>%
                    select(refmet_name, study, study_urls, select,
                           kegg_id, refmetname_url, keggid_url)

                metabStudyDF <- rbind(metabStudyDF, df)

            } else {
                metabStudyDF <- rbind(
                    metabStudyDF,
                    data.frame(
                        refmet_name = "",
                        study = "",
                        study_urls = "No studies found",
                        select = "",
                        kegg_id = metabStr,
                        refmetname_url = "",
                        keggid_url = paste0(
                            "<a href=\"https://www.genome.jp/entry/cpd:",
                            metabStr, "\">", metabStr, "</a>"
                        ),
                        stringsAsFactors = FALSE
                    )
                )
            }
        }
    }


    # ---------------------------------------------------
    # Output: JSON / TXT / HTML
    # ---------------------------------------------------
    vtFlag <- tolower(viewType_raw)

    if (vtFlag == "json") {
        studiesTableDF <- metabStudyDF[, c("kegg_id", "refmet_name", "study")]
        colnames(studiesTableDF) <- c("KEGG_COMPOUND_ID", "REFMET_NAME", "STUDY_ID")
        return(cat(toJSON(studiesTableDF, pretty = TRUE)))
    }

    if (vtFlag == "txt") {
        studiesTableDF <- metabStudyDF[, c("kegg_id", "refmet_name", "study")]
        colnames(studiesTableDF) <- c("KEGG_COMPOUND_ID", "REFMET_NAME", "STUDY_ID")
        return(cat(format_csv(studiesTableDF)))
    }

    # HTML
    if (nrow(metabStudyDF) > 0) {
        tableDF <- metabStudyDF[, c("select", "kegg_id", "refmetname_url", "study_urls")]
        tableDF$study_urls <- gsub(",", "", tableDF$study_urls)
        colnames(tableDF) <- c("SELECT", "KEGGMETABID", "REFMETNAME", "STUDIES")

        return(print(
            xtable(tableDF, html.table.attributes = "id='Table1' class='styled-table'"),
            type = "html",
            include.rownames = FALSE,
            sanitize.text.function = identity
        ))
    }

    print("Does not code for metabolites")
}


# ---------------------------------------------------
# Main
# ---------------------------------------------------
args <- commandArgs(TRUE)
species     <- args[1]
geneArray   <- strsplit(args[2], split = ",", fixed = TRUE)[[1]]
diseaseStr  <- args[3]
anatomyStr  <- args[4]
viewTypeStr <- args[5]

if (toupper(diseaseStr) == "NA") diseaseStr <- ""
if (toupper(anatomyStr) == "NA") anatomyStr <- ""

outhtml <- getMetaboliteStudiesForGene(
    species_raw   = species,
    geneArray_raw = geneArray,
    disease_raw   = diseaseStr,
    anatomy_raw   = anatomyStr,
    viewType_raw  = viewTypeStr
)
