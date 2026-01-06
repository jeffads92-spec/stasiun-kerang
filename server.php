<?php
// server.php - Main router
$request = $_SERVER['REQUEST_URI'];

// Remove query string
$request_path = parse_url($request, PHP_URL_PATH);
$request = $request_path ?: $request;

// API routing
if (strpos($request, '/api/') === 0) {
    // Remove '/api' prefix
    $api_path = substr($request, 4);
    // Map to the actual API file
    $api_file = __DIR__ . '/api' . $api_path;
    if (file_exists($api_file)) {
        require_once $api_file;
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found']);
        exit;
    }
}

// Map routes for static pages
$routes = [
    '/' => 'index.html',
    '/dashboard' => 'dashboard.html',
    '/orders' => 'orders.html',
    '/menu-management' => 'menu-management.html',
    '/kitchen' => 'kitchen.html',
    '/reports' => 'reports.html',
    '/settings' => 'settings.html'
];

// Check for direct HTML file
if (preg_match('/\.html$/', $request)) {
    $file = __DIR__ . $request;
    if (file_exists($file)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        exit;
    }
}

// Check route mapping
if (isset($routes[$request])) {
    $file = __DIR__ . '/' . $routes[$request];
    if (file_exists($file)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($file);
        exit;
    }
}

// Default to index.html
if (file_exists(__DIR__ . '/index.html')) {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    exit;
}

// 404
header('HTTP/1.0 404 Not Found');
echo 'Page not found';
?>
