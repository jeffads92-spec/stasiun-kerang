<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Get menu items with category info
            if (isset($_GET['id'])) {
                // Get specific menu item
                $stmt = $db->prepare("
                    SELECT 
                        m.*,
                        c.name as category_name,
                        c.description as category_description
                    FROM menu_items m
                    LEFT JOIN categories c ON m.category_id = c.id
                    WHERE m.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($item) {
                    echo json_encode([
                        'success' => true,
                        'data' => $item,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Menu item not found',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
            } elseif (isset($_GET['resource']) && $_GET['resource'] === 'categories') {
                // Get all categories
                $stmt = $db->query("
                    SELECT 
                        c.*,
                        COUNT(m.id) as item_count
                    FROM categories c
                    LEFT JOIN menu_items m ON c.id = m.category_id AND m.is_available = 1
                    GROUP BY c.id
                    ORDER BY c.name
                ");
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $categories,
                    'count' => count($categories),
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Get all menu items
                $query = "
                    SELECT 
                        m.*,
                        c.name as category_name
                    FROM menu_items m
                    LEFT JOIN categories c ON m.category_id = c.id
                    WHERE 1=1
                ";
                
                $params = [];
                
                // Filter by category
                if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
                    $query .= " AND m.category_id = ?";
                    $params[] = $_GET['category_id'];
                }
                
                // Filter by availability
                if (isset($_GET['available'])) {
                    $query .= " AND m.is_available = ?";
                    $params[] = $_GET['available'] ? 1 : 0;
                }
                
                // Search
                if (isset($_GET['search']) && $_GET['search'] !== '') {
                    $query .= " AND (m.name LIKE ? OR m.description LIKE ?)";
                    $searchTerm = '%' . $_GET['search'] . '%';
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                $query .= " ORDER BY c.name, m.name";
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => $items,
                    'count' => count($items),
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            break;
            
        case 'POST':
            // Create new menu item
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['name']) || !isset($data['category_id']) || !isset($data['price'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields: name, category_id, price',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
            }
            
            $stmt = $db->prepare("
                INSERT INTO menu_items 
                (name, category_id, price, description, image_url, is_available)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['category_id'],
                $data['price'],
                $data['description'] ?? null,
                $data['image_url'] ?? null,
                $data['is_available'] ?? 1
            ]);
            
            if ($result) {
                $id = $db->lastInsertId();
                echo json_encode([
                    'success' => true,
                    'message' => 'Menu item created successfully',
                    'data' => ['id' => $id],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create menu item',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            break;
            
        case 'PUT':
            // Update menu item
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Menu item ID is required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
            }
            
            $updates = [];
            $params = [];
            
            if (isset($data['name'])) {
                $updates[] = "name = ?";
                $params[] = $data['name'];
            }
            if (isset($data['category_id'])) {
                $updates[] = "category_id = ?";
                $params[] = $data['category_id'];
            }
            if (isset($data['price'])) {
                $updates[] = "price = ?";
                $params[] = $data['price'];
            }
            if (isset($data['description'])) {
                $updates[] = "description = ?";
                $params[] = $data['description'];
            }
            if (isset($data['image_url'])) {
                $updates[] = "image_url = ?";
                $params[] = $data['image_url'];
            }
            if (isset($data['is_available'])) {
                $updates[] = "is_available = ?";
                $params[] = $data['is_available'] ? 1 : 0;
            }
            
            if (empty($updates)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No fields to update',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
            }
            
            $params[] = $_GET['id'];
            
            $stmt = $db->prepare("
                UPDATE menu_items 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            
            $result = $stmt->execute($params);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Menu item updated successfully',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update menu item',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            break;
            
        case 'DELETE':
            // Delete menu item
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Menu item ID is required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
            }
            
            $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
            $result = $stmt->execute([$_GET['id']]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Menu item deleted successfully',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete menu item',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
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
