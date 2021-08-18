<?php
  /*
    * 
    * Based on (forked from) the work by https://gist.github.com/Kostanos
    *
    * This revision allows the PHP file to be included/required in another PHP file and called as a function, rather than focusing on command line usage.
    * 
    * Convert JSON file to CSV and output it.
    *
    * JSON should be an array of objects, dictionaries with simple data structure
    * and the same keys in each object.
    * The order of keys it took from the first element.
    *
    * Example:
    * json:
    * [
    *  { "key1": "value", "kye2": "value", "key3": "value" },
    *  { "key1": "value", "kye2": "value", "key3": "value" },
    *  { "key1": "value", "kye2": "value", "key3": "value" }
    * ]
    *
    * The csv output: (keys will be used for first row):
    * 1. key1, key2, key3
    * 2. value, value, value
    * 3. value, value, value
    * 4. value, value, value
    *
    * Usage:
    * 
    *     require '/path/to/json-to-csv.php';
    *     
    *     // echo a JSON string as CSV
    *     jsonToCsv($strJson);
    *     
    *     // echo an arrayJSON string as CSV
    *     jsonToCsv($arrJson);
    *     
    *     // save a JSON string as CSV file
    *     jsonToCsv($strJson,"/save/path/csvFile.csv");
    *     
    *     // save a JSON string as CSV file through the browser (no file saved on server)
    *     jsonToCsv($strJson,false,true);
    *     
    *     
  */
  
  function jsonToCsv ($json, $csvFilePath = false, $boolOutputFile = false) {
    
    // See if the string contains something
    if (empty($json)) { 
      die("The JSON string is empty!");
    }
    
    // If passed a string, turn it into an array
    if (is_array($json) === false) {
      $json = json_decode($json, true);
    }
    
    // If a path is included, open that file for handling. Otherwise, use a temp file (for echoing CSV string)
    if ($csvFilePath !== false) {
      $f = fopen($csvFilePath,'w+');
      if ($f === false) {
        die("Couldn't create the file to store the CSV, or the path is invalid,or folder doesn't have write permission. Make sure you're including the full path, INCLUDING the name of the output file (e.g. '../save/path/csvOutput.csv')");
      }
    }
    else {
      $boolEchoCsv = true;
      if ($boolOutputFile === true) {
        $boolEchoCsv = false;
      }
      $strTempFile = 'csvOutput' . date("U") . ".csv";
      $f = fopen($strTempFile,"w+");
    }
    
    $firstLineKeys = false;
    foreach ($json as $line) {
      if (empty($firstLineKeys)) {
        $firstLineKeys = array_keys($line);
        fputcsv($f, $firstLineKeys);
        $firstLineKeys = array_flip($firstLineKeys);
      }
      
      // Using array_merge is important to maintain the order of keys acording to the first element
      fputcsv($f, array_merge($firstLineKeys, $line));
    }
    fclose($f);
    
    // Take the file and put it to a string/file for output (if no save path was included in function arguments)
    if ($boolOutputFile === true) {
      if ($csvFilePath !== false) {
        $file = $csvFilePath;
      }
      else {
        $file = $strTempFile;
      }
      
      // Output the file to the browser (for open/save)
      if (file_exists($file)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename='.basename($file));
        header('Content-Length: ' . filesize($file));
        readfile($file);
      }
    }
    elseif ($boolEchoCsv === true) {
      if (($handle = fopen($strTempFile, "r")) !== FALSE) {
        while (($data = fgetcsv($handle)) !== FALSE) {
          echo implode(",",$data);
          echo "<br />";
        }
        fclose($handle);
      }
    }
    
    // Delete the temp file
    unlink($strTempFile);
    
  }
   function array_pluck($array, $key) {
    return array_map(function($v) use ($key) {
      return is_object($v) ? $v->$key : $v[$key];
    }, $array);
}
?>