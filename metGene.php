<?php
// ============================================================
// BOOTSTRAP — PHP must run first, before ANY html output
// ============================================================
require_once __DIR__ . '/metgene_common.php';

$METGENE_BASE_DIR_NAME = getBaseDirName();
$BASE = escapeHtml($METGENE_BASE_DIR_NAME);

// nav.php handles: session_start(), sendSecurityHeaders(),
// input validation, and sets these plain PHP variables:
//   $species, $geneList, $geneIDType, $disease, $anatomy, $phenotype
//   $anatomy_array, $disease_array, $phenotype_array
include(getNavIncludePath($METGENE_BASE_DIR_NAME, "nav.php"));

// ============================================================
// CHANGE DETECTION
// ============================================================
$prev_species  = safeSession('prev_species');
$prev_geneList = safeSession('prev_geneList');
$prev_anatomy  = safeSession('prev_anatomy');
$prev_disease  = safeSession('prev_disease');
$prev_pheno    = safeSession('prev_pheno');

if (strcmp($prev_species, $species) !== 0) {
    $_SESSION['prev_species']    = $species;
    $_SESSION['metgene_changed'] = 1;
} elseif (strcmp($prev_geneList, $geneList) !== 0) {
    $_SESSION['prev_geneList']   = $geneList;
    $_SESSION['metgene_changed'] = 1;
} elseif (strcmp($prev_disease, $disease) !== 0) {
    $_SESSION['prev_disease']    = $disease;
    $_SESSION['metgene_changed'] = 1;
} elseif (strcmp($prev_anatomy, $anatomy) !== 0) {
    $_SESSION['prev_anatomy']    = $anatomy;
    $_SESSION['metgene_changed'] = 1;
} elseif (strcmp($prev_pheno, $phenotype) !== 0) {
    $_SESSION['prev_pheno']      = $phenotype;
    $_SESSION['metgene_changed'] = 1;
} else {
    $_SESSION['metgene_changed'] = 0;
}

// ============================================================
// CACHE SETUP
// ============================================================
$metgene_changed = (int) safeSession('metgene_changed');

$url       = $_SERVER["SCRIPT_NAME"] ?? '';
$break     = explode('/', $url);
$file      = $break[count($break) - 1];
$cachefile = buildCacheFilePath(__DIR__ . '/cache', $file, session_id());
$_SESSION['metgene_cache_file'] = $cachefile;
$cachetime = 18000;

if ($metgene_changed === 0
    && file_exists($cachefile)
    && (time() - $cachetime) < filemtime($cachefile)
) {
    echo "<!-- Cached copy, generated " . date('H:i', filemtime($cachefile)) . " -->\n";
    readfile($cachefile);
    exit;
}

ob_start();
?>
<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head><title>MetGENE: Home</title>
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo $BASE; ?>/images/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32"  href="<?php echo $BASE; ?>/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16"  href="<?php echo $BASE; ?>/images/favicon-16x16.png">
<link rel="manifest" href="<?php echo $BASE; ?>/site.webmanifest">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style type='text/css' media='screen, projection, print'>
figcaption {
  padding: 5px;
  font-family: 'Cherry Swash', cursive;
  font-size: 0.7em;
  font-weight: 700;
  border: none;
  background: transparent;
  word-wrap: normal;
  text-align: center;
}
.container {
  position: relative;
  text-align: center;
  color: black;
}
.Disease {
  width: 80px;
  position: absolute;
  bottom: 40px;
  left: 16px;
  word-wrap: break-word;
  white-space: pre-line;
}
.Phenotype {
  width: 80px;
  position: absolute;
  bottom: 40px;
  left: 16px;
  word-wrap: break-word;
  white-space: pre-line;
}
.Organism {
  width: 80px;
  position: absolute;
  top: 30px;
  left: 16px;
  word-wrap: break-word;
  white-space: pre-line;
}
.Anatomy {
  width: 80px;
  position: absolute;
  top: 130px;
  left: 16px;
  color: black;
  word-wrap: break-word;
  white-space: pre-line;
}
.Pathways {
  position: absolute;
  top: 20px;
  right: 20px;
  color: black;
  font-size: 0.75em;
}
.Reactions {
  position: absolute;
  top: 100px;
  right: 18px;
  color: black;
  font-size: 0.75em;
}
.Metabolites {
  position: absolute;
  top: 182px;
  right: 14px;
  color: black;
  font-size: 0.75em;
}
.Studies {
  position: absolute;
  top: 268px;
  right: 25px;
  color: black;
  font-size: 0.75em;
}
.Gene {
  width: 80px;
  position: absolute;
  top: 44%;
  left: 54%;
  transform: translate(-50%, -50%);
  color: red;
}
</style>
</head>
<body>

<div id="constrain">
<div class="constrain">

<p style="font-family: Arial; font-size: 14px; text-align: justify; text-indent: 30px;">
<table>
<tr>
<td>
<div class="container">
  <img src="<?php echo $BASE; ?>/images/MetGeneSchematicNew.png" width="395" height="320" usemap="#schematic">
  <map name="schematic">
    <area shape="circle" coords="380,340,100"     alt="Genes Link"       href="<?php echo $BASE; ?>/geneInfo.php">
    <area shape="rect"   coords="665,60,840,180"  alt="Pathways Link"    href="<?php echo $BASE; ?>/pathways.php">
    <area shape="rect"   coords="665,240,840,360" alt="Reactions Link"   href="<?php echo $BASE; ?>/reactions.php">
    <area shape="rect"   coords="665,420,840,540" alt="Metabolites Link" href="<?php echo $BASE; ?>/metabolites.php">
    <area shape="rect"   coords="665,700,840,720" alt="Studies Link"     href="<?php echo $BASE; ?>/studies.php">
  </map>

<?php
// ============================================================
// MAIN RENDER — only when something changed
// ============================================================
if (isset($_SESSION['species'])
    && isset($_SESSION['geneArray'])
    && isset($_SESSION['metgene_changed'])
    && $_SESSION['metgene_changed'] == 1
) {
    // --------------------------------------------------------
    // Gene data
    // --------------------------------------------------------
    $geneSymbols      = safeSession('geneSymbols');
    $gene_ids_arr     = (isset($_SESSION['geneArray'])   && is_array($_SESSION['geneArray']))   ? $_SESSION['geneArray']   : [];
    $gene_list_arr    = (isset($_SESSION['geneListArr']) && is_array($_SESSION['geneListArr'])) ? $_SESSION['geneListArr'] : [];
    $gene_symbols_arr = explode(",", $geneSymbols);
    $geneIDType       = validateGeneIDType($geneIDType);

    // --------------------------------------------------------
    // Gene name string + invalid gene detection
    // --------------------------------------------------------
    $geneNameStr       = $gene_symbols_arr[0] ?? '';
    $has_invalid_genes = 0;
    $invalidGenesArr   = [];

    if (count($gene_ids_arr) > 1) {
        for ($x = 0; $x < count($gene_ids_arr); $x++) {
            if (strcmp($gene_ids_arr[$x], "NA") == 0 || strcmp($gene_symbols_arr[$x], "NA") == 0) {
                if (strcmp($gene_ids_arr[$x], "NA") == 0) {
                    $invalidGenesArr[] = (strcmp($gene_symbols_arr[$x], "NA") != 0)
                        ? $gene_symbols_arr[$x]
                        : ($gene_list_arr[$x] ?? '');
                } else {
                    $invalidGenesArr[] = $gene_ids_arr[$x];
                }
                $has_invalid_genes = 1;
            }
        }
        $geneNameStr = $gene_symbols_arr[0] . ", ...";
    } else {
        if (!empty($gene_ids_arr)
            && (strcmp($gene_ids_arr[0], "NA") == 0 || strcmp($gene_symbols_arr[0], "NA") == 0)
        ) {
            $geneNameStr = "NA";
            if (strcmp($gene_ids_arr[0], "NA") == 0) {
                $invalidGenesArr[] = (strcmp($gene_symbols_arr[0], "NA") != 0)
                    ? $gene_symbols_arr[0]
                    : ($gene_list_arr[0] ?? '');
            } else {
                $invalidGenesArr[] = $gene_ids_arr[0];
            }
            $has_invalid_genes = 1;
        }
    }

    // --------------------------------------------------------
    // Anatomy / disease / phenotype
    // nav.php already set $anatomy_array, $disease_array,
    // $phenotype_array as plain PHP variables via explode("__",...)
    // Just use them directly — no session lookup needed.
    // --------------------------------------------------------
    $anatomyDisplay   = escapeHtml($anatomy_array[0])   . (count($anatomy_array)   > 1 ? ", ..." : "");
    $diseaseDisplay   = escapeHtml($disease_array[0])   . (count($disease_array)   > 1 ? ", ..." : "");
    $phenotypeDisplay = escapeHtml($phenotype_array[0]) . (count($phenotype_array) > 1 ? ", ..." : "");

    // --------------------------------------------------------
    // Species
    // --------------------------------------------------------
    [$species_code, $organism_name, $org_sci_name] = normalizeSpecies($species);
    $_SESSION['org_name']     = $organism_name;
    $_SESSION['species_name'] = $org_sci_name;

    // --------------------------------------------------------
    // Build URLs
    // --------------------------------------------------------
    $urlParams = [
        'GeneInfoStr' => $geneList,
        'species'     => $species,
        'GeneIDType'  => $geneIDType,
        'disease'     => $disease,
        'anatomy'     => $anatomy,
        'phenotype'   => $phenotype,
    ];

    $geneUrl        = escapeHtml(buildInternalUrl($METGENE_BASE_DIR_NAME, 'geneInfo.php',    $urlParams));
    $pathwaysUrl    = escapeHtml(buildInternalUrl($METGENE_BASE_DIR_NAME, 'pathways.php',    $urlParams));
    $reactionsUrl   = escapeHtml(buildInternalUrl($METGENE_BASE_DIR_NAME, 'reactions.php',   $urlParams));
    $metabolitesUrl = escapeHtml(buildInternalUrl($METGENE_BASE_DIR_NAME, 'metabolites.php', $urlParams));
    $studiesUrl     = escapeHtml(buildInternalUrl($METGENE_BASE_DIR_NAME, 'studies.php',     $urlParams));
    $keggUrl        = escapeHtml("https://www.genome.jp/kegg-bin/show_organism?org=" . urlencode($species));

    // --------------------------------------------------------
    // Emit overlay labels
    // --------------------------------------------------------
    echo "<div class=\"Organism\"><a href=\"" . $keggUrl . "\" target=\"_blank\">" . escapeHtml($organism_name) . "</a></div>";
    echo "<div class=\"Anatomy\">"            . $anatomyDisplay   . "</div>";
    echo "<div class=\"Disease\">"            . $diseaseDisplay   . "</div>";
    // Phenotype display suppressed — overlaps with Disease position
    echo "<div class=\"Gene\"><a href=\""         . $geneUrl        . "\">" . escapeHtml($geneNameStr) . "</a></div>";
    echo "<div class=\"Pathways\"><a href=\""     . $pathwaysUrl    . "\">Pathways</a></div>";
    echo "<div class=\"Reactions\"><a href=\""    . $reactionsUrl   . "\">Reactions</a></div>";
    echo "<div class=\"Metabolites\"><a href=\""  . $metabolitesUrl . "\">Metabolites</a></div>";
    echo "<div class=\"Studies\"><a href=\""      . $studiesUrl     . "\">Studies</a></div>";

    $_SESSION['metgene_changed'] = 0;
}
?>

</div>
</td>

<td><p style="margin:25px;font-size:120%;">
<?php
// ============================================================
// INVALID GENE WARNINGS
// ============================================================
if (!empty($has_invalid_genes) && $has_invalid_genes == 1) {
    $arrlen              = count($invalidGenesArr);
    $escapedInvalidGenes = array_map('escapeHtml', $invalidGenesArr);

    if ($arrlen > 1) {
        echo "<p style=\"font-size:14px;\"><h3><b>" . implode(", ", $escapedInvalidGenes) . "</b>"
           . "<span style=\"color:#ff0000\"> are not valid gene IDs for the Gene ID type "
           . escapeHtml($geneIDType) . " for species " . escapeHtml($organism_name ?? '') . ".</span></h3></p>";
    } elseif ($arrlen == 1) {
        echo "<p style=\"font-size:14px;\"><h3><b>" . $escapedInvalidGenes[0] . "</b>"
           . "<span style=\"color:#ff0000\"> is not a valid gene ID for type "
           . escapeHtml($geneIDType) . " for species " . escapeHtml($organism_name ?? '') . ".</span></h3></p>";
    } else {
        echo "<br>";
    }
}

// ============================================================
// NON-METABOLIC GENE WARNINGS
// ============================================================
$safeSpecies           = preg_replace('/[^A-Za-z0-9_-]/', '', $species);
$metGeneSYMBOLFileName = __DIR__ . "/data/" . $safeSpecies . "_metSYMBOLs.txt";

if (is_readable($metGeneSYMBOLFileName)) {
    $metGeneSyms     = explode("\n", file_get_contents($metGeneSYMBOLFileName));
    $resultSyms      = array_diff($gene_symbols_arr ?? [], $metGeneSyms);
    $num_nonmetGenes = count($resultSyms);

    if (!empty($resultSyms)) {
        $nonmetGenes = escapeHtml(implode(",", $resultSyms));
        echo "<p style=\"font-size:14px; color:#538b01; font-weight:bold; font-style:italic;\">"
           . "<h3><b>Warning: Gene" . ($num_nonmetGenes > 1 ? "s " : " ") . $nonmetGenes
           . ($num_nonmetGenes > 1 ? " are not metabolic genes" : " is not a metabolic gene")
           . " and hence will not contain Reactions, Metabolites, Studies or Summary views.</b></h3></p>";
    }
}

// ============================================================
// DESCRIPTION PARAGRAPH
// ============================================================
if (!isset($urlParams)) {
    $urlParams = [
        'GeneInfoStr' => $geneList,
        'species'     => $species,
        'GeneIDType'  => validateGeneIDType($geneIDType),
        'disease'     => $disease,
        'anatomy'     => $anatomy,
        'phenotype'   => $phenotype,
    ];
    $geneNameStr   = escapeHtml(safeSession('geneSymbols'));
    $organism_name = safeSession('org_name');
}

$descGeneUrl        = escapeHtml(buildInternalUrl($METGENE_BASE_DIR_NAME, 'geneInfo.php',    $urlParams));
$descPathwaysUrl    = escapeHtml(buildInternalUrl($METGENE_BASE_DIR_NAME, 'pathways.php',    $urlParams));
$descReactionsUrl   = escapeHtml(buildInternalUrl($METGENE_BASE_DIR_NAME, 'reactions.php',   $urlParams));
$descMetabolitesUrl = escapeHtml(buildInternalUrl($METGENE_BASE_DIR_NAME, 'metabolites.php', $urlParams));
$descStudiesUrl     = escapeHtml(buildInternalUrl($METGENE_BASE_DIR_NAME, 'studies.php',     $urlParams));

echo "In the MetGENE tool, information about the gene(s) <b>" . escapeHtml($geneNameStr ?? '') . "</b> "
   . "is presented in <a href=\"" . $descGeneUrl . "\">Genes</a>, "
   . "the corresponding pathways in <a href=\"" . $descPathwaysUrl . "\">Pathways</a> "
   . "and the reactions in <a href=\"" . $descReactionsUrl . "\">Reactions</a> tabs. "
   . "The metabolites participating in the reactions are presented in "
   . "<a href=\"" . $descMetabolitesUrl . "\">Metabolites</a> tab. "
   . "For each metabolite, the studies containing the metabolite are identified from the "
   . "<a href=\"https://www.metabolomicsworkbench.org\" target=\"_blank\">Metabolomics Workbench</a> (MW) "
   . "and presented in <a href=\"" . $descStudiesUrl . "\">Studies</a> tab.";
?>
</p>
<p style="margin:25px;font-size:120%;">
    The data from MW studies are presented as table(s), with the metabolite names hyperlinked to MW
    <a href="https://www.metabolomicsworkbench.org/databases/refmet/index.php" target="_blank">RefMet</a>
    page (or to the corresponding <a href="https://www.genome.jp/kegg/" target="_blank">KEGG</a>
    entry in the absence of a RefMet name) for the metabolite, reaction hyperlinked to its KEGG entry
    and MW studies hyperlinked to their respective pages. The user also has access to the metabolite
    statistics via <a href="https://www.metabolomicsworkbench.org/data/metstat_form.php" target="_blank">MetStat</a>.
    Further, the user has the option to select more than one metabolite to list only those studies in
    which all the selected metabolites appear and can download the table as a text, HTML or JSON file.
</p></td>

</tr>
</table>
</p>

</div>
</div>
<?php include(getNavIncludePath($METGENE_BASE_DIR_NAME, "footer.php")); ?>
<?php
// ============================================================
// WRITE CACHE
// ============================================================
$cachefile = $_SESSION['metgene_cache_file'];
$cached = @fopen($cachefile, 'w');
if ($cached !== false) {
    fwrite($cached, ob_get_contents());
    fclose($cached);
}
ob_end_flush();
?>

</body>
</html>