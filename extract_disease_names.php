<?php

// Load JSON from file (local or remote)
$json = file_get_contents('disease_pulldown_menu_cascaded.json');
$data = json_decode($json, true);

$diseaseNames = [];

foreach ($data as $outer) {                // 1st level
    foreach ($outer as $inner) {           // 2nd level
        if (isset($inner['disease_name'])) {
            $diseaseNames[] = $inner['disease_name'];
        }
    }
}

print_r($diseaseNames);

?>

