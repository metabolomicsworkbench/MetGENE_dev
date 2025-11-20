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
    #$URL = "https://bdcw.org/MetGENE/rest/reactions/species/hsa/GeneIDType/SYMBOL/GeneInfoStr/RPE/anatomy/Blood/disease/Diabetes/phenotype/BMI/viewType/json"
    #$URL = "https://bdcw.org/MetGENE/rest/metabolites/species/hsa/GeneIDType/SYMBOL/GeneInfoStr/RPE/anatomy/Blood/disease/Diabetes/phenotype/BMI/viewType/json"
    #$URL = "https://bdcw.org/MetGENE/rest/studies/species/hsa/GeneIDType/SYMBOL/GeneInfoStr/RPE/anatomy/Blood/disease/Diabetes/phenotype/BMI/viewType/json"



    public function processApi($cs) {

        $debug = 0;
        $example_URL = "https://".$_SERVER['SERVER_NAME']."/MetGENE/rest/reactions/species/hsa/GeneIDType/SYMBOL/GeneInfoStr/RPE/anatomy/Blood/disease/Diabetes/phenotype/BMI/viewType/json";


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


        $func_arg_key = $myarr[0];
        $species_arg_key = $myarr[1];  $species = $myarr[2];
        $GeneIDType_arg_key = $myarr[3]; $GeneIDType = $myarr[4];
        $GeneInfoStr_arg_key = $myarr[5]; $GeneInfoStr = $myarr[6];
        $anatomy_arg_key = $myarr[7];  $anatomy = $myarr[8];
        $disease_arg_key = $myarr[9]; $disease = $myarr[10];
        $phenotype_arg_key = $myarr[11]; $phenotype = $myarr[12];

        if ( ($species_arg_key != "species") || ($GeneIDType_arg_key != "GeneIDType") || ($GeneInfoStr_arg_key != "GeneInfoStr")
             || ($anatomy_arg_key != "anatomy") || ($disease_arg_key != "disease") || ($phenotype_arg_key != "phenotype") ) {
            $msg = "Expected keywords <species>, <GeneIDType>, <GeneInfoStr> <anatomy> <disease> <phenotype> at appropriate locations in the URL. See example below:<br>{$example_URL}<br>";
            #$this->_content_type="text/plain; charset=UTF-8"; # not needed as being passed as 1st argument to this->response
            $this->response($this->content_types["text"], $msg, 406);            exit;
        }



        if (count($myarr) > 13) {
            $viewType_arg_key = $myarr[13];
            if ($viewType_arg_key != "viewType") {
                $msg = "Expected keyword <viewType> at this location in the URL. See example below:<br>{$example_URL}<br>";
                $this->response($this->content_types["text"], $msg, 406);            exit;
            }
            $viewType = $myarr[14];
        } else {
            $viewType = "json";
        }

        #$func=$myarr[1]; $input_name=$myarr[2]; $input_value=$myarr[3]; $output_name=$myarr[4]; $output_format=$myarr[5]; $FORMAT=$myarr[6];

        $this->check_variable_values($func_arg_key, $species, $GeneIDType, $GeneInfoStr, $anatomy, $disease, $phenotype, $viewType);
        $this->metgene($func_arg_key, $species, $GeneIDType, $GeneInfoStr, $anatomy, $disease, $phenotype, $viewType);
    }

    ###############################################

    public function check_variable_values($func_arg_key, $species, $GeneIDType, $GeneInfoStr, $anatomy, $disease, $phenotype, $viewType) {
        $debug = 0;
        if($debug) {echo "func = $func_arg_key, species = $species, GeneIDType = $GeneIDType, GeneInfoStr = $GeneInfoStr, anatomy = $anatomy, disease = $disease, phenotype = $phenotype, viewType = $viewType<br>";}

        # add more species as your R code is updated to handle them; allowing both 'human' and 'hsa'
        # option to expand these in the files where this file is included
        $allowed_species = array('human', 'hsa', 'mouse', 'mmu', 'rat', 'rno');
        $allowed_func = array('reactions', 'metabolites', 'studies', 'summary');
        $allowed_GeneIDType = array('SYMBOL', 'SYMBOL_OR_ALIAS', 'ALIAS', 'ENTREZID', 'GENENAME', 'ENSEMBL', 'REFSEQ', 'UNIPROT', 'HGNC');
        $allowed_viewType = array('txt', 'json', "jsonfile"); # allow ""

        //$allowed_species_concat = implode(" ", preg_filter('/$/', '>', preg_filter('/^/', '<', $allowed_species)));
        //$allowed_GeneIDType_concat = implode(" ", preg_filter('/$/', '>', preg_filter('/^/', '<', $allowed_GeneIDType)));
        //$allowed_View_concat = implode(" ", preg_filter('/$/', '>', preg_filter('/^/', '<', $allowed_View)));
        $allowed_species_concat = implode(", ", $allowed_species);
        $allowed_GeneIDType_concat = implode(", ", $allowed_GeneIDType);
        $allowed_viewType_concat = implode(", ", $allowed_viewType);
        $allowed_func_concat = implode(",", $allowed_func);

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

        if (empty($GeneInfoStr)) {
            $msg = "GeneInfoStr should not be empty";
            $this->response($this->content_types["text"], $msg, 406);            exit;
        }

        $func_str = strtolower($func_arg_key);
        if (in_array($func_str, $allowed_func) == FALSE) {
            $msg = "This program cannot handle entities $func_str. Chose one from: $allowed_func_concat";
            $this->response($this->content_types["text"], $msg, 406);            exit;
        }
        

        if (in_array($viewType, $allowed_viewType) == FALSE) {
            $msg = "This program cannot handle View $viewType yet. Chose one from: $allowed_viewType_concat";
            $this->response($this->content_types["text"], $msg, 406);            exit;
        }

        if($debug) { $LINENUM = __LINE__; echo "Line $LINENUM"; }
    }
    ###############################################

    public function metgene($func_arg_key, $species, $GeneIDType, $GeneInfoStr, $anatomy, $disease, $phenotype, $viewType) {
        $debug = 0;

        if ($this->get_request_method() != "GET") {
            $this->response('', '', 406); exit;
        }

        if($debug) {echo "species = $species, GeneIDType = $GeneIDType, GeneInfoStr = $GeneInfoStr, anatomy = $anatomy, disease = $disease, phenotype = $phenotype, viewType = $viewType<br>";}

        if($debug) { $LINENUM = __LINE__; echo "Line $LINENUM"; }

        $UseRedirect = 0;

        if ($UseRedirect) {
            # construct URL
            $URL_base = "https://".$_SERVER['SERVER_NAME']."/MetGENE/{$func_arg_key}.php?";
            $URL = "{$URL_base}species={$species}&GeneIDType={$GeneIDType}&GeneInfoStr={$GeneInfoStr}&anatomy={$anatomy}&disease={$disease}&phenotype={$phenotype}&view={$viewType}";
            if ($debug) {
                print "$URL";
            }
            
            # redirect
            header("Location: $URL");
            exit;
        } else {
            # write much of the code you have in geneid_proc_selcol_GET.php

            // define variables and set to empty values
            $species_default = "hsa";
            $species_codes_names = array(array("hsa", "Human"), array("mmu", "Mouse"), array("rno", "Rat"));

            $rscript_prefix_wrt_rest = "../";


            if($debug) { $LINENUM = __LINE__; echo "Line {$LINENUM}: species: {$species}"; }

            # change dir to rscript dir, run R and change dir back to current
            $curdir = getcwd();

            # extract ENTREZ IDs
            chdir($rscript_prefix_wrt_rest);
            $domainName = $_SERVER['SERVER_NAME'];
            $script_str = "/usr/bin/Rscript extractGeneIDsAndSymbols.R $species '$GeneInfoStr' $GeneIDType $domainName";
            exec("$script_str",$symbol_geneIDs, $retvar);

            
            $gene_symbols = array();
            $gene_array = array();
            $gene_id_symbols_arr = array();

            foreach ($symbol_geneIDs as $val) {
                if($debug) { $LINENUM = __LINE__; echo "Line {$LINENUM}: symbol_geneID: {$val}"; }
                $gene_id_symbols_arr = explode(",", $val);
            }

            $length = count($gene_id_symbols_arr);

            if($debug) { $LINENUM = __LINE__; echo "Line {$LINENUM}: length of gene_id_symbols_arr: {$length}"; }
            for ($i=0; $i < $length; $i++) {
                $my_str = $gene_id_symbols_arr[$i];
                $trimmed_str = trim($my_str, "\" ");

                if ($i < $length/2) {
                    array_push($gene_symbols, $trimmed_str);
                } else {
                    array_push($gene_array, $trimmed_str);
                }
            }
            $enc_disease = urlencode($disease);
            $enc_anatomy = urlencode($anatomy);

            if ($func_arg_key == "reactions") {
                $cnt = 0;
                echo "[";
                foreach ($gene_array as $value) {
                    if($debug) { $LINENUM = __LINE__; echo "Line {$LINENUM}: value: {$value}"; }
                    $htmlbuff = ""; $output = "";
                    $cmd_str = "/usr/bin/Rscript extractReactionInfo.R $species $value  $viewType";
                    exec("$cmd_str", $output, $retVar);
                    $htmlbuff = implode("\n", $output);
                    // Mano: 2023/01/06: if $htmlbuff is empty, set to []
                    if(strlen($htmlbuff)==0) $htmlbuff = "[]";
                    // Mano: 2022/12/14: set header only the first time, i.e., $cnt==0
                    if($cnt==0){
                     	if (strcmp($viewType, "json") == 0 ){
                                header('Content-type: application/json; charset=UTF-8');
                            } else {
                                header('Content-Type: text/plain; charset=UTF-8');
                            }
                            }

                        echo $htmlbuff;
                        if (count($gene_array) >1 && $cnt < count($gene_array)-1) {
                            echo ",";
                        }
                        $cnt = $cnt+1;

                    }
                    echo "]";


                } elseif ($func_arg_key == "metabolites") {
                    $cnt = 0;
                    echo "[";
                    $jsonmetfilename = "";
                    $jsonfile = "";
                    if (strcmp($viewType, "jsonfile") == 0) {
                       $prefix = "cache/met";
                       $suffix = ".json";
                       $jsonmetfilename = $prefix.rand(1,1000).$suffix;
                       $jsonfile = fopen($jsonmetfilename, "a");
                       fwrite($jsonfile, "[");
                    }

                    foreach ($gene_array as $value) {
                        $htmlbuff = ""; $output = "";
                        $cmd_str = "/usr/bin/Rscript extractMetaboliteInfo.R $species $value $enc_anatomy $enc_disease  $viewType";
                        if ($debug) { echo $cmd_str; }
                        exec("$cmd_str", $output, $retVar);
                        $htmlbuff = implode("\n", $output);
                        // Mano: 2023/01/06: if $htmlbuff is empty, set to []
                        if(strlen($htmlbuff)==0) $htmlbuff = "[]";
                        // Mano: 2022/12/14: set header only the first time, i.e., $cnt==0
                        if($cnt==0){
                            if (strcmp($viewType, "json") == 0 || strcmp($viewType, "jsonfile") == 0) {
                                header('Content-type: application/json; charset=UTF-8');
                            } else {
                                header('Content-Type: text/plain; charset=UTF-8');
                            }
                        }
                        if (strcmp($viewType, "jsonfile") == 0) {

                            fwrite($jsonfile, $htmlbuff);
                        } else {
                            echo $htmlbuff;
                        }
                        if (count($gene_array) >1 && $cnt < count($gene_array)-1) {
                            if (strcmp($viewType, "jsonfile") == 0) {
                                fwrite($jsonfile, ",");
                            } else {
                                echo ",";
                            }
                        }
                        $cnt = $cnt+1;
                    }
                    if (strcmp($viewType, "jsonfile") == 0) {
                        fwrite($jsonfile, "]");
                        fclose($jsonfile);
                        $url_value = "https://".$_SERVER['SERVER_NAME']."/MetGENE/".$jsonmetfilename;
                        $url_key = "FileURL";
                        $data = array(
                           $url_key => $url_value
                        );
                        $json_object = json_encode($data);
                        echo $json_object;
                        echo "]";
                    } else {
                        echo "]";
                    }
            } elseif ($func_arg_key == "summary") {
                        exec("/usr/bin/Rscript extractGeneIDsAndSymbols.R $species $GeneInfoStr $GeneIDType $domainName", $symbol_geneIDs, $retvar);
                        $gene_symbols = array();
                        $gene_array = array();
                        $gene_id_symbols_arr = array();
 
                        foreach ($symbol_geneIDs as $val) {
                            $gene_id_symbols_arr = explode(",", $val);
                        }

                        $length = count($gene_id_symbols_arr);


                        for ($i=0; $i < $length; $i++) {
                            $my_str = $gene_id_symbols_arr[$i];
                            $trimmed_str = trim($my_str, "\" ");
 
                            if ($i < $length/2) {
                                array_push($gene_symbols, $trimmed_str);
                            } else {
                                array_push($gene_array, $trimmed_str);
                            } 
                        }

                        $prefix = "cache/plot";
                        $suffix = ".png";
                        $filename = $prefix.rand(1,1000).$suffix;
                        $gene_array_str = implode("__", $gene_array);

                        $gene_sym_str = implode("__", $gene_symbols);

                        exec("/usr/bin/Rscript extractMWGeneSummary.R $species $gene_array_str $gene_sym_str $filename  $viewType", $output, $retVar);
                        $htmlbuff = implode("\n", $output);
                        if (strcmp($viewType, "json") == 0){ 
                            header('Content-type: application/json; charset=UTF-8'); 
                        } else {
                            header('Content-Type: text/plain; charset=UTF-8');
                        }

                        echo $htmlbuff;
            
                        //*****

                    }else { ## it is studies then
                        $gene_vec_str = implode(",", $gene_array);
                
                        $cmd_str = "/usr/bin/Rscript  extractFilteredStudiesInfo.R $species $gene_vec_str $enc_disease $enc_anatomy $viewType";
                        exec("$cmd_str", $output, $retVar);
                        $htmlbuff = implode("\n", $output);
                        if (strcmp($viewType, "json") == 0){
                            header('Content-type: application/json; charset=UTF-8');
                        } else {
                            header('Content-Type: text/plain; charset=UTF-8');
                        }
                        echo $htmlbuff;
                    }

                    chdir($curdir);

                } # if ($UseRedirect){
            } # public function metgene
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
