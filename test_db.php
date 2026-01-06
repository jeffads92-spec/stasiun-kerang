<?php
require_once 'config/database.php';
try {
    $pdo = getDbConnection();
    echo "Koneksi database berhasil.";
    // Cek tabel users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "Jumlah user: " . $result['count'];
} catch (Exception $e) {
    echo "Koneksi database gagal: " . $e->getMessage();
}
