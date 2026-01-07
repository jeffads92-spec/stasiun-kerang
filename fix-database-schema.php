<?php
/**
 * Fix Database Schema - Run this once to fix all database issues
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Fixing Database Schema...</h1>";
echo "<pre>";

try {
    // Get database connection
    if (class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    } elseif (function_exists('getDbConnection')) {
        $db = getDbConnection();
    } else {
        die("Database connection method not found");
    }
    
    echo "✓ Database connected successfully\n\n";
    
    // Check and fix menu_items table
    echo "Checking menu_items table...\n";
    
    $stmt = $db->query("SHOW COLUMNS FROM menu_items");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Current columns: " . implode(', ', $columns) . "\n";
    
    // Add missing columns if needed
    if (!in_array('image_url', $columns)) {
        echo "Adding image_url column...\n";
        $db->exec("ALTER TABLE menu_items ADD COLUMN image_url VARCHAR(255) NULL AFTER description");
        echo "✓ image_url added\n";
    }
    
    if (!in_array('is_available', $columns)) {
        echo "Adding is_available column...\n";
        $db->exec("ALTER TABLE menu_items ADD COLUMN is_available TINYINT(1) DEFAULT 1 AFTER image_url");
        echo "✓ is_available added\n";
    }
    
    if (!in_array('created_at', $columns)) {
        echo "Adding created_at column...\n";
        $db->exec("ALTER TABLE menu_items ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "✓ created_at added\n";
    }
    
    if (!in_array('updated_at', $columns)) {
        echo "Adding updated_at column...\n";
        $db->exec("ALTER TABLE menu_items ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "✓ updated_at added\n";
    }
    
    // Check orders table
    echo "\nChecking orders table...\n";
    
    $stmt = $db->query("SHOW COLUMNS FROM orders");
    $orderColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('customer_phone', $orderColumns)) {
        echo "Adding customer_phone column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(20) NULL AFTER customer_name");
        echo "✓ customer_phone added\n";
    }
    
    if (!in_array('completed_at', $orderColumns)) {
        echo "Adding completed_at column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN completed_at TIMESTAMP NULL");
        echo "✓ completed_at added\n";
    }
    
    // Fix any NULL values in menu_items
    echo "\nFixing NULL values...\n";
    $db->exec("UPDATE menu_items SET is_available = 1 WHERE is_available IS NULL");
    echo "✓ Fixed NULL is_available values\n";
    
    // Create indexes for better performance
    echo "\nCreating indexes...\n";
    try {
        $db->exec("CREATE INDEX idx_menu_category ON menu_items(category_id)");
        echo "✓ Index on category_id created\n";
    } catch (PDOException $e) {
        echo "⊗ Index on category_id already exists\n";
    }
    
    try {
        $db->exec("CREATE INDEX idx_menu_available ON menu_items(is_available)");
        echo "✓ Index on is_available created\n";
    } catch (PDOException $e) {
        echo "⊗ Index on is_available already exists\n";
    }
    
    try {
        $db->exec("CREATE INDEX idx_orders_status ON orders(status)");
        echo "✓ Index on orders status created\n";
    } catch (PDOException $e) {
        echo "⊗ Index on orders status already exists\n";
    }
    
    try {
        $db->exec("CREATE INDEX idx_orders_date ON orders(created_at)");
        echo "✓ Index on orders created_at created\n";
    } catch (PDOException $e) {
        echo "⊗ Index on orders created_at already exists\n";
    }
    
    echo "\n========================================\n";
    echo "✅ DATABASE SCHEMA FIXED SUCCESSFULLY!\n";
    echo "========================================\n\n";
    
    // Show final schema
    echo "Final menu_items schema:\n";
    $stmt = $db->query("DESCRIBE menu_items");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\nYou can now use the system normally.\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
echo "<br><br>";
echo "<a href='menu-management.html' style='padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;'>Go to Menu Management</a>";
?>
