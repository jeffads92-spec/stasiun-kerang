<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$file = __DIR__ . '/index.html';
if (file_exists($file)) {
    header('Content-Type: text/html');
    readfile($file);
} else {
    http_response_code(404);
    echo 'File not found. Current dir: ' . __DIR__;
}
?>
