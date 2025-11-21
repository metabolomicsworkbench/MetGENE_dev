<?php
/******************************************************************************
 * MetGENE – Final Hardened nav.php (NO HTML WRAPPER)
 *
 * SECURITY:
 *  - Uses metgene_common.php for all sanitization & escaping
 *  - No <html>, <head>, <body> tags (safe for include inside other pages)
 *  - All GET input sanitized via safeGet()
 *  - All dynamic output escaped
 *  - Navigation URLs built with buildInternalUrl()
 *  - No Rscript command injection (escapeshellarg everywhere)
 ******************************************************************************/

declare(strict_types=1);

require_once __DIR__ . "/metgene_common.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------------------- Load sanitized input ------------------------ */
$speciesRaw   = safeGet("species");
$geneListRaw  = safeGet("GeneInfoStr");
$geneIDType   = safeGet("GeneIDType");
$disease      = safeGet("disease");
$anatomy      = safeGet("anatomy");
$phenotype    = safeGet("phenotype");

/* ---------------------------- Normalize species --------------------------- */
list($species, $organism_name, $org_sci_name) = normalizeSpecies($speciesRaw);

/* ---------------------------- Sanitize gene list -------------------------- */
$cleanGenes = sanitizeGeneList($geneListRaw);       // returns array of safe IDs
$geneList   = implode("__", $cleanGenes);

/* ---------------------------- Track session state ------------------------- */
$navFields = [
    "species"    => $species,
    "geneList"   => $geneList,
    "geneIDType" => $geneIDType,
    "disease"    => $disease,
    "anatomy"    => $anatomy,
    "phenotype"  => $phenotype,
];

$_SESSION['nav_changed'] = 0;

foreach ($navFields as $k => $v) {
    if (!isset($_SESSION[$k]) || !isset($_SESSION["prev_nav_$k"])) {
        $_SESSION[$k]            = "";
        $_SESSION["prev_nav_$k"] = "";
    }
    if ($_SESSION["prev_nav_$k"] !== $v) {
        $_SESSION["prev_nav_$k"] = $v;
        $_SESSION[$k]            = $v;
        $_SESSION['nav_changed'] = 1;
    }
}

/* ------------------- Execute ID→Symbol mapping when needed ---------------- */
if ($_SESSION['nav_changed'] === 1) {

    $allowedSpecies = ["hsa","mmu","rno"];
    if (!in_array($species, $allowedSpecies, true)) {
        $species = "hsa";
    }

    $allowedGeneTypes = ["SYMBOL","SYMBOL_OR_ALIAS","ENTREZID","ENSEMBL","REFSEQ","UNIPROT"];
    if (!in_array($geneIDType, $allowedGeneTypes, true)) {
        $geneIDType = "SYMBOL";
    }

    $cmd = "/usr/bin/Rscript "
        . escapeshellarg("extractGeneIDsAndSymbols.R") . " "
        . escapeshellarg($species) . " "
        . escapeshellarg($geneList) . " "
        . escapeshellarg($geneIDType) . " "
        . escapeshellarg($_SERVER['SERVER_NAME'] ?? "localhost");

    $symbol_geneIDs = [];
    $retCode = 0;
    exec($cmd, $symbol_geneIDs, $retCode);

    if ($retCode === 0 && !empty($symbol_geneIDs)) {
        $geneSymbolsArr = [];
        $geneIDsArr     = [];

        foreach ($symbol_geneIDs as $line) {
            $parts = explode(",", $line);
            foreach ($parts as $i => $val) {
                $cleanVal = trim($val, "\" ");
                if ($i < count($parts)/2) {
                    $geneSymbolsArr[] = $cleanVal;
                } else {
                    $geneIDsArr[] = $cleanVal;
                }
            }
        }

        $_SESSION['geneArray']   = $geneIDsArr;
        $_SESSION['geneSymbols'] = implode(",", $geneSymbolsArr);
        $_SESSION['geneListArr'] = explode("__", $geneList);

    } else {
        $_SESSION['geneArray']   = [];
        $_SESSION['geneSymbols'] = "";
        $_SESSION['geneListArr'] = [];
    }
}

/* ------------------ Store anatomy, disease, phenotype arrays -------------- */
$_SESSION['diseaseArray']   = explode("__", $disease);
$_SESSION['phenotypeArray'] = explode("__", $phenotype);
$_SESSION['anatomyArray']   = explode("__", $anatomy);

$_SESSION['org_name']     = $organism_name;
$_SESSION['species_name'] = $org_sci_name;

/* ------------------------- Build Navigation URLs -------------------------- */

$base = getBaseDirName();
$currentFile = basename($_SERVER["SCRIPT_NAME"]);

$navParams = [
    "species"     => $species,
    "GeneIDType"  => $geneIDType,
    "anatomy"     => $anatomy,
    "disease"     => $disease,
    "phenotype"   => $phenotype,
    "GeneInfoStr" => $geneList
];

/* Helper to build nav links */
function buildNavItem(string $base, string $file, array $params, bool $active): string {
    $url = buildInternalUrl($base, $file, $params);
    $cls = $active ? "dropbtnlit" : "dropbtn";
    $label = ucfirst(basename($file, ".php"));
    return '<a href="'.escapeHtml($url).'" class="'.$cls.'">'.escapeHtml($label).'</a>';
}

?>
<!-- ======================= NO HTML WRAPPER — nav only ===================== -->

<link rel="stylesheet" type="text/css"
      href="<?php echo escapeHtml($base); ?>/css/header.css">
<link rel="stylesheet" type="text/css"
      href="<?php echo escapeHtml($base); ?>/css/layout.css">

<div id="hdr">
    <div class="login-nav"
         style="background-image:url('<?php echo escapeHtml($base); ?>/images/MetGeneBanner.png');">
    </div>

    <ul id="header-nav" class="topnav" style="position:absolute;width:100%;bottom:-1px;">

        <li class="dropdown">
            <?= buildNavItem($base, "metGene.php",      $navParams, $currentFile === "metGene.php") ?>
        </li>
        <li class="dropdown">
            <?= buildNavItem($base, "geneInfo.php",     $navParams, $currentFile === "geneInfo.php") ?>
        </li>
        <li class="dropdown">
            <?= buildNavItem($base, "pathways.php",     $navParams, $currentFile === "pathways.php") ?>
        </li>
        <li class="dropdown">
            <?= buildNavItem($base, "reactions.php",    $navParams, $currentFile === "reactions.php") ?>
        </li>
        <li class="dropdown">
            <?= buildNavItem($base, "metabolites.php",  $navParams, $currentFile === "metabolites.php") ?>
        </li>
        <li class="dropdown">
            <?= buildNavItem($base, "studies.php",      $navParams, $currentFile === "studies.php") ?>
        </li>
        <li class="dropdown">
            <?= buildNavItem($base, "summary.php",      $navParams, $currentFile === "summary.php") ?>
        </li>

    </ul>
</div>
