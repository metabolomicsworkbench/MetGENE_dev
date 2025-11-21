<?php
/******************************************************************************
 * MetGENE – Hardened footer.php
 *
 * SECURITY FIXES:
 *  - No direct $_GET usage
 *  - Pulls only sanitized session variables
 *  - Uses metgene_common.php helpers for:
 *      • safe escaping
 *      • safe URL building
 *      • safe base directory resolution
 *  - Removes XSS vectors in links
 *  - Ensures all dynamic HTML attributes are escaped
 ******************************************************************************/

declare(strict_types=1);

require_once __DIR__ . "/metgene_common.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* --------------------------- LOAD SAFE SESSION VALUES ---------------------- */
/* Each of these was sanitized upstream via safeGet() + session storage */
$species    = $_SESSION['species']    ?? "hsa";
$geneList   = $_SESSION['geneList']   ?? "";
$geneIDType = $_SESSION['geneIDType'] ?? "";
$disease    = $_SESSION['disease']    ?? "NA";
$anatomy    = $_SESSION['anatomy']    ?? "NA";
$phenotype  = $_SESSION['phenotype']  ?? "NA";

/* ------------------------------- Base path -------------------------------- */
$base = getBaseDirName();

/* ------------------------- Build Terms & Contact URLs ---------------------- */
$footerParams = [
    "species"     => $species,
    "GeneInfoStr" => $geneList,
    "GeneIDType"  => $geneIDType,
    "disease"     => $disease,
    "anatomy"     => $anatomy,
    "phenotype"   => $phenotype
];

$termsUrl   = buildInternalUrl($base, "termsofuse.php", $footerParams);
$contactUrl = buildInternalUrl($base, "contact.php",   $footerParams);

/* ------------------------ Build Site Reset / Home Link --------------------- */
$resetUrl = buildInternalUrl($base, "index.php");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
</head>
<body>

<div id="constrain">
<div class="constrain">

<div id="ftr" class="footer">

    <ul class="journal-details">
        <li class="last">
            <a href="<?php echo escapeHtml($resetUrl); ?>">
                <img src="<?php echo escapeHtml($base); ?>/images/Reset_Btn.png"
                     alt="Reset" width="125">
            </a>
        </li>
    </ul>

    <ul class="footer-links">
        <li><a href="<?php echo escapeHtml($termsUrl); ?>">Terms of use</a></li>
        <li><a href="<?php echo escapeHtml($contactUrl); ?>">Contact</a></li>
    </ul>

    <span class="cleardiv"></span>
</div>

<!-- Logos -->
<table>
<tr>
    <td>&nbsp;</td>

    <td style="vertical-align:middle; width:60px;">
        <a href="https://www.ucsd.edu/" target="_blank">
            <img src="<?php echo escapeHtml($base); ?>/images/ucsd_logo.png"
                 alt="UCSD" width="80">
        </a>
    </td>

    <td style="vertical-align:middle; width:60px;">
        <a href="https://commonfund.nih.gov/dataecosystem" target="_blank">
            <img src="<?php echo escapeHtml($base); ?>/images/CFDEtransparent.png"
                 alt="CFDE" width="80">
        </a>
    </td>
</tr>
</table>

<!-- Admin banner -->
<table style="width:100%; border-collapse:collapse;">
    <tr>
        <td style="background-color: lightblue;
                   color: black;
                   padding: 10px;
                   text-align: center;
                   border: 1px solid black;">
            This repository is under review for potential modification
            in compliance with Administration directives.
        </td>
    </tr>
</table>

</div>
</div>

</body>
</html>
