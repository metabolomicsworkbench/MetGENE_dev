<?php
 $viewType = $_GET["viewType"];
?>
<?php if ( strcmp($viewType, "json") == 0 || strcmp($viewType, "txt") == 0): 
 $species = $_GET["species"];
 $geneList = $_GET["GeneInfoStr"];
 $geneIDType = $_GET["GeneIDType"];

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
   exec("/usr/bin/Rscript extractReactionInfo.R $species $value  $viewType", $output, $retVar);
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
<head><title>MetGENE: Reactions</title>
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

  $_SESSION['prev_rxn_species'] = isset($_SESSION['prev_rxn_species'])?$_SESSION['prev_rxn_species']:'';
  $_SESSION['prev_rxn_geneList'] = isset($_SESSION['prev_rxn_geneList'])?$_SESSION['prev_rxn_geneList']:'';
  $_SESSION['prev_rxn_anatomy'] = isset($_SESSION['prev_rxn_anatomy'])?$_SESSION['prev_rxn_anatomy']:'';
  $_SESSION['prev_rxn_disease'] = isset($_SESSION['prev_rxn_disease'])?$_SESSION['prev_rxn_disease']:'';
  $_SESSION['prev_rxn_pheno'] = isset($_SESSION['prev_rxn_pheno'])?$_SESSION['prev_rxn_pheno']:'';

  if (strcmp($_SESSION['prev_rxn_species'],$_SESSION['species']) != 0) {
    $_SESSION['prev_rxn_species'] = $_SESSION['species'];
//    echo "prev rxn species updated=".$_SESSION['prev_rxn_species'];
    $_SESSION['rxn_changed'] = 1;
//    echo "species rxn_changed= ".$_SESSION['rxn_changed'];
  } else if (strcmp($_SESSION['prev_rxn_geneList'], $_SESSION['geneList']) != 0) {
    $_SESSION['prev_rxn_geneList'] = $_SESSION['geneList'];
//     echo "prev geneList  updated=".$_SESSION['prev_rxn_geneList'];
    $_SESSION['rxn_changed'] = 1;
//    echo "geneList rxn_changed= ".$_SESSION['rxn_changed'];
  } else if (strcmp($_SESSION['prev_rxn_disease'], $_SESSION['disease']) != 0) {
    $_SESSION['prev_rxn_disease'] = $_SESSION['disease'];
//     echo "prev rxn disease updated=".$_SESSION['prev_rxn_disease'];
    $_SESSION['rxn_changed'] = 1;
//    echo "disease rxn_changed= ".$_SESSION['rxn_changed'];
  }  else if (strcmp($_SESSION['prev_rxn_anatomy'], $_SESSION['anatomy']) != 0) {
    $_SESSION['prev_rxn_anatomy'] = $_SESSION['anatomy'];
//     echo "prev rxn anatomy updated=".$_SESSION['prev_rxn_anatomy'];
    $_SESSION['rxn_changed'] = 1;
//    echo "anatomy rxn_changed= ".$_SESSION['rxn_changed'];
  }  else if (strcmp($_SESSION['prev_rxn_pheno'], $_SESSION['phenotype']) != 0) {
    $_SESSION['prev_rxn_pheno'] = $_SESSION['phenotype'];
//     echo "prev rxn phenotype updated=".$_SESSION['prev_rxn_phenotype'];
    $_SESSION['rxn_changed'] = 1;
//    echo "phenotype rxn_changed= ".$_SESSION['rxn_changed'];
  }  else {
   $_SESSION['rxn_changed'] = 0;
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
$_SESSION['rxn_cache_file'] = $cachefile;
$cachetime = 18000;

//echo "<h3>Session changed ".$_SESSION['rxn_changed']."</h3>";
// Serve from the cache if it is younger than $cachetime
if ( $_SESSION['rxn_changed'] == False && isset($_SESSION['rxn_cache_file']) && file_exists($_SESSION['rxn_cache_file']) && time() - $cachetime < filemtime($_SESSION['rxn_cache_file'])) {
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
//echo "Gene array = ".$gene_array;
$i = 0;
if(isset($_SESSION['species']) && isset($_SESSION['geneArray']) && isset($_SESSION['rxn_changed']) && $_SESSION['rxn_changed'] == 1) {
//  echo "<h3>Regenerating...</h3>";
  foreach ($gene_array as $value) {
    $output = array();
    $htmlbuff = array();
    $geneSymbolStr = $gene_sym_arr[$i];
    if ($value != "NA") {
    $h3_str = "<h3>Reaction Information for <i><b>".$organism_name."</b></i> gene <i><b>".$geneSymbolStr."</b></i></h3>";
    echo $h3_str;
    $viewType = "html";
    exec("/usr/bin/Rscript extractReactionInfo.R $species $value $viewType", $output, $retvar);
    $htmlbuff = implode($output);
    echo "<pre>";
    echo $htmlbuff;
    echo "</pre>";
    echo "<br>";
    } else {
    $h3_str = "<h3>No reaction information for found for <i><b>".$organism_name."</b></i> gene <i><b>".$geneSymbolStr."</b></i></h3>";
    echo $h3_str;
    }
    $i++;
  }
    $btnStr = "<p><button id=\"json\">TO JSON</button> <button id=\"csv\">TO CSV</button> </p>";
    echo $btnStr;
    $_SESSION['rxn_changed'] = 0;


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
        fname = "Gene"+gene_arr[i]+"Reactions.json";
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
        fname = "Gene"+gene_arr[i]+"Reactions.csv";
        $(tabName).tableHTMLExport({type:'csv',filename:fname});
    }
  })


</script>

<?php include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/footer.php");?>
<?php
// bottom-cache.php
// Cache the contents to a cache file
//echo "creating cached file ".$cachefile;

$cachefile = $_SESSION['rxn_cache_file'];
$cached = fopen($cachefile, 'w');

fwrite($cached, ob_get_contents());
fclose($cached);
ob_end_flush(); // Send the output to the browser
?>
</body>




</html>
<?php endif; ?> 
