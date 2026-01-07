<?php
// Include database config and get connection
require_once 'config/database.php';
$pdo = getDbConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Database Schema</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #333; }
        h4 { color: #666; margin-top: 20px; }
        .success { color: green; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        hr { margin: 20px 0; border: 1px solid #ddd; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>";

echo "<h2>🔧 Fixing Database Schema</h2>";
echo "<p>Adding missing timestamp columns to all tables...</p><hr>";

try {
    // Get all tables in database
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p><strong>Found " . count($tables) . " tables. Processing...</strong></p><hr>";
    
    $fixedTables = [];
    $skippedTables = [];
    
    foreach ($tables as $table) {
        echo "<h4>🔍 Checking table: <strong>$table</strong></h4>";
        
        $changes = [];
        
        // Check and add created_at if missing
        $hasCreatedAt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'created_at'")->fetch();
        if (!$hasCreatedAt) {
            try {
                $sql = "ALTER TABLE `$table` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                $pdo->exec($sql);
                echo "<p class='success'>✅ Added 'created_at' column</p>";
                $changes[] = 'created_at';
            } catch (PDOException $e) {
                echo "<p class='warning'>⚠️ Could not add 'created_at': " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p class='info'>✓ Already has 'created_at' column</p>";
        }
        
        // Check and add updated_at if missing
        $hasUpdatedAt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'updated_at'")->fetch();
        if (!$hasUpdatedAt) {
            try {
                $sql = "ALTER TABLE `$table` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
                $pdo->exec($sql);
                echo "<p class='success'>✅ Added 'updated_at' column</p>";
                $changes[] = 'updated_at';
            } catch (PDOException $e) {
                echo "<p class='warning'>⚠️ Could not add 'updated_at': " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p class='info'>✓ Already has 'updated_at' column</p>";
        }
        
        if (empty($changes)) {
            echo "<p class='info'>✓ No changes needed - table is OK</p>";
            $skippedTables[] = $table;
        } else {
            echo "<p class='success'><strong>✅ Fixed: Added " . implode(', ', $changes) . "</strong></p>";
            $fixedTables[] = $table;
        }
        
        echo "<hr>";
    }
    
    // Final Summary
    echo "<h3>📊 Final Summary:</h3>";
    echo "<ul>";
    echo "<li><strong>Total tables processed:</strong> " . count($tables) . "</li>";
    echo "<li><strong class='success'>Tables fixed:</strong> " . count($fixedTables) . "</li>";
    echo "<li><strong class='info'>Tables already OK:</strong> " . count($skippedTables) . "</li>";
    echo "</ul>";
    
    if (!empty($fixedTables)) {
        echo "<p class='success'><strong>✅ Schema fix complete! Fixed tables:</strong></p>";
        echo "<ul>";
        foreach ($fixedTables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    }
    
    echo "<hr>";
    echo "<h3 class='error'>⚠️ IMPORTANT - Security Notice:</h3>";
    echo "<p><strong>Delete these debug files NOW to prevent security risks!</strong></p>";
    echo "<pre>git rm check-schema.php fix-schema.php
git commit -m \"Remove debug files\"
git push</pre>";
    
    echo "<p>After deleting, test your login again at: <a href='index.html'>index.html</a></p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ <strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
