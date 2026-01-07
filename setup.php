<?php
/**
 * Database Setup Script
 * Upload ke ROOT folder (bukan /api/)
 * Akses: https://your-app.railway.app/setup.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database config
require_once __DIR__ . '/config/database.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Stasiun Kerang</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); max-width: 800px; width: 100%; }
        h1 { color: #333; margin-bottom: 30px; text-align: center; }
        .result { padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        .warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 14px; }
        .btn { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; font-weight: 600; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🦐 Database Setup - Stasiun Kerang</h1>
        
        <?php
        try {
            $pdo = getDbConnection();
            echo '<div class="result success">✓ Koneksi database berhasil!</div>';
            
            // Create users table
            echo '<h3>Creating users table...</h3>';
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    full_name VARCHAR(100),
                    email VARCHAR(100),
                    role ENUM('admin', 'cashier', 'kitchen', 'waiter') DEFAULT 'cashier',
                    avatar VARCHAR(255),
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            echo '<div class="result success">✓ Table users created</div>';
            
            // Insert default users
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            if ($stmt->fetchColumn() == 0) {
                echo '<h3>Inserting default users...</h3>';
                $defaultUsers = [
                    ['admin', 'admin123', 'Administrator', 'admin@stasiun-kerang.com', 'admin'],
                    ['cashier', 'cashier123', 'Kasir 1', 'cashier@stasiun-kerang.com', 'cashier'],
                    ['kitchen', 'kitchen123', 'Kitchen Staff', 'kitchen@stasiun-kerang.com', 'kitchen']
                ];
                
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
                foreach ($defaultUsers as $user) {
                    $hashedPassword = password_hash($user[1], PASSWORD_DEFAULT);
                    $stmt->execute([$user[0], $hashedPassword, $user[2], $user[3], $user[4]]);
                }
                echo '<div class="result success">✓ Default users inserted (admin, cashier, kitchen)</div>';
            } else {
                echo '<div class="result info">ℹ Users already exist, skipping...</div>';
            }
            
            // Create categories table
            echo '<h3>Creating categories table...</h3>';
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS categories (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    icon VARCHAR(50),
                    sort_order INT DEFAULT 0,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            echo '<div class="result success">✓ Table categories created</div>';
            
            // Insert default categories
            $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
            if ($stmt->fetchColumn() == 0) {
                echo '<h3>Inserting default categories...</h3>';
                $categories = [
                    ['Seafood', 'Fresh seafood dishes', '🦐', 1],
                    ['Kerang Special', 'Special shellfish dishes', '🦪', 2],
                    ['Appetizers', 'Starters and appetizers', '🥗', 3],
                    ['Main Course', 'Main dishes', '🍛', 4],
                    ['Beverages', 'Drinks and beverages', '🥤', 5],
                    ['Desserts', 'Sweet treats', '🍰', 6]
                ];
                
                $stmt = $pdo->prepare("INSERT INTO categories (name, description, icon, sort_order) VALUES (?, ?, ?, ?)");
                foreach ($categories as $cat) {
                    $stmt->execute($cat);
                }
                echo '<div class="result success">✓ Default categories inserted</div>';
            } else {
                echo '<div class="result info">ℹ Categories already exist, skipping...</div>';
            }
            
            // Create menu_items table
            echo '<h3>Creating menu_items table...</h3>';
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS menu_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    category_id INT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    price DECIMAL(10,2) NOT NULL,
                    cost_price DECIMAL(10,2),
                    image VARCHAR(255),
                    is_available BOOLEAN DEFAULT TRUE,
                    is_featured BOOLEAN DEFAULT FALSE,
                    preparation_time INT DEFAULT 15,
                    stock_quantity INT,
                    calories INT,
                    spicy_level ENUM('none', 'mild', 'medium', 'hot', 'very_hot') DEFAULT 'none',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (category_id) REFERENCES categories(id)
                )
            ");
            echo '<div class="result success">✓ Table menu_items created</div>';
            
            // Insert sample menu items
            $stmt = $pdo->query("SELECT COUNT(*) FROM menu_items");
            if ($stmt->fetchColumn() == 0) {
                echo '<h3>Inserting sample menu items...</h3>';
                $menuItems = [
                    [1, 'Kerang Asam Manis', 'Kerang segar dengan saus asam manis pedas', 85000, 55000, 15, 'medium', true, true],
                    [1, 'Udang Goreng Mentega', 'Udang jumbo goreng dengan saus mentega gurih', 95000, 65000, 20, 'mild', true, true],
                    [1, 'Cumi Saus Padang', 'Cumi segar dengan bumbu Padang kaya rempah', 78000, 48000, 18, 'hot', true, false],
                    [2, 'Kepiting Saos Tiram', 'Kepiting segar dengan saus tiram spesial', 150000, 100000, 25, 'none', true, true],
                    [3, 'Nasi Putih', 'Nasi putih hangat', 15000, 5000, 5, 'none', true, false],
                    [5, 'Es Teh Manis', 'Teh manis dingin segar', 8000, 3000, 3, 'none', true, false]
                ];
                
                $stmt = $pdo->prepare("INSERT INTO menu_items (category_id, name, description, price, cost_price, preparation_time, spicy_level, is_available, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($menuItems as $item) {
                    $stmt->execute($item);
                }
                echo '<div class="result success">✓ Sample menu items inserted</div>';
            } else {
                echo '<div class="result info">ℹ Menu items already exist, skipping...</div>';
            }
            
            // Create tables table
            echo '<h3>Creating tables table...</h3>';
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tables (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    table_number VARCHAR(20) UNIQUE NOT NULL,
                    capacity INT NOT NULL,
                    location VARCHAR(50),
                    status ENUM('available', 'occupied', 'reserved', 'maintenance') DEFAULT 'available',
                    qr_code VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            echo '<div class="result success">✓ Table tables created</div>';
            
            // Create orders table
            echo '<h3>Creating orders table...</h3>';
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS orders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_number VARCHAR(50) UNIQUE NOT NULL,
                    table_id INT,
                    table_number VARCHAR(20),
                    customer_name VARCHAR(100),
                    customer_phone VARCHAR(20),
                    user_id INT,
                    order_type ENUM('dine_in', 'takeaway', 'delivery') DEFAULT 'dine_in',
                    status ENUM('pending', 'preparing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
                    subtotal DECIMAL(10,2) NOT NULL,
                    tax DECIMAL(10,2) DEFAULT 0,
                    service_charge DECIMAL(10,2) DEFAULT 0,
                    discount DECIMAL(10,2) DEFAULT 0,
                    total DECIMAL(10,2) NOT NULL,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            ");
            echo '<div class="result success">✓ Table orders created</div>';
            
            // Create order_items table
            echo '<h3>Creating order_items table...</h3>';
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS order_items (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    menu_item_id INT NOT NULL,
                    menu_name VARCHAR(255) NOT NULL,
                    quantity INT NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    subtotal DECIMAL(10,2) NOT NULL,
                    notes TEXT,
                    status ENUM('pending', 'preparing', 'ready', 'served') DEFAULT 'pending',
                    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
                )
            ");
            echo '<div class="result success">✓ Table order_items created</div>';
            
            // Create payments table
            echo '<h3>Creating payments table...</h3>';
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    payment_number VARCHAR(50) UNIQUE NOT NULL,
                    order_id INT NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    payment_method ENUM('cash', 'card', 'qr_code', 'transfer') NOT NULL,
                    transaction_id VARCHAR(100),
                    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (order_id) REFERENCES orders(id)
                )
            ");
            echo '<div class="result success">✓ Table payments created</div>';
            
            // Create settings table
            echo '<h3>Creating settings table...</h3>';
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(100) UNIQUE NOT NULL,
                    setting_value TEXT,
                    setting_type VARCHAR(20) DEFAULT 'string',
                    description TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            echo '<div class="result success">✓ Table settings created</div>';
            
            echo '<div class="result success" style="margin-top: 30px; font-size: 18px;">
                <strong>🎉 Database setup completed successfully!</strong>
            </div>';
            
            echo '<h3 style="margin-top: 30px;">Default Login Credentials:</h3>';
            echo '<pre>';
            echo "Admin:\n";
            echo "  Username: admin\n";
            echo "  Password: admin123\n\n";
            echo "Cashier:\n";
            echo "  Username: cashier\n";
            echo "  Password: cashier123\n\n";
            echo "Kitchen:\n";
            echo "  Username: kitchen\n";
            echo "  Password: kitchen123\n";
            echo '</pre>';
            
            echo '<div class="result warning">
                <strong>⚠️ IMPORTANT:</strong> Setelah setup selesai, HAPUS atau RENAME file setup.php ini untuk keamanan!
            </div>';
            
            echo '<a href="index.html" class="btn">→ Go to Login Page</a>';
            
        } catch (PDOException $e) {
            echo '<div class="result error">❌ Error: ' . $e->getMessage() . '</div>';
            echo '<pre>' . $e->getTraceAsString() . '</pre>';
        } catch (Exception $e) {
            echo '<div class="result error">❌ Error: ' . $e->getMessage() . '</div>';
        }
        ?>
    </div>
</body>
</html>
