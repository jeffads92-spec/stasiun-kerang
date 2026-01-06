<?php
/**
 * Reports & Analytics API
 * Generate various business reports
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Only admin and cashier can access reports
if (!in_array($_SESSION['role'], ['admin', 'cashier'])) {
    sendResponse(403, false, 'Forbidden: Insufficient permissions');
    exit();
}

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
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        sendResponse(400, false, 'Invalid date format. Use YYYY-MM-DD');
        return;
    }
    
    if (strtotime($startDate) > strtotime($endDate)) {
        sendResponse(400, false, 'Start date cannot be after end date');
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
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Compare with previous period
        $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
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
            (($summary['total_revenue'] - $prevRevenue) / $prevRevenue) * 100 : 0;
        
        // Payment method breakdown
        $stmt = $pdo->prepare("
            SELECT 
                p.payment_method,
                COUNT(*) as transaction_count,
                COALESCE(SUM(p.amount), 0) as total_amount
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            WHERE DATE(p.created_at) BETWEEN ? AND ?
            AND p.payment_status = 'completed'
            GROUP BY p.payment_method
        ");
        $stmt->execute([$startDate, $endDate]);
        $paymentBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, true, 'Sales report generated', [
            'summary' => $summary,
            'revenue_change' => round($revenueChange, 2),
            'payment_breakdown' => $paymentBreakdown,
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
        error_log("Sales report error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function getSalesTrend($pdo) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        sendResponse(400, false, 'Invalid date format. Use YYYY-MM-DD');
        return;
    }
    
    if (strtotime($startDate) > strtotime($endDate)) {
        sendResponse(400, false, 'Start date cannot be after end date');
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
        $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, true, 'Sales trend retrieved', [
            'trend' => $trend,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Sales trend error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function getMenuPerformance($pdo) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    
    // Validate parameters
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        sendResponse(400, false, 'Invalid date format. Use YYYY-MM-DD');
        return;
    }
    
    if (strtotime($startDate) > strtotime($endDate)) {
        sendResponse(400, false, 'Start date cannot be after end date');
        return;
    }
    
    if ($limit < 1 || $limit > 100) {
        sendResponse(400, false, 'Limit must be between 1 and 100');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.id, m.name, m.price, m.cost_price,
                c.name as category_name,
                COUNT(oi.id) as order_count,
                COALESCE(SUM(oi.quantity), 0) as total_quantity,
                COALESCE(SUM(oi.subtotal), 0) as total_revenue,
                COALESCE(SUM(oi.subtotal - (m.cost_price * oi.quantity)), 0) as profit,
                ROUND(COALESCE(AVG(oi.quantity), 0), 2) as avg_quantity_per_order
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
        $performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, true, 'Menu performance retrieved', [
            'performance' => $performance,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Menu performance error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function getCategoryBreakdown($pdo) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        sendResponse(400, false, 'Invalid date format. Use YYYY-MM-DD');
        return;
    }
    
    if (strtotime($startDate) > strtotime($endDate)) {
        sendResponse(400, false, 'Start date cannot be after end date');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.name as category,
                COUNT(DISTINCT o.id) as orders_count,
                COALESCE(SUM(oi.quantity), 0) as items_sold,
                COALESCE(SUM(oi.subtotal), 0) as revenue
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
        $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(200, true, 'Category breakdown retrieved', [
            'breakdown' => $breakdown,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
        
    } catch (PDOException $e) {
        error_log("Category breakdown error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function getTransactions($pdo) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    
    // Validate parameters
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        sendResponse(400, false, 'Invalid date format. Use YYYY-MM-DD');
        return;
    }
    
    if (strtotime($startDate) > strtotime($endDate)) {
        sendResponse(400, false, 'Start date cannot be after end date');
        return;
    }
    
    if ($limit < 1 || $limit > 500) {
        sendResponse(400, false, 'Limit must be between 1 and 500');
        return;
    }
    
    if ($offset < 0) {
        sendResponse(400, false, 'Offset must be 0 or greater');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.id,
                o.order_number, 
                o.created_at, 
                o.customer_name, 
                o.customer_phone,
                t.table_number, 
                o.order_type, 
                o.total, 
                o.status,
                p.payment_method, 
                p.paid_at,
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
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
            'total_count' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + count($transactions)) < $totalCount
        ]);
        
    } catch (PDOException $e) {
        error_log("Transactions error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function exportReport($pdo) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $format = isset($_GET['format']) ? $_GET['format'] : 'csv';
    
    // Validate parameters
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        sendResponse(400, false, 'Invalid date format. Use YYYY-MM-DD');
        return;
    }
    
    if (strtotime($startDate) > strtotime($endDate)) {
        sendResponse(400, false, 'Start date cannot be after end date');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.order_number, 
                DATE_FORMAT(o.created_at, '%Y-%m-%d %H:%i:%s') as order_date,
                o.customer_name, 
                o.customer_phone,
                t.table_number, 
                o.order_type,
                o.subtotal, 
                o.tax, 
                o.service_charge, 
                o.discount, 
                o.total,
                o.status, 
                p.payment_method,
                DATE_FORMAT(p.paid_at, '%Y-%m-%d %H:%i:%s') as payment_date
            FROM orders o
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN payments p ON o.id = p.order_id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$startDate, $endDate]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="sales_report_' . date('Ymd_His') . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Add UTF-8 BOM for Excel compatibility
            fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
            
            // Header
            if (!empty($data)) {
                $headers = array_keys($data[0]);
                fputcsv($output, $headers);
            }
            
            // Data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit();
        } else {
            sendResponse(200, true, 'Export data retrieved', [
                'data' => $data,
                'format' => $format,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Export report error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}
?>
