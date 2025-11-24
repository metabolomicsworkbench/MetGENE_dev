#!/usr/bin/env Rscript
# THis script extracts the metabolite information pertaining to the gene input
# Call syntax : Rscript extractMetaboliteInfo.R <species> <geneStr> <anatomyStr> <diseaseStr> <viewType>
# Input: species e.g. hsa, mmu
#        geneStr : ENTREZID of a gene e.g. 3098
#        anatomyStr : e.g. Blood, Brain, NA (if not planning to use)
#        diseaseStr: e.g. Diabetes, Cancer, NA (if not planning to use)
#        viewType : e.g. json, txt
# Output: A table in json or txt format comprising of metabolite information
#         or a html table (if viewType is neither json or txt).
#         The table contains Gene, KEGG_COMPOUND_ID, REFMET_NAME, KEGG_REACTION_ID, METSTAT_LINK
#
################################################
# Restrictions due to the use of KEGG APIs (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html)
# * Using this code to provide user's own web service
# The code we provide is free for non-commercial use (see LICENSE). While it is our understanding that no KEGG license is required to run the web app on user's local computer for personal use (e.g., access as localhost:install_location_withrespectto_DocumentRoot/MetGENE, or, restrict its access to the IP addresses belonging to their own research group), the users must understand the KEGG license terms (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html) and decide for themselves. For example, if the user wishes to provide this tool (or their own tool based on a subset of MetGENE scripts with KEGG APIs) as a service (see LICENSE), they must obtain their own KEGG license with suitable rights.
# * Faster version of MetGENE
# If and only if the user has purchased license for KEGG FTP Data, they can activate a 'preCompute' mode to run faster version of MetGENE. To achieve this, please set preCompute = 1 in the file setPrecompute.R. Otherwise, please ensure that preCompute is set to 0 in the file setPrecompute.R. Further, to use the faster version, the user needs to run the R scripts in the 'data' folder first. Please see the respective R files in the 'data' folder for instructions to run them.
# Please see the files README.md and LICENSE for more details.
################################################
# source("libPathKEGGREST.R") # nolint: spaces_inside_linter, line_length_linter.

suppressPackageStartupMessages({
  library(KEGGREST)
  library(rlang)
  library(stringr)
  library(data.table)
  library(xtable)
  library(jsonlite)
  library(tictoc)
  library(tidyverse)
  library(plyr)
})

# local helpers (validation / normalization)
source("common_functions.R")

# set flag for precompute tables
source("setPrecompute.R")
source("refmet_convert_faster_fun.R")

# --------------------------------------------------------------------
# Helper: get reactions that contain a metabolite
# --------------------------------------------------------------------
getRxnsContainingMetabolite <- function(rxnList, metdf) {
  ## Get reactions from KeGG for the metabolite and return common reactions with gene reaction set
  metRxns <- metdf$REACTION
  metRxnsArr <- unlist(metRxns)
  combined_metRxns <- paste(metRxnsArr, collapse = " ")
  metRxns <- strsplit(combined_metRxns, " ")[[1]]
  commonRxns <- (rxnList[rxnList %in% metRxns])
  commonRxns
}

# --------------------------------------------------------------------
# Helper: get reaction IDs + compound IDs from KEGG
# --------------------------------------------------------------------
getRxnIDsAndCpdIDsFromKEGG <- function(queryStr) {
  # Fetch enzyme information from KEGG
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

  # Get reaction IDs
  rxns <- keggLink("reaction", ec_number)
  rxn_vec <- unname(as.vector(rxns))

  # Create a dataframe for reactions
  reaction_labels <- paste("reaction", seq_along(rxn_vec))
  rxn_df <- data.frame(
    Type = reaction_labels,
    ID   = rxn_vec,
    stringsAsFactors = FALSE
  )

  # Get compound IDs
  cpds <- keggLink("compound", ec_number)
  cpd_vec <- unname(as.vector(cpds))

  # Create a dataframe for compounds
  compound_labels <- paste("compound", seq_along(cpd_vec))
  cpd_df <- data.frame(
    Type = compound_labels,
    ID   = cpd_vec,
    stringsAsFactors = FALSE
  )

  # Combine the dataframes
  rbind(rxn_df, cpd_df)
}

# --------------------------------------------------------------------
# Main table generator
# --------------------------------------------------------------------
getMetaboliteInfoTable <- function(species_raw,
                                   geneIdStr_raw,
                                   anatomyStr_raw,
                                   diseaseStr_raw,
                                   viewType_raw) {
  # Normalize species (mirrors PHP normalizeSpecies)
  sp_norm <- normalize_species(species_raw)
  species <- sp_norm$code
  organism_name <- sp_norm$org_name

  # Load curated lists for validation
  allowed_diseases <- load_allowed_diseases("disease_pulldown_menu_cascaded.json")
  allowed_anatomy  <- load_allowed_anatomy("ssdm_sample_source_pulldown_menu.html")

  # Sanitize gene ID(s), use the first valid entry
  gene_ids <- sanitize_gene_ids(geneIdStr_raw)
  if (length(gene_ids) == 0) {
    stop("No valid gene ID provided for metabolite extraction.")
  }
  geneIdStr <- gene_ids[[1]]

  # Validate disease / anatomy using both curated list + safe pattern
  diseaseStr <- validate_disease(diseaseStr_raw, allowed_diseases)
  anatomyStr <- validate_anatomy(anatomyStr_raw, allowed_anatomy)

  # convert "empty" to "" (used in URLs)
  if (identical(diseaseStr, "")) {
    diseaseStr <- ""
  }
  if (identical(anatomyStr, "")) {
    anatomyStr <- ""
  }

  # view type: pass through, but use lower-case flag
  viewType <- as.character(viewType_raw[[1]])
  vtFlag <- tolower(viewType)

  currDir <- paste0("/", basename(getwd()))

  metabRxnList <- data.frame(
    matrix(ncol = 4, nrow = 0),
    stringsAsFactors = FALSE
  )
  colnames(metabRxnList) <- c("KEGG_COMPOUND_ID", "REFMET_NAME", "KEGG_REACTION_ID", "METSTAT_LINK")

  jsonDF <- data.frame(
    matrix(ncol = 4, nrow = 0),
    stringsAsFactors = FALSE
  )
  colnames(jsonDF) <- c("KEGG_COMPOUND_ID", "REFMET_NAME", "KEGG_REACTION_ID", "METSTAT_LINK")

  # Build KEGG query
  queryStr <- paste0(species, ":", geneIdStr)

  if (preCompute == 1) {
    # Load RDS file containing gene information
    rdsFilename <- paste0("./data/", species, "_keggLink_mg.RDS")
    all_df <- readRDS(rdsFilename)
    df <- subset(all_df, org_ezid == queryStr)
  } else {
    df <- getRxnIDsAndCpdIDsFromKEGG(queryStr)
  }

  # All metabolites pertaining to the gene are prefixed as cpd:
  cpds <- df[str_detect(df[, 2], "cpd:"), 2]
  # All reactions pertaining to the gene are prefixed as rn:
  rxns <- df[str_detect(df[, 2], "rn:"), 2]

  metabList <- gsub("cpd:", "", cpds)
  reactionsList <- gsub("rn:", "", rxns)

  # prepare strings used for MW queries
  anatomyQryStr <- anatomyStr
  diseaseQryStr <- diseaseStr

  if (!is.null(anatomyQryStr) && nchar(anatomyQryStr) > 0 && str_detect(anatomyQryStr, " ")) {
    anatomyQryStr <- str_replace_all(anatomyQryStr, " ", "+")
  }

  if (!is.null(diseaseQryStr) && nchar(diseaseQryStr) > 0 && str_detect(diseaseQryStr, " ")) {
    diseaseQryStr <- str_replace_all(diseaseQryStr, " ", "+")
  }

  # Retrieve KEGG cpd info
  if (preCompute == 1) {
    rdsfilename <- paste0("./data/", species, "_keggGet_cpdInfo.RDS")
    cpdInfodf <- readRDS(rdsfilename)
    metRespdf <- cpdInfodf[rownames(cpdInfodf) %in% metabList, ]
  } else {
    query_split <- split(metabList, ceiling(seq_along(metabList) / 10))
    info <- llply(query_split, function(x) keggGet(x))
    unlist_info <- unlist(info, recursive = FALSE)
    extract_info <- lapply(unlist_info, "[", c("ENTRY", "NAME", "REACTION"))
    dd <- do.call(rbind, extract_info)
    metRespdf <- as.data.frame(dd)
    rownames(metRespdf) <- metRespdf$ENTRY
  }

  if (length(metabList) > 0) {
    allrefmetDF <- refmet_convert_fun(as.data.frame(metabList))

    for (m in seq_along(metabList)) {
      metabStr <- metabList[[m]]

      metabURLStr <- paste0(
        "<a href = \"https://www.kegg.jp/entry/",
        metabStr,
        "\" target = \"__blank\">",
        metabStr,
        "</a>"
      )

      metqryDF <- metRespdf[grep(metabStr, rownames(metRespdf)), ]
      keggCpdName <- unique(metqryDF$NAME)

      metabRxns <- getRxnsContainingMetabolite(reactionsList, metqryDF)

      mwDF <- subset(allrefmetDF, Input.name == metabStr)

      if (!is.null(mwDF) && nrow(mwDF) > 0) {
        refmetIdVals <- unique(mwDF$Standardized.name)
        refCounts <- length(refmetIdVals)

        if (refCounts == 1) {
          refmetId <- refmetIdVals

          refmetQryStr <- gsub("\\+", "%2b", refmetId)
          refmetQryStr <- gsub(" ", "+", refmetQryStr)

          refmetName <- paste0(
            "<a href=\"https://www.metabolomicsworkbench.org/databases/refmet/refmet_details.php?REFMET_NAME=",
            refmetQryStr,
            "\" target = \"_blank\"> ",
            refmetId,
            "</a>"
          )

          metStatStr <- paste0(
            "<a href=\"http://www.metabolomicsworkbench.org/data/metstat_hist.php?NAME_PREP1=Is&refmet_name_search=",
            refmetQryStr,
            "&refmet_name=",
            refmetQryStr,
            "&SPECIES=",
            organism_name,
            "&DISEASE=",
            diseaseQryStr,
            "&SOURCE=",
            anatomyQryStr,
            "&rows_to_display=1\" target=\"_blank\">",
            "<img src=\"",
            currDir,
            "/images/statSymbolIcon.png\" alt=\"metstat\" width=\"30\">",
            "</a>"
          )

          metabRxnsStr <- paste(metabRxns, collapse = ", ")
          rxnsURLs <- paste0(
            "<a href=\"https://www.genome.jp/entry/rn:",
            metabRxns,
            "\" target=\"_blank\">",
            metabRxns,
            "</a>"
          )
          rxnsURLStr <- paste(rxnsURLs, collapse = ", ")

          metabRxnList[nrow(metabRxnList) + 1, ] <-
            c(metabURLStr, refmetName, rxnsURLStr, metStatStr)

          metStatLink <- paste0(
            "http://www.metabolomicsworkbench.org/data/metstat_hist.php?NAME_PREP1=Is&refmet_name_search=",
            refmetQryStr,
            "&refmet_name=",
            refmetQryStr,
            "&SPECIES=",
            organism_name,
            "&DISEASE=",
            diseaseQryStr,
            "&SOURCE=",
            anatomyQryStr
          )

          jsonDF[nrow(jsonDF) + 1, ] <-
            c(metabStr, refmetId, metabRxnsStr, metStatLink)
        } else {
          # multiple refmet Ids case, vectorized
          refmetQryStrs <- gsub("\\+", "%2b", refmetIdVals)
          refmetQryStrs <- gsub(" ", "+", refmetQryStrs)

          refmetNameStrs <- paste0(
            "<a href=\"https://www.metabolomicsworkbench.org/databases/refmet/refmet_details.php?REFMET_NAME=",
            refmetQryStrs,
            "\" target = \"_blank\"> ",
            refmetIdVals,
            "</a>"
          )

          metStatStrs <- paste0(
            "<a href=\"http://www.metabolomicsworkbench.org/data/metstat_hist.php?NAME_PREP1=Is&refmet_name_search=",
            refmetQryStrs,
            "&refmet_name=",
            refmetQryStrs,
            "&SPECIES=",
            organism_name,
            "&DISEASE=",
            diseaseQryStr,
            "&SOURCE=",
            anatomyQryStr,
            "&rows_to_display=1\" target=\"_blank\">",
            "<img src=\"",
            currDir,
            "/images/statSymbolIcon.png\" alt=\"metstat\" width=\"30\">",
            "</a>"
          )

          metabRxnsStr <- paste(metabRxns, collapse = ", ")
          rxnsURLs <- paste0(
            "<a href=\"https://www.genome.jp/entry/rn:",
            metabRxns,
            "\" target=\"_blank\">",
            metabRxns,
            "</a>"
          )
          rxnsURLStr <- paste(rxnsURLs, collapse = ", ")

          rxnURLStrs    <- rep(rxnsURLStr, length(refmetIdVals))
          metabURLStrs  <- rep(metabURLStr, length(refmetIdVals))

          refMetDF <- data.frame(
            metabURLStrs,
            refmetNameStrs,
            rxnURLStrs,
            metStatStrs,
            stringsAsFactors = FALSE
          )
          colnames(refMetDF) <- colnames(metabRxnList)
          metabRxnList <- rbind(metabRxnList, refMetDF)

          metStatLinks <- paste0(
            "http://www.metabolomicsworkbench.org/data/metstat_hist.php?NAME_PREP1=Is&refmet_name_search=",
            refmetQryStrs,
            "&refmet_name=",
            refmetQryStrs,
            "&SPECIES=",
            organism_name,
            "&DISEASE=",
            diseaseQryStr,
            "&SOURCE=",
            anatomyQryStr
          )

          metabRxnsStr <- paste(metabRxns, collapse = ", ")
          metabStrs    <- rep(metabStr, length(refmetIdVals))
          metabRxnsStrs <- rep(metabRxnsStr, length(refmetIdVals))

          refmetJsonDF <- data.frame(
            metabStrs,
            refmetIdVals,
            metabRxnsStrs,
            metStatLinks,
            stringsAsFactors = FALSE
          )
          colnames(refmetJsonDF) <- colnames(jsonDF)
          jsonDF <- rbind(jsonDF, refmetJsonDF)
        }
      } else {
        # No RefMet mapping, fall back to KEGG name
        refmetId <- unique(metqryDF$NAME)
        refmetId <- gsub(";$", "", refmetId)

        refmetName <- paste0(
          "<a href=\"https://www.genome.jp/entry/cpd:",
          metabStr,
          "\" target = \"_blank\"> ",
          refmetId,
          "</a>"
        )

        metStatStr <- ""
        metabRxnsStr <- paste(metabRxns, collapse = ", ")

        rxnsURLs <- paste0(
          "<a href=\"https://www.genome.jp/entry/rn:",
          metabRxns,
          "\" target=\"_blank\">",
          metabRxns,
          "</a>"
        )
        rxnsURLStr <- paste(rxnsURLs, collapse = ", ")

        metabRxnList[nrow(metabRxnList) + 1, ] <-
          c(metabURLStr, refmetName, rxnsURLStr, metStatStr)

        metStatLink <- ""
        jsonDF[nrow(jsonDF) + 1, ] <-
          c(metabStr, refmetId, metabRxnsStr, metStatLink)
      }
    }

    metDF <- metabRxnList

    # ---------------------- Output selection --------------------------
    vtFlag <- tolower(viewType)

    if (vtFlag %in% c("json", "jsonfile")) {
      newDF <- cbind(Gene = geneIdStr, jsonDF)
      metabJson <- toJSON(x = newDF, pretty = TRUE)
      return(cat(toString(metabJson)))
    } else if (vtFlag == "txt") {
      newDF <- cbind(Gene = geneIdStr, jsonDF)
      return(cat(format_csv(newDF)))
    } else {
      nprint <- nrow(metDF)
      tableAttr <- paste0("id = 'Gene", geneIdStr, "Table' class=\"styled-table\"")
      return(print(
        xtable(metDF[1:nprint, ]),
        type = "html",
        include.rownames = FALSE,
        sanitize.text.function = function(x) x,
        html.table.attributes = tableAttr
      ))
    }
  }

  invisible(NULL)
}

# --------------------------------------------------------------------
# Main script entry
# --------------------------------------------------------------------
args <- commandArgs(TRUE)
species_raw   <- args[1]
geneStr_raw   <- args[2]
anatomy_raw   <- args[3]
disease_raw   <- args[4]
viewType_raw  <- args[5]

outhtml <- getMetaboliteInfoTable(
  species_raw  = species_raw,
  geneIdStr_raw = geneStr_raw,
  anatomyStr_raw = anatomy_raw,
  diseaseStr_raw = disease_raw,
  viewType_raw = viewType_raw
)
