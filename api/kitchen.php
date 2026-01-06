<?php
/**
 * Kitchen Display API
 * Manages kitchen order queue and status updates
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../helpers/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentication required
session_start();
if (!isset($_SESSION['user_id'])) {
    sendResponse(401, false, 'Unauthorized');
    exit();
}

// Hanya kitchen dan admin yang bisa mengakses
if ($_SESSION['role'] !== 'kitchen' && $_SESSION['role'] !== 'admin') {
    sendResponse(403, false, 'Forbidden: Kitchen and admin only');
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    $pdo = getDbConnection();
    
    switch ($method) {
        case 'GET':
            if ($action === 'queue') {
                getKitchenQueue($pdo);
            } elseif ($action === 'stats') {
                getKitchenStats($pdo);
            } else {
                getActiveOrders($pdo);
            }
            break;
            
        case 'PUT':
            updateOrderItemStatus($pdo);
            break;
            
        case 'POST':
            if ($action === 'start') {
                startCooking($pdo);
            } elseif ($action === 'complete') {
                markAsReady($pdo);
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

function getActiveOrders($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, t.table_number,
                   TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as elapsed_time
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            WHERE o.status IN ('pending', 'preparing')
            AND DATE(o.created_at) = CURDATE()
            ORDER BY 
                CASE o.status
                    WHEN 'preparing' THEN 1
                    WHEN 'pending' THEN 2
                    ELSE 3
                END,
                o.created_at ASC
        ");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get items for each order
        foreach ($orders as &$order) {
            $stmt = $pdo->prepare("
                SELECT oi.*, m.name, m.preparation_time, m.image
                FROM order_items oi
                JOIN menu_items m ON oi.menu_item_id = m.id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order['id']]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        sendResponse(200, true, 'Active orders retrieved', ['orders' => $orders]);
        
    } catch (PDOException $e) {
        error_log("Get active orders error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function getKitchenQueue($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT oi.*, m.name, m.preparation_time, o.order_number, 
                   o.table_id, t.table_number, o.order_type, o.notes as order_notes,
                   TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as elapsed_time
            FROM order_items oi
            JOIN menu_items m ON oi.menu_item_id = m.id
            JOIN orders o ON oi.order_id = o.id
            LEFT JOIN tables t ON o.table_id = t.id
            WHERE oi.status IN ('pending', 'preparing')
            AND DATE(o.created_at) = CURDATE()
            ORDER BY 
                CASE oi.status
                    WHEN 'preparing' THEN 1
                    WHEN 'pending' THEN 2
                    ELSE 3
                END,
                o.created_at ASC,
                oi.id ASC
        ");
        $stmt->execute();
        $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, true, 'Kitchen queue retrieved', ['queue' => $queue]);
        
    } catch (PDOException $e) {
        error_log("Get kitchen queue error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function getKitchenStats($pdo) {
    try {
        // Pending orders
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'pending' AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $pending = $stmt->fetch()['count'];
        
        // Preparing orders
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'preparing' AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $preparing = $stmt->fetch()['count'];
        
        // Ready orders
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'ready' AND DATE(created_at) = CURDATE()");
        $stmt->execute();
        $ready = $stmt->fetch()['count'];
        
        // Late orders (over 30 minutes)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM orders 
            WHERE status IN ('pending', 'preparing') 
            AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) > 30
            AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $late = $stmt->fetch()['count'];
        
        sendResponse(200, true, 'Kitchen stats retrieved', [
            'pending' => (int)$pending,
            'preparing' => (int)$preparing,
            'ready' => (int)$ready,
            'late' => (int)$late
        ]);
        
    } catch (PDOException $e) {
        error_log("Get kitchen stats error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function startCooking($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['order_id'])) {
        sendResponse(400, false, 'Order ID required');
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update order status
        $stmt = $pdo->prepare("UPDATE orders SET status = 'preparing', updated_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$data['order_id']]);
        
        // Update all order items
        $stmt = $pdo->prepare("UPDATE order_items SET status = 'preparing' WHERE order_id = ? AND status = 'pending'");
        $stmt->execute([$data['order_id']]);
        
        $pdo->commit();
        
        sendResponse(200, true, 'Order started cooking');
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Start cooking error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function markAsReady($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['order_id'])) {
        sendResponse(400, false, 'Order ID required');
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update order status
        $stmt = $pdo->prepare("UPDATE orders SET status = 'ready', updated_at = NOW() WHERE id = ? AND status = 'preparing'");
        $stmt->execute([$data['order_id']]);
        
        // Update all order items
        $stmt = $pdo->prepare("
            UPDATE order_items 
            SET status = 'ready', prepared_at = NOW() 
            WHERE order_id = ? AND status = 'preparing'
        ");
        $stmt->execute([$data['order_id']]);
        
        $pdo->commit();
        
        sendResponse(200, true, 'Order marked as ready');
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Mark as ready error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function updateOrderItemStatus($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['item_id']) || !isset($data['status'])) {
        sendResponse(400, false, 'Item ID and status required');
        return;
    }
    
    // Validate status
    $allowedStatuses = ['pending', 'preparing', 'ready'];
    if (!in_array($data['status'], $allowedStatuses)) {
        sendResponse(400, false, 'Invalid status');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE order_items 
            SET status = ?, 
                prepared_at = IF(? = 'ready', NOW(), prepared_at),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$data['status'], $data['status'], $data['item_id']]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(200, true, 'Order item status updated');
        } else {
            sendResponse(404, false, 'Order item not found');
        }
        
    } catch (PDOException $e) {
        error_log("Update order item status error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}
?>
