<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require 'koneksi.php';

$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'USER';

// 🔔 TEMBAK NOTIFIKASI LOGOUT
catatLog($pdo, $user_aktif, "Keluar dari sistem H2 BASE (Logout).", "🚪");

session_unset();
session_destroy();
header("Location: login.php");
exit();
?>