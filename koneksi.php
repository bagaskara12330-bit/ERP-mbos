<?php
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Jakarta');
$host     = 'localhost';
$dbname   = 'h2_base'; 
$username = 'root';        
$password = '';            

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // AUTO-CREATE TABEL PUSAT NOTIFIKASI
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_aktivitas_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50),
        aksi TEXT,
        ikon VARCHAR(10) DEFAULT '📝',
        waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// FUNGSI SAKTI UNTUK MENEMBAKKAN NOTIFIKASI DARI HALAMAN MANAPUN
function catatLog($pdo, $user, $aksi, $ikon = '📝') {
    try {
        $stmt = $pdo->prepare("INSERT INTO db_aktivitas_log (username, aksi, ikon) VALUES (?, ?, ?)");
        $stmt->execute([$user, $aksi, $ikon]);
    } catch (PDOException $e) {}
}
?>