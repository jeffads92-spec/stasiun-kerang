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

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

// Authentication check - COMMENTED OUT FOR TESTING
// Uncomment after login system is working
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
            // Check what resource is being requested
            if (isset($_GET['resource'])) {
                // Handle different resources
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
                        
                        echo json_encode([
                            'success' => true,
                            'data' => $categories,
                            'count' => count($categories),
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        break;
                        
                    case 'stats':
                        // Get menu statistics
                        $stats = [];
                        
                        // Total items
                        $stmt = $db->query("SELECT COUNT(*) as total FROM menu_items");
                        $stats['total_items'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        // Available items
                        $stmt = $db->query("SELECT COUNT(*) as total FROM menu_items WHERE is_available = 1");
                        $stats['available_items'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        // Total categories
                        $stmt = $db->query("SELECT COUNT(*) as total FROM categories");
                        $stats['total_categories'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        
                        // Average price
                        $stmt = $db->query("SELECT AVG(price) as avg_price FROM menu_items");
                        $stats['average_price'] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['avg_price'];
                        
                        echo json_encode([
                            'success' => true,
                            'data' => $stats,
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        break;
                        
                    default:
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Invalid resource requested',
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                }
            } elseif (isset($_GET['id'])) {
                // Get specific menu item by ID
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
                    // Convert is_available to boolean for easier handling
                    $item['is_available'] = (bool)$item['is_available'];
                    
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
                
                // Filter by category
                if (isset($_GET['category_id']) && $_GET['category_id'] !== '') {
                    $query .= " AND m.category_id = ?";
                    $params[] = $_GET['category_id'];
                }
                
                // Filter by availability
                if (isset($_GET['available'])) {
                    $query .= " AND m.is_available = ?";
                    $params[] = $_GET['available'] == 'true' || $_GET['available'] == '1' ? 1 : 0;
                }
                
                // Search by name or description
                if (isset($_GET['search']) && $_GET['search'] !== '') {
                    $query .= " AND (m.name LIKE ? OR m.description LIKE ?)";
                    $searchTerm = '%' . $_GET['search'] . '%';
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                // Price range filter
                if (isset($_GET['min_price']) && $_GET['min_price'] !== '') {
                    $query .= " AND m.price >= ?";
                    $params[] = (float)$_GET['min_price'];
                }
                
                if (isset($_GET['max_price']) && $_GET['max_price'] !== '') {
                    $query .= " AND m.price <= ?";
                    $params[] = (float)$_GET['max_price'];
                }
                
                // Sorting
                $sortBy = $_GET['sort_by'] ?? 'name';
                $sortOrder = $_GET['sort_order'] ?? 'ASC';
                
                $allowedSortFields = ['name', 'price', 'created_at', 'category_name'];
                $allowedSortOrders = ['ASC', 'DESC'];
                
                if (!in_array($sortBy, $allowedSortFields)) {
                    $sortBy = 'name';
                }
                if (!in_array(strtoupper($sortOrder), $allowedSortOrders)) {
                    $sortOrder = 'ASC';
                }
                
                if ($sortBy === 'category_name') {
                    $query .= " ORDER BY c.name $sortOrder, m.name ASC";
                } else {
                    $query .= " ORDER BY m.$sortBy $sortOrder";
                }
                
                // Execute query
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Convert is_available to boolean
                foreach ($items as &$item) {
                    $item['is_available'] = (bool)$item['is_available'];
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $items,
                    'count' => count($items),
                    'filters' => [
                        'category_id' => $_GET['category_id'] ?? null,
                        'available' => $_GET['available'] ?? null,
                        'search' => $_GET['search'] ?? null
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            break;
            
        case 'POST':
            // Create new menu item or category
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON data',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
            }
            
            // Check if creating category
            if (isset($_GET['resource']) && $_GET['resource'] === 'categories') {
                // Create category
                if (!isset($data['name'])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Category name is required',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    break;
                }
                
                $stmt = $db->prepare("
                    INSERT INTO categories (name, description)
                    VALUES (?, ?)
                ");
                
                $result = $stmt->execute([
                    $data['name'],
                    $data['description'] ?? null
                ]);
                
                if ($result) {
                    $id = $db->lastInsertId();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Category created successfully',
                        'data' => ['id' => $id],
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to create category',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
            } else {
                // Create menu item
                if (!isset($data['name']) || !isset($data['category_id']) || !isset($data['price'])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Missing required fields: name, category_id, price',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    break;
                }
                
                // Validate price
                if (!is_numeric($data['price']) || $data['price'] < 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Price must be a positive number',
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
                    isset($data['is_available']) ? ($data['is_available'] ? 1 : 0) : 1
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
            }
            break;
            
        case 'PUT':
            // Update menu item or category
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON data',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
            }
            
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID is required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
            }
            
            // Check if updating category
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
                    UPDATE categories 
                    SET " . implode(', ', $updates) . "
                    WHERE id = ?
                ");
                
                $result = $stmt->execute($params);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Category updated successfully',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to update category',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
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
                    if (!is_numeric($data['price']) || $data['price'] < 0) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'Price must be a positive number',
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        break;
                    }
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
            }
            break;
            
        case 'DELETE':
            // Delete menu item or category
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'ID is required',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
            }
            
            // Check if deleting category
            if (isset($_GET['resource']) && $_GET['resource'] === 'categories') {
                // Check if category has menu items
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM menu_items WHERE category_id = ?");
                $stmt->execute([$_GET['id']]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($count > 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => "Cannot delete category. It has {$count} menu items. Please reassign or delete them first.",
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    break;
                }
                
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $result = $stmt->execute([$_GET['id']]);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Category deleted successfully',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to delete category',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
            } else {
                // Delete menu item
                // Check if item is in any active orders
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM order_items oi
                    JOIN orders o ON oi.order_id = o.id
                    WHERE oi.menu_item_id = ? 
                    AND o.status NOT IN ('Completed', 'Cancelled')
                ");
                $stmt->execute([$_GET['id']]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($count > 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cannot delete menu item. It is in active orders. Consider marking it as unavailable instead.',
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
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
