<?php
/**
 * Authentication API
 * Handles login, logout, and session management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

try {
    $pdo = getDbConnection();
    
    switch ($method) {
        case 'POST':
            if ($action === 'login') {
                handleLogin($pdo);
            } elseif ($action === 'logout') {
                handleLogout();
            } elseif ($action === 'register') {
                // Only admin can register new users, check session first
                session_start();
                if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
                    sendResponse(403, false, 'Forbidden: Only admin can register new users');
                    return;
                }
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
    
    $username = trim($data['username']);
    $password = $data['password'];
    
    // Rate limiting: check failed attempts (you might want to implement this in database)
    session_start();
    if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] > 5) {
        $lastAttempt = $_SESSION['last_attempt_time'] ?? 0;
        if (time() - $lastAttempt < 300) { // 5 minutes lock
            sendResponse(429, false, 'Too many failed attempts. Please try again later.');
            return;
        } else {
            // Reset attempts after lockout period
            unset($_SESSION['login_attempts']);
        }
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            incrementLoginAttempts();
            sendResponse(401, false, 'Username atau password salah');
            return;
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Reset login attempts on success
            if (isset($_SESSION['login_attempts'])) {
                unset($_SESSION['login_attempts']);
            }
            
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['login_time'] = time(); // Set login time for timeout
            
            sendResponse(200, true, 'Login berhasil', [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'avatar' => $user['avatar'],
                    'last_login' => $user['last_login']
                ],
                'session_token' => session_id()
            ]);
        } else {
            incrementLoginAttempts();
            sendResponse(401, false, 'Username atau password salah');
        }
        
    } catch (PDOException $e) {
        sendResponse(500, false, 'Database error: ' . $e->getMessage());
    }
}

function incrementLoginAttempts() {
    session_start();
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 1;
    } else {
        $_SESSION['login_attempts']++;
    }
    $_SESSION['last_attempt_time'] = time();
}

function handleLogout() {
    session_start();
    // Destroy session completely
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    sendResponse(200, true, 'Logout berhasil');
}

function handleRegister($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['username', 'email', 'password', 'full_name', 'role'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            sendResponse(400, false, "Field {$field} harus diisi");
            return;
        }
    }
    
    // Validate role
    $allowedRoles = ['admin', 'cashier', 'kitchen', 'waiter'];
    if (!in_array($data['role'], $allowedRoles)) {
        sendResponse(400, false, 'Role tidak valid. Pilih: ' . implode(', ', $allowedRoles));
        return;
    }
    
    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        sendResponse(400, false, 'Email tidak valid');
        return;
    }
    
    // Validate password strength
    if (strlen($data['password']) < 6) {
        sendResponse(400, false, 'Password minimal 6 karakter');
        return;
    }
    
    try {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([trim($data['username'])]);
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
            trim($data['username']),
            $data['email'],
            $hashedPassword,
            trim($data['full_name']),
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
    // Check if session exists and not expired
    if (isset($_SESSION['user_id']) && isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            // Session expired
            session_destroy();
            sendResponse(401, false, 'Session expired');
        } else {
            // Update login time to extend session
            $_SESSION['login_time'] = time();
            sendResponse(200, true, 'Session active', [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'full_name' => $_SESSION['full_name']
            ]);
        }
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
