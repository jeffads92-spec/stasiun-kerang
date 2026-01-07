<?php
// Prevent any output before headers
ob_start();

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Try multiple path options for config
$configPath = __DIR__ . '/../config/database.php';
if (!file_exists($configPath)) {
    $configPath = $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
}
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__) . '/config/database.php';
}

if (!file_exists($configPath)) {
    ob_end_clean();
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database configuration file not found',
        'timestamp' => date('Y-m-d H:i:s')
    ]));
}

require_once $configPath;

// Helper function to send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    ob_end_clean(); // Clear any previous output
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

try {
    // Get database connection (support both class and function)
    if (class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    } elseif (function_exists('getDbConnection')) {
        $db = getDbConnection();
    } else {
        throw new Exception('Database connection method not found');
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            // Check what resource is being requested
            if (isset($_GET['resource'])) {
                switch ($_GET['resource']) {
                    case 'categories':
                        // Get all categories with item count
                        $stmt = $db->query("
                            SELECT 
                                c.*,
                                COUNT(m.id) as item_count,
                                SUM(CASE WHEN m.is_available = 1 THEN 1 ELSE 0 END) as available_count
                            FROM categories c
                            LEFT JOIN menu_items m ON c.id = m.category_id
                            GROUP BY c.id
                            ORDER BY c.name
                        ");
                        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        sendJsonResponse([
                            'success' => true,
                            'data' => $categories,
                            'count' => count($categories),
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        break;
                        
                    case 'stats':
                        // Get menu statistics
                        $stats = [];
                        
                        $stmt = $db->query("SELECT COUNT(*) as total FROM menu_items");
                        $stats['total_items'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        $stmt = $db->query("SELECT COUNT(*) as total FROM menu_items WHERE is_available = 1");
                        $stats['available_items'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        $stmt = $db->query("SELECT COUNT(*) as total FROM categories");
                        $stats['total_categories'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        $stmt = $db->query("SELECT AVG(price) as avg_price FROM menu_items");
                        $stats['average_price'] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['avg_price'];
                        
                        sendJsonResponse([
                            'success' => true,
                            'data' => $stats,
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        break;
                        
                    default:
                        sendJsonResponse([
                            'success' => false,
                            'message' => 'Invalid resource requested'
                        ], 400);
                }
            } elseif (isset($_GET['id'])) {
                // Get specific menu item
                $stmt = $db->prepare("
                    SELECT 
                        m.*,
                        c.name as category_name
                    FROM menu_items m
                    LEFT JOIN categories c ON m.category_id = c.id
                    WHERE m.id = ?
                ");
                $stmt->execute([$_GET['id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($item) {
                    $item['is_available'] = (bool)$item['is_available'];
                    sendJsonResponse([
                        'success' => true,
                        'data' => $item,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Menu item not found'
                    ], 404);
                }
            } else {
                // Get all menu items with filters
                $query = "
                    SELECT 
                        m.*,
                        c.name as category_name
                    FROM menu_items m
                    LEFT JOIN categories c ON m.category_id = c.id
                    WHERE 1=1
                ";
                
                $params = [];
                
                if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
                    $query .= " AND m.category_id = ?";
                    $params[] = $_GET['category_id'];
                }
                
                if (isset($_GET['available'])) {
                    $query .= " AND m.is_available = ?";
                    $params[] = ($_GET['available'] == 'true' || $_GET['available'] == '1') ? 1 : 0;
                }
                
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
                
                foreach ($items as &$item) {
                    $item['is_available'] = (bool)$item['is_available'];
                }
                
                sendJsonResponse([
                    'success' => true,
                    'data' => $items,
                    'count' => count($items),
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Invalid JSON data'
                ], 400);
            }
            
            if (isset($_GET['resource']) && $_GET['resource'] === 'categories') {
                // Create category
                if (!isset($data['name'])) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Category name is required'
                    ], 400);
                }
                
                $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $result = $stmt->execute([$data['name'], $data['description'] ?? null]);
                
                if ($result) {
                    sendJsonResponse([
                        'success' => true,
                        'message' => 'Category created successfully',
                        'data' => ['id' => $db->lastInsertId()],
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
            } else {
                // Create menu item
                if (!isset($data['name']) || !isset($data['category_id']) || !isset($data['price'])) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Missing required fields: name, category_id, price'
                    ], 400);
                }
                
                if (!is_numeric($data['price']) || $data['price'] < 0) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Price must be a positive number'
                    ], 400);
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
                    isset($data['is_available']) ? ($data['is_available'] ? 1 : 0) : 1
                ]);
                
                if ($result) {
                    sendJsonResponse([
                        'success' => true,
                        'message' => 'Menu item created successfully',
                        'data' => ['id' => $db->lastInsertId()],
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($_GET['id'])) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'ID is required'
                ], 400);
            }
            
            if (isset($_GET['resource']) && $_GET['resource'] === 'categories') {
                // Update category
                $updates = [];
                $params = [];
                
                if (isset($data['name'])) {
                    $updates[] = "name = ?";
                    $params[] = $data['name'];
                }
                if (isset($data['description'])) {
                    $updates[] = "description = ?";
                    $params[] = $data['description'];
                }
                
                if (empty($updates)) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'No fields to update'
                    ], 400);
                }
                
                $params[] = $_GET['id'];
                $stmt = $db->prepare("UPDATE categories SET " . implode(', ', $updates) . " WHERE id = ?");
                $result = $stmt->execute($params);
                
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Category updated successfully'
                ]);
            } else {
                // Update menu item
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
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'No fields to update'
                    ], 400);
                }
                
                $params[] = $_GET['id'];
                $stmt = $db->prepare("UPDATE menu_items SET " . implode(', ', $updates) . " WHERE id = ?");
                $result = $stmt->execute($params);
                
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Menu item updated successfully'
                ]);
            }
            break;
            
        case 'DELETE':
            if (!isset($_GET['id'])) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'ID is required'
                ], 400);
            }
            
            if (isset($_GET['resource']) && $_GET['resource'] === 'categories') {
                // Check if category has items
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM menu_items WHERE category_id = ?");
                $stmt->execute([$_GET['id']]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($count > 0) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => "Cannot delete category. It has {$count} menu items."
                    ], 400);
                }
                
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Category deleted successfully'
                ]);
            } else {
                $stmt = $db->prepare("DELETE FROM menu_items WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Menu item deleted successfully'
                ]);
            }
            break;
            
        default:
            sendJsonResponse([
                'success' => false,
                'message' => 'Method not allowed'
            ], 405);
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Database error occurred'
    ], 500);
} catch (Exception $e) {
    error_log("Server error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?>
