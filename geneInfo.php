<?php
/******************************************************************************
 * MetGENE – Hardened geneInfo.php
 *
 * BEHAVIOR:
 *  - Displays gene information table for selected genes
 *  - Uses external scripts for JSON/CSV export
 *
 * SECURITY:
 *  - Uses metgene_common.php helpers (security headers, escaping, safeGet)
 *  - Uses buildRscriptCommand() → escapeshellarg() for all exec() calls
 *  - Sanitizes gene IDs before passing to R
 *  - Safe includes for nav.php and footer.php
 *****************************************************************************/

declare(strict_types=1);

// SECURITY FIX: Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/metgene_common.php';

// SECURITY FIX: Send security headers
sendSecurityHeaders();

/******************************************************************************
 * HTML VIEW
 ******************************************************************************/

$base_dir = getBaseDirName(); // from metgene_common.php

// Track whether data changed for caching
$_SESSION['prev_geneInfo_species']  = $_SESSION['prev_geneInfo_species']  ?? '';
$_SESSION['prev_geneInfo_geneList'] = $_SESSION['prev_geneInfo_geneList'] ?? '';
$_SESSION['prev_geneInfo_anatomy']  = $_SESSION['prev_geneInfo_anatomy']  ?? '';
$_SESSION['prev_geneInfo_disease']  = $_SESSION['prev_geneInfo_disease']  ?? '';
$_SESSION['prev_geneInfo_pheno']    = $_SESSION['prev_geneInfo_pheno']    ?? '';

if (strcmp($_SESSION['prev_geneInfo_species'], $_SESSION['species'] ?? '') !== 0) {
    $_SESSION['prev_geneInfo_species'] = $_SESSION['species'] ?? '';
    $_SESSION['geneInfo_changed']      = 1;
} elseif (strcmp($_SESSION['prev_geneInfo_geneList'], $_SESSION['geneList'] ?? '') !== 0) {
    $_SESSION['prev_geneInfo_geneList'] = $_SESSION['geneList'] ?? '';
    $_SESSION['geneInfo_changed']       = 1;
} elseif (strcmp($_SESSION['prev_geneInfo_disease'], $_SESSION['disease'] ?? '') !== 0) {
    $_SESSION['prev_geneInfo_disease'] = $_SESSION['disease'] ?? '';
    $_SESSION['geneInfo_changed']      = 1;
} elseif (strcmp($_SESSION['prev_geneInfo_anatomy'], $_SESSION['anatomy'] ?? '') !== 0) {
    $_SESSION['prev_geneInfo_anatomy'] = $_SESSION['anatomy'] ?? '';
    $_SESSION['geneInfo_changed']      = 1;
} elseif (strcmp($_SESSION['prev_geneInfo_pheno'], $_SESSION['phenotype'] ?? '') !== 0) {
    $_SESSION['prev_geneInfo_pheno'] = $_SESSION['phenotype'] ?? '';
    $_SESSION['geneInfo_changed']    = 1;
} else {
    $_SESSION['geneInfo_changed'] = $_SESSION['geneInfo_changed'] ?? 0;
}

$species    = $_SESSION['species']      ?? '';
$org_name   = $_SESSION['org_name']     ?? '';
$gene_array = $_SESSION['geneArray']    ?? [];
$gene_syms  = $_SESSION['geneSymbols']  ?? '';

// SECURITY FIX: Ensure geneArray is actually an array
if (!is_array($gene_array)) {
    $gene_array = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MetGENE: Gene Information</title>

<link rel="apple-touch-icon" sizes="180x180" href="<?= escapeHtml($base_dir) ?>/images/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= escapeHtml($base_dir) ?>/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= escapeHtml($base_dir) ?>/images/favicon-16x16.png">
<link rel="manifest" href="<?= escapeHtml($base_dir) ?>/site.webmanifest">

<?php
// SECURITY FIX: Validate nav.php path with realpath
$nav_file = realpath(__DIR__ . '/nav.php');
if ($nav_file !== false && strpos($nav_file, __DIR__) === 0 && is_readable($nav_file)) {
    include $nav_file;
}
?>
</head>
<body>
<div id="constrain"><div class="constrain">

<br><br>
<p>
<?php
// ---------------------------- CACHE HANDLING -------------------------------

$url   = $_SERVER['SCRIPT_NAME'] ?? '';
$parts = explode('/', $url);
$file  = $parts[count($parts) - 1] ?? 'geneInfo.php';

// SECURITY FIX: Sanitize session ID and use absolute path
$safeSession = preg_replace('/[^A-Za-z0-9]/', '', session_id());
$cachefile = __DIR__ . '/cache/cached-' . $safeSession . '-' . basename($file, '.php') . '.html';
$_SESSION['geneInfo_cache_file'] = $cachefile;
$cachetime = 18000;

// Serve from cache if unchanged & fresh
if (
    ($_SESSION['geneInfo_changed'] ?? 0) === 0 &&
    isset($_SESSION['geneInfo_cache_file']) &&
    file_exists($_SESSION['geneInfo_cache_file']) &&
    (time() - $cachetime) < filemtime($_SESSION['geneInfo_cache_file'])
) {
    echo '<!-- Cached copy, generated ' . date('H:i', filemtime($_SESSION['geneInfo_cache_file'])) . " -->\n";
    readfile($_SESSION['geneInfo_cache_file']);
} else {
    ob_start(); // Start output buffer for caching

    $gene_sym_arr = $gene_syms !== '' ? explode(',', $gene_syms) : [];

    if (!empty($species) && !empty($gene_array) && ($_SESSION['geneInfo_changed'] ?? 0) === 1) {
        // SECURITY FIX: Ensure gene_array is valid
        if (!is_array($gene_array)) {
            echo "<h3>Error: Invalid gene data</h3>";
        } else {
            // Build gene vectors
            $gene_vec_arr = [];
            $gene_sym_arr_clean = [];
            
            for ($i = 0; $i < count($gene_array); $i++) {
                if ($gene_array[$i] !== 'NA' && $gene_array[$i] !== '') {
                    $gene_vec_arr[] = $gene_array[$i];
                    $gene_sym_arr_clean[] = $gene_sym_arr[$i] ?? '';
                }
            }
            
            $gene_vec_str = implode('__', $gene_vec_arr);
            $gene_sym_str = implode(',', $gene_sym_arr_clean);

            if (!empty($gene_vec_str)) {
                $h3_str = '<h3>Gene Information for <i><b>' .
                          escapeHtml($org_name) .
                          '</b></i> gene(s) <i><b>' .
                          escapeHtml($gene_sym_str) .
                          '</b></i></h3>';
                echo $h3_str;

                $domain_name = $_SERVER['SERVER_NAME'] ?? 'localhost';

                $cmd = buildRscriptCommand('extractGeneInfoTable.R', [
                    $species,
                    $gene_vec_str,
                    $domain_name,
                ]);

                // SECURITY FIX: Check if command was built successfully
                if ($cmd === '') {
                    error_log("SECURITY: extractGeneInfoTable.R not found or not readable");
                    echo '<p style="color:red;">Error: Gene information script not available.</p>';
                } else {
                    $output = [];
                    $retvar = 0;
                    exec($cmd, $output, $retvar);

                    // R script returns HTML table; we intentionally do NOT escape it
                    // because downstream JS (tableHTMLExport) needs the real table markup.
                    if ($retvar === 0) {
                        echo "<pre>";
                        echo implode("\n", $output);
                        echo "</pre>";
                        
                        // UPDATED: Use generateExportButtons helper
                        // Pass empty array since geneInfo uses a single table #Table1
                        echo generateExportButtons([], 'GeneInfo', 'Table1', 'SELECT');
                        
                        echo "<br>\n";
                    } else {
                        error_log("R script extractGeneInfoTable.R failed with exit code: $retvar");
                        echo '<p style="color:red;">Error retrieving gene information.</p>';
                    }
                }
            } else {
                echo '<h3><i>No gene information found for <b>' .
                     escapeHtml($org_name) .
                     '</b> gene(s) <b>' .
                     escapeHtml($gene_sym_str) .
                     '</b></i></h3>';
            }

            $_SESSION['geneInfo_changed'] = 0;

            // SECURITY FIX: Add error handling for cache write
            $cachefile = $_SESSION['geneInfo_cache_file'];
            if (!is_dir(dirname($cachefile))) {
                @mkdir(dirname($cachefile), 0755, true);
            }
            $cached = @fopen($cachefile, 'w');
            if ($cached) {
                fwrite($cached, ob_get_contents());
                fclose($cached);
                @chmod($cachefile, 0640); // Restrict permissions
            } else {
                error_log("Failed to write cache file: $cachefile");
            }
            ob_end_flush(); // send buffer
        }
    }
}
?>
</p>

</div></div>

<!-- UPDATED: Load scripts from external files -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?= escapeHtml($base_dir) ?>/src/tableHTMLExport.js"></script>
<script src="<?= escapeHtml($base_dir) ?>/js/table-export-handler.js"></script>
<script src="<?= escapeHtml($base_dir) ?>/js/table-export-init.js"></script>

<?php
// SECURITY FIX: Validate footer.php path with realpath
$footer_file = realpath(__DIR__ . '/footer.php');
if ($footer_file !== false && strpos($footer_file, __DIR__) === 0 && is_readable($footer_file)) {
    include $footer_file;
}
?>

</body>
</html>