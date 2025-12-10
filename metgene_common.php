<?php
/******************************************************************************
 * metgene_common.php  — with DEBUGGING SUPPORT
 *
 * Adds:
 *   - $METGENE_DEBUG (master switch)
 *   - $METGENE_LOGFILE (log destination)
 *   - dbg($msg) helper (writes to log + HTML comments)
 *
 * No existing logic modified. Debugging is fully optional.
 ******************************************************************************/

/* ============================================================
   DEBUG CONFIGURATION
   ============================================================ */

# Turn ON for debugging, OFF for production
$METGENE_DEBUG = false;     // <<--- SET TO false IN PRODUCTION

# Where logs will be written (make sure directory is writable)
$METGENE_LOGFILE = __DIR__ . "/cache/metgene_debug.log";

# Safe debug logger
# Safe debug logger — ALWAYS prints to page when debugging is on
if (!function_exists('dbg')) {
    function dbg(string $msg): void
    {
        global $METGENE_DEBUG, $METGENE_LOGFILE;

        if (!$METGENE_DEBUG) return;

        $timestamp = date("Y-m-d H:i:s");
        $line = "[$timestamp] $msg";

        // 1. Try writing to log file (if permissions allow)
        @error_log($line . "\n", 3, $METGENE_LOGFILE);

        // 2. Emit as HTML comment (visible in "view-source")
        echo "<!-- DBG: " . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . " -->\n";

        // 3. Guarantee-visible debug output on the page (during development)
        echo "<div style='background:#ffe0e0; color:#800; padding:4px; 
                     margin:2px; border:1px solid #a00; font-size:12px; 
                     font-family:monospace;'>
                    DEBUG: " . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "
              </div>";
    }
}


/******************************************************************************
 * Existing MetGENE utility functions
 * (All untouched — only debug support added above)
 ******************************************************************************/

function sendSecurityHeaders(): void
{
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: no-referrer-when-downgrade");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' https://code.jquery.com https://requirejs.org;" .
        "style-src 'self' 'unsafe-inline'; " .
        "img-src 'self' data:; " .
        "object-src 'none'; " .
        "frame-ancestors 'self';"
    );
}

function escapeHtml(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Backward-compatible alias: escape_html() → escapeHtml()
if (!function_exists('escape_html')) {
    function escape_html(string $v): string
    {
        return escapeHtml($v);
    }
}


function safeGet(string $key, string $default = ''): string
{
    if (!isset($_GET[$key]) || is_array($_GET[$key])) {
        return $default;
    }
    return trim((string) $_GET[$key]);
}

function safePost(string $key, string $default = ''): string
{
    if (!isset($_POST[$key]) || is_array($_POST[$key])) {
        return $default;
    }
    return trim((string) $_POST[$key]);
}

function safeSession(string $key, string $default = ''): string
{
    if (!isset($_SESSION[$key]) || is_array($_SESSION[$key])) {
        return $default;
    }
    return trim((string) $_SESSION[$key]);
}

function getBaseDirName(): string
{
    $curDir = dirname($_SERVER['PHP_SELF'] ?? '');
    $sanitized = preg_replace('#[^0-9A-Za-z/_-]#', '', $curDir);
    // Remove trailing slash to prevent double slashes when concatenating
    return rtrim($sanitized, '/');
}

function getNavIncludePath(string $baseDir, string $fileName): string
{
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    return $docRoot . $baseDir . '/' . ltrim($fileName, '/');
}

function buildInternalUrl(string $baseDir, string $script, array $params = []): string
{
    $query = http_build_query($params);
    $url   = rtrim($baseDir, '/') . '/' . ltrim($script, '/');
    if ($query !== '') {
        $url .= '?' . $query;
    }
    return $url;
}

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
    return ['hsa', 'Human', 'Homo sapiens'];
}

function validateGeneIDType(string $geneIDType)
{
    $allowed = ["SYMBOL","SYMBOL_OR_ALIAS","ENTREZID","ENSEMBL","REFSEQ","UNIPROT"];
    return in_array($geneIDType, $allowed, true) ? $geneIDType : 'NA';
}

function cleanGeneList(string $raw): array
{
    if ($raw === "") {
        return [];
    }
    $tmp = str_replace("__", ",", $raw);
    $parts = explode(",", $tmp);
    $clean = [];

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
        if (!is_array($entries)) continue;
        foreach ($entries as $entry) {
            if (isset($entry['disease_name'])) {
                $allowed[] = $entry['disease_name'];
            }
        }
    }

    return [$data, $allowed];
}

function validateDiseaseValue(string $disease, array $allowed): string
{
    return in_array($disease, $allowed, true) ? $disease : 'NA';
}

function loadAnatomyValuesFromHtml(string $htmlFile): array
{
    $allowed = ['NA'];

    if (!is_readable($htmlFile)) {
        return $allowed;
    }

    $html = @file_get_contents($htmlFile);
    if ($html === false) {
        return $allowed;
    }

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

function validateAnatomyValue(string $anatomy, array $allowed): string
{
    return in_array($anatomy, $allowed, true) ? $anatomy : 'NA';
}

function buildCacheFilePath(string $cacheDir, string $scriptName, string $sessionId): string
{
    $baseName = preg_replace('/[^A-Za-z0-9_-]/', '', pathinfo($scriptName, PATHINFO_FILENAME));
    $sessionSafe = preg_replace('/[^A-Za-z0-9]/', '', $sessionId);
    return rtrim($cacheDir, '/') . '/cached-' . $sessionSafe . '-' . $baseName . '.html';
}

function getBaseDir(): string
{
    return getBaseDirName();
}

function buildRscriptCommand(string $scriptName, array $args = []): string
{
    // Validate script path
    $scriptPath = realpath(__DIR__ . "/" . $scriptName);
    
    if ($scriptPath === false || !is_readable($scriptPath)) {
        error_log("R script not found or not readable: $scriptName");
        return '';
    }
    
    // Build command with escaped arguments
    $cmd = "/usr/bin/Rscript " . escapeshellarg($scriptPath);
    
    foreach ($args as $arg) {
        $cmd .= " " . escapeshellarg($arg);
    }
    
    return $cmd;
}

/**
 * Generate export buttons with configuration
 */
function generateExportButtons(array $dataArray, string $entityType, string $prefix = 'Gene', string $suffix = 'Table'): string
{
    $config = [
        'geneArray' => $dataArray,
        'entityType' => $entityType,
        'tableIdPrefix' => $prefix,
        'tableIdSuffix' => $suffix
    ];
    
    $jsonConfig = json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    
    return '<p id="export-buttons" data-export-config="' . 
           escapeHtml($jsonConfig) . 
           '"><button id="json">TO JSON</button> <button id="csv">TO CSV</button></p>';
}