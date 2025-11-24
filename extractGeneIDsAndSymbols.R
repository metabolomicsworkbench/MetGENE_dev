#!/usr/bin/env Rscript

suppressPackageStartupMessages({
  library(jsonlite)
  library(dplyr)
})

# -------------------------------
# SAFE URL BUILDER
# -------------------------------
safe_url <- function(domain, species, geneType, genes) {

  # encode each parameter safely
  genes_enc   <- URLencode(genes, reserved = TRUE)
  species_enc <- URLencode(species, reserved = TRUE)
  type_enc    <- URLencode(geneType, reserved = TRUE)
  
  USE_NCBI_GENE_INFO <- 0
  
  paste0(
    "https://", domain,
    "/geneid/rest/species/", species_enc,
    "/GeneIDType/", type_enc,
    "/GeneListStr/", genes_enc,
    "/USE_NCBI_GENE_INFO/", USE_NCBI_GENE_INFO,
    "/View/json"
  )
}

# -------------------------------
# MAIN FUNCTION (HARDENED)
# -------------------------------
getGeneSymbolEntrezIDs <- function(orgStr, geneInfoArray, geneIDType, domainName) {

  # normalize whitespace
  geneInfoArray <- gsub(" ", "%20", geneInfoArray)

  url <- safe_url(domainName, orgStr, geneIDType, geneInfoArray)

  # fetch with error handling
  GeneAllInfo <- tryCatch(
    {
      fromJSON(url, simplifyVector = TRUE)
    },
    error = function(e) {
      write("ERROR: Unable to fetch or parse JSON from remote API.", stderr())
      quit(status = 1)
    }
  )

  # ensure expected fields exist
  if (!all(c("SYMBOL", "ENTREZID") %in% names(GeneAllInfo))) {
    write("ERROR: JSON response missing expected fields.", stderr())
    quit(status = 1)
  }

  newdf <- unique(GeneAllInfo[c("SYMBOL", "ENTREZID")])

  # prepare output identical to original behavior
  out <- toString(c(newdf$SYMBOL, newdf$ENTREZID))
  
  cat(out) # safe: outputs only final string
}

# -------------------------------
# ENTRY POINT
# -------------------------------
args <- commandArgs(TRUE)

if (length(args) != 4) {
  write("ERROR: Expected 4 arguments: <species> <geneInfoArray> <geneIDType> <domainName>", stderr())
  quit(status = 1)
}

species      <- args[1]
geneInfo     <- args[2]
geneType     <- args[3]
domainName   <- args[4]

getGeneSymbolEntrezIDs(species, geneInfo, geneType, domainName)
