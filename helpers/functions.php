<?php
/**
 * Helper Functions
 */

function generateOrderNumber($pdo) {
    $date = date('Ymd');
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $count = $stmt->fetch()['count'] + 1;
    
    return 'ORD-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function generatePaymentNumber($pdo) {
    $date = date('Ymd');
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $count = $stmt->fetch()['count'] + 1;
    
    return 'PAY-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function calculateTax($subtotal) {
    // Default tax rate 10%
    $taxRate = 0.10;
    return round($subtotal * $taxRate, 2);
}

function sendResponse($code, $success, $message, $data = null) {
    http_response_code($code);
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit();
}

function requireAuth() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        sendResponse(401, false, 'Unauthorized');
        exit();
    }
}

function requireRole($allowedRoles) {
    session_start();
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
        sendResponse(403, false, 'Forbidden: Insufficient permissions');
        exit();
    }
}
?>
