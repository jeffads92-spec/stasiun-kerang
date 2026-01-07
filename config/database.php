<?php
/**
 * Database Configuration - Fixed for Railway
 * Supports both Railway.app production and local development
 */

function getDbConnection() {
    // Railway.app environment variables
    $host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'localhost';
    $port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306';
    $database = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'stasiun_kerang_pos';
    $username = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
    $password = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: '';

    // Build DSN
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        // Set charset after connection instead of in DSN options
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        return $pdo;
    } catch (PDOException $e) {
        // Log error but don't expose sensitive details
        error_log("Database connection failed: " . $e->getMessage());
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed. Please contact administrator.',
            'error' => $e->getMessage() // Remove this in production
        ]);
        exit;
    }
}

// Helper function to execute queries safely
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        throw $e;
    }
}

// Helper function for transactions
function executeTransaction($pdo, callable $callback) {
    try {
        $pdo->beginTransaction();
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Transaction failed: " . $e->getMessage());
        throw $e;
    }
}

// Helper function to generate unique order numbers
function generateOrderNumber($pdo) {
    $date = date('Ymd');
    $prefix = 'ORD-' . $date . '-';
    
    $stmt = $pdo->prepare("SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastOrder = $stmt->fetch();
    
    if ($lastOrder) {
        $lastNumber = intval(substr($lastOrder['order_number'], -4));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

// Helper function to generate payment numbers
function generatePaymentNumber($pdo) {
    $date = date('Ymd');
    $prefix = 'PAY-' . $date . '-';
    
    $stmt = $pdo->prepare("SELECT payment_number FROM payments WHERE payment_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastPayment = $stmt->fetch();
    
    if ($lastPayment) {
        $lastNumber = intval(substr($lastPayment['payment_number'], -4));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

// Helper function to format currency
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Helper function to calculate tax (PPN 10%)
function calculateTax($amount, $rate = 0.10) {
    return round($amount * $rate, 2);
}

// Helper function to validate date format
function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Return connection for scripts that just need the PDO object
return getDbConnection();
?>
