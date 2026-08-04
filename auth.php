<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once 'koneksi.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_role = $_SESSION['role'] ?? 'Viewer';
$user_akses = explode(',', $_SESSION['akses_menu'] ?? '');

function require_akses($kode_akses, $pesan = 'Anda tidak memiliki izin ke menu ini.') {
    global $user_role, $user_akses;
    if ($user_role != 'Admin' && !in_array($kode_akses, $user_akses)) {
        echo "<script>alert('🛑 AKSES DITOLAK! ' . $pesan); window.history.back();</script>";
        exit();
    }
}
?>