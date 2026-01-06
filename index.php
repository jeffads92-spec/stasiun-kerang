<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists('index.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile('index.html');
} else {
    echo 'File index.html tidak ditemukan.';
}
?>
