<?php
/**
 * Menu Management API
 * CRUD operations for menu items and categories
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
$resource = isset($_GET['resource']) ? $_GET['resource'] : 'menu';

try {
    $pdo = getDbConnection();
    
    if ($resource === 'categories') {
        handleCategories($pdo, $method, $id);
    } else {
        handleMenuItems($pdo, $method, $id);
    }
    
} catch (Exception $e) {
    sendResponse(500, false, 'Server error: ' . $e->getMessage());
}

function handleMenuItems($pdo, $method, $id) {
    switch ($method) {
        case 'GET':
            if ($id > 0) {
                getMenuItem($pdo, $id);
            } else {
                getAllMenuItems($pdo);
            }
            break;
            
        case 'POST':
            createMenuItem($pdo);
            break;
            
        case 'PUT':
            updateMenuItem($pdo, $id);
            break;
            
        case 'DELETE':
            deleteMenuItem($pdo, $id);
            break;
            
        default:
            sendResponse(405, false, 'Method not allowed');
    }
}

function getAllMenuItems($pdo) {
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $available = isset($_GET['available']) ? $_GET['available'] : '';
    
    try {
        $sql = "SELECT m.*, c.name as category_name 
                FROM menu_items m 
                LEFT JOIN categories c ON m.category_id = c.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($category)) {
            $sql .= " AND m.category_id = ?";
            $params[] = $category;
        }
        
        if (!empty($search)) {
            $sql .= " AND (m.name LIKE ? OR m.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        if ($available !== '') {
            $sql .= " AND m.is_available = ?";
            $params[] = $available ? 1 : 0;
        }
        
        $sql .= " ORDER BY c.sort_order, m.name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        
        sendResponse(200, true, 'Menu items retrieved', ['items' => $items]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getMenuItem($pdo, $id) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, c.name as category_name 
            FROM menu_items m 
            LEFT JOIN categories c ON m.category_id = c.id 
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if ($item) {
            sendResponse(200, true, 'Menu item retrieved', ['item' => $item]);
        } else {
            sendResponse(404, false, 'Menu item not found');
        }
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function createMenuItem($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['category_id', 'name', 'price'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            sendResponse(400, false, "Field {$field} is required");
            return;
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO menu_items (category_id, name, description, price, cost_price, 
                                   image, is_available, is_featured, preparation_time, 
                                   stock_quantity, calories, spicy_level)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['category_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['price'],
            $data['cost_price'] ?? null,
            $data['image'] ?? null,
            isset($data['is_available']) ? $data['is_available'] : 1,
            isset($data['is_featured']) ? $data['is_featured'] : 0,
            $data['preparation_time'] ?? 15,
            $data['stock_quantity'] ?? null,
            $data['calories'] ?? null,
            $data['spicy_level'] ?? 'none'
        ]);
        
        $itemId = $pdo->lastInsertId();
        
        sendResponse(201, true, 'Menu item created successfully', ['item_id' => $itemId]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function updateMenuItem($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid menu item ID');
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['category_id', 'name', 'description', 'price', 'cost_price', 
                         'image', 'is_available', 'is_featured', 'preparation_time', 
                         'stock_quantity', 'calories', 'spicy_level'];
        
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
        
        $sql = "UPDATE menu_items SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
        $params[] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(200, true, 'Menu item updated successfully');
        } else {
            sendResponse(404, false, 'Menu item not found');
        }
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function deleteMenuItem($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid menu item ID');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(200, true, 'Menu item deleted successfully');
        } else {
            sendResponse(404, false, 'Menu item not found');
        }
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function handleCategories($pdo, $method, $id) {
    switch ($method) {
        case 'GET':
            if ($id > 0) {
                getCategory($pdo, $id);
            } else {
                getAllCategories($pdo);
            }
            break;
            
        case 'POST':
            createCategory($pdo);
            break;
            
        case 'PUT':
            updateCategory($pdo, $id);
            break;
            
        case 'DELETE':
            deleteCategory($pdo, $id);
            break;
            
        default:
            sendResponse(405, false, 'Method not allowed');
    }
}

function getAllCategories($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, COUNT(m.id) as item_count 
            FROM categories c 
            LEFT JOIN menu_items m ON c.id = m.category_id 
            WHERE c.is_active = 1 
            GROUP BY c.id 
            ORDER BY c.sort_order, c.name
        ");
        $stmt->execute();
        $categories = $stmt->fetchAll();
        
        sendResponse(200, true, 'Categories retrieved', ['categories' => $categories]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getCategory($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if ($category) {
            sendResponse(200, true, 'Category retrieved', ['category' => $category]);
        } else {
            sendResponse(404, false, 'Category not found');
        }
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function createCategory($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name'])) {
        sendResponse(400, false, 'Category name is required');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO categories (name, description, icon, sort_order, is_active)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['icon'] ?? null,
            $data['sort_order'] ?? 0,
            isset($data['is_active']) ? $data['is_active'] : 1
        ]);
        
        sendResponse(201, true, 'Category created', ['category_id' => $pdo->lastInsertId()]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function updateCategory($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid category ID');
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        $updateFields = [];
        $params = [];
        
        foreach (['name', 'description', 'icon', 'sort_order', 'is_active'] as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($updateFields)) {
            sendResponse(400, false, 'No fields to update');
            return;
        }
        
        $sql = "UPDATE categories SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        sendResponse(200, true, 'Category updated');
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function deleteCategory($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid category ID');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(200, true, 'Category deleted');
        } else {
            sendResponse(404, false, 'Category not found');
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
