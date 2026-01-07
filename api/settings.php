<?php
// Prevent any output before headers
ob_start();

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

// Try multiple path options
$configPath = __DIR__ . '/../config/database.php';
if (!file_exists($configPath)) {
    $configPath = $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
}
if (!file_exists($configPath)) {
    $configPath = dirname(__DIR__) . '/config/database.php';
}

if (!file_exists($configPath)) {
    ob_end_clean();
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database configuration file not found'
    ]));
}

require_once $configPath;

// Helper function to send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    ob_end_clean();
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

try {
    // Get database connection
    if (class_exists('Database')) {
        $db = Database::getInstance()->getConnection();
    } elseif (function_exists('getDbConnection')) {
        $db = getDbConnection();
    } else {
        throw new Exception('Database connection method not found');
    }
    
    // Ensure settings table exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            setting_type VARCHAR(50) DEFAULT 'text',
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Initialize default settings if not exist
    $defaultSettings = [
        ['tax_rate', '10', 'number', 'Pajak (%)'],
        ['service_charge_rate', '5', 'number', 'Biaya Layanan (%)'],
        ['currency', 'IDR', 'text', 'Mata Uang'],
        ['restaurant_name', 'Stasiun Kerang', 'text', 'Nama Restoran'],
        ['restaurant_address', '', 'text', 'Alamat Restoran'],
        ['restaurant_phone', '', 'text', 'Nomor Telepon'],
        ['restaurant_email', '', 'email', 'Email'],
        
        // Bank Accounts
        ['bank_bca_name', '', 'text', 'Nama Pemilik Rekening BCA'],
        ['bank_bca_number', '', 'text', 'Nomor Rekening BCA'],
        ['bank_mandiri_name', '', 'text', 'Nama Pemilik Rekening Mandiri'],
        ['bank_mandiri_number', '', 'text', 'Nomor Rekening Mandiri'],
        ['bank_bri_name', '', 'text', 'Nama Pemilik Rekening BRI'],
        ['bank_bri_number', '', 'text', 'Nomor Rekening BRI'],
        ['bank_bni_name', '', 'text', 'Nama Pemilik Rekening BNI'],
        ['bank_bni_number', '', 'text', 'Nomor Rekening BNI'],
        
        // E-Wallets
        ['ewallet_gopay_name', '', 'text', 'Nama Pemilik GoPay'],
        ['ewallet_gopay_number', '', 'text', 'Nomor GoPay'],
        ['ewallet_ovo_name', '', 'text', 'Nama Pemilik OVO'],
        ['ewallet_ovo_number', '', 'text', 'Nomor OVO'],
        ['ewallet_dana_name', '', 'text', 'Nama Pemilik DANA'],
        ['ewallet_dana_number', '', 'text', 'Nomor DANA'],
        ['ewallet_shopee_name', '', 'text', 'Nama Pemilik ShopeePay'],
        ['ewallet_shopee_number', '', 'text', 'Nomor ShopeePay'],
        
        // Receipt Settings
        ['receipt_footer', 'Terima kasih atas kunjungan Anda!', 'textarea', 'Footer Struk'],
        ['receipt_show_tax', '1', 'boolean', 'Tampilkan Pajak di Struk'],
        ['receipt_show_service', '1', 'boolean', 'Tampilkan Biaya Layanan di Struk']
    ];
    
    $stmt = $db->prepare("
        INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description)
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            if (isset($_GET['key'])) {
                // Get specific setting
                $stmt = $db->prepare("SELECT * FROM settings WHERE setting_key = ?");
                $stmt->execute([$_GET['key']]);
                $setting = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($setting) {
                    sendJsonResponse([
                        'success' => true,
                        'data' => $setting
                    ]);
                } else {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Setting not found'
                    ], 404);
                }
            } else {
                // Get all settings grouped by category
                $stmt = $db->query("SELECT * FROM settings ORDER BY setting_key");
                $allSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Group settings by category
                $grouped = [
                    'general' => [],
                    'financial' => [],
                    'bank_accounts' => [],
                    'e_wallets' => [],
                    'receipt' => []
                ];
                
                foreach ($allSettings as $setting) {
                    $key = $setting['setting_key'];
                    
                    if (strpos($key, 'bank_') === 0) {
                        $grouped['bank_accounts'][] = $setting;
                    } elseif (strpos($key, 'ewallet_') === 0) {
                        $grouped['e_wallets'][] = $setting;
                    } elseif (strpos($key, 'receipt_') === 0) {
                        $grouped['receipt'][] = $setting;
                    } elseif (in_array($key, ['tax_rate', 'service_charge_rate', 'currency'])) {
                        $grouped['financial'][] = $setting;
                    } else {
                        $grouped['general'][] = $setting;
                    }
                }
                
                sendJsonResponse([
                    'success' => true,
                    'data' => $grouped,
                    'all_settings' => $allSettings
                ]);
            }
            break;
            
        case 'POST':
        case 'PUT':
            // Update settings
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Invalid JSON data'
                ], 400);
            }
            
            // Update multiple settings at once
            $stmt = $db->prepare("
                UPDATE settings 
                SET setting_value = ? 
                WHERE setting_key = ?
            ");
            
            $updated = 0;
            foreach ($data as $key => $value) {
                // Convert boolean to string
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }
                
                $stmt->execute([$value, $key]);
                if ($stmt->rowCount() > 0) {
                    $updated++;
                }
            }
            
            sendJsonResponse([
                'success' => true,
                'message' => "Updated {$updated} settings successfully"
            ]);
            break;
            
        default:
            sendJsonResponse([
                'success' => false,
                'message' => 'Method not allowed'
            ], 405);
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Database error occurred'
    ], 500);
} catch (Exception $e) {
    error_log("Server error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
}
?>
