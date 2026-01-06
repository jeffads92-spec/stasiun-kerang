<?php
/**
 * Payment Processing API
 * Handle payment transactions
 */

header('Content-Type: application/json');
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    $pdo = getDbConnection();
    
    switch ($method) {
        case 'POST':
            if ($action === 'process') {
                processPayment($pdo);
            } else {
                sendResponse(400, false, 'Invalid action');
            }
            break;
            
        case 'GET':
            if ($action === 'history') {
                getPaymentHistory($pdo);
            } elseif ($action === 'methods') {
                getPaymentMethods($pdo);
            } else {
                sendResponse(400, false, 'Invalid action');
            }
            break;
            
        default:
            sendResponse(405, false, 'Method not allowed');
    }
    
} catch (Exception $e) {
    sendResponse(500, false, 'Server error: ' . $e->getMessage());
}

function processPayment($pdo) {
    // Only authenticated users can process payments
    requireAuth();
    
    // Only admin and cashier can process payments
    requireRole(['admin', 'cashier']);
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['order_id', 'amount', 'payment_method'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            sendResponse(400, false, "Field {$field} is required");
            return;
        }
    }
    
    // Validate payment method
    $validMethods = ['cash', 'card', 'qr_code', 'transfer'];
    if (!in_array($data['payment_method'], $validMethods)) {
        sendResponse(400, false, 'Invalid payment method');
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Verify order exists and get total
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$data['order_id']]);
        $order = $stmt->fetch();
        
        if (!$order) {
            $pdo->rollBack();
            sendResponse(404, false, 'Order not found');
            return;
        }
        
        // Check if order is already paid
        if ($order['status'] === 'completed') {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ?");
            $stmt->execute([$data['order_id']]);
            if ($stmt->fetch()) {
                $pdo->rollBack();
                sendResponse(400, false, 'Order is already paid');
                return;
            }
        }
        
        // Verify amount matches order total (allow small difference for rounding)
        if (abs($data['amount'] - $order['total']) > 0.01) {
            $pdo->rollBack();
            sendResponse(400, false, 'Payment amount does not match order total');
            return;
        }
        
        // Generate payment number
        $paymentNumber = generatePaymentNumber($pdo);
        
        // Insert payment
        $stmt = $pdo->prepare("
            INSERT INTO payments (payment_number, order_id, amount, payment_method, 
                                 payment_status, transaction_id, paid_at)
            VALUES (?, ?, ?, ?, 'completed', ?, NOW())
        ");
        
        $stmt->execute([
            $paymentNumber,
            $data['order_id'],
            $data['amount'],
            $data['payment_method'],
            $data['transaction_id'] ?? null
        ]);
        
        $paymentId = $pdo->lastInsertId();
        
        // Update order status
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET status = 'completed', completed_at = NOW(), updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$data['order_id']]);
        
        // Update table status if dine-in
        if ($order['order_type'] === 'dine_in' && $order['table_id']) {
            $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
            $stmt->execute([$order['table_id']]);
        }
        
        $pdo->commit();
        
        sendResponse(200, true, 'Payment processed successfully', [
            'payment_id' => $paymentId,
            'payment_number' => $paymentNumber,
            'order_number' => $order['order_number']
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getPaymentHistory($pdo) {
    // Only authenticated users can view payment history
    requireAuth();
    
    // Only admin, cashier can view payment history
    requireRole(['admin', 'cashier']);
    
    $orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Validate dates
    if (!strtotime($startDate) || !strtotime($endDate)) {
        sendResponse(400, false, 'Invalid date format');
        return;
    }
    
    try {
        if ($orderId > 0) {
            // Get payment for specific order
            $stmt = $pdo->prepare("
                SELECT p.*, o.order_number, o.total as order_total
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                WHERE p.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $payment = $stmt->fetch();
            
            if ($payment) {
                sendResponse(200, true, 'Payment retrieved', ['payment' => $payment]);
            } else {
                sendResponse(404, false, 'Payment not found');
            }
        } else {
            // Get payment history
            $stmt = $pdo->prepare("
                SELECT p.*, o.order_number, o.customer_name, o.total as order_total
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                WHERE DATE(p.created_at) BETWEEN ? AND ?
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            $payments = $stmt->fetchAll();
            
            // Calculate totals
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(amount), 0) as total_amount,
                    COUNT(*) as total_transactions
                FROM payments
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$startDate, $endDate]);
            $totals = $stmt->fetch();
            
            sendResponse(200, true, 'Payment history retrieved', [
                'payments' => $payments,
                'summary' => $totals,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]);
        }
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getPaymentMethods($pdo) {
    // This endpoint doesn't require authentication (public)
    try {
        // Get enabled payment methods from settings (simulated)
        $methods = [
            ['id' => 'cash', 'name' => 'Cash', 'enabled' => true],
            ['id' => 'card', 'name' => 'Debit/Credit Card', 'enabled' => true],
            ['id' => 'qr_code', 'name' => 'QR Code (QRIS)', 'enabled' => true],
            ['id' => 'transfer', 'name' => 'Bank Transfer', 'enabled' => true]
        ];
        
        sendResponse(200, true, 'Payment methods retrieved', ['methods' => $methods]);
        
    } catch (Exception $e) {
        sendResponse(500, false, 'Error: ' . $e->getMessage());
    }
}

function generatePaymentNumber($pdo) {
    $date = date('Ymd');
    
    // Get the last payment number for today
    $stmt = $pdo->prepare("
        SELECT MAX(payment_number) as last_payment 
        FROM payments 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result && $result['last_payment']) {
        // Extract the numeric part and increment
        $parts = explode('-', $result['last_payment']);
        $lastNumber = intval(end($parts));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return 'PAY-' . $date . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
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
?>
