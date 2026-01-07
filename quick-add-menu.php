<?php
header('Content-Type: text/html; charset=utf-8');

require_once 'config/database.php';

// Use class if available, otherwise function
try {
    if (class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    } else {
        $db = getDbConnection();
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

echo "<h1>Adding Sample Menu Items...</h1>";
echo "<pre>";

// Categories already exist (from your test), let's get their IDs
$stmt = $db->query("SELECT id, name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($categories) . " categories:\n";
foreach ($categories as $cat) {
    echo "  - ID {$cat['id']}: {$cat['name']}\n";
}
echo "\n";

// Menu items to insert
$menuItems = [
    // Seafood (ID: 1)
    ['Kerang Saus Padang', 1, 45000, 'Kerang hijau segar dengan saus padang pedas'],
    ['Cumi Goreng Tepung', 1, 38000, 'Cumi goreng tepung crispy'],
    ['Udang Bakar Madu', 1, 55000, 'Udang bakar dengan saus madu'],
    ['Ikan Gurame Goreng', 1, 65000, 'Ikan gurame goreng crispy'],
    
    // Kerang Special (ID: 2)
    ['Kerang Hijau Rebus', 2, 35000, 'Kerang hijau rebus bumbu rahasia'],
    ['Kerang Dara Saus Tiram', 2, 42000, 'Kerang dara dengan saus tiram'],
    ['Kerang Bambu Bakar', 2, 50000, 'Kerang bambu bakar bumbu khas'],
    
    // Appetizers (ID: 3)
    ['Tahu Crispy', 3, 15000, 'Tahu goreng crispy dengan saus'],
    ['Kentang Goreng', 3, 18000, 'Kentang goreng dengan saus'],
    ['Calamari Ring', 3, 28000, 'Cumi goreng tepung ring'],
    
    // Main Course (ID: 4)
    ['Nasi Goreng Seafood', 4, 35000, 'Nasi goreng dengan seafood segar'],
    ['Mie Goreng Spesial', 4, 30000, 'Mie goreng dengan topping lengkap'],
    ['Nasi Uduk Komplit', 4, 32000, 'Nasi uduk dengan lauk lengkap'],
    
    // Beverages (ID: 5)
    ['Es Teh Manis', 5, 5000, 'Teh manis dingin segar'],
    ['Es Jeruk', 5, 8000, 'Jeruk peras segar'],
    ['Jus Alpukat', 5, 15000, 'Jus alpukat segar'],
    ['Kopi Susu', 5, 12000, 'Kopi susu hangat'],
    
    // Desserts (ID: 6)
    ['Es Krim Vanilla', 6, 12000, 'Es krim vanilla premium'],
    ['Puding Coklat', 6, 10000, 'Puding coklat lembut'],
    ['Pisang Goreng', 6, 10000, 'Pisang goreng crispy']
];

// Find category IDs by name
$catMap = [];
foreach ($categories as $cat) {
    $catMap[strtolower($cat['name'])] = $cat['id'];
}

echo "Inserting menu items...\n\n";

$stmt = $db->prepare("
    INSERT INTO menu_items (name, category_id, price, description, is_available)
    VALUES (?, ?, ?, ?, 1)
");

$success = 0;
$failed = 0;

foreach ($menuItems as $item) {
    list($catId, $name, $desc, $imageUrl, $price) = $item;
    
    try {
        $stmt->execute([$catId, $name, $desc, $imageUrl, $price]);
        echo "✓ Added: $name (Rp " . number_format($price, 0, ',', '.') . ")\n";
        $success++;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            echo "⊗ Skipped (exists): $name\n";
        } else {
            echo "✗ Failed: $name - " . $e->getMessage() . "\n";
        }
        $failed++;
    }
}

echo "\n";
echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "✓ Successfully added: $success items\n";
echo "✗ Failed/Skipped: $failed items\n";
echo "========================================\n\n";

// Show current menu count
$stmt = $db->query("
    SELECT c.name as category, COUNT(m.id) as count
    FROM categories c
    LEFT JOIN menu_items m ON c.id = m.category_id
    GROUP BY c.id
    ORDER BY c.name
");

echo "Current Menu Items by Category:\n";
while ($row = $stmt->fetch()) {
    echo "  - {$row['category']}: {$row['count']} items\n";
}

$total = $db->query("SELECT COUNT(*) FROM menu_items")->fetchColumn();
echo "\nTotal Menu Items: $total\n";

echo "</pre>";
echo "<br><br>";
echo "<a href='menu-management.html' style='padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px;'>Go to Menu Management</a>";
echo " ";
echo "<a href='api/menu.php' style='padding: 10px 20px; background: #27ae60; color: white; text-decoration: none; border-radius: 5px;'>Test API Menu</a>";
?>
