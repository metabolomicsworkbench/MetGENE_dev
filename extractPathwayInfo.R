#!/usr/bin/env Rscript
# THis script obtains the pathway information pertaining to the gene input
# Call syntax: Rscript extractPathwayInfo.R <species> <geneIDArray> <geneSymArr> <speciesSciName>
# Input: orgStr e.g. hsa, mmu (species code)
#        geneArray : e.g. 3098, 6120 (ENTREZID of genes)
#        geneSymbolArray : e.g. HK1,RPE (gene symbols)
#        species SciName : e.g. Homo sapiens, Mus musculus
# Output: A html table comprising of pathway information hyperlinked to various pathway databases
# susrinivasan@ucsd.edu; mano@sdsc.edu
#!/usr/bin/env Rscript



library(xtable);


getPathwayInfoTable <- function(species, geneArray, geneSymbolArray, species_sci_name) {
# Get the current directory
  currDir = paste0("/",basename(getwd()));

  newdf = data.frame(matrix(ncol = 4, nrow = 0), stringsAsFactors=False);


  pcStr = paste0("<img src=\"",currDir,"/images/pc_logo.png\" alt=\"Pathway Commons\" width=\"60\">");
  reactomeStr = paste0("<img src=\"",currDir,"/images/reactome.png\" alt=\"Reactome\" width=\"80\">");
  keggStr = paste0("<img src=\"",currDir,"/images/kegg4.gif\" alt=\"KeGG\" width=\"60\">");
  wikiStr = paste0("<img src=\"",currDir,"/images/wikipathways.PNG\" alt=\"Wiki Pathways\" width=\"60\">");

  colnames(newdf) = c(pcStr, reactomeStr, keggStr, wikiStr);

  if(species %in% c("Human","human","hsa","Homo sapiens")){
        organism_name = "Homo+sapiens";
  } else if(species %in% c("Mouse","mouse","mmu","Mus musculus")){
        organism_name = "Mus+muculus";
  } else if(species %in% c("Rat","rat","rno","Rattus norvegicus")){
        organism_name = "Rattus+norvegicus";
  } else {
        organism_name = "";
  }

  ## Loop through the keggDF to get the gene symbol and organism name
  for (i in 1:length(geneArray)) {
    geneIdStr = geneArray[i];
    geneSymbolStr = geneSymbolArray[i];
    for (j in 1:ncol(newdf)) {
      prelinkStr = "<a href=\"https://apps.pathwaycommons.org/search?type=Pathway&q=";                                                                            
      postlinkStr = "\" target=\"_blank\">";
      pcColStr = paste0(prelinkStr,geneSymbolStr,postlinkStr,geneSymbolStr,"</a>");

      prelinkStr = "<a href=\"https://reactome.org/content/query?q=";                                                                                           
      postlinkStr = paste0("&species=",organism_name,"&cluster=true\" target=\"_blank\">");
      reactomeColStr = paste0(prelinkStr,geneSymbolStr,postlinkStr,geneSymbolStr,"</a>");

      #prelinkStr = "<a href=\"https://www.genome.jp/dbget-bin/get_linkdb?-t+pathway+";
      prelinkStr = "<a href=\"https://www.kegg.jp/entry/"; # 2025/05/19
      postlinkStr = "\" target=\"_blank\">";
      keggColStr = paste0(prelinkStr,species,":",geneIdStr,postlinkStr,geneSymbolStr,"</a>");

#https://www.wikipathways.org/search.html?q=HK1
       prelinkStr = "<a href=\"https://www.wikipathways.org/search.html?query=";
       postlinkStr = paste0("&species=",organism_name,"&title=Special%3ASearchPathways&doSearch=1&ids=&codes=&type=query\" target=\"_blank\">");
       wikiColStr = paste0(prelinkStr,geneSymbolStr,postlinkStr,geneSymbolStr,"</a>");
#       wikiColStr = paste0(geneIdStr);

      newdf[i,] = c(pcColStr, reactomeColStr, keggColStr, wikiColStr);
    }
  }
  nprint = nrow(newdf);
  return(print(xtable(newdf[1:nprint,]), type="html", include.rownames=FALSE, sanitize.text.function=function(x){x}, html.table.attributes="class='styled-table' id='Table1'"));
    
  
}

args <- commandArgs(TRUE);
species <- args[1];
geneArray <- as.vector(strsplit(args[2],split=",",fixed=TRUE)[[1]])
geneSymbolArray <- as.vector(strsplit(args[3],split=",",fixed=TRUE)[[1]])
speciesSciName = args[4];
##species = "hsa"
##geneArray <- c(3098,229);
outhtml <- getPathwayInfoTable(species, geneArray, geneSymbolArray, speciesSciName);


