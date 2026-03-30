<?php
require_once __DIR__ . "/metgene_common.php";

/* ------------------------------------------------------------------
   GENERATE NONCE + SEND SECURITY HEADERS WITH NONCE SUPPORT
   ------------------------------------------------------------------ */
$nonce = base64_encode(random_bytes(16));

header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' https://code.jquery.com https://requirejs.org 'nonce-$nonce'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data:; " .
    "object-src 'none'; " .
    "frame-ancestors 'self';"
);

/* ----------------------- SANITIZE INPUTS ----------------------- */
$raw_species    = safeGet("species");
$raw_geneID     = safeGet("GeneID");
$raw_geneList   = safeGet("GeneInfoStr");
$raw_geneIDType = safeGet("GeneIDType");
$raw_anatomy    = safeGet("anatomy");
$raw_disease    = safeGet("disease");
$raw_phenotype  = safeGet("phenotype");

/* ----------------------- LOAD VALIDATION TABLES ----------------------- */
$base_dir = getBaseDir();

list($diseaseSlimMap, $allowedDiseaseNames) =
    loadDiseaseSlimMap(__DIR__ . "/disease_pulldown_menu_cascaded.json");

$allowedAnatomy =
    loadAnatomyValuesFromHtml(__DIR__ . "/ssdm_sample_source_pulldown_menu.html");

/* ----------------------- VALIDATE FIELDS ----------------------- */
list($speciesNorm, $speciesDisplay, $speciesSci) =
    normalizeSpecies($raw_species);

$validatedDisease = validateDiseaseValue($raw_disease, $allowedDiseaseNames);
$validatedAnatomy = validateAnatomyValue($raw_anatomy, $allowedAnatomy);

$geneIDType = validateGeneIDType($raw_geneIDType);

/* ----------------------- CLEAN GENE FIELD ----------------------- */
if ($raw_geneID !== "") {
    $geneVec = cleanGeneList($raw_geneID);
} else {
    $geneVec = cleanGeneList($raw_geneList);
}

$dispStr = implode(",", $geneVec);
$geneIDtxtField = "<input type=\"text\" name=\"GeneInfoStr\" value=\"" . escapeHtml($dispStr) . "\">";
?>
<!DOCTYPE HTML>
<html>
<head>
<title>MetGENE: Query</title>

<link rel="apple-touch-icon" sizes="180x180" href="<?= $base_dir ?>/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= $base_dir ?>/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= $base_dir ?>/favicon-16x16.png">
<link rel="manifest" href="<?= $base_dir ?>/site.webmanifest">

<?php include(getNavIncludePath($base_dir, "nav_index.php")); ?>

<style type='text/css'>
button {
  font-size: 18px;
  display: inline-block;
  outline: 0;
  border: 0;
  cursor: pointer;
  will-change: box-shadow,transform;
  background: radial-gradient( 100% 100% at 100% 0%, #89E5FF 0%, #5468FF 100% );
  box-shadow: 0px 0.01em 0.01em rgb(45 35 66 / 40%), 0px 0.3em 0.7em -0.01em rgb(45 35 66 / 30%), inset 0px -0.01em 0px rgb(58 65 111 / 50%);
  padding: 0 2em;
  border-radius: 0.3em;
  color: #fff;
  height: 2.6em;
  text-shadow: 0 1px 0 rgb(0 0 0 / 40%);
  transition: box-shadow 0.15s ease, transform 0.15s ease;
}

button:hover {
  box-shadow: 0px 0.1em 0.2em rgb(45 35 66 / 40%), 0px 0.4em 0.7em -0.1em rgb(45 35 66 / 30%), inset 0px -0.1em 0px #3c4fe0;
  transform: translateY(-0.1em);
}

button:active {
  box-shadow: inset 0px 0.1em 0.6em #3c4fe0;
  transform: translateY(0em);
}
</style>

<!-- External scripts allowed by CSP -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://requirejs.org/docs/release/2.3.5/minified/require.js"></script>

</head>
<body>
<div id="constrain"><div class="constrain">

<p><span style="font-style: italic; color:#6A2F47;">
&nbsp;Welcome to the MetGENE Tool
</span></p>

<p style="font-family:georgia,garamond,serif;font-size:12px;font-style:italic;">
iven one or more genes, the MetGENE tool identifies associations between the gene(s) and the metabolites that are biosynthesized, metabolized, or transported by proteins coded by the genes. The gene(s) link to metabolites, the chemical transformations involving the metabolites through gene-specified proteins/enzymes, the functional association of these gene-associated metabolites and the pathways involving these metabolites.
</p>

<p style="font-family:georgia,garamond,serif;font-size:12px;font-style:italic;">
The user can specify the gene using a multiplicity of IDs and gene ID conversion tool translates these into harmonized IDs that are basis at the computational end for metabolite associations. Further, all studies involving the metabolites associated with the gene-coded proteins, as present in the Metabolomics Workbench (MW), the portal for the NIH Common Fund National Metabolomics Data Repository (NMDR), will be accessible to the user through the portal interface. The user can begin her/his journey from the NIH Common Fund Data Ecosystem (CFDE) portal. A tutorial for MetGENE is available <b><a href="/MW/docs/MetGENETutorial.pdf" target="_blank">here</a></b>.
</p>

<!-- ======================= FORM ======================= -->
<form action="<?= escapeHtml($base_dir) ?>/metGene.php" method="get" id="metGene">

<p>Use separator "," for multiple gene symbols or IDs.</p>

<table><tr>
<td>Gene_ID: <?= $geneIDtxtField ?></td>

<td>
Gene-ID Type:
<select name="GeneIDType">
<?php
foreach (["SYMBOL","SYMBOL_OR_ALIAS","ENTREZID","ENSEMBL","REFSEQ","UNIPROT"] as $t) {
    $sel = ($geneIDType === $t) ? "selected" : "";
    echo "<option value=\"$t\" $sel>$t</option>";
}
?>
</select>
</td>
</tr></table>

<p>Filter by:</p>

<table>
<tr>
<td><center><img src="<?= $base_dir ?>/images/organisms.png" width="100"></center></td>
<td><center><img src="<?= $base_dir ?>/images/anatomy.png" width="65"></center></td>
<td><center><img src="<?= $base_dir ?>/images/disease.png" width="60"></center></td>
<td><center><img src="<?= $base_dir ?>/images/phenotype.png" width="70"></center></td>
</tr>

<tr>
<td>
<center>
<select name="species">
<option value="hsa" <?= $speciesNorm==="hsa"?"selected":"" ?>>Human</option>
<option value="mmu" <?= $speciesNorm==="mmu"?"selected":"" ?>>Mouse</option>
<option value="rno" <?= $speciesNorm==="rno"?"selected":"" ?>>Rat</option>
</select>
</center>
</td>

<td>
<center>
<select name="anatomy">
    <option value="NA">Select anatomy/Sample source</option>
    <?php include(__DIR__ . "/ssdm_sample_source_pulldown_menu.html"); ?>
</select>
</center>
</td>

<td>
<center>
<select name="disease_slim" id="disease_slim">
<option value="">Select disease category</option>
</select><br>

<select name="disease" id="disease">
<option value="NA" selected>Select disease</option>
</select>
</center>
</td>

<td>
<center>
<select name="phenotype">
<option value="NA" selected>Select phenotype</option>
</select>
</center>
</td>

</tr>
</table>

<br><br>
<button id="submit_form">Submit</button>

</form>

<br><br>
<i>Please address questions/issues to
<a href="mailto:susrinivasan@ucsd.edu">susrinivasan@ucsd.edu</a>,
<a href="mailto:mano@sdsc.edu">mano@sdsc.edu</a></i>

<br><br>

<?php include(getNavIncludePath($base_dir, "footer.php")); ?>

</div></div>

<!-- ============================ -->
<!-- INLINE SCRIPT WITH NONCE     -->
<!-- ============================ -->
<script nonce="<?= $nonce ?>">
$(document).ready(function(){
    var submitted = false;

    $("#submit_form").click(function(){
        if (!submitted) {
            var invalid = false;
            var form = document.forms["metGene"];
            var geneStr = form.elements["GeneInfoStr"].value.replace(/\s/g,"");
            var genes = geneStr.split(",");
            var lettersnumerals = /^[0-9a-zA-Z]+$/;

            for (var g of genes) {
                if (!g.match(lettersnumerals)) {
                    alert("Gene " + g + " is not a valid name");
                    invalid = true;
                }
            }
            if (!invalid) {
                form.elements["GeneInfoStr"].value = genes.join("__");
                $("#metGene").submit();
            }
        }
        submitted = true;
    });
});
</script>

<!-- ============================ -->
<!-- DISEASE JSON LOADER (NONCE)  -->
<!-- ============================ -->
<script nonce="<?= $nonce ?>">
window.onload = function() {
    var slimSel = document.getElementById("disease_slim");
    var disSel  = document.getElementById("disease");

    fetch("disease_pulldown_menu_cascaded.json")
        .then(r => r.json())
        .then(data => {
            for (var cat in data) {
                slimSel.options.add(new Option(cat, cat));
            }
            slimSel.onchange = function() {
                disSel.length = 1;
                var group = this.value;
                for (var j in data[group]) {
                    let dn = data[group][j]['disease_name'];
                    disSel.options.add(new Option(dn, dn));
                }
            }
        });
};
</script>

</body>
</html>
