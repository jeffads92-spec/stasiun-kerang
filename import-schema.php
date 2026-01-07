<?php
// Include database config
require_once 'config/database.php';
$pdo = getDbConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Import Database Schema</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #333; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        hr { margin: 20px 0; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>";

echo "<h2>📦 Import Database Schema</h2>";

// Check if schema.sql exists
if (!file_exists('config/schema.sql')) {
    echo "<p class='error'>❌ Error: config/schema.sql file not found!</p>";
    echo "<p>Please make sure the schema.sql file exists in the config/ directory.</p>";
    echo "</body></html>";
    exit;
}

echo "<p class='info'>Reading schema.sql file...</p>";

// Read schema file
$schema = file_get_contents('config/schema.sql');

if (!$schema) {
    echo "<p class='error'>❌ Error: Could not read schema.sql file!</p>";
    echo "</body></html>";
    exit;
}

echo "<p class='success'>✅ Schema file loaded (" . strlen($schema) . " bytes)</p><hr>";

// Disable foreign key checks temporarily
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "<p class='info'>🔓 Foreign key checks disabled</p>";
} catch (PDOException $e) {
    echo "<p class='warning'>⚠️ Warning: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Split SQL statements
// Handle both semicolon-separated statements and DELIMITER changes
$statements = [];
$currentStatement = '';
$delimiter = ';';

$lines = explode("\n", $schema);

foreach ($lines as $line) {
    $line = trim($line);
    
    // Skip comments and empty lines
    if (empty($line) || substr($line, 0, 2) === '--' || substr($line, 0, 1) === '#') {
        continue;
    }
    
    // Check for DELIMITER change
    if (stripos($line, 'DELIMITER') === 0) {
        $parts = explode(' ', $line);
        if (isset($parts[1])) {
            $delimiter = trim($parts[1]);
        }
        continue;
    }
    
    $currentStatement .= $line . "\n";
    
    // Check if statement is complete
    if (substr(trim($line), -strlen($delimiter)) === $delimiter) {
        $stmt = trim(substr($currentStatement, 0, -strlen($delimiter)));
        if (!empty($stmt)) {
            $statements[] = $stmt;
        }
        $currentStatement = '';
    }
}

// Add last statement if not empty
if (!empty(trim($currentStatement))) {
    $statements[] = trim($currentStatement);
}

echo "<p class='info'>📝 Found " . count($statements) . " SQL statements</p><hr>";

// Execute statements
$successCount = 0;
$errorCount = 0;
$skippedCount = 0;

foreach ($statements as $index => $statement) {
    $statementPreview = substr(str_replace(["\n", "\r"], ' ', $statement), 0, 100);
    
    try {
        $pdo->exec($statement);
        echo "<p class='success'>✅ Statement " . ($index + 1) . ": " . htmlspecialchars($statementPreview) . "...</p>";
        $successCount++;
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
        
        // Check if it's a "table already exists" error
        if (stripos($errorMsg, 'already exists') !== false || 
            stripos($errorMsg, 'Duplicate') !== false) {
            echo "<p class='info'>ℹ️ Statement " . ($index + 1) . ": Already exists (skipped)</p>";
            $skippedCount++;
        } else {
            echo "<p class='error'>❌ Statement " . ($index + 1) . " failed: " . htmlspecialchars($errorMsg) . "</p>";
            echo "<p style='color: #666; font-size: 0.9em;'>Statement: " . htmlspecialchars($statementPreview) . "...</p>";
            $errorCount++;
        }
    }
}

// Re-enable foreign key checks
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<hr><p class='info'>🔒 Foreign key checks re-enabled</p>";
} catch (PDOException $e) {
    echo "<p class='warning'>⚠️ Warning: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Final summary
echo "<hr><h3>📊 Import Summary:</h3>";
echo "<ul>";
echo "<li><strong class='success'>✅ Successful:</strong> $successCount statements</li>";
echo "<li><strong class='info'>ℹ️ Skipped (already exists):</strong> $skippedCount statements</li>";
echo "<li><strong class='error'>❌ Failed:</strong> $errorCount statements</li>";
echo "<li><strong>📝 Total:</strong> " . count($statements) . " statements</li>";
echo "</ul>";

if ($errorCount === 0) {
    echo "<p class='success'><strong>✅ Schema import completed successfully!</strong></p>";
    
    // Show created tables
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<h4>📋 Tables in database (" . count($tables) . "):</h4>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
    } catch (PDOException $e) {
        echo "<p class='warning'>Could not list tables: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<p>👉 Next step: Run <a href='fix-schema.php'>fix-schema.php</a> to ensure all tables have timestamp columns.</p>";
} else {
    echo "<p class='error'><strong>⚠️ Some statements failed. Please check the errors above.</strong></p>";
}

echo "<hr>";
echo "<h3 class='error'>⚠️ SECURITY WARNING:</h3>";
echo "<p><strong>Delete this file immediately after use!</strong></p>";
echo "<pre>git rm import-schema.php check-schema.php fix-schema.php
git commit -m \"Remove schema import tools\"
git push</pre>";

echo "</body></html>";
?>
