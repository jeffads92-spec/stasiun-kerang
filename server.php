<?php
// server.php - Router sederhana
$request = $_SERVER['REQUEST_URI'];
$request = strtok($request, '?'); // Hapus query string

// Jika request ke API, arahkan ke folder api
if (strpos($request, '/api/') === 0) {
    $apiFile = __DIR__ . '/api' . substr($request, 4);
    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found']);
        exit;
    }
}

// Jika request ke file HTML, sajikan file HTML
if (preg_match('/\.html$/', $request)) {
    $file = __DIR__ . $request;
    if (file_exists($file)) {
        header('Content-Type: text/html');
        readfile($file);
        exit;
    }
}

// Routing untuk halaman utama
if ($request === '/' || $request === '') {
    $file = __DIR__ . '/index.html';
    if (file_exists($file)) {
        header('Content-Type: text/html');
        readfile($file);
        exit;
    }
}

// Jika tidak ada file, coba cari file dengan ekstensi .html
$file = __DIR__ . $request . '.html';
if (file_exists($file)) {
    header('Content-Type: text/html');
    readfile($file);
    exit;
}

// Jika tidak ditemukan, kembalikan 404
http_response_code(404);
echo '404 - Page not found';
