# This R script generates SYMBOLS and EntrzIDs for use in copmuteGeneAssociations.R 
# Call Syntax: Rscript getEntrzIDsSymbolsFromKeggLinkDF.R <species> 
# Input: species of organism code liks hsa, mmu, rno
# Output: <specis>_metSYMBOLs.txt, <species>_metENTRZIDs.txt, <species>_metENTRZIDsAndSYMBOLs.txt
# susrinivasan@ucsd.edu ; mano@sdsc.edu

#library(jsonlite)
#library(plyr)
#library(dplyr)

args <- commandArgs(TRUE);
species = args[1]


rdsFileName = paste0("./", species,"_keggLink_mg.RDS")
print(rdsFileName)
df = readRDS(rdsFileName)
org_entrzIds = df$org_ezid

use_geneID_REST = 0;

# load the DF of ENTREZ ID and SYMBOL
#species = "hsa";
# Keeping the lines re original file name for internal tracking
#if(tolower(species) %in% c("human","hsa","homo sapiens")){
#    NCBI_gene_id_symbol_file = "Homo_sapiens.gene_info_20220517.txt_proteincoding_ENTREZID_SYMBOL.txt";
#}
NCBI_gene_id_symbol_file = paste0(species, "_EZID_SYMB.txt");
id_symb = read.table(NCBI_gene_id_symbol_file, header = TRUE, stringsAsFactors = FALSE, quote = "", comment.char = "#", sep="\t");


# removes orgId out of entrzId
extract_entrzids <- function(string) {
#    substr(string, start = 5, stop = nchar(string))
    splitarr = strsplit(string, split=":")
    return(splitarr[[1]][2])
}
entrz_ids = sort(as.vector(sapply(unique(org_entrzIds), extract_entrzids)))

newdf = id_symb[match(entrz_ids, id_symb$ENTREZID), ];
#entrziddf = sort(unique(newdf$ENTREZID))
#print(head(entrziddf))
symboldf =  sort(unique(newdf$SYMBOL))
print(head(symboldf))
#write.table(entrziddf, paste0("./",species,"_metEntrzIDs.txt"), sep="\n", col.names=FALSE, row.names=FALSE, quote=FALSE)
write.table(symboldf, paste0("./",species,"_metSYMBOLs.txt"), sep = "\n", col.names=FALSE, row.names=FALSE, quote=FALSE)
