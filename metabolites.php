<?php
 //$viewType = $_GET["viewType"]; // Mano commented: 2022/12/14
 $viewType = trim(filter_input(INPUT_GET, "viewType", FILTER_SANITIZE_STRING));
?>
<?php if ( strcmp($viewType, "json") == 0 || strcmp($viewType, "txt") == 0): 

/*
# Example code to sanitize input
$species = trim(filter_input(INPUT_GET, "species", FILTER_SANITIZE_STRING));
$GeneListStr = trim(filter_input(INPUT_GET, "GeneListStr", FILTER_SANITIZE_STRING));
$GeneIDType = trim(filter_input(INPUT_GET, "GeneIDType", FILTER_SANITIZE_STRING));
*/

 //$species = $_GET["species"]; // Mano commented: 2022/12/14
 $species = trim(filter_input(INPUT_GET, "species", FILTER_SANITIZE_STRING));
 //$geneList = $_GET["GeneInfoStr"]; // Mano commented: 2022/12/14
 $GeneList = trim(filter_input(INPUT_GET, "GeneInfoStr", FILTER_SANITIZE_STRING));
 //$geneIDType = $_GET["GeneIDType"]; // Mano commented: 2022/12/14
 $GeneIDType = trim(filter_input(INPUT_GET, "GeneIDType", FILTER_SANITIZE_STRING));
 //$disease = $_GET["disease"]; // Mano commented: 2022/12/14
 $disease = trim(filter_input(INPUT_GET, "disease", FILTER_SANITIZE_STRING));
 $enc_disease = urlencode($disease);
 $anatomy = $_GET["anatomy"];
 $enc_anatomy = urlencode($anatomy);

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

 foreach ($gene_array as $value) { 
   exec("/usr/bin/Rscript extractMetaboliteInfo.R $species $value $enc_anatomy $enc_disease  $viewType", $output, $retVar);
   $htmlbuff = implode("\n", $output);
   if (strcmp($viewType, "json") == 0){ 
     header('Content-type: application/json; charset=UTF-8'); 
   } else {
     header('Content-Type: text/plain; charset=UTF-8');
   }
   echo $htmlbuff;

 } 
?>
<?php else: ?>
<?php
  $curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));
  $METGENE_BASE_DIR_NAME = $curDirPath;
?>
<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>

<head><title>MetGENE: Metabolites</title>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<?php
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-32x32.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-16x16.png\">";
    echo "<link rel=\"manifest\" href=\"".$METGENE_BASE_DIR_NAME."/site.webmanifest\">";

?>
<?php     include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/nav.php");?>


<?php                                                                                                                              

//  echo "prev met gene list = ".$prev_met_geneList;
 
  $_SESSION['prev_met_species'] = isset($_SESSION['prev_met_species'])?$_SESSION['prev_met_species']:'';
  $_SESSION['prev_met_geneList'] = isset($_SESSION['prev_met_geneList'])?$_SESSION['prev_met_geneList']:'';
  $_SESSION['prev_met_anatomy'] = isset($_SESSION['prev_met_anatomy'])?$_SESSION['prev_met_anatomy']:'';
  $_SESSION['prev_met_disease'] = isset($_SESSION['prev_met_disease'])?$_SESSION['prev_met_disease']:'';
  $_SESSION['prev_met_pheno'] = isset($_SESSION['prev_met_pheno'])?$_SESSION['prev_met_pheno']:'';

//  echo "prev met species = ".$prev_met_species.";";
//  echo "prev met geneList = ".$prev_met_geneList.";";
//  echo "prev met anatomy = ".$prev_met_anatomy.";";
//  echo "prev met disease = ".$prev_met_disease.";";
//  echo "prev met phenotype = ".$prev_met_phenotype.";";

  if (strcmp($_SESSION['prev_met_species'],$_SESSION['species']) != 0) {
    $_SESSION['prev_met_species'] = $_SESSION['species'];
//    echo "prev met species updated=".$_SESSION['prev_met_species'].";";
    $_SESSION['met_changed'] = 1;
//    echo "species met_changed= ".$_SESSION['met_changed'];
  } else if (strcmp($_SESSION['prev_met_geneList'], $_SESSION['geneList']) != 0) {
    $_SESSION['prev_met_geneList'] = $_SESSION['geneList'];
//    echo "prev met geneList updated =".$_SESSION['prev_met_geneList'].";";
    $_SESSION['met_changed'] = 1;
//    echo "geneList met_changed= ".$_SESSION['met_changed'];
  } else if (strcmp($_SESSION['prev_met_disease'], $_SESSION['disease']) != 0) {
    $_SESSION['prev_met_disease'] = $_SESSION['disease'];
//    echo "prev met disease updated= ".$_SESSION['prev_met_disease'].";";
    $_SESSION['met_changed'] = 1;
//    echo "disease met_changed= ".$_SESSION['met_changed'];
  }  else if (strcmp($_SESSION['prev_met_anatomy'], $_SESSION['anatomy']) != 0) {
    $_SESSION['prev_met_anatomy'] = $_SESSION['anatomy'];
//    echo "prev met anatomy updated= ".$_SESSION['prev_met_anatomy'].";";
    $_SESSION['met_changed'] = 1;
//    echo "anatomy met_changed= ".$_SESSION['met_changed'];
  }  else if (strcmp($_SESSION['prev_met_pheno'], $_SESSION['phenotype']) != 0) {
    $_SESSION['prev_met_pheno'] = $_SESSION['phenotype'];
//    echo "prev met pheno updated= ".$_SESSION['prev_met_pheno'].";";
    $_SESSION['met_changed'] = 1;
//    echo "phenotype met_changed= ".$_SESSION['met_changed'];
  }  else {
   $_SESSION['met_changed'] = 0;
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
//$cachefile = 'cache/cached-'.substr_replace($file ,"",-4).'.html';
$cachefile = 'cache/cached-'.session_id().'-'.substr_replace($file ,"",-4).'.html';                                                                                $_SESSION['met_cache_file'] = $cachefile;
$cachetime = 18000;

//echo "<h3>Session changed ".$_SESSION['met_changed']."</h3>";
// Serve from the cache if it is younger than $cachetime
//if ( isset($_SESSION['met_changed']) && ($_SESSION['met_changed'] == False) && file_exists($cachefile) && time() - $cachetime < filemtime($cachefile)) {
if ( isset($_SESSION['met_changed']) && ($_SESSION['met_changed'] == False) && file_exists($_SESSION['met_cache_file']) && time() - $cachetime < filemtime($_SESSION['met_cache_file'])) {
    echo "<!-- Cached copy, generated ".date('H:i', filemtime($cachefile))." -->\n";
//    echo "<h3>loaded cache file</h3>";
    readfile($cachefile);
    exit;
}
ob_start(); // Start the output buffer
/////

$organism_name = (isset($_SESSION['org_name']))?$_SESSION['org_name']:'';
$gene_sym_str = (isset($_SESSION['geneSymbols']))?$_SESSION['geneSymbols']:'';
$gene_sym_arr = explode(",", $gene_sym_str);
$gene_array = (isset($_SESSION['geneArray']))?$_SESSION['geneArray']:'';
//echo "Gene Array =".print_r($gene_array);
$disease=(isset($_SESSION['disease']))?$_SESSION['disease']:'';                                                                                          $enc_disease = urlencode($disease);
$anatomy=(isset($_SESSION['anatomy']))?$_SESSION['anatomy']:'';
$enc_anatomy = urlencode($anatomy);
$phenotype=(isset($_SESSION['phenotype']))?$_SESSION['phenotype']:'';
$i = 0;
if (isset($_SESSION['species']) && isset($_SESSION['geneArray']) && isset($_SESSION['met_changed']) && ($_SESSION['met_changed'] == 1)) {
  foreach ($gene_array as $value) {
    $output = array();
    $htmlbuff = array();
    $geneSymbolStr = $gene_sym_arr[$i];
    if ($value != NA) {
    if (strcmp($anatomy,"NA") == 0 && strcmp($disease,"NA") == 0) {
      $h3_str = "<h3>Metabolite Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$geneSymbolStr."</b></i></h3>";
    } else if (strcmp($anatomy,"NA") == 0) {
      $h3_str = "<h3>Metabolite Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$geneSymbolStr."</b></i> disease <i><b>".$disease."</b></i></h3>";
    } else if (strcmp($disease,"NA") == 0) {
      $h3_str = "<h3>Metabolite Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$geneSymbolStr."</b></i> anatomy <i><b>".$anatomy."</b></i></h3>";
    } else {
      $h3_str = "<h3>Metabolite Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$geneSymbolStr."</b></i> anatomy <i><b>".$anatomy."</b></i> disease <i><b>".$disease."</b></i></h3>";
    }
    echo $h3_str;
    $viewType = "html";

    exec("/usr/bin/Rscript extractMetaboliteInfo.R $species $value $enc_anatomy $enc_disease $viewType", $output, $retvar);
    $htmlbuff = implode($output);
    echo "<pre>";
    echo $htmlbuff;
    echo "</pre>";
    echo "<br>";
    } else {
      $h3_str = "<h3><i>No metabolite information found for <b>".$organism_name."</b> gene(s) <b>".$geneSymbolStr."</i></h3>";
      echo $h3_str;
      echo "<br>";
    }
    $i++;
  }
    $btnStr = "<p><button id=\"json\">TO JSON</button> <button id=\"csv\">TO CSV</button> </p>";
    echo $btnStr;
    $_SESSION['met_changed'] = 0;
    
}

?>
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
        tabName = "#Gene"+gene_arr[i]+"Table";
        fname = "Gene"+gene_arr[i]+"Metabolites.json";
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
        tabName = "#Gene"+gene_arr[i]+"Table";
        fname = "Gene"+gene_arr[i]+"Metabolites.csv";
        $(tabName).tableHTMLExport({type:'csv',filename:fname});
    }
  })

</script>
<?php include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/footer.php");?>

<?php
// bottom-cache.php
// Cache the contents to a cache file
//echo "creating cached file ".$cachefile;

$cachefile = $_SESSION['met_cache_file'];  
$cached = fopen($cachefile, 'w');

fwrite($cached, ob_get_contents());
fclose($cached);
ob_end_flush(); // Send the output to the browser
?>

</body>




</html>
<?php endif; ?> 
