<?php
// Legacy compatibility shim - new code should use db.php (PDO)
require_once __DIR__ . '/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset(DB_CHARSET);

if ($conn->connect_error) {
    http_response_code(503);
    exit('Service unavailable');
}
