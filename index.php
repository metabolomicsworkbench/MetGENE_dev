<!DOCTYPE HTML>
<html>
<style type='text/css' media='screen, projection, print'>
.btn
{
    background-color: rgb(7, 55, 99);
    color: white;
    border: none;
    cursor: pointer;
    padding: 2px 12px 3px 12px;
    text-decoration: none;

}
</style>
<head><title>MetGENE: Query</title>
<?php
    $curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));
    $METGENE_BASE_DIR_NAME = $curDirPath;
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-32x32.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-16x16.png\">";
    echo "<link rel=\"manifest\" href=\"".$METGENE_BASE_DIR_NAME."/site.webmanifest\">";
    include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/nav_index.php");
?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://requirejs.org/docs/release/2.3.5/minified/require.js"></script>

</head>
<body>

<div id="constrain">
<div class="constrain">

<p> <span style="font-style: italic; text-align: left; color: #6A2F47;">
						&nbsp;Welcome to the MetGENE Tool
					</span></p>
<p>


<p style = "font-family:georgia,garamond,serif;font-size:12px;font-style:italic;">Given one or more genes, the MetGENE tool identifies associations between the gene(s) and the metabolites that are biosynthesized, metabolized, or transported by proteins coded by the genes. The gene(s) link to metabolites, the chemical transformations involving the metabolites through gene-specified proteins/enzymes, the functional association of these gene-associated metabolites and the pathways involving these metabolites.</p>

<p style = "font-family:georgia,garamond,serif;font-size:12px;font-style:italic;">The user can specify the gene using a multiplicity of IDs and gene ID conversion tool translates these into harmonized IDs that are basis at the computational end for metabolite associations. Further, all studies involving the metabolites associated with the gene-coded proteins, as present in the Metabolomics Workbench (MW), the portal for the NIH Common Fund National Metabolomics Data Repository (NMDR), will be accessible to the user through the portal interface. The user can begin her/his journey from the NIH Common Fund Data Ecosystem (CFDE) portal. A tutorial for MetGENE is available <b><a href="/MW/docs/MetGENETutorial.pdf" target="_blank">here</a></b>.</p>
<?php
  $species = $_GET["species"];
  $geneIDType = $_GET["GeneIDType"];
  $geneID = $_GET["GeneID"];
  $geneList = $_GET["GeneInfoStr"];
  $anatomy = $_GET["anatomy"];
  $disease = $_GET["disease"];
  $phenotype = $_GET["phenotype"];

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
  if (strcmp($geneID, "") == 0) {
    $genes = explode("__", $geneList);
    $dispStr = implode(",", $genes);
    $geneIDtxtField = "<input type=\"text\" name=\"GeneInfoStr\" value=\"".$dispStr."\">";
  } else {
    $genes = explode("__", $geneID);
    $dispStr = implode(",", $genes);
    $geneIDtxtField = "<input type=\"text\" name=\"GeneInfoStr\" value=\"".$dispStr."\">";
  }


?>

<?php echo "<form action=\"".$METGENE_BASE_DIR_NAME."/metGene.php\" method=\"get\" id=\"metGene\">";?>
  <p>Use separator "," for multiple gene symbols or IDs".</p>
  <table><tr>
   <td>Gene_ID: <?php echo $geneIDtxtField; ?></td>
   <td>Gene-ID Type: <select name="GeneIDType">
<!--  <option value="">Select...</option> -->
  <?php if ($geneIDType == "SYMBOL") { $optStr = "<option value=\"SYMBOL\" selected>SYMBOL</option>";} else {$optStr = "<option value=\"SYMBOL\">SYMBOL</option>";} echo $optStr;?>
  <?php if ($geneIDType == "SYMBOL_OR_ALIAS") { $optStr = "<option value=\"SYMBOL_OR_ALIAS\" selected>SYMBOL_OR_ALIAS</option>";} else {$optStr = "<option value=\"SYMBOL_OR_ALIAS\">SYMBOL_OR_ALIAS</option>";} echo $optStr;?>
  <?php if ($geneIDType == "ENTREZID") { $optStr = "<option value=\"ENTREZID\" selected>ENTREZID</option>";} else {$optStr = "<option value=\"ENTREZID\">ENTREZID</option>";} echo $optStr;?>
  <?php if ($geneIDType == "ENSEMBL") { $optStr = "<option value=\"ENSEMBL\" selected>ENSEMBL</option>";} else {$optStr = "<option value=\"ENSEMBL\">ENSEMBL</option>";} echo $optStr;?>
  <?php if ($geneIDType == "REFSEQ") { $optStr = "<option value=\"REFSEQ\" selected>REFSEQ</option>";} else {$optStr = "<option value=\"REFSEQ\">REFSEQ</option>";} echo $optStr;?>
  <?php if ($geneIDType == "UNIPROT") { $optStr = "<option value=\"UNIPROT\" selected>UNIPROT</option>";} else {$optStr = "<option value=\"UNIPROT\">UNIPROT</option>";} echo $optStr;?>
</select></td></tr>
</table>
<p>Filter by:</p>
  <table>
<tr>
  <?php echo "<td><center><img src =\"".$METGENE_BASE_DIR_NAME."/images/organisms.png\" width=\"100\"></center></td>";?>
  <?php echo "<td><center><img src =\"".$METGENE_BASE_DIR_NAME."/images/anatomy.png\" width=\"65\"></center></td>";?>
  <?php echo "<td><center><img src =\"".$METGENE_BASE_DIR_NAME."/images/disease.png\" width=\"60\"></center></td>";?>
<!--  <td><center><img src ="/MetGENE/images/phenotype.png" width="70"></center></td> -->
</tr>
<tr>
<td><center><select name = "species">
    <?php if ($species == "hsa") { $optStr = "<option value = \"hsa\" selected>Human</option>";} else {$optStr = "<option value = \"hsa\">Human</option>";} echo $optStr;?>
    <?php if ($species == "mmu") { $optStr = "<option value = \"mmu\" selected>Mouse</option>";} else {$optStr = "<option value = \"mmu\">Mouse</option>";} echo $optStr;?>
    <?php if ($species == "rno") { $optStr = "<option value = \"rno\" selected>Rat</option>";} else {$optStr = "<option value = \"rno\">Rat</option>";} echo $optStr;?>
</select></center></td>

<td><center><select name = "anatomy">
            <option value = "NA" selected>Select anatomy/Sample source</option>
<?php 
// include 'sample_source_pulldown_menu_phpcode.php' 
include 'ssdm_sample_source_pulldown_menu_phpcode.php' 
?>
</select></center></td>

<td><center><select name="disease_slim" id="disease_slim">
    <option value="" selected="selected">Select disease/phenotype category</option>
  </select>
  <br>
<select name="disease" id="disease">
    <option value="NA" selected="selected">Select disease/phenotype</option>
</select></center></td>

<!-- <td><center><select name = "phenotype"> -->
<!--            <option value = "NA" selected>Select phenotype</option> -->
<!--            <option value = "BMI">BMI</option>-->
<!-- </select></center></td> -->
</tr>
</table>
<br>
<br>

<span class="btn" id="submit_form">Submit</span>


</form>
<p><br><br> <i>Please address questions/issues/bugs regarding MetGENE to <a href="mailto: susrinivasan@ucsd.edu">susrinivasan[AT]ucsd[dot]edu</a>, <a href="mailto: mano@sdsc.edu">mano[AT]sdsc[dot]edu</a></i></p>

<span id="display"></span>



</p>
<div id="ftr" class="footer">
	<ul class="footer-links">
    <li><a href=termsofuse.php>Terms of use</a></li>
    <li><a href="contact.php">Contact</a></li>
	</ul>
	<span class="cleardiv"><!-- --></span>
	</div>
	<table>
	<tr>
	<td>&nbsp;</td>
	<td style="vertical-align:middle; width:60px;">
	<?php echo "<a href=\"https://www.ucsd.edu\" target=\"_blank\"><img src=\"".$METGENE_BASE_DIR_NAME."/images/ucsd_logo.png\" alt=\"logo\" width=\"80\"></a>";?>
    </td>
    <td style="vertical-align:middle; width:60px;">
    <?php echo "<a href=\"https://commonfund.nih.gov/dataecosystem\" target=\"_blank\"><img src=\"".$METGENE_BASE_DIR_NAME."/images/CFDEtransparent.png\" alt=\"logo\" width=\"80\"></a>";?>
	</td>
	</tr>
	</table>


<table style="width: 100%; border-collapse: collapse;">
  <tr>
    <td style="background-color: lightblue; color: black; padding: 10px; text-align: center; border: 1px solid black;">
      This repository is under review for potential modification in compliance with Administration directives.
    </td>
  </tr>
</table>


</div>
</div>


<script>
   $(document).ready(function(){
     // Initialize submitted with false
       var submitted = false;

//       alert("submitted = false");
     // Onclick Submit span.
     $("#submit_form").click(function(){
       $("#display").html("Processing....");
	 // Form is submitted.

         if(!submitted){
//           alert("Form will be submitted");
           // Submitting the form using its id.
             var invalid = false;
	     oFormObject = document.forms["metGene"];
             geneStr = oFormObject.elements["GeneInfoStr"].value.replace(/\s/g, "");
	     const genes = geneStr.split(",");
             const lettersnumerals = /^[0-9a-zA-Z]+$/;

             for (var i = 0; i < genes.length; i++) {
                 g = genes[i];
		 if (!g.match(lettersnumerals)) {
		     alert("Gene "+g+" is not a valid name");
                     invalid = true;
		 }
	     }
	     if (!invalid) {
	         const result = genes.join("__");
                 oFormObject.elements["GeneInfoStr"].value = result;
		 $("#metGene").submit();
	     } else {
		 returnToPreviousPage();
	     }
         }
         // On first click submitted will be change to true.
         // On the next click the submitted variable will be as true.
         // So the above if condition will not be executed after the first time.
         if(!submitted)
           submitted= true;
         });
   });

</script>



<script>
  // Replace ./data.json with your JSON feed
  var diseaseObject;

/*  var diseaseObject = {
      "urinary system disease": {
      "Kidney disease":[],
      "Interstitial cystitis":[],
      "Pediatric nephrotic syndrome":[]
      },
      "musculoskeletal system disease": {
      "Osteoarthritis":[],
      "Muscular dystrophy":[]
      }
  }
*/
  window.onload = function() {
      var diseaseSlimSel = document.getElementById("disease_slim");
      var diseaseSel = document.getElementById("disease");
      var diseaseObject;
      //fetch('/dev2/geneid/disease_pulldown_menu_cascaded.json').then(response => {
      fetch('disease_pulldown_menu_cascaded.json').then(response => {
	  return response.json();
      }).then(data => {
      // Work with JSON data here
	  diseaseObject = data;
	  for (var x in diseaseObject) {
   //           console.log(x);
	      diseaseSlimSel.options[diseaseSlimSel.options.length] = new Option(x, x);
	  }
	  diseaseSlimSel.onchange = function() {
	      //empty  disease- dropdowns
	      diseaseSel.length = 1;
	      slim_name = diseaseSlimSel.value;
	      //	console.log(slim_name);
	      //display correct values
	      for (var y in diseaseObject[this.value]) {
		  diseaseNames = diseaseObject[slim_name][y]['disease_name'];
		  //          console.log(diseaseNames);
		  diseaseSel.options[diseaseSel.options.length] = new Option(diseaseNames, diseaseNames);
	      }
	  }

      }).catch(err => {
      // Do something for an error here
      });
  }


</script>


</body>

</html>
