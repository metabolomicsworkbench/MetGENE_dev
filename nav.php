  <!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<?php
  session_start();
  $curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));                                                     
  $METGENE_BASE_DIR_NAME = $curDirPath;

  $_SESSION['species'] = $_GET["species"];
  $_SESSION['geneList'] = $_GET["GeneInfoStr"];
  $_SESSION['geneIDType'] = $_GET["GeneIDType"];
  $_SESSION['disease'] = $_GET["disease"];
  $_SESSION['anatomy'] = $_GET["anatomy"];
  $_SESSION['phenotype'] = $_GET["phenotype"];

// remove white spaces from geneList
  $_SESSION['geneList'] = str_replace(' ', '', $_SESSION['geneList']);
 
  $species=(isset($_SESSION['species']))?$_SESSION['species']:$_GET["species"]; 
  $geneList=(isset($_SESSION['geneList']))?$_SESSION['geneList']:$_GET["GeneInfoStr"]; 
  $geneIDType=(isset($_SESSION['geneIDType']))?$_SESSION['geneIDType']:$_GET["GeneIDType"];
  $disease=(isset($_SESSION['disease']))?$_SESSION['disease']:$_GET["disease"]; 
  $anatomy=(isset($_SESSION['anatomy']))?$_SESSION['anatomy']:$_GET["anatomy"]; 
  $phenotype=(isset($_SESSION['phenotype']))?$_SESSION['phenotype']:$_GET["phenotype"];


  $phenotype_array = explode("__", $phenotype);
  $disease_array = explode("__", $disease);
  $anatomy_array = explode("__", $anatomy);

  $_SESSION['prev_nav_species'] = isset($_SESSION['prev_nav_species'])?$_SESSION['prev_nav_species']:'';
  $_SESSION['prev_nav_geneList'] = isset($_SESSION['prev_nav_geneList'])?$_SESSION['prev_nav_geneList']:'';
  $_SESSION['prev_nav_geneIDType'] = isset($_SESSION['prev_nav_geneIDType'])?$_SESSION['prev_nav_geneIDType']:'';
  $_SESSION['prev_nav_anatomy'] = isset($_SESSION['prev_nav_anatomy'])?$_SESSION['prev_nav_anatomy']:'';
  $_SESSION['prev_nav_disease'] = isset($_SESSION['prev_nav_disease'])?$_SESSION['prev_nav_disease']:'';
  $_SESSION['prev_nav_pheno'] = isset($_SESSION['prev_nav_pheno'])?$_SESSION['prev_nav_pheno']:'';

    if (strcmp($_SESSION['prev_nav_species'],$_SESSION['species']) != 0) {
      $_SESSION['prev_nav_species'] = $_SESSION['species'];
//    echo "prev nav species updated=".$_SESSION['prev_nav_species'];
      $_SESSION['nav_changed'] = 1;
//    echo "species nav_changed= ".$_SESSION['nav_changed'];
  } else if (strcmp($_SESSION['prev_nav_geneList'], $_SESSION['geneList']) != 0) {
      $_SESSION['prev_nav_geneList'] = $_SESSION['geneList'];
//     echo "prev geneList  updated=".$_SESSION['prev_nav_geneList'];
      $_SESSION['nav_changed'] = 1;
//    echo "geneList nav_changed= ".$_SESSION['nav_changed'];
  } else  if (strcmp($_SESSION['prev_nav_geneIDType'], $_SESSION['geneIDType']) != 0) {
      $_SESSION['prev_nav_geneIDType'] = $_SESSION['geneIDType'];
//     echo "prev geneType  updated=".$_SESSION['prev_nav_geneIDType'];
      $_SESSION['nav_changed'] = 1;
//    echo "geneIDType nav_changed= ".$_SESSION['nav_changed'];
  } else if (strcmp($_SESSION['prev_nav_disease'], $_SESSION['disease']) != 0) {
      $_SESSION['prev_nav_disease'] = $_SESSION['disease'];
//     echo "prev nav disease updated=".$_SESSION['prev_nav_disease'];
      $_SESSION['nav_changed'] = 1;
//    echo "disease nav_changed= ".$_SESSION['nav_changed'];
  }  else if (strcmp($_SESSION['prev_nav_anatomy'], $_SESSION['anatomy']) != 0) {
      $_SESSION['prev_nav_anatomy'] = $_SESSION['anatomy'];
//    echo "prev nav anatomy updated=".$_SESSION['prev_nav_anatomy'];
      $_SESSION['nav_changed'] = 1;
//    echo "anatomy nav_changed= ".$_SESSION['nav_changed'];
  }  else if (strcmp($_SESSION['prev_nav_pheno'], $_SESSION['phenotype']) != 0) {
      $_SESSION['prev_nav_pheno'] = $_SESSION['phenotype'];
//     echo "prev nav phenotype updated=".$_SESSION['prev_nav_phenotype'];
      $_SESSION['nav_changed'] = 1;
//    echo "phenotype nav_changed= ".$_SESSION['nav_changed'];
  }  else {
      $_SESSION['nav_changed'] = 0;
  }
//  echo "nav_changed=".$_SESSION['nav_changed'];
  if (isset($_SESSION['nav_changed']) && $_SESSION['nav_changed'] == 1) {
      $domainName = $_SERVER['SERVER_NAME'];
      //      echo $domainName;
    exec("/usr/bin/Rscript extractGeneIDsAndSymbols.R $species $geneList $geneIDType $domainName", $symbol_geneIDs, $retvar);
//    print_r($symbol_geneIDs);
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
 
    $_SESSION['geneArray'] = $gene_array;
    $_SESSION['geneSymbols'] = implode(",", $gene_symbols);
    $_SESSION['geneListArr'] = explode("__", $geneList);

 } 
						   
// set a session array variable containong all gene names
  $_SESSION['diseaseArray'] = $disease_array;
  $_SESSION['phenotypeArray'] = $phenotype_array;
  $_SESSION['anatomyArray'] = $anatomy_array;



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
 
?>
<style type='text/css' media='screen, projection, print'>
@import "css/header.css";
@import "css/layout.css";
@import "css/style_layout.css";
@import "css/main.css";
@import "css/site.css";
@import "css/layout_2col.css";	@import "css/966px.css";

#hdr 
.login-nav {
	background-color: #ffffff;
	background-image:  <?php echo "url('".$METGENE_BASE_DIR_NAME."/images/MetGeneBanner.png');";?>
	background-size: 100%;
}

.topnav ul {
    list-style-type: none;
    margin: 0;
    padding: 0;
    overflow: hidden;
}

.topnav li {
    float: left;
}

.topnav li a .dropbtn{
    display: inline;
    color: #215EA9;
	text-align: center;
	text-decoration: none;
    
}

.topnav li a .dropbtnlit{
    display: inline;
    color: #215EA9;
	text-align: center;
	text-decoration: none;
    background-color: #edd8c5;    
}


.dropdown {
  float: left;
  overflow: hidden;
}

.dropdown .dropbtn{
  font-size: 16px;  
  border: none;
  color: #215EA9;
  padding: 8px 12px;  
  
  background-color: #eeeeee;
	font-size: 100%;
	margin-right:2px;
	padding: 8px 12px;
	font-weight: normal;
  
  }	

.dropdown .dropbtnlit{
  font-size: 16px;  
  border: none;
  color: #215EA9;
  padding: 8px 12px;  
  
  background-color: #edd8c5;
	font-size: 100%;
	margin-right:2px;
	padding: 8px 12px;
	font-weight: normal;
  
  }	

.dropdown-content {
  display: none;
  position: absolute;
  background-color: #f9f9f9;
 
}
.dropdown-content a {
  float: none;
  color: black;
  padding: 8px 12px;
  text-decoration: none;
  display: block;
  text-align: left;
}

.dropdown-content a:hover {
  background-color: #ddd;
}

/* Show the dropdown menu on hover */
.dropdown:hover .dropdown-content {
  display: block;
}

.styled-table {
    display:table;
    table-layout:fixed;
    width: 100%;
    word-wrap: break-word;

}

.styled-table td {
/*    border: 1px solid #000;*/
    padding:5px 10px;
    width: 3%;
    text-align: center;
    word-break: break-all;
    white-space: pre-line;
}
.styled-table tbody tr {
    border-bottom: 1px solid #dddddd;
}

.styled-table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
}
/*
table {
    border-collapse:collapse;
    table-layout:fixed;
}

td{
    border:1px solid #ccc;
    padding:5px 10px;
    vertical-align:top;
    word-break:break-word;
    white-space: pre-line;
}*/
/*table, th, td {
  border: 1px solid black;
  word-wrap: break-word;
  width:100%;
}
*/
</style>

<script type='text/javascript'><!--//--><![CDATA[//><!--
//--><!]]></script><style type="text/css">

</style>

<body>
<div id="constrain">
  <div class="constrain">
    <div id="hdr">
	<span class="cleardiv"><!-- --></span>
	<span class="cleardiv"><!-- --></span>
	<div class="login-nav"><div style="text-align:right; vertical-align: text-bottom; height:1.1cm; padding-right: 1.0em; padding-top: 1.0em;">
<!--        <a href="" style="color: #dddddd;">Log in</a> <span style="color: #dddddd;"> / </span> 
	    <a href="" style="color: #dddddd;">Register</a> 
             <a href="logout.php" style="color: #dddddd;">Logout</a> -->
        </div>
        <div style="text-align:right; position: absolute; top:80px;right:2px;">

<!--	<form method="get" id="searchform" action="">

	<table style='background-color:#cccccc;'>
		<tr>
			<td style="background-color: #cccccc;">
			<input type="text" placeholder="Search TNBC Workbench"
				value=""
				name="Name"
				id="s"
				size="24"
				title="Search TNBC Workbench"
				style="font-style: italic;"/>

		</td><td style="background-color: #cccccc; width: 12px; height: 18px;">
			<input
				style="
					background: url(/images/mag_glass_icon.png) no-repeat center;
					padding: 3px;
					padding-bottom: 4px;
					border-width:0px;
					border-color: #AAA9BB;
					border-radius: 6px;
					width: 20px;
					"
				 type="submit" id="go" title="Search" value="">
		</td>
		</tr>
		</table>

	</form>-->
</div>
<ul id="header-nav" class="topnav" style="position: absolute; width:100%; bottom:-1px;">	
	<li class="top-level dropdown">
        <?php
          $myurl = $_SERVER["SCRIPT_NAME"];
          $word="metGene.php";
          if(strpos($myurl, $word) !== false){
          $homeLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/metGene.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtnlit\">Home</a>";
          } else {
          $homeLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/metGene.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtn\">Home</a>";
	  }
          echo $homeLinkStr; ?>
			<!-- end class subnav -->
		</li>  
  
 <li class="dropdown"> 
        <?php
          $myurl = $_SERVER["SCRIPT_NAME"];
          $word="geneInfo.php";
          if(strpos($myurl, $word) !== false){
         $geneLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/geneInfo.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class=\"dropbtnlit\">Genes</a>";
          } else {
         $geneLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/geneInfo.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class=\"dropbtn\">Genes</a>";
          }
         echo $geneLinkStr; ?>
	<div class="dropdown-content">
				</div></li>
				
<li class="dropdown"> 
        <?php
          $myurl = $_SERVER["SCRIPT_NAME"];
          $word="pathways.php";
          if(strpos($myurl, $word) !== false){
          $pathwaysLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/pathways.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtnlit\">Pathways</a>";
          } else {
          $pathwaysLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/pathways.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtn\">Pathways</a>";
          }
         echo $pathwaysLinkStr; ?>

        <div class="dropdown-content">
</li>

<li class="dropdown"> 
        <?php
          $myurl = $_SERVER["SCRIPT_NAME"];
          $word="reactions.php";
          if(strpos($myurl, $word) !== false){
          $reactionsLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/reactions.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtnlit\">Reactions</a>";
         } else {
          $reactionsLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/reactions.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtn\">Reactions</a>";
         }
         echo $reactionsLinkStr; ?>   

	<div class="dropdown-content">
</li>
				
<li class="dropdown"> 
        <?php
          $myurl = $_SERVER["SCRIPT_NAME"];
          $word="metabolites.php";
          if(strpos($myurl, $word) !== false){
          $metsLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/metabolites.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtnlit\">Metabolites</a>";
          } else {
          $metsLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/metabolites.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtn\">Metabolites</a>";
          }
          echo $metsLinkStr; ?>

	<div class="dropdown-content">
</li>   
<li class="dropdown"> 
        <?php
          $myurl = $_SERVER["SCRIPT_NAME"];
          $word="studies.php";
          if(strpos($myurl, $word) !== false){
             $stuLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/studies.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtnlit\">Studies</a>";
          } else {
             $stuLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/studies.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtn\">Studies</a>";
          }
          echo $stuLinkStr; ?>


</li>		
<li class="dropdown"> 
        <?php
          $myurl = $_SERVER["SCRIPT_NAME"];
          $word="summary.php";
          if(strpos($myurl, $word) !== false){
          $reactionsLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/summary.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtnlit\">Summary</a>";
         } else {
          $reactionsLinkStr = "<a href=\"".$METGENE_BASE_DIR_NAME."/summary.php?species=".$species."&GeneIDType=".$geneIDType."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."&GeneInfoStr=".$geneList."\" class =\"dropbtn\">Summary</a>";
         }
         echo $reactionsLinkStr; ?>   

	<div class="dropdown-content">
</li>

				

 </ul>
 <span class="cleardiv"><!-- --></span>
					</div>
				</div>
			</div>
		</div>
		</body>
		</html>


