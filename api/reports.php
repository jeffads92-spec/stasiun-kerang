<?php
// Prevent any output before headers
ob_start();

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// Helper function to export to Excel (CSV format)
function exportToExcel($data, $filename) {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
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
    
    $action = $_GET['action'] ?? 'summary';
    
    switch ($action) {
        case 'summary':
            // Get sales summary
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            
            // Validate dates
            if (!strtotime($startDate) || !strtotime($endDate)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Invalid date format'
                ], 400);
            }
            
            // Total sales with error handling
            try {
                $stmt = $db->prepare("
                    SELECT 
                        COUNT(*) as total_orders,
                        COALESCE(SUM(total), 0) as total_sales,
                        COALESCE(SUM(subtotal), 0) as subtotal,
                        COALESCE(SUM(tax), 0) as total_tax,
                        COALESCE(SUM(service_charge), 0) as total_service
                    FROM orders
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    AND status != 'Cancelled'
                ");
                $stmt->execute([$startDate, $endDate]);
                $summary = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$summary) {
                    $summary = [
                        'total_orders' => 0,
                        'total_sales' => 0,
                        'subtotal' => 0,
                        'total_tax' => 0,
                        'total_service' => 0
                    ];
                }
            } catch (PDOException $e) {
                error_log("Summary query error: " . $e->getMessage());
                $summary = [
                    'total_orders' => 0,
                    'total_sales' => 0,
                    'subtotal' => 0,
                    'total_tax' => 0,
                    'total_service' => 0
                ];
            }
            
            // Top selling items
            try {
                $stmt = $db->prepare("
                    SELECT 
                        m.name,
                        SUM(oi.quantity) as total_quantity,
                        SUM(oi.subtotal) as total_revenue
                    FROM order_items oi
                    JOIN menu_items m ON oi.menu_item_id = m.id
                    JOIN orders o ON oi.order_id = o.id
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
                    AND o.status != 'Cancelled'
                    GROUP BY m.id
                    ORDER BY total_quantity DESC
                    LIMIT 10
                ");
                $stmt->execute([$startDate, $endDate]);
                $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Top items query error: " . $e->getMessage());
                $topItems = [];
            }
            
            // Orders by status
            try {
                $stmt = $db->prepare("
                    SELECT 
                        status,
                        COUNT(*) as count
                    FROM orders
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY status
                ");
                $stmt->execute([$startDate, $endDate]);
                $byStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Status query error: " . $e->getMessage());
                $byStatus = [];
            }
            
            sendJsonResponse([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'top_items' => $topItems,
                    'by_status' => $byStatus,
                    'period' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ]
                ]
            ]);
            break;
            
        case 'sales_trend':
            // Get sales trend by date
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            
            $stmt = $db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as orders,
                    COALESCE(SUM(total), 0) as revenue
                FROM orders
                WHERE DATE(created_at) BETWEEN ? AND ?
                AND status != 'Cancelled'
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([$startDate, $endDate]);
            $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendJsonResponse([
                'success' => true,
                'data' => $trend
            ]);
            break;
            
        case 'menu_performance':
            // Menu performance report
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            
            $stmt = $db->prepare("
                SELECT 
                    m.id,
                    m.name,
                    c.name as category,
                    COALESCE(SUM(oi.quantity), 0) as total_sold,
                    COALESCE(SUM(oi.subtotal), 0) as revenue,
                    m.price
                FROM menu_items m
                LEFT JOIN categories c ON m.category_id = c.id
                LEFT JOIN order_items oi ON m.id = oi.menu_item_id
                LEFT JOIN orders o ON oi.order_id = o.id AND DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'Cancelled'
                GROUP BY m.id
                ORDER BY total_sold DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            $performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendJsonResponse([
                'success' => true,
                'data' => $performance
            ]);
            break;
            
        case 'transactions':
            // Transaction list
            $startDate = $_GET['start_date'] ?? date('Y-m-d');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = ($page - 1) * $limit;
            
            $stmt = $db->prepare("
                SELECT 
                    o.order_number,
                    o.created_at,
                    o.customer_name,
                    o.order_type,
                    o.status,
                    o.total,
                    t.table_number,
                    u.username as cashier
                FROM orders o
                LEFT JOIN tables t ON o.table_id = t.id
                LEFT JOIN users u ON o.created_by = u.id
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$startDate, $endDate, $limit, $offset]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) BETWEEN ? AND ?");
            $stmt->execute([$startDate, $endDate]);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            sendJsonResponse([
                'success' => true,
                'data' => $transactions,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'export':
            // Export to CSV
            $format = $_GET['format'] ?? 'csv';
            $report_type = $_GET['type'] ?? 'transactions';
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-d');
            
            if ($report_type === 'transactions') {
                $stmt = $db->prepare("
                    SELECT 
                        o.order_number as 'Order Number',
                        DATE_FORMAT(o.created_at, '%Y-%m-%d %H:%i:%s') as 'Date Time',
                        o.customer_name as 'Customer',
                        o.order_type as 'Type',
                        o.status as 'Status',
                        o.subtotal as 'Subtotal',
                        o.tax as 'Tax',
                        o.service_charge as 'Service',
                        o.total as 'Total',
                        t.table_number as 'Table',
                        u.username as 'Cashier'
                    FROM orders o
                    LEFT JOIN tables t ON o.table_id = t.id
                    LEFT JOIN users u ON o.created_by = u.id
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
                    ORDER BY o.created_at DESC
                ");
                $stmt->execute([$startDate, $endDate]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                exportToExcel($data, "transactions_{$startDate}_to_{$endDate}.csv");
            } elseif ($report_type === 'menu_performance') {
                $stmt = $db->prepare("
                    SELECT 
                        m.name as 'Menu Item',
                        c.name as 'Category',
                        m.price as 'Price',
                        COALESCE(SUM(oi.quantity), 0) as 'Total Sold',
                        COALESCE(SUM(oi.subtotal), 0) as 'Revenue'
                    FROM menu_items m
                    LEFT JOIN categories c ON m.category_id = c.id
                    LEFT JOIN order_items oi ON m.id = oi.menu_item_id
                    LEFT JOIN orders o ON oi.order_id = o.id AND DATE(o.created_at) BETWEEN ? AND ? AND o.status != 'Cancelled'
                    GROUP BY m.id
                    ORDER BY total_sold DESC
                ");
                $stmt->execute([$startDate, $endDate]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                exportToExcel($data, "menu_performance_{$startDate}_to_{$endDate}.csv");
            }
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
