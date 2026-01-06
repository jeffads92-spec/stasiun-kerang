<?php
/**
 * Payment Processing API
 * Handle payment transactions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['order_id', 'amount', 'payment_method'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            sendResponse(400, false, "Field {$field} is required");
            return;
        }
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
        
        // Verify amount matches order total
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
    $orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
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
            
            sendResponse(200, true, 'Payment history retrieved', ['payments' => $payments]);
        }
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getPaymentMethods($pdo) {
    try {
        // Get enabled payment methods from settings
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
