<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<style type='text/css' media='screen, projection, print'>
figcaption {
  padding: 5px;
  font-family: 'Cherry Swash', cursive;
  font-size: 0.7em;
  font-weight: 700;
  border: none;
  background: transparent;
  word-wrap:normal;
  text-align: center;
}

.container {
  position: relative;
  text-align: center;
  color: black;
}

/* Disease text */
.Disease {
  width:80px;
  position: absolute;
  bottom: 40px;
  left: 16px;
     //  overflow-wrap: normal;
  word-wrap: break-word;
  white-space: pre-line;
     /*  word-break: break-all;*/
}

.Phenotype {
  width:80px;                   
  position: absolute;                        
  bottom: 40px;               
  left: 16px;
  word-wrap: break-word;
  white-space: pre-line;
/*  font-size:0.7em;  */
} 

/* Organism text */
.Organism {
  width:80px;
  position: absolute;
  top: 30px;
  left: 16px;
  word-wrap: break-word;
  white-space: pre-line;
}

/* Anatomy text */
.Anatomy {
  width:80px;
  position: absolute;
  top: 130px;
  left: 16px;
  color: black;
  word-wrap: break-word;
  white-space: pre-line;
}

/* Pathway text */
.Pathways {
  position: absolute;
  top: 20px;
  right: 20px;
  color: black;
  font-size: 0.75em;
}

/* Reaction text */
.Reactions {
  position: absolute;
  top: 100px;
  right: 18px;
  color: black;
  font-size: 0.75em;
}

/* Metabolites text */
.Metabolites {
  position: absolute;
  top: 182px;
  right: 14px;
  color: black;
  font-size: 0.75em;
}

/* Studies text */
.Studies {
  position: absolute;
  top: 268px;
  right: 25px;
  color: black;
  font-size: 0.75em;
}



/* Centered text */
.Gene {
  width:80px;
  position: absolute;
  top: 44%;
  left: 54%;
  transform: translate(-50%, -50%);
  color: red;
}

}
</style>
<head><title>MetGENE: Home</title>
<?php
    $curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));
    $METGENE_BASE_DIR_NAME = $curDirPath;
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-32x32.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-16x16.png\">";
    echo "<link rel=\"manifest\" href=\"".$METGENE_BASE_DIR_NAME."/site.webmanifest\">";
?>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php     include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/nav.php");?>
<?php


  $_SESSION['prev_species'] = isset($_SESSION['prev_species'])?$_SESSION['prev_species']:'';
  $_SESSION['prev_geneList'] = isset($_SESSION['prev_geneList'])?$_SESSION['prev_geneList']:'';
  $_SESSION['prev_anatomy'] = isset($_SESSION['prev_anatomy'])?$_SESSION['prev_anatomy']:'';
  $_SESSION['prev_disease'] = isset($_SESSION['prev_disease'])?$_SESSION['prev_disease']:'';
  $_SESSION['prev_pheno'] = isset($_SESSION['prev_pheno'])?$_SESSION['prev_pheno']:'';



  if (strcmp($_SESSION['prev_species'],$_SESSION['species']) != 0) {
    $_SESSION['prev_species'] = $_SESSION['species'];
    $_SESSION['metgene_changed'] = 1;
//    echo "species changed= ".$_SESSION['metgene_changed'];
  } else if (strcmp($_SESSION['prev_geneList'], $_SESSION['geneList']) != 0) {
    $_SESSION['prev_geneList'] = $_SESSION['geneList'];
    $_SESSION['metgene_changed'] = 1;
//    echo "geneList changed= ".$_SESSION['metgene_changed'];
  } else if (strcmp($_SESSION['prev_disease'], $_SESSION['disease']) != 0) {
    $_SESSION['prev_disease'] = $_SESSION['disease'];
    $_SESSION['metgene_changed'] = 1;
//    echo "disease changed= ".$_SESSION['metgene_changed'];
  }  else if (strcmp($_SESSION['prev_anatomy'], $_SESSION['anatomy']) != 0) {
    $_SESSION['prev_anatomy'] = $_SESSION['anatomy'];
    $_SESSION['metgene_changed'] = 1;
//    echo "anatomy changed= ".$_SESSION['metgene_changed'];
  }  else if (strcmp($_SESSION['prev_pheno'], $_SESSION['phenotype']) != 0) {
    $_SESSION['prev_pheno'] = $_SESSION['phenotype'];
    $_SESSION['metgene_changed'] = 1;
//    echo "phenotype changed= ".$_SESSION['metgene_changed'];
  }  else {
   $_SESSION['metgene_changed'] = 0;
  }

  
?>

</head>
<body>


<div id="constrain">
<div class ="constrain">




<p style="font-family: Arial; font-size: 14px; text-align: justify; text-indent: 30px;"> 
<table>
<tr>
<td>
<div class="container">
  <?php echo "<img src =\"".$METGENE_BASE_DIR_NAME."/images/MetGeneSchematicNew.png\" width=\"395\" height=\"320\" usemap=\"#schematic\">";?>

<map name="schematic">
         <?php echo "<area shape = \"circle\" coords = \"380,340,100\" alt = \"Genes Link\" href = \"".$METGENE_BASE_DIR_NAME."/geneInfo.php\">";?>
         <?php echo "<area shape = \"rect\" coords = \"665,60,840,180\" alt = \"Pathways Link\" href = \"".$METGENE_BASE_DIR_NAME."/pathways.php\">";?>
         <?php echo "<area shape = \"rect\" coords = \"665,240,840,360\" alt = \"Reactions Link\" href = \"".$METGENE_BASE_DIR_NAME."/reactions.php\">";?>
         <?php echo "<area shape = \"rect\" coords = \"665,420,840,540\" alt = \"Metabolites Link\" href = \"".$METGENE_BASE_DIR_NAME."/metabolites.php\">";?>
         <?php echo "<area shape = \"rect\" coords = \"665,700,840,720\" alt = \"Studies Link\" href = \"".$METGENE_BASE_DIR_NAME."/studies.php\">";?>
</map>
<?php


  $metgene_changed = (isset($_SESSION['metgene_changed']))?$_SESSION['metgene_changed']:'';  



// top-cache.php
  $url = $_SERVER["SCRIPT_NAME"];
  $break = explode('/', $url);
  $file = $break[count($break) - 1];
//  $cachefile = 'cache/cached-'.substr_replace($file ,"",-4).'.html';
  $cachefile = 'cache/cached-'.session_id().'-'.substr_replace($file ,"",-4).'.html';
  $_SESSION['metgene_cache_file'] = $cachefile;
  $cachetime = 18000;

//echo "<h3>Session changed ".$_SESSION['metgene_changed']."</h3>";
// Serve from the cache if it is younger than $cachetime
if ( $_SESSION['metgene_changed'] == False && file_exists($_SESSION['metgene_cache_file']) && time() - $cachetime < filemtime($_SESSION['metgene_cache_file'])) {
    echo "<!-- Cached copy, generated ".date('H:i', filemtime($cachefile))." -->\n";
//    echo "<h3>loaded cache file</h3>";
    readfile($cachefile);
    exit;
}
ob_start(); // Start the output buffer


if(isset($_SESSION['species']) && isset($_SESSION['geneArray']) && isset($_SESSION['metgene_changed']) && $_SESSION['metgene_changed'] == 1) {

  // Get the gene symbold here for the gene Ids

  $geneSymbols = isset($_SESSION['geneSymbols'])?$_SESSION['geneSymbols']:'';
  $gene_ids_arr = isset($_SESSION['geneArray'])?$_SESSION['geneArray']:'';
  $gene_list_arr = isset($_SESSION['geneListArr'])?$_SESSION['geneListArr']:'';
//  echo "geneIDs = ".$geneIDs;
  $gene_symbols_arr = explode(",",$geneSymbols);

  $geneTypeID = $_SESSION['geneTYpeID'];

  $geneNameStr = $gene_symbols_arr[0];
  $has_invalid_genes = 0;
  $invalidGenesArr = array();
//  echo "count of gene array = ".count($gene_ids_arr);
  if (count($gene_ids_arr) > 1) {
//    echo "Gene IDs = ".$gene_ids_arr[$x];
//    echo "Gene syms = ".$gene_symbols_arr[$x];
    for ($x=0; $x < count($gene_ids_arr); $x++) {
      if (strcmp($gene_ids_arr[$x], "NA") == 0 || strcmp($gene_symbols_arr[$x], "NA") == 0) {
          if (strcmp($gene_ids_arr[$x], "NA") == 0) {
            if (strcmp($gene_symbols_arr[$x], "NA") != 0) { 
              array_push($invalidGenesArr, $gene_symbols_arr[$x]);
            } else {
              array_push($invalidGenesArr, $gene_list_arr[$x]); 
            }
          } else {
            array_push($invalidGenesArr, $gene_ids_arr[$x]);
          }
         $has_invalid_genes = 1;    
      }
    }
    $geneNameStr = $gene_symbols_arr[0].", ...";
  } else {
    if (strcmp($gene_ids_arr[0], "NA") == 0 || strcmp($gene_symbols_arr[0], "NA") == 0) {
      $geneNameStr = "NA";

      if (strcmp($gene_ids_arr[0], "NA") == 0) {
         if (strcmp($gene_symbols_arr[0], "NA") != 0) {
           array_push($invalidGenesArr, $gene_symbols_arr[0]);
         } else {
           array_push($invalidGenesArr, $gene_list_arr[0]); 
         }
       } else {
           array_push($invalidGenesArr, $gene_ids_arr[0]);
       }

      $has_invalid_genes = 1;
    }
  }


  $anatomyStr = $anatomy_array[0];
  if (count($anatomy_array) > 1) {
    $anatomyStr = $anatomy_array[0].", ...";
  }

  $diseaseStr = $disease_array[0];
  if (count($disease_array) > 1) {
    $diseaseStr = $disease_array[0].", ...";
  }

  $phenotypeStr = $phenotype_array[0];
  if (count($phenotype_array) > 1) {
    $phenotypeStr = $phenotype_array[0].", ...";
  }




  
  $anaStr = "<div class=\"Anatomy\">".$anatomyStr."</div>";
  $diseaseStr = "<div class=\"Disease\">".$diseaseStr."</div>";
  $phenotypeStr = "<div class=\"Phenotype\">".$phenotypeStr."</div>";
// Get organism name
  $human = array("Human","human","hsa","Homo sapiens");
  $mouse = array("Mouse","mouse","mmu","Mus musculus");
  $rat = array("Rat","rat","rno","Rattus norvegicus");
  if(in_array($species, $human)){
     $organism_name = "Human";
     $org_sci_name = "Home sapiens";
  } else if(in_array($species, $mouse)){
     $organism_name = "Mouse";
     $org_sci_name = "Mus musculus";
  } else if(in_array($species, $rat)){
     $organism_name = "Rat";
     $org_sci_name = "Rattus norvegicus";
  } else {
     $organism_name = "";
  }
  $_SESSION['org_name'] = $organism_name;
  $_SESSION['species_name'] = $org_sci_name;

  $orgStr = "<div class=\"Organism\">"."<a href=\"https://www.genome.jp/kegg-bin/show_organism?org=".$species."\"target = \"_blank\">".$organism_name."</a></div>";  
  $geneStr = "<div class=\"Gene\">"."<a href=\"".$METGENE_BASE_DIR_NAME."/geneInfo.php?GeneInfoStr=".$geneList."&species=".$species."&GeneIDType=".$geneIDType."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">".$geneNameStr."</a></div>";  
  $pathwaysStr = "<div class=\"Pathways\">"."<a href=\"".$METGENE_BASE_DIR_NAME."/pathways.php?GeneInfoStr=".$geneList."&species=".$species."&GeneIDType=".$geneIDType."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">".Pathways."</a></div>";
  $reactionsStr = "<div class=\"Reactions\">"."<a href=\"".$METGENE_BASE_DIR_NAME."/reactions.php?GeneInfoStr=".$geneList."&species=".$species."&GeneIDType=".$geneIDType."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">".Reactions."</a></div>";
  $metabolitesStr = "<div class=\"Metabolites\">"."<a href=\"".$METGENE_BASE_DIR_NAME."/metabolites.php?GeneInfoStr=".$geneList."&species=".$species."&GeneIDType=".$geneIDType."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">".Metabolites."</a></div>";
  $studiesStr = "<div class=\"Studies\">"."<a href=\"".$METGENE_BASE_DIR_NAME."/studies.php?GeneInfoStr=".$geneList."&species=".$species."&GeneIDType=".$geneIDType."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">".Studies."</a></div>";
//  $studiesStr = "<div class=\"Studies\">"."<a href=\"/MetGENE/studies.php\">".Studies."</a></div>";
  echo $orgStr;
  echo $anaStr;
  echo $diseaseStr;
  echo $phenotypeStr;
  echo $geneStr;
  echo $pathwaysStr;
  echo $reactionsStr;
  echo $metabolitesStr;
  echo $studiesStr;
  $_SESSION['metgene_changed'] = 0;
}
?>

</div>
</td>

<td><p style="margin:25px;font-size:120%;"">
<?php
//  echo $metgene_changed;
//  echo "<br>";
//  echo $prev_geneList;
  if ($has_invalid_genes == 1) {
    $arrlen = count($invalidGenesArr);
    $geneIDType = $geneIDType=(isset($_SESSION['geneIDType']))?$_SESSION['geneIDType']:'';
    if ($arrlen > 1) {
      $invalidGeneListStr= implode(",", $invalidGenesArr);
      echo "<p style=\"font-size:14px; color:#538b01; font-weight:bold; font-style:italic;\"><h3><b>".$invalidGeneListStr."</b><span style=\"color: #ff0000\">  are not valid gene IDs for the Gene ID type ".$geneIDType." for species ".$organism_name.".</span></h3></p>";
    } elseif ($arrlen == 1) {
      $invalidGeneListStr= $invalidGenesArr[0];
      echo "<p style=\"font-size:14px; color:#538b01; font-weight:bold; font-style:italic;\"><h3><b>".$invalidGeneListStr."</b><span style=\"color: #ff0000\">  is not a valid gene ID for type ".$geneIDType." for species ".$organism_name.".</span></h3></p>";
    } else {
      echo "<br>";
    }
  }

//for ($i = 0; $i < count($gene_ids_arr); $i++) {
//  $gene_id = $gene_ids_arr[$i];
//  $gene_symbol = $gene_symbols_arr[$i];
//  $gene_id_sym_map[$gene_id] = $gene_symbol;
//}
// check for metabolic genes 
  $metGeneSYMBOLFileName = "./data/".$species."_metSYMBOLs.txt";
  $metGeneSyms =  explode("\n", file_get_contents($metGeneSYMBOLFileName));
  $resultSyms =  array_diff($gene_symbols_arr, $metGeneSyms);
  $num_nonmetGenes = count($resultSyms);
  if (!empty($resultSyms)) {
     $nonmetGenes = implode(",", $resultSyms);
     if ($num_nonmetGenes > 1) {
         echo("<p style=\"font-size:14px; color:#538b01; font-weight:bold; font-style:italic;\"><h3><b>"."Warning: Genes ".$nonmetGenes." are not metabolic genes and hence will not contain Reactions, Metabolites, Studies or Summary views.</b></h3></p>");
     } else {
         echo("<p style=\"font-size:14px; color:#538b01; font-weight:bold; font-style:italic;\"><h3><b>"."Warning: Gene ".$nonmetGenes." is not a metabolic gene and hence will not contain Reactions, Metabolites, Studies or Summary views.</b></h3></p>");
     }
  }

  $descStr = "In the MetGENE tool, information about the  gene(s) ".$geneNameStr."  is presented in <a href=\"".$METGENE_BASE_DIR_NAME."/geneInfo.php?GeneInfoStr=".$geneList."&species=".$species."&GeneIDType=".$geneIDType."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">Genes</a>, the corresponding pathways in <a href=\"".$METGENE_BASE_DIR_NAME."/pathways.php?GeneInfoStr=".$geneList."&GeneIDType=".$geneIDType."&species=".$species."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">Pathways</a> and the reactions in <a href=\"".$METGENE_BASE_DIR_NAME."/reactions.php?GeneInfoStr=".$geneList."&species=".$species."&GeneIDType=".$geneIDType."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">Reactions</a> tabs. The metabolites participating in the reactions are presented in <a href=\"".$METGENE_BASE_DIR_NAME."/metabolites.php?GeneInfoStr=".$geneList."&species=".$species."&GeneIDType=".$geneIDType."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">Metabolites</a> tab. For each metabolite, the studies containing the metabolite are identified from the <a href=\"https://www.metabolomicsworkbench.org\" target=\"_blank\">Metabolomics Workbench</a> (MW) and presented in <a href=\"".$METGENE_BASE_DIR_NAME."/studies.php?GeneInfoStr=".$geneList."&species=".$species."&GeneIDType=".$geneIDType."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">Studies</a> tab.";
 echo $descStr;
?>
</p>
<p style="margin:25px;font-size:120%;">
     The data from MW studies are presented as table(s), with the metabolite names hyperlinked to MW <a href="https://www.metabolomicsworkbench.org/databases/refmet/index.php" target = "_blank">RefMet</a> page (or to the corresponding <a href="https://www.genome.jp/kegg/" target = "_blank">KEGG</a> entry in the absence of a RefMet name) for the metabolite, reaction hyperlinked to its KEGG entry and MW studies hyperlinked to their respective pages. The user also has access to the metabolite statistics via <a href="https://www.metabolomicsworkbench.org/data/metstat_form.php" target="_blank">MetStat</a>. Further, the user has the option to select more than one metabolite to list only those studies in which all the selected metabolites appear and can download the table as a text, HTML or JSON file.
</p></td>

</tr>
</table>
</p>

</div>
</div>
<?php include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/footer.php");?>
<?php
// bottom-cache.php
// Cache the contents to a cache file
//echo "creating cached file ".$cachefile;

$cachefile = $_SESSION['metgene_cache_file']; 
$cached = fopen($cachefile, 'w');

fwrite($cached, ob_get_contents());
fclose($cached);
ob_end_flush(); // Send the output to the browser
?>

</body>

</html>
