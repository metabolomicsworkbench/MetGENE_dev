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

# SECURITY: Load centralized validation + normalization helpers
source("metgene_common.R")

# set flag for precompute
source("setPrecompute.R")
source("refmet_convert_faster_fun.R")

###############################################################
# Helper: find common reactions for a metabolite
###############################################################
getRxnsContainingMetabolite <- function(rxnList, metdf) {
  metRxns <- metdf$REACTION
  metRxnsArr <- unlist(metRxns)
  combined_metRxns <- paste(metRxnsArr, collapse = " ")
  metRxns <- strsplit(combined_metRxns, " ")[[1]]
  rxnList[rxnList %in% metRxns]
}

###############################################################
# Helper: retrieve KEGG reactions + compounds for one gene
###############################################################
getRxnIDsAndCpdIDsFromKEGG <- function(queryStr) {
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

  ec_number <- regmatches(enzyme, regexpr("EC:\\d+\\.\\d+\\.\\d+\\.\\d+", enzyme))
  if (length(ec_number) == 0) {
    stop("No EC number found in ORTHOLOGY field for ", queryStr, call. = FALSE)
  }

  ec_number <- tolower(ec_number)

  rxns <- tryCatch(
    keggLink("reaction", ec_number),
    error = function(e) {
      stop("keggLink(reaction) failed for ", ec_number, ": ", e$message, call. = FALSE)
    }
  )

  rxn_vec <- unname(as.vector(rxns))
  rxn_df <- data.frame(
    Type = paste("reaction", seq_along(rxn_vec)),
    ID = rxn_vec,
    stringsAsFactors = FALSE
  )

  cpds <- tryCatch(
    keggLink("compound", ec_number),
    error = function(e) {
      stop("keggLink(compound) failed for ", ec_number, ": ", e$message, call. = FALSE)
    }
  )

  cpd_vec <- unname(as.vector(cpds))
  cpd_df <- data.frame(
    Type = paste("compound", seq_along(cpd_vec)),
    ID = cpd_vec,
    stringsAsFactors = FALSE
  )

  rbind(rxn_df, cpd_df)
}

###############################################################
# Main extraction function (SECURITY HARDENED)
###############################################################
getMetaboliteInfoTable <- function(species_raw,
                                   geneIdStr_raw,
                                   anatomyStr_raw,
                                   diseaseStr_raw,
                                   viewType_raw) {
  # ---------------------------
  # SECURITY: Normalize species (PHP-like)
  # ---------------------------
  sp <- normalize_species(species_raw)
  species <- sp$species_code # hsa/mmu/rno
  organism_name <- sp$species_label # Human/Mouse/Rat

  # ---------------------------
  # SECURITY: Load curated validation lists
  # ---------------------------
  allowed_diseases <- load_allowed_diseases("disease_pulldown_menu_cascaded.json")
  allowed_anatomy <- load_allowed_anatomy("ssdm_sample_source_pulldown_menu.html")

  # ---------------------------
  # SECURITY: Sanitize gene ID (using validate_entrez_ids for strict validation)
  # ---------------------------
  geneIdStr <- validate_entrez_ids(geneIdStr_raw)

  # ---------------------------
  # SECURITY: Validate disease / anatomy
  # ---------------------------
  diseaseStr <- validate_disease(diseaseStr_raw, allowed_diseases)
  anatomyStr <- validate_anatomy(anatomyStr_raw, allowed_anatomy)

  if (identical(diseaseStr, "NA")) diseaseStr <- ""
  if (identical(anatomyStr, "NA")) anatomyStr <- ""

  # ---------------------------
  # SECURITY: Validate output type
  # ---------------------------
  viewType <- safe_view_type(viewType_raw)

  # SECURITY: Safe current directory reference
  currDir <- paste0("/", basename(getwd()))

  # ---------------------------
  # Dataframes to fill
  # ---------------------------
  metabRxnList <- data.frame(
    KEGG_COMPOUND_ID = character(),
    REFMET_NAME = character(),
    KEGG_REACTION_ID = character(),
    METSTAT_LINK = character(),
    stringsAsFactors = FALSE
  )

  jsonDF <- data.frame(
    KEGG_COMPOUND_ID = character(),
    REFMET_NAME = character(),
    KEGG_REACTION_ID = character(),
    METSTAT_LINK = character(),
    stringsAsFactors = FALSE
  )

  # ---------------------------
  # Build KEGG query
  # ---------------------------
  queryStr <- paste0(species, ":", geneIdStr)

  if (preCompute == 1) {
    # SECURITY: Use safe_read_rds from metgene_common.R
    all_df <- safe_read_rds(species, "_keggLink_mg.RDS", base_dir = "data")
    df <- subset(all_df, org_ezid == queryStr)
  } else {
    df <- getRxnIDsAndCpdIDsFromKEGG(queryStr)
  }

  cpds <- df[str_detect(df[, 2], "cpd:"), 2]
  rxns <- df[str_detect(df[, 2], "rn:"), 2]

  metabList <- gsub("cpd:", "", cpds)
  reactionsList <- gsub("rn:", "", rxns)

  # SECURITY: URL encode anatomy and disease for MW REST API
  # Note: MW REST API uses + for spaces, %2b for literal +
  anatomyQryStr <- anatomyStr
  if (anatomyStr != "" && str_detect(anatomyStr, " ")) {
    anatomyQryStr <- str_replace_all(anatomyStr, " ", "+")
  }

  diseaseQryStr <- diseaseStr
  if (diseaseStr != "" && str_detect(diseaseStr, " ")) {
    diseaseQryStr <- str_replace_all(diseaseStr, " ", "+")
  }

  # ---------------------------
  # Retrieve KEGG compound metadata
  # ---------------------------
  if (preCompute == 1) {
    # SECURITY: Use safe_read_rds from metgene_common.R
    cpdInfodf <- safe_read_rds(species, "_keggGet_cpdInfo.RDS", base_dir = "data")
    metRespdf <- cpdInfodf[rownames(cpdInfodf) %in% metabList, ]
  } else {
    query_split <- split(metabList, ceiling(seq_along(metabList) / 10))

    info <- llply(query_split, function(x) {
      tryCatch(
        keggGet(x),
        error = function(e) {
          warning(
            "keggGet failed for compounds: ", paste(x, collapse = ", "),
            " - ", e$message
          )
          return(NULL)
        }
      )
    })

    # Filter out NULL results from failed queries
    info <- Filter(Negate(is.null), info)

    if (length(info) == 0) {
      stop("Failed to retrieve any compound information from KEGG", call. = FALSE)
    }

    unlist_info <- unlist(info, recursive = FALSE)
    extract_info <- lapply(unlist_info, "[", c("ENTRY", "NAME", "REACTION"))
    dd <- do.call(rbind, extract_info)
    metRespdf <- as.data.frame(dd)
    rownames(metRespdf) <- metRespdf$ENTRY
  }

  # ---------------------------
  # Compute per-metabolite results
  # ---------------------------
  if (length(metabList) > 0) {
    allrefmetDF <- refmet_convert_fun(as.data.frame(metabList))

    for (m in seq_along(metabList)) {
      metabStr <- metabList[[m]]

      # SECURITY: Escape metabolite ID for HTML
      metabURLStr <- paste0(
        "<a href=\"https://www.kegg.jp/entry/",
        html_escape(metabStr),
        "\" target=\"_blank\">",
        html_escape(metabStr),
        "</a>"
      )

      metqryDF <- metRespdf[grep(metabStr, rownames(metRespdf)), ]
      metabRxns <- getRxnsContainingMetabolite(reactionsList, metqryDF)

      mwDF <- subset(allrefmetDF, Input.name == metabStr)

      # -------------------------------------------
      # (A) RefMet matches exist
      # -------------------------------------------
      if (!is.null(mwDF) && nrow(mwDF) > 0) {
        refmetIdVals <- unique(mwDF$Standardized.name)

        for (refmetId in refmetIdVals) {
          # SECURITY: URL encode RefMet name for MW URLs
          refmetQryStr <- gsub("\\+", "%2b", refmetId, fixed = FALSE)
          refmetQryStr <- gsub(" ", "+", refmetQryStr, fixed = TRUE)

          # SECURITY: Escape RefMet name for HTML display
          refmetName <- paste0(
            "<a href=\"https://www.metabolomicsworkbench.org/databases/refmet/refmet_details.php?REFMET_NAME=",
            refmetQryStr,
            "\" target=\"_blank\"> ",
            html_escape(refmetId),
            "</a>"
          )

          metabRxnsStr <- paste(metabRxns, collapse = ", ")

          # SECURITY: Escape reaction IDs for HTML
          rxnsURLs <- paste0(
            "<a href=\"https://www.genome.jp/entry/rn:",
            html_escape(metabRxns),
            "\" target=\"_blank\">",
            html_escape(metabRxns),
            "</a>"
          )
          rxnsURLStr <- paste(rxnsURLs, collapse = ", ")

          # Build MetStat link
          metStatLink <- paste0(
            "http://www.metabolomicsworkbench.org/data/metstat_hist.php?NAME_PREP1=Is",
            "&refmet_name_search=", refmetQryStr,
            "&refmet_name=", refmetQryStr,
            "&SPECIES=", organism_name,
            "&DISEASE=", diseaseQryStr,
            "&SOURCE=", anatomyQryStr
          )

          # SECURITY: Escape metStatLink for HTML attribute
          metStatStr <- paste0(
            "<a href=\"", html_escape(metStatLink),
            "&rows_to_display=1\" target=\"_blank\">",
            "<img src=\"", html_escape(currDir),
            "/images/statSymbolIcon.png\" alt=\"", metStatLink, "\" width=\"30\"></a>"
          )

          metabRxnList[nrow(metabRxnList) + 1, ] <-
            c(metabURLStr, refmetName, rxnsURLStr, metStatStr)

          jsonDF[nrow(jsonDF) + 1, ] <-
            c(metabStr, refmetId, metabRxnsStr, metStatLink)
        }
      } else {
        # -------------------------------------------
        # (B) No RefMet match → fallback to KEGG name
        # -------------------------------------------
        refName <- unique(metqryDF$NAME)
        refName <- gsub(";$", "", refName)

        # SECURITY: Escape KEGG compound name for HTML
        refmetName <- paste0(
          "<a href=\"https://www.genome.jp/entry/cpd:",
          html_escape(metabStr),
          "\" target=\"_blank\"> ",
          html_escape(refName),
          "</a>"
        )

        metabRxnsStr <- paste(metabRxns, collapse = ", ")

        # SECURITY: Escape reaction IDs for HTML
        rxnsURLs <- paste0(
          "<a href=\"https://www.genome.jp/entry/rn:",
          html_escape(metabRxns),
          "\" target=\"_blank\">",
          html_escape(metabRxns),
          "</a>"
        )
        rxnsURLStr <- paste(rxnsURLs, collapse = ", ")

        metabRxnList[nrow(metabRxnList) + 1, ] <-
          c(metabURLStr, refmetName, rxnsURLStr, "")

        jsonDF[nrow(jsonDF) + 1, ] <-
          c(metabStr, refName, metabRxnsStr, "")
      }
    }
  }

  # ---------------------------
  # Output section
  # ---------------------------
  if (viewType %in% c("json", "jsonfile")) {
    newDF <- cbind(Gene = geneIdStr, jsonDF)
    metabJson <- toJSON(x = newDF, pretty = TRUE)
    return(cat(metabJson))
  }

  if (viewType == "txt") {
    newDF <- cbind(Gene = geneIdStr, jsonDF)
    return(cat(format_csv(newDF)))
  }

  # HTML table output
  if (nrow(metabRxnList) == 0) {
    # No results - return empty table or message
    cat("<p>No metabolites found for this gene.</p>")
    return(invisible(NULL))
  }

  nprint <- nrow(metabRxnList)

  # SECURITY: Sanitize gene ID for HTML attribute
  safe_gene <- gsub("[^A-Za-z0-9_-]", "_", geneIdStr)
  tableAttr <- paste0('id="Gene', safe_gene, 'Table" class="styled-table"')

  print(
    xtable(metabRxnList[1:nprint, ]),
    type = "html",
    include.rownames = FALSE,
    sanitize.text.function = function(x) x, # Don't double-escape (already escaped above)
    html.table.attributes = tableAttr
  )

  invisible(NULL)
}

###############################################################
# Main script entry
###############################################################
args <- commandArgs(TRUE)

# SECURITY: Validate argument count
if (length(args) < 5) {
  write(
    "Usage: extractMetaboliteInfo.R <species> <geneStr> <anatomyStr> <diseaseStr> <viewType>",
    stderr()
  )
  quit(status = 1)
}

species_raw <- args[1]
geneStr_raw <- args[2]
anatomy_raw <- args[3]
disease_raw <- args[4]
viewType_raw <- args[5]

# SECURITY: Wrap in tryCatch for error handling
tryCatch(
  {
    outhtml <- getMetaboliteInfoTable(
      species_raw     = species_raw,
      geneIdStr_raw   = geneStr_raw,
      anatomyStr_raw  = anatomy_raw,
      diseaseStr_raw  = disease_raw,
      viewType_raw    = viewType_raw
    )
  },
  error = function(e) {
    write(paste("ERROR:", e$message), stderr())
    quit(status = 1)
  }
)
