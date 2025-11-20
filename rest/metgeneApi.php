<?php

############## Mano add default lines #################
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
ini_set("log_errors", TRUE);
define('EOL', (PHP_SAPI == 'cli') ? PHP_EOL : '<br />');
date_default_timezone_set('America/Los_Angeles');
#######################################################

set_time_limit(0);
ini_set('memory_limit', '2048M');

#$thisfilename = __FILE__; echo "Inside file: $thisfilename<br>";
# define str_contains
if (!function_exists('str_contains')) {

    function str_contains(string $haystack, string $needle): bool {
        return '' === $needle || false !== strpos($haystack, $needle);
    }
}

class REST {

    public $_allow = array();
    public $_content_type = "application/json";
    public $_request = array();
    private $_method = "";
    private $_code = 200;

    public function __construct() {
        $this->inputs();
    }

    public function get_referer() {
        return $_SERVER['HTTP_REFERER'];
    }

    public function response($data, $status) {
        $this->_code = ($status) ? $status : 200;
        $this->set_headers();
        echo $data;
        exit;
    }

    public function downloadresponse($data, $status) {
        $this->_code = ($status) ? $status : 200;
        $this->set_downloadheaders();
        echo $data;
        exit;
    }

    private function get_status_message() {
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
            505 => 'HTTP Version Not Supported');
        return ($status[$this->_code]) ? $status[$this->_code] : $status[500];
    }

    public function get_request_method() {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function inputs() {
        switch ($this->get_request_method()) {
            case "GET":
                $this->_request = $this->cleanInputs($_GET);
                break;
            default:
                $this->response('', 406);
                break;
        }
    }

    public function cleanInputs($data) {
        $clean_input = array();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $clean_input[$k] = $this->cleanInputs($v);
            }
        } else {
            if (get_magic_quotes_gpc()) {
                $data = trim(stripslashes($data));
            }
            $data = strip_tags($data);
            $clean_input = trim($data);
        }
        return $clean_input;
    }

    public function set_headers() {
        header("HTTP/1.1 " . $this->_code . " " . $this->get_status_message());
        header("Content-Type:" . $this->_content_type);
    }

    public function set_downloadheaders() {
        header("HTTP/1.1 " . $this->_code . " " . $this->get_status_message());
        header("Content-Type:" . $this->_content_type);
        header("Content-Disposition:attachment ;filename=\"mb.mol\"");
    }

}

#########################################################################################################

class API extends REST {

    public function __construct() {
        parent::__construct();                          // Init parent contructor
    }

# since redirect is used, use -L with curl
# test php file as standard php
# https://sc-cfdewebdev.sdsc.edu/dev2/geneid/rest/geneid_rest_api.php?rquest=species/hsa/GeneIDType/SYMBOL/GeneListStr/PNPLA3/View/json
# https://sc-cfdewebdev.sdsc.edu/dev2/geneid/rest/geneid_rest_api.php?rquest=species/hsa/GeneIDType/ENTREZID/GeneListStr/3098/View/json
#
$URL = "https://sc-cfdewebdev.sdsc.edu/MetGENE/rest/reactions/species/hsa/GeneIDType/SYMBOL/GeneInfoStr/RPE/anatomy/Blood/disease/Diabetes/phenotype/BMI/viewType/json"
#$URL="https://sc-cfdewebdev.sdsc.edu/dev2/geneid/rest/species/hsa/GeneIDType/ENTREZID/GeneListStr/3098/View/txt"

    public function processApi($cs) {

        $debug = 0;

        $example_URL = "https://sc-cfdewebdev.sdsc.edu/MetGENE/rest/reactions/species/hsa/GeneIDType/SYMBOL/GeneInfoStr/RPE/anatomy/Blood/disease/Diabetes/phenotype/BMI/viewType/json";

        #versioning: remove /v1/
        if ($debug) {
            $LINENUM = __LINE__;
            echo "<br>Line $LINENUM: rquest: [" . $_REQUEST['rquest'] . "]<br>";
        }
        $_REQUEST['rquest'] = preg_replace("|^/v1/|", "/", $_REQUEST['rquest']);
        $myarr = explode("/", $_REQUEST['rquest']); # $myarr=preg_split("\/", $_REQUEST['rquest']);

        if ($debug) {
            $LINENUM = __LINE__;
            echo "<br>Line $LINENUM:myarr:<br>";
            print_r($myarr);
}



        $func_arg_key = $myarr[0];
        if ($func_arg_key != "reactions" || $func_arg_key != "metabolites" || $func_arg_key != studies) {
            echo "Expected keywords <reactions, metabolites, studies> at this location in the URL. See example below:<br>";
            echo "$example_URL<br>";
            $this->response('', 406);
            exit;
        }


        $species_arg_key = $myarr[1];
        if ($species_arg_key != "species") {
            echo "Expected keyword <species> at this location in the URL. See example below:<br>";
            echo "$example_URL<br>";
            $this->response('', 406);
            exit;
        }
        $species = $myarr[2];

        $GeneIDType_arg_key = $myarr[3];
        if ($GeneIDType_arg_key != "GeneIDType") {
            echo "Expected keyword <GeneIDType> at this location in the URL. See example below:<br>";
            echo "$example_URL<br>";
            $this->response('', 406);
            exit;
        }
        $GeneIDType = $myarr[4];

        $GeneInfoStr_arg_key = $myarr[5];
        if ($GeneInfoStr_arg_key != "GeneInfoStr") {
            echo "Expected keyword <GeneInfoStr> at this location in the URL. See example below:<br>";
            echo "$example_URL<br>";
            $this->response('', 406);
            exit;
        }
        $GeneInfoStr = $myarr[6];

        $anatomyStr_arg_key = $myarr[7];
        if ($anatomy_arg_key != "anatomyStr") {
            echo "Expected keyword <anatomy> at this location in the URL. See example below:<br>";
            echo "$example_URL<br>";
            $this->response('', 406);
            exit;
        }
        $anatomyStr = $myarr[8];

        $diseaseStr_arg_key = $myarr[9];
        if ($disease_arg_key != "diseaseStr") {
            echo "Expected keyword <disease> at this location in the URL. See example below:<br>";
            echo "$example_URL<br>";
            $this->response('', 406);
            exit;
        }
        $diseaseStr = $myarr[10];

        $phenotypeStr_arg_key = $myarr[11];
        if ($phenotype_arg_key != "phenotypeStr") {
            echo "Expected keyword <phenotype> at this location in the URL. See example below:<br>";
            echo "$example_URL<br>";
            $this->response('', 406);
            exit;
        }
        $phenotypeStr = $myarr[11];


        if (count($myarr) > 12) {
            $View_arg_key = $myarr[12];
            if ($View_arg_key != "viewType") {
                echo "Expected keyword <viewType> at this location in the URL. See example below:<br>";
                echo "$example_URL<br>";
                $this->response('', 406);
                exit;
            }
            $viewType = $myarr[13];
        } else {
            $viewTYpe = "json";
        }

        #$func=$myarr[1]; $input_name=$myarr[2]; $input_value=$myarr[3]; $output_name=$myarr[4]; $output_format=$myarr[5]; $FORMAT=$myarr[6];

        $this->metGENE($species, $GeneIDType, $GeneInfoStr, $anatomy, $disease, $phenotype, $viewType);
    }

###############################################

    public function metGENE($species, $GeneIDType, $GeneInfoStr, $anatomy, $disease, $phenotype, $viewType) {
        $debug = 0;

        if ($this->get_request_method() != "GET") {
            $this->response('', 406);
        }

        if($debug) {echo "species = $species, GeneIDType = $GeneIDType, GeneInfoStr = $GeneInfoStr, anatomy = $anatomy, disease = $disease, phenotype = $phenotype, viewType = $viewType<br>";}

        # add more species as your R code is updated to handle them; allowing both 'human' and 'hsa'
        $allowed_species = array('human', 'hsa', 'mouse', 'mmu', 'rat', 'rno');
        $allowed_GeneIDType = array('SYMBOL', 'SYMBOL_OR_ALIAS', 'ALIAS', 'ENTREZID', 'GENENAME', 'ENSEMBL', 'REFSEQ', 'UNIPROT');
        $allowed_viewType = array('txt', 'json');

        //$allowed_species_concat = implode(" ", preg_filter('/$/', '>', preg_filter('/^/', '<', $allowed_species)));
        //$allowed_GeneIDType_concat = implode(" ", preg_filter('/$/', '>', preg_filter('/^/', '<', $allowed_GeneIDType)));
        //$allowed_View_concat = implode(" ", preg_filter('/$/', '>', preg_filter('/^/', '<', $allowed_View)));
        $allowed_species_concat = implode(", ", $allowed_species);
        $allowed_GeneIDType_concat = implode(", ", $allowed_GeneIDType);
        $allowed_viewType_concat = implode(", ", $allowed_viewType);

        # error checking on inputs
        $species = strtolower($species);
        if (in_array($species, $allowed_species) == FALSE) {
            print "This program cannot handle species $species yet. Chose one from: $allowed_species_concat";
            exit;
        }

        $GeneIDType = strtoupper($GeneIDType);
        if (in_array($GeneIDType, $allowed_GeneIDType) == FALSE) {
            print "This program cannot handle GeneIDType $GeneIDType yet. Chose one from: $allowed_GeneIDType_concat";
            exit;
        }

        if (empty($GeneInfoStr)) {
            print "GeneInfoStr should not be empty";
            exit;
        }

        if (in_array($viewType, $allowed_viewType) == FALSE) {
            print "This program cannot handle view type $viewType yet. Chose one from: $allowed_viewType_concat";
            exit;
        }

        if($debug) { $LINENUM = __LINE__; echo "Line $LINENUM"; }

        $UseRedirect = 0;

        if ($UseRedirect) {
            # construct URL
                        
            $URL_base = "https://sc-cfdewebdev.sdsc.edu/MetGENE/{$func_arg_key}.php?";
            $URL = "{$URL_base}species={$species}&GeneIDType={$GeneIDType}&GeneInfoStr={$GeneInfoStr}&anatomy={$anatomy}&disease={$disease}&phenotype={$phenotype}&viewType={$viewType}";
            if ($debug) {
                print "$URL";
            }
             
            # redirect
            header("Location: $URL");
            exit;
        } else {
            # run all your execs here

            if($debug) { $LINENUM = __LINE__; echo "Line $LINENUM"; }

            ## Get current directory before changing directory where R scripts are located.
            $curdir = getcwd();

            //$idconv_cmdstr = "Rscript {$rscript_prefix_wrt_rest}idconvFromPHP.R $species $USE_NCBI_GENE_INFO $fname_wrt_rscript_folder $GeneListStr $GeneIDType $GLF_HEADER $GenomeVersion $dbcolstr";
            $idconv_cmdstr = "Rscript idconvFromPHP.R $species $USE_NCBI_GENE_INFO $fname_wrt_rscript_folder $GeneListStr $GeneIDType $GLF_HEADER $GenomeVersion $dbcolstr";
            if ($debug > 1) {
                echo "<br>[run on unix to debug] Rscript Command was:<br>$idconv_cmdstr";
            }
            chdir($rscript_prefix_wrt_rest);
            $idres_lastcol = exec("$idconv_cmdstr", $idres_allcol, $idres_status); // idres: id results // id conversion results
            chdir($curdir);

            if($debug){ echo "$idres_allcol\n"; print_r($idres_allcol);}

            if ($View == "json") {
                header('Content-type: application/json; charset=UTF-8');
                if ($IncHTML == 0) {
                    if (file_exists($fname_json)) {
                        echo file_get_contents($fname_json);
                    } else {
                        echo "[]";
                    }
                } else {
                    if (file_exists($fname_IncHTML_json)) {
                        echo file_get_contents($fname_IncHTML_json);
                    } else {
                        echo "[]";
                    }
                }
            } elseif ($View == "txt") {
                header('Content-Type: text/plain; charset=UTF-8');
                if ($IncHTML == 0) {
                    if (file_exists($fname)) {
                        echo file_get_contents($fname);
                    } else {
                        echo "";
                    }
                } else {
                    if (file_exists($fname_IncHTML)) {
                        echo file_get_contents($fname_IncHTML);
                    } else {
                        echo "";
                    }
                }
            }
        } # if ($UseRedirect){
    } # public function geneid($species, $GeneIDType, $GeneListStr, $View){
###############################################

    public function json($data) {
        if (is_array($data)) {
            return json_encode($data);
        } else {
            return $data;
        }
    }

} # class API extends REST {

// Initiate Library
#include("../../rest.php");
#$thisfilename = __FILE__; echo "<html><head>Test</head><body>Inside file: $thisfilename<br></body></html>";

$api = new API;
$str = "Dummy";
$api->processApi($str);
?>
