<?php
/**
 * Database Configuration - Compatible version
 * Supports both Railway.app production and local development
 * Provides both function-based and class-based access
 */

// ============================================
// FUNCTION-BASED APPROACH (Original)
// ============================================

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
        
        // Set charset after connection
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        return $pdo;
    } catch (PDOException $e) {
        // Log error but don't expose sensitive details
        error_log("Database connection failed: " . $e->getMessage());
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed. Please contact administrator.'
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

// ============================================
// CLASS-BASED APPROACH (For Compatibility)
// ============================================

class Database {
    private static $instance = null;
    private $connection = null;
    
    /**
     * Private constructor
     */
    private function __construct() {
        // Railway.app environment variables
        $host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'localhost';
        $port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306';
        $database = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'stasiun_kerang_pos';
        $username = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
        $password = getenv('MYSQLPASSWORD') ?: getenv('DB_PASSWORD') ?: '';
        
        // Build DSN
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        
        try {
            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            // Set charset after connection
            $this->connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please contact administrator.");
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// ============================================
// SETUP
// ============================================

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// For backward compatibility with scripts that use direct include
// Uncomment the line below if needed
// return getDbConnection();
?>
