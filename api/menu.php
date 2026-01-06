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

// Only admin and kitchen can modify menu, but everyone can view
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
    // Check permissions for modifying
    if ($method !== 'GET' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'kitchen') {
        sendResponse(403, false, 'Forbidden: Only admin and kitchen can modify menu');
        exit();
    }
    
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
    $category = isset($_GET['category']) ? intval($_GET['category']) : 0;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $available = isset($_GET['available']) ? $_GET['available'] : '';
    
    try {
        $sql = "SELECT m.*, c.name as category_name 
                FROM menu_items m 
                LEFT JOIN categories c ON m.category_id = c.id 
                WHERE 1=1";
        $params = [];
        
        if ($category > 0) {
            $sql .= " AND m.category_id = ?";
            $params[] = $category;
        }
        
        if (!empty($search)) {
            $sql .= " AND (m.name LIKE ? OR m.description LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($available === 'true') {
            $sql .= " AND m.is_available = 1";
        } elseif ($available === 'false') {
            $sql .= " AND m.is_available = 0";
        }
        
        $sql .= " ORDER BY c.sort_order, m.name";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, true, 'Menu items retrieved', ['items' => $items]);
        
    } catch (PDOException $e) {
        error_log("Get all menu items error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
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
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            sendResponse(200, true, 'Menu item retrieved', ['item' => $item]);
        } else {
            sendResponse(404, false, 'Menu item not found');
        }
        
    } catch (PDOException $e) {
        error_log("Get menu item error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function createMenuItem($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['category_id', 'name', 'price'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            sendResponse(400, false, "Field {$field} is required");
            return;
        }
    }
    
    // Validate price
    if (!is_numeric($data['price']) || $data['price'] <= 0) {
        sendResponse(400, false, 'Price must be a positive number');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO menu_items (category_id, name, description, price, cost_price, 
                                   image, is_available, is_featured, preparation_time, 
                                   stock_quantity, calories, spicy_level, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            intval($data['category_id']),
            trim($data['name']),
            isset($data['description']) ? trim($data['description']) : null,
            floatval($data['price']),
            isset($data['cost_price']) ? floatval($data['cost_price']) : null,
            $data['image'] ?? null,
            isset($data['is_available']) ? intval($data['is_available']) : 1,
            isset($data['is_featured']) ? intval($data['is_featured']) : 0,
            isset($data['preparation_time']) ? intval($data['preparation_time']) : 15,
            isset($data['stock_quantity']) ? intval($data['stock_quantity']) : null,
            isset($data['calories']) ? intval($data['calories']) : null,
            $data['spicy_level'] ?? 'none'
        ]);
        
        $itemId = $pdo->lastInsertId();
        
        sendResponse(201, true, 'Menu item created successfully', ['item_id' => $itemId]);
        
    } catch (PDOException $e) {
        error_log("Create menu item error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function updateMenuItem($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid menu item ID');
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    try {
        // Check if item exists
        $stmt = $pdo->prepare("SELECT id FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            sendResponse(404, false, 'Menu item not found');
            return;
        }
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = ['category_id', 'name', 'description', 'price', 'cost_price', 
                         'image', 'is_available', 'is_featured', 'preparation_time', 
                         'stock_quantity', 'calories', 'spicy_level'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateFields[] = "{$field} = ?";
                // Handle different data types
                if (in_array($field, ['category_id', 'is_available', 'is_featured', 'preparation_time', 'stock_quantity', 'calories'])) {
                    $params[] = intval($data[$field]);
                } elseif (in_array($field, ['price', 'cost_price'])) {
                    $params[] = floatval($data[$field]);
                } else {
                    $params[] = $data[$field];
                }
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
        error_log("Update menu item error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function deleteMenuItem($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid menu item ID');
        return;
    }
    
    try {
        // Check if item is used in orders
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM order_items WHERE menu_item_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            sendResponse(400, false, 'Cannot delete menu item that has been ordered');
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(200, true, 'Menu item deleted successfully');
        } else {
            sendResponse(404, false, 'Menu item not found');
        }
        
    } catch (PDOException $e) {
        error_log("Delete menu item error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function handleCategories($pdo, $method, $id) {
    // Check permissions for modifying
    if ($method !== 'GET' && $_SESSION['role'] !== 'admin') {
        sendResponse(403, false, 'Forbidden: Only admin can modify categories');
        exit();
    }
    
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
            LEFT JOIN menu_items m ON c.id = m.category_id AND m.is_available = 1
            WHERE c.is_active = 1 
            GROUP BY c.id 
            ORDER BY c.sort_order, c.name
        ");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, true, 'Categories retrieved', ['categories' => $categories]);
        
    } catch (PDOException $e) {
        error_log("Get all categories error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function getCategory($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category) {
            sendResponse(200, true, 'Category retrieved', ['category' => $category]);
        } else {
            sendResponse(404, false, 'Category not found');
        }
        
    } catch (PDOException $e) {
        error_log("Get category error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function createCategory($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name']) || empty(trim($data['name']))) {
        sendResponse(400, false, 'Category name is required');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO categories (name, description, icon, sort_order, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            trim($data['name']),
            isset($data['description']) ? trim($data['description']) : null,
            $data['icon'] ?? null,
            isset($data['sort_order']) ? intval($data['sort_order']) : 0,
            isset($data['is_active']) ? intval($data['is_active']) : 1
        ]);
        
        sendResponse(201, true, 'Category created', ['category_id' => $pdo->lastInsertId()]);
        
    } catch (PDOException $e) {
        error_log("Create category error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
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
                if (in_array($field, ['sort_order', 'is_active'])) {
                    $params[] = intval($data[$field]);
                } else {
                    $params[] = trim($data[$field]);
                }
            }
        }
        
        if (empty($updateFields)) {
            sendResponse(400, false, 'No fields to update');
            return;
        }
        
        $sql = "UPDATE categories SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
        $params[] = $id;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(200, true, 'Category updated');
        } else {
            sendResponse(404, false, 'Category not found');
        }
        
    } catch (PDOException $e) {
        error_log("Update category error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function deleteCategory($pdo, $id) {
    if ($id <= 0) {
        sendResponse(400, false, 'Invalid category ID');
        return;
    }
    
    try {
        // Check if category has menu items
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM menu_items WHERE category_id = ?");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            sendResponse(400, false, 'Cannot delete category that has menu items');
            return;
        }
        
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            sendResponse(200, true, 'Category deleted');
        } else {
            sendResponse(404, false, 'Category not found');
        }
        
    } catch (PDOException $e) {
        error_log("Delete category error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}
?>
