<?php
/**
 * System Settings API
 * Manage application settings
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$key = isset($_GET['key']) ? $_GET['key'] : '';

try {
    $pdo = getDbConnection();
    
    switch ($method) {
        case 'GET':
            if (!empty($key)) {
                getSetting($pdo, $key);
            } else {
                getAllSettings($pdo);
            }
            break;
            
        case 'POST':
        case 'PUT':
            updateSetting($pdo);
            break;
            
        default:
            sendResponse(405, false, 'Method not allowed');
    }
    
} catch (Exception $e) {
    sendResponse(500, false, 'Server error: ' . $e->getMessage());
}

function getAllSettings($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM settings ORDER BY setting_key");
        $stmt->execute();
        $settings = $stmt->fetchAll();
        
        // Format settings as key-value pairs
        $formatted = [];
        foreach ($settings as $setting) {
            $formatted[$setting['setting_key']] = [
                'value' => $setting['setting_value'],
                'type' => $setting['setting_type'],
                'description' => $setting['description']
            ];
        }
        
        sendResponse(200, true, 'Settings retrieved', ['settings' => $formatted]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function getSetting($pdo, $key) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $setting = $stmt->fetch();
        
        if ($setting) {
            sendResponse(200, true, 'Setting retrieved', ['setting' => $setting]);
        } else {
            sendResponse(404, false, 'Setting not found');
        }
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function updateSetting($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['key']) || !isset($data['value'])) {
        sendResponse(400, false, 'Key and value are required');
        return;
    }
    
    try {
        // Check if setting exists
        $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ?");
        $stmt->execute([$data['key']]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing setting
            $stmt = $pdo->prepare("
                UPDATE settings 
                SET setting_value = ?, updated_at = NOW()
                WHERE setting_key = ?
            ");
            $stmt->execute([$data['value'], $data['key']]);
            $message = 'Setting updated successfully';
        } else {
            // Insert new setting
            $stmt = $pdo->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_type, description)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['key'],
                $data['value'],
                $data['type'] ?? 'string',
                $data['description'] ?? null
            ]);
            $message = 'Setting created successfully';
        }
        
        sendResponse(200, true, $message);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function sendResponse($code, $success, $message, $data = null) {
    http_response_code($code);
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit();
}
?>
