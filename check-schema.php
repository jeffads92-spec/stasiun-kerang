<?php
// Get connection from database.php
$pdo = require_once 'config/database.php';

echo "<h2>Database Schema Check</h2>";

if (!$pdo) {
    die("❌ Failed to connect to database");
}

try {
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Total Tables: " . count($tables) . "</h3>";
    
    foreach ($tables as $table) {
        echo "<h4>📋 Table: <strong>$table</strong></h4>";
        
        // Get columns for each table
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin-bottom: 20px;'>";
        echo "<tr style='background: #f0f0f0;'>
                <th>Field</th>
                <th>Type</th>
                <th>Null</th>
                <th>Key</th>
                <th>Default</th>
                <th>Extra</th>
              </tr>";
        
        $hasCreatedAt = false;
        $hasUpdatedAt = false;
        
        foreach ($columns as $col) {
            if ($col['Field'] === 'created_at') $hasCreatedAt = true;
            if ($col['Field'] === 'updated_at') $hasUpdatedAt = true;
            
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Show warnings
        if (!$hasCreatedAt) {
            echo "⚠️ <span style='color: orange;'><strong>Missing 'created_at' column!</strong></span><br>";
        }
        if (!$hasUpdatedAt) {
            echo "❌ <span style='color: red;'><strong>Missing 'updated_at' column!</strong></span><br>";
        }
        if ($hasCreatedAt && $hasUpdatedAt) {
            echo "✅ <span style='color: green;'>Has timestamp columns</span><br>";
        }
        
        echo "<hr style='margin: 20px 0;'>";
    }
    
    echo "<h3>Summary:</h3>";
    echo "<p>If any tables are missing 'updated_at' column, use fix-schema.php to add them.</p>";
    
} catch (PDOException $e) {
    echo "❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage());
}
?>
