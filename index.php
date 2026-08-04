<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require 'koneksi.php';

// 🚀 WAJIB LOGIN! Kalau belum, langsung tendang ke halaman login
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'GUEST';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'UNAUTHORIZED';

// 🚀 SMART LINK (ANTI NYANGKUT): Tentukan tombol "ACCESS SYSTEM" mengarah ke mana sesuai tiket aksesnya
$akses_login = explode(',', $_SESSION['akses_menu'] ?? '');
$link_masuk = "login.php"; // Fallback aman jika terjadi anomali

// 💡 PENGECEKAN RUTE (Di-update dengan seluruh halaman sistem baru)
if ($user_role == 'Admin' || in_array('dash_prod', $akses_login)) { $link_masuk = "dashboard.php"; }
elseif (in_array('dash_eff', $akses_login)) { $link_masuk = "dashboard2.php"; }
elseif (in_array('dash_hrd', $akses_login)) { $link_masuk = "karyawan_dashboard.php"; }
elseif (in_array('dash_flexo', $akses_login)) { $link_masuk = "dashboard_flexo.php"; }
elseif (in_array('dash_corr', $akses_login)) { $link_masuk = "dashboard_corr.php"; } // Rute Baru
elseif (in_array('inv_keluar', $akses_login)) { $link_masuk = "dashboard_proll.php"; }
elseif (in_array('dash_mtc', $akses_login)) { $link_masuk = "mtc_dashboard.php"; }
elseif (in_array('inv_masuk', $akses_login)) { $link_masuk = "stock_ex.php"; }
elseif (in_array('inv_stok', $akses_login)) { $link_masuk = "stok_opname.php"; }
elseif (in_array('prod_lap', $akses_login)) { $link_masuk = "laporan.php"; }
elseif (in_array('prod_rek', $akses_login)) { $link_masuk = "rekap_bulanan.php"; }
elseif (in_array('prod_kpi', $akses_login)) { $link_masuk = "kpi.php"; }
elseif (in_array('prod_flexo', $akses_login)) { $link_masuk = "produktifitas_flexo.php"; }
elseif (in_array('prod_downtime', $akses_login)) { $link_masuk = "downtime_corr.php"; } // Rute Baru
elseif (in_array('mtc_sparepart', $akses_login)) { $link_masuk = "mtc_sparepart.php"; }
elseif (in_array('hrd_data', $akses_login)) { $link_masuk = "karyawan_data.php"; }
elseif (in_array('hrd_lemb', $akses_login)) { $link_masuk = "karyawan_lembur.php"; }
elseif (in_array('set_master', $akses_login)) { $link_masuk = "master_data.php"; }
else {
    // 🛑 Jika sama sekali tidak ada akses yang dicentang admin (Akun kosongan), 
    // arahkan untuk logout saja daripada error nyangkut.
    $link_masuk = "logout.php"; 
}

// Menarik data metrik nyata dari sistem database
try {
    $total_karyawan = $pdo->query("SELECT COUNT(*) FROM db_karyawan_h2 WHERE ket_status='AKTIF'")->fetchColumn() ?: 0;
    $total_aktivitas = $pdo->query("SELECT COUNT(*) FROM db_aktivitas_log")->fetchColumn() ?: 0;
    
    $log_terakhir = $pdo->query("SELECT waktu FROM db_aktivitas_log ORDER BY id DESC LIMIT 1")->fetchColumn();
    $update_terakhir = $log_terakhir ? date('H:i', strtotime($log_terakhir)) : date('H:i');
} catch (Exception $e) {
    $total_karyawan = 0; $total_aktivitas = 0; $update_terakhir = date('H:i');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <!-- 🚀 META VIEWPORT SAKTI UNTUK ANDROID & iPHONE -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>H2 BASE — Mainframe Gate</title>
    
    <link rel="icon" type="image/png" href="logo.png?v=1">
    <link rel="shortcut icon" type="image/x-icon" href="logo.png?v=1">

    <style>
        /* === TEMA CYBERPUNK MINIMALIS (SUPER LIGHTWEIGHT) === */
        :root {
            --neon-cyan: #00f0ff;
            --cyber-blue: #38bdf8;
            --dark-bg: #0b132b;
            --card-bg: rgba(11, 19, 43, 0.75);
        }

        /* 🚀 BOX SIZING RESET AGAR TIDAK BOCOR DI HP */
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 20px 15px; /* Padding aman untuk HP */
            background-color: #020617;
            background-image: radial-gradient(rgba(0, 240, 255, 0.07) 1px, transparent 0);
            background-size: 24px 24px;
            color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            /* 🚀 FIX SAKTI UNTUK iPHONE & ANDROID BROWSER */
            min-height: 100vh;
            min-height: 100dvh; 
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
            overflow-y: auto; /* Memungkinkan scroll jika layar HP terlalu pendek */
            -webkit-text-size-adjust: 100%;
        }

        /* GLOW BACKGROUND EFFECT */
        .ambient-glow {
            position: fixed; /* Fixed agar tidak merusak scroll */
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(0, 240, 255, 0.15) 0%, transparent 70%);
            top: 10%;
            right: 10%;
            z-index: 1;
            pointer-events: none;
        }

        /* PANEL UTAMA CONSOLE CONTROL */
        .mainframe-box {
            position: relative;
            z-index: 10;
            background: var(--card-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 240, 255, 0.25);
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 720px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6), inset 0 0 15px rgba(0, 240, 255, 0.05);
            transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1), border-color 0.3s;
            margin: auto;
        }
        
        .mainframe-box::before, .mainframe-box::after {
            content: ''; position: absolute; width: 16px; height: 16px; border: 2px solid var(--neon-cyan);
        }
        .mainframe-box::before { top: -1px; left: -1px; border-right: none; border-bottom: none; border-radius: 16px 0 0 0; }
        .mainframe-box::after { bottom: -1px; right: -1px; border-left: none; border-top: none; border-radius: 0 0 16px 0; }

        .mainframe-box:hover {
            transform: translateY(-4px);
            border-color: var(--neon-cyan);
        }

        /* RADAR HUD GRAPHIC */
        .hud-radar {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 24px auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .hud-circle {
            position: absolute;
            border-radius: 50%;
            border: 1px dashed var(--neon-cyan);
            width: 100%; height: 100%;
            opacity: 0.4;
            will-change: transform;
            animation: radarRotate 12s linear infinite;
        }
        .hud-inner {
            position: absolute;
            width: 75%; height: 75%;
            border: 2px solid transparent;
            border-top: 2px solid var(--cyber-blue);
            border-bottom: 2px solid var(--cyber-blue);
            border-radius: 50%;
            will-change: transform;
            animation: radarRotateInverse 6s linear infinite;
        }
        
        /* 🚀 EFEK HOLOGRAM PULSE PADA LOGO */
        .hud-logo {
            width: 55px;
            height: auto;
            position: relative;
            z-index: 10;
            filter: drop-shadow(0 0 10px var(--neon-cyan)) drop-shadow(0 0 20px rgba(0, 240, 255, 0.4));
            animation: hologramPulse 2.5s infinite alternate ease-in-out;
        }
        .hud-core {
            font-size: 36px;
            position: relative;
            z-index: 2;
            filter: drop-shadow(0 0 8px var(--neon-cyan));
        }

        @keyframes radarRotate { 100% { transform: rotate(360deg); } }
        @keyframes radarRotateInverse { 100% { transform: rotate(-360deg); } }
        @keyframes hologramPulse { 
            0% { transform: scale(0.95); filter: drop-shadow(0 0 5px var(--neon-cyan)); } 
            100% { transform: scale(1.05); filter: drop-shadow(0 0 15px var(--neon-cyan)) drop-shadow(0 0 25px rgba(0, 240, 255, 0.6)); } 
        }

        /* HEADER TEXT */
        .matrix-title {
            font-size: 34px;
            font-weight: 900;
            margin: 0;
            letter-spacing: 3px;
            text-shadow: 0 0 12px rgba(0, 240, 255, 0.6);
        }
        
        .sys-subtitle {
            font-family: monospace;
            color: var(--cyber-blue);
            font-size: 13px;
            letter-spacing: 1.5px;
            margin: 8px 0 30px 0;
        }

        /* METRICS MONITORING GRID */
        .metrics-layout {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }
        .metric-card-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 16px 8px;
            border-radius: 8px;
            transition: border-color 0.2s;
        }
        .metric-card-box:hover {
            border-color: rgba(0, 240, 255, 0.3);
        }
        .m-value-text { font-size: 22px; font-weight: 800; color: #ffffff; text-shadow: 0 0 8px rgba(0, 240, 255, 0.4); margin-bottom: 4px; }
        .m-label-text { font-size: 9.5px; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; font-weight: 700; }

        /* STATUS TERMINAL PANEL */
        .status-terminal {
            background: rgba(2, 6, 23, 0.9);
            border: 1px solid #1e293b;
            padding: 12px 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: monospace;
            font-size: 11.5px;
            color: #cbd5e1;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .text-cyan { color: var(--neon-cyan); font-weight: bold; }

        /* LAUNCH CORE BUTTON */
        .launch-action-btn {
            display: block;
            background: linear-gradient(135deg, rgba(0, 240, 255, 0.15) 0%, rgba(2, 132, 199, 0.15) 100%);
            color: var(--neon-cyan);
            border: 1px solid var(--neon-cyan);
            padding: 15px;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 0 15px rgba(0, 240, 255, 0.1);
            width: 100%;
        }
        .launch-action-btn:hover {
            background: var(--neon-cyan);
            color: #020617;
            box-shadow: 0 0 25px var(--neon-cyan);
            transform: scale(1.02);
        }
        .launch-action-btn:active {
            transform: scale(0.98);
        }

        /* 🚀 RESPONSIVE LAYAR UNTUK iPHONE & ANDROID */
        @media (max-width: 640px) {
            .mainframe-box { 
                padding: 25px 15px; 
                border-radius: 12px;
                border-width: 1px;
            }
            .matrix-title { font-size: 22px; letter-spacing: 1.5px; }
            .sys-subtitle { font-size: 11px; margin-bottom: 20px; }
            .metrics-layout { 
                grid-template-columns: 1fr; /* Jadi 1 kolom bersusun ke bawah di HP */
                gap: 12px; 
                margin-bottom: 24px;
            }
            .metric-card-box { padding: 12px; }
            .m-value-text { font-size: 20px; }
            .status-terminal { 
                flex-direction: column; 
                text-align: center; 
                padding: 15px;
                font-size: 12px;
                margin-bottom: 24px;
            }
            .launch-action-btn { 
                padding: 18px; /* Tombol dibuat lebih tebal untuk jempol */
                font-size: 13px; 
            }
            .hud-radar { width: 80px; height: 80px; margin-bottom: 15px; }
            .hud-logo { width: 45px; } /* Logo lebih kecil di HP */
            .hud-core { font-size: 28px; }
        }
    </style>
</head>
<body>

    <div class="ambient-glow"></div>

    <div class="mainframe-box">
        
        <div class="hud-radar">
            <div class="hud-circle"></div>
            <div class="hud-inner"></div>
            <!-- 🚀 LOGO DENGAN EFEK HOLOGRAM PULSE -->
            <img src="logo.png?v=1" alt="H2 BASE Logo" class="hud-logo" onerror="this.outerHTML='<div class=\'hud-core\'>🏭</div>'">
        </div>

        <h1 class="matrix-title">H2 BASE CENTRAL</h1>
        <div class="sys-subtitle">> DATABASE CONSOLE ENTERPRISE</div>

        <!-- 🚀 DITAMBAHKAN: KOTAK JAM DIGITAL REAL-TIME -->
        <div style="font-family: monospace; color: #94a3b8; font-size: 12px; margin-bottom: 20px; background: rgba(0, 240, 255, 0.05); border: 1px solid rgba(0, 240, 255, 0.15); padding: 8px; border-radius: 6px; display: inline-block;">
            > SYS.DATETIME: <span id="live-clock" style="color: var(--neon-cyan); font-weight: bold;">SYNCING...</span>
        </div>

        <div class="metrics-layout">
            <div class="metric-card-box">
                <div class="m-value-text"><?= number_format($total_karyawan) ?></div>
                <div class="m-label-text">Personel Aktif</div>
            </div>
            
            <div class="metric-card-box">
                <div class="m-value-text"><?= number_format($total_aktivitas) ?></div>
                <div class="m-label-text">Log Aktivitas Sistem</div>
            </div>

            <div class="metric-card-box">
                <div class="m-value-text" style="color: var(--cyber-blue);"><?= $update_terakhir ?></div>
                <div class="m-label-text">SINKRONISASI REALTIME</div>
            </div>
        </div>

        <div class="status-terminal">
            <div>NETWORK: <span style="color:#10b981; font-weight: bold;">SECURE ONLINE</span></div>
            <div>OPERATOR: <span class="text-cyan">@<?= $user_aktif ?></span></div>
            <div>PRIVILEGE: <span class="text-cyan"><?= strtoupper($user_role) ?></span></div>
        </div>

        <a href="<?= $link_masuk ?>" class="launch-action-btn">ACCESS SYSTEM CONTROL ➔</a>

    </div>

    <!-- 🚀 SCRIPT PENGGERAK JAM DIGITAL -->
    <script>
        function updateClock() {
            const now = new Date();
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
            
            const dayName = days[now.getDay()];
            const date = String(now.getDate()).padStart(2, '0');
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();
            
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const timeString = `${dayName}, ${date} ${monthName} ${year} [${hours}:${minutes}:${seconds}]`;
            const clockEl = document.getElementById('live-clock');
            if(clockEl) clockEl.innerText = timeString.toUpperCase();
        }
        
        // Jalankan langsung dan set interval per 1 detik (1000ms)
        updateClock();
        setInterval(updateClock, 1000);
    </script>

</body>
</html>