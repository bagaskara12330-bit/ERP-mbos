<?php
session_start();
require 'koneksi.php';

// Cek hak akses ketat (Hanya Admin)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    die("<h2>🛑 AKSES DITOLAK!</h2><p>Hanya role <strong>ADMIN</strong> yang memiliki wewenang untuk mencetak brankas database pabrik.</p><a href='index.php'>Kembali ke Dashboard</a>");
}

$dbname = 'h2_base';
$filename = "Backup_H2BASE_Sistem_" . date("Y-m-d_H-i-s") . ".sql";

// Set header agar browser mendownload file
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo "-- ===================================================\n";
echo "-- BACKUP BRANKAS SERVER H2 BASE\n";
echo "-- TANGGAL CETAK: " . date("Y-m-d H:i:s") . "\n";
echo "-- ===================================================\n\n";

try {
    // 1. Ambil semua tabel di database
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    // 2. Loop setiap tabel untuk diekstrak strukturnya dan isinya
    foreach ($tables as $table) {
        echo "\n-- --------------------------------------------------------\n";
        echo "-- Struktur dari tabel `$table`\n";
        echo "-- --------------------------------------------------------\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        
        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $createRow = $createStmt->fetch(PDO::FETCH_NUM);
        echo $createRow[1] . ";\n\n";

        // 3. Ambil baris data
        $dataStmt = $pdo->query("SELECT * FROM `$table`");
        $rowCount = $dataStmt->rowCount();
        
        if ($rowCount > 0) {
            echo "-- Dumping data untuk tabel `$table`\n";
            $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($data as $row) {
                $fields = [];
                foreach ($row as $val) {
                    if (is_null($val)) {
                        $fields[] = "NULL";
                    } else {
                        // Bersihkan string agar aman di SQL
                        $val = str_replace(["\n", "\r", "'"], ["\\n", "\\r", "''"], $val);
                        $fields[] = "'$val'";
                    }
                }
                echo "INSERT INTO `$table` VALUES (" . implode(", ", $fields) . ");\n";
            }
        }
        echo "\n";
    }

    // Catat ke log radar notifikasi bahwa Admin baru saja backup!
    $user_id = $_SESSION['user_id'];
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';
    $log_stmt = $pdo->prepare("INSERT INTO db_aktivitas_log (user_id, username, ikon, aksi) VALUES (?, ?, '🛡️', 'Melakukan Backup Database (Brankas Anti-Kiamat)')");
    $log_stmt->execute([$user_id, $username]);

} catch (PDOException $e) {
    echo "\n-- ERROR KETIKA BACKUP: " . $e->getMessage() . "\n";
}
exit();
?>
