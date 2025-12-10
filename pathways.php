<?php
/******************************************************************************
 * MetGENE – Hardened pathways.php (SECURE & CONSISTENT)
 *
 * SECURITY + ARCHITECTURE:
 *  - Uses metgene_common.php for escaping, sanitization, Rscript building
 *  - No direct $_GET access (all via session or safeGet)
 *  - All exec() calls use buildRscriptCommand() → escapeshellarg()
 *  - All HTML output escaped except R-generated HTML tables
 *  - Navigation and footer includes validated
 *  - Gene arrays sanitized and validated identical to reactions.php
 *****************************************************************************/

declare(strict_types=1);
session_start();

require_once __DIR__ . '/metgene_common.php';

sendSecurityHeaders();

$base_dir = getBaseDir(); // consistent with reactions.php

/* --------------------------------------------------------------------------
 * READ SESSION VALUES SAFELY
 * -------------------------------------------------------------------------- */
$species       = $_SESSION['species']       ?? '';
$disease       = $_SESSION['disease']       ?? '';
$anatomy       = $_SESSION['anatomy']       ?? '';
$phenotype     = $_SESSION['phenotype']     ?? '';
$gene_array    = $_SESSION['geneArray']     ?? [];
// SECURITY FIX: Ensure geneArray is actually an array
if (!is_array($gene_array)) {
    $gene_array = [];
}

$gene_symbols  = $_SESSION['geneSymbols']   ?? '';
$species_sci   = $_SESSION['species_name']  ?? '';
$org_name      = $_SESSION['org_name']      ?? '';

/* --------------------------------------------------------------------------
 * SANITIZE GENE IDs & SYMBOLS
 * -------------------------------------------------------------------------- */

$clean_gene_ids  = [];
$clean_gene_syms = [];

$symbol_parts = $gene_symbols !== '' ? explode(',', $gene_symbols) : [];
$pat = '/^[A-Za-z0-9]+$/';

for ($i = 0; $i < count($gene_array); $i++) {

    $gid = $gene_array[$i] ?? '';
    $sym = $symbol_parts[$i] ?? '';

    if ($gid === '' || $gid === 'NA') {
        continue;
    }
    if (preg_match($pat, $gid)) {
        $clean_gene_ids[]  = $gid;
        $clean_gene_syms[] = $sym;
    }
}

$gene_vec_str = implode(',', $clean_gene_ids);
$gene_sym_str = implode(',', $clean_gene_syms);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MetGENE: Pathways</title>

<link rel="apple-touch-icon" sizes="180x180" href="<?= escapeHtml($base_dir) ?>/images/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= escapeHtml($base_dir) ?>/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= escapeHtml($base_dir) ?>/images/favicon-16x16.png">
<link rel="manifest" href="<?= escapeHtml($base_dir) ?>/site.webmanifest">

<?php
// Safe include of nav
$nav_file = $_SERVER['DOCUMENT_ROOT'] . $base_dir . '/nav.php';
if (is_readable($nav_file)) {
    include $nav_file;
}
?>
</head>

<body>
<div id="constrain"><div class="constrain">
<br><br>

<?php
/******************************************************************************
 * MAIN CONTENT
 *****************************************************************************/

if ($gene_vec_str !== '') {

    echo "<h3>Pathway information for <b>" .
         escapeHtml($org_name) .
         "</b> gene(s): <i><b>" .
         escapeHtml($gene_sym_str) .
         "</b></i></h3>";

    /* ---------------------------------------------------------------
     * SECURE RSCRIPT CALL THROUGH buildRscriptCommand()
     * --------------------------------------------------------------- */

    $cmd = buildRscriptCommand(
        'extractPathwayInfo.R',
        [
            $species,
            $gene_vec_str,
            $gene_sym_str,
            $species_sci
        ]
    );

    // SECURITY FIX: Check if command was built successfully
    if ($cmd === '') {
        echo "<p style='color:red;'>Error: Pathway information script not available.</p>";
        error_log("SECURITY: extractPathwayInfo.R not found or not readable");
    } else {
        $output = [];
        $retvar = 0;
        exec($cmd, $output, $retvar);
        
        // SECURITY FIX: Check return code
        if ($retvar !== 0) {
            error_log("R script extractPathwayInfo.R failed with exit code: $retvar");
            echo "<p style='color:red;'>Error retrieving pathway information.</p>";
        } else {
            echo "<pre>";
            /* Pathway script returns real HTML tables — DO NOT escape them */
            echo implode("\n", $output);
            echo "</pre>";
        }
    }

} else {

    echo "<h3><i>No valid gene identifiers found for <b>" .
         escapeHtml($org_name) .
         "</b>.</i></h3>";
}
?>

</div></div>

<?php
$footer_file = $_SERVER['DOCUMENT_ROOT'] . $base_dir . "/footer.php";
if (is_readable($footer_file)) {
    include $footer_file;
}
?>

</body>
</html>
