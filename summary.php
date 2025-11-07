<?php
 $viewType = $_GET["viewType"];
?>
<?php if ( strcmp($viewType, "json") == 0 || strcmp($viewType, "txt") == 0): 
 $species = escapeshellarg($species);
 $gene_array_str = escapeshellarg($gene_array_str);
 $gene_sym_str = escapeshellarg($gene_sym_str);
 $filename = escapeshellarg($filename);
 $viewType = escapeshellarg($viewType);
  $anatomy = urlencode($_GET["anatomy"]);
  $disease = urlencode($_GET["disease"]);


exec("/usr/bin/Rscript extractMWGeneSummary.R $species $gene_array_str $gene_sym_str $filename $viewType $anatomy $disease", $output, $retVar);


  $domainName = $_SERVER['SERVER_NAME'];
 exec("/usr/bin/Rscript extractGeneIDsAndSymbols.R $species $geneList $geneIDType $domainName", $symbol_geneIDs, $retvar);
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

 $prefix = "cache/plot";
 $suffix = ".png";
 $filename = $prefix.rand(1,1000).$suffix;
 $gene_array_str = implode("__", $gene_array);
 
 $gene_sym_str = implode("__", $gene_symbols);
 $anatomy = urlencode($_GET["anatomy"]);
 $disease = urlencode($_GET["disease"]);
 exec("/usr/bin/Rscript extractMWGeneSummary.R $species $gene_array_str $gene_sym_str  $filename  $viewType $anatomy $disease", $output, $retVar);
 $htmlbuff = implode("\n", $output);
 if (strcmp($viewType, "json") == 0){ 
   header('Content-type: application/json; charset=UTF-8'); 
 } else {
   header('Content-Type: text/plain; charset=UTF-8');
 }
 echo $htmlbuff;
//echo "<p>Disease: <strong>$disease</strong></p>";
//echo "<p>Anatomy: <strong>$anatomy</strong></p>";

?>
<?php else: ?>

<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head><title>MetGENE: Summarys</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<?php
  $curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));
  $METGENE_BASE_DIR_NAME = $curDirPath;

    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-32x32.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-16x16.png\">";
    echo "<link rel=\"manifest\" href=\"".$METGENE_BASE_DIR_NAME."/site.webmanifest\">";

?>


<style>
.styled-table {
    display:table;
    table-layout:fixed;
    width: 100%;
    word-wrap: break-word;

}

.styled-table td {
    border: 1px solid #000;
    padding:5px 10px;
    text-align: center;
    width: 3%;
    word-break: break-all;
    white-space: pre-line;
}
.styled-table tbody tr {
    border-bottom: 1px solid #dddddd;
    text-align: center;         
}

.styled-table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
    text-align: center;
}
.summary {
    background-color: white;
    width: 75;
  color: black;
  border: 2px solid black;
  margin: 20px;
  padding: 20px;
}
</style>


<?php     include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/nav.php");?>
<?php

  $_SESSION['prev_summary_species'] = isset($_SESSION['prev_summary_species'])?$_SESSION['prev_summary_species']:'';
  $_SESSION['prev_summary_geneList'] = isset($_SESSION['prev_summary_geneList'])?$_SESSION['prev_summary_geneList']:'';
  $_SESSION['prev_summary_anatomy'] = isset($_SESSION['prev_summary_anatomy'])?$_SESSION['prev_summary_anatomy']:'';
  $_SESSION['prev_summary_disease'] = isset($_SESSION['prev_summary_disease'])?$_SESSION['prev_summary_disease']:'';
  $_SESSION['prev_summary_pheno'] = isset($_SESSION['prev_summary_pheno'])?$_SESSION['prev_summary_pheno']:'';

  if (strcmp($_SESSION['prev_summary_species'],$_SESSION['species']) != 0) {
    $_SESSION['prev_summary_species'] = $_SESSION['species'];
//    echo "prev summary species updated=".$_SESSION['prev_summary_species'];
    $_SESSION['summary_changed'] = 1;
//    echo "species summary_changed= ".$_SESSION['summary_changed'];
  } else if (strcmp($_SESSION['prev_summary_geneList'], $_SESSION['geneList']) != 0) {
    $_SESSION['prev_summary_geneList'] = $_SESSION['geneList'];
//     echo "prev geneList  updated=".$_SESSION['prev_summary_geneList'];
    $_SESSION['summary_changed'] = 1;
//    echo "geneList summary_changed= ".$_SESSION['summary_changed'];
  } else if (strcmp($_SESSION['prev_summary_disease'], $_SESSION['disease']) != 0) {
    $_SESSION['prev_summary_disease'] = $_SESSION['disease'];
//     echo "prev summary disease updated=".$_SESSION['prev_summary_disease'];
    $_SESSION['summary_changed'] = 1;
//    echo "disease summary_changed= ".$_SESSION['summary_changed'];
  }  else if (strcmp($_SESSION['prev_summary_anatomy'], $_SESSION['anatomy']) != 0) {
    $_SESSION['prev_summary_anatomy'] = $_SESSION['anatomy'];
//     echo "prev summary anatomy updated=".$_SESSION['prev_summary_anatomy'];
    $_SESSION['summary_changed'] = 1;
//    echo "anatomy summary_changed= ".$_SESSION['summary_changed'];
  }  else if (strcmp($_SESSION['prev_summary_pheno'], $_SESSION['phenotype']) != 0) {
    $_SESSION['prev_summary_pheno'] = $_SESSION['phenotype'];
//     echo "prev summary phenotype updated=".$_SESSION['prev_summary_phenotype'];
    $_SESSION['summary_changed'] = 1;
//    echo "phenotype summary_changed= ".$_SESSION['summary_changed'];
  }  else {
   $_SESSION['summary_changed'] = 0;
  }
?>

</head>
<body>


<div id="constrain">
<div class="constrain">
<br>
<br>
<p>

<?php
// top-cache.php
$url = $_SERVER["SCRIPT_NAME"];
$break = explode('/', $url);
$file = $break[count($break) - 1];
$cachefile = 'cache/cached-'.session_id().'-'.substr_replace($file ,"",-4).'.html';
$_SESSION['summary_cache_file'] = $cachefile;
$cachetime = 18000;

//echo "<h3>Session changed ".$_SESSION['summary_changed']."</h3>";
// Serve from the cache if it is younger than $cachetime
if ( $_SESSION['summary_changed'] == False && isset($_SESSION['summary_cache_file']) && file_exists($_SESSION['summary_cache_file']) && time() - $cachetime < filemtime($_SESSION['summary_cache_file'])) {
    echo "<!-- Cached copy, generated ".date('H:i', filemtime($cachefile))." -->\n";
//    echo "<h3>loaded cache file</h3>";
    readfile($cachefile);
    exit;
}
ob_start(); // Start the output buffer
///////

$gene_array = (isset($_SESSION['geneArray']))?$_SESSION['geneArray']:'';
$gene_sym_str = (isset($_SESSION['geneSymbols']))?$_SESSION['geneSymbols']:'';
$gene_sym_arr = explode(",", $gene_sym_str);

$i = 0;
if(isset($_SESSION['species']) && isset($_SESSION['geneArray']) && isset($_SESSION['summary_changed']) && $_SESSION['summary_changed'] == 1) {
//  echo "<h3>Regenerating...</h3>";
    //       echo "<h3>Summary contains all studies pertaining to the given gene(s) in Metabolomics Workbench</h3>"
   $prefix = "cache/plot";
   $suffix = ".png";
  $filename = $prefix.rand(1,1000).$suffix;
  $gene_array_str = implode("__", $gene_array);
  $gene_sym_str = implode("__", $gene_sym_arr);
  $anatomy_str = $_GET["anatomy"];
  $disease_str = $_GET["disease"];
  $anatomy = urlencode($anatomy_str);
  $disease = urlencode($disease_str);
  $viewType = "all";
  $organism_name = (isset($_SESSION['org_name']))?$_SESSION['org_name']:'';

  if (strcmp($anatomy,"NA") == 0 && strcmp($disease,"NA") == 0) {
    $h3_str = "<h3>Summary Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$gene_sym_str."</b></i></h3>";
  } else if (strcmp($anatomy,"NA") == 0) {
    $h3_str = "<h3>Summary Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$gene_sym_str."</b></i> disease <i><b>".$disease_str."</b></i></h3>";
  } else if (strcmp($disease,"NA") == 0) {
    $h3_str = "<h3>Summary Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$gene_sym_str."</b></i> anatomy <i><b>".$anatomy_str."</b></i></h3>";
  } else {
    $h3_str = "<h3>Summary Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$gene_sym_str."</b></i> anatomy <i><b>".$anatomy_str."</b></i> disease <i><b>".$disease_str."</b></i></h3>";
  }
  echo $h3_str;
  exec("/usr/bin/Rscript extractMWGeneSummary.R $species $gene_array_str $gene_sym_str  $filename $viewType $anatomy $disease", $output, $retvar);
  $htmlbuff = implode("\n", $output);
  echo $htmlbuff;
    $btnStr = "<p><button id=\"json\">TO JSON</button> <button id=\"csv\">TO CSV</button> </p>";
    echo $btnStr;
    $_SESSION['summary_changed'] = 0;


}

?>


</p>
    <p>
    </p>

</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php echo "<script src=\"".$METGENE_BASE_DIR_NAME."/src/tableHTMLExport.js\"></script>"; ?>
<script>
  $('#json').on('click',function(){
    var gene_arr_str = '<?php echo json_encode($gene_array); ?>';
    var gene_arr = JSON.parse(gene_arr_str);
    let len = gene_arr.length;
    let tabName = "";
    let fname = "";
    for (let i =0; i < len; i++) {
        tabName = "#Table1";
        fname = "Summary.json";
        $(tabName).tableHTMLExport({type:'json',filename:fname});
    }
  })
  $('#csv').on('click',function(){
    var gene_arr_str = '<?php echo json_encode($gene_array); ?>';
    var gene_arr = JSON.parse(gene_arr_str);
    let len = gene_arr.length;
    let tabName = "";
    let fname = "";
    for (let i =0; i < len; i++) {
        tabName = "#Table1";
        fname = "Summary.csv";
        $(tabName).tableHTMLExport({type:'csv',filename:fname});
    }
  })


</script>

<?php include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/footer.php");?>
<?php
// bottom-cache.php
// Cache the contents to a cache file
//echo "creating cached file ".$cachefile;

$cachefile = $_SESSION['summary_cache_file'];
$cached = fopen($cachefile, 'w');

fwrite($cached, ob_get_contents());
fclose($cached);
ob_end_flush(); // Send the output to the browser
?>
</body>




</html>
<?php endif; ?> 
