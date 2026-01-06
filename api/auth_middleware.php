<?php
/**
 * Simple Authentication Middleware
 */

function requireAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
}

function requireRole($allowedRoles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Forbidden: Insufficient permissions',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
}
?>
