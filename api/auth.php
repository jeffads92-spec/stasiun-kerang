<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? 'login';

// Response helper
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

switch ($action) {
    case 'login':
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            sendResponse(false, 'Username dan password harus diisi');
        }
        
        try {
            // Check if users table exists, if not create it
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt->rowCount() === 0) {
                // Create users table
                $pdo->exec("
                    CREATE TABLE users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) UNIQUE NOT NULL,
                        password VARCHAR(255) NOT NULL,
                        full_name VARCHAR(100),
                        email VARCHAR(100),
                        role VARCHAR(20) DEFAULT 'cashier',
                        avatar VARCHAR(255),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                
                // Insert default users
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
            }
            
            // Try to authenticate
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                sendResponse(false, 'Username tidak ditemukan');
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                sendResponse(false, 'Password salah');
            }
            
            // Generate session token
            $sessionToken = bin2hex(random_bytes(32));
            
            // Update last login (optional)
            $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?")->execute([$user['id']]);
            
            sendResponse(true, 'Login berhasil', [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'avatar' => $user['avatar']
                ],
                'session_token' => $sessionToken
            ]);
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            sendResponse(false, 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
        break;
        
    case 'logout':
        sendResponse(true, 'Logout berhasil');
        break;
        
    case 'check':
        // Simple session check - in production use proper session management
        sendResponse(true, 'Session active', [
            'user_id' => 1,
            'username' => 'admin',
            'role' => 'admin'
        ]);
        break;
        
    case 'register':
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        $fullName = $input['full_name'] ?? '';
        $email = $input['email'] ?? '';
        $role = $input['role'] ?? 'cashier';
        
        if (empty($username) || empty($password)) {
            sendResponse(false, 'Username dan password harus diisi');
        }
        
        try {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                sendResponse(false, 'Username sudah digunakan');
            }
            
            // Insert new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashedPassword, $fullName, $email, $role]);
            
            sendResponse(true, 'User berhasil didaftarkan', [
                'user_id' => $pdo->lastInsertId()
            ]);
            
        } catch (PDOException $e) {
            error_log("Register error: " . $e->getMessage());
            sendResponse(false, 'Gagal mendaftarkan user');
        }
        break;
        
    default:
        sendResponse(false, 'Action tidak valid');
}
?>
