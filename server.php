<?php
$port = getenv('PORT') ?: 8080;
$host = '0.0.0.0';
echo "Starting server on {$host}:{$port}\n";
passthru("php -S {$host}:{$port} -t .");
?>
