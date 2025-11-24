#!/usr/bin/env Rscript
# --------------------------------------------------------------------
# Common validation / sanitization helpers shared across MetGENE R code
#
# Includes:
#   - normalize_species()      : map species aliases to code + names (mirrors PHP normalizeSpecies)
#   - sanitize_gene_ids()      : validate and clean gene ID strings
#   - load_allowed_diseases()  : read curated disease list from JSON
#   - load_allowed_anatomy()   : read curated anatomy list from HTML <option> tags
#   - validate_disease()       : check disease against curated + safe free-text
#   - validate_anatomy()       : check anatomy against curated + safe free-text
#
# This file is meant to be sourced from other scripts like
#   - extractMetaboliteInfo.R
#   - others that need consistent validation.
# --------------------------------------------------------------------

suppressPackageStartupMessages({
  library(jsonlite)
})

# --------------------------------------------------------------------
# Species normalization (mirrors PHP normalizeSpecies())
# --------------------------------------------------------------------
# Input: species_raw – arbitrary string (e.g. "hsa", "Human", "Homo sapiens")
# Output: named list with:
#   code       : "hsa" | "mmu" | "rno"
#   org_name   : "Human" | "Mouse" | "Rat"
#   sci_name   : "Homo sapiens" | "Mus musculus" | "Rattus norvegicus"
# --------------------------------------------------------------------
normalize_species <- function(species_raw) {
  human <- c("Human", "human", "hsa", "Homo sapiens")
  mouse <- c("Mouse", "mouse", "mmu", "Mus musculus")
  rat   <- c("Rat", "rat", "rno", "Rattus norvegicus")

  if (species_raw %in% human) {
    return(list(code = "hsa", org_name = "Human", sci_name = "Homo sapiens"))
  }
  if (species_raw %in% mouse) {
    return(list(code = "mmu", org_name = "Mouse", sci_name = "Mus musculus"))
  }
  if (species_raw %in% rat) {
    return(list(code = "rno", org_name = "Rat", sci_name = "Rattus norvegicus"))
  }

  # Sensible default: Human / hsa
  list(code = "hsa", org_name = "Human", sci_name = "Homo sapiens")
}

# --------------------------------------------------------------------
# Gene ID sanitization
# --------------------------------------------------------------------
# raw_ids: a single string, possibly using:
#   - "__" as separator
#   - "," as separator
# Returns: character vector of valid IDs, each matching:
#   [A-Za-z0-9._-]+
# --------------------------------------------------------------------
sanitize_gene_ids <- function(raw_ids) {
  if (is.null(raw_ids) || length(raw_ids) == 0) {
    return(character(0))
  }

  raw_ids <- as.character(raw_ids[[1]])
  if (nchar(raw_ids) == 0) {
    return(character(0))
  }

  tmp   <- gsub("__", ",", raw_ids, fixed = TRUE)
  parts <- unlist(strsplit(tmp, ",", fixed = TRUE))

  pattern <- "^[A-Za-z0-9._-]+$"
  clean   <- character(0)

  for (g in parts) {
    g <- trimws(g)
    if (g == "") next
    if (grepl(pattern, g)) {
      clean <- c(clean, g)
    }
  }

  unique(clean)
}

# --------------------------------------------------------------------
# Load allowed disease names from JSON
#   File: disease_pulldown_menu_cascaded.json
#   Structure (example):
#   {
#     "Cardiovascular disorder": [
#        { "disease_name": "Abdominal aortic aneurysm", ... },
#        { "disease_name": "Angina", ... },
#        ...
#     ],
#     "Metabolic disorder": [ ... ],
#     ...
#   }
# --------------------------------------------------------------------
load_allowed_diseases <- function(
  path = "disease_pulldown_menu_cascaded.json"
) {
  if (!file.exists(path)) {
    return(character(0))
  }

  obj <- jsonlite::fromJSON(path, simplifyDataFrame = TRUE)

  # obj is typically a named list where each element is a data.frame
  # with column "disease_name". Be defensive in case of mixed structure.
  all_names <- unlist(
    lapply(obj, function(group) {
      if (is.data.frame(group) && "disease_name" %in% colnames(group)) {
        group[["disease_name"]]
      } else if (is.list(group)) {
        # Maybe list of lists
        unlist(lapply(group, function(entry) {
          if (is.list(entry) && !is.null(entry[["disease_name"]])) {
            entry[["disease_name"]]
          } else {
            NA_character_
          }
        }), use.names = FALSE)
      } else {
        NA_character_
      }
    }),
    use.names = FALSE
  )

  all_names <- all_names[!is.na(all_names)]
  all_names <- trimws(all_names)
  unique(all_names[nchar(all_names) > 0])
}

# --------------------------------------------------------------------
# Load allowed anatomy names from HTML
#   File: ssdm_sample_source_pulldown_menu.html
#   Structure (example):
#     <option value="Adipose tissue">Adipose tissue</option>
# --------------------------------------------------------------------
load_allowed_anatomy <- function(
  path = "ssdm_sample_source_pulldown_menu.html"
) {
  if (!file.exists(path)) {
    return(character(0))
  }

  html <- paste(readLines(path, warn = FALSE), collapse = "\n")

  # Extract all value="...":
  m <- gregexpr('value="([^"]*)"', html, perl = TRUE)
  matches <- regmatches(html, m)[[1]]

  if (length(matches) == 0) {
    return(character(0))
  }

  values <- sub('^value="([^"]*)"$', "\\1", matches)
  values <- trimws(values)
  values <- values[values != ""]
  unique(values)
}

# --------------------------------------------------------------------
# Validate disease
#   - If empty or "NA" → return "" (no filter)
#   - If matches curated list (case-insensitive) → return canonical
#   - Else if passes simple safe pattern → allow as-is (free text)
#   - Else → ""
# --------------------------------------------------------------------
validate_disease <- function(disease_raw, allowed_diseases = character(0)) {
  if (is.null(disease_raw) || length(disease_raw) == 0) {
    return("")
  }
  d <- trimws(as.character(disease_raw[[1]]))
  if (d == "" || toupper(d) == "NA") {
    return("")
  }

  # Try curated list (case-insensitive)
  if (length(allowed_diseases) > 0) {
    lc_allowed <- tolower(allowed_diseases)
    idx <- match(tolower(d), lc_allowed)
    if (!is.na(idx)) {
      return(allowed_diseases[[idx]])
    }
  }

  # Fallback: free text, but restrict characters
  safe_pattern <- "^[A-Za-z0-9 .,_()\\-+/]+$"
  if (grepl(safe_pattern, d)) {
    return(d)
  }

  ""
}

# --------------------------------------------------------------------
# Validate anatomy
#   - If empty or "NA" → ""
#   - If matches curated list (case-insensitive) → canonical
#   - Else if passes safe free-text pattern → allow
#   - Else → ""
# --------------------------------------------------------------------
validate_anatomy <- function(anatomy_raw, allowed_anatomy = character(0)) {
  if (is.null(anatomy_raw) || length(anatomy_raw) == 0) {
    return("")
  }

  a <- trimws(as.character(anatomy_raw[[1]]))
  if (a == "" || toupper(a) == "NA") {
    return("")
  }

  if (length(allowed_anatomy) > 0) {
    lc_allowed <- tolower(allowed_anatomy)
    idx <- match(tolower(a), lc_allowed)
    if (!is.na(idx)) {
      return(allowed_anatomy[[idx]])
    }
  }

  safe_pattern <- "^[A-Za-z0-9 .,_()\\-+/]+$"
  if (grepl(safe_pattern, a)) {
    return(a)
  }

  ""
}
