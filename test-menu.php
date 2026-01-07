<?php
/**
 * Test Menu Addition - Debug Script
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h2>Testing Menu System</h2>";

$pdo = getDbConnection();

// Check if uploads directory exists
echo "<h3>1. Checking Upload Directory</h3>";
$uploadDir = __DIR__ . '/uploads';
if (!file_exists($uploadDir)) {
    echo "<p style='color: orange;'>⚠️ Upload directory does not exist. Creating...</p>";
    if (mkdir($uploadDir, 0755, true)) {
        echo "<p style='color: green;'>✅ Created uploads directory</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create uploads directory</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Upload directory exists</p>";
}

// Check if helpers directory exists
echo "<h3>2. Checking Helpers Directory</h3>";
$helpersDir = __DIR__ . '/helpers';
if (!file_exists($helpersDir)) {
    echo "<p style='color: orange;'>⚠️ Helpers directory does not exist. Creating...</p>";
    if (mkdir($helpersDir, 0755, true)) {
        echo "<p style='color: green;'>✅ Created helpers directory</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create helpers directory</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Helpers directory exists</p>";
}

// Check if functions.php exists
$functionsFile = $helpersDir . '/functions.php';
if (!file_exists($functionsFile)) {
    echo "<p style='color: orange;'>⚠️ functions.php does not exist</p>";
    echo "<p>Please create helpers/functions.php file from the artifact provided</p>";
} else {
    echo "<p style='color: green;'>✅ functions.php exists</p>";
}

// Check categories
echo "<h3>3. Checking Categories</h3>";
try {
    $categories = $pdo->query("SELECT id, name FROM categories")->fetchAll();
    if (empty($categories)) {
        echo "<p style='color: orange;'>⚠️ No categories found. Adding sample categories...</p>";
        
        $sampleCategories = [
            ['Seafood', 'Fresh seafood dishes', '🦐'],
            ['Beverages', 'Drinks and beverages', '🥤'],
            ['Main Course', 'Main dishes', '🍽️'],
            ['Dessert', 'Sweet desserts', '🍰']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO categories (name, description, icon) VALUES (?, ?, ?)");
        foreach ($sampleCategories as $cat) {
            $stmt->execute($cat);
            echo "<p style='color: green;'>✅ Added category: {$cat[0]}</p>";
        }
        
        $categories = $pdo->query("SELECT id, name FROM categories")->fetchAll();
    }
    
    echo "<p style='color: green;'>✅ Found " . count($categories) . " categories:</p>";
    echo "<ul>";
    foreach ($categories as $cat) {
        echo "<li>ID: {$cat['id']} - {$cat['name']}</li>";
    }
    echo "</ul>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test insert menu item
echo "<h3>4. Test Insert Menu Item</h3>";
try {
    // Get first category
    $category = $pdo->query("SELECT id FROM categories LIMIT 1")->fetch();
    
    if ($category) {
        $testData = [
            'category_id' => $category['id'],
            'name' => 'Test Menu Item',
            'description' => 'This is a test menu item',
            'price' => 50000,
            'cost_price' => 30000,
            'is_available' => 1,
            'preparation_time' => 15
        ];
        
        $sql = "INSERT INTO menu_items (category_id, name, description, price, cost_price, is_available, preparation_time) 
                VALUES (:category_id, :name, :description, :price, :cost_price, :is_available, :preparation_time)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($testData);
        
        echo "<p style='color: green;'>✅ Successfully inserted test menu item (ID: " . $pdo->lastInsertId() . ")</p>";
        
        // Clean up test data
        $pdo->exec("DELETE FROM menu_items WHERE name = 'Test Menu Item'");
        echo "<p style='color: blue;'>ℹ️ Test data cleaned up</p>";
    } else {
        echo "<p style='color: red;'>❌ No categories available for testing</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Insert failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check permissions
echo "<h3>5. Directory Permissions</h3>";
echo "<ul>";
echo "<li>uploads/ is " . (is_writable($uploadDir) ? "<span style='color: green;'>writable ✅</span>" : "<span style='color: red;'>NOT writable ❌</span>") . "</li>";
echo "<li>helpers/ is " . (is_writable($helpersDir) ? "<span style='color: green;'>writable ✅</span>" : "<span style='color: red;'>NOT writable ❌</span>") . "</li>";
echo "</ul>";

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>If all checks pass, try adding a menu item again from the menu management page.</p>";
echo "<p><a href='menu-management.html'>Go to Menu Management</a></p>";

echo "<hr>";
echo "<p style='color: red;'><strong>⚠️ Delete this file after testing!</strong></p>";
echo "<pre>git rm test-menu.php
git commit -m \"Remove test file\"
git push</pre>";
?>
