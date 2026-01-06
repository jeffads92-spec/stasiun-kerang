<?php
/**
 * Reports & Analytics API
 * Generate various business reports
 */

header('Content-Type: application/json');
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../middleware/auth.php';

// Only admin and cashier can access reports
requireRole(['admin', 'cashier']);

$action = isset($_GET['action']) ? $_GET['action'] : 'summary';

try {
    $pdo = getDbConnection();
    
    switch ($action) {
        case 'summary':
            getSalesReport($pdo);
            break;
        case 'sales_trend':
            getSalesTrend($pdo);
            break;
        case 'menu_performance':
            getMenuPerformance($pdo);
            break;
        case 'category_breakdown':
            getCategoryBreakdown($pdo);
            break;
        case 'transactions':
            getTransactions($pdo);
            break;
        case 'export':
            exportReport($pdo);
            break;
        default:
            sendResponse(400, false, 'Invalid action');
    }
    
} catch (Exception $e) {
    sendResponse(500, false, 'Server error: ' . $e->getMessage());
}

function getSalesReport($pdo) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Validate dates
    if (!strtotime($startDate) || !strtotime($endDate)) {
        sendResponse(400, false, 'Invalid date format');
        return;
    }
    
    try {
        // Total statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                COALESCE(SUM(total), 0) as total_revenue,
                COALESCE(AVG(total), 0) as average_transaction,
                COUNT(DISTINCT customer_phone) as unique_customers
            FROM orders
            WHERE DATE(created_at) BETWEEN ? AND ?
            AND status != 'cancelled'
        ");
        $stmt->execute([$startDate, $endDate]);
        $summary = $stmt->fetch();
        
        // Compare with previous period
        $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
        $prevStartDate = date('Y-m-d', strtotime($startDate . " -{$daysDiff} days"));
        $prevEndDate = date('Y-m-d', strtotime($endDate . " -{$daysDiff} days"));
        
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total), 0) as prev_revenue
            FROM orders
            WHERE DATE(created_at) BETWEEN ? AND ?
            AND status != 'cancelled'
        ");
        $stmt->execute([$prevStartDate, $prevEndDate]);
        $prevRevenue = $stmt->fetch()['prev_revenue'];
        
        $revenueChange = $prevRevenue > 0 ? 
            (($summary['total_revenue'] - $prevRevenue) / $prevRevenue) * 100 : 
            ($summary['total_revenue'] > 0 ? 100 : 0);
        
        sendResponse(200, true, 'Sales report generated', [
            'summary' => $summary,
            'revenue_change' => round($revenueChange, 2),
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'previous_period' => [
                    'start_date' => $prevStartDate,
                    'end_date' => $prevEndDate
                ]
            ]
        ]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getSalesTrend($pdo) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Validate dates
    if (!strtotime($startDate) || !strtotime($endDate)) {
        sendResponse(400, false, 'Invalid date format');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders_count,
                COALESCE(SUM(total), 0) as revenue,
                COALESCE(AVG(total), 0) as average_order
            FROM orders
            WHERE DATE(created_at) BETWEEN ? AND ?
            AND status != 'cancelled'
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$startDate, $endDate]);
        $trend = $stmt->fetchAll();
        
        sendResponse(200, true, 'Sales trend retrieved', ['trend' => $trend]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getMenuPerformance($pdo) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    
    // Validate dates and limit
    if (!strtotime($startDate) || !strtotime($endDate)) {
        sendResponse(400, false, 'Invalid date format');
        return;
    }
    if ($limit < 1 || $limit > 100) {
        $limit = 10;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.id, m.name, m.price, m.cost_price,
                c.name as category_name,
                COUNT(oi.id) as order_count,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.subtotal) as total_revenue,
                COALESCE(SUM(oi.subtotal - (m.cost_price * oi.quantity)), 0) as profit
            FROM menu_items m
            LEFT JOIN order_items oi ON m.id = oi.menu_item_id
            LEFT JOIN orders o ON oi.order_id = o.id
            LEFT JOIN categories c ON m.category_id = c.id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
            AND o.status != 'cancelled'
            GROUP BY m.id
            ORDER BY total_quantity DESC
            LIMIT ?
        ");
        $stmt->execute([$startDate, $endDate, $limit]);
        $performance = $stmt->fetchAll();
        
        sendResponse(200, true, 'Menu performance retrieved', ['performance' => $performance]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getCategoryBreakdown($pdo) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Validate dates
    if (!strtotime($startDate) || !strtotime($endDate)) {
        sendResponse(400, false, 'Invalid date format');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.name as category,
                COUNT(DISTINCT o.id) as orders_count,
                SUM(oi.quantity) as items_sold,
                SUM(oi.subtotal) as revenue
            FROM categories c
            LEFT JOIN menu_items m ON c.id = m.category_id
            LEFT JOIN order_items oi ON m.id = oi.menu_item_id
            LEFT JOIN orders o ON oi.order_id = o.id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
            AND o.status != 'cancelled'
            GROUP BY c.id
            ORDER BY revenue DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $breakdown = $stmt->fetchAll();
        
        sendResponse(200, true, 'Category breakdown retrieved', ['breakdown' => $breakdown]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getTransactions($pdo) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    // Validate dates, limit and offset
    if (!strtotime($startDate) || !strtotime($endDate)) {
        sendResponse(400, false, 'Invalid date format');
        return;
    }
    if ($limit < 1 || $limit > 1000) {
        $limit = 100;
    }
    if ($offset < 0) {
        $offset = 0;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.order_number, o.created_at, o.customer_name, o.customer_phone,
                t.table_number, o.order_type, o.total, o.status,
                p.payment_method, p.paid_at,
                COUNT(oi.id) as items_count
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN payments p ON o.id = p.order_id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$startDate, $endDate, $limit, $offset]);
        $transactions = $stmt->fetchAll();
        
        // Get total count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM orders
            WHERE DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        $totalCount = $stmt->fetch()['total'];
        
        sendResponse(200, true, 'Transactions retrieved', [
            'transactions' => $transactions,
            'total_count' => $totalCount,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function exportReport($pdo) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
    
    // Validate dates
    if (!strtotime($startDate) || !strtotime($endDate)) {
        sendResponse(400, false, 'Invalid date format');
        return;
    }
    
    // Validate format
    if (!in_array($format, ['csv', 'json'])) {
        sendResponse(400, false, 'Invalid format. Use csv or json');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.order_number, 
                DATE_FORMAT(o.created_at, '%Y-%m-%d %H:%i:%s') as order_date,
                o.customer_name, o.customer_phone,
                t.table_number, o.order_type,
                o.subtotal, o.tax, o.service_charge, o.discount, o.total,
                o.status, p.payment_method
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN payments p ON o.id = p.order_id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $data = $stmt->fetchAll();
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="report_' . date('YmdHis') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Header
            if (!empty($data)) {
                fputcsv($output, array_keys($data[0]));
            }
            
            // Data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit();
        } else {
            sendResponse(200, true, 'Export data retrieved', ['data' => $data]);
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
