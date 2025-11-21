<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . "/metgene_common.php";
sendSecurityHeaders();

/* Restore session variables (escaped later if printed) */
$species   = $_SESSION['species']   ?? '';
$geneList  = $_SESSION['geneList']  ?? '';
$disease   = $_SESSION['disease']   ?? '';
$anatomy   = $_SESSION['anatomy']   ?? '';
$phenotype = $_SESSION['phenotype'] ?? '';

$base_dir = getBaseDir();   // from metgene_common.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Terms of Use</title>

<link rel="apple-touch-icon" sizes="180x180" href="<?= escapeHtml($base_dir) ?>/images/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= escapeHtml($base_dir) ?>/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= escapeHtml($base_dir) ?>/images/favicon-16x16.png">
<link rel="manifest" href="<?= escapeHtml($base_dir) ?>/site.webmanifest">

<?php include($_SERVER['DOCUMENT_ROOT'] . $base_dir . "/nav.php"); ?>

<style>
#constrain {
    max-width: 950px;
    margin: auto;
}
h1 { margin-top: 30px; }
p { font-size: 16px; line-height: 1.5; }
</style>
</head>

<body>

<div id="constrain">
    <div class="constrain">
        <h1>Terms of Use</h1>

        <p style="margin:25px;">
            MetGENE tool is provided by the Metabolomics Workbench Data Coordination Center (DCC)
            of the NIH Common Fund Data Ecosystem (CFDE) on an “as is” basis, without warranty or
            representation of any kind, express or implied. The content of the tool is protected by
            international copyright, trademark and other laws.
            <br><br>
            You may download outputs (such as tables and web pages) from this site for your personal,
            non-commercial use only, provided that you keep intact all authorship, copyright and
            other proprietary notices.
            <br><br>
            If you use this tool, you accept these terms. MW DCC reserves the right to modify these
            terms at any time.
        </p>
    </div>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . $base_dir . "/footer.php"); ?>

</body>
</html>
