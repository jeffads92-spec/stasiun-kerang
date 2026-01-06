<?php
$request = $_SERVER['REQUEST_URI'];

// Jika request ke API, arahkan ke folder api
if (strpos($request, '/api/') === 0) {
    $apiFile = __DIR__ . '/api' . substr($request, 4);
    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    }
}

// Jika request ke file HTML, serve file tersebut
if (preg_match('/\.html$/', $request)) {
    $file = __DIR__ . $request;
    if (file_exists($file)) {
        header('Content-Type: text/html');
        readfile($file);
        exit;
    }
}

// Jika root, arahkan ke index.html
if ($request === '/' || $request === '') {
    header('Content-Type: text/html');
    readfile(__DIR__ . '/index.html');
    exit;
}

// Jika tidak ditemukan, tampilkan 404
http_response_code(404);
echo 'Not Found';
