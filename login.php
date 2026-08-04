<?php
session_start();
require 'koneksi.php';

// AUTO-CREATE TABEL AKUN (Termasuk kolom akses_menu untuk fitur Checklist Granular)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS master_akun (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('Admin', 'Editor', 'Viewer', 'Operator') DEFAULT 'Operator',
        akses_menu TEXT,
        status ENUM('Aktif', 'Nonaktif') DEFAULT 'Aktif'
    )");

    // Pengecekan kolom akses_menu jika sebelumnya belum ada
    try {
        $pdo->exec("ALTER TABLE master_akun ADD COLUMN akses_menu TEXT AFTER role");
        $pdo->exec("UPDATE master_akun SET akses_menu='inv_stok,prod_lap,prod_rek,prod_kpi,prod_flexo,dash_prod,dash_eff,dash_hrd,hrd_data,hrd_lemb,set_master' WHERE akses_menu IS NULL");
    } catch (PDOException $e) { /* Abaikan jika kolom sudah ada */ }
    
    // Bikin akun default "Admin" jika tabel kosong (password: admin123)
    $cek = $pdo->query("SELECT COUNT(*) FROM master_akun")->fetchColumn();
    if ($cek == 0) {
        $pass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO master_akun (username, password, role, akses_menu) VALUES ('admin', '$pass', 'Admin', 'inv_stok,prod_lap,prod_rek,prod_kpi,prod_flexo,dash_prod,dash_eff,dash_hrd,hrd_data,hrd_lemb,set_master')");
    }
} catch (PDOException $e) { die("Database Error: " . $e->getMessage()); }

// 🚀 JIKA SUDAH LOGIN, LEMPAR KE HALAMAN YANG SESUAI TIKET AKSESNYA
if (isset($_SESSION['user_id'])) {
    // 🚀 SMART REDIRECT (Lempar user sesuai centangannya!)
    $akses_login = explode(',', $_SESSION['akses_menu'] ?? '');

    if ($_SESSION['role'] == 'Admin' || in_array('dash_prod', $akses_login)) { header("Location: dashboard.php"); exit(); }
    if (in_array('dash_eff', $akses_login)) { header("Location: dashboard2.php"); exit(); }
    if (in_array('dash_hrd', $akses_login)) { header("Location: karyawan_dashboard.php"); exit(); }
    if (in_array('inv_stok', $akses_login)) { header("Location: stok_opname.php"); exit(); }
    if (in_array('prod_lap', $akses_login)) { header("Location: laporan.php"); exit(); }

    // 👇 Tambahan supaya user yang aksesnya spesifik nggak nyasar ke halaman kosong
    if (in_array('prod_flexo', $akses_login)) { header("Location: produktifitas_flexo.php"); exit(); }
    if (in_array('prod_rek', $akses_login)) { header("Location: rekap_bulanan.php"); exit(); }
    if (in_array('prod_kpi', $akses_login)) { header("Location: kpi.php"); exit(); }
    if (in_array('hrd_data', $akses_login)) { header("Location: karyawan_data.php"); exit(); }
    if (in_array('hrd_lemb', $akses_login)) { header("Location: karyawan_lembur.php"); exit(); }

    header("Location: index.php"); exit();
}

$error = "";

// 🚀 PROSES PENGECEKAN PASSWORD SAAT TOMBOL LOGIN DIKLIK
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = strtolower(trim($_POST['username']));
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM master_akun WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] == 'Nonaktif') {
            $error = "AKSES DIBLOKIR: Hubungi Administrator Utama.";
        } else {
            // Pasang tiket ke dalam Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['akses_menu'] = $user['akses_menu'];

            // 🚀 SMART REDIRECT (Lempar user sesuai centangannya!)
            $akses_login = explode(',', $user['akses_menu'] ?? '');
            
            if ($user['role'] == 'Admin' || in_array('dash_prod', $akses_login)) { header("Location: dashboard.php"); exit(); }
            if (in_array('dash_eff', $akses_login)) { header("Location: dashboard2.php"); exit(); }
            if (in_array('dash_hrd', $akses_login)) { header("Location: karyawan_dashboard.php"); exit(); }
            if (in_array('inv_stok', $akses_login)) { header("Location: stok_opname.php"); exit(); }
            if (in_array('prod_lap', $akses_login)) { header("Location: laporan.php"); exit(); }
            
            header("Location: index.php"); exit();
        }
    } else {
        $error = "OPERATOR ID atau PASSCODE tidak dikenali!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Otentikasi — H2 BASE</title>
    
    <link rel="icon" type="image/png" href="logo.png?v=1">
    <link rel="shortcut icon" type="image/x-icon" href="logo.png?v=1">
    
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #050a15; margin: 0; display: flex; justify-content: center; align-items: center; height: 100vh; overflow: hidden; color: #fff; }
        :root { --neon-cyan: #0ea5e9; --neon-glow: rgba(14, 165, 233, 0.4); }

        /* ========================================================================================= */
        /* 🚀 BACKGROUND: BREATHING BLUEPRINT GRID & RADAR SCHEMATIC */
        /* ========================================================================================= */
        .blueprint-bg {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-color: #030712;
            background-image: 
                linear-gradient(var(--neon-glow) 1px, transparent 1px),
                linear-gradient(90deg, var(--neon-glow) 1px, transparent 1px),
                linear-gradient(rgba(14, 165, 233, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(14, 165, 233, 0.05) 1px, transparent 1px);
            background-size: 150px 150px, 150px 150px, 30px 30px, 30px 30px;
            background-position: center center;
            z-index: 0;
            opacity: 0.6;
        }

        /* LASER SCANNER SWEEP */
        .scanner-laser {
            position: absolute;
            top: -10%; left: 0; width: 100%; height: 2px;
            background: #0ea5e9;
            box-shadow: 0 0 20px 5px rgba(14, 165, 233, 0.6);
            z-index: 1;
            animation: laserSweep 5s infinite linear;
            pointer-events: none;
        }
        @keyframes laserSweep {
            0% { top: -10%; opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { top: 110%; opacity: 0; }
        }

        /* ROTATING RADAR / MACHINE SCHEMATICS */
        .schematic-circle {
            position: absolute;
            border-radius: 50%;
            border: 1px dashed rgba(14, 165, 233, 0.3);
            border-top: 2px solid rgba(14, 165, 233, 0.6);
            border-bottom: 2px solid rgba(14, 165, 233, 0.1);
            animation: spin 25s linear infinite;
            z-index: 0;
            pointer-events: none;
        }
        .schematic-inner {
            position: absolute;
            top: 15%; left: 15%; right: 15%; bottom: 15%;
            border-radius: 50%;
            border: 1px dotted rgba(14, 165, 233, 0.4);
            animation: spinReverse 15s linear infinite;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        @keyframes spinReverse { 100% { transform: rotate(-360deg); } }

        /* BLINKING CROSSHAIRS (Garis Bidik) */
        .crosshair {
            position: absolute; width: 30px; height: 30px; z-index: 1;
            animation: blink 4s infinite;
        }
        .crosshair::before { content: ''; position: absolute; top: 50%; left: 0; width: 100%; height: 1px; background: rgba(14, 165, 233, 0.8); }
        .crosshair::after { content: ''; position: absolute; top: 0; left: 50%; width: 1px; height: 100%; background: rgba(14, 165, 233, 0.8); }
        @keyframes blink { 0%, 96%, 98% { opacity: 1; } 97%, 99% { opacity: 0; } }

        /* BLUEPRINT DATA TEXT (Dekorasi) */
        .bp-text {
            position: absolute;
            font-family: monospace;
            font-size: 10px;
            color: rgba(14, 165, 233, 0.7);
            letter-spacing: 2px;
            z-index: 1;
        }

        /* ========================================================================================= */

        .ambient-glow { position: absolute; width: 500px; height: 500px; background: radial-gradient(circle, rgba(14,165,233,0.1) 0%, rgba(15,23,42,0) 70%); top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none; z-index: 1; }
        
        .auth-panel { background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(14, 165, 233, 0.4); border-radius: 12px; padding: 40px; width: 100%; max-width: 320px; box-shadow: 0 0 50px rgba(0,0,0,0.8), inset 0 0 20px rgba(14,165,233,0.05); position: relative; overflow: hidden; z-index: 10; transition: border-color 0.3s, box-shadow 0.3s; }
        .auth-panel:hover { border-color: var(--neon-cyan); box-shadow: 0 0 50px rgba(0,0,0,0.8), inset 0 0 30px rgba(14,165,233,0.1); }
        
        .text-center { text-align: center; }
        .logo-box { font-size: 40px; margin-bottom: 10px; position: relative; display: inline-block;}
        .logo-box img { filter: drop-shadow(0 0 8px rgba(14, 165, 233, 0.8)); transition: 0.3s; }
        .logo-box:hover img { filter: drop-shadow(0 0 15px rgba(14, 165, 233, 1)); transform: scale(1.05); }

        h1 { margin: 0 0 5px 0; font-size: 24px; letter-spacing: 2px; color: #fff; font-weight: 800; text-shadow: 0 0 10px rgba(14,165,233,0.5); }
        .subtitle { margin: 0 0 25px 0; font-size: 11px; color: #94a3b8; font-family: monospace; letter-spacing: 1px; }
        .alert { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; padding: 10px; border-radius: 6px; font-size: 12px; margin-bottom: 20px; font-weight: 600; }
        
        .form-group { margin-bottom: 15px; text-align: left; }
        .form-group label { display: block; font-size: 10px; color: #94a3b8; margin-bottom: 5px; font-family: monospace; letter-spacing: 1px; }
        .form-group input { width: 100%; padding: 12px 15px; background: rgba(2, 6, 23, 0.6); border: 1px solid #334155; border-radius: 6px; color: #fff; font-size: 14px; box-sizing: border-box; outline: none; transition: 0.3s; font-family: monospace;}
        .form-group input:focus { border-color: var(--neon-cyan); box-shadow: 0 0 15px rgba(14,165,233,0.25); background: rgba(2, 6, 23, 0.9);}
        
        .btn-login { width: 100%; padding: 14px; background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%); color: #fff; border: 1px solid var(--neon-cyan); border-radius: 6px; font-size: 14px; font-weight: 800; letter-spacing: 2px; cursor: pointer; transition: 0.3s; margin-top: 10px; text-transform: uppercase; box-shadow: 0 0 15px rgba(14,165,233,0.2); }
        .btn-login:hover { background: #0284c7; box-shadow: 0 0 25px rgba(14,165,233,0.6); transform: translateY(-2px); }
    </style>
</head>
<body>

    <!-- 🚀 BACKGROUND ELEMENTS -->
    <div class="blueprint-bg"></div>
    <div class="scanner-laser"></div>

    <!-- Mesin Schematic Kiri Atas -->
    <div class="schematic-circle" style="width: 60vh; height: 60vh; top: -15vh; left: -10vh;">
        <div class="schematic-inner"></div>
    </div>
    
    <!-- Mesin Schematic Kanan Bawah -->
    <div class="schematic-circle" style="width: 80vh; height: 80vh; bottom: -20vh; right: -15vh; animation-direction: reverse; animation-duration: 40s; opacity: 0.5;">
        <div class="schematic-inner"></div>
    </div>

    <!-- Titik Crosshair Presisi -->
    <div class="crosshair" style="top: 25%; left: 20%; animation-delay: 0s;"></div>
    <div class="crosshair" style="top: 75%; left: 30%; animation-delay: 1.5s;"></div>
    <div class="crosshair" style="top: 20%; right: 25%; animation-delay: 0.7s;"></div>
    <div class="crosshair" style="top: 65%; right: 15%; animation-delay: 2s;"></div>

    <!-- Teks Koordinat Digital -->
    <div class="bp-text" style="top: 27%; left: 20%;">SEC-01 // ALIGN OK</div>
    <div class="bp-text" style="top: 67%; right: 15%;">SYS.NAV // CALIBRATING</div>
    <div class="bp-text" style="bottom: 5%; left: 5%;">H2_BASE_CORE_v1.0</div>

    <div class="ambient-glow"></div>

    <!-- 🚀 PANEL LOGIN UTAMA -->
    <div class="auth-panel text-center">
        <div class="logo-box">
            <img src="logo.png?v=1" alt="Logo" style="width: 65px; height: auto;" onerror="this.outerHTML='🔐'">
        </div>
        <h1>H2 BASE</h1>
        <p class="subtitle">> SECURE ACCESS TERMINAL_</p>

        <!-- Live Clock Container -->
        <div id="live-clock" style="font-family: monospace; color: var(--neon-cyan); font-size: 11px; margin-bottom: 25px; font-weight: 700; letter-spacing: 1px; text-shadow: 0 0 5px rgba(14,165,233,0.5); border-top: 1px dashed rgba(14,165,233,0.3); border-bottom: 1px dashed rgba(14,165,233,0.3); padding: 8px 0;"></div>

        <?php if($error): ?>
            <div class="alert">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>OPERATOR ID</label>
                <input type="text" name="username" placeholder="Enter ID..." required autocomplete="off">
            </div>
            <div class="form-group">
                <label>PASSCODE</label>
                <input type="password" name="password" placeholder="Enter PIN..." required>
            </div>
            <button type="submit" class="btn-login">INITIATE LOGIN</button>
        </form>
    </div>

    <!-- Live Clock Script -->
    <script>
        function updateClock() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric' };
            const dateStr = now.toLocaleDateString('id-ID', options).toUpperCase();
            const timeStr = now.toLocaleTimeString('id-ID', { hour12: false });
            document.getElementById('live-clock').innerText = dateStr + ' | ' + timeStr;
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>