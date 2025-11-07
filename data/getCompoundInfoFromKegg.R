# This Rscript generates the precomputed RDS file for compound/metabolite information from KEGG
# Call syntax: Rscript getCompoundInfoFromKegg.R <species>
# Input: species or organism code like hsa, mmu, rno
# Output: <species>_keggGet_cpdInfo.RDS containing compound or metabolite information from KEGG
# susrinivasan@ucsd.edu; mano@sdsc.edu

################################################
# Please read the KEGG license terms before use:
# https://www.kegg.jp/kegg/legal.html
# https://www.pathway.jp/en/academic.html
################################################

library(tidyverse)
library(plyr)
library(KEGGREST)

remove_prefix <- function(entry) {
  substr(entry, start = 5, stop = nchar(entry))
}

args <- commandArgs(TRUE)
orgStr <- args[1]
rdsFilename <- paste0("./", orgStr, "_keggLink_mg.RDS")
print(paste("Reading from:", rdsFilename))

all_df <- readRDS(rdsFilename)
cpds <- all_df[str_detect(all_df[, 2], "cpd:"), ]
cpds$kegg_data <- sapply(cpds$kegg_data, remove_prefix)
metabList <- cpds$kegg_data

# Parameters
batch_size <- 10            # Number of IDs per KEGG API call
batches_per_phase <- 10     # Number of API calls per phase
pause_between_phases <- 10  # Seconds to pause between phases

query_split <- split(metabList, ceiling(seq_along(metabList) / batch_size))
total_batches <- length(query_split)
print(paste("Total batches:", total_batches))

info_list <- list()
num_phases <- ceiling(total_batches / batches_per_phase)

# Process in phases
for (phase_idx in seq_len(num_phases)) {
  start_batch <- (phase_idx - 1) * batches_per_phase + 1
  end_batch <- min(phase_idx * batches_per_phase, total_batches)
  
  cat(sprintf("Processing phase %d/%d (batches %d to %d)\n", phase_idx, num_phases, start_batch, end_batch))
  
  for (batch_idx in start_batch:end_batch) {
    batch_ids <- query_split[[batch_idx]]
    
    tryCatch({
      batch_info <- keggGet(batch_ids)
      info_list <- c(info_list, batch_info)
    }, error = function(e) {
      cat(sprintf("Error in batch %d: %s\n", batch_idx, e$message))
    })
  }
  
  if (phase_idx < num_phases) {
    cat(sprintf("Pausing for %d seconds before next phase...\n", pause_between_phases))
    Sys.sleep(pause_between_phases)
  }
}

# Extract and format info
extract_info <- lapply(info_list, '[', c("ENTRY", "NAME", "REACTION"))
dd <- do.call(rbind, extract_info)
cpdInfodf <- data.frame(dd)
colnames(cpdInfodf) <- c("ENTRY", "NAME", "REACTION")

cpdInfodf <- cpdInfodf[!duplicated(cpdInfodf$ENTRY), ]
rownames(cpdInfodf) <- cpdInfodf$ENTRY

metfilename <- paste0("./", orgStr, "_keggGet_cpdInfo.RDS")
print(paste("Saving to:", metfilename))
saveRDS(cpdInfodf, file = metfilename, compress = TRUE)
