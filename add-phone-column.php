<?php
require_once 'config/database.php';
$pdo = getDbConnection();

echo "<h2>Adding Phone Column to Users Table</h2>";

try {
    // Check if phone column exists
    $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'phone'")->fetch();
    
    if ($check) {
        echo "<p style='color: blue;'>✓ Phone column already exists</p>";
    } else {
        // Add phone column
        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER email");
        echo "<p style='color: green;'><strong>✅ Successfully added phone column!</strong></p>";
    }
    
    // Now insert default admin user
    echo "<h3>Inserting Default Admin User</h3>";
    
    // Check if admin exists
    $adminCheck = $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetch();
    
    if ($adminCheck) {
        echo "<p style='color: blue;'>✓ Admin user already exists (ID: " . $adminCheck['id'] . ")</p>";
    } else {
        // Insert admin user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, role, phone) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        // Password: admin123 (hashed with PASSWORD_DEFAULT)
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        
        $stmt->execute([
            'admin',
            'admin@stasiun-kerang.com',
            $hashedPassword,
            'Administrator',
            'admin',
            '081234567890'
        ]);
        
        echo "<p style='color: green;'><strong>✅ Admin user created successfully!</strong></p>";
        echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>Login Credentials:</h4>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<p style='color: green;'><strong>✅ Setup complete! You can now login.</strong></p>";
    echo "<p>👉 Go to: <a href='index.html'>Login Page</a></p>";
    
    echo "<hr>";
    echo "<h3 style='color: red;'>⚠️ SECURITY WARNING:</h3>";
    echo "<p><strong>Delete this file NOW!</strong></p>";
    echo "<pre>git rm add-phone-column.php import-schema.php check-schema.php fix-schema.php
git commit -m \"Remove all setup files\"
git push</pre>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
