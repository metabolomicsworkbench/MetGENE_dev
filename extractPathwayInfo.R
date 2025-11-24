#!/usr/bin/env Rscript
# Hardened extractPathwayInfo.R
# Generates pathway link table for a list of genes.

suppressPackageStartupMessages({
  library(xtable)
  library(utils)
})

# ---------------------------- Helper Functions ------------------------------

safe_urlencode <- function(x) {
  URLencode(as.character(x), reserved = TRUE)
}

resolve_organism <- function(sp) {
  s <- tolower(sp)
  if (s %in% c("human", "hsa", "homo sapiens"))   return("Homo+sapiens")
  if (s %in% c("mouse", "mmu", "mus musculus"))   return("Mus+musculus")
  if (s %in% c("rat", "rno", "rattus norvegicus")) return("Rattus+norvegicus")
  return("")
}

build_links <- function(currDir, speciesCode, geneId, geneSymbol, organismName) {

  geneSymbol_enc <- safe_urlencode(geneSymbol)
  geneId_enc     <- safe_urlencode(geneId)

  pc <- paste0(
    "<a href=\"https://apps.pathwaycommons.org/search?type=Pathway&q=",
    geneSymbol_enc, "\" target=\"_blank\">", geneSymbol, "</a>"
  )

  reactome <- paste0(
    "<a href=\"https://reactome.org/content/query?q=", geneSymbol_enc,
    "&species=", organismName,
    "&cluster=true\" target=\"_blank\">",
    geneSymbol, "</a>"
  )

  kegg <- paste0(
    "<a href=\"https://www.kegg.jp/entry/",
    safe_urlencode(speciesCode), ":", geneId_enc,
    "\" target=\"_blank\">", geneSymbol, "</a>"
  )

  wiki <- paste0(
    "<a href=\"https://www.wikipathways.org/search.html?query=",
    geneSymbol_enc,
    "&species=", organismName,
    "&title=Special%3ASearchPathways&doSearch=1&ids=&codes=&type=query\" target=\"_blank\">",
    geneSymbol, "</a>"
  )

  c(pc, reactome, kegg, wiki)
}

# ---------------------------- Main Table Builder -----------------------------

getPathwayInfoTable <- function(species, geneArray, geneSymbolArray, species_sci_name) {

  currDir <- paste0("/", basename(getwd()))

  # Validate lengths
  if (length(geneArray) != length(geneSymbolArray)) {
    write("ERROR: geneArray and geneSymbolArray lengths do not match.", stderr())
    quit(status = 1)
  }

  if (length(geneArray) == 0) {
    write("ERROR: No gene identifiers provided.", stderr())
    quit(status = 1)
  }

  organism_name <- resolve_organism(species)

  # Column headers (icons)
  pcStr       <- paste0("<img src=\"", currDir, "/images/pc_logo.png\" alt=\"Pathway Commons\" width=\"60\">")
  reactomeStr <- paste0("<img src=\"", currDir, "/images/reactome.png\" alt=\"Reactome\" width=\"80\">")
  keggStr     <- paste0("<img src=\"", currDir, "/images/kegg4.gif\" alt=\"KEGG\" width=\"60\">")
  wikiStr     <- paste0("<img src=\"", currDir, "/images/wikipathways.PNG\" alt=\"Wiki Pathways\" width=\"60\">")

  newdf <- data.frame(
    matrix(ncol = 4, nrow = length(geneArray)),
    stringsAsFactors = FALSE
  )
  colnames(newdf) <- c(pcStr, reactomeStr, keggStr, wikiStr)

  # Fill rows
  for (i in seq_along(geneArray)) {
    links <- build_links(
      currDir       = currDir,
      speciesCode   = species,
      geneId        = geneArray[i],
      geneSymbol    = geneSymbolArray[i],
      organismName  = organism_name
    )
    newdf[i, ] <- links
  }

  # Convert to HTML
  html <- capture.output(
    print(
      xtable(newdf),
      type = "html",
      include.rownames = FALSE,
      sanitize.text.function = function(x) x,  # Do NOT escape HTML
      html.table.attributes = "class='styled-table' id='Table1'"
    )
  )

  cat(paste(html, collapse = "\n"))
}

# ------------------------------- MAIN ----------------------------------------

args <- commandArgs(TRUE)

if (length(args) != 4) {
  write("ERROR: Usage: extractPathwayInfo.R <species> <geneIDArray> <geneSymArray> <speciesSciName>", stderr())
  quit(status = 1)
}

species    <- args[1]
geneArray  <- strsplit(args[2], ",", fixed = TRUE)[[1]]
geneSymbol <- strsplit(args[3], ",", fixed = TRUE)[[1]]
speciesSci <- args[4]

getPathwayInfoTable(species, geneArray, geneSymbol, speciesSci)
