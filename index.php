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
.btn {
    background-color: rgb(7,55,99);
    color: white;
    border: none;
    cursor: pointer;
    padding: 2px 12px 3px 12px;
    text-decoration: none;
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
<option value="NA" <?= $validatedAnatomy==="NA"?"selected":"" ?>>
Select anatomy/Sample source
</option>
<option value = "293T HEK cell line">293T HEK cell line</option>
<option value = "Acorns">Acorns</option>
<option value = "Adherent cultured mammalian cells">Adherent cultured mammalian cells</option>
<option value = "Adipose tissue">Adipose tissue</option>
<option value = "Adrenal gland">Adrenal gland</option>
<option value = "Algae">Algae</option>
<option value = "Algae oil">Algae oil</option>
<option value = "AML cells">AML cells</option>
<option value = "Amniotic fluid">Amniotic fluid</option>
<option value = "Aqueous humour">Aqueous humour</option>
<option value = "Arteries">Arteries</option>
<option value = "Astrocyte cells">Astrocyte cells</option>
<option value = "B-cells">B-cells</option>
<option value = "B cells">B cells</option>
<option value = "B Cells">B Cells</option>
<option value = "Bacteria">Bacteria</option>
<option value = "Bacterial cells">Bacterial cells</option>
<option value = "Bacterial Cells">Bacterial Cells</option>
<option value = "Biofilm">Biofilm</option>
<option value = "Biopsy">Biopsy</option>
<option value = "Bladder">Bladder</option>
<option value = "Blank/QC">Blank/QC</option>
<option value = "Blastocysts">Blastocysts</option>
<option value = "Blood">Blood</option>
<option value = "Blood (plasma)">Blood (plasma)</option>
<option value = "Blood (plasma) and tumor tissue">Blood (plasma) and tumor tissue</option>
<option value = "Blood (serum)">Blood (serum)</option>
<option value = "Blood (serum) and Liver">Blood (serum) and Liver</option>
<option value = "Blood (whole)">Blood (whole)</option>
<option value = "Blubber">Blubber</option>
<option value = "Bone">Bone</option>
<option value = "Bone and bone marrow">Bone and bone marrow</option>
<option value = "Bone marrow">Bone marrow</option>
<option value = "Bone Marrow">Bone Marrow</option>
<option value = "brain">brain</option>
<option value = "Brain">Brain</option>
<option value = "Brain - Cerebral Hemisphere">Brain - Cerebral Hemisphere</option>
<option value = "Breast">Breast</option>
<option value = "Breast cancer cells">Breast cancer cells</option>
<option value = "Breast milk">Breast milk</option>
<option value = "Breast tissue">Breast tissue</option>
<option value = "Breath">Breath</option>
<option value = "Bronchoalveolar lavage">Bronchoalveolar lavage</option>
<option value = "C. elegans">C. elegans</option>
<option value = "Cardiomyocyte cells">Cardiomyocyte cells</option>
<option value = "Cecum">Cecum</option>
<option value = "Cells">Cells</option>
<option value = "Cerebral Cortex">Cerebral Cortex</option>
<option value = "Cerebrospinal fluid">Cerebrospinal fluid</option>
<option value = "CHO cells">CHO cells</option>
<option value = "Colon">Colon</option>
<option value = "Conditioned treatment media">Conditioned treatment media</option>
<option value = "Coral nubbins">Coral nubbins</option>
<option value = "Cord Blood">Cord Blood</option>
<option value = "Cord blood serum">Cord blood serum</option>
<option value = "Corpus Luteum">Corpus Luteum</option>
<option value = "Cortex">Cortex</option>
<option value = "CSF">CSF</option>
<option value = "Cultured cells">Cultured cells</option>
<option value = "Cultured diatom cells">Cultured diatom cells</option>
<option value = "Cultured fibroblasts">Cultured fibroblasts</option>
<option value = "Cultured human myotubes with Toxoplasma gondii">Cultured human myotubes with Toxoplasma gondii</option>
<option value = "Cultured plankton cells">Cultured plankton cells</option>
<option value = "Cultured primary cells">Cultured primary cells</option>
<option value = "Cultured Prochlorococcus cells and vesicles">Cultured Prochlorococcus cells and vesicles</option>
<option value = "Cyst fluid">Cyst fluid</option>
<option value = "Date palm fruit">Date palm fruit</option>
<option value = "Dendritic cells">Dendritic cells</option>
<option value = "Diatom cells/Particulate matter from sea ice cores">Diatom cells/Particulate matter from sea ice cores</option>
<option value = "Diet">Diet</option>
<option value = "Dorsal root ganglia">Dorsal root ganglia</option>
<option value = "Dried long pepper">Dried long pepper</option>
<option value = "Duodenum">Duodenum</option>
<option value = "Eggs">Eggs</option>
<option value = "Embryonic cells">Embryonic cells</option>
<option value = "Endothelial Cells">Endothelial Cells</option>
<option value = "Epididymal adipose tissue">Epididymal adipose tissue</option>
<option value = "Epithelial cells">Epithelial cells</option>
<option value = "Esophagus">Esophagus</option>
<option value = "Exhaled Breath condensate">Exhaled Breath condensate</option>
<option value = "Exhaled breath condensate  and  bronchoalveolar lavage fluid">Exhaled breath condensate  and  bronchoalveolar lavage fluid</option>
<option value = "Eye tissue">Eye tissue</option>
<option value = "Face">Face</option>
<option value = "Feces">Feces</option>
<option value = "Feces; Blood (serum)">Feces; Blood (serum)</option>
<option value = "Femoral muscle">Femoral muscle</option>
<option value = "Fibroblast cells">Fibroblast cells</option>
<option value = "Fibrous root">Fibrous root</option>
<option value = "Fish larvae">Fish larvae</option>
<option value = "Flushing">Flushing</option>
<option value = "Fly">Fly</option>
<option value = "Follicular fluid">Follicular fluid</option>
<option value = "Foreskin">Foreskin</option>
<option value = "Fruit juice">Fruit juice</option>
<option value = "Fungal cells">Fungal cells</option>
<option value = "Fungal mycelium">Fungal mycelium</option>
<option value = "Gastrocnemius">Gastrocnemius</option>
<option value = "Glioblastoma cells">Glioblastoma cells</option>
<option value = "Glioma cells">Glioma cells</option>
<option value = "Head tissue">Head tissue</option>
<option value = "heart">heart</option>
<option value = "Heart">Heart</option>
<option value = "HEK cells">HEK cells</option>
<option value = "HeLa cells">HeLa cells</option>
<option value = "Hemolymph">Hemolymph</option>
<option value = "Hep G2 cells">Hep G2 cells</option>
<option value = "Hindbrain">Hindbrain</option>
<option value = "Hippocampus">Hippocampus</option>
<option value = "Human breast epithelial and adipose tissue">Human breast epithelial and adipose tissue</option>
<option value = "human cells">human cells</option>
<option value = "Human Milk">Human Milk</option>
<option value = "human monocyte-derived macrophages (hMDM)">human monocyte-derived macrophages (hMDM)</option>
<option value = "Hypothalamus">Hypothalamus</option>
<option value = "Ileum">Ileum</option>
<option value = "Insect brain">Insect brain</option>
<option value = "Insect tissue">Insect tissue</option>
<option value = "Intestinal lumen contents">Intestinal lumen contents</option>
<option value = "Intestine">Intestine</option>
<option value = "Jejunum">Jejunum</option>
<option value = "Jurkat cells, Clone E6-1">Jurkat cells, Clone E6-1</option>
<option value = "K562 Cells">K562 Cells</option>
<option value = "Keratinocytes">Keratinocytes</option>
<option value = "kidney">kidney</option>
<option value = "Kidney">Kidney</option>
<option value = "Kidney cortex">Kidney cortex</option>
<option value = "kidney epithelial cells">kidney epithelial cells</option>
<option value = "Larvae">Larvae</option>
<option value = "Leaf">Leaf</option>
<option value = "Leaves">Leaves</option>
<option value = "Leukemia cells">Leukemia cells</option>
<option value = "liver">liver</option>
<option value = "Liver">Liver</option>
<option value = "Liver;Spleen;Gut">Liver;Spleen;Gut</option>
<option value = "Liver_Brain_Kidney">Liver_Brain_Kidney</option>
<option value = "LnCap cells">LnCap cells</option>
<option value = "Lung">Lung</option>
<option value = "Lung organoids">Lung organoids</option>
<option value = "Lymphoma cells">Lymphoma cells</option>
<option value = "Macrophages">Macrophages</option>
<option value = "Maize kernel">Maize kernel</option>
<option value = "maize starchy endosperm">maize starchy endosperm</option>
<option value = "Media">Media</option>
<option value = "Mesenchymal stromal cells">Mesenchymal stromal cells</option>
<option value = "Mesenteric lymph">Mesenteric lymph</option>
<option value = "Milk">Milk</option>
<option value = "Mitochondria">Mitochondria</option>
<option value = "Monocytes">Monocytes</option>
<option value = "Mononuclear cells">Mononuclear cells</option>
<option value = "Mouse tissues and interstitial fluids">Mouse tissues and interstitial fluids</option>
<option value = "muscle">muscle</option>
<option value = "Muscle">Muscle</option>
<option value = "Muscle and Gonad tissue">Muscle and Gonad tissue</option>
<option value = "muscle tissue">muscle tissue</option>
<option value = "Mycelia in media">Mycelia in media</option>
<option value = "Nasal Polyp tissue">Nasal Polyp tissue</option>
<option value = "Neurons">Neurons</option>
<option value = "new">new</option>
<option value = "Nucleus accumbens">Nucleus accumbens</option>
<option value = "optic nerve">optic nerve</option>
<option value = "Optic nerve">Optic nerve</option>
<option value = "Other (digesta/intestinal contents)">Other (digesta/intestinal contents)</option>
<option value = "Outer medulla kidney">Outer medulla kidney</option>
<option value = "Ovarian cancer cells">Ovarian cancer cells</option>
<option value = "Ovary">Ovary</option>
<option value = "Pancreas">Pancreas</option>
<option value = "Pancreatic Islets">Pancreatic Islets</option>
<option value = "Parasite">Parasite</option>
<option value = "PDX GBM - mouse brain tumor section">PDX GBM - mouse brain tumor section</option>
<option value = "Peripheral blood mono-nuclear cells">Peripheral blood mono-nuclear cells</option>
<option value = "Peritoneal fluid">Peritoneal fluid</option>
<option value = "Pf infected RBCs">Pf infected RBCs</option>
<option value = "Photosynthetic organism">Photosynthetic organism</option>
<option value = "Phytoplankton">Phytoplankton</option>
<option value = "Placenta">Placenta</option>
<option value = "Plankton cells">Plankton cells</option>
<option value = "Plant">Plant</option>
<option value = "plant root tissue">plant root tissue</option>
<option value = "Plaque samples">Plaque samples</option>
<option value = "plasma">plasma</option>
<option value = "Plasma">Plasma</option>
<option value = "Plasmodium cells">Plasmodium cells</option>
<option value = "Platelets">Platelets</option>
<option value = "Prostate">Prostate</option>
<option value = "Quadriceps Muscle">Quadriceps Muscle</option>
<option value = "Rectum">Rectum</option>
<option value = "Red blood cells">Red blood cells</option>
<option value = "Renal Tissue">Renal Tissue</option>
<option value = "retina">retina</option>
<option value = "Retina">Retina</option>
<option value = "retinal ganglion cells">retinal ganglion cells</option>
<option value = "Rice grain">Rice grain</option>
<option value = "Root">Root</option>
<option value = "Saccharomyces cerevisiae">Saccharomyces cerevisiae</option>
<option value = "Saliva">Saliva</option>
<option value = "Sarcoma">Sarcoma</option>
<option value = "Scalp">Scalp</option>
<option value = "Sciatic nerve">Sciatic nerve</option>
<option value = "Sciatic Nerve">Sciatic Nerve</option>
<option value = "seawater">seawater</option>
<option value = "Seaweed">Seaweed</option>
<option value = "Seedlings">Seedlings</option>
<option value = "Seeds">Seeds</option>
<option value = "Serum">Serum</option>
<option value = "Sewage sludge">Sewage sludge</option>
<option value = "SI">SI</option>
<option value = "Skeletal muscle">Skeletal muscle</option>
<option value = "Skeletal Muscle">Skeletal Muscle</option>
<option value = "Skeletal Muscle (Gastrocnemius)">Skeletal Muscle (Gastrocnemius)</option>
<option value = "Skeletal myotubes">Skeletal myotubes</option>
<option value = "skin">skin</option>
<option value = "Skin">Skin</option>
<option value = "Skin Biopsy">Skin Biopsy</option>
<option value = "Small intestinal fecal contents">Small intestinal fecal contents</option>
<option value = "Small intestine">Small intestine</option>
<option value = "Small intestine tissue">Small intestine tissue</option>
<option value = "Sodium Heparin Plasma">Sodium Heparin Plasma</option>
<option value = "Spinal cord">Spinal cord</option>
<option value = "Spleen">Spleen</option>
<option value = "Sputum">Sputum</option>
<option value = "Stage 43 tadpoles">Stage 43 tadpoles</option>
<option value = "Standard phosphadylcholines">Standard phosphadylcholines</option>
<option value = "Stem cells">Stem cells</option>
<option value = "Stem cells culture media">Stem cells culture media</option>
<option value = "Stomach">Stomach</option>
<option value = "Subcutaneous tumor tissues of pancreatic cancer">Subcutaneous tumor tissues of pancreatic cancer</option>
<option value = "Suspended Marine Particulate Matter">Suspended Marine Particulate Matter</option>
<option value = "Sweat">Sweat</option>
<option value = "Synthetic Mixture">Synthetic Mixture</option>
<option value = "T-cells">T-cells</option>
<option value = "T-Cells">T-Cells</option>
<option value = "Tails">Tails</option>
<option value = "Testis">Testis</option>
<option value = "Thyroid">Thyroid</option>
<option value = "Tissue">Tissue</option>
<option value = "Tissue and skeleton">Tissue and skeleton</option>
<option value = "Tissue homogenate">Tissue homogenate</option>
<option value = "Tongue tissue">Tongue tissue</option>
<option value = "total bone marrow flush (cells)">total bone marrow flush (cells)</option>
<option value = "Toxoplasma gondii">Toxoplasma gondii</option>
<option value = "Tumor cells">Tumor cells</option>
<option value = "Ulcer biopsies">Ulcer biopsies</option>
<option value = "Umbilical cord plasma">Umbilical cord plasma</option>
<option value = "Unspecifed">Unspecifed</option>
<option value = "Unspecified">Unspecified</option>
<option value = "Urine">Urine</option>
<option value = "Uterine fluid">Uterine fluid</option>
<option value = "uterine flushing">uterine flushing</option>
<option value = "Uterus">Uterus</option>
<option value = "Vaginal epithelium">Vaginal epithelium</option>
<option value = "Vitreous">Vitreous</option>
<option value = "Wastewater">Wastewater</option>
<option value = "White adipose tissue">White adipose tissue</option>
<option value = "Whole Animal">Whole Animal</option>
<option value = "Whole animals">Whole animals</option>
<option value = "Whole bodies">Whole bodies</option>
<option value = "Whole insect">Whole insect</option>
<option value = "Whole Plant">Whole Plant</option>
<option value = "Whole Tissue">Whole Tissue</option>
<option value = "Whole worm extract">Whole worm extract</option>
<option value = "Wine">Wine</option>
<option value = "Worms">Worms</option>
<option value = "Yeast cells">Yeast cells</option>
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
<span class="btn" id="submit_form">Submit</span>

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
