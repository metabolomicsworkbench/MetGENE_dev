<?php
/******************************************************************************
 * metgene_common.php
 *
 * Shared utility functions for MetGENE PHP pages.
 *
 * GOALS:
 *  - Centralize security-sensitive logic (escaping, input validation, headers)
 *  - Avoid duplicated code between index.php, metGene.php, geneInfo.php, etc.
 *  - Make it easy to reason about how inputs are validated and outputs escaped
 *
 * FUNCTIONS PROVIDED:
 *  - sendSecurityHeaders()      : sets common HTTP security headers
 *  - escapeHtml()               : safe HTML escaping for all output
 *  - safeGet()                  : safe access to $_GET with scalar-only semantics
 *  - safePost()                 : (optional) safe access to $_POST
 *  - safeSession()              : safe access to $_SESSION with scalar-only semantics
 *  - getBaseDirName()           : safely compute base directory URL path for the app
 *  - getNavIncludePath()        : safe path builder for nav/footer includes
 *  - buildInternalUrl()         : safe helper to build app-relative URLs with query params
 *  - normalizeSpecies()         : normalize species code + human/scientific name
 *  - loadDiseaseSlimMap()       : load + validate hierarchical disease JSON
 *  - validateDiseaseValue()     : restrict disease to whitelist from JSON
 *  - loadAnatomyValuesFromHtml(): (optional) parse anatomy HTML fragment for whitelisting
 ******************************************************************************/

/**
 * Send common security headers.
 *
 * WHY:
 *  - X-Frame-Options: prevents clickjacking by disallowing framing by other sites.
 *  - X-Content-Type-Options: prevents MIME type sniffing (reduces XSS vectors).
 *  - Referrer-Policy: avoids leaking sensitive URLs to third-party sites.
 *  - Permissions-Policy: disables unused dangerous browser features (camera/mic).
 *  - Content-Security-Policy: constrains where scripts/styles/images can load from.
 *
 * HOW IT AVERTS ATTACKS:
 *  - Reduces the impact of XSS bugs if one slips through.
 *  - Prevents other sites from embedding MetGENE UI in hostile contexts.
 */
function sendSecurityHeaders(): void
{
    // These header() calls are idempotent per request; calling multiple times is safe.
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: no-referrer-when-downgrade");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

    // CSP tuned for MetGENE: only local assets + two CDNs for JS are allowed.
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' https://code.jquery.com https://requirejs.org; " .
        "style-src 'self' 'unsafe-inline'; " .
        "img-src 'self' data:; " .
        "object-src 'none'; " .
        "frame-ancestors 'self';"
      );
}

/**
 * HTML escaping helper.
 *
 * WHY:
 *  - Any string that may contain user/session/URL data MUST be escaped before
 *    being printed into HTML to avoid Cross-Site Scripting (XSS).
 *
 * HOW IT AVERTS ATTACKS:
 *  - Converts characters like `<`, `>`, `"`, `'` into safe HTML entities so that
 *    they cannot terminate tags or inject scripts.
 */
function escapeHtml(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Safely read a GET parameter as a scalar string.
 *
 * - If the parameter is missing or an array (e.g., ?x[]=1&x[]=2), returns default.
 * - Trims whitespace.
 *
 * WHY:
 *  - Prevents unexpected array values that could break code or introduce bugs.
 *  - Provides a single place to reason about how query parameters are consumed.
 */
function safeGet(string $key, string $default = ''): string
{
    if (!isset($_GET[$key]) || is_array($_GET[$key])) {
        return $default;
    }
    return trim((string) $_GET[$key]);
}

/**
 * Safely read a POST parameter as a scalar string.
 *
 * (You may not need this yet, but it’s useful for any POST-based forms.)
 */
function safePost(string $key, string $default = ''): string
{
    if (!isset($_POST[$key]) || is_array($_POST[$key])) {
        return $default;
    }
    return trim((string) $_POST[$key]);
}

/**
 * Safely read a SESSION value as a scalar string.
 *
 * WHY:
 *  - Session data, while server-side, may still be influenced by earlier
 *    user inputs. We treat it as untrusted and sanitize similarly.
 */
function safeSession(string $key, string $default = ''): string
{
    if (!isset($_SESSION[$key]) || is_array($_SESSION[$key])) {
        return $default;
    }
    return trim((string) $_SESSION[$key]);
}

/**
 * Compute the base directory of the application in URL space.
 *
 * Example:
 *   If SCRIPT_NAME is "/MetGENE_dev/index.php" => returns "/MetGENE_dev"
 *
 * WHY:
 *  - We need this to build links like /MetGENE_dev/geneInfo.php safely,
 *    regardless of where the app is deployed.
 *
 * HOW IT AVERTS ATTACKS:
 *  - The regexp strips anything except allowed path characters to reduce risk
 *    of header injection or weird path manipulation.
 */
function getBaseDirName(): string
{
    $curDir = dirname($_SERVER['PHP_SELF'] ?? '');
    // Allow only alphanumerics, slash, underscore, dash.
    return preg_replace('#[^0-9A-Za-z/_-]#', '', $curDir);
}

/**
 * Build an absolute filesystem path for a nav/footer PHP include.
 *
 * WHY:
 *  - Avoids sprinkling `$_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME` logic
 *    everywhere and ensures we only include from expected locations.
 */
function getNavIncludePath(string $baseDir, string $fileName): string
{
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    return $docRoot . $baseDir . '/' . ltrim($fileName, '/');
}

/**
 * Build an internal MetGENE URL like:
 *   /MetGENE_dev/geneInfo.php?GeneInfoStr=...
 *
 * PARAMETERS:
 *  - $baseDir:   output of getBaseDirName() (e.g., "/MetGENE_dev")
 *  - $script:    target PHP file (e.g., "geneInfo.php")
 *  - $params:    associative array of query parameters
 *
 * WHY:
 *  - Centralized helper for building links keeps encoding consistent and safe.
 *
 * HOW IT AVERTS ATTACKS:
 *  - Uses http_build_query (built-in) to safely URL-encode parameter values.
 */
function buildInternalUrl(string $baseDir, string $script, array $params = []): string
{
    $query = http_build_query($params);
    $url   = rtrim($baseDir, '/') . '/' . ltrim($script, '/');
    if ($query !== '') {
        $url .= '?' . $query;
    }
    return $url;
}

/**
 * Normalize species to a canonical short code and human/scientific names.
 *
 * INPUT:
 *  - $species: user or session-provided species identifier.
 *
 * OUTPUT:
 *  - array: [ $normalizedCode, $displayName, $scientificName ]
 *
 * Example:
 *  - "Homo sapiens", "hsa", "Human" => ["hsa", "Human", "Homo sapiens"]
 *
 * WHY:
 *  - Several pages need the species code and human-readable label.
 *  - Centralizing logic ensures consistent behavior & easier whitelisting.
 *
 * HOW IT AVERTS ATTACKS:
 *  - We only ever pass normalized, known codes to downstream file paths and
 *    external links (e.g., KEGG). Unknown inputs get mapped or rejected by caller.
 */
function normalizeSpecies(string $species): array
{
    $human = ["Human","human","hsa","Homo sapiens"];
    $mouse = ["Mouse","mouse","mmu","Mus musculus"];
    $rat   = ["Rat","rat","rno","Rattus norvegicus"];

    if (in_array($species, $human, true)) {
        return ['hsa', 'Human', 'Homo sapiens'];
    }
    if (in_array($species, $mouse, true)) {
        return ['mmu', 'Mouse', 'Mus musculus'];
    }
    if (in_array($species, $rat, true)) {
        return ['rno', 'Rat', 'Rattus norvegicus'];
    }

    // Sensible default: Human/hsa
    return ['hsa', 'Human', 'Homo sapiens'];
}
/**
 * 
 * 
 */

 function validateGeneIDType(string $geneIDType) {
    $allowedGeneIDTypes = ["SYMBOL","SYMBOL_OR_ALIAS","ENTREZID","ENSEMBL","REFSEQ","UNIPROT"];
    return in_array($geneIDType, $allowedGeneIDTypes, true) ? $geneIDType : 'NA';

 }

/**
 * Sanitize a raw gene list (GeneInfoStr, GeneID, ENSEMBL, etc.)
 * Accepts alphanumeric + . _ -
 * Allows "__" or "," as separators.
 *
 * @param string $raw
 * @return array Clean list of gene IDs/symbols
 */
function cleanGeneList(string $raw): array
{
    if ($raw === "") {
        return [];
    }

    // Normalize separators: "__" → ","
    $tmp = str_replace("__", ",", $raw);

    $parts = explode(",", $tmp);
    $clean = [];

    // Alphanumeric + dot, underscore, dash
    $pattern = '/^[A-Za-z0-9._-]+$/';

    foreach ($parts as $g) {
        $g = trim($g);
        if ($g === "") continue;
        if (preg_match($pattern, $g)) {
            $clean[] = $g;
        }
    }

    return $clean;
}


/**
 * Load the hierarchical disease JSON used for disease_slim + disease dropdowns.
 *
 * INPUT:
 *  - $jsonFile: path to disease_pulldown_menu_cascaded.json
 *
 * OUTPUT:
 *  - array [$diseaseSlimMap, $allowedDiseaseNames]
 *    where:
 *      - $diseaseSlimMap is the full decoded JSON array
 *      - $allowedDiseaseNames is a flat whitelist of all disease_name values
 *
 * WHY:
 *  - Used in index.php and potentially other pages to restrict allowed diseases.
 *
 * HOW IT AVERTS ATTACKS:
 *  - If JSON is malformed or unreadable, returns empty structures so that no
 *    unvalidated user-provided disease values are trusted.
 */
function loadDiseaseSlimMap(string $jsonFile): array
{
    if (!is_readable($jsonFile)) {
        return [[], []];
    }

    $raw  = @file_get_contents($jsonFile);
    $data = is_string($raw) ? json_decode($raw, true) : null;

    if (!is_array($data)) {
        return [[], []];
    }

    $allowed = [];
    foreach ($data as $cat => $entries) {
        if (!is_array($entries)) {
            continue;
        }
        foreach ($entries as $entry) {
            if (isset($entry['disease_name'])) {
                $allowed[] = $entry['disease_name'];
            }
        }
    }

    return [$data, $allowed];
}

/**
 * Validate a disease value against the allowed list from loadDiseaseSlimMap().
 *
 * If the value is not in the whitelist, returns "NA".
 *
 * WHY:
 *  - Ensures downstream pages and R scripts only receive known diseases.
 *  - Prevents injection of arbitrary strings (used later in queries/filters).
 */
function validateDiseaseValue(string $disease, array $allowed): string
{
    return in_array($disease, $allowed, true) ? $disease : 'NA';
}

/**
 * Parse allowed anatomy values from the static HTML fragment
 * e.g., ssdm_sample_source_pulldown_menu.html.
 *
 * It looks for patterns like:
 *
 *   <option value="Adipose tissue">Adipose tissue</option>
 *
 * OUTPUT:
 *  - array of allowed "value" strings, including "" if present.
 *
 * WHY:
 *  - This lets index.php and others treat the HTML file as the single
 *    source of truth for anatomy options, while still validating user input
 *    server-side against the parsed list.
 *
 * HOW IT AVERTS ATTACKS:
 *  - Even if a user crafts a URL with anatomy=HACK<script>, the application
 *    can verify that the value was actually defined in the HTML list.
 */
function loadAnatomyValuesFromHtml(string $htmlFile): array
{
    $allowed = ['NA']; // Always treat "NA" as safe sentinel

    if (!is_readable($htmlFile)) {
        return $allowed;
    }

    $html = @file_get_contents($htmlFile);
    if ($html === false) {
        return $allowed;
    }

    // Regex to capture value="...". This is intentionally simple and narrow.
    if (preg_match_all('#<option\s+value="([^"]*)"#i', $html, $matches)) {
        foreach ($matches[1] as $val) {
            $val = trim($val);
            if (!in_array($val, $allowed, true)) {
                $allowed[] = $val;
            }
        }
    }

    return $allowed;
}

/**
 * Validate an anatomy value against the whitelist from loadAnatomyValuesFromHtml().
 *
 * If not in allowed list, returns "NA".
 *
 * WHY:
 *  - Prevents arbitrary anatomy strings from being propagated into downstream
 *    filters or external calls.
 */
function validateAnatomyValue(string $anatomy, array $allowed): string
{
    return in_array($anatomy, $allowed, true) ? $anatomy : 'NA';
}

/**
 * Compute a safe cache file path for a given script + session.
 *
 * This is optional but useful for any page implementing HTML output caching.
 *
 * HOW IT AVERTS ATTACKS:
 *  - Restricts the cache file name to alphanumerics, dash, underscore.
 *  - Ensures all cache files live under a known cache directory (no traversal).
 */
function buildCacheFilePath(string $cacheDir, string $scriptName, string $sessionId): string
{
    $baseName = preg_replace('/[^A-Za-z0-9_-]/', '', pathinfo($scriptName, PATHINFO_FILENAME));
    $sessionSafe = preg_replace('/[^A-Za-z0-9]/', '', $sessionId);
    return rtrim($cacheDir, '/') . '/cached-' . $sessionSafe . '-' . $baseName . '.html';
}
