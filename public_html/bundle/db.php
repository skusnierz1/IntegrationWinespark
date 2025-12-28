<?php
// db.php — mysqli connection helper
require_once __DIR__ . '/config.php';

$config = require '/home/u394535132/domains/lightgray-vulture-703201.hostingersite.com/config.php';
// === Database connection ===

$host = $config['DB_HOST'];
$user = $config['DB_USER'];
$pass = $config['DB_PASS'];
$dbname = $config['DB_NAME'];


$mysqli = new mysqli($host, $user, $pass, $dbname);
 if ($mysqli->connect_error) {
        http_response_code(500);
        die("DB Connection failed");
    }
$mysqli->set_charset('utf8mb4');
?>




  