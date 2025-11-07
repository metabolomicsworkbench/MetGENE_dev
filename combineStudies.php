<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head><title>MetGENE: Combined Studies</title>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
  $curDirPath = dirname(htmlentities($_SERVER['PHP_SELF']));
  $METGENE_BASE_DIR_NAME = $curDirPath;

    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".$METGENE_BASE_DIR_NAME."/images/apple-touch-icon.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-32x32.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"".$METGENE_BASE_DIR_NAME."/images/favicon-16x16.png\">";
    echo "<link rel=\"manifest\" href=\"".$METGENE_BASE_DIR_NAME."/site.webmanifest\">";

?>
<?php     include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/nav.php");?>



</head>
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

table th, td {word-wrap:break-word;white-space: pre-line;border-bottom: 1px solid #dddddd;}
table tbody tr:nth-of-type(even) {
    background-color: #f3f3f3;
}


</style>
<body>


<div id="constrain">
<div class="constrain">
<h3>Combined studies for the selected metabolites</h3>
<br>
<?php
function CallAPI($method, $url, $data = false)
{
    $curl = curl_init();

    switch ($method)
    {
        case "POST":
            curl_setopt($curl, CURLOPT_POST, 1);

            if ($data)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            break;
        case "PUT":
            curl_setopt($curl, CURLOPT_PUT, 1);
            break;
        default:
            if ($data)
                $url = sprintf("%s?%s", $url, http_build_query($data));
    }

    // Optional Authentication:
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_USERPWD, "username:password");

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

    curl_close($curl);

    return $result;
}

//Retrieve the string, which was sent via the POST parameter "user" 
$map1 = isset($_GET['metabolites']) ? $_GET['metabolites'] : '';


//Decode the JSON string and convert it into a PHP associative array.
$string = explode(',', $map1);

$compound_arr = [];
$all_studies_arr = array();

foreach ($string as &$value) {
 $split_str = explode(':',$value);
  $curr_comp = $split_str[0];
  $curr_comp = str_replace("___", ":",$curr_comp);
//  echo $curr_comp;
  // remove all characters except alphanumeric and + and -
  $compName = preg_replace("/[^a-zA-Z0-9\s\-\+\:\_]/", "", $curr_comp);
//  $compQry = encodeURIComponent($compName);  
// if there is a space replace with plus
  $compQry = str_replace("+","%2b",$compName);
  $compId = str_replace(" ","+",$compQry);
  
//  echo $compName." ";
//  echo "<br>";
// convert the string to URL

  $compURL = "<a href=\"https://www.metabolomicsworkbench.org/databases/refmet/refmet_details.php?REFMET_NAME=".$compId."\">".$compName."</a>";
//  echo $compURL;
//  echo "<br>";
//  array_push($compound_arr, $compId);
    array_push($compound_arr, $compURL);


 $studiesStr = $split_str[1];
  $studies = explode(" ", $studiesStr);


/* Iterate through all elements and save all unique values in the result array */
  for ($i = 0; $i < count($studies); $i++) {
      if (!in_array($studies[$i], $all_studies_arr)) {
          //          $curr_study = preg_replace("/[^a-zA-Z0-9]+/", "", $studies[$i]);
          $curr_study = preg_replace("/[^A-Z0-9]/", "", $studies[$i]);
	  $mw_rest_url = "https://www.metabolomicsworkbench.org/rest/study/study_id/".$curr_study."/summary/";
          $mw_res = callAPI("GET", $mw_rest_url, true);

          $decoded_json = json_decode($mw_res, true);
          $curr_study_title = $decoded_json['study_title'];
          $curr_study_url = "<a href=\"https://www.metabolomicsworkbench.org/data/DRCCMetadata.php?Mode=Study&StudyID=".$curr_study."\" title=\"".$curr_study_title."\" target=\"_blank\">".$curr_study."</a>";
          array_push($all_studies_arr, $curr_study_url);

      }
  }

/* Unset array */
  unset($studies);

}
$unique_studies_arr = array_unique($all_studies_arr);
//unset($unique_studies_arr[0]);

//print_r($compound_arr);
//print_r($unique_studies_arr);
$compStr = implode(',', $compound_arr);

$allStudiesStr = implode(', ',$unique_studies_arr);

//echo $compStr;
echo "<table id='Table1' class='styled-table'>";
echo "<tr>";
echo "<td>".$compStr."</td>"."<td>".$allStudiesStr."</td>";

echo "</tr>";
echo "</table>"; 


?>
<p><button id="json">TO JSON</button> <button id="csv">TO CSV</button> </p>

  
</div>

</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php echo "<script src=\"".$METGENE_BASE_DIR_NAME."/src/tableHTMLExport.js\"></script>"; ?>
<script>
  $('#json').on('click',function(){
    $("#Table1").tableHTMLExport({type:'json',filename:'combinedStudies.json'});
  })
  $('#csv').on('click',function(){
    $("#Table1").tableHTMLExport({type:'csv',filename:'combinedStudies.csv'});
  })
  </script>

<?php include($_SERVER['DOCUMENT_ROOT'].$METGENE_BASE_DIR_NAME."/footer.php");?>
</body>
</html>
