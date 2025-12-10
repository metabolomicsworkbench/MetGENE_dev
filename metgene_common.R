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
#   - html_escape()            : SECURITY: escape HTML special characters
#   - safe_view_type()         : SECURITY: whitelist validation for view/output types
#   - safe_read_rds()          : SECURITY: validate file paths before reading RDS files
#   - validate_entrez_ids()    : SECURITY: strict validation for ENTREZ gene IDs
#   - validate_gene_id_type()  : SECURITY: whitelist validation for gene ID types
#   - sanitize_gene_info()     : SECURITY: sanitize gene info strings for API calls
#
# This file is meant to be sourced from other scripts like
#   - extractMetaboliteInfo.R
#   - extractReactionInfo.R
#   - extractFilteredStudiesInfo.R
#   - extractGeneIDsAndSymbols.R
#   - others that need consistent validation.
# --------------------------------------------------------------------

suppressPackageStartupMessages({
    library(jsonlite)
})

# ====================================================================
# SECURITY FUNCTIONS
# ====================================================================

# --------------------------------------------------------------------
# HTML escaping to prevent XSS in generated HTML output
# --------------------------------------------------------------------
# SECURITY: Use this for ALL user/external data inserted into HTML
# --------------------------------------------------------------------
html_escape <- function(x) {
    if (is.null(x) || length(x) == 0) {
        return(character(0))
    }

    x <- as.character(x)
    x <- gsub("&", "&amp;", x, fixed = TRUE)
    x <- gsub("<", "&lt;", x, fixed = TRUE)
    x <- gsub(">", "&gt;", x, fixed = TRUE)
    x <- gsub("\"", "&quot;", x, fixed = TRUE)
    x <- gsub("'", "&#39;", x, fixed = TRUE)
    return(x)
}

# --------------------------------------------------------------------
# Validate view/output type with strict whitelist
# --------------------------------------------------------------------
# SECURITY: Prevents arbitrary strings being used as output format
# Input: raw view type string
# Output: validated view type or stops with error
# --------------------------------------------------------------------
safe_view_type <- function(vt) {
    if (is.null(vt) || length(vt) == 0) {
        stop("viewType cannot be NULL or empty", call. = FALSE)
    }

    v <- tolower(trimws(as.character(vt[[1]])))
    allowed <- c("json", "txt", "text", "html", "table", "all", "png", "jsonfile")

    if (v %in% allowed) {
        return(v)
    }

    stop("Invalid viewType: '", v, "'. Must be one of: ",
        paste(allowed, collapse = ", "),
        call. = FALSE
    )
}

# --------------------------------------------------------------------
# Safe RDS file reading with path validation
# --------------------------------------------------------------------
# SECURITY: Prevents path traversal attacks when reading RDS files
# Args:
#   orgStr: validated species code (hsa, mmu, rno)
#   filename_pattern: the suffix pattern (e.g., "_keggLink_mg.RDS")
#   base_dir: base directory (default: "data")
# Returns: contents of RDS file
# --------------------------------------------------------------------
safe_read_rds <- function(orgStr, filename_pattern, base_dir = "data") {
    # Construct filename from validated inputs
    filename <- paste0(orgStr, filename_pattern)

    # Build file path
    filepath <- file.path(base_dir, filename)

    # Normalize paths to absolute
    filepath <- normalizePath(filepath, mustWork = FALSE)
    data_dir <- normalizePath(base_dir, mustWork = TRUE)

    # SECURITY: Ensure file path is within data directory (prevent path traversal)
    if (!startsWith(filepath, data_dir)) {
        stop("SECURITY: Invalid file path detected - path traversal attempt blocked",
            call. = FALSE
        )
    }

    # Check file exists
    if (!file.exists(filepath)) {
        stop("Missing RDS file: ", basename(filepath), call. = FALSE)
    }

    # Read and return
    tryCatch(
        readRDS(filepath),
        error = function(e) {
            stop("Failed to read RDS file '", basename(filepath), "': ",
                e$message,
                call. = FALSE
            )
        }
    )
}

# --------------------------------------------------------------------
# Validate ENTREZ gene IDs with strict pattern
# --------------------------------------------------------------------
# SECURITY: Ensures gene IDs contain only digits and commas
# Args:
#   gene_str: string containing gene IDs (comma-separated or single)
#   max_length: maximum allowed length (default: 1000)
# Returns: cleaned gene string or stops with error
# --------------------------------------------------------------------
validate_entrez_ids <- function(gene_str, max_length = 1000) {
    if (is.null(gene_str) || length(gene_str) == 0) {
        stop("Gene ID string cannot be NULL or empty", call. = FALSE)
    }

    g <- trimws(as.character(gene_str[[1]]))

    if (nchar(g) == 0) {
        stop("Gene ID string is empty after trimming", call. = FALSE)
    }

    # SECURITY: Enforce maximum length to prevent DoS
    if (nchar(g) > max_length) {
        stop("Gene ID string too long (max ", max_length, " characters)",
            call. = FALSE
        )
    }

    # SECURITY: Only allow digits and commas (strict ENTREZ ID format)
    if (!grepl("^[0-9,]+$", g)) {
        stop("Gene IDs must contain only digits and commas", call. = FALSE)
    }

    # Additional check: no consecutive commas, no leading/trailing commas
    if (grepl(",,", g) || grepl("^,|,$", g)) {
        stop("Invalid gene ID format: consecutive, leading, or trailing commas",
            call. = FALSE
        )
    }

    return(g)
}

# --------------------------------------------------------------------
# Validate gene ID type with strict whitelist
# --------------------------------------------------------------------
# SECURITY: Ensures gene ID type is one of allowed types
# Input: raw gene ID type string
# Output: validated, uppercase gene ID type or stops with error
# --------------------------------------------------------------------
validate_gene_id_type <- function(geneIDType) {
    allowed_types <- c(
        "SYMBOL",
        "SYMBOL_OR_ALIAS",
        "ALIAS",
        "ENTREZID",
        "GENENAME",
        "ENSEMBL",
        "REFSEQ",
        "UNIPROT",
        "HGNC"
    )

    geneIDType_upper <- toupper(trimws(geneIDType))

    if (!geneIDType_upper %in% allowed_types) {
        stop("Invalid GeneIDType: ", geneIDType, ". Must be one of: ",
            paste(allowed_types, collapse = ", "),
            call. = FALSE
        )
    }

    return(geneIDType_upper)
}

# --------------------------------------------------------------------
# Sanitize gene info string for API calls
# --------------------------------------------------------------------
# SECURITY: Validates gene info string format and length
# Input: raw gene info string (comma/underscore separated gene IDs)
# Output: sanitized gene info string or stops with error
# Args:
#   geneInfoStr: string containing gene identifiers
#   max_length: maximum allowed length (default: 2000)
# --------------------------------------------------------------------
sanitize_gene_info <- function(geneInfoStr, max_length = 2000) {
    if (is.null(geneInfoStr) || length(geneInfoStr) == 0) {
        stop("Gene info string cannot be NULL or empty", call. = FALSE)
    }

    gene_str <- trimws(as.character(geneInfoStr[[1]]))

    if (nchar(gene_str) == 0) {
        stop("Gene info string is empty after trimming", call. = FALSE)
    }

    # SECURITY: Enforce maximum length to prevent DoS
    if (nchar(gene_str) > max_length) {
        stop("Gene info string too long (max ", max_length, " characters)",
            call. = FALSE
        )
    }

    # SECURITY: Only allow safe characters
    # Allow: alphanumeric, space, comma, underscore, hyphen, period, colon
    # This covers most gene identifier formats
    if (!grepl("^[A-Za-z0-9 ,._:\\-]+$", gene_str)) {
        stop("Gene info string contains invalid characters. ",
            "Allowed: letters, numbers, spaces, commas, underscores, hyphens, periods, colons",
            call. = FALSE
        )
    }

    return(gene_str)
}

# ====================================================================
# ORIGINAL VALIDATION FUNCTIONS (Enhanced for Security)
# ====================================================================

# --------------------------------------------------------------------
# Species normalization (mirrors PHP normalizeSpecies())
# --------------------------------------------------------------------
# Input: species_raw – arbitrary string (e.g. "hsa", "Human", "Homo sapiens")
# Output: named list with:
#   code       : "hsa" | "mmu" | "rno"
#   org_name   : "Human" | "Mouse" | "Rat"
#   sci_name   : "Homo sapiens" | "Mus musculus" | "Rattus norvegicus"
# --------------------------------------------------------------------
normalize_species <- function(species) {
    species <- trimws(species)

    human <- c("Human", "human", "hsa", "Homo sapiens", "Homo sapiens (Human)")
    mouse <- c("Mouse", "mouse", "mmu", "Mus musculus", "Mus musculus (Mouse)")
    rat <- c("Rat", "rat", "rno", "Rattus norvegicus", "Rattus norvegicus (Rat)")

    if (species %in% human) {
        return(list(
            species_code = "hsa",
            species_label = "Human",
            species_scientific = "Homo sapiens"
        ))
    }
    if (species %in% mouse) {
        return(list(
            species_code = "mmu",
            species_label = "Mouse",
            species_scientific = "Mus musculus"
        ))
    }
    if (species %in% rat) {
        return(list(
            species_code = "rno",
            species_label = "Rat",
            species_scientific = "Rattus norvegicus"
        ))
    }

    # Default: human
    return(list(
        species_code = "hsa",
        species_label = "Human",
        species_scientific = "Homo sapiens"
    ))
}


# --------------------------------------------------------------------
# Gene ID sanitization
# --------------------------------------------------------------------
# raw_ids: a single string, possibly using:
#   - "__" as separator
#   - "," as separator
# Returns: character vector of valid IDs, each matching:
#   [A-Za-z0-9._-]+
# SECURITY: Enhanced with max length check per ID
# --------------------------------------------------------------------
sanitize_gene_ids <- function(raw_ids, max_id_length = 50) {
    if (is.null(raw_ids) || length(raw_ids) == 0) {
        return(character(0))
    }

    raw_ids <- as.character(raw_ids[[1]])
    if (nchar(raw_ids) == 0) {
        return(character(0))
    }

    tmp <- gsub("__", ",", raw_ids, fixed = TRUE)
    parts <- unlist(strsplit(tmp, ",", fixed = TRUE))

    pattern <- "^[A-Za-z0-9._-]+$"
    clean <- character(0)

    for (g in parts) {
        g <- trimws(g)
        if (g == "") next

        # SECURITY: Check individual ID length
        if (nchar(g) > max_id_length) {
            warning("Gene ID too long, skipping: ", substr(g, 1, 20), "...")
            next
        }

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
# SECURITY: Enhanced with error handling and path validation
# --------------------------------------------------------------------
load_allowed_diseases <- function(
    path = "disease_pulldown_menu_cascaded.json") {
    # SECURITY: Validate path exists and is readable
    if (!file.exists(path)) {
        warning("Disease file not found: ", path)
        return(character(0))
    }

    # SECURITY: Catch JSON parsing errors
    obj <- tryCatch(
        jsonlite::fromJSON(path, simplifyDataFrame = TRUE),
        error = function(e) {
            warning("Failed to parse disease JSON: ", e$message)
            return(NULL)
        }
    )

    if (is.null(obj)) {
        return(character(0))
    }

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
# SECURITY: Enhanced with error handling and path validation
# --------------------------------------------------------------------
load_allowed_anatomy <- function(
    path = "ssdm_sample_source_pulldown_menu.html") {
    # SECURITY: Validate path exists
    if (!file.exists(path)) {
        warning("Anatomy file not found: ", path)
        return(character(0))
    }

    # SECURITY: Safe file reading with error handling
    html <- tryCatch(
        paste(readLines(path, warn = FALSE), collapse = "\n"),
        error = function(e) {
            warning("Failed to read anatomy HTML: ", e$message)
            return("")
        }
    )

    if (html == "") {
        return(character(0))
    }

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
# SECURITY: Enhanced pattern to be more restrictive
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

    # SECURITY: Fallback - free text, but restrict characters
    # Allow: letters, numbers, spaces, common punctuation
    # Maximum length: 100 characters
    if (nchar(d) > 100) {
        warning("Disease name too long (max 100 chars), truncating")
        d <- substr(d, 1, 100)
    }

    safe_pattern <- "^[A-Za-z0-9 .,_()\\-+/]+$"
    if (grepl(safe_pattern, d)) {
        return(d)
    }

    # SECURITY: If pattern doesn't match, return empty (don't allow arbitrary input)
    warning("Disease name contains invalid characters, ignoring: ", d)
    ""
}

# --------------------------------------------------------------------
# Validate anatomy
#   - If empty or "NA" → ""
#   - If matches curated list (case-insensitive) → canonical
#   - Else if passes safe free-text pattern → allow
#   - Else → ""
# SECURITY: Enhanced pattern to be more restrictive
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

    # SECURITY: Fallback - free text with restrictions
    # Maximum length: 100 characters
    if (nchar(a) > 100) {
        warning("Anatomy name too long (max 100 chars), truncating")
        a <- substr(a, 1, 100)
    }

    safe_pattern <- "^[A-Za-z0-9 .,_()\\-+/]+$"
    if (grepl(safe_pattern, a)) {
        return(a)
    }

    # SECURITY: If pattern doesn't match, return empty
    warning("Anatomy name contains invalid characters, ignoring: ", a)
    ""
}
# ====================================================================
# SPECIES NAME FORMATTING
# ====================================================================
# --------------------------------------------------------------------
# Get URL-encoded species scientific name
# --------------------------------------------------------------------
# Used for external pathway database URLs (Reactome, WikiPathways, etc.)
# Input: species code (hsa/mmu/rno) or any species alias
# Output: URL-encoded scientific name (e.g., "Homo+sapiens")
# --------------------------------------------------------------------
get_species_url_name <- function(species) {
    sp_info <- normalize_species(species)

    # Return URL-encoded format (spaces as +)
    scientific_name <- sp_info$species_scientific
    gsub(" ", "+", scientific_name, fixed = TRUE)
}
# ====================================================================
# INITIALIZATION MESSAGE (optional, for debugging)
# ====================================================================
# Uncomment to see when this file is sourced:
# message("MetGENE common validation functions loaded (metgene_common.R)")
