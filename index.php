<?php
/******************************************************************************
 * MetGENE – Hardened index.php
 * Uses metgene_common.php for all shared security, sanitization, whitelist,
 * species normalization, URL building, etc.
 ******************************************************************************/
/* Enables strict typing in PHP */
declare(strict_types=1);

/* ---------------------------- LOAD COMMON MODULE --------------------------- */
require_once __DIR__ . "/metgene_common.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------------------- SECURITY HEADERS ----------------------------- */
sendSecurityHeaders();

/* ---------------------------- SAFE BASE DIR --------------------------------*/
$METGENE_BASE_DIR_NAME = getBaseDirName();

/* ---------------------------- SAFE GET INPUTS ------------------------------*/
$species      = safeGet("species");
$geneIDType   = safeGet("GeneIDType");
$geneID       = safeGet("GeneID");
$geneList     = safeGet("GeneInfoStr");
$anatomy      = safeGet("anatomy");
$disease      = safeGet("disease");
$diseaseSlim  = safeGet("disease_slim");
$phenotype    = safeGet("phenotype");

/* ---------------------------- NAV INCLUDE ----------------------------------*/
$navFile = getNavIncludePath($METGENE_BASE_DIR_NAME, "nav_index.php");
if (is_readable($navFile)) {
    include $navFile;
}

/* ---------------------------- LOAD DISEASE JSON ----------------------------*/
$jsonFile = __DIR__ . "/disease_pulldown_menu_cascaded.json";
list($diseaseSlimMap, $allowedDiseaseNames) = loadDiseaseSlimMap($jsonFile);

/* ---------------------------- VALIDATE DISEASE -----------------------------*/
$disease = validateDiseaseValue($disease, $allowedDiseaseNames);

/* ---------------------------- LOAD ANATOMY OPTIONS -------------------------*/
$anatomyFile = __DIR__ . "/ssdm_sample_source_pulldown_menu.html";
$allowedAnatomyValues = loadAnatomyValuesFromHtml($anatomyFile);

/* ---------------------------- VALIDATE ANATOMY -----------------------------*/
$anatomy = validateAnatomyValue($anatomy, $allowedAnatomyValues);

/* ---------------------------- NORMALIZE SPECIES ----------------------------*/
list($species, $organism_name, $org_sci_name) = normalizeSpecies($species);

/* ---------------------------- SANITIZE GENE ID TYPE --------------------------------*/
$geneIDType = validateGeneIDType($geneIDType);

/* ---------------------------- SANITIZE GENE LIST ---------------------------*/
$rawGeneInput = ($geneID !== "" ? $geneID : $geneList);

$cleanGenes = cleanGeneList($rawGeneInput);

$dispStr = implode(",", $cleanGenes);

$geneIDtxtField  = '<input type="text" name="GeneInfoStr" value="'.escapeHtml($dispStr).'">';

?>
<!DOCTYPE HTML>
<html>
<head>
    <title>MetGENE: Query</title>

    <link rel="apple-touch-icon" sizes="180x180"
          href="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/images/apple-touch-icon.png">

    <link rel="icon" type="image/png" sizes="32x32"
          href="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/images/favicon-32x32.png">

    <style>
        .btn {
            background-color: rgb(7,55,99);
            color: white;
            border: none;
            cursor: pointer;
            padding: 2px 12px 3px 12px;
            text-decoration: none;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
<div id="constrain">
<div class="constrain">

<p><span style="font-style: italic; color: #6A2F47;">&nbsp;Welcome to the MetGENE Tool</span></p>

<p style="font-family:georgia,garamond,serif;font-size:12px;font-style:italic;">
Given one or more genes, the MetGENE tool identifies associations …
</p>

<?php
$formAction = buildInternalUrl($METGENE_BASE_DIR_NAME, "metGene.php");
?>
<form action="<?php echo escapeHtml($formAction); ?>" method="get" id="metGene">

<p>Use separator "," for multiple gene symbols or IDs.</p>

<table>
<tr>
    <td>Gene_ID: <?php echo $geneIDtxtField; ?></td>
    <td>
        Gene-ID Type:
        <select name="GeneIDType">
        <?php
        $allowedTypes = ["SYMBOL","SYMBOL_OR_ALIAS","ENTREZID","ENSEMBL","REFSEQ","UNIPROT"];
        foreach ($allowedTypes as $t) {
            $sel = ($t === $geneIDType) ? " selected" : "";
            echo '<option value="'.escapeHtml($t).'"'.$sel.'>'.escapeHtml($t).'</option>';
        }
        ?>
        </select>
    </td>
</tr>
</table>

<p>Filter by:</p>

<table>
<tr>
    <td><center><img src="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/images/organisms.png" width="100"></center></td>
    <td><center><img src="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/images/anatomy.png" width="65"></center></td>
    <td><center><img src="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/images/disease.png" width="60"></center></td>
</tr>

<tr>
    <!-- SPECIES -->
    <td>
        <center>
        <select name="species">
            <option value="hsa" <?php echo $species==="hsa"?"selected":""; ?>>Human</option>
            <option value="mmu" <?php echo $species==="mmu"?"selected":""; ?>>Mouse</option>
            <option value="rno" <?php echo $species==="rno"?"selected":""; ?>>Rat</option>
        </select>
        </center>
    </td>

    <!-- ANATOMY -->
    <td>
        <center>
        <select name="anatomy">
            <option value="NA" <?php echo $anatomy==="NA"?"selected":""; ?>>Select anatomy/Sample source</option>

            <?php if (is_readable($anatomyFile)) include $anatomyFile; ?>

        </select>
        </center>
    </td>

    <!-- DISEASE -->
    <td>
        <center>

        <select name="disease_slim">
            <option value="">Select disease/phenotype category</option>
            <?php
            foreach ($diseaseSlimMap as $cat => $entries) {
                $sel = ($cat === $diseaseSlim) ? "selected" : "";
                echo '<option value="'.escapeHtml($cat).'" '.$sel.'>'.escapeHtml($cat).'</option>';
            }
            ?>
        </select>

        <br>

        <select name="disease">
            <option value="NA">Select disease</option>
            <?php
            if ($diseaseSlim !== "" && isset($diseaseSlimMap[$diseaseSlim])) {
                foreach ($diseaseSlimMap[$diseaseSlim] as $entry) {
                    if (!isset($entry['disease_name'])) continue;
                    $dn  = $entry['disease_name'];
                    $sel = ($dn === $disease) ? "selected" : "";
                    echo '<option value="'.escapeHtml($dn).'" '.$sel.'>'.escapeHtml($dn).'</option>';
                }
            }
            ?>
        </select>

        </center>
    </td>
</tr>
</table>

<br><br>
<span class="btn" id="submit_form">Submit</span>
</form>

<script>
/* Client-side gene validation (server already validates securely) */
$(document).ready(function(){
    var submitted = false;

    $("#submit_form").click(function(){
        if (!submitted) {
            var formObj = document.forms["metGene"];
            var geneStr = formObj.elements["GeneInfoStr"].value.replace(/\s/g,'');
            var genes   = geneStr.split(",");
            var pat     = /^[A-Za-z0-9._-]+$/;

            for (let g of genes) {
                if (g && !pat.test(g)) {
                    alert("Invalid gene name: " + g);
                    return;
                }
            }

            formObj.elements["GeneInfoStr"].value = genes.join("__");
            $("#metGene").submit();
        }
        submitted = true;
    });
});
</script>

</div>
</div>
</body>
</html>
