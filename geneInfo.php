<?php
/******************************************************************************
 * MetGENE – Secure & Consistent geneInfo.php
 *
 * FEATURES:
 *  - Hard security model: CSP, whitelist validation, sanitized Rscript calls
 *  - Zero direct GET access, everything through safeGet()
 *  - Gene/disease/anatomy values validated through metgene_common.php
 *  - Cached HTML output to speed up repeated queries
 *
 * AUTHOR: Regenerated securely (ChatGPT)
 *****************************************************************************/

declare(strict_types=1);
session_start();

require_once __DIR__ . '/metgene_common.php';

/* ---------------------------- SECURITY HEADERS ---------------------------- */
sendSecurityHeaders();

/* ---------------------------- COMPUTE BASE DIR ---------------------------- */
$base_dir = getBaseDir();    // wrapper added for compatibility
if ($base_dir === "") {
    $base_dir = "/MetGENE_dev"; // safe fallback
}

/* ---------------------------- SANITIZE GET INPUT -------------------------- */
$species     = safeGet("species");
$gene_raw    = safeGet("GeneInfoStr");
$geneType    = safeGet("GeneIDType");
$disease_raw = safeGet("disease");
$anatomy_raw = safeGet("anatomy");
$phenotype   = safeGet("phenotype");

/* ---------------------------- STORE IN SESSION ---------------------------- */
$_SESSION['species']    = $species;
$_SESSION['geneList']   = $gene_raw;
$_SESSION['geneIDType'] = $geneType;
$_SESSION['disease']    = $disease_raw;
$_SESSION['anatomy']    = $anatomy_raw;
$_SESSION['phenotype']  = $phenotype;

/* ---------------------------- NORMALIZE SPECIES --------------------------- */
[$species_code, $species_disp, $species_sci] = normalizeSpecies($species);

$_SESSION['org_name']      = $species_disp;
$_SESSION['species_name']  = $species_sci;

/* ---------------------------- VALIDATE DISEASE ---------------------------- */
[$diseaseMap, $allowedDiseaseNames] =
    loadDiseaseSlimMap("disease_pulldown_menu_cascaded.json");

$disease = validateDiseaseValue($disease_raw, $allowedDiseaseNames);

/* ---------------------------- VALIDATE ANATOMY ---------------------------- */
$allowedAnatomy =
    loadAnatomyValuesFromHtml("ssdm_sample_source_pulldown_menu.html");

$anatomy = validateAnatomyValue($anatomy_raw, $allowedAnatomy);

/* ---------------------------- SANITIZE GENE LIST -------------------------- */
$cleanGenes = cleanGeneList($gene_raw);
$_SESSION['geneArray']   = $cleanGenes;
$_SESSION['geneSymbols'] = implode(",", $cleanGenes);  // Rscript expects this

$gene_vec_str = implode("__", $cleanGenes);
$gene_sym_str = implode(", ", $cleanGenes);

/* ---------------------------- CHANGE TRACKING ----------------------------- */
$tracked = [
    "prev_species"  => $species,
    "prev_geneList" => $gene_raw,
    "prev_disease"  => $disease,
    "prev_anatomy"  => $anatomy,
];

$changed = 0;
foreach ($tracked as $k => $curVal) {
    if (!isset($_SESSION[$k]) || $_SESSION[$k] !== $curVal) {
        $_SESSION[$k] = $curVal;
        $changed = 1;
    }
}
$_SESSION['geneInfo_changed'] = $changed;

/* ---------------------------- CACHE FILE --------------------------------- */
$cachefile = buildCacheFilePath(
    "cache",
    basename(__FILE__),
    session_id()
);
$_SESSION['geneInfo_cache_file'] = $cachefile;

$cache_lifetime = 18000; // 5 hours

/* ---------------------------- SERVE CACHED OUTPUT -------------------------- */
if (
    $_SESSION['geneInfo_changed'] === 0 &&
    file_exists($cachefile) &&
    (time() - filemtime($cachefile)) < $cache_lifetime
) {
    echo "<!-- Cached copy generated at " .
         escapeHtml(date('Y-m-d H:i:s', filemtime($cachefile))) .
         " -->\n";
    readfile($cachefile);
    exit;
}

/* ---------------------------- BEGIN OUTPUT BUFFER ------------------------- */
ob_start();

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
/* ---------------------------- INCLUDE NAVIGATION -------------------------- */
$nav_include = getNavIncludePath($base_dir, "nav.php");
if (is_readable($nav_include)) {
    include $nav_include;
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
         escapeHtml($species_disp) .
         "</b> gene(s): <i><b>" .
         escapeHtml($gene_sym_str) .
         "</b></i></h3>";

    /* ----------------------- SECURE Rscript CALL ------------------------- */
    $cmd = buildRscriptCommand(
        "extractGeneInfoTable.R",
        [
            $species_code,   // normalized species
            $gene_vec_str,   // sanitized gene vector
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
$footer_include = getNavIncludePath($base_dir, "footer.php");
if (is_readable($footer_include)) {
    include $footer_include;
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
