<?php
/**
 * Orders Management API
 * CRUD operations for orders
 */

header('Content-Type: application/json');
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

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
    // Only authenticated users can view orders
    requireAuth();
    
    // Additional role check: admin, cashier, waiter, kitchen can view orders
    $role = getCurrentUserRole();
    $allowedRoles = ['admin', 'cashier', 'waiter', 'kitchen'];
    if (!in_array($role, $allowedRoles)) {
        sendResponse(403, false, 'Forbidden: Insufficient permissions');
        return;
    }
    
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    // Validate date format
    if (!strtotime($date)) {
        sendResponse(400, false, 'Invalid date format');
        return;
    }
    
    // Validate limit and offset
    if ($limit < 1 || $limit > 1000) {
        $limit = 100;
    }
    if ($offset < 0) {
        $offset = 0;
    }
    
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
            // Validate status
            $validStatuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                sendResponse(400, false, 'Invalid status');
                return;
            }
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
    // Only authenticated users can view order details
    requireAuth();
    
    // Additional role check
    $role = getCurrentUserRole();
    $allowedRoles = ['admin', 'cashier', 'waiter', 'kitchen'];
    if (!in_array($role, $allowedRoles)) {
        sendResponse(403, false, 'Forbidden: Insufficient permissions');
        return;
    }
    
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

function createOrder($pdo) {
    // Only authenticated users can create orders
    requireAuth();
    
    // Only admin, cashier, waiter can create orders
    $role = getCurrentUserRole();
    $allowedRoles = ['admin', 'cashier', 'waiter'];
    if (!in_array($role, $allowedRoles)) {
        sendResponse(403, false, 'Forbidden: Insufficient permissions');
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['table_id', 'items', 'order_type'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            sendResponse(400, false, "Field {$field} is required");
            return;
        }
    }
    
    if (empty($data['items']) || !is_array($data['items'])) {
        sendResponse(400, false, 'Order must have at least one item');
        return;
    }
    
    // Validate order type
    $validOrderTypes = ['dine_in', 'takeaway', 'delivery'];
    if (!in_array($data['order_type'], $validOrderTypes)) {
        sendResponse(400, false, 'Invalid order type. Must be: ' . implode(', ', $validOrderTypes));
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Generate order number
        $orderNumber = generateOrderNumber($pdo);
        
        // Calculate totals
        $subtotal = 0;
        foreach ($data['items'] as $item) {
            if (!isset($item['menu_item_id']) || !isset($item['quantity']) || !isset($item['price'])) {
                throw new Exception('Each item must have menu_item_id, quantity, and price');
            }
            $subtotal += $item['price'] * $item['quantity'];
        }
        
        $tax = calculateTax($subtotal);
        $serviceCharge = $data['order_type'] === 'dine_in' ? $subtotal * 0.05 : 0;
        $discount = isset($data['discount']) ? floatval($data['discount']) : 0;
        $total = $subtotal + $tax + $serviceCharge - $discount;
        
        // Get current user ID
        $userId = getCurrentUserId();
        
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
            $userId,
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
        
        // Update table status if dine-in
        if ($data['order_type'] === 'dine_in' && $data['table_id']) {
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
    } catch (Exception $e) {
        $pdo->rollBack();
        sendResponse(400, false, $e->getMessage());
    }
}

function updateOrder($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid order ID');
        return;
    }
    
    // Only authenticated users can update orders
    requireAuth();
    
    // Only admin, cashier, waiter, kitchen can update orders based on status
    $role = getCurrentUserRole();
    $allowedRoles = ['admin', 'cashier', 'waiter', 'kitchen'];
    if (!in_array($role, $allowedRoles)) {
        sendResponse(403, false, 'Forbidden: Insufficient permissions');
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
            // Validate status
            $validStatuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
            if (!in_array($data['status'], $validStatuses)) {
                sendResponse(400, false, 'Invalid status');
                return;
            }
            
            // Check permissions for status change
            if ($role === 'waiter' && !in_array($data['status'], ['pending', 'cancelled'])) {
                sendResponse(403, false, 'Waiter can only set status to pending or cancelled');
                return;
            }
            
            if ($role === 'kitchen' && !in_array($data['status'], ['preparing', 'ready'])) {
                sendResponse(403, false, 'Kitchen can only set status to preparing or ready');
                return;
            }
            
            $updateFields[] = "status = ?";
            $params[] = $data['status'];
            
            // If status is completed, set completed_at
            if ($data['status'] === 'completed') {
                $updateFields[] = "completed_at = NOW()";
            }
            
            // If status is cancelled, update table status if dine-in
            if ($data['status'] === 'cancelled' && $order['order_type'] === 'dine_in' && $order['table_id']) {
                $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
                $stmt->execute([$order['table_id']]);
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
    
    // Only admin and cashier can delete (cancel) orders
    requireAuth();
    requireRole(['admin', 'cashier']);
    
    try {
        // Get order details first
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            sendResponse(404, false, 'Order not found');
            return;
        }
        
        // Soft delete by updating status
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        
        // If dine-in, update table status
        if ($order['order_type'] === 'dine_in' && $order['table_id']) {
            $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
            $stmt->execute([$order['table_id']]);
        }
        
        sendResponse(200, true, 'Order cancelled successfully');
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function generateOrderNumber($pdo) {
    $date = date('Ymd');
    
    // Get the last order number for today
    $stmt = $pdo->prepare("
        SELECT MAX(order_number) as last_order 
        FROM orders 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result && $result['last_order']) {
        // Extract the numeric part and increment
        $parts = explode('-', $result['last_order']);
        $lastNumber = intval(end($parts));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return 'ORD-' . $date . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

function calculateTax($subtotal) {
    // Assuming tax rate is 10%
    $taxRate = 0.10;
    return $subtotal * $taxRate;
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
