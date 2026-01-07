<?php
require_once 'config/database.php';

echo "<h2>Database Schema Check</h2>";

try {
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Tables in Database:</h3>";
    
    foreach ($tables as $table) {
        echo "<h4>Table: $table</h4>";
        
        // Get columns for each table
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        $hasUpdatedAt = false;
        foreach ($columns as $col) {
            if ($col['Field'] === 'updated_at') {
                $hasUpdatedAt = true;
            }
            echo "<tr>";
            echo "<td>" . $col['Field'] . "</td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . $col['Null'] . "</td>";
            echo "<td>" . $col['Key'] . "</td>";
            echo "<td>" . $col['Default'] . "</td>";
            echo "<td>" . $col['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if (!$hasUpdatedAt) {
            echo "⚠️ <strong>Missing 'updated_at' column!</strong><br>";
        }
        echo "<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
