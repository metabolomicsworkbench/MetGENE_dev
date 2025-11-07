#!/usr/bin/env Rscript
# This script generates a table of gene information pertaining to NCBI, GeeCards, KEGG, Uniprot,Marrvel etc
# Call Syntax : RScript extractGeneInfoTable.R <species> <geneIDArray> <domainName>
# Input : species ororganism code (e.g. hsa, mmu)
#       : geneArray (e.g. 3098,6120 , array of ENTREZIDs)
#       : domainName (e.g. bcdw.org)
# Output : A html tablecontaining links to KEGG, GeneCards, NCBI, Ensembl, Uniprot,Marrvel
# susrinivasan@ucsd.edu, mano@sdsc.edu

library(utils)
library(dplyr)
library(xtable)
library(textutils)
library(jsonlite)
library(tuple)
library(tidyr)
library(plyr)



getGeneInfoTable <- function(orgStr, geneArray, domainName) {
# Get the current directory
  currDir = paste0("/",basename(getwd()));
  #url_str_gene_php = paste0("https://", domainName,"/geneid/geneid_proc_selcol_GET.php?species=",orgStr,"&GeneListStr=", geneArray, "&GeneIDType=ENTREZID&View=json&IncHTML=1");
  GeneIDType = "ENTREZID";
  USE_NCBI_GENE_INFO = 0;
  ViewType = "json";
  IncHTML = 1;
  url_str_gene_php = paste0("https://", domainName, "/geneid/geneid_proc_selcol_GET.php?species=", orgStr, "&GeneListStr=", geneArray, "&GeneIDType=", GeneIDType, "&USE_NCBI_GENE_INFO=", USE_NCBI_GENE_INFO, "&View=", ViewType, "&IncHTML=", IncHTML);

  GeneAllInfo = fromJSON(url_str_gene_php, simplifyVector = TRUE);
    GeneAllInfo_forhtml = unique(GeneAllInfo[c("SYMBOL","HTML_KEGG", "HTML_SYMBOL", "HTML_ENTREZID", "HTML_ENSEMBL", "HTML_UNIPROT", "HTML_MARRVEL")]);

##  Added on 5/26/2022
    GeneAllInfo_forhtml_orig = GeneAllInfo_forhtml;
##  print(head(GeneAllInfo_forhtml));
    newdf = ddply(GeneAllInfo_forhtml,  .(SYMBOL, HTML_KEGG, HTML_SYMBOL, HTML_ENTREZID, HTML_ENSEMBL, HTML_MARRVEL),  .fun = summarize,  HTML_UNIPROT_COLLAPSED = paste0(unique(HTML_UNIPROT), collapse=", "),  .progress = "text", .inform = FALSE,  .drop = TRUE,  .parallel = TRUE,  .paropts = NULL);
    
  colnames(newdf)[(which(colnames(newdf) %in% "HTML_UNIPROT_COLLAPSED"))] = "HTML_UNIPROT";      

    GeneAllInfo_forhtml = newdf;

    ## Collapsing ENSEMBL, 2nr argument to ddply do not use the column that is collapsed
    newdf = ddply(GeneAllInfo_forhtml,  .(SYMBOL, HTML_KEGG, HTML_SYMBOL, HTML_ENTREZID, HTML_UNIPROT, HTML_MARRVEL),  .fun = summarize,  HTML_ENSEMBL_COLLAPSED = paste0(unique(HTML_ENSEMBL), collapse=", "),  .progress = "text", .inform = FALSE,  .drop = TRUE,  .parallel = TRUE,  .paropts = NULL);
    
  colnames(newdf)[(which(colnames(newdf) %in% "HTML_ENSEMBL_COLLAPSED"))] = "HTML_ENSEMBL";
   GeneAllInfo_forhtml = newdf;          
##    print("____________");
##    print(dim(GeneAllInfo_forhtml));
##    print(head(newdf));


  newdf = data.frame(matrix(ncol = 7, nrow = 0), stringsAsFactors=False);
  symbolStr = "Symbol";
  keggStr = paste0("<img src=\"",currDir,"/images/kegg4.gif\" alt=\"KEGG\" width=\"60\">");
  geneCardsStr = paste0("<img src=\"",currDir,"/images/logo_genecards.png\" alt=\"Gene Cards\" width=\"60\">");
  ncbiStr = paste0("<img src=\"",currDir,"/images/NCBILogo.gif\" alt=\"NCBI\" width=\"60\">");
  ensemblStr = paste0("<img src=\"",currDir,"/images/ensembl_logo.png\" alt=\"Ensembl\" width=\"60\">");
  uniprotStr = paste0("<img src=\"",currDir,"/images/Uniprot.png\" alt=\"Uniprot\" style=\"background-color:gray;padding:20px;\" width=\"60\">");
  marrvelStr = paste0("<img src=\"",currDir,"/images/marrvel.png\" alt=\"Marrvel\"  width=\"60\">");

  colnames(newdf) = c(symbolStr, keggStr, geneCardsStr, ncbiStr, ensemblStr, uniprotStr, marrvelStr);
  for (i in 1:dim(GeneAllInfo_forhtml)[1]) {
      symbolStr = GeneAllInfo_forhtml$SYMBOL[i];
      keggColStr =  GeneAllInfo_forhtml$HTML_KEGG[i];
      geneCardsColStr = GeneAllInfo_forhtml$HTML_SYMBOL[i];
      ncbiColStr = GeneAllInfo_forhtml$HTML_ENTREZID[i];
      ensemblColStr = GeneAllInfo_forhtml$HTML_ENSEMBL[i];
      uniprotColStr = GeneAllInfo_forhtml$HTML_UNIPROT[i];
      marrvelColStr = GeneAllInfo_forhtml$HTML_MARRVEL[i];
      newdf[i,] = c(symbolStr, keggColStr, geneCardsColStr, ncbiColStr, ensemblColStr, uniprotColStr, marrvelColStr);

  }
#  newdf  <- newdf %>% group_by(Symbol) %>% summarise(uniprotStr = paste0(uniprotStr, collapse = ', '), .groups = 'drop');
  nprint = nrow(newdf);
  return(print(xtable(newdf[1:nprint,]), type="html", include.rownames=FALSE, sanitize.text.function=function(x){x}, html.table.attributes="class='styled-table' id='Table1'"));
    
  
}

args <- commandArgs(TRUE);
species <- args[1];
geneArray  <- args[2];
domainName  <- args[3];
outhtml <- getGeneInfoTable(species, geneArray, domainName);


