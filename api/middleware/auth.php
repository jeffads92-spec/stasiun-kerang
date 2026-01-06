<?php
/**
 * Authentication Middleware
 */

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

/**
 * Require authentication for the endpoint
 */
function requireAuth() {
    session_start();
    
    // Check if session exists and not expired
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
        sendAuthError('Unauthorized');
        return;
    }
    
    if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
        // Session expired
        session_destroy();
        sendAuthError('Session expired');
        return;
    }
    
    // Extend session
    $_SESSION['login_time'] = time();
}

/**
 * Require specific roles for the endpoint
 * @param array $allowedRoles Roles that are allowed to access
 */
function requireRole($allowedRoles) {
    session_start();
    
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        sendAuthError('Forbidden: Insufficient permissions', 403);
        return;
    }
}

/**
 * Get current authenticated user ID
 * @return int|null User ID or null if not authenticated
 */
function getCurrentUserId() {
    session_start();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current authenticated user role
 * @return string|null User role or null if not authenticated
 */
function getCurrentUserRole() {
    session_start();
    return $_SESSION['role'] ?? null;
}

/**
 * Send authentication error response
 * @param string $message Error message
 * @param int $code HTTP status code
 */
function sendAuthError($message, $code = 401) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}
?>
