#!/usr/bin/env Rscript
# This script generates a table of gene information (NCBI, GeneCards, KEGG, Uniprot, Marrvel).
# Call Syntax : Rscript extractGeneInfoTable.R <species> <geneIDArray> <domainName>
# Input  : species (e.g. hsa, mmu)
#        : geneArray (string of ENTREZ IDs, e.g. "3098,6120")
#        : domainName (e.g. bcdw.org)
# Output : An HTML table containing links to KEGG, GeneCards, NCBI, Ensembl, Uniprot, Marrvel

suppressPackageStartupMessages({
  library(jsonlite)
  library(dplyr)
  library(xtable)
  library(plyr)
})

# ----------------------------- Helpers --------------------------------------

build_geneinfo_url <- function(domain, species, geneArray) {
  GeneIDType <- "ENTREZID"
  USE_NCBI_GENE_INFO <- 0
  ViewType <- "json"
  IncHTML <- 1

  species_enc <- URLencode(species, reserved = TRUE)
  geneArray_enc <- URLencode(geneArray, reserved = TRUE)

  paste0(
    "https://", domain,
    "/dev/geneid/geneid_proc_selcol_GET.php", # remove dev for production code
    "?species=", species_enc,
    "&GeneListStr=", geneArray_enc,
    "&GeneIDType=", GeneIDType,
    "&USE_NCBI_GENE_INFO=", USE_NCBI_GENE_INFO,
    "&View=", ViewType,
    "&IncHTML=", IncHTML
  )
}

getGeneInfoTable <- function(orgStr, geneArray, domainName) {
  # resolve a relative path for images based on current working directory
  currDir <- paste0("/", basename(getwd()))

  url_str_gene_php <- build_geneinfo_url(domainName, orgStr, geneArray)

  GeneAllInfo <- tryCatch(
    {
      fromJSON(url_str_gene_php, simplifyVector = TRUE)
    },
    error = function(e) {
      write("ERROR: Unable to fetch or parse JSON from geneid service.", stderr())
      quit(status = 1)
    }
  )

  required_cols <- c(
    "SYMBOL",
    "HTML_KEGG",
    "HTML_SYMBOL",
    "HTML_ENTREZID",
    "HTML_ENSEMBL",
    "HTML_UNIPROT",
    "HTML_MARRVEL"
  )

  if (!all(required_cols %in% names(GeneAllInfo))) {
    write("ERROR: JSON response missing expected gene info columns.", stderr())
    quit(status = 1)
  }

  GeneAllInfo_forhtml <- unique(GeneAllInfo[required_cols])

  # collapse UNIPROT entries
  newdf <- ddply(
    GeneAllInfo_forhtml,
    .(SYMBOL, HTML_KEGG, HTML_SYMBOL, HTML_ENTREZID, HTML_ENSEMBL, HTML_MARRVEL),
    .fun = summarize,
    HTML_UNIPROT_COLLAPSED = paste0(unique(HTML_UNIPROT), collapse = ", "),
    .progress = "none",
    .inform = FALSE,
    .drop = TRUE,
    .parallel = FALSE,
    .paropts = NULL
  )
  colnames(newdf)[colnames(newdf) == "HTML_UNIPROT_COLLAPSED"] <- "HTML_UNIPROT"
  GeneAllInfo_forhtml <- newdf

  # collapse ENSEMBL entries
  newdf <- ddply(
    GeneAllInfo_forhtml,
    .(SYMBOL, HTML_KEGG, HTML_SYMBOL, HTML_ENTREZID, HTML_UNIPROT, HTML_MARRVEL),
    .fun = summarize,
    HTML_ENSEMBL_COLLAPSED = paste0(unique(HTML_ENSEMBL), collapse = ", "),
    .progress = "none",
    .inform = FALSE,
    .drop = TRUE,
    .parallel = FALSE,
    .paropts = NULL
  )
  colnames(newdf)[colnames(newdf) == "HTML_ENSEMBL_COLLAPSED"] <- "HTML_ENSEMBL"
  GeneAllInfo_forhtml <- newdf

  # build display table with logo images in header
  n <- nrow(GeneAllInfo_forhtml)
  outdf <- data.frame(matrix(ncol = 7, nrow = n), stringsAsFactors = FALSE)

  symbolStr <- "Symbol"
  keggStr <- paste0("<img src=\"", currDir, "/images/kegg4.gif\" alt=\"KEGG\" width=\"60\">")
  geneCardsStr <- paste0("<img src=\"", currDir, "/images/logo_genecards.png\" alt=\"Gene Cards\" width=\"60\">")
  ncbiStr <- paste0("<img src=\"", currDir, "/images/NCBILogo.gif\" alt=\"NCBI\" width=\"60\">")
  ensemblStr <- paste0("<img src=\"", currDir, "/images/ensembl_logo.png\" alt=\"Ensembl\" width=\"60\">")
  uniprotStr <- paste0("<img src=\"", currDir, "/images/Uniprot.png\" alt=\"Uniprot\" style=\"background-color:gray;padding:20px;\" width=\"60\">")
  marrvelStr <- paste0("<img src=\"", currDir, "/images/marrvel.png\" alt=\"Marrvel\" width=\"60\">")

  colnames(outdf) <- c(symbolStr, keggStr, geneCardsStr, ncbiStr, ensemblStr, uniprotStr, marrvelStr)

  for (i in seq_len(n)) {
    outdf[i, ] <- c(
      GeneAllInfo_forhtml$SYMBOL[i],
      GeneAllInfo_forhtml$HTML_KEGG[i],
      GeneAllInfo_forhtml$HTML_SYMBOL[i],
      GeneAllInfo_forhtml$HTML_ENTREZID[i],
      GeneAllInfo_forhtml$HTML_ENSEMBL[i],
      GeneAllInfo_forhtml$HTML_UNIPROT[i],
      GeneAllInfo_forhtml$HTML_MARRVEL[i]
    )
  }

  # print as HTML table with id='Table1' (same as original usage)
  # sanitize.text.function = identity so that HTML links are not escaped
  html <- capture.output(
    print(
      xtable(outdf, caption = NULL),
      type = "html",
      include.rownames = FALSE,
      sanitize.text.function = function(x) x,
      html.table.attributes = "class='styled-table' id='Table1'"
    )
  )

  cat(paste(html, collapse = "\n"))
}

# ----------------------------- Main ----------------------------------------

args <- commandArgs(TRUE)

if (length(args) != 3) {
  write("ERROR: Expected 3 arguments: <species> <geneIDArray> <domainName>", stderr())
  quit(status = 1)
}

species <- args[1]
geneArray <- args[2]
domainName <- args[3]

getGeneInfoTable(species, geneArray, domainName)
