<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . "/metgene_common.php";
sendSecurityHeaders();

/* Restore session variables (escaped later if used) */
$species   = $_SESSION['species']   ?? '';
$geneList  = $_SESSION['geneList']  ?? '';
$disease   = $_SESSION['disease']   ?? '';
$anatomy   = $_SESSION['anatomy']   ?? '';
$phenotype = $_SESSION['phenotype'] ?? '';

$base_dir = getBaseDir();   // consistent and secure
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MetGENE: Contact</title>

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
p { font-size: 16px; line-height: 1.6; }
</style>
</head>

<body>

<div id="constrain">
    <div class="constrain">

        <h1>Contact</h1>

        <p style="margin:25px;">
            <strong>MetGENE – Gene-centric Information Retrieval Tool</strong>
            <br>c/o Dr. Shankar Subramaniam, Ph.D.
            <br>9500 Gilman Drive
            <br>La Jolla, CA 92093
            <br>Phone: 858-822-0986
            <br>E-mail: <a href="mailto:shankar@ucsd.edu">shankar@ucsd.edu</a>

            <br><br>
            <i>Please address questions about MetGENE to
                <a href="mailto:susrinivasan@ucsd.edu">susrinivasan@ucsd.edu</a>,
                <a href="mailto:mano@sdsc.edu">mano@sdsc.edu</a>
            </i>
        </p>

    </div>
</div>

<?php include($_SERVER['DOCUMENT_ROOT'] . $base_dir . "/footer.php"); ?>

</body>
</html>
