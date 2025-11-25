#!/usr/bin/env Rscript
# THis script extracts the summary information pertaining to the gene input
# Call syntax: Rscript extractMWGeneSummary.R <species> <geneIDArr> <geneSymArr>  <filename> <viewType> <anatomy> <disease>
# Input: species e.g. hsa, mmu
#        geneIdArr : e.g. 3098, 6120 (ENTREZID of genes)
#        geneStrArr : e.g. HK1, RPE
#        filename: e.g. plot.png for the summary plot
#        viewType : e.g. json, txt, png, bar, table, all (default is pie chart)
# Output: A table in json or txt format comprising of summary (Pathways, Reactions, Metabolites, Studies) information
#       : A html table (if view type is table)
#       : A bar plot (viewType is bar)
#       : A pie chart, and table for all
# Example: Rscript extractMWGeneSummary.R hsa 3098__6120 HK1__RPE plot.png all blood diabetes
# susrinivasan@ucsd.edu; mano@sdsc.edu
#
################################################
# Restrictions due to the use of KEGG APIs (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html)
# * Using this code to provide user's own web service
# The code we provide is free for non-commercial use (see LICENSE). While it is our understanding that no KEGG license is required to run the web app on user's local computer for personal use (e.g., access as localhost:install_location_withrespectto_DocumentRoot/MetGENE, or, restrict its access to the IP addresses belonging to their own research group), the users must understand the KEGG license terms (https://www.kegg.jp/kegg/legal.html, see also https://www.pathway.jp/en/academic.html) and decide for themselves. For example, if the user wishes to provide this tool (or their own tool based on a subset of MetGENE scripts with KEGG APIs) as a service (see LICENSE), they must obtain their own KEGG license with suitable rights.
# * Faster version of MetGENE
# If and only if the user has purchased license for KEGG FTP Data, they can activate a 'preCompute' mode to run faster version of MetGENE. To achieve this, please set preCompute = 1 in the file setPrecompute.R. Otherwise, please ensure that preCompute is set to 0 in the file setPrecompute.R. Further, to use the faster version, the user needs to run the R scripts in the 'data' folder first. Please see the respective R files in the 'data' folder for instructions to run them.
# Please see the files README.md and LICENSE for more details.
################################################

## Linux
suppressPackageStartupMessages({
  library(KEGGREST)
  library(rlang)
  library(stringr)
  library(data.table)
  library(xtable)
  library(jsonlite)
  library(tictoc)
  library(utils)
  library(textutils)
  library(tuple)
  library(tidyr)
  library(ggplot2)
  library(reshape2)
  library(ggrepel)
  library(tidyverse)
})

# precompute flag + shared validation helpers
source("setPrecompute.R")
source("common_functions.R")   # expects normalize_species(), load_allowed_diseases(), load_allowed_anatomy()

# ----------------- small helpers -----------------

cleanFun <- function(htmlString) {
  gsub("<.*?>", "", htmlString)
}

# Legacy debug helper retained for compatibility
printvar <- function(x, xpr = NA) {
  write(paste0(deparse(substitute(x)), " = "), "")
  if (!is.na(xpr)) write(xpr, "")
  print(x)
  if (!is.na(xpr)) write(xpr, "")
}

# KEGG helper: get reactions, compounds, pathways for a gene (live query mode)
getRxnIDsPthwyIDsCpdIDsFromKEGG <- function(queryStr) {
  kegg_data <- keggGet(queryStr)
  if (length(kegg_data) == 0 || is.null(kegg_data[[1]]$ORTHOLOGY)) {
    stop("Invalid KEGG entry or no ORTHOLOGY information found.")
  }

  enzyme <- kegg_data[[1]]$ORTHOLOGY[[1]]

  ec_number <- regmatches(enzyme, regexpr("EC:\\d+\\.\\d+\\.\\d+\\.\\d+", enzyme))
  if (length(ec_number) == 0) {
    stop("No EC number found in ORTHOLOGY field.")
  }
  ec_number <- tolower(ec_number)

  # reactions
  rxns <- keggLink("reaction", ec_number)
  rxn_vec <- unname(as.vector(rxns))
  rxn_df <- data.frame(
    Type = paste0("reaction", seq_along(rxn_vec)),
    ID   = rxn_vec,
    stringsAsFactors = FALSE
  )

  # compounds
  cpds <- keggLink("compound", ec_number)
  cpd_vec <- unname(as.vector(cpds))
  cpd_df <- data.frame(
    Type = paste0("compound", seq_along(cpd_vec)),
    ID   = cpd_vec,
    stringsAsFactors = FALSE
  )

  # pathways
  pthwys   <- keggLink("pathway", queryStr)
  pthwy_vec <- unname(as.vector(pthwys))
  pthwy_df <- data.frame(
    Type = paste0("pathway", seq_along(pthwy_vec)),
    ID   = pthwy_vec,
    stringsAsFactors = FALSE
  )

  rbind(rxn_df, cpd_df, pthwy_df)
}

# Parse MW metstat JSON list into data frame (shared w/ other scripts)
list_of_list_to_df <- function(jslist) {
  cols_needed <- c("refmet_name", "kegg_id", "study", "study_title")

  if (length(jslist) == 0) {
    return(NULL)
  }

  if (is.list(jslist[[1]])) {
    n <- length(jslist)
    for (i in seq_len(n)) {
      row_i <- as.data.frame(t(as.data.frame(unlist(jslist[[i]]))))
      row_i <- row_i[, cols_needed, drop = FALSE]
      if (i == 1) {
        jsdf <- row_i
      } else {
        jsdf <- rbind(jsdf, row_i)
      }
    }
    rownames(jsdf) <- as.character(seq_len(nrow(jsdf)))
  } else {
    jsdf <- as.data.frame(t(as.data.frame(unlist(jslist))))
    jsdf <- jsdf[, cols_needed, drop = FALSE]
    rownames(jsdf) <- "1"
  }

  jsdf
}

# ----------------- plotting / output -----------------

plotSummary <- function(countMatrix,
                        genesCnt,
                        symbolStrArray,
                        organism_name,
                        pathwaysLinkStr,
                        rxnsLinkStr,
                        metsLinkStr,
                        studiesLinkStr,
                        plotFile,
                        viewType) {

  currDir <- paste0("/", basename(getwd()))

  if (nrow(countMatrix) == 0) {
    return(cat("<p> No summaries found in Metabolomics Workbench for the specified genes.</p>"))
  }

  categories <- c(
    rep("Pathways",    genesCnt),
    rep("Reactions",   genesCnt),
    rep("Metabolites", genesCnt),
    rep("Studies",     genesCnt)
  )
  Genes  <- rep(as.vector(symbolStrArray), 4)
  values <- as.vector(countMatrix)

  data <- data.frame(categories, Genes, values)
  titleStr <- organism_name

  if (genesCnt <= 0) {
    return(print("<p><i>No genes specified</i></p>"))
  }

  rplot <- ggplot(data, aes(x = "", y = values, fill = Genes)) +
    geom_bar(stat = "identity", position = position_fill()) +
    geom_text(aes(label = values), position = position_fill(vjust = 0.5), size = 6) +
    theme_void() +
    theme(
      plot.title  = element_text(color = "blue", size = 20, face = "bold", hjust = 0.5, vjust = 0.75),
      strip.text.x = element_text(size = 20),
      legend.position = "bottom",
      legend.text  = element_text(size = 20),
      legend.title = element_text(size = 20)
    ) +
    labs(title = titleStr) +
    facet_wrap(~categories) +
    theme(
      axis.title.x = element_blank(),
      axis.title.y = element_blank()
    ) +
    guides(fill = guide_legend(ncol = 4, byrow = TRUE))

  tabDF <- as.data.frame(countMatrix)
  colnames(tabDF) <- c(pathwaysLinkStr, rxnsLinkStr, metsLinkStr, studiesLinkStr)
  rownames(tabDF) <- symbolStrArray

  tabDF[] <- lapply(tabDF, function(x) as.integer(x))
  nprint <- nrow(tabDF)
  vtFlag <- tolower(viewType)

  if (vtFlag == "all") {
    rplot <- rplot + coord_polar(theta = "y")
    ggplot2::ggsave(plotFile, plot = rplot, device = "png")
    cat(paste0(
      "<table><tr><td><a href=\"", plotFile, "\" download>",
      "<img src=", currDir, "/", plotFile,
      " height=300 width=320 alt='R Graph'></a></td><td>"
    ))
    return(print(
      xtable(tabDF[1:nprint, ]),
      type = "html",
      include.rownames = TRUE,
      sanitize.text.function = function(x) x,
      html.table.attributes = "class='styled-table' id='Table1'"
    ))
  } else if (vtFlag == "bar") {
    ggplot2::ggsave(plotFile, plot = rplot, device = "png")
    cat(paste0(
      "<a href=\"", plotFile, "\" download>",
      "<img src=", currDir, "/", plotFile,
      " height=300 width=320 alt='R Graph'></a>"
    ))
    return(print(
      xtable(tabDF[1:nprint, ]),
      type = "html",
      include.rownames = TRUE,
      sanitize.text.function = function(x) x,
      html.table.attributes = "class='styled-table' style='display:none' id='Table1'"
    ))
  } else if (vtFlag == "json") {
    colnames(tabDF) <- cleanFun(colnames(tabDF))
    tabDF$Genes <- rownames(tabDF)
    tabJson <- toJSON(x = tabDF, pretty = TRUE)
    return(cat(tabJson))
  } else if (vtFlag == "txt") {
    colnames(tabDF) <- cleanFun(colnames(tabDF))
    tabDF$Genes <- rownames(tabDF)
    return(cat(format_delim(tabDF, ",")))
  } else if (vtFlag == "png") {
    rplot <- rplot + coord_polar(theta = "y")
    ggplot2::ggsave(plotFile, plot = rplot, device = "png")
    return(cat("Image generated"))
  } else if (vtFlag == "table") {
    return(print(
      xtable(tabDF[1:nprint, ]),
      type = "html",
      include.rownames = TRUE,
      sanitize.text.function = function(x) x,
      html.table.attributes = "class='styled-table' id='Table1'"
    ))
  } else {
    rplot <- rplot + coord_polar(theta = "y")
    ggplot2::ggsave(plotFile, plot = rplot, device = "png")
    cat(paste0(
      "<table><tr><td><a href=\"", plotFile, "\" download>",
      "<img src=", currDir, "/", plotFile,
      " height=200 width=220 alt='R Graph'></a></td><td>"
    ))
    return(print(
      xtable(tabDF[1:nprint, ]),
      type = "html",
      include.rownames = TRUE,
      sanitize.text.function = function(x) x,
      html.table.attributes = "class='styled-table' id='Table1'"
    ))
  }
}

# ----------------- LEGACY (kept for compatibility, not hardened) -----------------
# Old precompute summary function (not used by current PHP front-end, left as-is)
getGeneSummaryInfoTableOld <- function(orgStr, geneIDArray, geneSymArray,
                                       anatomy = "NA", disease = "NA",
                                       plotFile, viewType) {
  currDir <- paste0("/", basename(getwd()))
  symbolStrArray <- as.vector(strsplit(geneSymArray, split = "__", fixed = TRUE)[[1]])
  geneArray      <- as.vector(strsplit(geneIDArray, split = "__", fixed = TRUE)[[1]])

  metGeneSYMBOLFileName <- file.path("data", paste0(orgStr, "_metSYMBOLs.txt"))
  metGeneVec <- readLines(metGeneSYMBOLFileName, warn = FALSE)
  symbolStrArray <- symbolStrArray[symbolStrArray %in% metGeneVec]

  if (orgStr %in% c("Human","human","hsa","Homo sapiens")) {
    organism_name <- "Human"
  } else if (orgStr %in% c("Mouse","mouse","mmu","Mus musculus")) {
    organism_name <- "Mouse"
  } else if (orgStr %in% c("Rat","rat","rno","Rattus norvegicus")) {
    organism_name <- "Rat"
  } else {
    organism_name <- "Human"
  }

  rdsfilename <- file.path("data", paste0(orgStr, "_summaryTable.RDS"))
  sumTable <- readRDS(rdsfilename)
  geneSumTable <- sumTable[sumTable$Genes %in% symbolStrArray, ]

  geneSumTable$Pathways   <- as.numeric(geneSumTable$Pathways)
  geneSumTable$Reactions  <- as.numeric(geneSumTable$Reactions)
  geneSumTable$Metabolites<- as.numeric(geneSumTable$Metabolites)
  geneSumTable$Studies    <- as.numeric(geneSumTable$Studies)

  countMatrix <- as.matrix(geneSumTable[, c("Pathways", "Reactions", "Metabolites", "Studies")])
  countMatrix <- unname(countMatrix)
  genesCnt    <- length(symbolStrArray)

  pathwaysLinkStr <- paste0(
    "<a href=\"", currDir, "/pathways.php?species=", orgStr,
    "&GeneIDType=ENTREZID&anatomy=", anatomy,
    "&disease=", disease,
    "&phenotype=NA",
    "&GeneInfoStr=", geneIDArray,
    "\" target=\"_blank\">Pathways</a>"
  )
  rxnsLinkStr <- paste0(
    "<a href=\"", currDir, "/reactions.php?species=", orgStr,
    "&GeneIDType=ENTREZID&anatomy=", anatomy,
    "&disease=", disease,
    "&phenotype=NA",
    "&GeneInfoStr=", geneIDArray,
    "\" target=\"blank\">Reactions</a>"
  )
  metsLinkStr <- paste0(
    "<a href=\"", currDir, "/metabolites.php?species=", orgStr,
    "&GeneIDType=ENTREZID&anatomy=", anatomy,
    "&disease=", disease,
    "&phenotype=NA",
    "&GeneInfoStr=", geneIDArray,
    "\" target=\"blank\">Metabolites</a>"
  )
  studiesLinkStr <- paste0(
    "<a href=\"", currDir, "/studies.php?species=", orgStr,
    "&GeneIDType=ENTREZID&anatomy=", anatomy,
    "&disease=", disease,
    "&phenotype=NA",
    "&GeneInfoStr=", geneIDArray,
    "\" target=\"blank\">Studies</a>"
  )

  plotSummary(countMatrix, genesCnt, symbolStrArray, organism_name,
              pathwaysLinkStr, rxnsLinkStr, metsLinkStr, studiesLinkStr,
              plotFile, viewType)
}

# ----------------- HARDENED RDS VERSION -----------------

getGeneSummaryInfoTable <- function(orgStr,
                                    geneIDArray,
                                    geneSymArray,
                                    anatomy = "NA",
                                    disease = "NA",
                                    plotFile,
                                    viewType) {

  # --- Species normalization (strict, matches PHP normalizeSpecies) ---
  species_info  <- normalize_species(orgStr)
  species_code  <- species_info$species_code    # hsa/mmu/rno
  organism_name <- species_info$species_label   # "Human"/"Mouse"/"Rat"

  # --- Load controlled vocabularies (Option A: reject invalid) ---
  base_dir          <- getwd()
  disease_json_path <- file.path(base_dir, "disease_pulldown_menu_cascaded.json")
  anatomy_html_path <- file.path(base_dir, "ssdm_sample_source_pulldown_menu.html")

  allowed_diseases <- load_allowed_diseases(disease_json_path)
  allowed_anatomy  <- load_allowed_anatomy(anatomy_html_path)

  anatomy_raw <- trimws(ifelse(is.null(anatomy), "", anatomy))
  disease_raw <- trimws(ifelse(is.null(disease), "", disease))

  if (identical(anatomy_raw, "NA")) anatomy_raw <- ""
  if (identical(disease_raw, "NA")) disease_raw <- ""

  if (nzchar(anatomy_raw) && !(anatomy_raw %in% allowed_anatomy)) {
    stop(sprintf(
      "Invalid anatomy term '%s'. Term must come from the controlled SSDM menu.",
      anatomy_raw
    ))
  }
  if (nzchar(disease_raw) && !(disease_raw %in% allowed_diseases)) {
    stop(sprintf(
      "Invalid disease term '%s'. Term must come from the controlled disease menu.",
      disease_raw
    ))
  }

  anatomy <- anatomy_raw
  disease <- disease_raw

  # --- Parse and strictly sanitize gene IDs + symbols in parallel ---
  raw_ids  <- as.vector(strsplit(geneIDArray, split = "__", fixed = TRUE)[[1]])
  raw_syms <- as.vector(strsplit(geneSymArray, split = "__", fixed = TRUE)[[1]])

  max_len <- max(length(raw_ids), length(raw_syms))
  if (length(raw_ids)  < max_len) raw_ids  <- c(raw_ids,  rep(NA_character_, max_len - length(raw_ids)))
  if (length(raw_syms) < max_len) raw_syms <- c(raw_syms, rep(NA_character_, max_len - length(raw_syms)))

  id_pattern <- "^[0-9]+$"   # ENTrez IDs are digits

  keep_idx <- which(
    !is.na(raw_ids) &
      nzchar(trimws(raw_ids)) &
      grepl(id_pattern, raw_ids)
  )

  if (length(keep_idx) == 0) {
    stop("No valid gene IDs remain after sanitization.")
  }

  geneArray      <- raw_ids[keep_idx]
  symbolStrArray <- raw_syms[keep_idx]

  # Rebuild a cleaned geneID string to propagate to PHP, instead of the raw one
  clean_geneID_str <- paste(geneArray, collapse = "__")

  # --- Load precomputed RDS table ---
  rdsfilename <- file.path("data", sprintf("%s_keggLink_mg.RDS", species_code))
  if (!file.exists(rdsfilename)) {
    stop(sprintf("Precomputed RDS file not found: %s", rdsfilename))
  }
  all_data <- readRDS(rdsfilename)

  required_cols <- c("org_ezid", "relation_type", "kegg_data")
  missing_cols  <- setdiff(required_cols, colnames(all_data))
  if (length(missing_cols) > 0) {
    stop(sprintf(
      "Precomputed RDS is missing required columns: %s",
      paste(missing_cols, collapse = ", ")
    ))
  }

  # --- Count matrix: Pathways, Reactions, Metabolites, Studies per gene ---
  countMatrix <- matrix(ncol = 4, nrow = length(geneArray))

  for (g in seq_along(geneArray)) {
    geneIdStr  <- geneArray[g]
    geneSymbol <- symbolStrArray[g]

    org_entrzIdStr <- paste0(species_code, ":", geneIdStr)
    gene_df <- all_data[all_data$org_ezid == org_entrzIdStr, , drop = FALSE]

    if (nrow(gene_df) == 0) {
      countMatrix[g, ] <- c(0L, 0L, 0L, 0L)
      next
    }

    genePthwyCnt <- gene_df %>%
      dplyr::filter(relation_type == "pathway") %>%
      dplyr::pull(kegg_data) %>%
      unique() %>%
      length()

    geneRxnsCnt <- gene_df %>%
      dplyr::filter(relation_type == "reaction") %>%
      dplyr::pull(kegg_data) %>%
      unique() %>%
      length()

    metabolites <- gene_df %>%
      dplyr::filter(relation_type == "compound") %>%
      dplyr::pull(kegg_data) %>%
      unique()

    metabolites <- sub("^cpd:", "", metabolites)

    geneMetabCnt <- 0L
    geneStudyCnt <- 0L
    allStudies   <- character(0)

    if (length(metabolites) > 0) {
      for (metabStr in metabolites) {

        anatomyQryStr <- if (nzchar(anatomy)) gsub("\\+", "%20", anatomy) else ""
        diseaseQryStr <- if (nzchar(disease)) gsub("\\+", "%20", disease) else ""

        mw_url <- paste0(
          "https://www.metabolomicsworkbench.org/rest/metstat/;;;",
          organism_name, ";",
          anatomyQryStr, ";",
          diseaseQryStr, ";",
          metabStr
        )

        jslist <- tryCatch(
          read_json(mw_url, simplifyVector = TRUE),
          error = function(e) NULL
        )
        if (is.null(jslist) || length(jslist) == 0) next

        mydf_studies <- list_of_list_to_df(jslist)
        if (is.null(mydf_studies) || nrow(mydf_studies) == 0) next

        studiesVec <- mydf_studies$study
        refMetVec  <- mydf_studies$refmet_name

        geneMetabCnt <- geneMetabCnt + length(unique(refMetVec))
        allStudies   <- c(allStudies, studiesVec)
      }

      if (geneMetabCnt == 0L) {
        geneMetabCnt <- length(metabolites)
      }
      geneStudyCnt <- length(unique(allStudies))
    }

    countMatrix[g, ] <- c(
      as.integer(genePthwyCnt),
      as.integer(geneRxnsCnt),
      as.integer(geneMetabCnt),
      as.integer(geneStudyCnt)
    )
  }

  currDir <- paste0("/", basename(getwd()))

  # Use cleaned gene IDs in all links
  pathwaysLinkStr <- paste0(
    "<a href=\"", currDir,
    "/pathways.php?species=", species_code,
    "&GeneIDType=ENTREZID",
    "&anatomy=", URLencode(anatomy, reserved = TRUE),
    "&disease=", URLencode(disease, reserved = TRUE),
    "&phenotype=NA",
    "&GeneInfoStr=", clean_geneID_str,
    "\" target=\"_blank\">Pathways</a>"
  )

  rxnsLinkStr <- paste0(
    "<a href=\"", currDir,
    "/reactions.php?species=", species_code,
    "&GeneIDType=ENTREZID",
    "&anatomy=", URLencode(anatomy, reserved = TRUE),
    "&disease=", URLencode(disease, reserved = TRUE),
    "&phenotype=NA",
    "&GeneInfoStr=", clean_geneID_str,
    "\" target=\"_blank\">Reactions</a>"
  )

  metsLinkStr <- paste0(
    "<a href=\"", currDir,
    "/metabolites.php?species=", species_code,
    "&GeneIDType=ENTREZID",
    "&anatomy=", URLencode(anatomy, reserved = TRUE),
    "&disease=", URLencode(disease, reserved = TRUE),
    "&phenotype=NA",
    "&GeneInfoStr=", clean_geneID_str,
    "\" target=\"_blank\">Metabolites</a>"
  )

  studiesLinkStr <- paste0(
    "<a href=\"", currDir,
    "/studies.php?species=", species_code,
    "&GeneIDType=ENTREZID",
    "&anatomy=", URLencode(anatomy, reserved = TRUE),
    "&disease=", URLencode(disease, reserved = TRUE),
    "&phenotype=NA",
    "&GeneInfoStr=", clean_geneID_str,
    "\" target=\"_blank\">Studies</a>"
  )

  plotSummary(
    countMatrix,
    nrow(countMatrix),
    symbolStrArray,
    organism_name,
    pathwaysLinkStr,
    rxnsLinkStr,
    metsLinkStr,
    studiesLinkStr,
    plotFile,
    viewType
  )
}

# ----------------- HARDENED KEGG LIVE VERSION -----------------

getGeneSummaryInfoTableWithKeggQuery <- function(orgStr,
                                                 geneIDArray,
                                                 geneSymArray,
                                                 anatomy = "NA",
                                                 disease = "NA",
                                                 plotFile,
                                                 viewType) {

  species_info  <- normalize_species(orgStr)
  species_code  <- species_info$species_code
  organism_name <- species_info$species_label

  base_dir          <- getwd()
  disease_json_path <- file.path(base_dir, "disease_pulldown_menu_cascaded.json")
  anatomy_html_path <- file.path(base_dir, "ssdm_sample_source_pulldown_menu.html")

  allowed_diseases <- load_allowed_diseases(disease_json_path)
  allowed_anatomy  <- load_allowed_anatomy(anatomy_html_path)

  anatomy_raw <- trimws(ifelse(is.null(anatomy), "", anatomy))
  disease_raw <- trimws(ifelse(is.null(disease), "", disease))

  if (identical(anatomy_raw, "NA")) anatomy_raw <- ""
  if (identical(disease_raw, "NA")) disease_raw <- ""

  if (nzchar(anatomy_raw) && !(anatomy_raw %in% allowed_anatomy)) {
    stop(sprintf(
      "Invalid anatomy term '%s'. Term must come from the controlled SSDM menu.",
      anatomy_raw
    ))
  }
  if (nzchar(disease_raw) && !(disease_raw %in% allowed_diseases)) {
    stop(sprintf(
      "Invalid disease term '%s'. Term must come from the controlled disease menu.",
      disease_raw
    ))
  }

  anatomy <- anatomy_raw
  disease <- disease_raw

  # sanitize gene IDs + symbols
  raw_ids  <- as.vector(strsplit(geneIDArray, split = "__", fixed = TRUE)[[1]])
  raw_syms <- as.vector(strsplit(geneSymArray, split = "__", fixed = TRUE)[[1]])

  max_len <- max(length(raw_ids), length(raw_syms))
  if (length(raw_ids)  < max_len) raw_ids  <- c(raw_ids,  rep(NA_character_, max_len - length(raw_ids)))
  if (length(raw_syms) < max_len) raw_syms <- c(raw_syms, rep(NA_character_, max_len - length(raw_syms)))

  id_pattern <- "^[0-9]+$"

  keep_idx <- which(
    !is.na(raw_ids) &
      nzchar(trimws(raw_ids)) &
      grepl(id_pattern, raw_ids)
  )

  if (length(keep_idx) == 0) {
    stop("No valid gene IDs remain after sanitization.")
  }

  geneArray      <- raw_ids[keep_idx]
  symbolStrArray <- raw_syms[keep_idx]
  clean_geneID_str <- paste(geneArray, collapse = "__")

  countMatrix <- matrix(ncol = 4, nrow = length(geneArray))
  currDir    <- paste0("/", basename(getwd()))

  for (g in seq_along(geneArray)) {
    geneIdStr  <- geneArray[g]
    queryStr   <- paste0(species_code, ":", geneIdStr)

    df <- tryCatch(
      getRxnIDsPthwyIDsCpdIDsFromKEGG(queryStr),
      error = function(e) {
        warning(sprintf("KEGG query failed for %s: %s", queryStr, e$message))
        NULL
      }
    )
    if (is.null(df) || nrow(df) == 0) {
      countMatrix[g, ] <- c(0L, 0L, 0L, 0L)
      next
    }

    cpds   <- df[str_detect(df[, 2], "cpd:"), 2]
    rxns   <- df[str_detect(df[, 2], "rn:"), 2]
    pthwys <- df[str_detect(df[, 2], "path:"), 2]

    pathwayList <- gsub("path:", "", pthwys)
    metabList   <- gsub("cpd:", "", cpds)
    reactions   <- gsub("rn:",   "", rxns)

    genePthwyCnt <- length(unique(pathwayList))
    geneRxnsCnt  <- length(unique(reactions))
    geneMetabCnt <- 0L
    geneStudyCnt <- 0L
    allStudies   <- character(0)

    if (length(metabList) > 0) {
      for (metabStr in metabList) {

        anatomyQryStr <- if (nzchar(anatomy)) gsub("\\+", "%20", anatomy) else ""
        diseaseQryStr <- if (nzchar(disease)) gsub("\\+", "%20", disease) else ""

        mw_url <- paste0(
          "https://www.metabolomicsworkbench.org/rest/metstat/;;;",
          organism_name, ";",
          anatomyQryStr, ";",
          diseaseQryStr, ";",
          metabStr
        )

        jslist <- tryCatch(
          read_json(mw_url, simplifyVector = TRUE),
          error = function(e) NULL
        )
        if (is.null(jslist) || length(jslist) == 0) next

        mydf_studies <- list_of_list_to_df(jslist)
        if (is.null(mydf_studies) || nrow(mydf_studies) == 0) next

        studiesVec <- mydf_studies$study
        refMetVec  <- mydf_studies$refmet_name

        geneMetabCnt <- geneMetabCnt + length(unique(refMetVec))
        allStudies   <- c(allStudies, studiesVec)
      }

      if (geneMetabCnt == 0L) {
        geneMetabCnt <- length(metabList)
      }
      geneStudyCnt <- length(unique(allStudies))
    }

    countMatrix[g, ] <- c(
      as.integer(genePthwyCnt),
      as.integer(geneRxnsCnt),
      as.integer(geneMetabCnt),
      as.integer(geneStudyCnt)
    )
  }

  pathwaysLinkStr <- paste0(
    "<a href=\"", currDir,
    "/pathways.php?species=", species_code,
    "&GeneIDType=ENTREZID",
    "&anatomy=", URLencode(anatomy, reserved = TRUE),
    "&disease=", URLencode(disease, reserved = TRUE),
    "&phenotype=NA",
    "&GeneInfoStr=", clean_geneID_str,
    "\" target=\"_blank\">Pathways</a>"
  )
  rxnsLinkStr <- paste0(
    "<a href=\"", currDir,
    "/reactions.php?species=", species_code,
    "&GeneIDType=ENTREZID",
    "&anatomy=", URLencode(anatomy, reserved = TRUE),
    "&disease=", URLencode(disease, reserved = TRUE),
    "&phenotype=NA",
    "&GeneInfoStr=", clean_geneID_str,
    "\" target=\"_blank\">Reactions</a>"
  )
  metsLinkStr <- paste0(
    "<a href=\"", currDir,
    "/metabolites.php?species=", species_code,
    "&GeneIDType=ENTREZID",
    "&anatomy=", URLencode(anatomy, reserved = TRUE),
    "&disease=", URLencode(disease, reserved = TRUE),
    "&phenotype=NA",
    "&GeneInfoStr=", clean_geneID_str,
    "\" target=\"_blank\">Metabolites</a>"
  )
  studiesLinkStr <- paste0(
    "<a href=\"", currDir,
    "/studies.php?species=", species_code,
    "&GeneIDType=ENTREZID",
    "&anatomy=", URLencode(anatomy, reserved = TRUE),
    "&disease=", URLencode(disease, reserved = TRUE),
    "&phenotype=NA",
    "&GeneInfoStr=", clean_geneID_str,
    "\" target=\"_blank\">Studies</a>"
  )

  plotSummary(
    countMatrix,
    nrow(countMatrix),
    symbolStrArray,
    organism_name,
    pathwaysLinkStr,
    rxnsLinkStr,
    metsLinkStr,
    studiesLinkStr,
    plotFile,
    viewType
  )
}

# ----------------- main -----------------

args <- commandArgs(trailingOnly = TRUE)

if (length(args) < 5) {
  stop("Usage: Rscript extractMWGeneSummary.R <species> <geneIDArr> <geneSymArr> <filename> <viewType> [<anatomy>] [<disease>]")
}

species    <- args[1]
geneIDArr  <- args[2]
geneSymArr <- args[3]
filename   <- args[4]
viewType   <- args[5]

anatomy <- if (length(args) >= 6) args[6] else "NA"
disease <- if (length(args) >= 7) args[7] else "NA"

# tic("Total time elapsed = ")
if (preCompute == 1) {
  getGeneSummaryInfoTable(species, geneIDArr, geneSymArr, anatomy, disease, filename, viewType)
} else {
  getGeneSummaryInfoTableWithKeggQuery(species, geneIDArr, geneSymArr, anatomy, disease, filename, viewType)
}
# toc()
