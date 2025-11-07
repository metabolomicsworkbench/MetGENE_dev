<?php
  $species = $_GET["species"];
  $geneSym = $_GET["GeneSym"];
  $ENSEMBL =  $_GET["ENSEMBL"];
  $geneSymArr = explode("__", $geneSym);
  $geneDisp = implode(", ", $geneSymArr);
  $geneID = $_GET["GeneID"];
  $viewType = $_GET["viewType"];
  if (strcmp($viewType,"") == 0) {
    $viewType = "html";
  }
  $prefix = "cache/plot";
  $suffix = ".png";
  $filename = $prefix.rand(1,1000).$suffix;

  ## 8/8/2022: Adding ENSEMBL id for Content generation for the portal Markdown CFDE
  if (strcmp($geneSym,"") == 0 || strcmp($geneID,"") == 0) {
      ## Assume user is specifying ENSEMBL id
      if (strcmp($ENSEMBL,"") == 0) {
          echo("Either Gene Symbol and Gene ID (ENTREZ) should be specified  or ENSEMBL ID should be specified"); 
      } else {
          ## call GEne ID conversion tool to get Gene Symbol and Gene ID
          //          echo "Proecessing ENSEMBL ... ";
          $geneIDType = "ENSEMBL";
          $geneList = $ENSEMBL;
          //echo "geneList =".$geneList," ";
          //echo "geneIDType =".$geneIDType." ";
          //echo "species = ".$species." ";
          $domainName = $_SERVER['SERVER_NAME'];
          //echo "Domain Name = ".$domainName." ";
          exec("/usr/bin/Rscript extractGeneIDsAndSymbols.R $species $geneList $geneIDType $domainName", $symbol_geneIDs, $retvar);

          foreach ($symbol_geneIDs as $val) {
              $gene_id_symbols_arr = explode(",", $val);
          }

          $geneID = $gene_id_symbols_arr[1];
          //echo "GeneID = ".$geneID." ";
          $geneSym = $gene_id_symbols_arr[0];
          //echo "Gene Symbol = ".$geneSym." ";
          
      }
  }

  exec("/usr/bin/Rscript extractMWGeneSummary.R $species $geneID $geneSym $filename $viewType", $output, $retVar);
  $htmlbuff = implode("\n", $output);

?>
<?php if ( strcmp($viewType, "json") == 0): header('Content-type: application/json; charset=UTF-8'); echo $htmlbuff; ?>
<?php elseif ( strcmp($viewType, "txt") == 0):  header('Content-Type: text/plain; charset=UTF-8'); echo $htmlbuff; ?>
<?php elseif ( strcmp($viewType, "table") == 0):  echo $htmlbuff; ?>
<?php elseif ( strcmp($viewType, "all") == 0):  echo $htmlbuff;echo "</td></tr></table>" ?>
<?php elseif ( strcmp($viewType, "png") == 0):  $fp = fopen($filename, 'rb'); header ('Content-Type: image/png'); header("Content-Length: " . filesize($filename));  fpassthru($fp);?>
<?php else: ?>
<?php 
$curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));                                                     
$METGENE_BASE_DIR_NAME = $curDirPath;
?>

<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head><title>MetGENE: Summary</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-32x32.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-16x16.png\">";
    echo "<link rel=\"manifest\" href=\"".$METGENE_BASE_DIR_NAME."/site.webmanifest\">";

?>


<style>
.styled-table {
    display:table;
    table-layout:fixed;
    width: 100%;
    word-wrap: break-word;

}

.styled-table td {
    border: 1px solid #000;
    padding:5px 10px;
    text-align: center;
    width: 3%;
    word-break: break-all;
    white-space: pre-line;
}
.styled-table tbody tr {
    border-bottom: 1px solid #dddddd;
}

.styled-table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
}
.summary {
    background-color: white;
    width: 75;
  color: black;
  border: 2px solid black;
  margin: 20px;
  padding: 20px;
}
</style>
</head>

<body>
<div id="constrain">
<div class="constrain">
  <table>
    <tr>
      <?php echo "<td><img src =\"".$METGENE_BASE_DIR_NAME."/images/MetGeneLogoNew.png\" width=\"105\" height=\"90\"> </td>";?>
      <td><div class="summary"><p>
<?php
  $gene_len = count($geneSymArr);
  $server_name = htmlentities($_SERVER['SERVER_NAME']);
  $curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));    # does not include the ending /
  $curDir_url =  "https://" . $server_name . "/" . $curDirPath . "/";

  if ($gene_len > 1) {
     $descStr =  "<a href=\"".$currDir_url."index.php?GeneID=".$geneID."&species=".$species."&GeneIDType=ENTREZID\">"."MetGENE</a> identifies the pathways and reactions catalyzed by the given genes ".$geneDisp.", their related metabolites and the studies in <a href=\"http://www.metabolomicsworkbench.org\">Metabolomics Workbench</a> with data on such metabolites.</p></div></td></tr>";
  } else {
    $descStr =  "<a href=\"".$currDir_url."index.php?GeneID=".$geneID."&species=".$species."&GeneIDType=ENTREZID\">"."MetGENE</a> identifies the pathways and reactions catalyzed by the given gene ".$geneDisp.", its related metabolites and the studies in <a href=\"http://www.metabolomicsworkbench.org\">Metabolomics Workbench</a> with data on such metabolites.</p></div></td></tr>";
  }
  echo $descStr;
  $prefix = "cache/plot";
  $suffix = ".png";
  $filename = $prefix.rand(1,1000).$suffix;
  $url_prefix = "<tr><td></td><td><div class=\"summary\">";
  $url_suffix = "</div>";
  echo $url_prefix;
  echo "<pre>";
  echo $htmlbuff;
  echo "</td></tr></table></pre>";
  echo $url_suffix;
  $btnStr = "<p><button id=\"json\">TO JSON</button> <button id=\"csv\">TO CSV</button> </p>";
  echo $btnStr;

?>
    </td>
    </tr>
    </table>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php echo "<script src=\"/".$METGENE_BASE_DIR_NAME."/src/tableHTMLExport.js\"></script>"; ?>

<script>
  $('#json').on('click',function(){
        tabName = "#Table1";
        fname = "Summary.json";
        $(tabName).tableHTMLExport({type:'json',filename:fname});

  })
  $('#csv').on('click',function(){
        tabName = "#Table1";
        fname = "Summary.csv";
        $(tabName).tableHTMLExport({type:'csv',filename:fname});

  })


</script>

</body>

</html>



<?php endif; ?>
