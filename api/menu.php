<?php
/**
 * Menu API - Complete version with all actions
 */

// Start session
session_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Always return JSON
header('Content-Type: application/json');

// Handle errors gracefully
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'System error: ' . $errstr,
        'debug' => [
            'file' => basename($errfile),
            'line' => $errline
        ]
    ]);
    exit;
});

// Include dependencies
require_once __DIR__ . '/../config/database.php';

// Include helpers if exists
if (file_exists(__DIR__ . '/../helpers/functions.php')) {
    require_once __DIR__ . '/../helpers/functions.php';
}

// Get database connection
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Handle different actions
switch ($action) {
    case 'list':
        handleList($pdo);
        break;
    case 'add':
        handleAdd($pdo);
        break;
    case 'update':
        handleUpdate($pdo);
        break;
    case 'delete':
        handleDelete($pdo);
        break;
    case 'get':
        handleGet($pdo);
        break;
    case 'categories':
        handleCategories($pdo);
        break;
    default:
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action'
        ]);
        exit;
}

/**
 * List all menu items
 */
function handleList($pdo) {
    try {
        $sql = "SELECT m.*, c.name as category_name 
                FROM menu_items m 
                LEFT JOIN categories c ON m.category_id = c.id 
                ORDER BY m.id DESC";
        
        $stmt = $pdo->query($sql);
        $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'data' => $menuItems
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch menu items: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get single menu item
 */
function handleGet($pdo) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Menu ID is required'
        ]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT m.*, c.name as category_name 
                              FROM menu_items m 
                              LEFT JOIN categories c ON m.category_id = c.id 
                              WHERE m.id = ?");
        $stmt->execute([$id]);
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($menu) {
            echo json_encode([
                'status' => 'success',
                'data' => $menu
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Menu not found'
            ]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch menu: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get all categories
 */
function handleCategories($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name, icon, sort_order 
                            FROM categories 
                            WHERE is_active = 1 
                            ORDER BY sort_order, name");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'data' => $categories
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch categories: ' . $e->getMessage()
        ]);
    }
}

/**
 * Add new menu item
 */
function handleAdd($pdo) {
    try {
        // Validate required fields
        $required = ['category_id', 'name', 'price'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => "Field '$field' is required"
                ]);
                return;
            }
        }
        
        // Get form data
        $category_id = $_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price']);
        $cost_price = !empty($_POST['cost_price']) ? floatval($_POST['cost_price']) : null;
        $preparation_time = !empty($_POST['preparation_time']) ? intval($_POST['preparation_time']) : 15;
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $spicy_level = $_POST['spicy_level'] ?? 'none';
        $calories = !empty($_POST['calories']) ? intval($_POST['calories']) : null;
        
        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImageLocal($_FILES['image']);
            if ($uploadResult['success']) {
                $imagePath = $uploadResult['filename'];
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => $uploadResult['message']
                ]);
                return;
            }
        }
        
        // Insert into database
        $sql = "INSERT INTO menu_items 
                (category_id, name, description, price, cost_price, image, 
                 is_available, is_featured, preparation_time, calories, spicy_level) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $category_id, $name, $description, $price, $cost_price, $imagePath,
            $is_available, $is_featured, $preparation_time, $calories, $spicy_level
        ]);
        
        $menuId = $pdo->lastInsertId();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Menu berhasil ditambahkan',
            'menu_id' => $menuId
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menyimpan menu: ' . $e->getMessage()
        ]);
    }
}

/**
 * Update menu item
 */
function handleUpdate($pdo) {
    try {
        $id = $_POST['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Menu ID is required'
            ]);
            return;
        }
        
        // Get existing menu
        $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Menu not found'
            ]);
            return;
        }
        
        // Get form data
        $category_id = $_POST['category_id'] ?? $existing['category_id'];
        $name = trim($_POST['name'] ?? $existing['name']);
        $description = trim($_POST['description'] ?? $existing['description']);
        $price = floatval($_POST['price'] ?? $existing['price']);
        $cost_price = isset($_POST['cost_price']) ? floatval($_POST['cost_price']) : $existing['cost_price'];
        $preparation_time = isset($_POST['preparation_time']) ? intval($_POST['preparation_time']) : $existing['preparation_time'];
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $spicy_level = $_POST['spicy_level'] ?? $existing['spicy_level'];
        $calories = isset($_POST['calories']) ? intval($_POST['calories']) : $existing['calories'];
        
        // Handle image upload
        $imagePath = $existing['image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadImageLocal($_FILES['image']);
            if ($uploadResult['success']) {
                // Delete old image if exists
                if ($existing['image']) {
                    $oldImagePath = __DIR__ . '/../uploads/' . $existing['image'];
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
                $imagePath = $uploadResult['filename'];
            }
        }
        
        // Update database
        $sql = "UPDATE menu_items SET 
                category_id = ?, name = ?, description = ?, price = ?, 
                cost_price = ?, image = ?, is_available = ?, is_featured = ?,
                preparation_time = ?, calories = ?, spicy_level = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $category_id, $name, $description, $price, $cost_price, $imagePath,
            $is_available, $is_featured, $preparation_time, $calories, $spicy_level, $id
        ]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Menu berhasil diupdate'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal update menu: ' . $e->getMessage()
        ]);
    }
}

/**
 * Delete menu item
 */
function handleDelete($pdo) {
    try {
        $id = $_POST['id'] ?? $_GET['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Menu ID is required'
            ]);
            return;
        }
        
        // Get menu to delete image
        $stmt = $pdo->prepare("SELECT image FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$menu) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Menu not found'
            ]);
            return;
        }
        
        // Delete image if exists
        if ($menu['image']) {
            $imagePath = __DIR__ . '/../uploads/' . $menu['image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Menu berhasil dihapus'
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal hapus menu: ' . $e->getMessage()
        ]);
    }
}

/**
 * Local upload image function (avoids duplicate with helpers)
 */
function uploadImageLocal($file) {
    $uploadDir = __DIR__ . '/../uploads/';
    
    // Create directory if not exists
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedMimeTypes)) {
        return [
            'success' => false,
            'message' => 'Invalid file type. Only JPG, PNG, GIF, and WEBP allowed'
        ];
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        return [
            'success' => false,
            'message' => 'File too large. Maximum size is 5MB'
        ];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'menu_' . uniqid() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'success' => true,
            'filename' => $filename
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to upload file'
        ];
    }
}
?>
