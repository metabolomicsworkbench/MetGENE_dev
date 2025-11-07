<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<?php
  session_start();
  $species=(isset($_SESSION['species']))?$_SESSION['species']:'';
  $geneList=(isset($_SESSION['geneList']))?$_SESSION['geneList']:'';
  $disease=(isset($_SESSION['disease']))?$_SESSION['disease']:'';
  $anatomy=(isset($_SESSION['anatomy']))?$_SESSION['anatomy']:'';
  $phenotype=(isset($_SESSION['phenotype']))?$_SESSION['phenotype']:'';
?>
<head><title>Terms of Use</title>		
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<link rel="apple-touch-icon" sizes="180x180" href="/MetGENE/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/MetGENE/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/MetGENE/favicon-16x16.png">
<link rel="manifest" href="/MetGENE/site.webmanifest">
<?php include($_SERVER['DOCUMENT_ROOT'] . "/MetGENE/nav.php");?>
</head>
<body>


<div id="constrain">
<div class="constrain">
<br>
<br>

<h1>Terms of Use</h1>
<p style="margin:25px;">
MetGENE tool is provided by the Metabolomics Workbench Data Coordination Center (DCC) of the NIH Common Fund Data Ecosystem (CFDE) on an "as is" basis, without warranty or representation of any kind, express or implied. The content of the conversion tool is protected by international copyright, trademark and other laws. You may download outputs (such as tables and web pages) from this site for your personal, non-commercial use only, provided that you keep intact all authorship, copyright and other proprietary notices. If you use this tool, you accept these terms. MW DCC reserves the right to modify these terms at any time. 
  </p>
</div>
</div>



<?php include($_SERVER['DOCUMENT_ROOT'] . "/MetGENE/footer.php");?>
<body>
</html>
