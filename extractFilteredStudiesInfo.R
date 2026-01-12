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

# SECURITY: Load centralized validation + normalization helpers
source("metgene_common.R")

# set flag for precompute tables
source("setPrecompute.R")

###############################################################
# Helper: convert list-of-lists from MW REST API to data frame
###############################################################
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

###############################################################
# Helper: retrieve KEGG compound IDs for one gene
###############################################################
getCpdIDsFromKEGG <- function(queryStr) {
    kegg_data <- tryCatch(
        keggGet(queryStr),
        error = function(e) {
            stop("KEGG query failed for ", queryStr, ": ", e$message, call. = FALSE)
        }
    )

    if (length(kegg_data) == 0 || is.null(kegg_data[[1]]$ORTHOLOGY)) {
        stop("Invalid KEGG entry or no ORTHOLOGY information found for ", queryStr, call. = FALSE)
    }

    enzyme <- kegg_data[[1]]$ORTHOLOGY[[1]]

    # Extract EC number
    ec_number <- regmatches(enzyme, regexpr("EC:\\d+\\.\\d+\\.\\d+\\.\\d+", enzyme))
    if (length(ec_number) == 0) {
        stop("No EC number found in ORTHOLOGY field for ", queryStr, call. = FALSE)
    }

    ec_number <- tolower(ec_number) # Convert to lowercase, e.g., ec:1.1.1.1

    # Get compound IDs
    cpds <- tryCatch(
        keggLink("compound", ec_number),
        error = function(e) {
            stop("keggLink failed for ", ec_number, ": ", e$message, call. = FALSE)
        }
    )

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

###############################################################
# Main function to get metabolite studies (SECURITY HARDENED)
###############################################################
getMetaboliteStudiesForGene <- function(orgId, geneArray, diseaseId, anatomyId, viewType) {
    # ---------------------------
    # SECURITY: Normalize species
    # ---------------------------
    sp <- normalize_species(orgId)
    organism_name <- sp$species_label # Human/Mouse/Rat
    orgId_code <- sp$species_code # hsa/mmu/rno

    # ---------------------------
    # SECURITY: Load curated validation lists
    # ---------------------------
    allowed_diseases <- load_allowed_diseases("disease_pulldown_menu_cascaded.json")
    allowed_anatomy <- load_allowed_anatomy("ssdm_sample_source_pulldown_menu.html")

    # ---------------------------
    # SECURITY: Validate disease / anatomy
    # ---------------------------
    diseaseId <- validate_disease(diseaseId, allowed_diseases)
    anatomyId <- validate_anatomy(anatomyId, allowed_anatomy)

    # ---------------------------
    # Initialize data structures
    # ---------------------------
    studiesTableDF <- data.frame(matrix(ncol = 3, nrow = 0), stringsAsFactors = FALSE)
    metabStudyDF <- data.frame(matrix(ncol = 7, nrow = 0), stringsAsFactors = FALSE)
    colnames(metabStudyDF) <- c(
        "refmet_name", "study", "study_urls", "select",
        "kegg_id", "refmetname_url", "keggid_url"
    )
    metabStudyList <- list()
    metabList <- list()

    # ---------------------------
    ## Get compounds and reactions for all genes.
    # ---------------------------
    for (g in 1:length(geneArray)) {
        # SECURITY: Validate each gene ID
        geneId <- validate_entrez_ids(geneArray[g])

        queryStr <- paste0(orgId_code, ":", geneId)

        if (preCompute == 1) {
            ## Get studies, reactions pertaining to compounds from RDS file
            # SECURITY: Use safe_read_rds from metgene_common.R
            all_df <- safe_read_rds(orgId_code, "_keggLink_mg.RDS", base_dir = "data")
            df <- subset(all_df, org_ezid == queryStr)
        } else {
            df <- getCpdIDsFromKEGG(queryStr)
        }

        # All compounds pertaining to the gene are prefixed by cpd:
        cpds <- df[str_detect(df[, 2], "cpd:"), 2]
        # Prune the prefixes so that the list comprises of only the ids
        metabList <- append(metabList, gsub("cpd:", "", cpds))
    }

    metabList <- unique(metabList)

    # ---------------------------
    # SECURITY: URL encode anatomy and disease for MW REST API
    # MW REST API expects %20 for spaces, not +
    # ---------------------------
    anatomyQryStr <- anatomyId
    diseaseQryStr <- diseaseId

    # ---------------------------
    # SECURITY: URL encode anatomy and disease for MW REST API
    # MW REST API expects %20 for spaces, not +
    # Handle both spaces and + characters
    # ---------------------------

    # Process anatomy
    if (!is.null(anatomyId) && nzchar(anatomyId) && anatomyId != "NA") {
        # First convert + to space (in case it came from URL encoding)
        anatomyQryStr <- gsub("\\+", " ", anatomyId, fixed = FALSE)
        # Then URL encode (converts spaces to %20)
        anatomyQryStr <- URLencode(anatomyQryStr, reserved = TRUE)
    } else {
        anatomyQryStr <- ""
    }

    # Process disease
    if (!is.null(diseaseId) && nzchar(diseaseId) && diseaseId != "NA") {
        # First convert + to space (in case it came from URL encoding)
        diseaseQryStr <- gsub("\\+", " ", diseaseId, fixed = FALSE)
        # Then URL encode (converts spaces to %20)
        diseaseQryStr <- URLencode(diseaseQryStr, reserved = TRUE)
    } else {
        diseaseQryStr <- ""
    }
    # ---------------------------
    # Process each metabolite
    # ---------------------------
    if (length(metabList) > 0) {
        for (m in 1:length(metabList)) {
            metabStr <- metabList[[m]]

            ## Need this to get RefMet Names, study-ids, study titles
            # /rest/metstat/<ANALYSIS_TYPE>;<POLARITY>;<CHROMATOGRAPHY>;<SPECIES>;<SAMPLE SOURCE>;<DISEASE>;<KEGG_ID>;<REFMET_NAME>
            #  metabStr is KEGG_ID
            path <- paste0(
                "https://www.metabolomicsworkbench.org/rest/metstat/;;;",
                organism_name, ";",
                anatomyQryStr, ";",
                diseaseQryStr, ";",
                metabStr
            )
            # print(path)
            # SECURITY: Wrap REST API call in tryCatch
            jslist <- tryCatch(
                read_json(path, simplifyVector = TRUE),
                error = function(e) {
                    warning("MW REST API failed for ", metabStr, ": ", e$message)
                    return(list())
                }
            )

            respDF <- list_of_list_to_df(jslist)

            if (is.null(respDF)) {
                # No response - create empty entry
                kegg_id <- metabStr
                # SECURITY: Escape metabolite ID for HTML
                keggid_url <- paste0(
                    "<a href=\"https://www.genome.jp/entry/cpd:",
                    html_escape(metabStr),
                    "\">",
                    html_escape(metabStr),
                    "</a>"
                )
                refmet_name <- ""
                refmetname_url <- " "
                study <- ""
                study_urls <- "No studies found"
                select <- ""

                result_df <- data.frame(
                    kegg_id = kegg_id,
                    refmet_name = refmet_name,
                    study = study,
                    study_urls = study_urls,
                    keggid_url = keggid_url,
                    refmetname_url = refmetname_url,
                    select = select,
                    stringsAsFactors = FALSE
                )
                metabStudyDF <- rbind(metabStudyDF, result_df)
                next
            }

            mydf_studies <- respDF[, c("refmet_name", "kegg_id", "study", "study_title")]

            # SUMANA ADDED March 21 - remove duplicate studies
            if (!is.null(mydf_studies) && nrow(mydf_studies) > 0) {
                mydf_studies <- mydf_studies %>%
                    distinct(study, .keep_all = TRUE) %>%
                    arrange(study) %>%
                    select(refmet_name, kegg_id, study, study_title)
            }

            if (!is.null(mydf_studies) && nrow(mydf_studies) > 0) {
                ##      multiple refmet IDs case, loop through and create an entry for each variant

                # SECURITY: Escape all values for HTML output
                url_df <- mydf_studies %>%
                    mutate(
                        study_urls = paste0(
                            "<a href=\"https://www.metabolomicsworkbench.org/data/DRCCMetadata.php?Mode=Study&StudyID=",
                            html_escape(study),
                            "\" target=\"_blank\" title=\"",
                            html_escape(study_title),
                            "\"> ",
                            html_escape(study),
                            " </a>"
                        )
                    )

                # SECURITY: Escape KEGG IDs for HTML
                url_df <- url_df %>%
                    mutate(
                        keggid_url = paste0(
                            "<a href=\"https://www.genome.jp/entry/cpd:",
                            html_escape(kegg_id),
                            "\">",
                            html_escape(kegg_id),
                            "</a>"
                        )
                    )

                # SECURITY: URL encode RefMet names properly, escape for HTML
                url_df <- url_df %>%
                    mutate(
                        refmetname_url = paste0(
                            "<a href=\"https://www.metabolomicsworkbench.org/databases/refmet/refmet_details.php?REFMET_NAME=",
                            str_replace_all(refmet_name, c("\\+" = "%2b", " " = "+")),
                            "\" target=\"_blank\"> ",
                            html_escape(refmet_name),
                            "</a>"
                        )
                    )

                refmetnameURLDF <- unique(url_df$refmetname_url)
                keggidDF <- unique(url_df$kegg_id)
                keggidURLDF <- unique(url_df$keggid_url)

                # Aggregate studies per RefMet name
                result_df <- aggregate(
                    cbind(study, study_urls) ~ refmet_name,
                    data = url_df,
                    FUN = function(x) paste(x, collapse = " ")
                )

                result_df <- result_df %>%
                    mutate(select = paste0("<input type=\"checkbox\"/>"))

                result_df <- cbind(
                    result_df,
                    kegg_id = keggidURLDF,
                    refmetname_url = refmetnameURLDF,
                    keggid_url = keggidURLDF
                )

                metabStudyDF <- rbind(metabStudyDF, result_df)
            }
        }
    }

    # ---------------------------
    # SECURITY: Validate view type
    # ---------------------------
    viewType <- safe_view_type(viewType)

    # ---------------------------
    # Output section
    # ---------------------------
    if (viewType == "json") {
        studiesTableDF <- metabStudyDF[, c("kegg_id", "refmet_name", "study")]
        colnames(studiesTableDF) <- c("KEGG_COMPOUND_ID", "REFMET_NAME", "STUDY_ID")
        studyJson <- toJSON(x = studiesTableDF, pretty = TRUE)
        return(cat(toString(studyJson)))
    } else if (viewType == "txt") {
        studiesTableDF <- metabStudyDF[, c("kegg_id", "refmet_name", "study")]
        colnames(studiesTableDF) <- c("KEGG_COMPOUND_ID", "REFMET_NAME", "STUDY_ID")
        return(cat(format_csv(studiesTableDF)))
    } else {
        # HTML output
        if (nrow(metabStudyDF) > 0) {
            tableDF <- metabStudyDF[, c("select", "kegg_id", "refmetname_url", "study_urls")]
            tableDF$study_urls <- gsub(",", "", tableDF$study_urls)
            colnames(tableDF) <- c("&#10003;", "KEGG ID", "REFMETNAME", "STUDIES")
            nprint <- nrow(tableDF)

            return(
                print(
                    xtable(tableDF[1:nprint, ]),
                    type = "html",
                    include.rownames = FALSE,
                    sanitize.text.function = function(x) x, # Don't double-escape
                    html.table.attributes = "id='Table1' class='styled-table'"
                )
            )
        } else {
            return(print(paste0("Does not code for metabolites")))
        }
    }
}

###############################################################
# Main script entry
###############################################################
args <- commandArgs(TRUE)

# SECURITY: Validate argument count
if (length(args) < 5) {
    write(
        "Usage: extractFilteredStudiesInfo.R <species> <geneArray> <diseaseStr> <anatomyStr> <viewTypeStr>",
        stderr()
    )
    quit(status = 1)
}

species <- args[1]
geneArrayStr <- args[2]
diseaseStr <- args[3]
anatomyStr <- args[4]
viewTypeStr <- args[5]

# SECURITY: Parse and validate gene array
# Split by comma, trim whitespace, validate each ID
geneArray <- as.vector(strsplit(geneArrayStr, split = ",", fixed = TRUE)[[1]])
geneArray <- trimws(geneArray)
geneArray <- geneArray[geneArray != ""] # Remove empty strings

if (length(geneArray) == 0) {
    write("ERROR: No valid gene IDs provided", stderr())
    quit(status = 1)
}

# SECURITY: Validate each gene ID individually
for (i in seq_along(geneArray)) {
    tryCatch(
        {
            geneArray[i] <- validate_entrez_ids(geneArray[i])
        },
        error = function(e) {
            write(paste("ERROR: Invalid gene ID:", geneArray[i], "-", e$message), stderr())
            quit(status = 1)
        }
    )
}

# Handle "NA" strings for disease and anatomy
if (diseaseStr == "NA") {
    diseaseStr <- ""
}
if (anatomyStr == "NA") {
    anatomyStr <- ""
}

# SECURITY: Wrap main execution in tryCatch
tryCatch(
    {
        outhtml <- getMetaboliteStudiesForGene(
            species,
            geneArray,
            diseaseStr,
            anatomyStr,
            viewTypeStr
        )
    },
    error = function(e) {
        write(paste("ERROR:", e$message), stderr())
        quit(status = 1)
    }
)
