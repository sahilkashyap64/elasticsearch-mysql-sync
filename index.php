<?php
require 'vendor/autoload.php';
require './jsonToCsv.php';
require './env.php';
ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
class elastic_to_db_custom
{
    public $csvFileObject;
    // Hoolds the index and its info ( health, status, total )
    private $elastic_index_array = array();

    // Holds the error info
    private $errors = array();

    // Elastic object to fetch from server
    private $elastic_obj;

    // Mysqli connection obj
    private $mysqli_conn;

    // PDO connection obj
    private $pdo_conn;

    // index which are not going to use in db
    private $neglet_indexes = array();
    /*
    * @param null
    * constructor
    */
    public function __construct()
    {
        if ($this->check_elastic_status()) {
            echo "Elastic health is good. Now proceed to next step i.e check available indexs.<br>";
            if ($this->count_index_and_store_info()) {

                // We have successfully recived the index and its data now get from elastic and insert in db step;
                $this->elasticsearch_include_its_library_prepare_obj();
                // echo '<pre>';
                // print_r( $this->elastic_index_array );
                // echo '</pre>';

                //$this->create_mysqli_connection();
                $this->create_pdo_connection();

                $this->fetch_from_elastic_put_in_db();
            }
        } else {
            throw new Exception("Elastic health is not good.", 1);
        }
    }

    /*
    * @param null
    * Used defined variable : CURL_STATUS_CHECK_URI
    * check the health of elasticsearch plugin
    */
    private function check_elastic_status()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, CURL_STATUS_CHECK_URI);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
        }
        curl_close($ch);
        return true;
    }

    private function count_index_and_store_info()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, CURL_INDEX_CHECK_INFO_URI);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $result = json_decode(curl_exec($ch));

        if (curl_errno($ch)) {
            throw new Exception("Can't get indexes", 2);
        }
        curl_close($ch);

        foreach ($result as $key => $value) {
            $count_obj_name = 'docs.count';
            $this->elastic_index_array[ $value->index ] = [ 'health' => $value->health, 'status' => $value->status, 'total' => $value->$count_obj_name ];
        }

        return true;
    }

    /*
    * @param : null
    * function used to include elastic library and create its object.
    */
    private function elasticsearch_include_its_library_prepare_obj()
    {
        if (empty($this->elastic_index_array)) {
            echo "Cant fetch for empty / No index found on elastic server";
            throw new Exception("Cant fetch for empty / No index found on elastic server", 3);
        }



        $this->elastic_obj = Elasticsearch\ClientBuilder::create()
            ->setSSLVerification(false)
            ->setHosts([ELSTIC_IP.':'.ELSTIC_PORT])->build();

        // Elastic library included and its object is ready
    }
/*
    @sahil
    *fetch_the_es_data_with_scroll_dump_csv
    */
    private function fetch_the_es_data_with_scroll_dump_csv($index_name){
        $params = array(
            "scroll" => "5m",
            "size" => 100,
            'index' => $index_name,
            "_source_excludes"=>["@version","@timestamp"]
        );
        $client=$this->elastic_obj;
        $docs = $client->search($params);
        $scroll_id = $docs['_scroll_id'];
        // echo "<pre>";
        // print_r($docs);
        // echo "</pre>";
        
        $tablename= $params['index'];
        $dockeys=array_keys($docs['hits']['hits'][0]['_source']);
        
        $keys = array_flip($dockeys);
            $fieldsArray = array_fill_keys(array_keys($keys),"");
        // echo $row;
        $source_data = array_pluck( $docs['hits']['hits'], '_source' );
        $this->csvFileObject="./ForSql_importCSV/".$tablename.".csv";
        // $thecsv="./ForSql_importCSV/".$tablename.".csv";
        jsonToCsv($source_data,$this->csvFileObject);
        // echo $tablename;
        // exit;
        $fp = fopen($this->csvFileObject, 'a');
        while (\true) {
            $response = $client->scroll(
                array(
                    "scroll_id" => $scroll_id,
                    "scroll" => "5m"
                )
            );
            
            if (count($response['hits']['hits']) > 0) {
                // echo "Do Work Here";
                // echo "<pre>";
                // print_r($response['hits']['hits']);
                // echo "</pre>";
                
                // echo "<br>";
                // Get new scroll_id
                // $source_datainscroll = array_pluck( $response['hits']['hits'], '_source' );
                // echo "<pre>";
                // print_r($source_datainscroll);
                // echo "</pre>";
                // $row = array_replace($fieldsArray,$source_datainscroll);
                // echo "<pre>row";
                // print_r($row);
                // echo "</pre>";
                $fp = fopen($this->csvFileObject, 'a');
                foreach ($response['hits']['hits'] as $fields) {
                    
                    fputcsv($fp,$fields['_source']);
                }
                
                
                $scroll_id = $response['_scroll_id'];
            } else {

                fclose($fp);
                // All done scrolling over data
                echo $tablename;
                echo "<br>All done scrolling over data<br>";
                break;
            }
        }
        
    }
    /*
    * @param : null
    * function used to fetch from elastic and load the dependence of elastic library
    */
    private function fetch_from_elastic_put_in_db()
    {   echo 'index found via elastic_index_array<pre> ';
        print_r($this->elastic_index_array);
        echo '</pre>';
        
        foreach ($this->elastic_index_array as $key => $value) {
            $othertables=[];
        //    $othertables= ['custom_blogs_tag','custom_blogs_tag_relation','custom_blogs_tag_relation','custom_blogs'];
        //    $blogtables= ['custom_tag_category','custom_portfolio','custom_carrier_post','custom_portfolio_category','custom_portfolio_technologies_rel','custom_portfolio_category_rel','custom_team'];
           $blogtables= ['custom_carrier_post'];
           $othertableBool=in_array($key, $othertables)??false;
           $blogtablesBool=in_array($key, $blogtables);
           if ($value['health'] == 'yellow' && $value['status'] == 'open' && $value['total'] !=0 && $othertableBool||$blogtablesBool) {
                
                
                $param['index'] = $key;
                $param['_source_excludes'] = ["@version","@timestamp"];
                $param['size'] = 2;
                $current_index_result = $this->elastic_obj->search($param);
                echo '<pre> number of '.$key;
                print_r($current_index_result['hits']['total']);
                echo '</pre>';
                $columns = '';

                $this->neglet_indexes = array(
                    '@version',
                    '@timestamp',
                );

                $all_columns = [];
                foreach ($current_index_result['hits']['hits'][0]['_source'] as $key => $value) {
                    if (!in_array($key, $this->neglet_indexes)) {
                        $all_columns[] = $key;
                    }
                }

                foreach ($all_columns as $key) {
                    if ($key == end($all_columns)) {
                        $columns .= $key . ' LONGTEXT';
                    } else {
                        $columns .= $key . ' LONGTEXT ,';
                    }
                }

                $drop_sql_table = 'DROP TABLE IF EXISTS '.$current_index_result['hits']['hits'][0]['_index'];
                $this->pdo_conn->exec($drop_sql_table);

                $sql = "CREATE TABLE IF NOT EXISTS ".$current_index_result['hits']['hits'][0]['_index']." (
			    $columns
			    )";

                // use exec() because no results are returned
                $this->pdo_conn->exec($sql);

                // echo '<pre>';
                // print_r($current_index_result);
                // echo '</pre>';

                //wp_die();
                $this->fetch_the_es_data_with_scroll_dump_csv($current_index_result['hits']['hits'][0]['_index']);
               
                $this->insert_csv_in_db($current_index_result['hits']['hits'][0]['_index']);
                // $this->check_db_for_index($current_index_result);
                echo "<br>";
                echo "Data stored successfully in ".$current_index_result['hits']['hits'][0]['_index'];
            } else {
                $this->errors[$key] = [ $value['status'], $value['health'] ];
               
            
            }
        }
        echo "<br>";
        echo "<pre>";
        echo "error ".print_r($this->$errors);
        echo "</pre>";
    }

    /*
    * Param : 'Total' -> 1, { holds index data from elastic search, type: array }
    * Store data to DB
    */
    private function insert_csv_in_db($tablename){
        echo '<br>'.$tablename;
        echo "<br>";
        $folder= $this->home_url('/ForSql_importCSV/');
        
         echo $folder;
         
        $sqlquery="LOAD DATA LOCAL INFILE '".$folder.$tablename.".csv'
                                    INTO TABLE ".$tablename."
                                    FIELDS TERMINATED BY ','
                                    OPTIONALLY ENCLOSED BY '\"'
                                    LINES TERMINATED BY '\n'
                                    IGNORE 1 ROWS";
                                    echo "<br>";
                                    echo $sqlquery;
                                    
        $this->pdo_conn->exec($sqlquery);
    }
    private function check_db_for_index($data)
    {
        if (empty($data['hits']['hits'])) {
            echo "no index found";
            return;
        }
        $tablename= $data['hits']['hits'][0]['_index'];
        
        $source_data = array_pluck( $data['hits']['hits'], '_source' );
        jsonToCsv($source_data,"./ForSql_importCSV/".$tablename.".csv");
        echo $tablename;
        echo "<br>";
        $folder= $this->home_url('/ForSql_importCSV/');
        // echo $folder;
        // exit;
        $sqlquery="LOAD DATA LOCAL INFILE '".$folder.$tablename.".csv'
                                    INTO TABLE ".$tablename."
                                    FIELDS TERMINATED BY ','
                                    OPTIONALLY ENCLOSED BY '\"'
                                    LINES TERMINATED BY '\n'
                                    IGNORE 1 ROWS";
                                    echo "<br>";
                                    echo $sqlquery;
                                    
        $this->pdo_conn->exec($sqlquery);
        
        
    }

    /*
    * @param null
    * connect to DB using mysqli
    */
    private function create_mysqli_connection()
    {
        $this->mysqli_conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD);

        if ($this->mysqli_conn->connect_error) {
            echo "cant connect to db";
            throw new Exception("Cant connect to DB", 5);
        }
    }

    /*
    * @param null
    * connect to DB using pdo
    */
    private function create_pdo_connection()
    {
        $host     = DB_HOST;//Ip of database, in this case my host machine    
        $user     = DB_USER;	//Username to use
        $pass     = DB_PASSWORD;//Password for that user
        $dbname   = DB_NAME;//Name of the database
        $this->pdo_conn = new pdo("mysql:host=$host;dbname=$dbname", $user, $pass,array(PDO::MYSQL_ATTR_LOCAL_INFILE => true));
        $this->pdo_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected to db successfully";
    }
    
    /*
    * Param : 'Total' -> 1, { 'name' -> $param 'type' -> Exception }
    * Generate log file for monitoring operation of our cron file.
    */
    public static function generate_log($param)
    {

        //Something to write to txt log
        $log  = "User: ".$_SERVER['REMOTE_ADDR'].' - '.date("F j, Y, g:i a").PHP_EOL.
                "Reason :" . $param ." ". PHP_EOL.
                "-------------------------".PHP_EOL;
        //Save string to log, use FILE_APPEND to append.
        file_put_contents( 'ForSql_importCSV/elastic_sync.log', $log, FILE_APPEND);
    }
  
    public static function home_url($p){ 
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')   {
    $url = "https://";   }else  {
    $url = "http://";   }
// Append the host(domain name, ip) to the URL.   
$url.= $_SERVER['HTTP_HOST'];   

// Append the requested resource location to the URL   
$url.= $_SERVER['REQUEST_URI'];    
 
return $url.$p;  }
}

try {
    $elastic_obj = new elastic_to_db_custom();
} catch (Exception $e) {
    echo "<pre>";
    print_r($e);
    echo "</pre>";
    
    elastic_to_db_custom::generate_log($e);
}
// SET GLOBAL local_infile=1;
// show global variables like 'local_infile';
?>