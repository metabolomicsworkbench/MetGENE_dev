#!/usr/bin/env Rscript

# ============================================================
# Script: mergeKEGGPhases.R
#
# Purpose:
#   Combine multiple KEGG phase RDS files for a given species
#   into one merged RDS file.
#
# Usage:
#   Rscript mergeKEGGPhases.R <species_code> <n_phases>
#
#   <species_code>   Short species code (e.g., "mmu" for mouse)
#   <n_phases>       Number of phase files to merge (e.g., 25)
#
# Example:
#   Rscript mergeKEGGPhases.R mmu 25
#
# Output:
#   Saves merged file as <species_code>_keggLink_mg.RDS
#
# Author: [Your Name]
# Date:   2025-05-20
# ============================================================

suppressPackageStartupMessages({
  library(tidyverse)
})

# ---- Parse command line arguments ----
args <- commandArgs(trailingOnly = TRUE)
if(length(args) < 2) {
  stop(
    "\nUsage: Rscript merge_kegg.R <species_code> <n_phases>\n",
    "Example: Rscript merge_kegg.R mmu 25\n"
  )
}
species_code <- args[1]
n_phases <- as.integer(args[2])

# ---- Generate list of input files ----
all_files <- paste0(species_code, "_keggLink_phase", 1:n_phases, ".RDS")

# ---- Read and combine all phase files ----
all_data <- map_dfr(all_files, readRDS)

# ---- Rename columns for clarity ----
colnames(all_data) <- c("org_ezid", "kegg_data", "relation_type")

# ---- Save merged data ----
output_file <- paste0(species_code, "_keggLink_mg.RDS")
saveRDS(all_data, output_file)
message(sprintf("✅ Merged and grouped data saved to: %s", output_file))
