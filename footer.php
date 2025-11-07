  <!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<?php
$species=(isset($_SESSION['species']))?$_SESSION['species']:$_GET["species"];
$geneList=(isset($_SESSION['geneList']))?$_SESSION['geneList']:$_GET["GeneInfoStr"];
$geneIDType = (isset($_SESSION['geneIDType']))?$_SESSION['geneIDType']:$_GET["GeneIDType"];
$geneID = (isset($_SESSION['geneID']))?$_SESSION['geneID']:$_GET["GeneID"];
$disease=(isset($_SESSION['disease']))?$_SESSION['disease']:$_GET["disease"];
$anatomy=(isset($_SESSION['anatomy']))?$_SESSION['anatomy']:$_GET["anatomy"];
$phenotype=(isset($_SESSION['phenotype']))?$_SESSION['phenotype']:$_GET["phenotype"];

$termsofusehref = "<li><a href=\"termsofuse.php?GeneInfoStr=".$geneList."&species=".$species."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">Terms of use</a></li>";
$contacthref = "<li><a href=\"contact.php?GeneInfoStr=".$geneList."&species=".$species."&disease=".$disease."&anatomy=".$anatomy."&phenotype=".$phenotype."\">Contact</a></li>";
?>
<div id="constrain">
<div class ="constrain">
<div id="ftr" class="footer">
	<ul class="journal-details">
	  <li class="last">
	    <?php
        $curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));
        $METGENE_BASE_DIR_NAME = $curDirPath;
        $servername = htmlentities($_SERVER['SERVER_NAME']);

       //$linkStr = "<a href=\"https://bdcw.org/MetGENE/index.php?species=".$species."&GeneIDType=".$geneIDType."&GeneInfoStr=".$geneList."&anatomy=".$anatomy."&disease=".$disease."&phenotype=".$phenotype."\">";
           $linkStr = "<a href=\"https://".$servername.$METGENE_BASE_DIR_NAME."/index.php\">";
           echo $linkStr;
        ?>
       <img src="/MetGENE/images/Reset_Btn.png" alt="logo" width="125">
        </a></li>
	</ul>
	<ul class="footer-links">
<?php
        echo $termsofusehref;
        echo $contacthref;
?>
		
	<!--	</ul> -->
	<!--	</li> -->
	</ul>
	<span class="cleardiv"><!-- --></span>
	</div>
	<table>
	<tr>
	<td>&nbsp;</td>
	<td style="vertical-align:middle; width:60px;">
	<a href="https://www.ucsd.edu//" target="_blank">
        <img src="/MetGENE/images/ucsd_logo.png" alt="logo" width="80">
        </a>
	</td>
	<td style="vertical-align:middle; width:60px;">
	<a href="https://commonfund.nih.gov/dataecosystem" target="_blank">
        <img src="/MetGENE/images/CFDEtransparent.png" alt="logo" width="80">
        </a>
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


</html>
