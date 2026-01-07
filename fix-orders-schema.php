<?php
/**
 * Fix Orders Schema - Fix database issues for orders table
 */

require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Fixing Orders Schema...</h1>";
echo "<pre>";

try {
    if (class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    } elseif (function_exists('getDbConnection')) {
        $db = getDbConnection();
    } else {
        die("Database connection method not found");
    }
    
    echo "✓ Database connected successfully\n\n";
    
    // Check orders table
    echo "Checking orders table...\n";
    
    $stmt = $db->query("SHOW COLUMNS FROM orders");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Current columns: " . implode(', ', $columns) . "\n\n";
    
    // Add missing columns
    if (!in_array('order_number', $columns)) {
        echo "Adding order_number column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN order_number VARCHAR(50) UNIQUE AFTER id");
        echo "✓ order_number added\n";
    }
    
    if (!in_array('table_id', $columns)) {
        echo "Adding table_id column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN table_id INT NULL");
        echo "✓ table_id added\n";
    }
    
    if (!in_array('customer_name', $columns)) {
        echo "Adding customer_name column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN customer_name VARCHAR(100) NULL");
        echo "✓ customer_name added\n";
    }
    
    if (!in_array('customer_phone', $columns)) {
        echo "Adding customer_phone column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN customer_phone VARCHAR(20) NULL");
        echo "✓ customer_phone added\n";
    }
    
    if (!in_array('order_type', $columns)) {
        echo "Adding order_type column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN order_type VARCHAR(20) DEFAULT 'Dine In'");
        echo "✓ order_type added\n";
    }
    
    if (!in_array('status', $columns)) {
        echo "Adding status column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN status VARCHAR(20) DEFAULT 'Pending'");
        echo "✓ status added\n";
    }
    
    if (!in_array('subtotal', $columns)) {
        echo "Adding subtotal column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0");
        echo "✓ subtotal added\n";
    }
    
    if (!in_array('tax', $columns)) {
        echo "Adding tax column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN tax DECIMAL(10,2) DEFAULT 0");
        echo "✓ tax added\n";
    }
    
    if (!in_array('service_charge', $columns)) {
        echo "Adding service_charge column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN service_charge DECIMAL(10,2) DEFAULT 0");
        echo "✓ service_charge added\n";
    }
    
    if (!in_array('total', $columns)) {
        echo "Adding total column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN total DECIMAL(10,2) DEFAULT 0");
        echo "✓ total added\n";
    }
    
    if (!in_array('notes', $columns)) {
        echo "Adding notes column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN notes TEXT NULL");
        echo "✓ notes added\n";
    }
    
    if (!in_array('created_by', $columns)) {
        echo "Adding created_by column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN created_by INT NULL");
        echo "✓ created_by added\n";
    }
    
    if (!in_array('created_at', $columns)) {
        echo "Adding created_at column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "✓ created_at added\n";
    }
    
    if (!in_array('completed_at', $columns)) {
        echo "Adding completed_at column...\n";
        $db->exec("ALTER TABLE orders ADD COLUMN completed_at TIMESTAMP NULL");
        echo "✓ completed_at added\n";
    }
    
    // Check order_items table
    echo "\nChecking order_items table...\n";
    
    try {
        $stmt = $db->query("SHOW COLUMNS FROM order_items");
        $itemColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Current columns: " . implode(', ', $itemColumns) . "\n";
    } catch (PDOException $e) {
        echo "Creating order_items table...\n";
        $db->exec("
            CREATE TABLE order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                menu_item_id INT NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                price DECIMAL(10,2) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
            )
        ");
        echo "✓ order_items table created\n";
        $itemColumns = [];
    }
    
    if (!empty($itemColumns)) {
        if (!in_array('order_id', $itemColumns)) {
            $db->exec("ALTER TABLE order_items ADD COLUMN order_id INT NOT NULL");
            echo "✓ order_id added to order_items\n";
        }
        
        if (!in_array('menu_item_id', $itemColumns)) {
            $db->exec("ALTER TABLE order_items ADD COLUMN menu_item_id INT NOT NULL");
            echo "✓ menu_item_id added to order_items\n";
        }
        
        if (!in_array('quantity', $itemColumns)) {
            $db->exec("ALTER TABLE order_items ADD COLUMN quantity INT NOT NULL DEFAULT 1");
            echo "✓ quantity added to order_items\n";
        }
        
        if (!in_array('price', $itemColumns)) {
            $db->exec("ALTER TABLE order_items ADD COLUMN price DECIMAL(10,2) NOT NULL");
            echo "✓ price added to order_items\n";
        }
        
        if (!in_array('subtotal', $itemColumns)) {
            $db->exec("ALTER TABLE order_items ADD COLUMN subtotal DECIMAL(10,2) NOT NULL");
            echo "✓ subtotal added to order_items\n";
        }
    }
    
    // Test query that orders API uses
    echo "\nTesting orders API query...\n";
    
    try {
        $stmt = $db->query("
            SELECT 
                o.*,
                u.username as created_by_name,
                t.table_number,
                COUNT(oi.id) as item_count
            FROM orders o
            LEFT JOIN users u ON o.created_by = u.id
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            GROUP BY o.id
            LIMIT 1
        ");
        echo "✓ Orders query test successful\n";
    } catch (PDOException $e) {
        echo "✗ Orders query test failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n========================================\n";
    echo "✅ ORDERS SCHEMA FIXED SUCCESSFULLY!\n";
    echo "========================================\n\n";
    
    // Show final schema
    echo "Final orders schema:\n";
    $stmt = $db->query("DESCRIBE orders");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\nFinal order_items schema:\n";
    $stmt = $db->query("DESCRIBE order_items");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\nOrders API should now work properly!\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}

echo "</pre>";
echo "<br><br>";
echo "<a href='test-all-apis.html' style='padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;'>Test APIs Again</a>";
echo " ";
echo "<a href='orders.html' style='padding: 10px 20px; background: #27ae60; color: white; text-decoration: none; border-radius: 5px;'>Go to Orders</a>";
?>
