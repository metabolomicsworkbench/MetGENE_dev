<!DOCTYPE html>
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>
<?php
  // Start session securely
  if (session_status() === PHP_SESSION_NONE) {
      session_start([
          'cookie_httponly' => true,
          'cookie_samesite' => 'Strict',
          'use_strict_mode' => true
      ]);
  }

  // Load shared helpers
  require_once(__DIR__ . "/metgene_common.php");
  
  // Send security headers
  sendSecurityHeaders();

  // Define esc() wrapper
  if (!function_exists('esc')) {
      function esc(string $v): string {
          return escapeHtml($v);
      }
  }

  // Use secure base directory function
  $METGENE_BASE_DIR_NAME = getBaseDirName();

  // ---- VALIDATE AND NORMALIZE ALL INPUTS ----
  
  // Species validation
  $speciesRaw = safeGet("species", safeSession('species', ''));
  list($speciesCanonical, $speciesLabel, $speciesScientific) = normalizeSpecies($speciesRaw);
  $_SESSION['species']      = $speciesCanonical;
  $_SESSION['org_name']     = $speciesLabel;
  $_SESSION['species_name'] = $speciesScientific;

  // Gene list validation
  $geneListRaw = safeGet("GeneInfoStr", safeSession('geneList', ''));
  $geneListRaw = str_replace(' ', '', $geneListRaw);
  $geneListArrClean = cleanGeneList($geneListRaw);
  $geneListClean = implode("__", $geneListArrClean);
  $_SESSION['geneList'] = $geneListClean;

  // Gene ID type validation
  $geneIDTypeRaw = safeGet("GeneIDType", safeSession('geneIDType', ''));
  $geneIDTypeValidated = validateGeneIDType($geneIDTypeRaw);
  $_SESSION['geneIDType'] = $geneIDTypeValidated;

  // Disease validation
  $diseaseRaw = safeGet("disease", safeSession('disease', ''));
  list($diseaseMap, $allowedDiseases) = loadDiseaseSlimMap(__DIR__ . "/Disease_Slim.json");
  $diseaseValidated = validateDiseaseValue($diseaseRaw, $allowedDiseases);
  $_SESSION['disease'] = $diseaseValidated;

  // Anatomy validation
  $anatomyRaw = safeGet("anatomy", safeSession('anatomy', ''));
  $allowedAnatomy = loadAnatomyValuesFromHtml(__DIR__ . "/anatomy_list.html");
  $anatomyValidated = validateAnatomyValue($anatomyRaw, $allowedAnatomy);
  $_SESSION['anatomy'] = $anatomyValidated;

  // Phenotype validation (basic sanitization)
  $_SESSION['phenotype'] = safeGet("phenotype", safeSession('phenotype', ''));

  // Load validated session variables
  $species    = $_SESSION['species'];
  $geneList   = $_SESSION['geneList'];
  $geneIDType = $_SESSION['geneIDType'];
  $disease    = $_SESSION['disease'];
  $anatomy    = $_SESSION['anatomy'];
  $phenotype  = $_SESSION['phenotype'];

  $phenotype_array = explode("__", $phenotype);
  $disease_array   = explode("__", $disease);
  $anatomy_array   = explode("__", $anatomy);

  // Initialize previous session state
  $_SESSION['prev_nav_species']    = safeSession('prev_nav_species', '');
  $_SESSION['prev_nav_geneList']   = safeSession('prev_nav_geneList', '');
  $_SESSION['prev_nav_geneIDType'] = safeSession('prev_nav_geneIDType', '');
  $_SESSION['prev_nav_anatomy']    = safeSession('prev_nav_anatomy', '');
  $_SESSION['prev_nav_disease']    = safeSession('prev_nav_disease', '');
  $_SESSION['prev_nav_pheno']      = safeSession('prev_nav_pheno', '');

  // Detect if navigation state changed
  $nav_changed = false;
  if (strcmp($_SESSION['prev_nav_species'], $species) != 0) {
      $_SESSION['prev_nav_species'] = $species;
      $nav_changed = true;
  } else if (strcmp($_SESSION['prev_nav_geneList'], $geneList) != 0) {
      $_SESSION['prev_nav_geneList'] = $geneList;
      $nav_changed = true;
  } else if (strcmp($_SESSION['prev_nav_geneIDType'], $geneIDType) != 0) {
      $_SESSION['prev_nav_geneIDType'] = $geneIDType;
      $nav_changed = true;
  } else if (strcmp($_SESSION['prev_nav_disease'], $disease) != 0) {
      $_SESSION['prev_nav_disease'] = $disease;
      $nav_changed = true;
  } else if (strcmp($_SESSION['prev_nav_anatomy'], $anatomy) != 0) {
      $_SESSION['prev_nav_anatomy'] = $anatomy;
      $nav_changed = true;
  } else if (strcmp($_SESSION['prev_nav_pheno'], $phenotype) != 0) {
      $_SESSION['prev_nav_pheno'] = $phenotype;
      $nav_changed = true;
  }

  $_SESSION['nav_changed'] = $nav_changed ? 1 : 0;

  // CRITICAL: Execute R script only if navigation changed
  if ($nav_changed) {
      // Regenerate session ID for security
      session_regenerate_id(true);
      
      // Validate R script path
      $scriptPath = realpath(__DIR__ . "/extractGeneIDsAndSymbols.R");
      if ($scriptPath === false || !is_readable($scriptPath)) {
          error_log("R script not found: extractGeneIDsAndSymbols.R");
          $_SESSION['geneArray'] = [];
          $_SESSION['geneSymbols'] = '';
          $_SESSION['geneListArr'] = [];
      } else {
          $domainName = $_SERVER['SERVER_NAME'] ?? 'localhost';
          
          $cmd = "/usr/bin/Rscript "
               . escapeshellarg($scriptPath) . " "
               . escapeshellarg($species) . " "
               . escapeshellarg($geneList) . " "
               . escapeshellarg($geneIDType) . " "
               . escapeshellarg($domainName);
          
          $symbol_geneIDs = [];
          $retvar = 0;
          exec($cmd, $symbol_geneIDs, $retvar);
          
          // Check for execution errors
          if ($retvar !== 0) {
              error_log("R script failed with exit code: $retvar");
              $_SESSION['geneArray'] = [];
              $_SESSION['geneSymbols'] = '';
              $_SESSION['geneListArr'] = [];
          } else {
              $gene_symbols = [];
              $gene_array = [];
              $gene_id_symbols_arr = [];

              // Validate output structure
              if (!empty($symbol_geneIDs)) {
                  foreach ($symbol_geneIDs as $val) {
                      $parts = explode(",", $val);
                      $gene_id_symbols_arr = array_merge($gene_id_symbols_arr, $parts);
                  }

                  $length = count($gene_id_symbols_arr);
                  
                  for ($i = 0; $i < $length; $i++) {
                      $trimmed_str = trim($gene_id_symbols_arr[$i], "\" ");
                      
                      if ($i < $length / 2) {
                          $gene_symbols[] = $trimmed_str;
                      } else {
                          $gene_array[] = $trimmed_str;
                      }
                  }
              }

              $_SESSION['geneArray']   = $gene_array;
              $_SESSION['geneSymbols'] = implode(",", $gene_symbols);
              $_SESSION['geneListArr'] = explode("__", $geneList);
          }
      }
  }

  // Store arrays in session
  $_SESSION['diseaseArray']   = $disease_array;
  $_SESSION['phenotypeArray'] = $phenotype_array;
  $_SESSION['anatomyArray']   = $anatomy_array;

  // Helper function to build secure navigation URLs
  function buildNavUrl(string $baseDir, string $page, array $params): string {
      $url = rtrim($baseDir, '/') . '/' . ltrim($page, '/');
      $queryParts = [];
      
      foreach ($params as $key => $value) {
          $queryParts[] = urlencode($key) . '=' . urlencode($value);
      }
      
      if (!empty($queryParts)) {
          $url .= '?' . implode('&', $queryParts);
      }
      
      return $url;
  }

  // Build navigation parameters array
  $navParams = [
      'species'      => $species,
      'GeneIDType'   => $geneIDType,
      'anatomy'      => $anatomy,
      'disease'      => $disease,
      'phenotype'    => $phenotype,
      'GeneInfoStr'  => $geneList
  ];
?>

<style type='text/css' media='screen, projection, print'>
@import "css/header.css";
@import "css/layout.css";
@import "css/style_layout.css";
@import "css/main.css";
@import "css/site.css";
@import "css/layout_2col.css";
@import "css/966px.css";

#hdr .login-nav {
    background-color: #ffffff;
    background-image: url('<?php echo esc($METGENE_BASE_DIR_NAME); ?>/images/MetGeneBanner.png');
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

.topnav li a, .dropbtn, .dropbtnlit {
    display: inline;
    color: #215EA9;
    text-align: center;
    text-decoration: none;
}

.dropdown {
    float: left;
    overflow: hidden;
}

.dropdown .dropbtn {
    font-size: 16px;  
    border: none;
    color: #215EA9;
    padding: 8px 12px;  
    background-color: #eeeeee;
    margin-right: 2px;
    font-weight: normal;
}

.dropdown .dropbtnlit {
    font-size: 16px;  
    border: none;
    color: #215EA9;
    padding: 8px 12px;  
    background-color: #edd8c5;
    margin-right: 2px;
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

.dropdown:hover .dropdown-content {
    display: block;
}

.styled-table {
    display: table;
    table-layout: fixed;
    width: 100%;
    word-wrap: break-word;
}

.styled-table td {
    padding: 5px 10px;
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
</style>

<body>
<div id="constrain">
  <div class="constrain">
    <div id="hdr">
    <span class="cleardiv"><!-- --></span>
    <span class="cleardiv"><!-- --></span>
    <div class="login-nav">
      <div style="text-align:right; vertical-align: text-bottom; height:1.1cm; padding-right: 1.0em; padding-top: 1.0em;">
      </div>
      <div style="text-align:right; position: absolute; top:80px; right:2px;">
      </div>

<ul id="header-nav" class="topnav" style="position: absolute; width:100%; bottom:-1px;">
<?php
    // Get current script name safely
    $currentScript = basename($_SERVER["SCRIPT_NAME"] ?? "");
    
    // Define navigation items
    $navItems = [
        ['file' => 'metGene.php', 'label' => 'Home'],
        ['file' => 'geneInfo.php', 'label' => 'Genes'],
        ['file' => 'pathways.php', 'label' => 'Pathways'],
        ['file' => 'reactions.php', 'label' => 'Reactions'],
        ['file' => 'metabolites.php', 'label' => 'Metabolites'],
        ['file' => 'studies.php', 'label' => 'Studies'],
        ['file' => 'summary.php', 'label' => 'Summary']
    ];

    // Generate navigation links
    foreach ($navItems as $item) {
        $url = buildNavUrl($METGENE_BASE_DIR_NAME, $item['file'], $navParams);
        $class = ($currentScript === $item['file']) ? 'dropbtnlit' : 'dropbtn';
        
        echo '<li class="dropdown">';
        echo '<a href="' . esc($url) . '" class="' . esc($class) . '">' . esc($item['label']) . '</a>';
        echo '<div class="dropdown-content"></div>';
        echo '</li>' . "\n";
    }
?>
</ul>

<span class="cleardiv"><!-- --></span>
        </div>
      </div>
    </div>
  </div>
</body>
</html>