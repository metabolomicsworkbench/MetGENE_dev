<?php
declare(strict_types=1);

require_once __DIR__ . '/metgene_common.php';

/**
 * Branch 1: JSON / TXT API mode (no HTML shell)
 * viewType=json | txt
 */
$view_type = strtolower(safeGet('viewType'));

if ($view_type === 'json' || $view_type === 'txt') {
    // Sanitize and normalize inputs from GET
    $species      = safeGet('species');       // e.g. hsa/mmu/rno
    $gene_list    = safeGet('GeneInfoStr');   // raw gene list string
    $gene_id_type = safeGet('GeneIDType');
    $disease      = safeGet('disease');
    $anatomy      = safeGet('anatomy');

    $domain_name  = $_SERVER['SERVER_NAME'] ?? '';

    // Resolve gene IDs and symbols via Rscript (safely)
    $cmd = buildRscriptCommand('extractGeneIDsAndSymbols.R', [
        $species,
        $gene_list,
        $gene_id_type,
        $domain_name,
    ]);

    $symbol_gene_ids = [];
    $retvar = 0;
    exec($cmd, $symbol_gene_ids, $retvar);

    if ($retvar !== 0) {
        // Hard failure, return 500
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Error: extractGeneIDsAndSymbols.R failed (exit code {$retvar}).";
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

        $output  = [];
        $ret_var = 0;
        exec($cmd_met, $output, $ret_var);

        // We don't hard-fail everything if one gene errors; just skip output
        if ($ret_var !== 0) {
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
    echo '<link rel="apple-touch-icon" sizes="180x180" href="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/images/apple-touch-icon.png">';
    echo '<link rel="icon" type="image/png" sizes="32x32" href="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/images/favicon-32x32.png">';
    echo '<link rel="icon" type="image/png" sizes="16x16" href="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/images/favicon-16x16.png">';
    echo '<link rel="manifest" href="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/site.webmanifest">';
    ?>

    <?php
    // Main navigation bar (sets up sessions etc.)
    include $_SERVER['DOCUMENT_ROOT'] . $METGENE_BASE_DIR_NAME . '/nav.php';

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

$cachefile = 'cache/cached-' . session_id() . '-' . substr_replace($file_name, '', -4) . '.html';
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
    foreach ($gene_array as $gene_id) {
        $output       = [];
        $htmlbuff     = [];
        $geneSymbolStr = $gene_sym_arr[$i] ?? '';

        if ($gene_id !== 'NA' && $gene_id !== '') {
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

            $retvar = 0;
            exec($cmd_met, $output, $retvar);

            if ($retvar === 0) {
                $htmlbuff = implode($output);
                echo "<pre>";
                echo $htmlbuff;
                echo "</pre>";
                echo "<br>";
            } else {
                $msg = "<h3><i>Error running metabolite extraction for <b>"
                    . escapeHtml($organism_name) . "</b> gene <b>"
                    . escapeHtml($geneSymbolStr) . "</b>.</i></h3>";
                echo $msg;
                echo "<br>";
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

    $btn_str = '<p><button id="json">TO JSON</button> <button id="csv">TO CSV</button></p>';
    echo $btn_str;

    $_SESSION['met_changed'] = 0;
}
?>
</p>

</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php
echo '<script src="' . escapeHtml($METGENE_BASE_DIR_NAME) . '/src/tableHTMLExport.js"></script>';
?>

<script>
  $('#json').on('click', function() {
    var gene_arr_str = '<?php echo json_encode($gene_array, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>';
    var gene_arr = JSON.parse(gene_arr_str);
    var len = gene_arr.length;
    var tabName = "";
    var fname = "";
    for (var i = 0; i < len; i++) {
      tabName = "#Gene" + gene_arr[i] + "Table";
      fname = "Gene" + gene_arr[i] + "Metabolites.json";
      $(tabName).tableHTMLExport({ type: 'json', filename: fname });
    }
  });

  $('#csv').on('click', function() {
    var gene_arr_str = '<?php echo json_encode($gene_array, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>';
    var gene_arr = JSON.parse(gene_arr_str);
    var len = gene_arr.length;
    var tabName = "";
    var fname = "";
    for (var i = 0; i < len; i++) {
      tabName = "#Gene" + gene_arr[i] + "Table";
      fname = "Gene" + gene_arr[i] + "Metabolites.csv";
      $(tabName).tableHTMLExport({ type: 'csv', filename: fname });
    }
  });
</script>

<?php
include $_SERVER['DOCUMENT_ROOT'] . $METGENE_BASE_DIR_NAME . '/footer.php';

// bottom-cache.php
$cachefile = $_SESSION['met_cache_file'] ?? null;
if ($cachefile) {
    $cached = @fopen($cachefile, 'w');
    if ($cached !== false) {
        fwrite($cached, ob_get_contents());
        fclose($cached);
    }
}
ob_end_flush();
?>

</body>
</html>
