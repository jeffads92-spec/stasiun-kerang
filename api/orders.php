<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Try multiple path options
$configPath = __DIR__ . '/../config/database.php';
if (!file_exists($configPath)) {
    $configPath = $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
}
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__) . '/config/database.php';
}

if (!file_exists($configPath)) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database configuration file not found',
        'timestamp' => date('Y-m-d H:i:s')
    ]));
}

require_once $configPath;

// Check authentication - COMMENTED OUT FOR TESTING
// Uncomment this after login is working
/*
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login first.',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}
*/

try {
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get specific order with items
                $stmt = $db->prepare("
                    SELECT 
                        o.*,
                        u.username as created_by_name,
                        t.table_number
                    FROM orders o
                    LEFT JOIN users u ON o.created_by = u.id
                    LEFT JOIN tables t ON o.table_id = t.id
                    WHERE o.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$order) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Order not found',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    exit();
                }
                
                // Get order items
                $stmt = $db->prepare("
                    SELECT 
                        oi.*,
                        m.name as menu_name,
                        m.price as menu_price
                    FROM order_items oi
                    JOIN menu_items m ON oi.menu_item_id = m.id
                    WHERE oi.order_id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $order,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Get all orders
                $query = "
                    SELECT 
                        o.*,
                        u.username as created_by_name,
                        t.table_number,
                        COUNT(oi.id) as item_count
                    FROM orders o
                    LEFT JOIN users u ON o.created_by = u.id
                    LEFT JOIN tables t ON o.table_id = t.id
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    WHERE 1=1
                ";
                
                $params = [];
                
                // Filter by status
                if (isset($_GET['status']) && $_GET['status'] !== '') {
                    $query .= " AND o.status = ?";
                    $params[] = $_GET['status'];
                }
                
                // Filter by order type
                if (isset($_GET['order_type']) && $_GET['order_type'] !== '') {
                    $query .= " AND o.order_type = ?";
                    $params[] = $_GET['order_type'];
                }
                
                // Filter by date
                if (isset($_GET['date'])) {
                    $query .= " AND DATE(o.created_at) = ?";
                    $params[] = $_GET['date'];
                }
                
                $query .= " GROUP BY o.id ORDER BY o.created_at DESC";
                
                // Pagination
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                $offset = ($page - 1) * $limit;
                
                $query .= " LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get total count
                $countQuery = "SELECT COUNT(*) as total FROM orders WHERE 1=1";
                $countParams = [];
                
                if (isset($_GET['status']) && $_GET['status'] !== '') {
                    $countQuery .= " AND status = ?";
                    $countParams[] = $_GET['status'];
                }
                
                if (isset($_GET['order_type']) && $_GET['order_type'] !== '') {
                    $countQuery .= " AND order_type = ?";
                    $countParams[] = $_GET['order_type'];
                }
                
                if (isset($_GET['date'])) {
                    $countQuery .= " AND DATE(created_at) = ?";
                    $countParams[] = $_GET['date'];
                }
                
                $stmt = $db->prepare($countQuery);
                $stmt->execute($countParams);
                $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                echo json_encode([
                    'success' => true,
                    'data' => $orders,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => (int)$total,
                        'total_pages' => ceil($total / $limit)
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            break;
            
        case 'POST':
            // Create new order
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if (!isset($data['order_type']) || !isset($data['items']) || empty($data['items'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields: order_type and items are required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit();
            }
            
            $db->beginTransaction();
            
            try {
                // Generate order number
                $orderNumber = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Insert order
                $stmt = $db->prepare("
                    INSERT INTO orders 
                    (order_number, table_id, customer_name, customer_phone, order_type, status, subtotal, tax, service_charge, total, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?)
                ");
                
                $subtotal = 0;
                foreach ($data['items'] as $item) {
                    $subtotal += $item['price'] * $item['quantity'];
                }
                
                $taxRate = 0.10; // 10%
                $serviceChargeRate = 0.05; // 5%
                
                $tax = $subtotal * $taxRate;
                $serviceCharge = $subtotal * $serviceChargeRate;
                $total = $subtotal + $tax + $serviceCharge;
                
                $userId = $_SESSION['user_id'] ?? 1; // Default to 1 if no session
                
                $stmt->execute([
                    $orderNumber,
                    $data['table_id'] ?? null,
                    $data['customer_name'] ?? null,
                    $data['customer_phone'] ?? null,
                    $data['order_type'],
                    $subtotal,
                    $tax,
                    $serviceCharge,
                    $total,
                    $data['notes'] ?? null,
                    $userId
                ]);
                
                $orderId = $db->lastInsertId();
                
                // Insert order items
                $stmt = $db->prepare("
                    INSERT INTO order_items 
                    (order_id, menu_item_id, quantity, price, subtotal, notes)
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
                
                // Update table status if table_id is provided
                if (isset($data['table_id']) && $data['table_id']) {
                    $stmt = $db->prepare("UPDATE tables SET status = 'Occupied' WHERE id = ?");
                    $stmt->execute([$data['table_id']]);
                }
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Order created successfully',
                    'data' => [
                        'id' => $orderId,
                        'order_number' => $orderNumber,
                        'total' => $total
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            // Update order
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Order ID is required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit();
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updates = [];
            $params = [];
            
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
                
                // If status is Completed or Cancelled, update completed_at
                if (in_array($data['status'], ['Completed', 'Cancelled'])) {
                    $updates[] = "completed_at = NOW()";
                }
            }
            
            if (isset($data['customer_name'])) {
                $updates[] = "customer_name = ?";
                $params[] = $data['customer_name'];
            }
            
            if (isset($data['customer_phone'])) {
                $updates[] = "customer_phone = ?";
                $params[] = $data['customer_phone'];
            }
            
            if (isset($data['notes'])) {
                $updates[] = "notes = ?";
                $params[] = $data['notes'];
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No fields to update',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit();
            }
            
            $params[] = $_GET['id'];
            
            $stmt = $db->prepare("
                UPDATE orders 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            
            $result = $stmt->execute($params);
            
            if ($result) {
                // If order is completed or cancelled, free up the table
                if (isset($data['status']) && in_array($data['status'], ['Completed', 'Cancelled'])) {
                    $stmt = $db->prepare("
                        UPDATE tables t
                        JOIN orders o ON t.id = o.table_id
                        SET t.status = 'Available'
                        WHERE o.id = ?
                    ");
                    $stmt->execute([$_GET['id']]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Order updated successfully',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update order',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            break;
            
        case 'DELETE':
            // Cancel order
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Order ID is required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit();
            }
            
            $db->beginTransaction();
            
            try {
                // Update order status to Cancelled
                $stmt = $db->prepare("
                    UPDATE orders 
                    SET status = 'Cancelled', completed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$_GET['id']]);
                
                // Free up table if any
                $stmt = $db->prepare("
                    UPDATE tables t
                    JOIN orders o ON t.id = o.table_id
                    SET t.status = 'Available'
                    WHERE o.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                
                $db->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Order cancelled successfully',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
