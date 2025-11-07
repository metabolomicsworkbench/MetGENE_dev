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
<head><title>Contact</title>		
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

<h1>Contact</h1>
<p style="margin:25px;">
<strong>MetGENE - Gene-centric Information Retrieval Tool</strong>
<br>c/o Dr. Shankar Subramaniam, Ph. D.
<br>9500 Gilman Drive
<br>La Jolla, CA 92093
<br>Phone: 858-822-0986
<br>E-mail: <a href="mailto: shankar@ucsd.edu">shankar@ucsd.edu</a>
<br><br> <i>Please address questions about MetGENE to <a href="mailto: susrinivasan@ucsd.edu">susrinivasan@ucsd.edu</a>, <a href="mailto: mano@sdsc.edu">mano@sdsc.edu</a></i>
</p>


</div>
</div>



<?php include($_SERVER['DOCUMENT_ROOT'] . "/MetGENE/footer.php");?>
<body>
</html>
