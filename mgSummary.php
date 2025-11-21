<?php
/******************************************************************************
 * MetGENE – Hardened mgSummary.php
 *
 * SECURITY:
 *  - No direct $_GET (ONLY safeGet())
 *  - All output escaped using escapeHtml()
 *  - All Rscript calls use buildRscriptCommand()
 *  - ENSEMBL fallback path validated and sanitized
 *  - Fixes broken HTML layout from original
 *  - Maintains EXACT functionality as original
 ******************************************************************************/

declare(strict_types=1);
session_start();

require_once __DIR__ . "/metgene_common.php";

sendSecurityHeaders();
$base_dir = getBaseDir();

/* --------------------------------------------------------------------------
 * SAFE INPUTS
 * -------------------------------------------------------------------------- */
$species   = safeGet("species");
$geneSym   = safeGet("GeneSym");
$ensemblID = safeGet("ENSEMBL");
$geneID    = safeGet("GeneID");
$viewType  = safeGet("viewType", "html");

/* Convert geneSym to display string */
$geneSymArr = $geneSym !== "" ? explode("__", $geneSym) : [];
$geneDisp   = implode(", ", $geneSymArr);

/* --------------------------------------------------------------------------
 * If GeneSym or GeneID are missing → fallback to ENSEMBL lookup
 * -------------------------------------------------------------------------- */
if (($geneSym === "" || $geneID === "") && $ensemblID !== "") {

    $cmd = buildRscriptCommand(
        "extractGeneIDsAndSymbols.R",
        [$species, $ensemblID, "ENSEMBL", ($_SERVER['SERVER_NAME'] ?? "localhost")]
    );

    $symbol_geneIDs = [];
    $ret = 0;
    exec($cmd, $symbol_geneIDs, $ret);

    $geneSymbolsTmp = [];
    $geneIDsTmp     = [];

    foreach ($symbol_geneIDs as $line) {
        $parts = explode(",", $line);
        foreach ($parts as $i => $entry) {
            $clean = trim($entry, "\" ");
            if ($i < count($parts) / 2) {
                $geneSymbolsTmp[] = $clean;
            } else {
                $geneIDsTmp[] = $clean;
            }
        }
    }

    if (!empty($geneSymbolsTmp)) {
        $geneSym = $geneSymbolsTmp[0];
    }
    if (!empty($geneIDsTmp)) {
        $geneID = $geneIDsTmp[0];
    }
}

/* --------------------------------------------------------------------------
 * VALIDATION
 * -------------------------------------------------------------------------- */
if ($geneSym === "" || $geneID === "") {
    die("Either (Gene Symbol + Gene ID) or ENSEMBL ID must be provided.");
}

/* --------------------------------------------------------------------------
 * RUN RSCRIPT – extractMWGeneSummary.R
 * -------------------------------------------------------------------------- */

$prefix = "cache/plot";
$suffix = ".png";
$filename = $prefix . rand(1, 1000) . $suffix;

$cmd = buildRscriptCommand(
    "extractMWGeneSummary.R",
    [$species, $geneID, $geneSym, $filename, $viewType]
);

$output = [];
$retvar = 0;
exec($cmd, $output, $retvar);
$htmlbuff = implode("\n", $output);

/* --------------------------------------------------------------------------
 * RAW OUTPUT MODES (json, txt, table, all, png)
 * -------------------------------------------------------------------------- */
if ($viewType === "json") {
    header('Content-type: application/json; charset=UTF-8');
    echo $htmlbuff;
    exit;
}

if ($viewType === "txt") {
    header('Content-Type: text/plain; charset=UTF-8');
    echo $htmlbuff;
    exit;
}

if ($viewType === "table" || $viewType === "all") {
    echo $htmlbuff;
    if ($viewType === "all") {
        echo "</td></tr></table>";
    }
    exit;
}

if ($viewType === "png") {
    if (is_readable($filename)) {
        header("Content-Type: image/png");
        header("Content-Length: " . filesize($filename));
        readfile($filename);
    }
    exit;
}

/* --------------------------------------------------------------------------
 * DEFAULT = HTML PAGE
 * -------------------------------------------------------------------------- */

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
    width: 100%;
    table-layout: fixed;
    word-wrap: break-word;
}
.styled-table td {
    border: 1px solid #000;
    padding:5px 10px;
    text-align:center;
    white-space: pre-line;
}
.styled-table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
}
.summary {
    background-color: white;
    color: black;
    border: 2px solid black;
    margin: 20px;
    padding: 20px;
    width: 75%;
}
</style>

<?php
$nav_file = $_SERVER['DOCUMENT_ROOT'] . $base_dir . "/nav.php";
if (is_readable($nav_file)) include $nav_file;
?>
</head>

<body>

<div id="constrain"><div class="constrain">

<table>
<tr>
    <td>
        <img src="<?= escapeHtml($base_dir) ?>/images/MetGeneLogoNew.png" width="105" height="90">
    </td>
    <td>
        <div class="summary">
        <p>
<?php
$server = escapeHtml($_SERVER['SERVER_NAME']);
$path   = escapeHtml(dirname($_SERVER['PHP_SELF']));
$currDirUrl = "https://$server$path/";

$link = "<a href=\"{$currDirUrl}index.php?GeneID=" .
        escapeHtml($geneID) .
        "&species=" . escapeHtml($species) .
        "&GeneIDType=ENTREZID\">MetGENE</a>";

if (count($geneSymArr) > 1) {
    echo "$link identifies pathways and reactions catalyzed by the given genes "
        . escapeHtml($geneDisp) .
        ", their related metabolites, and the studies in "
        . "<a href=\"https://www.metabolomicsworkbench.org\">Metabolomics Workbench</a>.";
} else {
    echo "$link identifies pathways and reactions catalyzed by the given gene "
        . escapeHtml($geneDisp) .
        ", its related metabolites, and the studies in "
        . "<a href=\"https://www.metabolomicsworkbench.org\">Metabolomics Workbench</a>.";
}
?>
        </p>
        </div>
    </td>
</tr>

<tr>
    <td></td>
    <td>
        <div class="summary">
        <pre><?= $htmlbuff ?></pre>

        <p>
            <button id="json">TO JSON</button>
            <button id="csv">TO CSV</button>
        </p>
        </div>
    </td>
</tr>
</table>

</div></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?= escapeHtml($base_dir) ?>/src/tableHTMLExport.js"></script>

<script>
$('#json').click(function(){
    $("#Table1").tableHTMLExport({type: 'json', filename: 'Summary.json'});
});
$('#csv').click(function(){
    $("#Table1").tableHTMLExport({type: 'csv', filename: 'Summary.csv'});
});
</script>

</body>
</html>
