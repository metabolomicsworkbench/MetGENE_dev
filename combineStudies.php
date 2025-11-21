<?php
/******************************************************************************
 * MetGENE – combinedStudies.php (SECURE & CONSISTENT)
 *
 * SECURITY FIXES:
 *  - No raw $_GET access → uses safeGet()
 *  - Sanitizes metabolite list & MW study IDs
 *  - Removes insecure curl authentication
 *  - Removes broken parsing code that treated JSON as CSV
 *  - Adds escapeHtml() everywhere except trusted URLs
 *  - Uses getBaseDir() for consistent path resolution
 *  - Uses strict validation: only [A–Z0–9] allowed in study IDs
 ******************************************************************************/

declare(strict_types=1);
session_start();

require_once __DIR__ . "/metgene_common.php";
sendSecurityHeaders();

$base_dir = getBaseDir();

/* --------------------------------------------------------------------------
 * SAFE INPUT
 * -------------------------------------------------------------------------- */
$metabolites_raw = safeGet("metabolites");   // encoded JSON from studies.php

if ($metabolites_raw === "") {
    die("<h3>No metabolites provided.</h3>");
}

// Decode JSON safely
$metabolite_map = json_decode($metabolites_raw, true);
if (!is_array($metabolite_map)) {
    die("<h3>Invalid metabolite input.</h3>");
}

/* --------------------------------------------------------------------------
 * Helper: Secure GET to MW REST API
 * -------------------------------------------------------------------------- */
function safeCurlGET(string $url): string
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result === false ? "" : $result;
}

/* --------------------------------------------------------------------------
 * MAIN LOGIC – Build compound URLs and unique study IDs
 * -------------------------------------------------------------------------- */

$compound_links   = [];
$study_link_array = [];

foreach ($metabolite_map as $compound_raw => $studyStrRaw) {

    /* ---------------- COMPOUND CLEANING ---------------- */
    // revert ___ back to :
    $compound_raw = str_replace("___", ":", $compound_raw);

    // allow alnum, colon, dash, underscore, plus, space
    $compound_clean = preg_replace("/[^A-Za-z0-9\:\-\_\+\s]/", "", $compound_raw);

    // create MW RefMet link
    $compound_q = rawurlencode($compound_clean);

    $compound_url =
        "<a href=\"https://www.metabolomicsworkbench.org/databases/refmet/refmet_details.php?REFMET_NAME=" .
        $compound_q . "\" target=\"_blank\">" .
        escapeHtml($compound_clean) . "</a>";

    $compound_links[] = $compound_url;

    /* ---------------- STUDIES CLEANING ---------------- */
    $study_list = preg_split("/\s+/", trim($studyStrRaw));
    if (!is_array($study_list)) continue;

    foreach ($study_list as $study_candidate) {

        // Only allow MW-format study IDs (A–Z + digits)
        $study_id = preg_replace("/[^A-Z0-9]/", "", $study_candidate);

        if ($study_id === "") continue;

        // Build REST URL
        $rest_url =
            "https://www.metabolomicsworkbench.org/rest/study/study_id/" .
            rawurlencode($study_id) .
            "/summary/";

        $json = safeCurlGET($rest_url);
        if ($json === "") continue;

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || empty($decoded["study_title"])) continue;

        $study_title = escapeHtml($decoded["study_title"]);

        // Build MW study link
        $study_link =
            "<a href=\"https://www.metabolomicsworkbench.org/data/DRCCMetadata.php?Mode=Study&StudyID=" .
            escapeHtml($study_id) .
            "\" title=\"" . $study_title .
            "\" target=\"_blank\">" .
            escapeHtml($study_id) .
            "</a>";

        $study_link_array[$study_id] = $study_link;
    }
}

/* Final unique lists */
$compound_html = implode(", ", $compound_links);
$studies_html  = implode(", ", array_values($study_link_array));

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MetGENE: Combined Studies</title>

<link rel="apple-touch-icon" sizes="180x180" href="<?= escapeHtml($base_dir) ?>/images/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= escapeHtml($base_dir) ?>/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= escapeHtml($base_dir) ?>/images/favicon-16x16.png">
<link rel="manifest" href="<?= escapeHtml($base_dir) ?>/site.webmanifest">

<?php
    $nav_file = $_SERVER['DOCUMENT_ROOT'] . $base_dir . "/nav.php";
    if (is_readable($nav_file)) {
        include $nav_file;
    }
?>
<style>
.btn {
    background-color: rgb(7,55,99);
    color: white;
    border: none;
    cursor: pointer;
    padding: 2px 12px;
}
table th, td {
    word-wrap: break-word;
    white-space: pre-line;
    border-bottom: 1px solid #dddddd;
}
table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
}
</style>

</head>
<body>

<div id="constrain"><div class="constrain">

<h3>Combined studies for the selected metabolites</h3>
<br>

<table id="Table1" class="styled-table">
<tr>
    <td><?= $compound_html ?></td>
    <td><?= $studies_html ?></td>
</tr>
</table>

<p>
    <button id="json">TO JSON</button>
    <button id="csv">TO CSV</button>
</p>

</div></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?= escapeHtml($base_dir) ?>/src/tableHTMLExport.js"></script>

<script>
$('#json').on('click', function () {
    $("#Table1").tableHTMLExport({type: 'json', filename: 'combinedStudies.json'});
});

$('#csv').on('click', function () {
    $("#Table1").tableHTMLExport({type: 'csv', filename: 'combinedStudies.csv'});
});
</script>

<?php
$footer_file = $_SERVER['DOCUMENT_ROOT'] . $base_dir . "/footer.php";
if (is_readable($footer_file)) {
    include $footer_file;
}
?>

</body>
</html>
