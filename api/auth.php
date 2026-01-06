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
    session_start();
    
    // Rate limiting: max 5 attempts in 15 minutes
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['first_failed_attempt'] = time();
    }
    
    $timeWindow = 900; // 15 minutes in seconds
    if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['first_failed_attempt']) < $timeWindow) {
        sendResponse(429, false, 'Too many login attempts. Please try again later.');
        return;
    }
    
    // Reset after 15 minutes
    if ((time() - $_SESSION['first_failed_attempt']) >= $timeWindow) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['first_failed_attempt'] = time();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        sendResponse(400, false, 'Username and password are required');
        return;
    }
    
    $username = trim($data['username']);
    $password = $data['password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        sendResponse(400, false, 'Username and password cannot be empty');
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $_SESSION['login_attempts']++;
            sendResponse(401, false, 'Invalid username or password');
            return;
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Reset login attempts
            $_SESSION['login_attempts'] = 0;
            
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Regenerate session ID
            session_regenerate_id(true);
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['last_login'] = $user['last_login'];
            
            sendResponse(200, true, 'Login successful', [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'avatar' => $user['avatar']
                ]
            ]);
        } else {
            $_SESSION['login_attempts']++;
            sendResponse(401, false, 'Invalid username or password');
        }
        
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function handleLogout() {
    session_start();
    
    // Clear all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    sendResponse(200, true, 'Logout successful');
}

function handleRegister($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['username', 'email', 'password', 'full_name', 'role'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            sendResponse(400, false, "Field {$field} is required");
            return;
        }
    }
    
    // Validate email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        sendResponse(400, false, 'Invalid email format');
        return;
    }
    
    // Validate role
    $allowedRoles = ['admin', 'cashier', 'kitchen', 'waiter'];
    if (!in_array($data['role'], $allowedRoles)) {
        sendResponse(400, false, 'Invalid role');
        return;
    }
    
    // Validate password strength
    if (strlen($data['password']) < 6) {
        sendResponse(400, false, 'Password must be at least 6 characters long');
        return;
    }
    
    try {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([trim($data['username'])]);
        if ($stmt->fetch()) {
            sendResponse(400, false, 'Username already exists');
            return;
        }
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([trim($data['email'])]);
        if ($stmt->fetch()) {
            sendResponse(400, false, 'Email already exists');
            return;
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, full_name, role, phone, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            trim($data['username']),
            trim($data['email']),
            $hashedPassword,
            trim($data['full_name']),
            $data['role'],
            isset($data['phone']) ? trim($data['phone']) : null
        ]);
        
        sendResponse(201, true, 'User created successfully', [
            'user_id' => $pdo->lastInsertId()
        ]);
        
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
    }
}

function checkSession() {
    session_start();
    if (isset($_SESSION['user_id'])) {
        sendResponse(200, true, 'Session active', [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'full_name' => $_SESSION['full_name']
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
        $stmt = $pdo->prepare("
            SELECT id, username, email, full_name, role, phone, avatar, last_login 
            FROM users WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            sendResponse(200, true, 'User info retrieved', ['user' => $user]);
        } else {
            sendResponse(404, false, 'User not found');
        }
        
    } catch (PDOException $e) {
        error_log("Get user info error: " . $e->getMessage());
        sendResponse(500, false, 'Database error');
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
