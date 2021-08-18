<?php
// ELSTIC_IP hold for curl operation to change default ip find the elasticsearch plugin in wp-dashbord.
define('ELSTIC_IP', 'localhost');

// Elastic search ip port

define('ELSTIC_PORT', '9200');

// Health status url of elasticsearch provided by devops.
define('CURL_STATUS_CHECK_URI', 'http' . ':' . '//'.ELSTIC_IP.':'.ELSTIC_PORT.'/_cluster/health');

// Curl url for fetching the data of indexs (name,count).
define('CURL_INDEX_CHECK_INFO_URI', 'http' . ':' . '//'.ELSTIC_IP.':'.ELSTIC_PORT.'/_cat/indices?format=json');

// ** MySQL settings - You can get this info from your web host ** //

define( 'DB_NAME', 'mainsiteProd' );

/** MySQL database username */
define( 'DB_USER', 'phpmyadmin' );

/** MySQL database password */
define( 'DB_PASSWORD', '123456' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );
?>