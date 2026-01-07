<?php
/**
 * Helper Functions
 * Common utility functions used across the application
 */

/**
 * Format currency to Indonesian Rupiah
 */
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

/**
 * Format date to Indonesian format
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

/**
 * Format datetime to Indonesian format
 */
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (empty($datetime)) return '-';
    return date($format, strtotime($datetime));
}

/**
 * Calculate percentage
 */
function calculatePercentage($part, $total) {
    if ($total == 0) return 0;
    return round(($part / $total) * 100, 2);
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Sanitize input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number (Indonesian format)
 */
function isValidPhone($phone) {
    // Remove spaces and dashes
    $phone = preg_replace('/[\s\-]/', '', $phone);
    // Check if it's a valid Indonesian phone number
    return preg_match('/^(\+62|62|0)[0-9]{9,12}$/', $phone);
}

/**
 * Upload image file
 */
function uploadImage($file, $targetDir = '../uploads/', $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
    // Create upload directory if not exists
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $fileName = $file['name'];
    $fileTmpName = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    // Check for upload errors
    if ($fileError !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error: ' . $fileError];
    }
    
    // Get file extension
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Validate file type
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
    }
    
    // Validate file size (max 5MB)
    if ($fileSize > 5242880) {
        return ['success' => false, 'message' => 'File too large. Maximum size: 5MB'];
    }
    
    // Generate unique filename
    $newFileName = uniqid('img_', true) . '.' . $fileExt;
    $targetFile = $targetDir . $newFileName;
    
    // Move uploaded file
    if (move_uploaded_file($fileTmpName, $targetFile)) {
        return ['success' => true, 'filename' => $newFileName, 'path' => $targetFile];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Delete file
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

/**
 * Get file size in human readable format
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Send JSON response
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Log error to file
 */
function logError($message, $file = 'error.log') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    error_log($logMessage, 3, $file);
}

/**
 * Generate order number
 */
function generateOrderNumber($pdo) {
    $date = date('Ymd');
    $prefix = 'ORD-' . $date . '-';
    
    $stmt = $pdo->prepare("SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastOrder = $stmt->fetch();
    
    if ($lastOrder) {
        $lastNumber = intval(substr($lastOrder['order_number'], -4));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate payment number
 */
function generatePaymentNumber($pdo) {
    $date = date('Ymd');
    $prefix = 'PAY-' . $date . '-';
    
    $stmt = $pdo->prepare("SELECT payment_number FROM payments WHERE payment_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastPayment = $stmt->fetch();
    
    if ($lastPayment) {
        $lastNumber = intval(substr($lastPayment['payment_number'], -4));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * Calculate tax
 */
function calculateTax($amount, $rate = 0.10) {
    return round($amount * $rate, 2);
}

/**
 * Validate date format
 */
function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Get time ago string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return $diff . ' detik yang lalu';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' menit yang lalu';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' jam yang lalu';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' hari yang lalu';
    } else {
        return date('d/m/Y H:i', $time);
    }
}
?>
