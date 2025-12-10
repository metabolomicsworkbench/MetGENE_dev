#!/usr/bin/env Rscript
# Hardened extractPathwayInfo.R
# Generates pathway link table for a list of genes.

suppressPackageStartupMessages({
  library(xtable)
  library(utils)
})

# SECURITY: Load centralized validation + normalization helpers
source("metgene_common.R")

# ---------------------------- Helper Functions ------------------------------

safe_urlencode <- function(x) {
  # SECURITY: Validate input before encoding
  if (is.null(x) || length(x) == 0) {
    return("")
  }

  x_str <- as.character(x)

  # SECURITY: Check for reasonable length
  if (nchar(x_str) > 200) {
    warning("Input too long for URL encoding, truncating to 200 chars")
    x_str <- substr(x_str, 1, 200)
  }

  URLencode(x_str, reserved = TRUE)
}

build_links <- function(currDir, speciesCode, geneId, geneSymbol, organismName) {
  # SECURITY: Escape all values for HTML output
  geneSymbol_safe <- html_escape(geneSymbol)
  geneId_safe <- html_escape(geneId)

  # SECURITY: URL encode for use in URLs
  geneSymbol_enc <- safe_urlencode(geneSymbol)
  geneId_enc <- safe_urlencode(geneId)
  speciesCode_enc <- safe_urlencode(speciesCode)

  # SECURITY: Validate currDir doesn't contain path traversal attempts
  currDir_clean <- gsub("\\.\\.", "", currDir) # Remove ..
  currDir_clean <- gsub("^/+", "/", currDir_clean) # Remove multiple leading slashes
  currDir_safe <- html_escape(currDir_clean)

  pc <- paste0(
    "<a href=\"https://apps.pathwaycommons.org/search?type=Pathway&q=",
    geneSymbol_enc, "\" target=\"_blank\" rel=\"noopener noreferrer\">",
    geneSymbol_safe, "</a>"
  )

  reactome <- paste0(
    "<a href=\"https://reactome.org/content/query?q=", geneSymbol_enc,
    "&species=", organismName,
    "&cluster=true\" target=\"_blank\" rel=\"noopener noreferrer\">",
    geneSymbol_safe, "</a>"
  )

  kegg <- paste0(
    "<a href=\"https://www.kegg.jp/entry/",
    speciesCode_enc, ":", geneId_enc,
    "\" target=\"_blank\" rel=\"noopener noreferrer\">",
    geneSymbol_safe, "</a>"
  )

  wiki <- paste0(
    "<a href=\"https://www.wikipathways.org/search.html?query=",
    geneSymbol_enc,
    "&species=", organismName,
    "&title=Special%3ASearchPathways&doSearch=1&ids=&codes=&type=query\" target=\"_blank\" rel=\"noopener noreferrer\">",
    geneSymbol_safe, "</a>"
  )

  c(pc, reactome, kegg, wiki)
}

# ---------------------------- Main Table Builder -----------------------------

getPathwayInfoTable <- function(species, geneArray, geneSymbolArray, species_sci_name) {
  # SECURITY: Normalize species using metgene_common.R
  sp_info <- normalize_species(species)
  species_code <- sp_info$species_code # hsa/mmu/rno

  # SECURITY: Get URL-encoded organism name from metgene_common.R
  organism_name <- get_species_url_name(species) # ← Use centralized function

  # SECURITY: Safe current directory reference
  currDir <- paste0("/", basename(getwd()))

  # Validate lengths
  if (length(geneArray) != length(geneSymbolArray)) {
    stop("ERROR: geneArray and geneSymbolArray lengths do not match.", call. = FALSE)
  }

  if (length(geneArray) == 0) {
    stop("ERROR: No gene identifiers provided.", call. = FALSE)
  }

  # SECURITY: Validate array sizes to prevent DoS
  if (length(geneArray) > 1000) {
    stop("ERROR: Too many genes (max 1000). Please split into smaller batches.", call. = FALSE)
  }

  # Column headers (icons)
  # SECURITY: Escape currDir for HTML
  currDir_safe <- html_escape(currDir)

  pcStr <- paste0("<img src=\"", currDir_safe, "/images/pc_logo.png\" alt=\"Pathway Commons\" width=\"60\">")
  reactomeStr <- paste0("<img src=\"", currDir_safe, "/images/reactome.png\" alt=\"Reactome\" width=\"80\">")
  keggStr <- paste0("<img src=\"", currDir_safe, "/images/kegg4.gif\" alt=\"KEGG\" width=\"60\">")
  wikiStr <- paste0("<img src=\"", currDir_safe, "/images/wikipathways.PNG\" alt=\"Wiki Pathways\" width=\"60\">")

  newdf <- data.frame(
    matrix(ncol = 4, nrow = length(geneArray)),
    stringsAsFactors = FALSE
  )
  colnames(newdf) <- c(pcStr, reactomeStr, keggStr, wikiStr)

  # Fill rows
  for (i in seq_along(geneArray)) {
    # SECURITY: Validate each gene ID and symbol
    gene_id <- trimws(as.character(geneArray[i]))
    gene_sym <- trimws(as.character(geneSymbolArray[i]))

    # SECURITY: Skip empty entries
    if (gene_id == "" || gene_sym == "") {
      warning("Skipping empty gene at position ", i)
      next
    }

    # SECURITY: Validate gene ID format (alphanumeric, underscore, hyphen, period)
    if (!grepl("^[A-Za-z0-9._-]+$", gene_id)) {
      warning("Invalid gene ID at position ", i, ": ", gene_id)
      next
    }

    # SECURITY: Validate gene symbol format
    if (!grepl("^[A-Za-z0-9._-]+$", gene_sym)) {
      warning("Invalid gene symbol at position ", i, ": ", gene_sym)
      next
    }

    links <- build_links(
      currDir       = currDir,
      speciesCode   = species_code,
      geneId        = gene_id,
      geneSymbol    = gene_sym,
      organismName  = organism_name
    )
    newdf[i, ] <- links
  }

  # SECURITY: Check if we have any valid data
  if (all(is.na(newdf[, 1]))) {
    stop("ERROR: No valid gene data after validation.", call. = FALSE)
  }

  # Convert to HTML
  html <- capture.output(
    print(
      xtable(newdf),
      type = "html",
      include.rownames = FALSE,
      sanitize.text.function = function(x) x, # Do NOT double-escape (already escaped above)
      html.table.attributes = "class='styled-table' id='Table1'"
    )
  )

  cat(paste(html, collapse = "\n"))
  invisible(NULL)
}

# ------------------------------- MAIN ----------------------------------------

args <- commandArgs(TRUE)

# SECURITY: Validate argument count
if (length(args) != 4) {
  write("ERROR: Usage: extractPathwayInfo.R <species> <geneIDArray> <geneSymArray> <speciesSciName>", stderr())
  quit(status = 1)
}

species <- args[1]
geneIDStr <- args[2]
geneSymStr <- args[3]
speciesSci <- args[4]

# SECURITY: Validate inputs before splitting
if (is.null(geneIDStr) || geneIDStr == "") {
  write("ERROR: Gene ID array cannot be empty.", stderr())
  quit(status = 1)
}

if (is.null(geneSymStr) || geneSymStr == "") {
  write("ERROR: Gene symbol array cannot be empty.", stderr())
  quit(status = 1)
}

# Parse arrays
geneArray <- strsplit(geneIDStr, ",", fixed = TRUE)[[1]]
geneSymbol <- strsplit(geneSymStr, ",", fixed = TRUE)[[1]]

# SECURITY: Trim whitespace from all entries
geneArray <- trimws(geneArray)
geneSymbol <- trimws(geneSymbol)

# SECURITY: Remove empty entries
geneArray <- geneArray[geneArray != ""]
geneSymbol <- geneSymbol[geneSymbol != ""]

# SECURITY: Validate we still have data
if (length(geneArray) == 0 || length(geneSymbol) == 0) {
  write("ERROR: No valid gene identifiers after parsing.", stderr())
  quit(status = 1)
}

# SECURITY: Wrap main execution in tryCatch
tryCatch(
  {
    getPathwayInfoTable(species, geneArray, geneSymbol, speciesSci)
  },
  error = function(e) {
    write(paste("ERROR:", e$message), stderr())
    quit(status = 1)
  }
)
