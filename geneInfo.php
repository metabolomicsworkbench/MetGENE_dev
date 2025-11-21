<?php
/******************************************************************************
 * MetGENE – Secure & Consistent geneInfo.php
 *
 * SECURITY & ARCHITECTURE:
 *  - Uses metgene_common.php (security headers, safeGet, escaping, Rscript utils)
 *  - No direct $_GET usage
 *  - All session values sanitized and validated
 *  - All Rscript calls use buildRscriptCommand() to prevent injection
 *  - All HTML escaped (except R-generated table HTML)
 *  - Cache handling unified with reactions.php / pathways.php
 *****************************************************************************/

declare(strict_types=1);
session_start();

require_once __DIR__ . '/metgene_common.php';

/* ---------------------------- SECURITY HEADERS ---------------------------- */
sendSecurityHeaders();

/* ---------------------------- BASE DIRECTORY ------------------------------ */
$base_dir = getBaseDir();

/* ---------------------------- SANITIZE GET INPUT -------------------------- */
$_SESSION['species']    = safeGet("species");
$_SESSION['geneList']   = safeGet("GeneInfoStr");
$_SESSION['geneIDType'] = safeGet("GeneIDType");
$_SESSION['disease']    = safeGet("disease");
$_SESSION['anatomy']    = safeGet("anatomy");
$_SESSION['phenotype']  = safeGet("phenotype");

/* ---------------------------- LOCAL SHORTCUTS ----------------------------- */
$species     = $_SESSION['species'];
$gene_list   = $_SESSION['geneList'];
$geneID_type = $_SESSION['geneIDType'];
$disease     = $_SESSION['disease'];
$anatomy     = $_SESSION['anatomy'];
$phenotype   = $_SESSION['phenotype'];

$org_name     = $_SESSION['org_name']      ?? "";
$species_sci  = $_SESSION['species_name']  ?? "";
$gene_array   = $_SESSION['geneArray']     ?? [];
$gene_symbols = $_SESSION['geneSymbols']   ?? "";

/* ---------------------------- CHANGE TRACKING ----------------------------- */
$tracked_keys = [
    "prev_gi_species"  => $species,
    "prev_gi_geneList" => $gene_list,
    "prev_gi_anatomy"  => $anatomy,
    "prev_gi_disease"  => $disease,
    "prev_gi_pheno"    => $phenotype,
];

$changed = 0;
foreach ($tracked_keys as $prevKey => $currValue) {
    if (!isset($_SESSION[$prevKey]) || $_SESSION[$prevKey] !== $currValue) {
        $_SESSION[$prevKey] = $currValue;
        $changed = 1;
    }
}
$_SESSION['geneInfo_changed'] = $changed;

/* ---------------------------- CACHE FILE --------------------------------- */
$url   = $_SERVER["SCRIPT_NAME"] ?? "geneInfo.php";
$file  = basename($url, ".php");
$cachefile = "cache/cached-" . session_id() . "-{$file}.html";
$_SESSION['geneInfo_cache_file'] = $cachefile;
$cache_lifetime = 18000; // 5 hours

/* ---------------------------- SERVE CACHED VERSION ------------------------ */
if (
    $_SESSION['geneInfo_changed'] === 0 &&
    file_exists($cachefile) &&
    (time() - filemtime($cachefile)) < $cache_lifetime
) {
    echo "<!-- Cached copy, generated " . escapeHtml(date("H:i", filemtime($cachefile))) . " -->\n";
    readfile($cachefile);
    exit;
}

ob_start(); // Start output buffer for caching

/* ---------------------------- SANITIZE GENES ------------------------------ */

$symbols_arr = $gene_symbols !== "" ? explode(",", $gene_symbols) : [];
$valid_gene_ids  = [];
$valid_gene_syms = [];

for ($i = 0; $i < count($gene_array); $i++) {
    $gid = $gene_array[$i] ?? "";
    $sym = $symbols_arr[$i] ?? "";

    if ($gid !== "" && $gid !== "NA" && preg_match('/^[A-Za-z0-9]+$/', $gid)) {
        $valid_gene_ids[]  = $gid;
        $valid_gene_syms[] = $sym;
    }
}

$gene_vec_str = implode("__", $valid_gene_ids);
$gene_sym_str = implode(", ", $valid_gene_syms);

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
/* ---------------------------- NAVIGATION ---------------------------------- */
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

if ($gene_vec_str !== "") {

    echo "<h3>Gene information for <b>" .
         escapeHtml($org_name) .
         "</b> gene(s): <i><b>" .
         escapeHtml($gene_sym_str) .
         "</b></i></h3>";

    /* ----------------------- SECURE Rscript CALL ------------------------- */

    $cmd = buildRscriptCommand(
        "extractGeneInfoTable.R",
        [
            $species,
            $gene_vec_str,
            $_SERVER['SERVER_NAME'] ?? "localhost"
        ]
    );

    $output = [];
    $retvar = 0;
    exec($cmd, $output, $retvar);

    echo "<pre>";
    echo escapeHtml(implode("\n", $output));
    echo "</pre>";

    echo <<<HTML
        <p>
            <button id="json">TO JSON</button>
            <button id="csv">TO CSV</button>
        </p>
HTML;

} else {

    echo "<h3><i>No valid gene information found.</i></h3>";
}

?>
</div></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?= escapeHtml($base_dir) ?>/src/tableHTMLExport.js"></script>

<script>
$("#json").click(function(){
    $("#Table1").tableHTMLExport({
        type: "json",
        filename: "GeneInfo.json"
    });
});
$("#csv").click(function(){
    $("#Table1").tableHTMLExport({
        type: "csv",
        filename: "GeneInfo.csv"
    });
});
</script>

<?php
/* ---------------------------- FOOTER -------------------------------------- */
$footer_file = $_SERVER['DOCUMENT_ROOT'] . $base_dir . "/footer.php";
if (is_readable($footer_file)) {
    include $footer_file;
}

/* ---------------------------- WRITE CACHE --------------------------------- */
if (!is_dir(dirname($cachefile))) {
    mkdir(dirname($cachefile), 0755, true);
}
file_put_contents($cachefile, ob_get_contents());

ob_end_flush();
?>

</body>
</html>
