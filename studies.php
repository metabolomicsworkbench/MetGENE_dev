<?php
/******************************************************************************
 * MetGENE – Hardened studies.php (SECURE & CONSISTENT)
 *
 * BEHAVIOR:
 *  - If ?viewType=json|txt  → returns raw JSON / TXT from R (no HTML shell)
 *  - Else                  → full HTML page with studies per gene
 *
 * SECURITY:
 *  - Uses metgene_common.php helpers:
 *      • sendSecurityHeaders()
 *      • safeGet()
 *      • normalizeSpecies()
 *      • sanitizeGeneList()
 *      • buildRscriptCommand()
 *      • escapeHtml()
 *      • getBaseDir()
 *  - No direct unsanitized $_GET access
 *  - All exec() calls use buildRscriptCommand() → escapeshellarg()
 *  - Gene IDs sanitized (alphanumeric only)
 *  - All HTML safely escaped (except R-returned table HTML)
 *  - Safe includes for nav.php and footer.php
 *****************************************************************************/

declare(strict_types=1);

session_start();
require_once __DIR__ . '/metgene_common.php';

sendSecurityHeaders();

/* --------------------------------------------------------------------------
 * BRANCH ON viewType (API vs HTML)
 * -------------------------------------------------------------------------- */
$viewType = safeGet('viewType');

if ($viewType === 'json' || $viewType === 'txt') {
    /**************************************************************************
     * API BRANCH: return JSON or text (no HTML shell)
     **************************************************************************/

    $speciesRaw   = safeGet('species');
    $geneListRaw  = safeGet('GeneInfoStr');
    $geneIDType   = safeGet('GeneIDType');
    $diseaseRaw   = safeGet('disease');
    $anatomyRaw   = safeGet('anatomy');

    // Normalize species
    [$species, , ] = normalizeSpecies($speciesRaw);

    // Sanitize gene list using helper
    $cleanGenes   = sanitizeGeneList($geneListRaw);
    $geneInfoStr  = implode('__', $cleanGenes);

    // Whitelist gene ID type
    $allowedTypes = ["SYMBOL","SYMBOL_OR_ALIAS","ENTREZID","ENSEMBL","REFSEQ","UNIPROT"];
    if (!in_array($geneIDType, $allowedTypes, true)) {
        $geneIDType = "SYMBOL";
    }

    $domainName = $_SERVER['SERVER_NAME'] ?? 'localhost';

    // ---- Map gene IDs / symbols via R (secure) ----
    $cmdMap = buildRscriptCommand(
        'extractGeneIDsAndSymbols.R',
        [$species, $geneInfoStr, $geneIDType, $domainName]
    );

    $symbol_geneIDs = [];
    $retMap = 0;
    exec($cmdMap, $symbol_geneIDs, $retMap);

    $gene_array = [];
    $pat = '/^[A-Za-z0-9]+$/';

    if ($retMap === 0 && !empty($symbol_geneIDs)) {
        $gene_id_symbols_arr = [];
        foreach ($symbol_geneIDs as $val) {
            $gene_id_symbols_arr = array_merge(
                $gene_id_symbols_arr,
                explode(",", $val)
            );
        }

        $length = count($gene_id_symbols_arr);
        for ($i = 0; $i < $length; $i++) {
            $trimmed = trim($gene_id_symbols_arr[$i], "\" ");
            // second half of array = gene IDs
            if ($i >= $length / 2 && preg_match($pat, $trimmed)) {
                $gene_array[] = $trimmed;
            }
        }
    }

    $gene_vec_str = implode(',', $gene_array);

    $enc_disease = rawurlencode($diseaseRaw);
    $enc_anatomy = rawurlencode($anatomyRaw);

    $cmdStudies = buildRscriptCommand(
        'extractFilteredStudiesInfo.R',
        [$species, $gene_vec_str, $enc_disease, $enc_anatomy, $viewType]
    );

    $output = [];
    $retVar = 0;
    exec($cmdStudies, $output, $retVar);

    if ($viewType === 'json') {
        header('Content-Type: application/json; charset=UTF-8');
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo implode("\n", $output) . "\n";
    exit;
}

/******************************************************************************
 * HTML VIEW BRANCH
 ******************************************************************************/

$base_dir = getBaseDir(); // e.g. /MetGENE or "" depending on deployment

// Change tracking keys for cache invalidation
$_SESSION['prev_study_species']  = $_SESSION['prev_study_species']  ?? '';
$_SESSION['prev_study_geneList'] = $_SESSION['prev_study_geneList'] ?? '';
$_SESSION['prev_study_anatomy']  = $_SESSION['prev_study_anatomy']  ?? '';
$_SESSION['prev_study_disease']  = $_SESSION['prev_study_disease']  ?? '';
$_SESSION['prev_study_pheno']    = $_SESSION['prev_study_pheno']    ?? '';

$curr_species  = $_SESSION['species']   ?? '';
$curr_geneList = $_SESSION['geneList']  ?? '';
$curr_anatomy  = $_SESSION['anatomy']   ?? '';
$curr_disease  = $_SESSION['disease']   ?? '';
$curr_pheno    = $_SESSION['phenotype'] ?? '';

if (strcmp($_SESSION['prev_study_species'], $curr_species) !== 0) {
    $_SESSION['prev_study_species'] = $curr_species;
    $_SESSION['study_changed'] = 1;
} elseif (strcmp($_SESSION['prev_study_geneList'], $curr_geneList) !== 0) {
    $_SESSION['prev_study_geneList'] = $curr_geneList;
    $_SESSION['study_changed'] = 1;
} elseif (strcmp($_SESSION['prev_study_disease'], $curr_disease) !== 0) {
    $_SESSION['prev_study_disease'] = $curr_disease;
    $_SESSION['study_changed'] = 1;
} elseif (strcmp($_SESSION['prev_study_anatomy'], $curr_anatomy) !== 0) {
    $_SESSION['prev_study_anatomy'] = $curr_anatomy;
    $_SESSION['study_changed'] = 1;
} elseif (strcmp($_SESSION['prev_study_pheno'], $curr_pheno) !== 0) {
    $_SESSION['prev_study_pheno'] = $curr_pheno;
    $_SESSION['study_changed'] = 1;
} else {
    $_SESSION['study_changed'] = $_SESSION['study_changed'] ?? 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MetGENE: Studies</title>

<link rel="apple-touch-icon" sizes="180x180" href="<?= escapeHtml($base_dir) ?>/images/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= escapeHtml($base_dir) ?>/images/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= escapeHtml($base_dir) ?>/images/favicon-16x16.png">
<link rel="manifest" href="<?= escapeHtml($base_dir) ?>/site.webmanifest">

<style>
  table th, td {
    word-wrap: break-word;
    white-space: pre-line;
    border-bottom: 1px solid #dddddd;
  }
  table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
    width: 100%;
  }
</style>

<?php
// Safe include of nav
$nav_file = $_SERVER['DOCUMENT_ROOT'] . $base_dir . '/nav.php';
if (is_readable($nav_file)) {
    include $nav_file;
}
?>
</head>

<body>
<div id="constrain"><div class="constrain">
<br>

<?php
/******************************************************************************
 * CACHE HANDLING
 *****************************************************************************/
$url   = $_SERVER["SCRIPT_NAME"] ?? 'studies.php';
$file  = basename($url, '.php');
$cachefile = 'cache/cached-' . session_id() . '-' . $file . '.html';
$_SESSION['study_cache_file'] = $cachefile;
$cachetime = 18000; // 5 hours

if (
    (int)($_SESSION['study_changed'] ?? 0) === 0 &&
    file_exists($cachefile) &&
    (time() - filemtime($cachefile)) < $cachetime
) {
    echo "<!-- Cached copy, generated " .
         escapeHtml(date('H:i', filemtime($cachefile))) .
         " -->\n";
    readfile($cachefile);
} else {

    ob_start(); // Start buffer for cacheable portion

    $gene_symbols = $_SESSION['geneSymbols'] ?? '';
    $gene_array   = $_SESSION['geneArray']   ?? [];
    $disease      = $_SESSION['disease']     ?? 'NA';
    $anatomy      = $_SESSION['anatomy']     ?? 'NA';
    $phenotype    = $_SESSION['phenotype']   ?? 'NA';
    $species      = $_SESSION['species']     ?? '';
    $organism_name = $_SESSION['org_name']   ?? '';

    $symbol_parts = $gene_symbols !== '' ? explode(',', $gene_symbols) : [];
    $clean_gene_ids  = [];
    $clean_gene_syms = [];
    $pat = '/^[A-Za-z0-9]+$/';

    for ($i = 0; $i < count($gene_array); $i++) {
        $gid = $gene_array[$i] ?? '';
        $sym = $symbol_parts[$i] ?? '';

        if ($gid === '' || $gid === 'NA') {
            continue;
        }
        if (preg_match($pat, $gid)) {
            $clean_gene_ids[]  = $gid;
            $clean_gene_syms[] = $sym;
        }
    }

    $gene_vec_str = implode(',', $clean_gene_ids);
    $gene_sym_str = implode(',', $clean_gene_syms);

    if ($gene_vec_str !== '') {
        // Heading text with anatomy/disease context
        if ($anatomy === 'NA' && $disease === 'NA') {
            $h3_str = "Metabolomic studies information for ";
        } elseif ($anatomy === 'NA') {
            $h3_str = "Metabolomic studies information for ";
        } elseif ($disease === 'NA') {
            $h3_str = "Metabolomic studies information for ";
        } else {
            $h3_str = "Metabolomic studies information for ";
        }

        // Build descriptive heading (mirroring original logic, but escaped)
        if ($anatomy === 'NA' && $disease === 'NA') {
            $heading = "<h3>" . $h3_str .
                       "<i><b>" . escapeHtml($organism_name) .
                       "</b></i> gene(s) <i><b>" .
                       escapeHtml($gene_sym_str) .
                       "</b></i></h3>";
        } elseif ($anatomy === 'NA') {
            $heading = "<h3>" . $h3_str .
                       "<i><b>" . escapeHtml($organism_name) .
                       "</b></i> gene(s) <i><b>" .
                       escapeHtml($gene_sym_str) .
                       "</b></i> disease <i><b>" .
                       escapeHtml($disease) .
                       "</b></i></h3>";
        } elseif ($disease === 'NA') {
            $heading = "<h3>" . $h3_str .
                       "<i><b>" . escapeHtml($organism_name) .
                       "</b></i> gene(s) <i><b>" .
                       escapeHtml($gene_sym_str) .
                       "</b></i> anatomy <i><b>" .
                       escapeHtml($anatomy) .
                       "</b></i></h3>";
        } else {
            $heading = "<h3>" . $h3_str .
                       "<i><b>" . escapeHtml($organism_name) .
                       "</b></i> gene(s) <i><b>" .
                       escapeHtml($gene_sym_str) .
                       "</b></i> anatomy <i><b>" .
                       escapeHtml($anatomy) .
                       "</b></i> disease <i><b>" .
                       escapeHtml($disease) .
                       "</b></i></h3>";
        }

        echo $heading;

        $enc_disease = rawurlencode($disease);
        $enc_anatomy = rawurlencode($anatomy);
        $viewTypeHtml = 'html';

        $cmdStudies = buildRscriptCommand(
            'extractFilteredStudiesInfo.R',
            [$species, $gene_vec_str, $enc_disease, $enc_anatomy, $viewTypeHtml]
        );

        $output = [];
        $retvar = 0;
        exec($cmdStudies, $output, $retvar);
        $htmlbuff = implode("", $output);

        if ($htmlbuff !== '') {
            echo '<p>Use check boxes to select metabolites to combine their studies.</p>';
            echo "<pre>";
            // R returns HTML tables here; we intentionally do NOT escape
            echo $htmlbuff;
            echo "</pre>";

            echo '<input type="button" value="Combine Studies" onclick="GetSelected()" /><br>';
            echo '<p><button id="json">TO JSON</button> <button id="csv">TO CSV</button></p>';
        } else {
            echo "<h3><i>No studies were found for <b>" .
                 escapeHtml($organism_name) .
                 "</b> gene(s) <b>" .
                 escapeHtml($gene_symbols) .
                 "</b></i></h3>";
        }
    } else {
        echo "<h3><i>No studies were found for <b>" .
             escapeHtml($organism_name) .
             "</b> gene(s) <b>" .
             escapeHtml($gene_symbols) .
             "</b></i></h3>";
    }

    $_SESSION['study_changed'] = 0;

    echo '<span id="display"></span>';

    // Write cache
    if (!is_dir(dirname($cachefile))) {
        @mkdir(dirname($cachefile), 0755, true);
    }
    $cached = @fopen($cachefile, 'w');
    if ($cached) {
        fwrite($cached, ob_get_contents());
        fclose($cached);
    }
    ob_end_flush();
}
?>

</div></div>

<br><br>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?= escapeHtml($base_dir) ?>/src/tableHTMLExport.js"></script>

<script>
  $('#json').on('click', function(){
      $("#Table1").tableHTMLExport({
          type: 'json',
          filename: 'Studies.json',
          ignoreColumns: "SELECT"
      });
  });

  $('#csv').on('click', function(){
      $("#Table1").tableHTMLExport({
          type: 'csv',
          filename: 'Studies.csv'
      });
  });

  function GetSelected() {
      var grid = document.getElementById("Table1");
      var checkBoxes = grid.getElementsByTagName("input");
      $("#display").html("Processing....");
      var map1 = new Map();

      for (var i = 0; i < checkBoxes.length; i++) {
          if (checkBoxes[i].checked) {
              var row = checkBoxes[i].parentNode.parentNode;
              var compId = row.cells[2].innerText;
              var newId = compId.replaceAll(":", "___");
              var studiesStr = row.cells[3].innerText;
              map1.set(newId, studiesStr);
          }
      }

      var obj = Object.fromEntries(map1);
      var objStr = encodeURIComponent(JSON.stringify(obj));

      // Safely inject session values into JS
      var species   = <?= json_encode($curr_species) ?>;
      var geneList  = <?= json_encode($curr_geneList) ?>;
      var geneIDType = <?= json_encode($curr_species ? ($_SESSION['geneIDType'] ?? '') : '') ?>;
      var disease   = <?= json_encode($curr_disease) ?>;
      var anatomy   = <?= json_encode($curr_anatomy) ?>;
      var phenotype = <?= json_encode($curr_pheno) ?>;
      var baseDir   = <?= json_encode($base_dir) ?>;

      $.ajax({
          url: baseDir + "/combineStudies.php",
          type: "get",
          data: { metabolites: objStr },
          success: function() {
              var url = baseDir + "/combineStudies.php" +
                        "?metabolites=" + objStr +
                        "&GeneInfoStr=" + encodeURIComponent(geneList) +
                        "&GeneIDType=" + encodeURIComponent(geneIDType) +
                        "&species=" + encodeURIComponent(species) +
                        "&disease=" + encodeURIComponent(disease) +
                        "&anatomy=" + encodeURIComponent(anatomy) +
                        "&phenotype=" + encodeURIComponent(phenotype);

              window.location.href = url;
          }
      });
  }
</script>

<?php
// Footer include
$footer_file = $_SERVER['DOCUMENT_ROOT'] . $base_dir . "/footer.php";
if (is_readable($footer_file)) {
    include $footer_file;
}
?>

</body>
</html>
