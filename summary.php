<?php
/******************************************************************************
 * MetGENE – Hardened summary.php (SECURE & CONSISTENT)
 *
 * BEHAVIOR:
 *  - If ?viewType=json|txt  → returns raw JSON/TXT from R (no HTML shell)
 *  - Else                  → full HTML page with summary table + export buttons
 *
 * SECURITY:
 *  - Uses metgene_common.php (safeGet, sendSecurityHeaders, getBaseDir,
 *    buildRscriptCommand, escapeHtml, normalizeSpecies, sanitizeGeneList)
 *  - No direct unsanitized $_GET usage in HTML branch
 *  - All exec() calls go through buildRscriptCommand()
 *  - All HTML from PHP is escaped; R-generated HTML is passed through untouched
 *****************************************************************************/

declare(strict_types=1);
session_start();

require_once __DIR__ . '/metgene_common.php';

sendSecurityHeaders();

$base_dir = getBaseDir();

/* --------------------------------------------------------------------------
 * BRANCH ON viewType (API vs HTML)
 * -------------------------------------------------------------------------- */
$viewType = safeGet('viewType');

if ($viewType === 'json' || $viewType === 'txt') {
    /**************************************************************************
     * API BRANCH: return JSON or text summary (no HTML shell)
     **************************************************************************/

    // ---- Sanitized GET parameters ----
    $speciesRaw  = safeGet('species');
    $geneListRaw = safeGet('GeneInfoStr');
    $geneIDType  = safeGet('GeneIDType');
    $anatomyRaw  = safeGet('anatomy');
    $diseaseRaw  = safeGet('disease');

    // Normalize species (e.g., hsa/mmu/etc.)
    $norm = normalizeSpecies($speciesRaw);
    $species = is_array($norm) ? $norm[0] : $speciesRaw;

    // Whitelist geneIDType
    $allowedGeneTypes = ['SYMBOL', 'SYMBOL_OR_ALIAS', 'ENTREZID', 'ENSEMBL', 'REFSEQ', 'UNIPROT'];
    if (!in_array($geneIDType, $allowedGeneTypes, true)) {
        $geneIDType = 'SYMBOL';
    }

    // Sanitize gene list into "__"-separated IDs
    $cleanGenesArray = sanitizeGeneList($geneListRaw);
    $geneListClean   = implode('__', $cleanGenesArray);

    $domainName = $_SERVER['SERVER_NAME'] ?? 'localhost';

    // ---- 1) Map input IDs → (symbols, internal IDs) via R ----
    $cmdIds = buildRscriptCommand(
        'extractGeneIDsAndSymbols.R',
        [
            $species,
            $geneListClean,
            $geneIDType,
            $domainName,
        ]
    );

    $symbol_geneIDs = [];
    $retIds         = 0;
    exec($cmdIds, $symbol_geneIDs, $retIds);

    $gene_symbols = [];
    $gene_ids     = [];

    if ($retIds === 0 && !empty($symbol_geneIDs)) {
        $gene_id_symbols_arr = [];

        // Flatten all lines into one array
        foreach ($symbol_geneIDs as $line) {
            $parts = explode(',', $line);
            foreach ($parts as $piece) {
                $gene_id_symbols_arr[] = trim($piece, "\" \t\n\r\0\x0B");
            }
        }

        $length = count($gene_id_symbols_arr);
        for ($i = 0; $i < $length; $i++) {
            $val = $gene_id_symbols_arr[$i];

            if ($i < $length / 2) {
                $gene_symbols[] = $val;
            } else {
                // We rely on buildRscriptCommand for injection safety, so no regex filter here
                if ($val !== '') {
                    $gene_ids[] = $val;
                }
            }
        }
    }

    // Build strings for R summary script
    $gene_array_str = implode('__', $gene_ids);
    $gene_sym_str   = implode('__', $gene_symbols);

    // If nothing usable, short-circuit
    if ($gene_array_str === '' || $gene_sym_str === '') {
        if ($viewType === 'json') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'No valid gene identifiers found.']);
        } else {
            header('Content-Type: text/plain; charset=UTF-8');
            echo "No valid gene identifiers found.\n";
        }
        exit;
    }

    // SECURITY FIX: R script expects relative path from MetGENE root
    $cache_dir = __DIR__ . '/cache';
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
    }

    $plot_basename = 'plot' . mt_rand(1, 1000000) . '.png';
    $filename = 'cache/' . $plot_basename;  // R script needs relative path "cache/plot.png"

    // Encode filters (fall back to NA as in original code)
    $anatomyEnc = rawurlencode($anatomyRaw !== '' ? $anatomyRaw : 'NA');
    $diseaseEnc = rawurlencode($diseaseRaw !== '' ? $diseaseRaw : 'NA');

    // ---- 2) Call extractMWGeneSummary.R to produce JSON/TXT summary ----
    $cmdSummary = buildRscriptCommand(
        'extractMWGeneSummary.R',
        [
            $species,
            $gene_array_str,
            $gene_sym_str,
            $filename,
            $viewType,   // "json" or "txt"
            $anatomyEnc,
            $diseaseEnc,
        ]
    );

    $output = [];
    $ret    = 0;
    exec($cmdSummary, $output, $ret);

    // SECURITY FIX: Ensure plot file is readable
    $abs_filename = __DIR__ . '/' . $filename;
    if (file_exists($abs_filename)) {
        @chmod($abs_filename, 0644);
    }

    if ($viewType === 'json') {
        header('Content-Type: application/json; charset=UTF-8');
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo implode("\n", $output) . "\n";
    exit;
}

/* ==========================================================================
 * HTML BRANCH: full MetGENE UI summary with caching
 * ========================================================================== */

// Session values (set upstream via nav.php)
$species       = $_SESSION['species']       ?? '';
$geneList      = $_SESSION['geneList']      ?? '';
$anatomy       = $_SESSION['anatomy']       ?? 'NA';
$disease       = $_SESSION['disease']       ?? 'NA';
$phenotype     = $_SESSION['phenotype']     ?? '';
$gene_array    = $_SESSION['geneArray']     ?? [];
$gene_symbols  = $_SESSION['geneSymbols']   ?? '';
$organism_name = $_SESSION['org_name']      ?? '';

/* ------------------------- CHANGE TRACKING -------------------------------- */
if (!isset($_SESSION['prev_summary_species']))  $_SESSION['prev_summary_species']  = '';
if (!isset($_SESSION['prev_summary_geneList'])) $_SESSION['prev_summary_geneList'] = '';
if (!isset($_SESSION['prev_summary_anatomy']))  $_SESSION['prev_summary_anatomy']  = '';
if (!isset($_SESSION['prev_summary_disease']))  $_SESSION['prev_summary_disease']  = '';
if (!isset($_SESSION['prev_summary_pheno']))    $_SESSION['prev_summary_pheno']    = '';

$_SESSION['summary_changed'] = 0;

if ($_SESSION['prev_summary_species'] !== $species) {
    $_SESSION['prev_summary_species'] = $species;
    $_SESSION['summary_changed']      = 1;
} elseif ($_SESSION['prev_summary_geneList'] !== $geneList) {
    $_SESSION['prev_summary_geneList'] = $geneList;
    $_SESSION['summary_changed']       = 1;
} elseif ($_SESSION['prev_summary_disease'] !== $disease) {
    $_SESSION['prev_summary_disease'] = $disease;
    $_SESSION['summary_changed']      = 1;
} elseif ($_SESSION['prev_summary_anatomy'] !== $anatomy) {
    $_SESSION['prev_summary_anatomy'] = $anatomy;
    $_SESSION['summary_changed']      = 1;
} elseif ($_SESSION['prev_summary_pheno'] !== $phenotype) {
    $_SESSION['prev_summary_pheno'] = $phenotype;
    $_SESSION['summary_changed']    = 1;
}

/* ------------------------- CACHE HANDLING --------------------------------- */
$url       = $_SERVER['SCRIPT_NAME'] ?? 'summary.php';
$file      = basename($url, '.php');

// SECURITY FIX: Sanitize session ID and use absolute path
$safeSession = preg_replace('/[^A-Za-z0-9]/', '', session_id());
$cachefile = __DIR__ . '/cache/cached-' . $safeSession . '-' . $file . '.html';
$_SESSION['summary_cache_file'] = $cachefile;
$cachetime = 18000; // 5 hours

if (
    ($_SESSION['summary_changed'] ?? 1) == 0 &&
    file_exists($cachefile) &&
    (time() - filemtime($cachefile)) < $cachetime
) {
    echo "<!-- Cached copy, generated " .
         escapeHtml(date('H:i', filemtime($cachefile))) .
         " -->\n";
    readfile($cachefile);
    exit;
}

ob_start(); // Start output buffer for caching

/* ------------------------- PREPARE GENE STRINGS --------------------------- */
// Original intention: keep all non-"NA" gene IDs; no extra filtering.
$gene_sym_arr = $gene_symbols !== '' ? explode(',', $gene_symbols) : [];

$valid_gene_ids  = [];
$valid_gene_syms = [];

for ($i = 0; $i < count($gene_array); $i++) {
    $gid = $gene_array[$i] ?? '';
    $sym = $gene_sym_arr[$i] ?? '';

    if ($gid !== '' && $gid !== 'NA') {
        $valid_gene_ids[]  = $gid;
        $valid_gene_syms[] = $sym;
    }
}

$gene_array_str = implode('__', $valid_gene_ids);
$gene_sym_str   = implode('__', $valid_gene_syms);

// Filter text (use session values, not raw $_GET)
$anatomy_str = $anatomy !== '' ? $anatomy : 'NA';
$disease_str = $disease !== '' ? $disease : 'NA';

$anatomyEnc = rawurlencode($anatomy_str);
$diseaseEnc = rawurlencode($disease_str);

// SECURITY FIX: R script expects relative path from MetGENE root
$cache_dir = __DIR__ . '/cache';
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0755, true);
}

$plot_basename = 'plot' . mt_rand(1, 1000000) . '.png';
$filename = 'cache/' . $plot_basename;  // R script needs relative path "cache/plot.png"
$plot_url = $base_dir . '/cache/' . $plot_basename;  // Web URL for browser
$viewMode = 'all';   // original "all" summary mode
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MetGENE: Summary</title>

<link rel="apple-touch-icon" sizes="180x180" href="<?= escapeHtml($base_dir) ?>/images/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= escapeHtml($base_dir) ?>/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= escapeHtml($base_dir) ?>/images/favicon-16x16.png">
<link rel="manifest" href="<?= escapeHtml($base_dir) ?>/site.webmanifest">

<style>
.styled-table {
    display: table;
    table-layout: fixed;
    width: 100%;
    word-wrap: break-word;
}
.styled-table td {
    border: 1px solid #000;
    padding: 5px 10px;
    text-align: center;
    width: 3%;
    word-break: break-all;
    white-space: pre-line;
}
.styled-table tbody tr {
    border-bottom: 1px solid #dddddd;
    text-align: center;
}
.styled-table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
    text-align: center;
}
.summary {
    background-color: white;
    width: 75;
    color: black;
    border: 2px solid black;
    margin: 20px;
    padding: 20px;
}
</style>

<?php
// SECURITY FIX: Validate nav.php path with realpath
$nav_file = realpath(__DIR__ . '/nav.php');
if ($nav_file !== false && strpos($nav_file, __DIR__) === 0 && is_readable($nav_file)) {
    include $nav_file;
}
?>
</head>
<body>

<div id="constrain">
<div class="constrain">
<br><br>
<p>
<?php
/******************************************************************************
 * MAIN HTML CONTENT
 *****************************************************************************/

if ($gene_array_str !== '') {

    // Build heading text (same logic as original, now escaped)
    if ($anatomy_str === 'NA' && $disease_str === 'NA') {
        $h3 = "Summary Information for <i><b>" .
              escapeHtml($organism_name) .
              "</b></i> gene(s) <i><b>" .
              escapeHtml($gene_sym_str) .
              "</b></i>";
    } elseif ($anatomy_str === 'NA') {
        $h3 = "Summary Information for <i><b>" .
              escapeHtml($organism_name) .
              "</b></i> gene(s) <i><b>" .
              escapeHtml($gene_sym_str) .
              "</b></i> disease <i><b>" .
              escapeHtml($disease_str) .
              "</b></i>";
    } elseif ($disease_str === 'NA') {
        $h3 = "Summary Information for <i><b>" .
              escapeHtml($organism_name) .
              "</b></i> gene(s) <i><b>" .
              escapeHtml($gene_sym_str) .
              "</b></i> anatomy <i><b>" .
              escapeHtml($anatomy_str) .
              "</b></i>";
    } else {
        $h3 = "Summary Information for <i><b>" .
              escapeHtml($organism_name) .
              "</b></i> gene(s) <i><b>" .
              escapeHtml($gene_sym_str) .
              "</b></i> anatomy <i><b>" .
              escapeHtml($anatomy_str) .
              "</b></i> disease <i><b>" .
              escapeHtml($disease_str) .
              "</b></i>";
    }

    echo "<h3>{$h3}</h3>";

    // Call R to generate HTML summary (table with id="Table1")
    $cmdSummaryHtml = buildRscriptCommand(
        'extractMWGeneSummary.R',
        [
            $species,
            $gene_array_str,
            $gene_sym_str,
            $filename,
            $viewMode,
            $anatomyEnc,
            $diseaseEnc,
        ]
    );

    $output = [];
    $ret    = 0;
    exec($cmdSummaryHtml, $output, $ret);

    // SECURITY FIX: Ensure plot file is readable and fix path in output
    $abs_filename = __DIR__ . '/' . $filename;  // Convert to absolute for file operations
    if (file_exists($abs_filename)) {
        @chmod($abs_filename, 0644);
        
        // The R script generates paths like: src=/MetGENE/cache/plot.png
        // We need to replace with the correct base_dir for dev/prod
        // In dev: src=/dev/MetGENE/cache/plot.png
        // In prod: src=/MetGENE/cache/plot.png
        $output = array_map(function($line) use ($filename, $plot_url) {
            // Replace R's hardcoded /MetGENE/ prefix with our dynamic base_dir
            $line = str_replace('src=/MetGENE/' . $filename, 'src=' . $plot_url, $line);
            $line = str_replace('src=' . $filename, 'src=' . $plot_url, $line);
            // Also fix href in download link
            $line = str_replace('href="/MetGENE/' . $filename, 'href="' . $plot_url, $line);
            $line = str_replace('href="' . $filename, 'href="' . $plot_url, $line);
            return $line;
        }, $output);
    }

    // DO NOT escape this; it's real HTML from R needed by tableHTMLExport
    echo implode("\n", $output);

    echo '<p><button id="json">TO JSON</button> <button id="csv">TO CSV</button></p>';

    $_SESSION['summary_changed'] = 0;
} else {
    echo "<h3><i>No summary available: no valid gene identifiers.</i></h3>";
}
?>
</p>
</div>
</div>

<!-- Load scripts from external files -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?= escapeHtml($base_dir) ?>/src/tableHTMLExport.js"></script>
<script src="<?= escapeHtml($base_dir) ?>/js/summary-export.js"></script>

<?php
// SECURITY FIX: Validate footer.php path with realpath
$footer_file = realpath(__DIR__ . '/footer.php');
if ($footer_file !== false && strpos($footer_file, __DIR__) === 0 && is_readable($footer_file)) {
    include $footer_file;
}

// SECURITY FIX: Add error handling for cache write
if (!is_dir(dirname($cachefile))) {
    @mkdir(dirname($cachefile), 0755, true);
}
$cached = @fopen($cachefile, 'w');
if ($cached !== false) {
    fwrite($cached, ob_get_contents());
    fclose($cached);
    @chmod($cachefile, 0640); // Restrict permissions
} else {
    error_log("Failed to write cache file: $cachefile");
}
ob_end_flush();
?>

</body>
</html>