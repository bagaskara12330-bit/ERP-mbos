<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// =========================================================================================
// 🚀 [PERBAIKAN SAKTI] SISTEM KEAMANAN REAL-TIME (ANTI BOCOR AKSES)
// =========================================================================================
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt_cek_akses = $pdo->prepare("SELECT role, akses_menu, status FROM master_akun WHERE id = ?");
        $stmt_cek_akses->execute([$_SESSION['user_id']]);
        $user_realtime = $stmt_cek_akses->fetch(PDO::FETCH_ASSOC);

        if ($user_realtime) {
            // Tendang paksa jika akun mendadak diblokir Admin
            if ($user_realtime['status'] == 'Nonaktif') {
                session_destroy();
                echo "<script>alert('🛑 AKSES DIBLOKIR! Akun Anda dinonaktifkan Admin.'); window.location.href='login.php';</script>";
                exit();
            }
            // Update memori session secara real-time!
            $_SESSION['role'] = $user_realtime['role'];
            $_SESSION['akses_menu'] = $user_realtime['akses_menu'];
        } else {
            session_destroy(); header("Location: login.php"); exit();
        }
    } catch (Exception $e) {}
}
// =========================================================================================

if (!isset($page_title)) { $page_title = "H2 BASE ERP"; }
if (!isset($active_page)) { $active_page = ""; }

// 🚀 LOGIKA PINTAR KELOMPOK LACI TERBUKA OTOMATIS
$grp_inv  = in_array($active_page, ['stock_ex', 'stok_opname', 'inv_wip']) ? 'show' : ''; 
$grp_prod = in_array($active_page, ['laporan', 'rekap', 'kpi', 'downtime', 'flexo', 'produksi_nc']) ? 'show' : ''; 
$grp_qc   = in_array($active_page, ['qc_laporan', 'qc_incoming']) ? 'show' : ''; 
$grp_dash = in_array($active_page, ['dashboard', 'dashboard2', 'karyawan_dashboard', 'dashboard_flexo', 'dashboard_proll', 'mtc_dashboard', 'dash_corr', 'dash_prod', 'qc_dashboard', 'dashboard_stok_opname']) ? 'show' : '';
$grp_hrd  = in_array($active_page, ['karyawan_data', 'karyawan_lembur', 'karyawan_absen', 'hrd_interview']) ? 'show' : '';
$grp_mtc  = in_array($active_page, ['mtc_sparepart']) ? 'show' : ''; 
$grp_set  = in_array($active_page, ['master', 'akun']) ? 'show' : '';

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Viewer';
$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'GUEST';

// Menerjemahkan Akses Super Granular dari Database
$user_akses = isset($_SESSION['akses_menu']) ? explode(',', $_SESSION['akses_menu']) : [];
$is_admin = ($user_role === 'Admin');

// HANYA MENGANDALKAN CHECKBOX DARI DATABASE!
$s_inv_m = $is_admin || in_array('inv_masuk', $user_akses);
$s_inv_m = $is_admin || in_array('inv_masuk', $user_akses);
$s_inv_k = $is_admin || in_array('inv_keluar', $user_akses);
$s_inv_s = $is_admin || in_array('inv_stok', $user_akses);
$s_inv_wip = $is_admin || in_array('inv_wip', $user_akses);
$show_grp_inv = $s_inv_m || $s_inv_k || $s_inv_s || $s_inv_wip;

$s_prod_l = $is_admin || in_array('prod_lap', $user_akses);
$s_prod_r = $is_admin || in_array('prod_rek', $user_akses);
$s_prod_k = $is_admin || in_array('prod_kpi', $user_akses);
$s_prod_f = $is_admin || in_array('prod_flexo', $user_akses);
$s_prod_d = $is_admin || in_array('prod_downtime', $user_akses); 
$s_prod_s = $is_admin || in_array('prod_slitter', $user_akses);
$show_grp_prod = $s_prod_l || $s_prod_r || $s_prod_k || $s_prod_f || $s_prod_d || $s_prod_s;

$s_qc_lap = $is_admin || in_array('qc_lap', $user_akses);
$s_qc_inc = $is_admin || in_array('qc_inc', $user_akses);
$show_grp_qc = $s_qc_lap || $s_qc_inc;

$s_dash_p = $is_admin || in_array('dash_prod', $user_akses);
$s_dash_nc = $is_admin || in_array('dash_nc', $user_akses);
$s_dash_e = $is_admin || in_array('dash_eff', $user_akses);
$s_dash_h = $is_admin || in_array('dash_hrd', $user_akses);
$s_dash_f = $is_admin || in_array('dash_flexo', $user_akses);
$s_dash_k = $is_admin || in_array('inv_keluar', $user_akses); 
$s_dash_m = $is_admin || in_array('dash_mtc', $user_akses);
$s_dash_c = $is_admin || in_array('dash_corr', $user_akses); 
$s_qc_dash = $is_admin || in_array('qc_dash', $user_akses);
$s_dash_stok = $is_admin || in_array('dash_stok', $user_akses);
$show_grp_dash = $s_dash_p || $s_dash_nc || $s_dash_e || $s_dash_h || $s_dash_f || $s_dash_k || $s_dash_m || $s_dash_c || $s_qc_dash || $s_dash_stok;

$s_hrd_d = $is_admin || in_array('hrd_data', $user_akses);
$s_hrd_l = $is_admin || in_array('hrd_lemb', $user_akses);
$s_hrd_i = $is_admin || in_array('hrd_interview', $user_akses);
$show_grp_hrd = $s_hrd_d || $s_hrd_l || $s_hrd_i;

$s_mtc_dash = $is_admin || in_array('dash_mtc', $user_akses);
$s_mtc_part = $is_admin || in_array('mtc_sparepart', $user_akses);
$show_grp_mtc = $s_mtc_dash || $s_mtc_part;

$s_set_m = $is_admin || in_array('set_master', $user_akses);
$s_set_a = $is_admin;
$show_grp_set = $s_set_m || $s_set_a;

// AMBIL NOTIFIKASI
try {
    if (isset($pdo)) {
        // 🧹 AUTO-CLEANUP: Hapus otomatis notifikasi yang usianya lebih dari 7 hari agar database tidak penuh
        $pdo->exec("DELETE FROM db_aktivitas_log WHERE waktu < NOW() - INTERVAL 7 DAY");

        $stmt_log = $pdo->query("SELECT * FROM db_aktivitas_log ORDER BY id DESC LIMIT 15");
        $list_notif = $stmt_log->fetchAll(PDO::FETCH_ASSOC);
        $jml_notif = count($list_notif);
        $latest_notif_id = $jml_notif > 0 ? $list_notif[0]['id'] : 0;
    } else {
        $list_notif = []; $jml_notif = 0; $latest_notif_id = 0;
    }
} catch (PDOException $e) { $list_notif = []; $jml_notif = 0; $latest_notif_id = 0; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    
    <link rel="icon" type="image/png" href="logo.png?v=1">
    <link rel="shortcut icon" type="image/x-icon" href="logo.png?v=1">
    
    <!-- PWA & Mobile Home Screen Shortcut -->
    <link rel="apple-touch-icon" href="logo.png?v=2">
    <meta name="apple-mobile-web-app-title" content="MBOS ERP">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="manifest.json?v=2">
    <meta name="theme-color" content="#0f172a">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <style>
        /* CSS GLOBAL OVERHAUL (HIGH PREMIUM SAAS STYLE) */
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8fafc; margin: 0; padding: 0; color: #0f172a; overflow-x: hidden; -webkit-font-smoothing: antialiased;}
        .container { margin-left: 260px; padding: 25px 35px; min-height: 100vh; box-sizing: border-box; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        h2 { color: #0f172a; font-size: 16px; font-weight: 800; margin: 0 0 20px 0; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; letter-spacing: 0.5px;}
        h3, .chart-title { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px;}

        /* SIDEBAR RADICAL DESIGN (CLEAN PILL STYLE) */
        .sidebar { width: 260px; background: #0f172a; height: 100vh; position: fixed; top: 0; left: 0; overflow-y: auto; z-index: 1000; display: flex; flex-direction: column; border-right: 1px solid #1e293b; box-shadow: 4px 0 20px rgba(15,23,42,0.03); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar-brand { padding: 26px 20px; text-align: center; border-bottom: 1px solid #1e293b; margin-bottom: 15px; }
        .sidebar-brand h1 { margin: 0; color: #ffffff; font-size: 20px; font-weight: 800; letter-spacing: 0.5px; display: flex; align-items: center; justify-content: center; gap: 10px;}
        .sidebar-brand p { margin: 6px 0 0 0; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700;}
        
        .menu-group { margin-bottom: 12px; }
        .menu-title { font-size: 10px; font-weight: 800; color: #475569; text-transform: uppercase; margin: 0 20px 6px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; padding-bottom: 4px; letter-spacing: 1px; transition: color 0.2s;}
        .menu-title:hover { color: #94a3b8; }
        .menu-items { display: none; } .menu-items.show { display: block; }
        .toggle-arrow { font-size: 8px; transition: transform 0.2s ease; color: #475569;} .toggle-arrow.open { transform: rotate(-180deg); color: #94a3b8;}
        
        /* MODERN CAPSULE PILL DESIGN FOR LINKS */
        .nav-item-main { display: block; color: #94a3b8; text-decoration: none; padding: 10px 16px; font-size: 13px; font-weight: 500; border-radius: 8px; margin: 2px 14px; transition: all 0.2s ease-in-out; }
        .nav-item-main:hover { background: rgba(255, 255, 255, 0.04); color: #ffffff; padding-left: 20px; }
        .nav-item-main.active { background: #0ea5e9; color: #ffffff; font-weight: 700; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25); }
        
        .sidebar-footer { padding: 18px 20px; text-align: center; margin-top: auto; border-top: 1px solid #1e293b; font-size: 11px; color: #475569; font-weight: 500; line-height: 1.4;}
        .sidebar::-webkit-scrollbar { width: 4px; } .sidebar::-webkit-scrollbar-track { background: #0f172a; } .sidebar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
        body.hide-sidebar .sidebar { transform: translateX(-260px); } body.hide-sidebar .container { margin-left: 0; }

        /* FLOATING GLASSMORPHISM TOPBAR */
        .topbar-desktop { 
            position: relative; /* 🛠 FIX NOTIF TENGGELAM */
            z-index: 9999;      /* 🛠 FIX NOTIF TENGGELAM */
            display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; padding: 8px 16px; 
            background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); 
            border: 1px solid rgba(226,232,240,0.8); border-radius: 12px; box-shadow: 0 1px 3px rgba(15,23,42,0.03); 
        }
        .menu-toggle-desktop { background: #0f172a; color: #ffffff; border: none; padding: 7px 12px; border-radius: 6px; cursor: pointer; font-weight: 700; font-size: 11.5px; transition: all 0.2s; display: flex; align-items: center; gap: 6px;}
        .menu-toggle-desktop:hover { background: #1e293b; transform: translateY(-1px); }
        .topbar-title { font-size: 12px; font-weight: 600; color: #475569; }
        
        /* BUTTON RE-DESIGN */
        .btn-print { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 7px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; transition: all 0.2s;}
        .btn-print:hover { background: #bae6fd; transform: translateY(-1px); }
        .btn-excel { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; padding: 7px 12px; border-radius: 6px; font-weight: 700; font-size: 11.5px; cursor: pointer; transition: all 0.2s;}
        .btn-excel:hover { background: #bbf7d0; transform: translateY(-1px); }
        
        .user-profile { margin-left: 5px; padding-left: 12px; border-left: 2px solid #e2e8f0; font-size: 11.5px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px; }
        .btn-logout { color: #dc2626; text-decoration: none; font-size: 11px; font-weight: 800; background: #fee2e2; padding: 5px 10px; border-radius: 6px; border: 1px solid #fecaca; transition: all 0.2s;}
        .btn-logout:hover { background: #fca5a5; }

        /* NOTIFICATION PREMUM POPUP */
        .notif-wrapper { position: relative; display: inline-block; margin-left: 5px;}
        .notif-icon { font-size: 16px; cursor: pointer; position: relative; user-select: none; background: #f1f5f9; padding: 6px; border-radius: 6px; transition: all 0.2s; border: 1px solid #e2e8f0;}
        .notif-icon:hover { background: #e2e8f0; }
        .notif-badge { position: absolute; top: -3px; right: -3px; background: #ef4444; color: white; font-size: 8px; font-weight: 900; border-radius: 10px; padding: 1px 4px; border: 2px solid #fff;}
        .notif-dropdown { display: none; position: absolute; top: 46px; right: -10px; width: 360px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); z-index: 99999; overflow: hidden; }
        .notif-dropdown.show { display: block; animation: slideDown 0.15s cubic-bezier(0, 0, 0.2, 1); }
        .notif-header { background: #f8fafc; padding: 14px 16px; font-weight: 800; color: #0f172a; border-bottom: 1px solid #e2e8f0; font-size: 13px; display: flex; justify-content: space-between; align-items: center;}
        .notif-list { max-height: 360px; overflow-y: auto; }
        .notif-item { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; display: flex; gap: 12px; align-items: flex-start; transition: all 0.15s;}
        .notif-item:hover { background: #f8fafc; }
        .notif-avatar { background: #eff6ff; width: 34px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; border: 1px solid #dbeafe;}
        .notif-text { font-size: 12px; line-height: 1.5; color: #334155; }
        .notif-text strong { color: #0f172a; font-weight: 700; }
        .notif-time { display: block; font-size: 10px; color: #94a3b8; margin-top: 5px; font-weight: 600; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        /* CORE UI ELEMENTS (CARDS & TABLES RE-TOUCH) */
        @keyframes globalFadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card, .form-group-col, .filter-card, .chart-card, .full-width-card, .chart-container, .table-wrapper, .dashboard-grid { 
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 24px; 
            box-shadow: 0 1px 3px rgba(15,23,42,0.03); transition: box-shadow 0.2s ease; 
            animation: globalFadeInUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px 22px; margin-bottom: 20px; }
        input, select { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: #0f172a; background: #f8fafc; box-sizing: border-box; font-weight: 500; transition: all 0.2s;}
        input:focus, select:focus { border-color: #0ea5e9; outline: none; background: #ffffff; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }
        
        /* PREMIUM TABLE STYLING */
        .table-responsive { background: white; border-radius: 10px; border: 1px solid #cbd5e1; overflow-x: auto; max-height: 650px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #334155; white-space: nowrap; }
        th, td { padding: 12px 14px; text-align: right; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9; }
        th { background-color: #0f172a; color: #ffffff; font-weight: 600; position: sticky; top: 0; z-index: 10; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 2px solid #1e293b;}
        th.text-center, td.text-center, td.text-left { text-align: center; } td.text-left { text-align: left; }
        table tbody tr:nth-child(even) td { background-color: #f8fafc; } table tbody tr:hover td { background-color: #f1f5f9 !important; }
        .total-row td { background-color: #e2e8f0 !important; font-weight: 800; color: #0f172a; border-top: 2px solid #cbd5e1;}
        
        /* PREMIUM STICKY TABLE COLUMN INDICATORS */
        .sticky-col-1 { position: sticky; left: 0; z-index: 5; background: #fff; font-weight: 700; border-right: 2px solid #cbd5e1 !important; }
        .sticky-col-2 { position: sticky; left: 40px; z-index: 5; background: #fff; }
        .sticky-col-3 { position: sticky; left: 95px; z-index: 5; background: #f0f9ff; font-weight: 800; color: #0284c7; }
        .sticky-col-4 { position: sticky; left: 175px; z-index: 5; background: #f8fafc; border-right: 2px solid #cbd5e1 !important; }
        th.sticky-col-1, th.sticky-col-2, th.sticky-col-3, th.sticky-col-4 { background: #0f172a; z-index: 11; color: white;}
        tr:nth-child(even) td.sticky-col-1, tr:nth-child(even) td.sticky-col-2 { background: #f8fafc; } tr:nth-child(even) td.sticky-col-3 { background: #edf8ff; }

        /* DROPDOWN LIVE SEARCH AJAX */
        .ajax-dropdown-container {
            position: absolute; top: 100%; left: 0; right: 0; min-width: 280px; background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid #e2e8f0;
            border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); margin-top: 8px; z-index: 10000;
            max-height: 400px; overflow-y: auto; display: none; flex-direction: column; text-align: left;
        }
        .ajax-group { font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; padding: 12px 16px 4px 16px; letter-spacing: 0.5px; }
        .ajax-item { display: flex; align-items: flex-start; gap: 12px; padding: 10px 16px; text-decoration: none; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; }
        .ajax-item:hover { background: #f8fafc; }
        .ajax-icon { font-size: 16px; margin-top: 2px; }
        .ajax-text { display: flex; flex-direction: column; gap: 2px; }
        .ajax-title { font-size: 13px; font-weight: 600; color: #0f172a; line-height: 1.3; }
        .ajax-subtitle { font-size: 11px; color: #64748b; }
        .ajax-see-all { display: block; text-align: center; padding: 10px; background: #f0f9ff; color: #0284c7; font-size: 12px; font-weight: 700; text-decoration: none; border-top: 1px solid #e0f2fe; border-radius: 0 0 12px 12px; transition: background 0.2s; }
        .ajax-see-all:hover { background: #e0f2fe; }

        /* RESPONSIVE HEADERS FOR MOBILE */
        .mobile-header { display: none; padding: 12px 16px; background: rgba(255,255,255,0.85); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 9999; align-items: center; justify-content: space-between; margin: -25px -35px 25px -35px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); } /* 🚀 FIX NOTIF TENGGELAM MOBILE */
        .menu-toggle { background: #0f172a; color: white; border: none; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 6px;}
        .sidebar-overlay { display: none; position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(15, 23, 42, 0.3); z-index: 999; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-260px); } .sidebar.show { transform: translateX(0); }
            .container { margin-left: 0; padding: 25px 20px; width: 100%; } .mobile-header { display: flex; margin: -25px -20px 20px -20px; }
            .sidebar-overlay.show { display: block; } .topbar-desktop { display: none; }
        }
        @media (max-width: 768px) {
            .container { padding: 15px; } .mobile-header { margin: -15px -15px 15px -15px; padding: 12px 15px; }
            .form-grid, .form-grid-top, [class*="form-grid"] { grid-template-columns: 1fr !important; gap: 12px !important; }
            input, select, textarea { width: 100% !important; } .btn-group { flex-direction: column; width: 100%; gap: 10px; }
            .btn-submit, .btn-batal { width: 100%; padding: 12px; font-size: 14px; } .card, .chart-card { padding: 18px; margin-bottom: 16px; }
            input.search-box { width: 100% !important; margin-top: 10px; }
            
            /* FIX NOTIF DROPDOWN OVERFLOW */
            .notif-dropdown { right: -15px; width: 92vw; max-width: 320px; }
            .notif-dropdown::before { right: 25px; }
            
            /* GLOBAL MOBILE FLEX FIXES */
            .form-toggle-header > div { flex-direction: column !important; align-items: stretch !important; gap: 12px; text-align: center; }
            .form-toggle-header h2 { font-size: 15px !important; }
            .form-toggle-header span { width: 100%; box-sizing: border-box; text-align: center; }
            
            /* FIX TABLE HEADER & BUTTONS OVERLAPPING */
            div[style*="display:flex"] > h3 { width: 100%; text-align: center; margin-bottom: 5px !important; font-size: 14px !important; }
            div[style*="display:flex"] > .btn-submit-modern { width: 100%; padding: 10px; justify-content: center; }
            form[style*="display:flex"], form[style*="display: flex"] { flex-direction: column !important; align-items: stretch !important; gap: 10px !important; width: 100%; }
            
            /* DASHBOARD & MTC WIDGET FIXES */
            .summary-grid, .half-grid, .dashboard-grid { grid-template-columns: 1fr !important; }
            .filter-box, .filter-container { flex-direction: column !important; align-items: stretch !important; }
            .filter-box .form-group, .filter-container .form-group { min-width: 100% !important; }
            
            /* FIX SEARCH HEADER HP */
            .mobile-header .ajax-search-input { width: 100px !important; transition: width 0.3s; }
            .mobile-header .ajax-search-input:focus { width: 160px !important; }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="mainSidebar">
        <div class="sidebar-brand">
            <h1><img src="logo.png" alt="Logo" style="width: 32px; height: auto;"> H2 BASE</h1>
            <p>Enterprise Resource Planning</p>
        </div>

        <?php if ($show_grp_inv): ?>
        <div class="menu-group">
            <div class="menu-title" onclick="toggleMenu('menu-inv', this)"><span>📦 Inventory & Gudang</span><span class="toggle-arrow <?= $grp_inv ? 'open' : '' ?>">▼</span></div>
            <div class="menu-items <?= $grp_inv ?>" id="menu-inv">
                <?php if ($s_inv_m): ?><a href="stock_ex.php" class="nav-item-main <?= ($active_page == 'stock_ex') ? 'active' : '' ?>">Stock Ex Papper</a><?php endif; ?>
                <?php if ($s_inv_s): ?><a href="stok_opname.php" class="nav-item-main <?= ($active_page == 'stok_opname') ? 'active' : '' ?>">Stok Opname</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($show_grp_prod): ?>
        <div class="menu-group">
            <div class="menu-title" onclick="toggleMenu('menu-prod', this)"><span>⚙️ Produksi & Pabrik</span><span class="toggle-arrow <?= $grp_prod ? 'open' : '' ?>">▼</span></div>
            <div class="menu-items <?= $grp_prod ?>" id="menu-prod">
                <?php if ($s_prod_l): ?><a href="laporan.php" class="nav-item-main <?= ($active_page == 'laporan') ? 'active' : '' ?>">Input Laporan</a><?php endif; ?>
                <?php if ($s_prod_r): ?><a href="rekap_bulanan.php" class="nav-item-main <?= ($active_page == 'rekap') ? 'active' : '' ?>">Rekap Bulanan</a><?php endif; ?>
                <?php if ($s_prod_k): ?><a href="kpi.php" class="nav-item-main <?= ($active_page == 'kpi') ? 'active' : '' ?>">Report KPI Harian</a><?php endif; ?>
                <?php if ($s_prod_f): ?><a href="produktifitas_flexo.php" class="nav-item-main <?= ($active_page == 'flexo') ? 'active' : '' ?>">Produktifitas Flexo</a><?php endif; ?>
                <?php if ($s_prod_d): ?><a href="downtime_corr.php" class="nav-item-main <?= ($active_page == 'downtime') ? 'active' : '' ?>">Downtime Corrugator</a><?php endif; ?>
                
                <?php if ($s_prod_s): ?><a href="produksi_nc.php" class="nav-item-main <?= ($active_page == 'produksi_nc') ? 'active' : '' ?>">Produksi NC</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($show_grp_qc): ?>
        <div class="menu-group">
            <div class="menu-title" onclick="toggleMenu('menu-qc', this)"><span>🔎 Quality Control</span><span class="toggle-arrow <?= $grp_qc ? 'open' : '' ?>">▼</span></div>
            <div class="menu-items <?= $grp_qc ?>" id="menu-qc">
                <?php if ($s_qc_inc): ?><a href="qc_incoming.php" class="nav-item-main <?= ($active_page == 'qc_incoming') ? 'active' : '' ?>">QC Incoming</a><?php endif; ?>
                <?php if ($s_qc_lap): ?><a href="qc_laporan.php" class="nav-item-main <?= ($active_page == 'qc_laporan') ? 'active' : '' ?>">Laporan QC</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($show_grp_dash): ?>
        <div class="menu-group">
            <div class="menu-title" onclick="toggleMenu('menu-dash', this)"><span>📊 Executive Dashboard</span><span class="toggle-arrow <?= $grp_dash ? 'open' : '' ?>">▼</span></div>
            <div class="menu-items <?= $grp_dash ?>" id="menu-dash">
                <?php if ($s_dash_p): ?>
                    <a href="dashboard.php" class="nav-item-main <?= ($active_page == 'dashboard') ? 'active' : '' ?>">Dash. Corr</a>
                <?php endif; ?>
                <?php if ($s_dash_nc): ?>
                    <a href="dashboard_produksi_nc.php" class="nav-item-main <?= ($active_page == 'dash_prod') ? 'active' : '' ?>">Dash. Produksi NC</a>
                <?php endif; ?>
                <?php if ($s_dash_e): ?><a href="dashboard2.php" class="nav-item-main <?= ($active_page == 'dashboard2') ? 'active' : '' ?>">Dashboard Efisiensi</a><?php endif; ?>
                <?php if ($s_dash_h): ?><a href="karyawan_dashboard.php" class="nav-item-main <?= ($active_page == 'karyawan_dashboard') ? 'active' : '' ?>">Dashboard Karyawan</a><?php endif; ?>
                <?php if ($s_dash_f): ?><a href="dashboard_flexo.php" class="nav-item-main <?= ($active_page == 'dashboard_flexo') ? 'active' : '' ?>">Dashboard Flexo</a><?php endif; ?>
                <?php if ($s_dash_c): ?><a href="dashboard_corr.php" class="nav-item-main <?= ($active_page == 'dash_corr') ? 'active' : '' ?>">Dashboard Downtime</a><?php endif; ?>
                <?php if ($s_qc_dash): ?><a href="qc_dashboard.php" class="nav-item-main <?= ($active_page == 'qc_dashboard') ? 'active' : '' ?>">Dashboard QC</a><?php endif; ?>
                <?php if ($s_dash_k): ?><a href="dashboard_proll.php" class="nav-item-main <?= ($active_page == 'dashboard_proll') ? 'active' : '' ?>">Dashboard Proll</a><?php endif; ?>
                <?php if ($s_dash_m): ?><a href="mtc_dashboard.php" class="nav-item-main <?= ($active_page == 'mtc_dashboard') ? 'active' : '' ?>">Dashboard MTC</a><?php endif; ?>            
            </div>
        </div>
        <?php endif; ?>

        <?php if ($show_grp_mtc): ?>
        <div class="menu-group">
            <div class="menu-title" onclick="toggleMenu('menu-mtc', this)"><span>🛠️ MTC & Utility</span><span class="toggle-arrow <?= $grp_mtc ? 'open' : '' ?>">▼</span></div>
            <div class="menu-items <?= $grp_mtc ?>" id="menu-mtc">
                <?php if ($s_mtc_part): ?><a href="mtc_sparepart.php" class="nav-item-main <?= ($active_page == 'mtc_sparepart') ? 'active' : '' ?>">Sparepart & Transaksi</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($show_grp_hrd): ?>
        <div class="menu-group">
            <div class="menu-title" onclick="toggleMenu('menu-hrd', this)"><span>👥 HRD & Personalia</span><span class="toggle-arrow <?= $grp_hrd ? 'open' : '' ?>">▼</span></div>
            <div class="menu-items <?= $grp_hrd ?>" id="menu-hrd">
                <?php if ($s_hrd_d): ?><a href="karyawan_data.php" class="nav-item-main <?= ($active_page == 'karyawan_data') ? 'active' : '' ?>">Data Karyawan</a><?php endif; ?>
                <?php if ($s_hrd_l): ?><a href="karyawan_lembur.php" class="nav-item-main <?= ($active_page == 'karyawan_lembur') ? 'active' : '' ?>">Log Lembur</a><?php endif; ?>
                <?php if ($s_hrd_d): ?><a href="karyawan_absen.php" class="nav-item-main <?= ($active_page == 'karyawan_absen') ? 'active' : '' ?>">Absensi Harian</a><?php endif; ?>
                <?php if ($s_hrd_i): ?><a href="hrd_interview.php" class="nav-item-main <?= ($active_page == 'hrd_interview') ? 'active' : '' ?>">Report Interview</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($show_grp_set): ?>
        <div class="menu-group">
            <div class="menu-title" onclick="toggleMenu('menu-set', this)"><span>🔐 Pengaturan</span><span class="toggle-arrow <?= $grp_set ? 'open' : '' ?>">▼</span></div>
            <div class="menu-items <?= $grp_set ?>" id="menu-set">
                <?php if ($s_set_m): ?><a href="master_data.php" class="nav-item-main <?= ($active_page == 'master') ? 'active' : '' ?>">Master Data Regu</a><?php endif; ?>
                <?php if ($s_set_a): ?><a href="akun.php" class="nav-item-main <?= ($active_page == 'akun') ? 'active' : '' ?>">Manajemen Akun Login</a><?php endif; ?>
                <?php if ($is_admin): ?>
                    <a href="audit_log.php" class="nav-item-main <?= ($active_page == 'audit') ? 'active' : '' ?>">🕵️ Jejak Digital (Audit Log)</a>
                    <a href="backup_db.php" class="nav-item-main" style="color:#10b981; font-weight:800;">🛡️ Backup Database</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="sidebar-footer">
            H2 BASE v2.0<br>Role: <strong style="color: #0ea5e9;"><?= strtoupper($user_role) ?></strong>
        </div>
    </div>

    <div class="container">
        <div class="topbar-desktop">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="menu-toggle-desktop" onclick="toggleDesktopSidebar()">☰ Menu</button>
                <span class="topbar-title">H2 BASE ERP / <span style="color: #0ea5e9; font-weight: 700;">Manufacturing System</span></span>
            </div>

            <div style="margin-left: auto; display: flex; align-items: center; gap: 10px;">
                <form action="global_search.php" method="GET" style="margin: 0; display: flex; align-items: center; position: relative; flex-shrink: 0;" autocomplete="off">
                    <input type="text" name="q" class="ajax-search-input" value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>" placeholder="Cari data..." required autocomplete="off" style="box-sizing: border-box; padding: 7px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 11.5px; width: 200px; background: #f1f5f9; transition: all 0.2s; outline:none; color: #0f172a; font-weight:600; text-align: left; margin: 0;" onfocus="this.style.background='#ffffff'; this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 0 0 3px rgba(14,165,233,0.15)';" onblur="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                    <div class="ajax-dropdown-container"></div>
                </form>

                <button class="btn-print" onclick="printGrafikAja()" style="margin:0;">🖨️ Cetak PDF</button>
                <button class="btn-excel" onclick="exportToExcel()" style="margin:0;">📊 Export Excel</button>

                <div class="notif-wrapper">
                    <div class="notif-icon" onclick="markNotifRead(); toggleNotif('desktop')" title="Aktivitas Terkini">
                        🔔 <span class="notif-badge" id="badge_desktop" style="display: none;"><?= $jml_notif ?></span>
                    </div>
                    <div class="notif-dropdown" id="notifDropdown_desktop">
                        <div class="notif-header">Aktivitas Sistem Terkini<span style="font-size: 10px; color: #64748b; font-weight: normal; cursor: pointer;" onclick="toggleNotif('desktop')">Tutup ✖</span></div>
                        <div class="notif-list">
                            <?php foreach($list_notif as $log): ?>
                                <div class="notif-item">
                                    <div class="notif-avatar"><?= $log['ikon'] ?></div>
                                    <div class="notif-text"><strong>@<?= htmlspecialchars($log['username']) ?></strong> <?= htmlspecialchars($log['aksi']) ?><span class="notif-time"><?= date('d M Y, H:i', strtotime($log['waktu'])) ?> WIB</span></div>
                                </div>
                            <?php endforeach; ?>
                            <?php if($jml_notif == 0): ?><div style="padding:20px; text-align:center; color:#94a3b8; font-size:12px;">Belum ada aktivitas terekam.</div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="user-profile" style="margin:0;">👤 Halo, <?= $user_aktif ?>!<a href="logout.php" class="btn-logout" onclick="return confirm('Keluar dari sistem?')">Logout</a></div>
            </div>
        </div>
        
        <div class="mobile-header">
            <h2 style="margin:0; border:none; padding:0; display:flex; align-items:center; gap:8px;">
                <img src="logo.png" alt="Logo" style="width: 23px; height: auto;"> H2 BASE
            </h2>
            <div style="display:flex; gap:12px; align-items: center;">
                <form action="global_search.php" method="GET" style="margin: 0; display: flex; position: relative;" autocomplete="off">
                    <input type="text" name="q" class="ajax-search-input" placeholder="Cari..." required autocomplete="off" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 12px; width: 100px; background: #f1f5f9; outline:none; font-weight: 600;">
                    <div class="ajax-dropdown-container" style="min-width: 250px; right: 0; left: auto;"></div>
                </form>

                <div class="notif-wrapper">
                    <div class="notif-icon" onclick="markNotifRead(); toggleNotif('mobile')">🔔 <span class="notif-badge" id="badge_mobile" style="display: none;"><?= $jml_notif ?></span></div>
                    <div class="notif-dropdown" id="notifDropdown_mobile">
                        <div class="notif-header">Aktivitas Terkini <span style="font-size: 10px; color: #64748b; font-weight: normal;" onclick="toggleNotif('mobile')">Tutup ❌</span></div>
                        <div class="notif-list">
                            <?php foreach($list_notif as $log): ?>
                                <div class="notif-item"><div class="notif-avatar"><?= $log['ikon'] ?></div><div class="notif-text"><strong>@<?= htmlspecialchars($log['username']) ?></strong> <?= htmlspecialchars($log['aksi']) ?><span class="notif-time"><?= date('d/m H:i', strtotime($log['waktu'])) ?></span></div></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout" style="margin: 0; padding: 6px 10px;" onclick="return confirm('Keluar dari sistem?')">❌</a>
                <button class="menu-toggle" onclick="toggleSidebar()">☰ Menu</button>
            </div>
        </div>

        <script>
            // === LOGIC AJAX LIVE SEARCH ===
            let searchTimeout = null;
            document.querySelectorAll('.ajax-search-input').forEach(input => {
                input.addEventListener('input', function(e) {
                    clearTimeout(searchTimeout);
                    const q = e.target.value.trim();
                    const container = e.target.closest('form').querySelector('.ajax-dropdown-container');
                    
                    if (q.length < 2) {
                        container.style.display = 'none';
                        return;
                    }
                    
                    searchTimeout = setTimeout(() => {
                        fetch('ajax_search.php?q=' + encodeURIComponent(q))
                            .then(response => response.text())
                            .then(html => {
                                if (html.trim() !== "") {
                                    container.innerHTML = html;
                                    container.style.display = 'flex';
                                } else {
                                    container.style.display = 'none';
                                }
                            })
                            .catch(err => console.error('Search error:', err));
                    }, 300); // 300ms debounce delay
                });
            });

            // Tutup dropdown kalau user klik di luar kotak pencarian
            document.addEventListener('click', function(e) {
                if (!e.target.closest('form')) {
                    document.querySelectorAll('.ajax-dropdown-container').forEach(c => c.style.display = 'none');
                }
            });

            // === JALAN PINTAS (SHORTCUT) KEYBOARD ===
            document.addEventListener('keydown', function(e) {
                // Abaikan jika sedang ngetik di form lain
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
                
                // Deteksi shortcut Ctrl+K atau tombol Slash (/)
                if ((e.ctrlKey && e.key.toLowerCase() === 'k') || e.key === '/') {
                    e.preventDefault();
                    let searchDesktop = document.querySelector('.topbar-desktop input[name="q"]');
                    let searchMobile = document.querySelector('.mobile-header input[name="q"]');
                    
                    if (searchDesktop && searchDesktop.offsetParent !== null) {
                        searchDesktop.focus();
                    } else if (searchMobile && searchMobile.offsetParent !== null) {
                        searchMobile.focus();
                    }
                }
            });

            let serverLatestId = <?= $latest_notif_id ?>;
            let localLatestId = parseInt(localStorage.getItem('h2_notif_read_id') || '0');
            
            function checkNotifBadge() {
                const bdgDesk = document.getElementById('badge_desktop');
                const bdgMob = document.getElementById('badge_mobile');
                if (serverLatestId > localLatestId && serverLatestId !== 0) {
                    if(bdgDesk) { bdgDesk.style.display = 'block'; bdgDesk.classList.add('pulse'); }
                    if(bdgMob) { bdgMob.style.display = 'block'; bdgMob.classList.add('pulse'); }
                } else {
                    if(bdgDesk) bdgDesk.style.display = 'none';
                    if(bdgMob) bdgMob.style.display = 'none';
                }
            }
            checkNotifBadge(); // Cek awal
            
            function markNotifRead() {
                localStorage.setItem('h2_notif_read_id', serverLatestId);
                localLatestId = serverLatestId;
                checkNotifBadge();
            }

            function toggleNotif(type) { var dropdown = document.getElementById('notifDropdown_' + type); dropdown.classList.toggle('show'); }
            window.onclick = function(event) {
                if (!event.target.matches('.notif-icon') && !event.target.matches('.notif-icon *')) {
                    var dropdowns = document.getElementsByClassName("notif-dropdown");
                    for (var i = 0; i < dropdowns.length; i++) { if (dropdowns[i].classList.contains('show')) { dropdowns[i].classList.remove('show'); } }
                }
            }

            // AUTO-REFRESH RADAR NOTIFIKASI (30 DETIK)
            setInterval(() => {
                fetch('ajax_notif_count.php')
                .then(res => res.json())
                .then(data => {
                    if (data.latest_id > serverLatestId) {
                        serverLatestId = data.latest_id;
                        checkNotifBadge();
                        
                        // Update List HTML Tanpa Refresh
                        document.querySelectorAll('.notif-list').forEach(list => {
                            list.innerHTML = data.html;
                        });
                    }
                }).catch(e => console.error(e));
            }, 30000); // 30 detik

            function toggleMenu(menuId, element) {
                const menu = document.getElementById(menuId); const arrow = element.querySelector('.toggle-arrow');
                if (menu.classList.contains('show')) { menu.classList.remove('show'); arrow.classList.remove('open'); } 
                else { menu.classList.add('show'); arrow.classList.add('open'); }
            }
            function toggleDesktopSidebar() { document.body.classList.toggle('hide-sidebar'); }
            function toggleSidebar() { document.getElementById('mainSidebar').classList.toggle('show'); document.getElementById('sidebarOverlay').classList.toggle('show'); }

            // ==============================================================
            // 🔒 SISTEM AUTO-LOGOUT (SENSOR IDLE 15 MENIT)
            // ==============================================================
            let idleTime = 0;
            const maxIdleTime = 15 * 60; // 15 menit (dalam detik)
            const warningTime = maxIdleTime - 10; // Munculkan peringatan di 10 detik terakhir

            // Buat Elemen Peringatan (Toast/Modal) secara dinamis
            const warningDiv = document.createElement('div');
            warningDiv.id = 'idleWarning';
            warningDiv.innerHTML = `
                <div style="font-size:32px; margin-bottom:10px;">⏳</div>
                <strong style="font-size:16px;">Sesi Akan Berakhir!</strong><br>
                <span style="font-size:13px; color:#fca5a5;">Komputer tidak mendeteksi aktivitas. Anda akan otomatis logout dalam <b id="idleCountdown">10</b> detik demi keamanan data.</span>
                <br><br><span style="font-size:11px; background:rgba(255,255,255,0.2); padding:4px 8px; border-radius:4px;">Gerakkan mouse atau tekan keyboard untuk membatalkan</span>
            `;
            warningDiv.style.cssText = "display:none; position:fixed; top:20px; left:50%; transform:translateX(-50%); background:#dc2626; color:white; padding:20px 30px; border-radius:12px; box-shadow:0 10px 25px rgba(220,38,38,0.5); z-index:999999; text-align:center; min-width:300px; border:2px solid #f87171;";
            document.body.appendChild(warningDiv);

            // Timer utama berjalan setiap detik
            const idleInterval = setInterval(timerIncrement, 1000);

            function timerIncrement() {
                idleTime = idleTime + 1;
                if (idleTime >= maxIdleTime) {
                    window.location.href = 'logout.php?reason=idle';
                } else if (idleTime >= warningTime) {
                    document.getElementById('idleWarning').style.display = 'block';
                    document.getElementById('idleCountdown').innerText = (maxIdleTime - idleTime);
                }
            }

            // Reset Timer jika ada gerakan
            function resetIdleTimer() {
                idleTime = 0;
                document.getElementById('idleWarning').style.display = 'none';
            }

            // Pasang sensor ke seluruh aktivitas fisik
            ['mousemove', 'keydown', 'scroll', 'click', 'touchstart'].forEach(evt => 
                document.addEventListener(evt, resetIdleTimer, true)
            );

            function printGrafikAja() { document.body.classList.add('print-grafik-only'); window.print(); }
            window.addEventListener('afterprint', () => { document.body.classList.remove('print-grafik-only'); });
            function exportToExcel() {
                var table = document.querySelector("table"); if (!table) { alert("⚠️ Belum ada tabel data di halaman ini!"); return; }
                var html = table.outerHTML; var blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel' });
                var url = URL.createObjectURL(blob); var a = document.createElement('a'); a.href = url;
                a.download = 'Laporan_H2_BASE_' + new Date().toISOString().slice(0,10) + '.xls';
                document.body.appendChild(a); a.click(); document.body.removeChild(a); URL.revokeObjectURL(url);
            }
        </script>