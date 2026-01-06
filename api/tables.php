<?php
/**
 * Table Management API
 * CRUD operations for restaurant tables
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

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    $pdo = getDbConnection();
    
    switch ($method) {
        case 'GET':
            if ($id > 0) {
                getTable($pdo, $id);
            } else {
                getAllTables($pdo);
            }
            break;
            
        case 'POST':
            createTable($pdo);
            break;
            
        case 'PUT':
            updateTable($pdo, $id);
            break;
            
        case 'DELETE':
            deleteTable($pdo, $id);
            break;
            
        default:
            sendResponse(405, false, 'Method not allowed');
    }
    
} catch (Exception $e) {
    sendResponse(500, false, 'Server error: ' . $e->getMessage());
}

function getAllTables($pdo) {
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    
    try {
        $sql = "SELECT t.*, 
                (SELECT COUNT(*) FROM orders o 
                 WHERE o.table_id = t.id 
                 AND o.status IN ('pending', 'preparing', 'ready')
                 AND DATE(o.created_at) = CURDATE()) as active_orders
                FROM tables t WHERE 1=1";
        $params = [];
        
        if (!empty($status)) {
            $sql .= " AND t.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY t.table_number";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $tables = $stmt->fetchAll();
        
        sendResponse(200, true, 'Tables retrieved', ['tables' => $tables]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getTable($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tables WHERE id = ?");
        $stmt->execute([$id]);
        $table = $stmt->fetch();
        
        if (!$table) {
            sendResponse(404, false, 'Table not found');
            return;
        }
        
        // Get current order if occupied
        if ($table['status'] === 'occupied') {
            $stmt = $pdo->prepare("
                SELECT o.*, 
                       COUNT(oi.id) as items_count,
                       TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) as duration
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.table_id = ? 
                AND o.status IN ('pending', 'preparing', 'ready')
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $table['current_order'] = $stmt->fetch();
        }
        
        sendResponse(200, true, 'Table retrieved', ['table' => $table]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function createTable($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['table_number', 'capacity'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            sendResponse(400, false, "Field {$field} is required");
            return;
        }
    }
    
    try {
        // Check if table number exists
        $stmt = $pdo->prepare("SELECT id FROM tables WHERE table_number = ?");
        $stmt->execute([$data['table_number']]);
        if ($stmt->fetch()) {
            sendResponse(400, false, 'Table number already exists');
            return;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO tables (table_number, capacity, location, status, qr_code)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['table_number'],
            $data['capacity'],
            $data['location'] ?? null,
            $data['status'] ?? 'available',
            $data['qr_code'] ?? null
        ]);
        
        sendResponse(201, true, 'Table created successfully', [
            'table_id' => $pdo->lastInsertId()
        ]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function updateTable($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid table ID');
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        // Check if table exists
        $stmt = $pdo->prepare("SELECT * FROM tables WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            sendResponse(404, false, 'Table not found');
            return;
        }
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['table_number', 'capacity', 'location', 'status', 'qr_code'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updateFields)) {
            sendResponse(400, false, 'No fields to update');
            return;
        }
        
        $sql = "UPDATE tables SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
        $params[] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        sendResponse(200, true, 'Table updated successfully');
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function deleteTable($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid table ID');
        return;
    }
    
    try {
        // Check if table has active orders
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM orders 
            WHERE table_id = ? 
            AND status IN ('pending', 'preparing', 'ready')
        ");
        $stmt->execute([$id]);
        if ($stmt->fetch()['count'] > 0) {
            sendResponse(400, false, 'Cannot delete table with active orders');
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM tables WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(200, true, 'Table deleted successfully');
        } else {
            sendResponse(404, false, 'Table not found');
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
