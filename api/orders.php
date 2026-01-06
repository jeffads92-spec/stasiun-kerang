<?php
/**
 * Orders Management API
 * CRUD operations for orders
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Cek otentikasi
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

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    $pdo = getDbConnection();
    
    switch ($method) {
        case 'GET':
            if ($id > 0) {
                getOrderById($pdo, $id);
            } else {
                getAllOrders($pdo);
            }
            break;
            
        case 'POST':
            createOrder($pdo);
            break;
            
        case 'PUT':
            updateOrder($pdo, $id);
            break;
            
        case 'DELETE':
            deleteOrder($pdo, $id);
            break;
            
        default:
            sendResponse(405, false, 'Method not allowed');
    }
    
} catch (Exception $e) {
    sendResponse(500, false, 'Server error: ' . $e->getMessage());
}

function getAllOrders($pdo) {
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    try {
        $sql = "SELECT o.*, t.table_number, u.full_name as waiter_name,
                COUNT(oi.id) as total_items
                FROM orders o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE DATE(o.created_at) = ?";
        
        $params = [$date];
        
        if (!empty($status)) {
            $sql .= " AND o.status = ?";
            $params[] = $status;
        }
        
        $sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        sendResponse(200, true, 'Orders retrieved successfully', ['orders' => $orders]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getOrderById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, t.table_number, u.full_name as waiter_name
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            sendResponse(404, false, 'Order not found');
            return;
        }
        
        // Get order items
        $stmt = $pdo->prepare("
            SELECT oi.*, m.name as menu_name, m.image
            FROM order_items oi
            JOIN menu_items m ON oi.menu_item_id = m.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$id]);
        $order['items'] = $stmt->fetchAll();
        
        sendResponse(200, true, 'Order retrieved successfully', ['order' => $order]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function generateOrderNumber($pdo) {
    $prefix = 'ORD';
    $date = date('Ymd');
    
    // Cari nomor terakhir hari ini
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $count = $stmt->fetch()['count'] + 1;
    
    return $prefix . '-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

function calculateTax($subtotal) {
    // Asumsi pajak 10%
    return $subtotal * 0.10;
}

function createOrder($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['table_id', 'items', 'order_type'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            sendResponse(400, false, "Field {$field} is required");
            return;
        }
    }
    
    if (empty($data['items'])) {
        sendResponse(400, false, 'Order must have at least one item');
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Generate order number
        $orderNumber = generateOrderNumber($pdo);
        
        // Calculate totals
        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        $tax = calculateTax($subtotal);
        $serviceCharge = $data['order_type'] === 'dine_in' ? $subtotal * 0.05 : 0;
        $discount = isset($data['discount']) ? $data['discount'] : 0;
        $total = $subtotal + $tax + $serviceCharge - $discount;
        
        // Insert order
        $stmt = $pdo->prepare("
            INSERT INTO orders (order_number, table_id, customer_name, customer_phone, 
                               user_id, order_type, status, subtotal, tax, discount, 
                               service_charge, total, notes)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $orderNumber,
            $data['table_id'],
            $data['customer_name'] ?? null,
            $data['customer_phone'] ?? null,
            $data['user_id'] ?? $_SESSION['user_id'],
            $data['order_type'],
            $subtotal,
            $tax,
            $discount,
            $serviceCharge,
            $total,
            $data['notes'] ?? null
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // Insert order items
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, menu_item_id, quantity, price, subtotal, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($data['items'] as $item) {
            $itemSubtotal = $item['price'] * $item['quantity'];
            $stmt->execute([
                $orderId,
                $item['menu_item_id'],
                $item['quantity'],
                $item['price'],
                $itemSubtotal,
                $item['notes'] ?? null
            ]);
        }
        
        // Update table status
        if ($data['order_type'] === 'dine_in') {
            $stmt = $pdo->prepare("UPDATE tables SET status = 'occupied' WHERE id = ?");
            $stmt->execute([$data['table_id']]);
        }
        
        $pdo->commit();
        
        sendResponse(201, true, 'Order created successfully', [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'total' => $total
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function updateOrder($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid order ID');
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        // Check if order exists
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            sendResponse(404, false, 'Order not found');
            return;
        }
        
        // Update order
        $updateFields = [];
        $params = [];
        
        if (isset($data['status'])) {
            $updateFields[] = "status = ?";
            $params[] = $data['status'];
            
            // If status is completed, set completed_at
            if ($data['status'] === 'completed') {
                $updateFields[] = "completed_at = NOW()";
            }
        }
        
        if (isset($data['notes'])) {
            $updateFields[] = "notes = ?";
            $params[] = $data['notes'];
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE orders SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            sendResponse(200, true, 'Order updated successfully');
        } else {
            sendResponse(400, false, 'No fields to update');
        }
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function deleteOrder($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid order ID');
        return;
    }
    
    try {
        // Soft delete by updating status
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(200, true, 'Order cancelled successfully');
        } else {
            sendResponse(404, false, 'Order not found');
        }
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
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
