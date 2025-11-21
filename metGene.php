<?php
/******************************************************************************
 * MetGENE – Hardened metGene.php
 * Uses metgene_common.php for all shared security, sanitization, species
 * normalization, path resolution, HTML escaping, etc.
 ******************************************************************************/

declare(strict_types=1);

require_once __DIR__ . "/metgene_common.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* --------------------------- SECURITY HEADERS ------------------------------ */
sendSecurityHeaders();

/* --------------------------- SAFE BASE DIR -------------------------------- */
$METGENE_BASE_DIR_NAME = getBaseDirName();

/* --------------------------- SAFE GET INPUTS ------------------------------- */
$species     = safeGet("species");
$geneList    = safeGet("GeneInfoStr");
$geneIDType  = safeGet("GeneIDType");
$disease     = safeGet("disease");
$anatomy     = safeGet("anatomy");
$phenotype   = safeGet("phenotype");

/* ---------------------------- SANITIZE GENE ID TYPE --------------------------------*/
$geneIDType = validateGeneIDType($geneIDType);
/* --------------------------- SANITIZE GENE LIST ---------------------------- */
$cleanGenes = cleanGeneList($geneList);
$geneList   = implode("__", $cleanGenes);

/* --------------------------- NORMALIZE SPECIES ----------------------------- */
list($species, $organism_name, $org_sci_name) = normalizeSpecies($species);

/* --------------------------- NAV INCLUDE ----------------------------------- */
$navFile = getNavIncludePath($METGENE_BASE_DIR_NAME, "nav.php");
if (is_readable($navFile)) {
    include $navFile;
}

/* --------------------------- SESSION SETUP -------------------------------- */
$_SESSION['species']   = $species;
$_SESSION['geneList']  = $geneList;
$_SESSION['anatomy']   = $anatomy;
$_SESSION['disease']   = $disease;
$_SESSION['phenotype'] = $phenotype;

/* Mark cache invalidation if filters changed */
$previousKeys = ['species','geneList','anatomy','disease','phenotype'];
$_SESSION['metgene_changed'] = 0;

foreach ($previousKeys as $k) {
    $prevKey = "prev_" . $k;
    if (!isset($_SESSION[$prevKey])) {
        $_SESSION[$prevKey] = "";
    }
    if ($_SESSION[$prevKey] !== $_SESSION[$k]) {
        $_SESSION[$prevKey] = $_SESSION[$k];
        $_SESSION['metgene_changed'] = 1;
    }
}

/* --------------------------- CACHING SYSTEM -------------------------------- */
$url      = $_SERVER["SCRIPT_NAME"];
$break    = explode('/', $url);
$file     = end($break);
$cachefile = 'cache/cached-' . session_id() . '-' . basename($file, ".php") . '.html';
$_SESSION['metgene_cache_file'] = $cachefile;
$cachetime = 18000;

if ($_SESSION['metgene_changed'] == 0 &&
    file_exists($cachefile) &&
    time() - $cachetime < filemtime($cachefile)) {

    echo "<!-- Cached copy, generated " . date('H:i', filemtime($cachefile)) . " -->\n";
    readfile($cachefile);
    exit;
}

ob_start(); // begin buffering (will be written to cache later)
?>
<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml'>
<head>
<title>MetGENE: Home</title>

<link rel="apple-touch-icon" sizes="180x180"
      href="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/images/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32"
      href="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/images/favicon-32x32.png">

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<style>
/* (your original CSS unchanged) */
figcaption {
  padding: 5px;
  font-family: 'Cherry Swash', cursive;
  font-size: 0.7em;
  font-weight: 700;
  background: transparent;
  text-align: center;
}
.container { position: relative; text-align:center; color:black; }
.Disease,.Phenotype,.Organism,.Anatomy,.Pathways,.Reactions,.Metabolites,.Studies,.Gene {
  word-wrap: break-word; white-space: pre-line;
}
.Disease { width:80px; bottom:40px; left:16px; position:absolute; }
.Phenotype { width:80px; bottom:40px; left:16px; position:absolute; }
.Organism { width:80px; top:30px; left:16px; position:absolute; }
.Anatomy { width:80px; top:130px; left:16px; position:absolute; color:black; }
.Pathways { position:absolute; top:20px; right:20px; color:black; font-size:0.75em; }
.Reactions { position:absolute; top:100px; right:18px; color:black; font-size:0.75em; }
.Metabolites { position:absolute; top:182px; right:14px; color:black; font-size:0.75em; }
.Studies { position:absolute; top:268px; right:25px; color:black; font-size:0.75em; }
.Gene { width:80px; position:absolute; top:44%; left:54%; transform:translate(-50%,-50%); color:red; }
</style>

</head>
<body>

<div id="constrain">
<div class="constrain">

<table>
<tr>
<td>
<div class="container">

<img src="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/images/MetGeneSchematicNew.png"
     width="395" height="320" usemap="#schematic">

<map name="schematic">
    <area shape="circle" coords="380,340,100" href="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/geneInfo.php">
    <area shape="rect" coords="665,60,840,180" href="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/pathways.php">
    <area shape="rect" coords="665,240,840,360" href="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/reactions.php">
    <area shape="rect" coords="665,420,840,540" href="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/metabolites.php">
    <area shape="rect" coords="665,700,840,720" href="<?php echo escapeHtml($METGENE_BASE_DIR_NAME); ?>/studies.php">
</map>

<?php
/******************************************************************************
 * Render the diagram labels: organism, anatomy, phenotype, disease, gene, etc.
 ******************************************************************************/

$anatomyStr       = escapeHtml($anatomy);
$diseaseStr       = escapeHtml($disease);
$phenotypeStr     = escapeHtml($phenotype);
$geneSymbolsStr   = escapeHtml($cleanGenes[0] ?? "NA");

echo '<div class="Organism"><a href="https://www.genome.jp/kegg-bin/show_organism?org=' .
        escapeHtml($species) . '" target="_blank">' . escapeHtml($organism_name) . '</a></div>';

echo '<div class="Anatomy">'. $anatomyStr .'</div>';
echo '<div class="Disease">'. $diseaseStr .'</div>';
echo '<div class="Phenotype">'. $phenotypeStr .'</div>';

$geneUrl = buildInternalUrl(
    $METGENE_BASE_DIR_NAME,
    "geneInfo.php",
    ["GeneInfoStr"=>$geneList, "species"=>$species, "GeneIDType"=>$geneIDType,
     "disease"=>$disease, "anatomy"=>$anatomy, "phenotype"=>$phenotype]
);

echo '<div class="Gene"><a href="' . escapeHtml($geneUrl) . '">' . $geneSymbolsStr . '</a></div>';

function mkLinkDiv($cls, $label, $file, $params, $base) {
    $url = buildInternalUrl($base, $file, $params);
    return '<div class="'.$cls.'"><a href="'.escapeHtml($url).'">'.$label.'</a></div>';
}

$params = ["GeneInfoStr"=>$geneList, "species"=>$species, "GeneIDType"=>$geneIDType,
           "disease"=>$disease, "anatomy"=>$anatomy, "phenotype"=>$phenotype];

echo mkLinkDiv("Pathways", "Pathways", "pathways.php", $params, $METGENE_BASE_DIR_NAME);
echo mkLinkDiv("Reactions", "Reactions", "reactions.php", $params, $METGENE_BASE_DIR_NAME);
echo mkLinkDiv("Metabolites", "Metabolites", "metabolites.php", $params, $METGENE_BASE_DIR_NAME);
echo mkLinkDiv("Studies", "Studies", "studies.php", $params, $METGENE_BASE_DIR_NAME);

$_SESSION['metgene_changed'] = 0;
?>
</div>
</td>

<td>
<p style="margin:25px;font-size:120%;">

<?php
/******************************************************************************
 * Invalid gene warning
 ******************************************************************************/

// geneArray, geneSymbols, geneListArr must be populated earlier in pipeline
$gene_ids_arr     = $_SESSION['geneArray']     ?? [];
$gene_symbols_arr = explode(",", ($_SESSION['geneSymbols'] ?? ""));
$gene_list_arr    = $_SESSION['geneListArr']   ?? [];

$invalidGenesArr = [];
$has_invalid_genes = false;

for ($i = 0; $i < count($gene_ids_arr); $i++) {
    $gid = $gene_ids_arr[$i]     ?? "NA";
    $gs  = $gene_symbols_arr[$i] ?? "NA";
    if ($gid === "NA" || $gs === "NA") {
        $has_invalid_genes = true;
        $invalidGenesArr[] = ($gs !== "NA") ? $gs : ($gene_list_arr[$i] ?? "NA");
    }
}

if ($has_invalid_genes) {
    $msg = implode(",", $invalidGenesArr);
    echo '<p style="color:#ff0000;font-weight:bold;"><b>Invalid gene(s): '.$msg.'</b></p>';
}

/******************************************************************************
 * Check for non-metabolic genes
 ******************************************************************************/

$metGeneSYMBOLFileName = __DIR__ . "/data/" . $species . "_metSYMBOLs.txt";
$metGeneSyms = file_exists($metGeneSYMBOLFileName)
    ? explode("\n", file_get_contents($metGeneSYMBOLFileName))
    : [];

$resultSyms = array_diff($gene_symbols_arr, $metGeneSyms);

if (!empty($resultSyms)) {
    $nonmet = escapeHtml(implode(",", $resultSyms));
    echo '<p style="font-size:14px;color:#538b01;font-weight:bold;">
            <b>Warning:</b> Gene(s) '.$nonmet.' are not metabolic and will not contain
            Reactions, Metabolites, Studies, or Summary views.
          </p>';
}

/******************************************************************************
 * Description text
 ******************************************************************************/

$descStr = "
In the MetGENE tool, information about the gene(s) ".escapeHtml($geneSymbolsStr)."
is presented in <a href=\"".buildInternalUrl($METGENE_BASE_DIR_NAME,"geneInfo.php",$params)."\">Genes</a>,
the corresponding pathways in <a href=\"".buildInternalUrl($METGENE_BASE_DIR_NAME,"pathways.php",$params)."\">Pathways</a>
and the reactions in <a href=\"".buildInternalUrl($METGENE_BASE_DIR_NAME,"reactions.php",$params)."\">Reactions</a>.
The metabolites participating in the reactions are presented in
<a href=\"".buildInternalUrl($METGENE_BASE_DIR_NAME,"metabolites.php",$params)."\">Metabolites</a>.
For each metabolite, studies containing it are retrieved from the
<a href=\"https://www.metabolomicsworkbench.org\" target=\"_blank\">Metabolomics Workbench</a>.
";

echo "<p>$descStr</p>";

?>
</p>
</td>
</tr>
</table>

</div>
</div>

<?php
/* FOOTER */
$footer = getNavIncludePath($METGENE_BASE_DIR_NAME, "footer.php");
if (is_readable($footer)) include $footer;

/* --------------------------- WRITE CACHE FILE ------------------------------ */
$cached = fopen($cachefile, 'w');
fwrite($cached, ob_get_contents());
fclose($cached);
ob_end_flush();
?>

</body>
</html>
