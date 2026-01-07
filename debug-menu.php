<?php
// Support both function and class approach
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} elseif (file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
}

try {
    // Try class approach first, fall back to function
    if (class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    } else {
        $db = getDbConnection();
    }
    
    // Insert categories first
    echo "Menambahkan kategori...\n";
    
    $categories = [
        ['name' => 'Makanan Utama', 'description' => 'Menu makanan utama'],
        ['name' => 'Minuman', 'description' => 'Menu minuman'],
        ['name' => 'Snack', 'description' => 'Menu snack dan cemilan'],
        ['name' => 'Dessert', 'description' => 'Menu penutup']
    ];
    
    $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    
    foreach ($categories as $cat) {
        try {
            $stmt->execute([$cat['name'], $cat['description']]);
            echo "✓ Kategori '{$cat['name']}' ditambahkan\n";
        } catch (PDOException $e) {
            echo "✗ Kategori '{$cat['name']}' sudah ada\n";
        }
    }
    
    // Get category IDs
    $stmt = $db->query("SELECT id, name FROM categories");
    $catMap = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $catMap[$row['name']] = $row['id'];
    }
    
    // Insert menu items
    echo "\nMenambahkan menu...\n";
    
    $menuItems = [
        // Makanan Utama
        ['name' => 'Nasi Goreng Seafood', 'category' => 'Makanan Utama', 'price' => 35000, 'description' => 'Nasi goreng dengan seafood segar'],
        ['name' => 'Mie Goreng Spesial', 'category' => 'Makanan Utama', 'price' => 30000, 'description' => 'Mie goreng dengan topping lengkap'],
        ['name' => 'Soto Ayam', 'category' => 'Makanan Utama', 'price' => 25000, 'description' => 'Soto ayam dengan kuah gurih'],
        ['name' => 'Ayam Penyet', 'category' => 'Makanan Utama', 'price' => 28000, 'description' => 'Ayam goreng dengan sambal terasi'],
        ['name' => 'Nasi Uduk Komplit', 'category' => 'Makanan Utama', 'price' => 32000, 'description' => 'Nasi uduk dengan lauk lengkap'],
        
        // Minuman
        ['name' => 'Es Teh Manis', 'category' => 'Minuman', 'price' => 5000, 'description' => 'Teh manis dingin segar'],
        ['name' => 'Es Jeruk', 'category' => 'Minuman', 'price' => 8000, 'description' => 'Jeruk peras segar'],
        ['name' => 'Jus Alpukat', 'category' => 'Minuman', 'price' => 15000, 'description' => 'Jus alpukat segar'],
        ['name' => 'Kopi Susu', 'category' => 'Minuman', 'price' => 12000, 'description' => 'Kopi susu hangat'],
        ['name' => 'Es Campur', 'category' => 'Minuman', 'price' => 18000, 'description' => 'Es campur dengan buah dan topping'],
        
        // Snack
        ['name' => 'Pisang Goreng', 'category' => 'Snack', 'price' => 10000, 'description' => 'Pisang goreng crispy'],
        ['name' => 'Tahu Crispy', 'category' => 'Snack', 'price' => 12000, 'description' => 'Tahu goreng renyah'],
        ['name' => 'Kentang Goreng', 'category' => 'Snack', 'price' => 15000, 'description' => 'Kentang goreng dengan saus'],
        
        // Dessert
        ['name' => 'Es Krim Vanilla', 'category' => 'Dessert', 'price' => 12000, 'description' => 'Es krim vanilla premium'],
        ['name' => 'Puding Coklat', 'category' => 'Dessert', 'price' => 10000, 'description' => 'Puding coklat lembut']
    ];
    
    $stmt = $db->prepare("
        INSERT INTO menu_items (name, category_id, price, description, is_available) 
        VALUES (?, ?, ?, ?, 1)
    ");
    
    $success = 0;
    $skipped = 0;
    
    foreach ($menuItems as $item) {
        if (!isset($catMap[$item['category']])) {
            echo "✗ Kategori '{$item['category']}' tidak ditemukan untuk '{$item['name']}'\n";
            continue;
        }
        
        try {
            $stmt->execute([
                $item['name'],
                $catMap[$item['category']],
                $item['price'],
                $item['description']
            ]);
            echo "✓ Menu '{$item['name']}' ditambahkan (Rp " . number_format($item['price'], 0, ',', '.') . ")\n";
            $success++;
        } catch (PDOException $e) {
            echo "✗ Menu '{$item['name']}' sudah ada\n";
            $skipped++;
        }
    }
    
    echo "\n=================================\n";
    echo "SELESAI!\n";
    echo "✓ Berhasil: {$success} menu\n";
    echo "✗ Dilewati: {$skipped} menu\n";
    echo "=================================\n";
    
    // Show total menu
    $stmt = $db->query("SELECT COUNT(*) as total FROM menu_items");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "Total menu di database: {$total}\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
