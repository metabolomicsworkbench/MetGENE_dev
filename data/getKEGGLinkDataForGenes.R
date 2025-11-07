#!/usr/bin/env Rscript


# -------------------------------------------------------------------------
# KEGG Metabolic Link Extractor for Gene Lists
#
# USAGE:
#   Rscript getKEGGLinkDataForGenes.R <species_code> <mode>
#
# ARGUMENTS:
#   <species_code> : KEGG species code (e.g., hsa for human, mmu for mouse, rno for rat)
#   <mode>         : 'test', 'full', or 'phaseN' (e.g., phase1, phase2, ...)
#
# INPUT:
#   The script expects a file named <species_code>_EZID_SYMB.txt in the
#   working directory, containing one Entrez gene ID per line (no header).
#
# MODES:
#   test   : Processes the first 100 genes (for quick testing).
#   full   : Processes all genes in the file.
#   phaseN : Splits the gene list into N phases (default N=3) and processes
#            only the genes in the specified phase (e.g., 'phase1').
#
# OUTPUT:
#   Results are saved as <species_code>_keggLink_phase<i>.RDS
#   The output is a data frame with columns: gene_id, enzyme_id, reaction_id, compound_id
# Run merge to consolidate
# EXAMPLES:
#   Rscript getKEGGLinkDataForGenes.R hsa test
#   Rscript getKEGGLinkDataForGenes.R mmu full
#   Rscript getKEGGLinkDataForGenes.R rno phase2
#
# NOTES:
#   - Requires the KEGGREST and tidyverse packages.
#   - Please be considerate of KEGG server load; the script includes delays.
# -------------------------------------------------------------------------

suppressPackageStartupMessages({
  library(tidyverse)
  library(KEGGREST)
})

# Revised batch processing function
getMetabolicKEGGLinksForGeneBatch <- function(org_entrzid_batch) {

  tryCatch(
    {
      # Get enzyme numbers for the entire batch
      enzyme_links <- keggLink("enzyme", org_entrzid_batch)

      if (length(enzyme_links) == 0) {
        return(data.frame(
          gene_id = character(),
          kegg_data = character(),
          relation_type = character()
        ))
      }

      # Extract unique enzyme IDs (EC numbers)
      ec_batch <- unique(unname(enzyme_links))

      # Get related KEGG entities
      pathway_links  <- if (length(org_entrzid_batch) > 0) keggLink("pathway", org_entrzid_batch) else list()
      reaction_links <- if (length(ec_batch) > 0) keggLink("reaction", ec_batch) else list()
      compound_links <- if (length(ec_batch) > 0) keggLink("compound", ec_batch) else list()

      # Create mapping data frames
      df_enzyme <- data.frame(
        gene_id = rep(names(enzyme_links), times = lengths(enzyme_links)),
        enzyme_id = unname(unlist(enzyme_links)),
        stringsAsFactors = FALSE
      )

      df_reaction <- data.frame(
        enzyme_id = rep(names(reaction_links), times = lengths(reaction_links)),
        reaction_id = unname(unlist(reaction_links)),
        relation_type = "reaction",
        stringsAsFactors = FALSE
      )

      df_enzyme_reaction <- left_join(df_enzyme, df_reaction, by = "enzyme_id") %>%
                            select(gene_id, kegg_data = reaction_id, relation_type)

      df_compound <- data.frame(
        enzyme_id = rep(names(compound_links), times = lengths(compound_links)),
        compound_id = unname(unlist(compound_links)),
        relation_type = "compound",
        stringsAsFactors = FALSE
      )

      df_enzyme_compound <- left_join(df_enzyme, df_compound, by = "enzyme_id") %>%
                            select(gene_id, kegg_data = compound_id, relation_type)

      df_pathway <- data.frame(
        gene_id = rep(names(pathway_links), times = lengths(pathway_links)),
        kegg_data = unname(unlist(pathway_links)),
        relation_type = "pathway",
        stringsAsFactors = FALSE
      )

      # Combine all
      df_combined <- bind_rows(
        df_enzyme_reaction,
        df_enzyme_compound,
        df_pathway
      )

      # Remove exact duplicate rows
      df_combined <- distinct(df_combined)

      return(df_combined)
    },
    error = function(e) {
      if (grepl("Forbidden|403", e$message, ignore.case = TRUE)) {
        stop("🛑 KEGG API access forbidden (HTTP 403). Possible rate limit or permission issue. Stopping script.")
      } else {
        warning(sprintf("⚠️ Error processing batch: %s", e$message))
        return(data.frame(
          gene_id = character(),
          kegg_data = character(),
          relation_type = character()
        ))
      }
      
    }
  )
}


getKEGGLinkDataForGenes <- function(species_code, mode = "test", phase_idx = NULL, n_phases = 25, test_n = 100) {
  gene_file <- paste0(species_code, "_EZID_SYMB.txt")
  if (!file.exists(gene_file)) stop(sprintf("Gene file not found: %s", gene_file))

  # New code properly handles headers and column structure
  geneVec <- readr::read_tsv(gene_file,
    col_names = c("ENTREZID", "SYMBOL"), # Explicit column names
    skip = 1, # Skip header row
    col_types = cols_only(ENTREZID = "c")
  ) %>%
    filter(!is.na(ENTREZID) & ENTREZID != "") %>%
    mutate(ENTREZID = paste0(species_code, ":", ENTREZID)) %>%
    pull(ENTREZID)

  # Ensure geneVec is a vector, not a list
  geneVec <- unlist(geneVec)
  total_genes <- length(geneVec)
  message(sprintf("Loaded %d gene IDs for species %s", total_genes, species_code))
  print(head(geneVec))
  # Gene selection logic (same as before)
  if (tolower(mode) == "test") {
    queryStrVec <- head(geneVec, test_n)
    phase_label <- "test"
  } else if (tolower(mode) == "full") {
    queryStrVec <- geneVec   # <-- Add this line
    phase_label <- "full"
  } else if (grepl("^phase\\d+$", mode, ignore.case = TRUE)) {
    phase_idx <- as.integer(gsub("phase", "", tolower(mode)))
    genes_per_phase <- ceiling(total_genes / n_phases)
    start_idx <- (phase_idx - 1) * genes_per_phase + 1
    end_idx <- min(phase_idx * genes_per_phase, total_genes)
    queryStrVec <- geneVec[start_idx:end_idx]
    phase_label <- mode
  }
  output_file <- paste0(species_code, "_keggLink_", phase_label, ".RDS")
  print(paste0("Output file = ", output_file))
  # Process in batches of 50 genes
  batch_size <- 10
  batches <- split(queryStrVec, ceiling(seq_along(queryStrVec) / batch_size))
  results <- list()
  print(paste0("Number of batches in phase ", length(batches)))
  for (i in seq_along(batches)) {
    message(sprintf("Processing batch %d/%d (%d genes)", i, length(batches), length(batches[[i]])))
    print(paste0("batch ", i, ": ", paste(batches[[i]], collapse = ", ")))
    batch_result <- getMetabolicKEGGLinksForGeneBatch(batches[[i]])
    #print(head(batch_result))
    # Check if batch_result is empty
    if (nrow(batch_result) == 0) {
      message("No KEGG links found for this batch.")
      next
    }
    # Add batch results if not empty
    if (nrow(batch_result) > 0) {
      print(paste0("Appending batch ", i , " result"))
      results[[i]] <- batch_result
    }

    Sys.sleep(4.5) # Increased delay for batch processing
  }
  print("After batch loop")
  #print(head(results))
  # Check if results are empty
  if (length(results) == 0 ) {
    stop("No KEGG links found for the provided gene list.")
  }
  # Combine all results
  print("Before final_df")
  final_df <- bind_rows(results)
  

  # Save to file
  
  saveRDS(final_df, output_file)
  message(sprintf("✅ Saved results with %d rows to: %s", nrow(final_df), output_file))
  print(paste0("After final_df " , head(final_df)))
}

# Entry point remains the same
args <- commandArgs(TRUE)
if (length(args) < 2) stop("Usage: Rscript getKEGGLinkDataForGenes.R <species_code> <phase|full|test>")

species_code <- args[1]
mode <- tolower(args[2])

# Default number of phases remains 3
if (grepl("^phase\\d+$", mode, ignore.case = TRUE)) {
  getKEGGLinkDataForGenes(species_code, mode = mode, n_phases = 25)
} else {
  getKEGGLinkDataForGenes(species_code, mode = mode)
}
