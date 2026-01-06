<?php
/**
 * Dashboard Statistics API
 * Provides real-time statistics for dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

$action = isset($_GET['action']) ? $_GET['action'] : 'stats';

try {
    $pdo = getDbConnection();
    
    switch ($action) {
        case 'stats':
            getDashboardStats($pdo);
            break;
        case 'sales':
            getSalesTrend($pdo);
            break;
        case 'top_menu':
            getTopMenu($pdo);
            break;
        case 'recent_orders':
            getRecentOrders($pdo);
            break;
        default:
            sendResponse(400, false, 'Invalid action');
    }
    
} catch (Exception $e) {
    sendResponse(500, false, 'Server error: ' . $e->getMessage());
}

function getDashboardStats($pdo) {
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    try {
        // Total orders today
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $totalOrders = $stmt->fetch()['total'];
        
        // Total revenue today
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) as revenue FROM orders WHERE DATE(created_at) = ? AND status != 'cancelled'");
        $stmt->execute([$date]);
        $totalRevenue = $stmt->fetch()['revenue'];
        
        // Active orders
        $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM orders WHERE status IN ('pending', 'preparing', 'ready') AND DATE(created_at) = ?");
        $stmt->execute([$date]);
        $activeOrders = $stmt->fetch()['active'];
        
        // Table occupancy
        $stmt = $pdo->prepare("SELECT COUNT(*) as occupied FROM tables WHERE status = 'occupied'");
        $stmt->execute();
        $occupiedTables = $stmt->fetch()['occupied'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tables");
        $stmt->execute();
        $totalTables = $stmt->fetch()['total'];
        
        $occupancy = $totalTables > 0 ? round(($occupiedTables / $totalTables) * 100, 1) : 0;
        
        sendResponse(200, true, 'Statistics retrieved', [
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'active_orders' => $activeOrders,
            'table_occupancy' => $occupancy,
            'occupied_tables' => $occupiedTables,
            'total_tables' => $totalTables
        ]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getSalesTrend($pdo) {
    $days = isset($_GET['days']) ? intval($_GET['days']) : 7;
    
    try {
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date, 
                   COALESCE(SUM(total), 0) as revenue,
                   COUNT(*) as orders
            FROM orders 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND status != 'cancelled'
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        $trend = $stmt->fetchAll();
        
        sendResponse(200, true, 'Sales trend retrieved', ['trend' => $trend]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getTopMenu($pdo) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            SELECT m.name, m.price, m.image,
                   SUM(oi.quantity) as total_sold,
                   SUM(oi.subtotal) as total_revenue
            FROM order_items oi
            JOIN menu_items m ON oi.menu_item_id = m.id
            JOIN orders o ON oi.order_id = o.id
            WHERE DATE(o.created_at) = ? AND o.status != 'cancelled'
            GROUP BY oi.menu_item_id
            ORDER BY total_sold DESC
            LIMIT ?
        ");
        $stmt->execute([$date, $limit]);
        $topMenu = $stmt->fetchAll();
        
        sendResponse(200, true, 'Top menu retrieved', ['top_menu' => $topMenu]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getRecentOrders($pdo) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, t.table_number,
                   COUNT(oi.id) as item_count
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE DATE(o.created_at) = CURDATE()
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $orders = $stmt->fetchAll();
        
        sendResponse(200, true, 'Recent orders retrieved', ['orders' => $orders]);
        
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
