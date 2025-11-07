#!/usr/bin/env Rscript
## This function takes an array of gene ENTREZ ids and returns corresponding gene symbols as a single string with gene symbols separated by comma.
# Call syntax : RScript extractGeneIDsAndSymbols.R <species> <geneInfoArray> <geneIDType> <domainName>
# Input: species (e.g. hsa, mmu)
#        geneInfoArray: array or ENTREZ IDs or SYMBOLS e.g. c(3098, 6120) or c(HK1, RPE)
#        geneIDType : e.g. one of ENTREZID, SYMBOL, SYMBOL_OR_ALIAS, ENSEMBL, REFSEQ, UNIROT
#        domainName : web domain address for the geneID REST API service
# Output : An array of different types of gene IDs e.g. ENTRZID, SYMBOL, etc.

library(utils)
library(xtable)
library(textutils)
library(jsonlite)
library(tuple)
library(tidyr)
library(tidyverse)



getGeneSymbolEntrezIDs <- function(orgStr, geneInfoArray, geneIDType, domainName) {
  #https://bdcw.org/geneid/rest/species/hsa/GeneIDType/SYMBOL_OR_ALIAS/GeneListStr/ITPR3,IL6,%20KLF4/View/json
  #https://bdcw.org/geneid/rest/species/hsa/GeneIDType/SYMBOL_OR_ALIAS/GeneListStr/RPE,%20ALDOB/View/json
  USE_NCBI_GENE_INFO = 0; # 2023/10/23
  geneInfoArray = gsub(" ", "%20", geneInfoArray);
#  url_str_gene_php = paste0("https://", domainName, "/geneid/rest/species/", orgStr, "/GeneIDType/", geneIDType, "/GeneListStr/", geneInfoArray, "/View/json");
  url_str_gene_php = paste0("https://", domainName, "/geneid/rest/species/", orgStr, "/GeneIDType/", geneIDType, "/GeneListStr/", geneInfoArray, "/USE_NCBI_GENE_INFO/", USE_NCBI_GENE_INFO, "/View/json");
  #print(url_str_gene_php);
  GeneAllInfo = fromJSON(url_str_gene_php, simplifyVector = TRUE);
  #GeneAllInfo = fromJSON(URLencode(url_str_gene_php), simplifyVector = TRUE);a # encode the URL: 2023/01/13 # doesn't work
  newdf = GeneAllInfo[c("SYMBOL", "ENTREZID")];
  uniquedf = unique(newdf);
  
  symbolStrArray = uniquedf$SYMBOL;
  entrezIDArray = uniquedf$ENTREZID;
  returndf = c(symbolStrArray, entrezIDArray);
   
  return(toString(returndf) %>% cat())


}

args <- commandArgs(TRUE);
species <- args[1];
geneInfoArray <- args[2];
geneIDType <- args[3];
domainName  <- args[4];
outval <- getGeneSymbolEntrezIDs(species, geneInfoArray, geneIDType, domainName)
