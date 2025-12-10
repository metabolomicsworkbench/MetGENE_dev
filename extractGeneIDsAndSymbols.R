#!/usr/bin/env Rscript

suppressPackageStartupMessages({
  library(jsonlite)
  library(dplyr)
})

# SECURITY: Load centralized validation + normalization helpers
source("metgene_common.R")

# -------------------------------
# SAFE URL BUILDER
# -------------------------------
safe_url <- function(domain, species, geneType, genes) {
  # SECURITY: Validate domain to prevent SSRF attacks
  # Only allow specific known domains
  allowed_domains <- c(
    "localhost",
    "127.0.0.1",
    "sc-cfdewebdev.sdsc.edu",
    "www.metabolomicsworkbench.org",
    "metabolomicsworkbench.org",
    "bdcw.org",
    "www.bdcw.org"
  )

  # Remove protocol if present
  domain_clean <- gsub("^https?://", "", domain)
  domain_clean <- gsub("/.*$", "", domain_clean) # Remove any path

  # Check if domain is in whitelist
  if (!domain_clean %in% allowed_domains) {
    stop("SECURITY: Invalid domain. Only whitelisted domains are allowed.", call. = FALSE)
  }

  # encode each parameter safely
  genes_enc <- URLencode(genes, reserved = TRUE)
  species_enc <- URLencode(species, reserved = TRUE)
  type_enc <- URLencode(geneType, reserved = TRUE)

  USE_NCBI_GENE_INFO <- 0

  paste0(
    "https://", domain_clean,
    "/dev/geneid/rest/species/", species_enc,
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
  # SECURITY: Normalize species (from metgene_common.R)
  sp <- normalize_species(orgStr)
  species_code <- sp$species_code # hsa/mmu/rno

  # SECURITY: Validate gene ID type (from metgene_common.R)
  geneIDType_validated <- validate_gene_id_type(geneIDType)

  # SECURITY: Sanitize gene info string (from metgene_common.R)
  geneInfo_clean <- sanitize_gene_info(geneInfoArray)

  # normalize whitespace
  geneInfoArray <- gsub(" ", "%20", geneInfo_clean)

  url <- safe_url(domainName, species_code, geneIDType_validated, geneInfoArray)

  # fetch with error handling
  GeneAllInfo <- tryCatch(
    {
      # SECURITY: Set timeout for API call (30 seconds)
      old_timeout <- getOption("timeout")
      options(timeout = 30)

      result <- fromJSON(url, simplifyVector = TRUE)

      # Restore original timeout
      options(timeout = old_timeout)

      result
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

  # SECURITY: Validate that we got data
  if (nrow(newdf) == 0) {
    write("ERROR: No valid gene data returned from API.", stderr())
    quit(status = 1)
  }

  # SECURITY: Sanitize symbols (remove any potential injection characters)
  newdf$SYMBOL <- gsub("[^A-Za-z0-9._-]", "", newdf$SYMBOL)

  # SECURITY: Validate ENTREZ IDs (should be numeric)
  if (!all(grepl("^[0-9]+$", newdf$ENTREZID))) {
    # Filter to keep only valid numeric ENTREZ IDs
    newdf <- newdf[grepl("^[0-9]+$", newdf$ENTREZID), ]

    # Check if we still have data after filtering
    if (nrow(newdf) == 0) {
      write("ERROR: No valid ENTREZ IDs after validation.", stderr())
      quit(status = 1)
    }
  }

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

species <- args[1]
geneInfo <- args[2]
geneType <- args[3]
domainName <- args[4]

# SECURITY: Wrap main execution in tryCatch
tryCatch(
  {
    getGeneSymbolEntrezIDs(species, geneInfo, geneType, domainName)
  },
  error = function(e) {
    write(paste("ERROR:", e$message), stderr())
    quit(status = 1)
  }
)
