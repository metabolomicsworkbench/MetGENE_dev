<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>

<head><title>MetGENE: Pathways</title>
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
   $species=(isset($_SESSION['species']))?$_SESSION['species']:''; 
   $disease=(isset($_SESSION['disease']))?$_SESSION['disease']:''; 
  $anatomy=(isset($_SESSION['anatomy']))?$_SESSION['anatomy']:''; 
  $phenotype=(isset($_SESSION['phenotype']))?$_SESSION['phenotype']:'';


  $gene_array=(isset($_SESSION['geneArray']))?$_SESSION['geneArray']:'';
  $species_sci_name=(isset($_SESSION['species_name']))?$_SESSION['species_name']:'';


?>
</head>
<body>


<div id="constrain">
<div class="constrain">
<br>
<br>
<p>
<?php
$gene_syms = (isset($_SESSION['geneSymbols']))?$_SESSION['geneSymbols']:'';
if(isset($_SESSION['species']) && isset($_SESSION['geneArray'])) {


  $output = array();
  $htmlbuff = array();
  $gene_vec_arr = array();
  $gene_sym_arr = array();
  $gene_sym_str_arr = explode(",", $gene_syms);
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
//  echo "gene symbols = ".$gene_sym_str;
  $h3_str = "<h3>Pathway Information for <i><b>".$_SESSION['org_name']."</b></i> gene(s) <i><b>".$gene_sym_str."</b></i></h3>";
  echo $h3_str;
  exec("/usr/bin/Rscript extractPathwayInfo.R $species $gene_vec_str $gene_sym_str $species_sci_name", $output, $retvar);
  $htmlbuff = implode($output);
  echo "<pre>";
  echo $htmlbuff;

  echo "</pre>";
  } else {
    echo "<h3><i>No pathways found for <b>".$organism_name."</b> gene(s) <b>".$gene_syms."</b></i></h3>";
  }

}?>


</p>

</div>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/footer.php");?>
</body>




</html>
