<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head><title>MetGENE: Gene Information</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php 
$curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));                                                     
$METGENE_BASE_DIR_NAME = $curDirPath;
?>
<?php
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-32x32.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-16x16.png\">";
    echo "<link rel=\"manifest\" href=\"".$METGENE_BASE_DIR_NAME."/site.webmanifest\">";

?>
<?php     include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/nav.php");?>

<?php

  $_SESSION['prev_geneInfo_species'] = isset($_SESSION['prev_geneInfo_species'])?$_SESSION['prev_geneInfo_species']:'';
  $_SESSION['prev_geneInfo_geneList'] = isset($_SESSION['prev_geneInfo_geneList'])?$_SESSION['prev_geneInfo_geneList']:'';
  $_SESSION['prev_geneInfo_anatomy'] = isset($_SESSION['prev_geneInfo_anatomy'])?$_SESSION['prev_geneInfo_anatomy']:'';
  $_SESSION['prev_geneInfo_disease'] = isset($_SESSION['prev_geneInfo_disease'])?$_SESSION['prev_geneInfo_disease']:'';
  $_SESSION['prev_geneInfo_pheno'] = isset($_SESSION['prev_geneInfo_pheno'])?$_SESSION['prev_geneInfo_pheno']:'';

  if (strcmp($_SESSION['prev_geneInfo_species'],$_SESSION['species']) != 0) {
    $_SESSION['prev_geneInfo_species'] = $_SESSION['species'];
//    echo "prev geneInfo species updated=".$_SESSION['prev_geneInfo_species'];
    $_SESSION['geneInfo_changed'] = 1;
//    echo "species geneInfo_changed= ".$_SESSION['geneInfo_changed'];
  } else if (strcmp($_SESSION['prev_geneInfo_geneList'], $_SESSION['geneList']) != 0) {
    $_SESSION['prev_geneInfo_geneList'] = $_SESSION['geneList'];
//     echo "prev geneList  updated=".$_SESSION['prev_geneInfo_geneList'];
    $_SESSION['geneInfo_changed'] = 1;
//    echo "geneList geneInfo_changed= ".$_SESSION['geneInfo_changed'];
  } else if (strcmp($_SESSION['prev_geneInfo_disease'], $_SESSION['disease']) != 0) {
    $_SESSION['prev_geneInfo_disease'] = $_SESSION['disease'];
//     echo "prev geneInfo disease updated=".$_SESSION['prev_geneInfo_disease'];
    $_SESSION['geneInfo_changed'] = 1;
//    echo "disease geneInfo_changed= ".$_SESSION['geneInfo_changed'];
  }  else if (strcmp($_SESSION['prev_geneInfo_anatomy'], $_SESSION['anatomy']) != 0) {
    $_SESSION['prev_geneInfo_anatomy'] = $_SESSION['anatomy'];
//     echo "prev geneInfo anatomy updated=".$_SESSION['prev_geneInfo_anatomy'];
    $_SESSION['geneInfo_changed'] = 1;
//    echo "anatomy geneInfo_changed= ".$_SESSION['geneInfo_changed'];
  }  else if (strcmp($_SESSION['prev_geneInfo_pheno'], $_SESSION['phenotype']) != 0) {
    $_SESSION['prev_geneInfo_pheno'] = $_SESSION['phenotype'];
//     echo "prev geneInfo phenotype updated=".$_SESSION['prev_geneInfo_phenotype'];
    $_SESSION['geneInfo_changed'] = 1;
//    echo "phenotype geneInfo_changed= ".$_SESSION['geneInfo_changed'];
  }  else {
   $_SESSION['geneInfo_changed'] = 0;
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
$_SESSION['geneInfo_cache_file'] = $cachefile;
$cachetime = 18000;

//echo "<h3>Session changed ".$_SESSION['geneInfo_changed']."</h3>";
// Serve from the cache if it is younger than $cachetime
if ( $_SESSION['geneInfo_changed'] == False && isset($_SESSION['geneInfo_cache_file']) && file_exists($_SESSION['geneInfo_cache_file']) && time() - $cachetime < filemtime($_SESSION['geneInfo_cache_file'])) {
    echo "<!-- Cached copy, generated ".date('H:i', filemtime($cachefile))." -->\n";
//    echo "<h3>loaded cache file</h3>";
    readfile($cachefile);
    exit;
}
ob_start(); // Start the output buffer
///////

$gene_array = (isset($_SESSION['geneArray']))?$_SESSION['geneArray']:'';
$gene_syms = (isset($_SESSION['geneSymbols']))?$_SESSION['geneSymbols']:'';											   
//echo "Gene array = ".$gene_array;
if(isset($_SESSION['species']) && isset($_SESSION['geneArray']) && isset($_SESSION['geneInfo_changed']) && $_SESSION['geneInfo_changed'] == 1) {
  $gene_vec_arr = array();
  $gene_sym_arr = array();
  $gene_sym_str_arr = explode(",", $gene_syms);
  for ($i=0; $i<count($gene_array); $i++) {
    if ($gene_array[$i] != "NA") {
       $gene_vec_arr[$i] = $gene_array[$i];
       $gene_sym_arr[$i] = $gene_sym_str_arr[$i];
    }
  }
  $gene_vec_str = implode("__", $gene_vec_arr);
//  echo "<h3>Gene vec str =".$gene_vec_str."</h3>";

  $gene_sym_str = implode(",", $gene_sym_arr);


  if (!empty($gene_vec_str)) {
    $h3_str = "<h3>Gene Information for <i><b>".$_SESSION['org_name']."</b></i> gene(s) <i><b>".$gene_sym_str."</b></i></h3>";
    echo $h3_str;
//    $gene_array = $_SESSION['geneArray'];
    $output = array();
    $htmlbuff = array();
    $domainName = $_SERVER['SERVER_NAME'];
    exec("/usr/bin/Rscript extractGeneInfoTable.R $species $gene_vec_str $domainName", $output, $retvar);
    $htmlbuff = implode($output);
    echo "<pre>";
    echo $htmlbuff;
    $btnStr = "<p><button id=\"json\">TO JSON</button> <button id=\"csv\">TO CSV</button> </p>";
    echo $btnStr;

    echo "</pre>";
  } else {
    echo "<h3><i>No gene information found for <b>".$organism_name."</b> gene(s) <b>".$gene_syms."</b></i></h3>";
  }
}?>
</p>


</div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php echo "<script src=\"".$METGENE_BASE_DIR_NAME."/src/tableHTMLExport.js\"></script>"; ?>
<script>
  $('#json').on('click',function(){
      $("#Table1").tableHTMLExport({type:'json',filename:'GeneInfo.json', ignoreColumns:"SELECT"});
  })
  $('#csv').on('click',function(){
    $("#Table1").tableHTMLExport({type:'csv',filename:'GeneInfo.csv'});
  })


</script>


<?php include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/footer.php");?>

<?php
// bottom-cache.php
// Cache the contents to a cache file
//echo "creating cached file ".$cachefile;

$cachefile = $_SESSION['geneInfo_cache_file'];
$cached = fopen($cachefile, 'w');

fwrite($cached, ob_get_contents());
fclose($cached);
ob_end_flush(); // Send the output to the browser
?>

</body>




</html>

