<?php
/**
 * combineStudies.php - Display combined studies for selected metabolites
 * Security hardened using metgene_common.php functions
 */

// SECURITY FIX: Start session and load helpers BEFORE any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . "/metgene_common.php");

// SECURITY FIX: Send security headers
sendSecurityHeaders();

// Define esc() wrapper
if (!function_exists('esc')) {
    function esc(string $v): string {
        return escapeHtml($v);
    }
}

// SECURITY FIX: Use getBaseDirName() function
$METGENE_BASE_DIR_NAME = getBaseDirName();

/**
 * SECURITY FIX: Secure API call function
 */
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

    // SECURITY FIX: Remove authentication (not needed for public API)
    // curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    // curl_setopt($curl, CURLOPT_USERPWD, "username:password");

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    
    // SECURITY FIX: Add timeout and SSL verification
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);

    $result = curl_exec($curl);
    
    // SECURITY FIX: Check for errors
    if ($result === false) {
        error_log("cURL Error: " . curl_error($curl));
        curl_close($curl);
        return false;
    }

    curl_close($curl);

    return $result;
}
?>
<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<head>
<title>MetGENE: Combined Studies</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<?php
    echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"".esc($METGENE_BASE_DIR_NAME)."/images/apple-touch-icon.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"32x32\" href=\"".esc($METGENE_BASE_DIR_NAME)."/images/favicon-32x32.png\">";
    echo "<link rel=\"icon\" type=\"image/png\" sizes=\"16x16\" href=\"".esc($METGENE_BASE_DIR_NAME)."/images/favicon-16x16.png\">";
    echo "<link rel=\"manifest\" href=\"".esc($METGENE_BASE_DIR_NAME)."/site.webmanifest\">";
?>

<?php
// SECURITY FIX: Validate nav.php path with realpath
$nav_file = realpath(__DIR__ . '/nav.php');
if ($nav_file !== false && strpos($nav_file, __DIR__) === 0 && is_readable($nav_file)) {
    include $nav_file;
}
?>

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

table th, td {
    word-wrap:break-word;
    white-space: normal;
    border-bottom: 1px solid #dddddd;
}

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

// SECURITY FIX: Retrieve and validate input
$map1 = safeGet('metabolites', '');

if ($map1 === '') {
    echo "<p>No metabolites selected.</p>";
} else {
    // Decode the JSON string and convert it into a PHP associative array
    $string = explode(',', $map1);

    $compound_arr = [];
    $all_studies_arr = array();

    foreach ($string as &$value) {
        $split_str = explode(':', $value);
        
        // SECURITY FIX: Validate array has expected structure
        if (count($split_str) < 2) {
            continue;
        }
        
        $curr_comp = $split_str[0];
        $curr_comp = str_replace("___", ":", $curr_comp);
        
        // SECURITY FIX: Sanitize compound name - allow only safe characters
        $compName = preg_replace("/[^a-zA-Z0-9\s\-\+\:\_]/", "", $curr_comp);
        
        // if there is a space replace with plus
        $compQry = str_replace("+", "%2b", $compName);
        $compId = str_replace(" ", "+", $compQry);

        // SECURITY FIX: Escape HTML output
        $compURL = "<a href=\"https://www.metabolomicsworkbench.org/databases/refmet/refmet_details.php?REFMET_NAME=".esc($compId)."\">".esc($compName)."</a>";
        array_push($compound_arr, $compURL);

        $studiesStr = $split_str[1];
        $studies = explode(" ", $studiesStr);

        /* Iterate through all elements and save all unique values in the result array */
        for ($i = 0; $i < count($studies); $i++) {
            $curr_study_raw = trim($studies[$i]);
            
            // SECURITY FIX: Skip empty studies
            if ($curr_study_raw === '') {
                continue;
            }
            
            if (!in_array($curr_study_raw, $all_studies_arr)) {
                // SECURITY FIX: Sanitize study ID - only uppercase letters and numbers
                $curr_study = preg_replace("/[^A-Z0-9]/", "", $curr_study_raw);
                
                // SECURITY FIX: Skip if sanitization removed everything
                if ($curr_study === '') {
                    continue;
                }
                
                $mw_rest_url = "https://www.metabolomicsworkbench.org/rest/study/study_id/" . $curr_study . "/summary/";
                $mw_res = callAPI("GET", $mw_rest_url, false);

                // SECURITY FIX: Check if API call succeeded
                if ($mw_res === false) {
                    error_log("Failed to fetch study info for: $curr_study");
                    continue;
                }

                $decoded_json = json_decode($mw_res, true);
                
                // SECURITY FIX: Validate decoded JSON
                if (!is_array($decoded_json) || !isset($decoded_json['study_title'])) {
                    error_log("Invalid JSON response for study: $curr_study");
                    continue;
                }
                
                $curr_study_title = $decoded_json['study_title'];
                
                // SECURITY FIX: Escape all output
                $curr_study_url = "<a href=\"https://www.metabolomicsworkbench.org/data/DRCCMetadata.php?Mode=Study&StudyID=".esc($curr_study)."\" title=\"".esc($curr_study_title)."\" target=\"_blank\">".esc($curr_study)."</a>";
                array_push($all_studies_arr, $curr_study_url);
            }
        }

        /* Unset array */
        unset($studies);
    }
    
    $unique_studies_arr = array_unique($all_studies_arr);

    if (count($compound_arr) > 0 && count($unique_studies_arr) > 0) {
        $compStr = implode(', ', $compound_arr);
        $allStudiesStr = implode(', ', $unique_studies_arr);

        echo "<table id='Table1' class='styled-table'>";
        echo "<tr><th>Metabolites</th><th>Studies</th></tr>";
        echo "<tr>";
        echo "<td>".$compStr."</td><td>".$allStudiesStr."</td>";
        echo "</tr>";
        echo "</table>";
    } else {
        echo "<p>No valid studies found for the selected metabolites.</p>";
    }
}
?>

<p><button id="json">TO JSON</button> <button id="csv">TO CSV</button></p>

</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php echo "<script src=\"".esc($METGENE_BASE_DIR_NAME)."/src/tableHTMLExport.js\"></script>"; ?>
<?php echo "<script src=\"".esc($METGENE_BASE_DIR_NAME)."/js/combineStudies-export.js\"></script>"; ?>

<?php
// SECURITY FIX: Validate footer.php path with realpath
$footer_file = realpath(__DIR__ . '/footer.php');
if ($footer_file !== false && strpos($footer_file, __DIR__) === 0 && is_readable($footer_file)) {
    include $footer_file;
}
?>

</body>
</html>