<?php
/******************************************************************************
 * MetGENE – Hardened reactions.php
 *
 * BEHAVIOR:
 *  - If ?viewType=json|txt  → returns raw JSON / TXT from R (no HTML shell)
 *  - Else                  → full HTML page with reactions per gene
 *
 * SECURITY:
 *  - Uses metgene_common.php helpers (security headers, escaping, safeGet)
 *  - Sanitizes all GET input (viewType, species, GeneInfoStr, GeneIDType)
 *  - Uses buildRscriptCommand() → escapeshellarg() for all exec() calls
 *  - Sanitizes gene IDs before passing to R
 *  - Safe includes for nav.php and footer.php
 *****************************************************************************/

declare(strict_types=1);
session_start();

require_once __DIR__ . '/metgene_common.php';

sendSecurityHeaders();

/* ---------------------------- BRANCH ON viewType -------------------------- */

$view_type = strtolower(safeGet('viewType'));  // from metgene_common.php

if ($view_type === 'json' || $view_type === 'txt') {
    /**************************************************************************
     * DATA / API BRANCH: return JSON or plain text (no HTML shell)
     **************************************************************************/

    $species      = safeGet('species');
    $gene_list    = safeGet('GeneInfoStr');
    $gene_id_type = safeGet('GeneIDType');

    // Normalize species to allowed values only
    $species = normalizeSpecies($species); // from metgene_common.php

    // Whitelist geneIDType
    $allowed_types = ['SYMBOL', 'SYMBOL_OR_ALIAS', 'ENTREZID', 'ENSEMBL', 'REFSEQ', 'UNIPROT'];
    if (!in_array($gene_id_type, $allowed_types, true)) {
        $gene_id_type = 'SYMBOL';
    }

    // Sanitize gene list for Rscript: allow only alphanumeric IDs separated by "__"/","
    $tmp   = str_replace('__', ',', $gene_list);
    $parts = explode(',', $tmp);
    $clean_genes = [];
    $pat = '/^[A-Za-z0-9]+$/';

    foreach ($parts as $g) {
        $g = trim($g);
        if ($g === '') {
            continue;
        }
        if (preg_match($pat, $g)) {
            $clean_genes[] = $g;
        }
    }
    $gene_info_str = implode('__', $clean_genes);

    $domain_name = $_SERVER['SERVER_NAME'] ?? 'localhost';

    // ---- Call R script to map IDs <-> symbols securely ----
    $cmd = buildRscriptCommand('extractGeneIDsAndSymbols.R', [
        $species,
        $gene_info_str,
        $gene_id_type,
        $domain_name,
    ]);

    $symbol_gene_ids = [];
    $retvar = 0;
    exec($cmd, $symbol_gene_ids, $retvar);

    $gene_symbols        = [];
    $gene_array          = [];
    $gene_id_symbols_arr = [];

    foreach ($symbol_gene_ids as $val) {
        // R returns a single comma-separated vector
        $gene_id_symbols_arr = explode(',', $val);
    }

    $length = count($gene_id_symbols_arr);

    for ($i = 0; $i < $length; $i++) {
        $my_str       = $gene_id_symbols_arr[$i];
        $trimmed_str  = trim($my_str, "\" \t\n\r\0\x0B");

        if ($i < $length / 2) {
            $gene_symbols[] = $trimmed_str;
        } else {
            $gene_array[] = $trimmed_str;
        }
    }

    // Set content type once
    if ($view_type === 'json') {
        header('Content-Type: application/json; charset=UTF-8');
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
    }

    // For each gene ID, call extractReactionInfo.R safely
    $first = true;
    foreach ($gene_array as $value) {
        if (!preg_match($pat, $value)) {
            continue; // skip anything non-alphanumeric
        }

        $cmd_rxn = buildRscriptCommand('extractReactionInfo.R', [
            $species,
            $value,
            $view_type,
        ]);

        $output   = [];
        $ret_var2 = 0;
        exec($cmd_rxn, $output, $ret_var2);

        if ($ret_var2 !== 0) {
            // skip failed ones but don't break others
            continue;
        }

        $buff = implode("\n", $output);
        if (!$first) {
            echo "\n";
        }
        echo $buff;
        $first = false;
    }

    exit;
}

/******************************************************************************
 * HTML VIEW BRANCH
 ******************************************************************************/

$base_dir = getBaseDir(); // from metgene_common.php

// Track whether data changed for caching
$_SESSION['prev_rxn_species']  = $_SESSION['prev_rxn_species']  ?? '';
$_SESSION['prev_rxn_geneList'] = $_SESSION['prev_rxn_geneList'] ?? '';
$_SESSION['prev_rxn_anatomy']  = $_SESSION['prev_rxn_anatomy']  ?? '';
$_SESSION['prev_rxn_disease']  = $_SESSION['prev_rxn_disease']  ?? '';
$_SESSION['prev_rxn_pheno']    = $_SESSION['prev_rxn_pheno']    ?? '';

if (strcmp($_SESSION['prev_rxn_species'], $_SESSION['species'] ?? '') !== 0) {
    $_SESSION['prev_rxn_species'] = $_SESSION['species'] ?? '';
    $_SESSION['rxn_changed']      = 1;
} elseif (strcmp($_SESSION['prev_rxn_geneList'], $_SESSION['geneList'] ?? '') !== 0) {
    $_SESSION['prev_rxn_geneList'] = $_SESSION['geneList'] ?? '';
    $_SESSION['rxn_changed']       = 1;
} elseif (strcmp($_SESSION['prev_rxn_disease'], $_SESSION['disease'] ?? '') !== 0) {
    $_SESSION['prev_rxn_disease'] = $_SESSION['disease'] ?? '';
    $_SESSION['rxn_changed']      = 1;
} elseif (strcmp($_SESSION['prev_rxn_anatomy'], $_SESSION['anatomy'] ?? '') !== 0) {
    $_SESSION['prev_rxn_anatomy'] = $_SESSION['anatomy'] ?? '';
    $_SESSION['rxn_changed']      = 1;
} elseif (strcmp($_SESSION['prev_rxn_pheno'], $_SESSION['phenotype'] ?? '') !== 0) {
    $_SESSION['prev_rxn_pheno'] = $_SESSION['phenotype'] ?? '';
    $_SESSION['rxn_changed']    = 1;
} else {
    $_SESSION['rxn_changed'] = $_SESSION['rxn_changed'] ?? 0;
}

$species    = $_SESSION['species']      ?? '';
$org_name   = $_SESSION['org_name']     ?? '';
$gene_array = $_SESSION['geneArray']    ?? [];
$gene_syms  = $_SESSION['geneSymbols']  ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MetGENE: Reactions</title>

<link rel="apple-touch-icon" sizes="180x180" href="<?= escapeHtml($base_dir) ?>/images/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= escapeHtml($base_dir) ?>/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= escapeHtml($base_dir) ?>/images/favicon-16x16.png">
<link rel="manifest" href="<?= escapeHtml($base_dir) ?>/site.webmanifest">

<?php
// Navigation bar
$nav_file = $_SERVER['DOCUMENT_ROOT'] . $base_dir . '/nav.php';
if (is_readable($nav_file)) {
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
$file  = $parts[count($parts) - 1] ?? 'reactions.php';

$cachefile = 'cache/cached-' . session_id() . '-' . substr_replace($file, '', -4) . '.html';
$_SESSION['rxn_cache_file'] = $cachefile;
$cachetime = 18000;

// Serve from cache if unchanged & fresh
if (
    ($_SESSION['rxn_changed'] ?? 0) === 0 &&
    isset($_SESSION['rxn_cache_file']) &&
    file_exists($_SESSION['rxn_cache_file']) &&
    (time() - $cachetime) < filemtime($_SESSION['rxn_cache_file'])
) {
    echo '<!-- Cached copy, generated ' . date('H:i', filemtime($_SESSION['rxn_cache_file'])) . " -->\n";
    readfile($_SESSION['rxn_cache_file']);
} else {
    ob_start(); // Start output buffer for caching

    $gene_sym_arr = $gene_syms !== '' ? explode(',', $gene_syms) : [];
    $i = 0;

    if (!empty($species) && !empty($gene_array) && ($_SESSION['rxn_changed'] ?? 0) === 1) {
        foreach ($gene_array as $value) {
            $gene_symbol_str = $gene_sym_arr[$i] ?? '';

            // sanitize ID before passing to R
            if ($value !== 'NA' && $value !== '' && preg_match('/^[A-Za-z0-9]+$/', $value)) {
                $h3_str = '<h3>Reaction information for <i><b>' .
                          escapeHtml($org_name) .
                          '</b></i> gene <i><b>' .
                          escapeHtml($gene_symbol_str) .
                          '</b></i></h3>';
                echo $h3_str;

                $view_type_html = 'html';

                $cmd_rxn = buildRscriptCommand('extractReactionInfo.R', [
                    $species,
                    $value,
                    $view_type_html,
                ]);

                $output = [];
                $retvar = 0;
                exec($cmd_rxn, $output, $retvar);

                // R script returns HTML table; we intentionally do NOT escape it
                // because downstream JS (tableHTMLExport) needs the real table markup.
                if ($retvar === 0) {
                    echo "<pre>";
                    echo implode("\n", $output);
                    echo "</pre><br>\n";
                } else {
                    $err = '<h3>Error running reaction extraction for <i><b>' .
                           escapeHtml($org_name) .
                           '</b></i> gene <i><b>' .
                           escapeHtml($gene_symbol_str) .
                           '</b></i></h3>';
                    echo $err . "<br>\n";
                }
            } else {
                $h3_str = '<h3>No reaction information found for <i><b>' .
                          escapeHtml($org_name) .
                          '</b></i> gene <i><b>' .
                          escapeHtml($gene_symbol_str) .
                          '</b></i></h3>';
                echo $h3_str . "<br>\n";
            }
            $i++;
        }

        // Export buttons
        echo '<p><button id="json">TO JSON</button> <button id="csv">TO CSV</button></p>';

        $_SESSION['rxn_changed'] = 0;

        // Write cache
        $cachefile = $_SESSION['rxn_cache_file'];
        if (!is_dir(dirname($cachefile))) {
            @mkdir(dirname($cachefile), 0755, true);
        }
        $cached = @fopen($cachefile, 'w');
        if ($cached) {
            fwrite($cached, ob_get_contents());
            fclose($cached);
        }
        ob_end_flush(); // send buffer
    }
}
?>
</p>

</div></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?= escapeHtml($base_dir) ?>/src/tableHTMLExport.js"></script>

<script>
  $('#json').on('click', function () {
    var gene_arr_str = '<?= json_encode($gene_array, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>';
    var gene_arr = JSON.parse(gene_arr_str);
    var len = gene_arr.length;
    var tabName = "";
    var fname = "";
    for (var i = 0; i < len; i++) {
        tabName = "#Gene" + gene_arr[i] + "Table";
        fname = "Gene" + gene_arr[i] + "Reactions.json";
        $(tabName).tableHTMLExport({ type: 'json', filename: fname });
    }
  });

  $('#csv').on('click', function () {
    var gene_arr_str = '<?= json_encode($gene_array, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>';
    var gene_arr = JSON.parse(gene_arr_str);
    var len = gene_arr.length;
    var tabName = "";
    var fname = "";
    for (var i = 0; i < len; i++) {
        tabName = "#Gene" + gene_arr[i] + "Table";
        fname = "Gene" + gene_arr[i] + "Reactions.csv";
        $(tabName).tableHTMLExport({ type: 'csv', filename: fname });
    }
  });
</script>

<?php
$footer_file = $_SERVER['DOCUMENT_ROOT'] . $base_dir . '/footer.php';
if (is_readable($footer_file)) {
    include $footer_file;
}
?>

</body>
</html>
