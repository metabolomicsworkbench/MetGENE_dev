<?php

# define str_contains
if (!function_exists('str_contains')) {

    function str_contains(string $haystack, string $needle): bool {
        return '' === $needle || false !== strpos($haystack, $needle);
    }
}

class REST {

    public $_allow = array();
    public $_content_type = "application/json; charset=UTF-8";
    public $_request = array();
    private $_method = "";
    private $_code = 200;

    public $content_types = ["txt" => "text/plain; charset=UTF-8", "text" => "text/plain; charset=UTF-8", "json" => "application/json; charset=UTF-8"];

    public function __construct() {
        $this->inputs();
    }

    public function get_referer() {
        return $_SERVER['HTTP_REFERER'];
    }

    # Mano: 2022/05/18: added $content_type argument so that it can be passed directly
    public function response($content_type, $data, $status) {
        $this->_code = ($status) ? $status : 200;
        $this->_content_type = ($content_type) ? $content_type : "text/plain; charset=UTF-8"; # Mano
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
                $this->response('', '', 406); # Mano added $content_type argument
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
#$URL="https://sc-cfdewebdev.sdsc.edu/dev2/geneid/rest/species/hsa/GeneIDType/SYMBOL/GeneListStr/PNPLA3/View/json"
#$URL="https://sc-cfdewebdev.sdsc.edu/dev2/geneid/rest/species/hsa/GeneIDType/SYMBOL_OR_ALIAS/GeneListStr/PNPLA3/View/json"
#$URL="https://sc-cfdewebdev.sdsc.edu/dev2/geneid/rest/species/hsa/GeneIDType/ENTREZID/GeneListStr/3098/View/json"
#$URL="https://sc-cfdewebdev.sdsc.edu/dev2/geneid/rest/species/hsa/GeneIDType/ENTREZID/GeneListStr/3098/View/txt"

    public function processApi($cs) {

        $debug = 0;

        $example_URL = "https://sc-cfdewebdev.sdsc.edu/dev2/geneid/rest/species/hsa/GeneIDType/SYMBOL/GeneListStr/PNPLA3/View/json";

        #versioning: remove /v1/
        if ($debug) {
            $LINENUM = __LINE__;
            echo "<br>Line $LINENUM: rquest: [" . $_REQUEST['rquest'] . "]<br>";
        }
        $_REQUEST['rquest'] = preg_replace("|^/v1/|", "/", $_REQUEST['rquest']); # Can be commented if no versioning
        $myarr = explode("/", $_REQUEST['rquest']); # $myarr=preg_split("\/", $_REQUEST['rquest']);

        if ($debug) {
            $LINENUM = __LINE__;
            echo "<br>Line $LINENUM:myarr:<br>";
            print_r($myarr);
        }
        
        // Likely, rquest doesn't include dev2/geneid/rest, so these three if are not needed
        $strip_dev_geneid_rest = 0;
        if($strip_dev_geneid_rest==1){
            if (str_contains($myarr[0], "dev")) {
                array_splice($myarr, 0, 1);
            }
            if (str_contains($myarr[0], "geneid")) {
                array_splice($myarr, 0, 1);
            }
            if (str_contains($myarr[0], "rest")) {
                array_splice($myarr, 0, 1);
            }
        }

        $species_arg_key = $myarr[0];        $species = $myarr[1];
        $GeneIDType_arg_key = $myarr[2];        $GeneIDType = $myarr[3];
        $GeneListStr_arg_key = $myarr[4];        $GeneListStr = $myarr[5];
        if ( ($species_arg_key != "species") || ($GeneIDType_arg_key != "GeneIDType") || ($GeneListStr_arg_key != "GeneListStr") ) {            
            $msg = "Expected keywords <species>, <GeneIDType> and <GeneListStr> at appropriate locations in the URL. See example below:<br>{$example_URL}<br>";
            #$this->_content_type="text/plain; charset=UTF-8"; # not needed as being passed as 1st argument to this->response
            $this->response($this->content_types["text"], $msg, 406);            exit;
        }
        
        /* if ($GeneIDType_arg_key != "GeneIDType") {
            $msg = "Expected keyword <GeneIDType> at this location in the URL. See example below:<br>{$example_URL}<br>";
            $this->response($this->content_types["text"], $msg, 406);            exit;
        } */

        if (count($myarr) > 6) {
            $View_arg_key = $myarr[6];
            if ($View_arg_key != "View") {
                $msg = "Expected keyword <View> at this location in the URL. See example below:<br>{$example_URL}<br>";
                $this->response($this->content_types["text"], $msg, 406);            exit;
            }
            $View = $myarr[7];
        } else {
            $View = "json";
        }

        #$func=$myarr[1]; $input_name=$myarr[2]; $input_value=$myarr[3]; $output_name=$myarr[4]; $output_format=$myarr[5]; $FORMAT=$myarr[6];

        $this->check_variable_values($species, $GeneIDType, $GeneListStr, $View);
        $this->geneid($species, $GeneIDType, $GeneListStr, $View);
    }

###############################################

    public function check_variable_values($species, $GeneIDType, $GeneListStr, $View) {
        $debug = 0;
        if($debug) {echo "species = $species, GeneIDType = $GeneIDType, GeneListStr = $GeneListStr, View = $View<br>";}

        # add more species as your R code is updated to handle them; allowing both 'human' and 'hsa'
        # option to expand these in the files where this file is included
        $allowed_species = array('human', 'hsa', 'mouse', 'mmu', 'rat', 'rno');
        $allowed_GeneIDType = array('SYMBOL', 'SYMBOL_OR_ALIAS', 'ALIAS', 'ENTREZID', 'GENENAME', 'ENSEMBL', 'REFSEQ', 'UNIPROT', 'HGNC');
        $allowed_View = array('txt', 'json', ""); # allow ""

        //$allowed_species_concat = implode(" ", preg_filter('/$/', '>', preg_filter('/^/', '<', $allowed_species)));
        //$allowed_GeneIDType_concat = implode(" ", preg_filter('/$/', '>', preg_filter('/^/', '<', $allowed_GeneIDType)));
        //$allowed_View_concat = implode(" ", preg_filter('/$/', '>', preg_filter('/^/', '<', $allowed_View)));
        $allowed_species_concat = implode(", ", $allowed_species);
        $allowed_GeneIDType_concat = implode(", ", $allowed_GeneIDType);
        $allowed_View_concat = implode(", ", $allowed_View);

        # error checking on inputs
        $species = strtolower($species);
        if (in_array($species, $allowed_species) == FALSE) {
            $msg = "This program cannot handle species $species yet. Chose one from: $allowed_species_concat";
            $this->response($this->content_types["text"], $msg, 406);            exit;
        }

        $GeneIDType = strtoupper($GeneIDType);
        if (in_array($GeneIDType, $allowed_GeneIDType) == FALSE) {
            $msg = "This program cannot handle GeneIDType $GeneIDType yet. Chose one from: $allowed_GeneIDType_concat";
            $this->response($this->content_types["text"], $msg, 406);            exit;
        }

        if (empty($GeneListStr)) {
            $msg = "GeneListStr should not be empty";
            $this->response($this->content_types["text"], $msg, 406);            exit;
        }

        if (in_array($View, $allowed_View) == FALSE) {
            $msg = "This program cannot handle View $View yet. Chose one from: $allowed_View_concat";
            $this->response($this->content_types["text"], $msg, 406);            exit;
        }

        if($debug) { $LINENUM = __LINE__; echo "Line $LINENUM"; }
    }
###############################################

    public function geneid($species, $GeneIDType, $GeneListStr, $View) {
        $debug = 0;

        if ($this->get_request_method() != "GET") {
            $this->response('', '', 406); exit;
        }

        if($debug) {echo "species = $species, GeneIDType = $GeneIDType, GeneListStr = $GeneListStr, View = $View<br>";}

        if($debug) { $LINENUM = __LINE__; echo "Line $LINENUM"; }

        $UseRedirect = 0;

        if ($UseRedirect) {
            # construct URL
            $URL_base = "https://sc-cfdewebdev.sdsc.edu/dev2/geneid/geneid_proc_selcol_GET.php?";
            $URL = "{$URL_base}species={$species}&GeneIDType={$GeneIDType}&GeneListStr={$GeneListStr}&View={$View}";
            if ($debug) {
                print "$URL";
            }

            # redirect
            header("Location: $URL");
            exit;
        } else {
            # write much of the code you have in geneid_proc_selcol_GET.php
            $GenomeVersion_default = "GenomeVersionUnknown"; # not setting to hg38 so that some option doesn't become active in R code
            $GenomeVersion = $GenomeVersion_default;

            $USE_NCBI_GENE_INFO = 1;
            $supress_ipaddress_in_fname = 1;

            $GeneList_filepath = "";
            $GLF_HEADER = "FALSE";
            $IncHTML = 0;

            $clientIP = $_SERVER['REMOTE_ADDR'];
            $clientIP_ = str_replace(".", "_", $_SERVER['REMOTE_ADDR']);
            $cur_t = date('Ymd_His'); # Hisu gives 000000 for microsecond
            //list($tusec, $tsec) = explode(" ", microtime()); $cur_t = date("Ymd_His", $tsec) . $tusec; // to really get microseconds
            $date_prefix = $cur_t;
            $IP_date_prefix = $clientIP_ . "_" . $cur_t;

            if($debug) { $LINENUM = __LINE__; echo "Line $LINENUM"; }

            // define variables and set to empty values
            $species_default = "hsa";
            $species_codes_names = array(array("hsa", "Human"), array("mmu", "Mouse"), array("rno", "Rat"));

            $rscript_prefix_wrt_rest = "../";

            # Mano: 2021/08/03: set dbcol here based on $GeneIDType; actually, keep it generic, so that end-user can extract needed columns
            //$dbcol = array("ALIAS", "ENSEMBL", "ENTREZID", "GENENAME", "REFSEQ", "SYMBOL", "UNIPROT"); // alphabetical, why?
            $dbcol = array("SYMBOL", "ENTREZID", "ALIAS", "GENENAME", "ENSEMBL", "REFSEQ", "UNIPROT");
            $dbcolvec = $dbcol; //$_POST["dbcol"]; # Mano: 2021/08/03: $dbcol set above instead of in _POST            
            $dbcolstr = implode(" ", $dbcolvec);
            $dbcolstr_for_fname = implode("_", $dbcolvec);

            $output_folder_wrt_rscript_folder = "tmp/";
            $output_folder = $rscript_prefix_wrt_rest . $output_folder_wrt_rscript_folder; # wrt current php script

            // Mano: 2021/10/21: use md5 hash instead of IP address for filename
            if ($supress_ipaddress_in_fname) {
                $str_for_hash = $IP_date_prefix . $dbcolstr_for_fname;
                $hash_length = 5;
                $hash_for_fname = substr(str_shuffle(hash('md5', $str_for_hash)), 0, $hash_length);
                $fname_wrt_rscript_folder = $output_folder_wrt_rscript_folder . $hash_for_fname . "_" . $date_prefix . "_ConvertedGeneIDs.txt"; // output filename
            } else {
                $fname_wrt_rscript_folder = $output_folder_wrt_rscript_folder . $IP_date_prefix . "_ConvertedGeneIDs_" . $dbcolstr_for_fname . ".txt"; // output filename
            }
            
            $fname = $rscript_prefix_wrt_rest .  $fname_wrt_rscript_folder;
            $fname_html = $fname . ".html";
            $fname_json = $fname . ".json";

            $fname_IncHTML = $fname . "_IncHTML.txt";
            $fname_IncHTML_json = $fname . "_IncHTML.json";

            if($debug) { $LINENUM = __LINE__; echo "Line {$LINENUM}: fname_IncHTML_json: {$fname_IncHTML_json}"; }
            
            // Mano: 20210428: If use NCBI_gene_info
            // Now read from $_SESSION //$USE_NCBI_GENE_INFO = 0;

            # change dir to rscript dir, run R and change dir back to current
            $curdir = getcwd();
            
            //$idconv_cmdstr = "Rscript {$rscript_prefix_wrt_rest}idconvFromPHP.R $species $USE_NCBI_GENE_INFO $fname_wrt_rscript_folder $GeneListStr $GeneIDType $GLF_HEADER $GenomeVersion $dbcolstr";
            $idconv_cmdstr = "Rscript idconvFromPHP.R $species $USE_NCBI_GENE_INFO $fname_wrt_rscript_folder $GeneListStr $GeneIDType $GLF_HEADER $GenomeVersion $dbcolstr";
            if ($debug > 1) {
                echo "<br>[run on unix to debug] Rscript Command was:<br>$idconv_cmdstr";
            }
            chdir($rscript_prefix_wrt_rest);
            $idres_lastcol = exec("$idconv_cmdstr", $idres_allcol, $idres_status); // idres: id results // id conversion results
            chdir($curdir);
            
            if($debug){ echo "\nidres_allcol\n"; print_r($idres_allcol);}
            
            if ($View == "json") {
                #header('Content-type: application/json; charset=UTF-8');
                if ($IncHTML == 0) {
                    $outputdata = (file_exists($fname_json)) ? file_get_contents($fname_json) : "[]";
                    #if (file_exists($fname_json)) { echo file_get_contents($fname_json); } else { echo "[]"; }
                } else {
                    $outputdata = (file_exists($fname_IncHTML_json)) ? file_get_contents($fname_IncHTML_json) : "[]";
                    #if (file_exists($fname_IncHTML_json)) { echo file_get_contents($fname_IncHTML_json); } else { echo "[]"; }
                }
                $this->response($this->content_types["json"], $outputdata, 200);            exit;
            } elseif ($View == "txt") {
                #header('Content-Type: text/plain; charset=UTF-8');
                if ($IncHTML == 0) {
                    $outputdata = (file_exists($fname)) ? file_get_contents($fname) : "";
                    #if (file_exists($fname)) { echo file_get_contents($fname); } else { echo ""; }
                } else {
                    $outputdata = (file_exists($fname_IncHTML)) ? file_get_contents($fname_IncHTML) : "";
                    #if (file_exists($fname_IncHTML)) { echo file_get_contents($fname_IncHTML); } else { echo ""; }
                }
                $this->response($this->content_types["text"], $outputdata, 200);            exit;
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

// do in the file in which this file is included
// Initiate Library
#include("../../rest.php");
#$thisfilename = __FILE__; echo "<html><head>Test</head><body>Inside file: $thisfilename<br></body></html>";

//$api = new API;
//$str = "Dummy";
//$api->processApi($str);
?>
