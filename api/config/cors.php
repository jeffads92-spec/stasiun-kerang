<?php
/**
 * CORS Configuration
 */

// Allow from any origin (in production, specify your domain)
$allowedOrigins = [
    'http://localhost:3000',
    'http://localhost:8080',
    // Add your production domains here
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // If you want to allow any origin, use:
    // header('Access-Control-Allow-Origin: *');
    // But note: using * with credentials is not allowed
    // So for development, you can set to * but in production specify domains
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400'); // 24 hours

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>
