<?php
/**
 * Authentication API
 * Handles login, logout, and session management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    $pdo = getDbConnection();
    
    switch ($method) {
        case 'POST':
            if ($action === 'login') {
                handleLogin($pdo);
            } elseif ($action === 'logout') {
                handleLogout();
            } elseif ($action === 'register') {
                handleRegister($pdo);
            } else {
                sendResponse(400, false, 'Invalid action');
            }
            break;
            
        case 'GET':
            if ($action === 'check') {
                checkSession();
            } elseif ($action === 'user') {
                getUserInfo($pdo);
            } else {
                sendResponse(400, false, 'Invalid action');
            }
            break;
            
        default:
            sendResponse(405, false, 'Method not allowed');
    }
    
} catch (Exception $e) {
    sendResponse(500, false, 'Server error: ' . $e->getMessage());
}

function handleLogin($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        sendResponse(400, false, 'Username dan password harus diisi');
        return;
    }
    
    $username = $data['username'];
    $password = $data['password'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendResponse(401, false, 'Username tidak ditemukan');
            return;
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Start session
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            sendResponse(200, true, 'Login berhasil', [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'avatar' => $user['avatar']
                ],
                'session_token' => session_id()
            ]);
        } else {
            sendResponse(401, false, 'Password salah');
        }
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function handleLogout() {
    session_start();
    session_destroy();
    sendResponse(200, true, 'Logout berhasil');
}

function handleRegister($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['username', 'email', 'password', 'full_name', 'role'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(400, false, "Field {$field} harus diisi");
            return;
        }
    }
    
    try {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        if ($stmt->fetch()) {
            sendResponse(400, false, 'Username sudah digunakan');
            return;
        }
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            sendResponse(400, false, 'Email sudah digunakan');
            return;
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, role, phone) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['username'],
            $data['email'],
            $hashedPassword,
            $data['full_name'],
            $data['role'],
            $data['phone'] ?? null
        ]);
        
        sendResponse(201, true, 'User berhasil dibuat', [
            'user_id' => $pdo->lastInsertId()
        ]);
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function checkSession() {
    session_start();
    if (isset($_SESSION['user_id'])) {
        sendResponse(200, true, 'Session active', [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role']
        ]);
    } else {
        sendResponse(401, false, 'Session expired');
    }
}

function getUserInfo($pdo) {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        sendResponse(401, false, 'Unauthorized');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, phone, avatar, last_login FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            sendResponse(200, true, 'User info retrieved', ['user' => $user]);
        } else {
            sendResponse(404, false, 'User not found');
        }
        
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
