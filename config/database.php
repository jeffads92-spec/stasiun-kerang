<?php
/**
 * Database Configuration untuk Stasiun Kerang Restaurant System
 * Railway MySQL Configuration
 */

function getDbConnection() {
    // KONFIGURASI RAILWAY - SUDAH TERBUKTI BERHASIL
    $host = 'mysql.railway.internal';
    $port = 3306;
    $database = 'railway';
    $username = 'root';
    $password = 'CvUzjFrJOQlpNjgoNNZjLmZpBRtegusm';
    
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    
    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);
        return $pdo;
    } catch (Exception $e) {
        die("Error koneksi database: " . $e->getMessage());
    }
}

// Fungsi-fungsi helper
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query gagal: " . $e->getMessage());
        throw $e;
    }
}

function executeTransaction($pdo, callable $callback) {
    try {
        $pdo->beginTransaction();
        $result = $callback($pdo);
        $pdo->commit();
        return $result;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Transaksi gagal: " . $e->getMessage());
        throw $e;
    }
}

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

function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function calculateTax($amount, $rate = 0.10) {
    return round($amount * $rate, 2);
}

function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

date_default_timezone_set('Asia/Jakarta');
?>
