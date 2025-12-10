<?php
/**
 * studies.php - Display metabolomic studies information
 * Security hardened using metgene_common.php functions
 */

// SECURITY FIX: Start session and load helpers BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . "/metgene_common.php");

// Define esc() wrapper
if (!function_exists('esc')) {
    function esc(string $v): string {
        return escapeHtml($v);
    }
}

$viewType = safeGet("viewType");
?>
<?php if ( strcmp($viewType, "json") == 0 || strcmp($viewType, "txt") == 0):
 // SECURITY FIX: Send headers at top of API branch
 sendSecurityHeaders();
 
 $species = safeGet("species");
 $geneList = safeGet("GeneInfoStr");
 $geneIDType = safeGet("GeneIDType");
 $disease = safeGet("disease");
 $enc_disease = urlencode($disease);
 $anatomy = safeGet("anatomy");
 $enc_anatomy = urlencode($anatomy);
 $domainName = $_SERVER['SERVER_NAME'] ?? 'localhost';
 
 // SECURITY FIX: Use buildRscriptCommand for first R script
 $scriptPath1 = realpath(__DIR__ . "/extractGeneIDsAndSymbols.R");
 if ($scriptPath1 === false || !is_readable($scriptPath1)) {
     error_log("SECURITY: extractGeneIDsAndSymbols.R not found");
     if ($viewType === 'json') {
         header('Content-Type: application/json; charset=UTF-8');
         echo json_encode(['error' => 'Script not available']);
     } else {
         header('Content-Type: text/plain; charset=UTF-8');
         echo "Error: Script not available\n";
     }
     exit;
 }
 
 $cmd1 = "/usr/bin/Rscript " . escapeshellarg($scriptPath1) . " "
       . escapeshellarg($species) . " "
       . escapeshellarg($geneList) . " "
       . escapeshellarg($geneIDType) . " "
       . escapeshellarg($domainName);
 
 exec($cmd1, $symbol_geneIDs, $retvar);
 
 // SECURITY FIX: Check return code
 if ($retvar !== 0) {
     error_log("R script extractGeneIDsAndSymbols.R failed with exit code: $retvar");
     if ($viewType === 'json') {
         header('Content-Type: application/json; charset=UTF-8');
         echo json_encode(['error' => 'Gene ID extraction failed']);
     } else {
         header('Content-Type: text/plain; charset=UTF-8');
         echo "Error: Gene ID extraction failed\n";
     }
     exit;
 }
 
 $gene_symbols = array();
 $gene_array = array();
 $gene_id_symbols_arr = array();

 foreach ($symbol_geneIDs as $val) {
     $gene_id_symbols_arr = explode(",", $val);
 }

 $length = count($gene_id_symbols_arr);

 for ($i=0; $i < $length; $i++) {
   $my_str = $gene_id_symbols_arr[$i];
   $trimmed_str = trim($my_str, "\" ");

   if ($i < $length/2) {
     array_push($gene_symbols, $trimmed_str);
   } else {
     array_push($gene_array, $trimmed_str);
   }
 }

 $gene_vec_str = implode(",", $gene_array);
 
 // SECURITY FIX: Validate second R script path
 $scriptPath2 = realpath(__DIR__ . "/extractFilteredStudiesInfo.R");
 if ($scriptPath2 === false || !is_readable($scriptPath2)) {
     error_log("SECURITY: extractFilteredStudiesInfo.R not found");
     if ($viewType === 'json') {
         header('Content-Type: application/json; charset=UTF-8');
         echo json_encode(['error' => 'Studies script not available']);
     } else {
         header('Content-Type: text/plain; charset=UTF-8');
         echo "Error: Studies script not available\n";
     }
     exit;
 }
 
 $cmd2 = "/usr/bin/Rscript " . escapeshellarg($scriptPath2) . " "
       . escapeshellarg($species) . " "
       . escapeshellarg($gene_vec_str) . " "
       . escapeshellarg($enc_disease) . " "
       . escapeshellarg($enc_anatomy) . " "
       . escapeshellarg($viewType);
 
 exec($cmd2, $output, $retVar);
 
 // SECURITY FIX: Check return code
 if ($retVar !== 0) {
     error_log("R script extractFilteredStudiesInfo.R failed with exit code: $retVar");
     if ($viewType === 'json') {
         header('Content-Type: application/json; charset=UTF-8');
         echo json_encode(['error' => 'Studies extraction failed']);
     } else {
         header('Content-Type: text/plain; charset=UTF-8');
         echo "Error: Studies extraction failed\n";
     }
     exit;
 }
 
 $htmlbuff = implode("\n", $output);
 if (strcmp($viewType, "json") == 0){
   header('Content-type: application/json; charset=UTF-8');
 } else {
   header('Content-Type: text/plain; charset=UTF-8');
 }
 echo $htmlbuff;

?>
<?php else: ?>
<?php
  // SECURITY FIX: Send security headers at top of HTML branch
  sendSecurityHeaders();
  
  // SECURITY FIX: Use getBaseDirName() function
  $METGENE_BASE_DIR_NAME = getBaseDirName();
?>

<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>

<head><title>MetGENE: Studies</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".esc($METGENE_BASE_DIR_NAME)."/images/apple-touch-icon.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"".esc($METGENE_BASE_DIR_NAME)."/images/favicon-32x32.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"".esc($METGENE_BASE_DIR_NAME)."/images/favicon-16x16.png\">";
    echo "<link rel=\"manifest\" href=\"".esc($METGENE_BASE_DIR_NAME)."/site.webmanifest\">";
?>

<?php
// SECURITY FIX: Validate nav.php path with realpath
$nav_file = realpath(__DIR__ . '/nav.php');
if ($nav_file !== false && strpos($nav_file, __DIR__) === 0 && is_readable($nav_file)) {
    include $nav_file;
}
?>

<?php
  $_SESSION['prev_study_species'] = $_SESSION['prev_study_species'] ?? '';
  $_SESSION['prev_study_geneList'] = $_SESSION['prev_study_geneList'] ?? '';
  $_SESSION['prev_study_anatomy'] = $_SESSION['prev_study_anatomy'] ?? '';
  $_SESSION['prev_study_disease'] = $_SESSION['prev_study_disease'] ?? '';
  $_SESSION['prev_study_pheno'] = $_SESSION['prev_study_pheno'] ?? '';

  if (strcmp($_SESSION['prev_study_species'],$_SESSION['species'] ?? '') != 0) {
    $_SESSION['prev_study_species'] = $_SESSION['species'] ?? '';
    $_SESSION['study_changed'] = 1;
  } else if (strcmp($_SESSION['prev_study_geneList'], $_SESSION['geneList'] ?? '') != 0) {
    $_SESSION['prev_study_geneList'] = $_SESSION['geneList'] ?? '';
    $_SESSION['study_changed'] = 1;
  } else if (strcmp($_SESSION['prev_study_disease'], $_SESSION['disease'] ?? '') != 0) {
    $_SESSION['prev_study_disease'] = $_SESSION['disease'] ?? '';
    $_SESSION['study_changed'] = 1;
  }  else if (strcmp($_SESSION['prev_study_anatomy'], $_SESSION['anatomy'] ?? '') != 0) {
    $_SESSION['prev_study_anatomy'] = $_SESSION['anatomy'] ?? '';
    $_SESSION['study_changed'] = 1;
  } else if (strcmp($_SESSION['prev_study_pheno'], $_SESSION['phenotype'] ?? '') != 0) {
    $_SESSION['prev_study_pheno'] = $_SESSION['phenotype'] ?? '';
    $_SESSION['study_changed'] = 1;
  }  else {
   $_SESSION['study_changed'] = 0;
  }
?>
</head>
<style>
  table th, td {word-wrap:break-word;white-space: normal;border-bottom: 1px solid #dddddd;}
  table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
    width: 100%;
  }
</style>
<body
    data-species="<?php echo esc($_SESSION['species'] ?? ''); ?>"
    data-genelist="<?php echo esc($_SESSION['geneList'] ?? ''); ?>"
    data-geneidtype="<?php echo esc($_SESSION['geneIDType'] ?? ''); ?>"
    data-disease="<?php echo esc($_SESSION['disease'] ?? ''); ?>"
    data-anatomy="<?php echo esc($_SESSION['anatomy'] ?? ''); ?>"
    data-phenotype="<?php echo esc($_SESSION['phenotype'] ?? ''); ?>"
    data-basedir="<?php echo esc($METGENE_BASE_DIR_NAME); ?>"
>
<p>

<br>

<div id="constrain">
<div class="constrain">
<br>
<?php
// top-cache.php
$myurl = $_SERVER["SCRIPT_NAME"];
$break = explode('/', $myurl);
$file = $break[count($break) - 1];

// SECURITY FIX: Sanitize session ID and use absolute path
$safeSession = preg_replace('/[^A-Za-z0-9]/', '', session_id());
$cachefile = __DIR__ . '/cache/cached-' . $safeSession . '-' . basename($file, '.php') . '.html';
$_SESSION['study_cache_file'] = $cachefile;
$cachetime = 18000;

// Serve from the cache if it is younger than $cachetime
if ( isset($_SESSION['study_changed']) && ($_SESSION['study_changed'] == False) && file_exists($_SESSION['study_cache_file']) && time() - $cachetime < filemtime($_SESSION['study_cache_file'])) {
    echo "<!-- Cached copy, generated ".date('H:i', filemtime($cachefile))." -->\n";
    readfile($cachefile);
    exit;
}
ob_start(); // Start the output buffer

$gene_symbols = $_SESSION['geneSymbols'] ?? '';
$gene_array = $_SESSION['geneArray'] ?? [];

// SECURITY FIX: Ensure geneArray is actually an array
if (!is_array($gene_array)) {
    $gene_array = [];
}

$disease = $_SESSION['disease'] ?? '';
$anatomy = $_SESSION['anatomy'] ?? '';
$phenotype = $_SESSION['phenotype'] ?? '';
$species = $_SESSION['species'] ?? '';
$organism_name = $_SESSION['org_name'] ?? '';

if(isset($_SESSION['species']) && isset($_SESSION['geneArray'])  &&  isset($_SESSION['study_changed']) && $_SESSION['study_changed'] == 1) {
  $output = array();
  $htmlbuff = array();
  $gene_vec_arr = array();
  $gene_sym_arr = array();
  $gene_sym_str_arr = explode(",", $gene_symbols);

  // Filter out the bad geneIDs by removing NA
  for ($i=0; $i<count($gene_array); $i++) {
    if ($gene_array[$i] != "NA") {
       $gene_vec_arr[$i] = $gene_array[$i];
       // SECURITY FIX: Add bounds check
       $gene_sym_arr[$i] = isset($gene_sym_str_arr[$i]) ? $gene_sym_str_arr[$i] : '';
    }
  }
  $gene_vec_str = implode(",", $gene_vec_arr);
  $gene_sym_str = implode(",", $gene_sym_arr);

  if (!empty($gene_vec_str)) {
    if (strcmp($anatomy,"NA") == 0 && strcmp($disease,"NA") == 0) {
      $h3_str = "<h3>Metabolomic Studies Information for <i><b>".esc($organism_name)."</b></i> gene(s) <i><b>".esc($gene_sym_str)."</b></i></h3>";
    } else if (strcmp($anatomy,"NA") == 0) {
      $h3_str = "<h3>Metabolomic Studies Information for <i><b>".esc($organism_name)."</b></i> gene(s) <i><b>".esc($gene_sym_str)."</b></i> disease <i><b>".esc($disease)."</b></i></h3>";
    } else if (strcmp($disease,"NA") == 0) {
      $h3_str = "<h3>Metabolomic Studies Information for <i><b>".esc($organism_name)."</b></i> gene(s) <i><b>".esc($gene_sym_str)."</b></i> anatomy <i><b>".esc($anatomy)."</b></i></h3>";
    } else {
      $h3_str = "<h3>Metabolomic Studies Information for <i><b>".esc($organism_name)."</b></i> gene(s) <i><b>".esc($gene_sym_str)."</b></i> anatomy <i><b>".esc($anatomy)."</b></i> disease <i><b>".esc($disease)."</b></i></h3>";
    }

    echo $h3_str;

    $enc_disease = urlencode($disease);
    $enc_anatomy = urlencode($anatomy);
    $viewType = "html";
    
    // SECURITY FIX: Validate R script path
    $scriptPath = realpath(__DIR__ . "/extractFilteredStudiesInfo.R");
    
    if ($scriptPath === false || !is_readable($scriptPath)) {
        error_log("SECURITY: extractFilteredStudiesInfo.R not found or not readable");
        echo "<h3><i>Error: Studies script not available.</i></h3>";
    } else {
        $cmd = "/usr/bin/Rscript " . escapeshellarg($scriptPath) . " "
             . escapeshellarg($species) . " "
             . escapeshellarg($gene_vec_str) . " "
             . escapeshellarg($enc_disease) . " "
             . escapeshellarg($enc_anatomy) . " "
             . escapeshellarg($viewType);
        
        exec($cmd, $output, $retvar);
        
        // SECURITY FIX: Check return code
        if ($retvar !== 0) {
            error_log("R script extractFilteredStudiesInfo.R failed with exit code: $retvar");
            echo "<h3><i>Error retrieving studies information.</i></h3>";
        } else {
            $htmlbuff = implode($output);

            if (!empty($htmlbuff)) {
              $p_str = "<p> Use check boxes to select metabolites to combine their studies. </p>";
              echo $p_str;
              echo "<pre>";
              echo $htmlbuff;
              echo "</pre>";
              $inputStr = "<input type=\"button\" id=\"combineStudiesBtn\" value=\"Combine Studies\" />";
              echo $inputStr;
              echo "<br>";
              $btnStr = "<p><button id=\"json\">TO JSON</button> <button id=\"csv\">TO CSV</button> </p>";
              echo $btnStr;
            }
        }
    }
  } else {
    echo "<h3><i>No studies were found for <b>".esc($organism_name)."</b> gene <b>".esc($gene_symbols)."</b></i></h3>";
  }
  $_SESSION['study_changed'] = 0;
}

?>
<span id="display"></span>
</div>
</div>
</p>

<br>
<br>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php echo "<script src=\"".esc($METGENE_BASE_DIR_NAME)."/src/tableHTMLExport.js\"></script>"; ?>
<?php echo "<script src=\"".esc($METGENE_BASE_DIR_NAME)."/js/studies-original.js\"></script>"; ?>

<?php
// SECURITY FIX: Validate footer.php path with realpath
$footer_file = realpath(__DIR__ . '/footer.php');
if ($footer_file !== false && strpos($footer_file, __DIR__) === 0 && is_readable($footer_file)) {
    include $footer_file;
}
?>

<?php
// bottom-cache.php
// SECURITY FIX: Add error handling for cache write
$cachefile = $_SESSION['study_cache_file'];
$cacheDir = dirname($cachefile);
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

$cached = @fopen($cachefile, 'w');
if ($cached !== false) {
    fwrite($cached, ob_get_contents());
    fclose($cached);
    @chmod($cachefile, 0640); // Restrict permissions
} else {
    error_log("Failed to write cache file: $cachefile");
}
ob_end_flush(); // Send the output to the browser
?>

</body>
</html>
<?php endif; ?>