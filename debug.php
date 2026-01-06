<?php
// debug.php - File sederhana untuk debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "=== Debug System ===<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br><br>";

// Cek apakah file config ada
echo "Checking config/database.php... ";
if (file_exists('config/database.php')) {
    echo "✅ File exists<br>";
    
    // Cek isi file (pertama 10 baris)
    $lines = file('config/database.php');
    echo "First 10 lines:<pre>";
    for($i=0; $i<min(10, count($lines)); $i++) {
        echo htmlspecialchars($lines[$i]);
    }
    echo "</pre>";
} else {
    echo "❌ File NOT found<br>";
}

echo "<br>=== Environment Variables ===<br>";
$envVars = [
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_PORT',
    'DATABASE_URL', 'PGHOST', 'PGDATABASE', 'PGUSER', 'PGPASSWORD', 'PGPORT'
];

foreach ($envVars as $var) {
    $value = getenv($var);
    echo "$var = " . ($value ? "✅ '" . htmlspecialchars(substr($value, 0, 3)) . "***'" : "❌ NOT SET") . "<br>";
}

echo "<br>=== Directory Structure ===<br>";
echo "Root: " . __DIR__ . "<br>";
$files = scandir(__DIR__);
echo "Files in root: " . implode(", ", array_slice($files, 0, 10)) . "...<br>";

if (file_exists('config')) {
    echo "Config folder exists<br>";
    $configFiles = scandir('config');
    echo "Files in config: " . implode(", ", $configFiles) . "<br>";
}
?>
