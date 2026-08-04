<?php
// Wajib ada session untuk tahu siapa yang menghapus
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require 'koneksi.php';

// Tangkap nama user
$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';

// 🚀 UBAH PENGECEKAN DARI $_GET MENJADI $_POST
if (isset($_POST['aksi']) && $_POST['aksi'] == 'hapus' && isset($_POST['id'])) {
    try {
        $id = intval($_POST['id']);
        
        // Tangkap nama regu dari POST untuk dicatat di notifikasi
        $rg = isset($_POST['rg']) ? $_POST['rg'] : 'Tidak Diketahui';
        
        $sql = "DELETE FROM mbos_regu WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        // 🔔 TEMBAK NOTIFIKASI HAPUS KE LONCENG
        catatLog($pdo, $user_aktif, "Menghapus Laporan Produksi milik Mesin/Regu $rg secara permanen.", "🗑️");
        
        header("Location: laporan.php?pesan=hapus_sukses");
        exit();
    } catch (PDOException $e) {
        die("Gagal menghapus data: " . $e->getMessage());
    }
} else {
    header("Location: laporan.php");
    exit();
}
?>