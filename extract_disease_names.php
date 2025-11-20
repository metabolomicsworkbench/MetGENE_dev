<?php

function getDiseaseNames(string $source): array
{
    // Load JSON (works for local file or URL)
    $json = file_get_contents($source);

    if ($json === false) {
        throw new Exception("Cannot read source: $source");
    }

    $data = json_decode($json, true);

    if (!is_array($data)) {
        throw new Exception("Invalid JSON in: $source");
    }

    $diseaseNames = [];

    foreach ($data as $outer) {
        foreach ($outer as $inner) {
            if (isset($inner['disease_name'])) {
                $diseaseNames[] = $inner['disease_name'];
            }
        }
    }

    return $diseaseNames;
}

// ---- Example usage ----

// If using in another script, add the line:
// include_once 'extract_disease_names.php';

// Local file:
//$names = getDiseaseNames('disease_pulldown_menu_cascaded.json');

// Remote:
//$names = getDiseaseNames(
//    'https://raw.githubusercontent.com/metabolomicsworkbench/MetGENE/main/disease_pulldown_menu_cascaded.json'
//);

//print_r($names);

?>

