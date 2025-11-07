# This Rscript generates the precomputed RDS file for summary table information in a parallel manner
# Run this script with precompute flag set to zero in setPrecompute.R
# Call syntax: Rscript computeMetGENESummary_parallel.R <species> 
# Input: species or organism codelike hsa, mmu, rno
#      
# Dependencies : Requires <species>_EZID_SYMB.txt which is a list of entrzId and symbols mapping.  # nolint # nolint
#              : Requires <species>_keggLink_mg.RDS which contains Kegg Link data mappings for genes in # nolint # nolint
# Output: <species>_summaryTable.RDS
# Make sure your OS supports parallel. This runs 8 seconds faster per query compared to computeMetGENESummary.R
# susrinivasan@ucsd.edu; mano@sdsc.edu
#!/usr/bin/env Rscript

library(jsonlite)
library(dplyr)
library(readr)
library(parallel)
library(tictoc)

# ------------ PARSE ARGUMENTS ----------------
args <- commandArgs(trailingOnly = TRUE)
tic()
if (length(args) < 1) {
    stop("❌ Please provide a species code (e.g., 'hsa') as the first argument.\nUsage: Rscript generate_summary_table.R hsa [test]")
}

species <- args[1]
test_mode <- length(args) >= 2 && args[2] == "test"
kegg_prefix <- paste0(species, ":")
if (species %in% c("Human", "human", "hsa", "Homo sapiens")) {
    organism_name <- "Human"
} else if (species %in% c("Mouse", "mouse", "mmu", "Mus musculus")) {
    organism_name <- "Mouse"
} else if (species %in% c("Rat", "rat", "rno", "Rattus norvegicus")) {
    organism_name <- "Rat"
} else {
    stop("❌ Unknown species")
}

# ------------ FILE PATHS ----------------
kegg_links_file <- paste0(species, "_keggLink_mg.RDS")
symbol_map_file <- paste0(species, "_EZID_SYMB.txt")
summary_outfile <- paste0(species, "_summaryTable.RDS")
print(summary_outfile)

# ------------ LOAD DATA ----------------
kegg_links <- readRDS(kegg_links_file)
symbol_map <- read_tsv(symbol_map_file, show_col_types = FALSE)

# ------------ HELPER FUNCTION ----------------
list_of_list_to_df <- function(jslist) {
    cols_needed <- c("refmet_name", "kegg_id", "study", "study_title")
    if (length(jslist) == 0) return(NULL)
    
    if (is.list(jslist[[1]])) {
        dfs <- lapply(jslist, function(item) {
            df <- as.data.frame(t(as.data.frame(unlist(item))))
            df[, cols_needed, drop = FALSE]
        })
        bind_rows(dfs)
    } else {
        jsdf <- as.data.frame(t(as.data.frame(unlist(jslist))))
        jsdf[, cols_needed, drop = FALSE]
    }
}

# ------------ FUNCTION TO PROCESS ONE GENE ----------------
process_gene <- function(gene) {
    gene_num <- as.numeric(sub(paste0(species, ":"), "", gene))
    gene_symbol <- symbol_map$SYMBOL[symbol_map$ENTREZID == gene_num]
    gene_symbol <- ifelse(length(gene_symbol) == 0, as.character(gene), gene_symbol[1])
    
    gene_links <- kegg_links[kegg_links$org_ezid == gene, ]
    
    pathways <- sum(gene_links$relation_type == "pathway")
    reactions <- sum(gene_links$relation_type == "reaction")
    
    compound_links <- gene_links[gene_links$relation_type == "compound", ]
    compound_ids <- unique(sub("cpd:", "", compound_links$kegg_data))

    geneMetabCnt <- 0
    geneStudyCnt <- 0
    
    if (length(compound_ids) > 0) {
        for (metab in compound_ids) {
            metabStr <- metab
            path <- paste0("https://www.metabolomicsworkbench.org/rest/metstat/;;;",
             organism_name, ";;;", metabStr)
            jslist <- tryCatch(read_json(path, simplifyVector = TRUE), error = function(e) NULL)
            if (is.null(jslist)) next
            mydf_studies <- list_of_list_to_df(jslist)
            if (is.null(mydf_studies)) next
            studiesVec <- mydf_studies$study
            refMetVec <- mydf_studies$refmet_name
            geneMetabCnt <- geneMetabCnt + length(unique(refMetVec))
            geneStudyCnt <- geneStudyCnt + length(unique(studiesVec))
        }
    }
    
    data.frame(
        Pathways = pathways,
        Reactions = reactions,
        Metabolites = geneMetabCnt,
        Studies = geneStudyCnt,
        Genes = gene_symbol,
        stringsAsFactors = FALSE
    )
}

# ------------ MAIN ----------------
if (test_mode) {
    cat("🧪 Test mode: Processing genes hsa:3098, hsa:229, hsa:6120...\n")
    genes <- c("hsa:3098", "hsa:229", "hsa:6120")
} else {
    genes <- unique(kegg_links$org_ezid)
}

# Detect cores for parallel
#ncores <- min(8, detectCores())
ncores <- detectCores()
cl <- makeCluster(ncores)

# Export needed variables and libraries to workers
clusterExport(cl, varlist = c("species", "symbol_map", "kegg_links", "organism_name", "list_of_list_to_df", "process_gene"))
clusterEvalQ(cl, {library(jsonlite); library(dplyr)})

# Run in parallel
summary_list <- parLapply(cl, genes, process_gene)

stopCluster(cl)

summary_df <- bind_rows(summary_list)
rownames(summary_df) <- summary_df$Genes

if (test_mode) {
    cat("🧪 Test output summary:\n")
    print(summary_df)
} else {
    saveRDS(summary_df, file = summary_outfile)
    cat(paste0("✅ Summary table saved to: ", summary_outfile, "\n"))
}
toc()