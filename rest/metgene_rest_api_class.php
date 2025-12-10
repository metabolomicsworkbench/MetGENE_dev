<?php
/**
 * rest.php - MetGENE REST API
 * Security hardened with proper input validation, command injection prevention,
 * and consistent use of security functions
 */

declare(strict_types=1);

// SECURITY FIX: Load metgene_common.php for security functions
require_once __DIR__ . '/../metgene_common.php';

// SECURITY FIX: Define str_contains for PHP < 8.0 compatibility
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return '' === $needle || false !== strpos($haystack, $needle);
    }
}

/**
 * REST base class - handles HTTP methods and responses
 */
class REST {

    public $_allow = array();
    public $_content_type = "application/json; charset=UTF-8";
    public $_request = array();
    private $_method = "";
    private $_code = 200;

    public $content_types = [
        "txt"  => "text/plain; charset=UTF-8",
        "text" => "text/plain; charset=UTF-8",
        "json" => "application/json; charset=UTF-8"
    ];

    public function __construct() {
        $this->inputs();
    }

    // SECURITY FIX: Validate and sanitize referer
    public function get_referer(): string {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        // Sanitize to prevent header injection
        return filter_var($referer, FILTER_SANITIZE_URL);
    }

    // SECURITY FIX: Added type hints and validation
    public function response(string $content_type, string $data, int $status): void {
        $this->_code = $status ?: 200;
        $this->_content_type = $content_type ?: "text/plain; charset=UTF-8";
        $this->set_headers();
        echo $data;
        exit;
    }

    // SECURITY FIX: Removed - file downloads should be handled separately with proper validation
    // public function downloadresponse($data, $status) { ... }

    private function get_status_message(): string {
        $status = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => '(Unused)',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported'
        );
        return $status[$this->_code] ?? $status[500];
    }

    public function get_request_method(): string {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public function inputs(): void {
        switch ($this->get_request_method()) {
            case "GET":
                $this->_request = $this->cleanInputs($_GET);
                break;
            default:
                $this->response('text/plain; charset=UTF-8', 'Method not allowed', 406);
                break;
        }
    }

    // SECURITY FIX: Enhanced input cleaning
    public function cleanInputs($data) {
        $clean_input = array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->cleanInputs($v);
            }
        } else {
            // SECURITY FIX: Removed deprecated get_magic_quotes_gpc check
            $data = strip_tags($data);
            $clean_input = trim($data);
        }
        return $clean_input;
    }

    private function set_headers(): void {
        // SECURITY FIX: Prevent header injection
        $code = (int)$this->_code;
        $status_message = $this->get_status_message();
        
        header("HTTP/1.1 {$code} {$status_message}");
        header("Content-Type: {$this->_content_type}");
        
        // SECURITY FIX: Add security headers
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
    }
}

/**
 * API class - handles MetGENE REST endpoints
 */
class API extends REST {

    public function __construct() {
        parent::__construct();
    }

    // SECURITY FIX: Complete rewrite with proper validation and command injection prevention
    public function processApi($cs): void {
        $debug = 0;
        $example_URL = "https://" . escapeHtml($_SERVER['SERVER_NAME'] ?? 'localhost') . 
                       "/MetGENE/rest/reactions/species/hsa/GeneIDType/SYMBOL/GeneInfoStr/RPE/anatomy/Blood/disease/Diabetes/phenotype/BMI/viewType/json";

        // SECURITY FIX: Validate and sanitize request path
        $request = $_REQUEST['rquest'] ?? '';
        $request = preg_replace("|^/v1/|", "/", $request);
        
        // SECURITY FIX: Prevent path traversal
        if (strpos($request, '..') !== false || strpos($request, '//') !== false) {
            $this->response($this->content_types["text"], "Invalid request path", 400);
            exit;
        }

        $myarr = explode("/", trim($request, '/'));

        if (count($myarr) < 13) {
            $msg = "Incomplete URL. Expected format: " . $example_URL;
            $this->response($this->content_types["text"], $msg, 400);
            exit;
        }

        // Parse URL components
        $func_arg_key = $myarr[0] ?? '';
        $species_arg_key = $myarr[1] ?? '';
        $species = $myarr[2] ?? '';
        $GeneIDType_arg_key = $myarr[3] ?? '';
        $GeneIDType = $myarr[4] ?? '';
        $GeneInfoStr_arg_key = $myarr[5] ?? '';
        $GeneInfoStr = $myarr[6] ?? '';
        $anatomy_arg_key = $myarr[7] ?? '';
        $anatomy = $myarr[8] ?? '';
        $disease_arg_key = $myarr[9] ?? '';
        $disease = $myarr[10] ?? '';
        $phenotype_arg_key = $myarr[11] ?? '';
        $phenotype = $myarr[12] ?? '';

        // Validate URL structure
        if ($species_arg_key !== "species" || 
            $GeneIDType_arg_key !== "GeneIDType" || 
            $GeneInfoStr_arg_key !== "GeneInfoStr" ||
            $anatomy_arg_key !== "anatomy" || 
            $disease_arg_key !== "disease" || 
            $phenotype_arg_key !== "phenotype") {
            $msg = "Expected keywords <species>, <GeneIDType>, <GeneInfoStr>, <anatomy>, <disease>, <phenotype> at appropriate locations. Example: {$example_URL}";
            $this->response($this->content_types["text"], $msg, 400);
            exit;
        }

        // Parse optional viewType
        if (count($myarr) > 13) {
            $viewType_arg_key = $myarr[13] ?? '';
            if ($viewType_arg_key !== "viewType") {
                $msg = "Expected keyword <viewType> at this location. Example: {$example_URL}";
                $this->response($this->content_types["text"], $msg, 400);
                exit;
            }
            $viewType = $myarr[14] ?? 'json';
        } else {
            $viewType = "json";
        }

        // Validate all inputs
        $this->check_variable_values($func_arg_key, $species, $GeneIDType, $GeneInfoStr, 
                                     $anatomy, $disease, $phenotype, $viewType);
        
        // Process the request
        $this->metgene($func_arg_key, $species, $GeneIDType, $GeneInfoStr, 
                      $anatomy, $disease, $phenotype, $viewType);
    }

    // SECURITY FIX: Enhanced validation
    private function check_variable_values(string $func_arg_key, string $species, string $GeneIDType, 
                                          string $GeneInfoStr, string $anatomy, string $disease, 
                                          string $phenotype, string $viewType): void {
        $debug = 0;

        // Whitelists
        $allowed_species = ['human', 'hsa', 'mouse', 'mmu', 'rat', 'rno'];
        $allowed_func = ['reactions', 'metabolites', 'studies', 'summary'];
        $allowed_GeneIDType = ['SYMBOL', 'SYMBOL_OR_ALIAS', 'ALIAS', 'ENTREZID', 
                               'GENENAME', 'ENSEMBL', 'REFSEQ', 'UNIPROT', 'HGNC'];
        $allowed_viewType = ['txt', 'json', 'jsonfile'];

        $allowed_species_concat = implode(", ", $allowed_species);
        $allowed_GeneIDType_concat = implode(", ", $allowed_GeneIDType);
        $allowed_viewType_concat = implode(", ", $allowed_viewType);
        $allowed_func_concat = implode(", ", $allowed_func);

        // Validate species
        $species = strtolower($species);
        if (!in_array($species, $allowed_species, true)) {
            $msg = "Invalid species: {$species}. Choose from: {$allowed_species_concat}";
            $this->response($this->content_types["text"], $msg, 400);
            exit;
        }

        // Validate GeneIDType
        $GeneIDType = strtoupper($GeneIDType);
        if (!in_array($GeneIDType, $allowed_GeneIDType, true)) {
            $msg = "Invalid GeneIDType: {$GeneIDType}. Choose from: {$allowed_GeneIDType_concat}";
            $this->response($this->content_types["text"], $msg, 400);
            exit;
        }

        // Validate GeneInfoStr
        if (empty($GeneInfoStr)) {
            $msg = "GeneInfoStr cannot be empty";
            $this->response($this->content_types["text"], $msg, 400);
            exit;
        }

        // SECURITY FIX: Validate GeneInfoStr format (alphanumeric, underscores, commas, hyphens only)
        if (!preg_match('/^[A-Za-z0-9_,\-]+$/', $GeneInfoStr)) {
            $msg = "Invalid GeneInfoStr format. Use only alphanumeric characters, underscores, commas, and hyphens.";
            $this->response($this->content_types["text"], $msg, 400);
            exit;
        }

        // Validate function
        $func_str = strtolower($func_arg_key);
        if (!in_array($func_str, $allowed_func, true)) {
            $msg = "Invalid function: {$func_str}. Choose from: {$allowed_func_concat}";
            $this->response($this->content_types["text"], $msg, 400);
            exit;
        }

        // Validate viewType
        if (!in_array($viewType, $allowed_viewType, true)) {
            $msg = "Invalid viewType: {$viewType}. Choose from: {$allowed_viewType_concat}";
            $this->response($this->content_types["text"], $msg, 400);
            exit;
        }
    }

    // SECURITY FIX: Complete rewrite with command injection prevention
    private function metgene(string $func_arg_key, string $species, string $GeneIDType, 
                           string $GeneInfoStr, string $anatomy, string $disease, 
                           string $phenotype, string $viewType): void {
        $debug = 0;

        if ($this->get_request_method() !== "GET") {
            $this->response($this->content_types["text"], 'Only GET method allowed', 405);
            exit;
        }

        // SECURITY FIX: Change to parent directory to access R scripts
        $parentDir = dirname(__DIR__);
        $originalDir = getcwd();
        
        if (!chdir($parentDir)) {
            error_log("Failed to change directory to: {$parentDir}");
            $this->response($this->content_types["text"], 'Internal server error', 500);
            exit;
        }

        try {
            $domainName = $_SERVER['SERVER_NAME'] ?? 'localhost';

            // SECURITY FIX: Use buildRscriptCommand for all R script calls
            $cmdIds = buildRscriptCommand(
                'extractGeneIDsAndSymbols.R',
                [$species, $GeneInfoStr, $GeneIDType, $domainName]
            );

            if ($cmdIds === '') {
                throw new Exception("Failed to build R script command");
            }

            $symbol_geneIDs = [];
            $retvar = 0;
            exec($cmdIds, $symbol_geneIDs, $retvar);

            if ($retvar !== 0) {
                throw new Exception("Gene ID extraction failed");
            }

            // Parse gene symbols and IDs
            $gene_symbols = [];
            $gene_array = [];
            $gene_id_symbols_arr = [];

            foreach ($symbol_geneIDs as $val) {
                $gene_id_symbols_arr = array_merge($gene_id_symbols_arr, explode(",", $val));
            }

            $length = count($gene_id_symbols_arr);

            for ($i = 0; $i < $length; $i++) {
                $trimmed_str = trim($gene_id_symbols_arr[$i], "\" ");

                if ($i < $length / 2) {
                    $gene_symbols[] = $trimmed_str;
                } else {
                    if ($trimmed_str !== '' && $trimmed_str !== 'NA') {
                        $gene_array[] = $trimmed_str;
                    }
                }
            }

            // URL encode disease and anatomy
            $enc_disease = rawurlencode($disease);
            $enc_anatomy = rawurlencode($anatomy);

            // SECURITY FIX: Process based on function type
            switch ($func_arg_key) {
                case 'reactions':
                    $this->process_reactions($gene_array, $species, $viewType);
                    break;
                    
                case 'metabolites':
                    $this->process_metabolites($gene_array, $species, $enc_anatomy, $enc_disease, $viewType);
                    break;
                    
                case 'summary':
                    $this->process_summary($gene_array, $gene_symbols, $species, $viewType);
                    break;
                    
                case 'studies':
                    $this->process_studies($gene_array, $species, $enc_disease, $enc_anatomy, $viewType);
                    break;
                    
                default:
                    throw new Exception("Unknown function: {$func_arg_key}");
            }

        } catch (Exception $e) {
            error_log("MetGENE API Error: " . $e->getMessage());
            $this->response($this->content_types["text"], 'Internal server error', 500);
        } finally {
            // SECURITY FIX: Always restore original directory
            chdir($originalDir);
        }
    }

    // SECURITY FIX: Separate processing methods with proper escaping
    private function process_reactions(array $gene_array, string $species, string $viewType): void {
        $cnt = 0;
        echo "[";
        
        foreach ($gene_array as $value) {
            $cmd = buildRscriptCommand('extractReactionInfo.R', [$species, $value, $viewType]);
            
            if ($cmd === '') {
                error_log("Failed to build R script command for reactions");
                continue;
            }

            $output = [];
            $retVar = 0;
            exec($cmd, $output, $retVar);
            
            $htmlbuff = implode("\n", $output);
            if (strlen($htmlbuff) == 0) {
                $htmlbuff = "[]";
            }

            if ($cnt == 0) {
                if ($viewType === "json") {
                    header('Content-type: application/json; charset=UTF-8');
                } else {
                    header('Content-Type: text/plain; charset=UTF-8');
                }
            }

            echo $htmlbuff;
            if (count($gene_array) > 1 && $cnt < count($gene_array) - 1) {
                echo ",";
            }
            $cnt++;
        }
        
        echo "]";
        exit;
    }

    private function process_metabolites(array $gene_array, string $species, string $enc_anatomy, 
                                        string $enc_disease, string $viewType): void {
        $cnt = 0;
        echo "[";
        
        $jsonmetfilename = "";
        $jsonfile = null;
        
        if ($viewType === "jsonfile") {
            // SECURITY FIX: Use absolute path and validate cache directory
            $cache_dir = __DIR__ . '/../cache';
            if (!is_dir($cache_dir)) {
                @mkdir($cache_dir, 0755, true);
            }
            
            $jsonmetfilename = $cache_dir . '/met' . mt_rand(1, 1000000) . '.json';
            $jsonfile = fopen($jsonmetfilename, 'w');
            if ($jsonfile) {
                fwrite($jsonfile, "[");
            }
        }

        foreach ($gene_array as $value) {
            $cmd = buildRscriptCommand('extractMetaboliteInfo.R', 
                                      [$species, $value, $enc_anatomy, $enc_disease, $viewType]);
            
            if ($cmd === '') {
                error_log("Failed to build R script command for metabolites");
                continue;
            }

            $output = [];
            $retVar = 0;
            exec($cmd, $output, $retVar);
            
            $htmlbuff = implode("\n", $output);
            if (strlen($htmlbuff) == 0) {
                $htmlbuff = "[]";
            }

            if ($cnt == 0) {
                if ($viewType === "json" || $viewType === "jsonfile") {
                    header('Content-type: application/json; charset=UTF-8');
                } else {
                    header('Content-Type: text/plain; charset=UTF-8');
                }
            }

            if ($viewType === "jsonfile" && $jsonfile) {
                fwrite($jsonfile, $htmlbuff);
            } else {
                echo $htmlbuff;
            }

            if (count($gene_array) > 1 && $cnt < count($gene_array) - 1) {
                if ($viewType === "jsonfile" && $jsonfile) {
                    fwrite($jsonfile, ",");
                } else {
                    echo ",";
                }
            }
            $cnt++;
        }

        if ($viewType === "jsonfile" && $jsonfile) {
            fwrite($jsonfile, "]");
            fclose($jsonfile);
            
            // Return relative URL path
            $base_dir = getBaseDirName();
            $relative_path = str_replace(__DIR__ . '/../', '', $jsonmetfilename);
            $url_value = "https://" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . 
                        $base_dir . "/" . $relative_path;
            
            $data = ["FileURL" => $url_value];
            echo json_encode($data);
            echo "]";
        } else {
            echo "]";
        }
        
        exit;
    }

    private function process_summary(array $gene_array, array $gene_symbols, string $species, 
                                    string $viewType): void {
        // SECURITY FIX: Use cache directory with absolute path
        $cache_dir = __DIR__ . '/../cache';
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0755, true);
        }
        
        $plot_basename = 'plot' . mt_rand(1, 1000000) . '.png';
        $filename = 'cache/' . $plot_basename;  // R script needs relative path
        
        $gene_array_str = implode("__", $gene_array);
        $gene_sym_str = implode("__", $gene_symbols);

        $cmd = buildRscriptCommand('extractMWGeneSummary.R', 
                                   [$species, $gene_array_str, $gene_sym_str, $filename, $viewType, 'NA', 'NA']);
        
        if ($cmd === '') {
            error_log("Failed to build R script command for summary");
            $this->response($this->content_types["text"], 'Internal server error', 500);
            exit;
        }

        $output = [];
        $retVar = 0;
        exec($cmd, $output, $retVar);
        
        $htmlbuff = implode("\n", $output);
        
        if ($viewType === "json") {
            header('Content-type: application/json; charset=UTF-8');
        } else {
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo $htmlbuff;
        exit;
    }

    private function process_studies(array $gene_array, string $species, string $enc_disease, 
                                    string $enc_anatomy, string $viewType): void {
        $gene_vec_str = implode(",", $gene_array);

        $cmd = buildRscriptCommand('extractFilteredStudiesInfo.R', 
                                   [$species, $gene_vec_str, $enc_disease, $enc_anatomy, $viewType]);
        
        if ($cmd === '') {
            error_log("Failed to build R script command for studies");
            $this->response($this->content_types["text"], 'Internal server error', 500);
            exit;
        }

        $output = [];
        $retVar = 0;
        exec($cmd, $output, $retVar);
        
        $htmlbuff = implode("\n", $output);
        
        if ($viewType === "json") {
            header('Content-type: application/json; charset=UTF-8');
        } else {
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo $htmlbuff;
        exit;
    }

    // SECURITY FIX: Removed unused json() method
}

// Note: Initialization happens in the file that includes this one
?>