<?php
declare(strict_types=1);

// SECURITY FIX: Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/metgene_common.php';

// SECURITY FIX: Send security headers
sendSecurityHeaders();

/**
 * Branch 1: JSON / TXT API mode (no HTML shell)
 * viewType=json | txt
 */
$view_type = strtolower(safeGet('viewType'));

if ($view_type === 'json' || $view_type === 'txt') {
    // Sanitize and normalize inputs from GET
    $species      = safeGet('species');
    $gene_list    = safeGet('GeneInfoStr');
    $gene_id_type = safeGet('GeneIDType');
    $disease      = safeGet('disease');
    $anatomy      = safeGet('anatomy');

    // SECURITY FIX: Normalize species to allowed values
    list($species, $species_label, $species_sci) = normalizeSpecies($species);

    // SECURITY FIX: Validate gene ID type
    $gene_id_type = validateGeneIDType($gene_id_type);

    $domain_name  = $_SERVER['SERVER_NAME'] ?? 'localhost';

    // Resolve gene IDs and symbols via Rscript (safely)
    $cmd = buildRscriptCommand('extractGeneIDsAndSymbols.R', [
        $species,
        $gene_list,
        $gene_id_type,
        $domain_name,
    ]);

    // SECURITY FIX: Check if command was built successfully
    if ($cmd === '') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Error: Script not available.";
        error_log("SECURITY: extractGeneIDsAndSymbols.R not found or not readable");
        exit;
    }

    $symbol_gene_ids = [];
    $retvar = 0;
    exec($cmd, $symbol_gene_ids, $retvar);

    if ($retvar !== 0) {
        // Hard failure, return 500
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Error: extractGeneIDsAndSymbols.R failed (exit code {$retvar}).";
        error_log("R script extractGeneIDsAndSymbols.R failed with exit code: $retvar");
        exit;
    }

    // Parse returned vector: first half = symbols, second half = IDs
    $gene_symbols      = [];
    $gene_ids          = [];
    $gene_id_symbols   = [];

    foreach ($symbol_gene_ids as $line) {
        // The R script appears to return a single comma-separated vector
        $gene_id_symbols = explode(',', $line);
    }

    $length = count($gene_id_symbols);

    if ($length > 0) {
        for ($i = 0; $i < $length; $i++) {
            $trimmed = trim($gene_id_symbols[$i], "\" \t\n\r\0\x0B");

            // First half: symbols; second half: IDs
            if ($i < $length / 2) {
                $gene_symbols[] = $trimmed;
            } else {
                $gene_ids[] = $trimmed;
            }
        }
    }

    // Prepare encoded filters
    $enc_disease = urlencode($disease);
    $enc_anatomy = urlencode($anatomy);

    // Decide content type once
    if ($view_type === 'json') {
        header('Content-Type: application/json; charset=UTF-8');
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
    }

    // For each gene ID, fetch metabolite info via R
    $first_output = true;
    foreach ($gene_ids as $gene_id) {
        if ($gene_id === '' || $gene_id === 'NA') {
            continue;
        }

        $cmd_met = buildRscriptCommand('extractMetaboliteInfo.R', [
            $species,
            $gene_id,
            $enc_anatomy,
            $enc_disease,
            $view_type,
        ]);

        // SECURITY FIX: Check if command was built successfully
        if ($cmd_met === '') {
            error_log("SECURITY: extractMetaboliteInfo.R not found or not readable");
            continue;
        }

        $output  = [];
        $ret_var = 0;
        exec($cmd_met, $output, $ret_var);

        // We don't hard-fail everything if one gene errors; just skip output
        if ($ret_var !== 0) {
            error_log("R script extractMetaboliteInfo.R failed for gene $gene_id with exit code: $ret_var");
            continue;
        }

        $htmlbuff = implode("\n", $output);

        // If JSON, you may want to merge/concat arrays; but original code
        // just printed one after another, so we preserve that behavior.
        if (!$first_output) {
            echo "\n";
        }
        echo $htmlbuff;
        $first_output = false;
    }

    exit;
}

/**
 * Branch 2: Normal HTML mode (interactive Metabolites page)
 */

$METGENE_BASE_DIR_NAME = getBaseDir();
?>
<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>

<head>
    <title>MetGENE: Metabolites</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <?php
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/images/apple-touch-icon.png">';
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/images/favicon-32x32.png">';
    echo '<link rel="icon" type="image/png" sizes="16x16" href="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/images/favicon-16x16.png">';
    echo '<link rel="manifest" href="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/site.webmanifest">';
    ?>

    <?php
    // SECURITY FIX: Validate nav.php path with realpath
    $nav_file = realpath(__DIR__ . '/nav.php');
    if ($nav_file !== false && strpos($nav_file, __DIR__) === 0 && is_readable($nav_file)) {
        include $nav_file;
    }

    // Track when filters change (for caching)
    $_SESSION['prev_met_species']  = $_SESSION['prev_met_species']  ?? '';
    $_SESSION['prev_met_geneList'] = $_SESSION['prev_met_geneList'] ?? '';
    $_SESSION['prev_met_anatomy']  = $_SESSION['prev_met_anatomy']  ?? '';
    $_SESSION['prev_met_disease']  = $_SESSION['prev_met_disease']  ?? '';
    $_SESSION['prev_met_pheno']    = $_SESSION['prev_met_pheno']    ?? '';

    if (strcmp($_SESSION['prev_met_species'], $_SESSION['species'] ?? '') !== 0) {
        $_SESSION['prev_met_species'] = $_SESSION['species'] ?? '';
        $_SESSION['met_changed']      = 1;
    } elseif (strcmp($_SESSION['prev_met_geneList'], $_SESSION['geneList'] ?? '') !== 0) {
        $_SESSION['prev_met_geneList'] = $_SESSION['geneList'] ?? '';
        $_SESSION['met_changed']       = 1;
    } elseif (strcmp($_SESSION['prev_met_disease'], $_SESSION['disease'] ?? '') !== 0) {
        $_SESSION['prev_met_disease'] = $_SESSION['disease'] ?? '';
        $_SESSION['met_changed']      = 1;
    } elseif (strcmp($_SESSION['prev_met_anatomy'], $_SESSION['anatomy'] ?? '') !== 0) {
        $_SESSION['prev_met_anatomy'] = $_SESSION['anatomy'] ?? '';
        $_SESSION['met_changed']      = 1;
    } elseif (strcmp($_SESSION['prev_met_pheno'], $_SESSION['phenotype'] ?? '') !== 0) {
        $_SESSION['prev_met_pheno'] = $_SESSION['phenotype'] ?? '';
        $_SESSION['met_changed']    = 1;
    } else {
        $_SESSION['met_changed'] = $_SESSION['met_changed'] ?? 0;
    }
    ?>
</head>

<body>
<div id="constrain">
<div class="constrain">
<br>
<br>

<p>
<?php
// --------- Top cache logic ----------
$url       = $_SERVER['SCRIPT_NAME'] ?? '';
$parts     = explode('/', $url);
$file_name = end($parts) ?: 'metabolites.php';

// SECURITY FIX: Sanitize session ID and use absolute path
$safeSession = preg_replace('/[^A-Za-z0-9]/', '', session_id());
$cachefile = __DIR__ . '/cache/cached-' . $safeSession . '-' . basename($file_name, '.php') . '.html';
$_SESSION['met_cache_file'] = $cachefile;
$cachetime = 18000;

if (
    isset($_SESSION['met_changed'], $_SESSION['met_cache_file']) &&
    $_SESSION['met_changed'] == 0 &&
    file_exists($_SESSION['met_cache_file']) &&
    time() - $cachetime < filemtime($_SESSION['met_cache_file'])
) {
    echo "<!-- Cached copy, generated " . date('H:i', filemtime($_SESSION['met_cache_file'])) . " -->\n";
    readfile($_SESSION['met_cache_file']);
    exit;
}

ob_start();
// --------- End cache check ----------

$organism_name = $_SESSION['org_name']      ?? '';
$gene_sym_str  = $_SESSION['geneSymbols']   ?? '';
$gene_sym_arr  = $gene_sym_str !== '' ? explode(',', $gene_sym_str) : [];
$gene_array    = $_SESSION['geneArray']     ?? [];

// SECURITY FIX: Ensure geneArray is actually an array
if (!is_array($gene_array)) {
    $gene_array = [];
}

$disease       = $_SESSION['disease']       ?? 'NA';
$anatomy       = $_SESSION['anatomy']       ?? 'NA';
$phenotype     = $_SESSION['phenotype']     ?? '';
$species       = $_SESSION['species']       ?? '';

$enc_disease   = urlencode($disease);
$enc_anatomy   = urlencode($anatomy);

$i = 0;

if (
    isset($_SESSION['species'], $_SESSION['geneArray'], $_SESSION['met_changed']) &&
    $_SESSION['met_changed'] == 1
) {
    // SECURITY FIX: Ensure gene_array is valid
    if (!is_array($gene_array)) {
        echo "<h3>Error: Invalid gene data</h3>";
    } else {
        foreach ($gene_array as $gene_id) {
            $output       = [];
            $htmlbuff     = [];
            $geneSymbolStr = $gene_sym_arr[$i] ?? '';

            // SECURITY FIX: Validate gene ID format
            if ($gene_id !== 'NA' && $gene_id !== '' && preg_match('/^[A-Za-z0-9._-]+$/', $gene_id)) {
                if ($anatomy === 'NA' && $disease === 'NA') {
                    $h3_str = "<h3>Metabolite Information for <i><b>"
                        . escapeHtml($organism_name) . "</b></i> gene(s) <i><b>"
                        . escapeHtml($geneSymbolStr) . "</b></i></h3>";
                } elseif ($anatomy === 'NA') {
                    $h3_str = "<h3>Metabolite Information for <i><b>"
                        . escapeHtml($organism_name) . "</b></i> gene(s) <i><b>"
                        . escapeHtml($geneSymbolStr) . "</b></i> disease <i><b>"
                        . escapeHtml($disease) . "</b></i></h3>";
                } elseif ($disease === 'NA') {
                    $h3_str = "<h3>Metabolite Information for <i><b>"
                        . escapeHtml($organism_name) . "</b></i> gene(s) <i><b>"
                        . escapeHtml($geneSymbolStr) . "</b></i> anatomy <i><b>"
                        . escapeHtml($anatomy) . "</b></i></h3>";
                } else {
                    $h3_str = "<h3>Metabolite Information for <i><b>"
                        . escapeHtml($organism_name) . "</b></i> gene(s) <i><b>"
                        . escapeHtml($geneSymbolStr) . "</b></i> anatomy <i><b>"
                        . escapeHtml($anatomy) . "</b></i> disease <i><b>"
                        . escapeHtml($disease) . "</b></i></h3>";
                }

                echo $h3_str;

                $view_type_html = 'html';

                $cmd_met = buildRscriptCommand('extractMetaboliteInfo.R', [
                    $species,
                    $gene_id,
                    $enc_anatomy,
                    $enc_disease,
                    $view_type_html,
                ]);

                // SECURITY FIX: Check if command was built successfully
                if ($cmd_met === '') {
                    error_log("SECURITY: extractMetaboliteInfo.R not found or not readable");
                    $msg = "<h3><i>Error: Metabolite script not available for <b>"
                        . escapeHtml($organism_name) . "</b> gene <b>"
                        . escapeHtml($geneSymbolStr) . "</b>.</i></h3>";
                    echo $msg;
                    echo "<br>";
                } else {
                    $retvar = 0;
                    exec($cmd_met, $output, $retvar);

                    if ($retvar === 0) {
                        $htmlbuff = implode($output);
                        echo "<pre>";
                        echo $htmlbuff;
                        echo "</pre>";
                        echo "<br>";
                    } else {
                        error_log("R script extractMetaboliteInfo.R failed for gene $gene_id with exit code: $retvar");
                        $msg = "<h3><i>Error running metabolite extraction for <b>"
                            . escapeHtml($organism_name) . "</b> gene <b>"
                            . escapeHtml($geneSymbolStr) . "</b>.</i></h3>";
                        echo $msg;
                        echo "<br>";
                    }
                }
            } else {
                $h3_str = "<h3><i>No metabolite information found for <b>"
                    . escapeHtml($organism_name) . "</b> gene(s) <b>"
                    . escapeHtml($geneSymbolStr) . "</b></i></h3>";
                echo $h3_str;
                echo "<br>";
            }

            $i++;
        }

        // UPDATED: Use generateExportButtons helper
        echo generateExportButtons($gene_array, 'Metabolites');

        $_SESSION['met_changed'] = 0;
    }
}
?>
</p>

</div>
</div>

<!-- UPDATED: Load scripts from external files -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php
echo '<script src="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/src/tableHTMLExport.js"></script>';
echo '<script src="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/js/table-export-handler.js"></script>';
echo '<script src="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/js/table-export-init.js"></script>';
?>

<?php
// SECURITY FIX: Validate footer.php path with realpath
$footer_file = realpath(__DIR__ . '/footer.php');
if ($footer_file !== false && strpos($footer_file, __DIR__) === 0 && is_readable($footer_file)) {
    include $footer_file;
}

// SECURITY FIX: Add error handling for cache write
$cachefile = $_SESSION['met_cache_file'] ?? null;
if ($cachefile) {
    // Ensure cache directory exists
    $cacheDir = dirname($cachefile);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    
    $cached = @fopen($cachefile, 'w');
    if ($cached !== false) {
        fwrite($cached, ob_get_contents());
        fclose($cached);
        @chmod($cachefile, 0640); // Restrict permissions
    } else {
        error_log("Failed to write cache file: $cachefile");
    }
}
ob_end_flush();
?>

</body>
</html>