<?php
// Prevent any output before headers
ob_start();

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
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
    ob_end_clean();
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database configuration file not found'
    ]));
}

require_once $configPath;

// Helper function to send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    ob_end_clean();
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

try {
    // Get database connection
    if (class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    } elseif (function_exists('getDbConnection')) {
        $db = getDbConnection();
    } else {
        throw new Exception('Database connection method not found');
    }
    
    $action = $_GET['action'] ?? 'stats';
    
    switch ($action) {
        case 'stats':
            // Get today's statistics
            $today = date('Y-m-d');
            
            // Total orders today
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM orders 
                WHERE DATE(created_at) = ?
            ");
            $stmt->execute([$today]);
            $totalOrders = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Revenue today
            $stmt = $db->prepare("
                SELECT COALESCE(SUM(total), 0) as revenue 
                FROM orders 
                WHERE DATE(created_at) = ? 
                AND status != 'Cancelled'
            ");
            $stmt->execute([$today]);
            $revenue = (float)$stmt->fetch(PDO::FETCH_ASSOC)['revenue'];
            
            // Active orders (not completed or cancelled)
            $stmt = $db->query("
                SELECT COUNT(*) as count 
                FROM orders 
                WHERE status NOT IN ('Completed', 'Cancelled')
            ");
            $activeOrders = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            // Calculate table occupancy
            $stmt = $db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied
                FROM tables
            ");
            $tableStats = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalTables = (int)$tableStats['total'];
            $occupiedTables = (int)$tableStats['occupied'];
            $occupancy = $totalTables > 0 ? round(($occupiedTables / $totalTables) * 100) : 0;
            
            sendJsonResponse([
                'success' => true,
                'data' => [
                    'total_orders' => $totalOrders,
                    'revenue' => $revenue,
                    'active_orders' => $activeOrders,
                    'table_occupancy' => $occupancy,
                    'occupied_tables' => $occupiedTables,
                    'total_tables' => $totalTables
                ]
            ]);
            break;
            
        case 'sales':
            // Get sales trend for last 7 days
            $stmt = $db->query("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as orders,
                    COALESCE(SUM(total), 0) as revenue
                FROM orders
                WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND status != 'Cancelled'
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $salesTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendJsonResponse([
                'success' => true,
                'data' => $salesTrend
            ]);
            break;
            
        case 'top_menu':
            // Get top selling menu items (last 30 days)
            $stmt = $db->query("
                SELECT 
                    m.name,
                    m.price,
                    SUM(oi.quantity) as total_sold,
                    SUM(oi.subtotal) as revenue
                FROM order_items oi
                JOIN menu_items m ON oi.menu_item_id = m.id
                JOIN orders o ON oi.order_id = o.id
                WHERE DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                AND o.status != 'Cancelled'
                GROUP BY m.id
                ORDER BY total_sold DESC
                LIMIT 10
            ");
            $topMenu = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendJsonResponse([
                'success' => true,
                'data' => $topMenu
            ]);
            break;
            
        case 'recent_orders':
            // Get recent orders
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            
            $stmt = $db->prepare("
                SELECT 
                    o.*,
                    t.table_number,
                    u.username as cashier
                FROM orders o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.created_by = u.id
                ORDER BY o.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendJsonResponse([
                'success' => true,
                'data' => $recentOrders
            ]);
            break;
            
        default:
            sendJsonResponse([
                'success' => false,
                'message' => 'Invalid action'
            ], 400);
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
