<?php
 $viewType = $_GET["viewType"];
?>
<?php if ( strcmp($viewType, "json") == 0 || strcmp($viewType, "txt") == 0):
 $species = $_GET["species"];
 $geneList = $_GET["GeneInfoStr"];
 $geneIDType = $_GET["GeneIDType"];
 $disease = $_GET["disease"];
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

 $gene_vec_str = implode(",", $gene_array);
 
 exec("/usr/bin/Rscript  extractFilteredStudiesInfo.R $species $gene_vec_str $enc_disease $enc_anatomy $viewType", $output, $retVar);
 $htmlbuff = implode("\n", $output);
 if (strcmp($viewType, "json") == 0){
   header('Content-type: application/json; charset=UTF-8');
 } else {
   header('Content-Type: text/plain; charset=UTF-8');
 }
 echo $htmlbuff;
 //echo "Printing encoded anatomy in php";
 //echo $enc_anatomy;


?>
<?php else: ?>
<?php
  $curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));
  $METGENE_BASE_DIR_NAME = $curDirPath;
?>

<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>

<head><title>MetGENE: Studies</title>
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



  $_SESSION['prev_study_species'] = isset($_SESSION['prev_study_species'])?$_SESSION['prev_study_species']:'';
  $_SESSION['prev_study_geneList'] = isset($_SESSION['prev_study_geneList'])?$_SESSION['prev_study_geneList']:'';
  $_SESSION['prev_study_anatomy'] = isset($_SESSION['prev_study_anatomy'])?$_SESSION['prev_study_anatomy']:'';
  $_SESSION['prev_study_disease'] = isset($_SESSION['prev_study_disease'])?$_SESSION['prev_study_disease']:'';
  $_SESSION['prev_study_pheno'] = isset($_SESSION['prev_study_pheno'])?$_SESSION['prev_study_pheno']:'';

//  echo "prev study species = ".$prev_study_species.";";
//  echo "prev study geneList = ".$prev_study_geneList.";";
//  echo "prev study anatomy = ".$prev_study_anatomy.";";
//  echo "prev study disease = ".$prev_study_disease.";";
//  echo "prev study phenotype = ".$prev_study_phenotype.";";


  if (strcmp($_SESSION['prev_study_species'],$_SESSION['species']) != 0) {
    $_SESSION['prev_study_species'] = $_SESSION['species'];
//    echo "prev study species updated=".$_SESSION['prev_study_species'].";";
    $_SESSION['study_changed'] = 1;
//    echo "species study_changed= ".$_SESSION['study_changed'];
  } else if (strcmp($_SESSION['prev_study_geneList'], $_SESSION['geneList']) != 0) {
    $_SESSION['prev_study_geneList'] = $_SESSION['geneList'];
//    echo "prev study geneList updated=".$_SESSION['prev_study_geneList'].";";
    $_SESSION['study_changed'] = 1;
//    echo "geneList study_changed= ".$_SESSION['study_changed'];
  } else if (strcmp($_SESSION['prev_study_disease'], $_SESSION['disease']) != 0) {
    $_SESSION['prev_study_disease'] = $_SESSION['disease'];
//    echo "prev study disease updated=".$_SESSION['prev_study_disease'].";";
    $_SESSION['study_changed'] = 1;
//    echo "disease study_changed= ".$_SESSION['study_changed'];
  }  else if (strcmp($_SESSION['prev_study_anatomy'], $_SESSION['anatomy']) != 0) {
    $_SESSION['prev_study_anatomy'] = $_SESSION['anatomy'];
//    echo "prev study anatomy updated=".$_SESSION['prev_study_anatomy'].";";
    $_SESSION['study_changed'] = 1;
//    echo "anatomy study_changed= ".$_SESSION['study_changed'];
  } else if (strcmp($_SESSION['prev_study_pheno'], $_SESSION['phenotype']) != 0) {
    $_SESSION['prev_study_pheno'] = $_SESSION['phenotype'];
//    echo "prev study pheno updated=".$_SESSION['prev_study_pheno'].";";
    $_SESSION['study_changed'] = 1;
//    echo "phenotype study_changed= ".$_SESSION['study_changed'];
  }  else {
   $_SESSION['study_changed'] = 0;
  }



?>
</head>
<style>
  table th, td {word-wrap:break-word;white-space: pre-line;border-bottom: 1px solid #dddddd;}
  table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
    width: 100%;
  }

</style>
<body>
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
//$cachefile = 'cache/cached-'.substr_replace($file ,"",-4).'.html';
$cachefile = 'cache/cached-'.session_id().'-'.substr_replace($file ,"",-4).'.html';
$_SESSION['study_cache_file'] = $cachefile;
$cachetime = 18000;

//echo "<h3>Session changed ".$_SESSION['study_changed']."</h3>";
// Serve from the cache if it is younger than $cachetime

//if ( isset($_SESSION['study_changed']) && ($_SESSION['study_changed'] == False) && file_exists($cachefile) && time() - $cachetime < filemtime($cachefile)) {
if ( isset($_SESSION['study_changed']) && ($_SESSION['study_changed'] == False) && file_exists($_SESSION['study_cache_file']) && time() - $cachetime < filemtime($_SESSION['study_cache_file'])) {
    echo "<!-- Cached copy, generated ".date('H:i', filemtime($cachefile))." -->\n";
//    echo "loaded cache file";
    readfile($cachefile);
    exit;
}
ob_start(); // Start the output buffer
//// end top-cache

$gene_symbols = (isset($_SESSION['geneSymbols']))?$_SESSION['geneSymbols']:'';
$gene_array = (isset($_SESSION['geneArray']))?$_SESSION['geneArray']:'';
$disease=(isset($_SESSION['disease']))?$_SESSION['disease']:'';
$anatomy=(isset($_SESSION['anatomy']))?$_SESSION['anatomy']:'';
$phenotype=(isset($_SESSION['phenotype']))?$_SESSION['phenotype']:'';
$species=(isset($_SESSION['species']))?$_SESSION['species']:'';
$organism_name = (isset($_SESSION['org_name']))?$_SESSION['org_name']:'';

if(isset($_SESSION['species']) && isset($_SESSION['geneArray'])  &&  isset($_SESSION['study_changed']) && $_SESSION['study_changed'] == 1) {
//if(isset($_SESSION['species']) && isset($_SESSION['geneArray'])) {
//  echo "<h3>Reloading...</h3>";
  $output = array();
  $htmlbuff = array();
  $gene_vec_arr = array();
  $gene_sym_arr = array();
  $gene_sym_str_arr = explode(",", $gene_symbols);

//  Filter out the bad geneIDs by removing NA

  for ($i=0; $i<count($gene_array); $i++) {
    if ($gene_array[$i] != "NA") {
       $gene_vec_arr[$i] = $gene_array[$i];
       $gene_sym_arr[$i] = $gene_sym_str_arr[$i];
    }
  }
  $gene_vec_str = implode(",", $gene_vec_arr);
//  echo "<h3>Gene vec str =".$gene_vec_str."</h3>";
  $gene_sym_str = implode(",", $gene_sym_arr);

  if (!empty($gene_vec_str)) {
    if (strcmp($anatomy,"NA") == 0 && strcmp($disease,"NA") == 0) {
      $h3_str = "<h3>Metabolomic Studies Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$gene_sym_str."</b></i></h3>";
    } else if (strcmp($anatomy,"NA") == 0) {
      $h3_str = "<h3>Metabolomic Studies Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$gene_sym_str."</b></i> disease <i><b>".$disease."</b></i></h3>";
    } else if (strcmp($disease,"NA") == 0) {
      $h3_str = "<h3>Metabolomic Studies Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$gene_sym_str."</b></i> anatomy <i><b>".$anatomy."</b></i></h3>";
    } else {
      $h3_str = "<h3>Metabolomic Studies Information for <i><b>".$organism_name."</b></i> gene(s) <i><b>".$gene_sym_str."</b></i> anatomy <i><b>".$anatomy."</b></i> disease <i><b>".$disease."</b></i></h3>";
    }


    echo $h3_str;

    $enc_disease = urlencode($disease);
    $enc_anatomy = urlencode($anatomy);
    $viewType = "html";
    //echo "Printing encoded anatomy in php";
    //echo $enc_anatomy;
    exec("/usr/bin/Rscript extractFilteredStudiesInfo.R $species $gene_vec_str $enc_disease $enc_anatomy $viewType", $output, $retvar);
    $htmlbuff = implode($output);

    if (!empty($htmlbuff)) {
      $p_str = "<p> Use check boxes to select metabolites to combine their studies. </p>";
      echo $p_str;
      echo "<pre>";
      echo $htmlbuff;
      echo "</pre>";
      $inputStr = "<input type=\"button\" value=\"Combine Studies\" onclick=\"GetSelected()\" />";
      echo $inputStr;
      echo "<br>";
      $btnStr = "<p><button id=\"json\">TO JSON</button> <button id=\"csv\">TO CSV</button> </p>";
      echo $btnStr;

    }
  } else {
    echo "<h3><i>No studies were found for <b>".$organism_name."</b> gene <b>".$gene_symbols."</b></i></h3>";
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
<?php echo "<script src=\"".$METGENE_BASE_DIR_NAME."/src/tableHTMLExport.js\"></script>"; ?>

<script>
  $('#json').on('click',function(){
      $("#Table1").tableHTMLExport({type:'json',filename:'Studies.json', ignoreColumns:"SELECT"});
  })
  $('#csv').on('click',function(){
    $("#Table1").tableHTMLExport({type:'csv',filename:'Studies.csv'});
  })


</script>
<script type="text/javascript">
    function GetSelected() {
        //Reference the Table.
       var grid = document.getElementById("Table1");

        //Reference the CheckBoxes in Table.
        var checkBoxes = grid.getElementsByTagName("input");
        var message = "Id Studies\n";
//        alert(message);
         $("#display").html("Processing....");
       var map1 = new Map();
        //Loop through the CheckBoxes.
        for (var i = 0; i < checkBoxes.length; i++) {
            if (checkBoxes[i].checked) {
                var row = checkBoxes[i].parentNode.parentNode;
                var compId = row.cells[2].innerText;
		var newId = compId.replaceAll(":", "___");
//		alert(newId);
                var studiesStr = row.cells[3].innerText;
                map1.set(newId, studiesStr);
            }

        }

        //Display selected Row data in Alert Box.
//        alert(message);
        var obj = Object.fromEntries(map1);

        var objStr = encodeURIComponent(JSON.stringify(obj));
	var species = '<?php echo $_SESSION["species"];?>';
	var geneList = '<?php echo $_SESSION["geneList"];?>';
	var geneIDType = '<?php echo $_SESSION["geneIDType"];?>';
	var disease = '<?php echo $_SESSION["disease"];?>';
	var anatomy = '<?php echo $_SESSION["anatomy"];?>';
        var phenotype = '<?php echo $_SESSION["phenotype"];?>';
//         alert(species);

         $.ajax({
                url: <?php echo "'".$METGENE_BASE_DIR_NAME."/combineStudies.php',";?>
                type: 'get',
                data: {metabolites: objStr},
                success: function(){
                  <?php echo "window.location.href = \"".$METGENE_BASE_DIR_NAME."/combineStudies.php?metabolites=\"+objStr+\"&GeneInfoStr=\" + geneList + \"&GeneIDType=\" + geneIDType + \"&species=\" +species + \"&disease=\" + disease + \"&anatomy=\" + anatomy + \"&phenotype=\" + phenotype;";?>

                }
          });
    }
</script>




<?php include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/footer.php");?>
<?php
// bottom-cache.php

// Cache the contents to a cache file
//echo "creating cached file ".$cachefile;
$cachefile = $_SESSION['study_cache_file'];
$cached = fopen($cachefile, 'w');

fwrite($cached, ob_get_contents());
fclose($cached);
ob_end_flush(); // Send the output to the browser
?>

</body>
</html>
<?php endif; ?>
