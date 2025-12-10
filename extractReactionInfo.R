#!/usr/bin/env Rscript
# THis script obtains the reaction information pertaining to the gene input
# Call syntax : Rscript extractReactionInfo.R <species> <geneStr> <viewType>
# Input: species e.g. hsa, mmu
#        geneStr : ENTREZID of genes e.g. 3098, 6120
#        viewType : e.g. json, txt
# Output: A table in json or txt format comprising of reaction information
#         or html table (if viewType is neither json or txt).
#         The table contains KEGG_REACTION_ID, KEGG_REACTION_NAME KEGG_REACTION_EQUATION
#
################################################
# Restrictions due to the use of KEGG APIs (https://www.kegg.jp/kegg/legal.html see also https://www.pathway.jp/en/academic.html)
# * Using this code to provide user's own web service. The code we provide is free for non-commercial use (see LICENSE).
# While it is our understanding that no KEGG license is required to run the web app on user's local computer for personal
#  use (e.g., access as localhost:install_location_with respect to_DocumentRoot/MetGENE, or, restrict its access to the
# IP addresses belonging to their own research group), the users must understand the KEGG license terms
# (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html)
# and decide for themselves. For example, if the user wishes to provide this tool (or their own tool based on a
# subset of MetGENE scripts with KEGG APIs) as a service (see LICENSE), they must obtain their own KEGG license with suitable rights.
# * Faster version of MetGENE
# If and only if the user has purchased license for KEGG FTP Data, they can activate a 'preCompute' mode to run faster version of MetGENE.
# To achieve this, please set preCompute = 1 in the file
# setPrecompute.R. Otherwise, please ensure that preCompute is set to 0
# in the file setPrecompute.R. Further, to use the faster version,
# the user needs to run the R scripts in the 'data' folder first.
# Please see the respective R files in the 'data' folder for instructions
# to run them.
# Please see the files README.md and LICENSE for more details.
################################################

suppressPackageStartupMessages({
  library(KEGGREST)
  library(stringr)
  library(data.table)
  library(xtable)
  library(jsonlite)
  library(tidyverse)
  library(plyr)
  library(tictoc)
})

# SECURITY: Load common validation functions
source("metgene_common.R")

# Load the preCompute flag
source("setPrecompute.R")

# -------------------------- Helper Functions -----------------------------

getRxnIDsFromKEGG <- function(queryStr) {
  res <- tryCatch(keggGet(queryStr), error = function(e) {
    stop("KEGG query failed for ", queryStr, ": ", e$message, call. = FALSE)
  })

  if (length(res) == 0 || is.null(res[[1]])) {
    stop("No KEGG entry found for ", queryStr, call. = FALSE)
  }

  entry <- res[[1]]
  if (is.null(entry$ORTHOLOGY) || length(entry$ORTHOLOGY) < 1) {
    stop("No ORTHOLOGY for ", queryStr, call. = FALSE)
  }

  enzyme <- entry$ORTHOLOGY[[1]]
  ec_number <- regmatches(enzyme, regexpr("EC:\\d+\\.\\d+\\.\\d+\\.\\d+", enzyme))

  if (length(ec_number) == 0) {
    stop("No EC number for ", queryStr, call. = FALSE)
  }

  ec_number <- tolower(ec_number)

  rxns <- tryCatch(keggLink("reaction", ec_number), error = function(e) {
    stop("keggLink failed for ", ec_number, ": ", e$message, call. = FALSE)
  })

  if (length(rxns) == 0) {
    stop("No reactions linked to EC ", ec_number, call. = FALSE)
  }

  rxn_vec <- unname(as.vector(rxns))
  reaction_labels <- paste0("reaction", seq_along(rxn_vec))

  data.frame(
    Reaction = reaction_labels,
    Reaction_ID = rxn_vec,
    stringsAsFactors = FALSE
  )
}

# ---------------------- Main Reaction Processing -------------------------

getReactionInfoTable <- function(orgStr, geneIdStr, viewType) {
  queryStr <- paste0(orgStr, ":", geneIdStr)

  # Step 1: Get reaction IDs
  if (preCompute == 1) {
    # SECURITY: Use safe_read_rds from metgene_common.R
    all_df <- safe_read_rds(orgStr, "_keggLink_mg.RDS", base_dir = "data")
    df <- subset(all_df, org_ezid == queryStr)

    if (nrow(df) == 0) {
      stop("No precomputed data for ", queryStr, call. = FALSE)
    }
  } else {
    df <- getRxnIDsFromKEGG(queryStr)
  }

  rxn_col <- df[, 2]
  rxns <- rxn_col[str_detect(rxn_col, "rn:")]
  if (length(rxns) == 0) stop("No rn:xxx reactions for ", queryStr)

  rxnList <- unique(gsub("^rn:", "", rxns))

  if (preCompute == 1) {
    # SECURITY: Use safe_read_rds from metgene_common.R
    rxnInfodf <- safe_read_rds(orgStr, "_keggGet_rxnInfo.RDS", base_dir = "data")
    rxndf <- rxnInfodf[rownames(rxnInfodf) %in% rxnList, , drop = FALSE]

    if (nrow(rxndf) == 0) stop("No precomputed reaction info found.")
  } else {
    query_split <- split(rxnList, ceiling(seq_along(rxnList) / 10))

    info <- llply(query_split, function(x) {
      tryCatch(keggGet(as.vector(x)),
        error = function(e) {
          stop("keggGet failed for: ", paste(x, collapse = ","), call. = FALSE)
        }
      )
    })

    unlist_info <- unlist(info, recursive = FALSE)

    extract_info <- lapply(unlist_info, function(entry) {
      list(
        ENTRY      = if (!is.null(entry$ENTRY)) entry$ENTRY else "",
        NAME       = if (!is.null(entry$NAME)) paste(entry$NAME, collapse = "; ") else "",
        DEFINITION = if (!is.null(entry$DEFINITION)) entry$DEFINITION else ""
      )
    })

    rxndf <- as.data.frame(do.call(rbind, extract_info), stringsAsFactors = FALSE)
    rxndf <- data.frame(lapply(rxndf, function(x) gsub("\"", "", x)))
  }

  colnames(rxndf) <- c("KEGG_REACTION_ID", "KEGG_REACTION_NAME", "KEGG_REACTION_EQN")

  # SECURITY: Use html_escape from metgene_common.R
  rxndf$KEGG_REACTION_URL <- paste0(
    "<a href=\"https://www.genome.jp/entry/rn:",
    html_escape(rxndf$KEGG_REACTION_ID), "\" target=\"_blank\">",
    html_escape(rxndf$KEGG_REACTION_ID), "</a>"
  )

  # JSON output
  if (viewType == "json") {
    newdf <- cbind(Gene = geneIdStr, rxndf[, 1:3])
    cat(toJSON(newdf, pretty = TRUE))
    return(invisible(NULL))
  }

  # TXT output
  if (viewType == "txt") {
    newdf <- cbind(Gene = geneIdStr, rxndf[, 1:3])
    cat(format_csv(newdf))
    return(invisible(NULL))
  }

  # HTML table output
  # SECURITY: Escape NAME and EQN fields
  newdf <- data.frame(
    KEGG_REACTION_ID = rxndf$KEGG_REACTION_URL, # Already contains escaped HTML
    KEGG_REACTION_NAME = html_escape(rxndf$KEGG_REACTION_NAME),
    KEGG_REACTION_EQN = html_escape(rxndf$KEGG_REACTION_EQN),
    stringsAsFactors = FALSE
  )

  # SECURITY: Sanitize gene ID for use in HTML attribute
  safe_gene <- gsub("[^A-Za-z0-9_-]", "_", geneIdStr)
  tableAttr <- paste0('id="Gene', safe_gene, 'Table" class="styled-table"')

  html <- capture.output(
    print(
      xtable(newdf),
      type = "html",
      include.rownames = FALSE,
      sanitize.text.function = function(x) x, # Don't double-escape
      html.table.attributes = tableAttr
    )
  )
  cat(paste(html, collapse = "\n"))
  invisible(NULL)
}

# ------------------------------- MAIN --------------------------------------

args <- commandArgs(TRUE)

if (length(args) < 3) {
  write("Usage: extractReactionInfo.R <species> <geneStr> <viewType>", stderr())
  quit(status = 1)
}

# SECURITY: Validate ALL inputs using metgene_common.R functions
species_info <- normalize_species(args[1])
species <- species_info$species_code

geneStr <- validate_entrez_ids(args[2])

viewType <- safe_view_type(args[3])

# Execute main function
getReactionInfoTable(species, geneStr, viewType)
