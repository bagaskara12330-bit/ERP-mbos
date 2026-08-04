<?php
require_once 'auth.php';
require_akses('inv_masuk');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false;

// TANGKAP FILTER BULAN DAN TAHUN
$bulan_filter = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$tahun_filter = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$nama_bulan_list = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$nama_bulan = $nama_bulan_list[$bulan_filter];

// Variabel Default Form
$id_edit = ''; $tanggal = date('Y-m-d'); $shift = '1'; 
$ex_kg = 0; $ex_roll = 0; $new_kg = 0; $new_roll = 0;

// 🚀 AUTO-CREATE TABEL DATABASE KHUSUS STOCK EX
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_stock_ex_paper (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        shift VARCHAR(10) DEFAULT '1',
        ex_kg DECIMAL(15,2) DEFAULT 0,
        ex_roll INT DEFAULT 0,
        new_kg DECIMAL(15,2) DEFAULT 0,
        new_roll INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// 🚀 LOGIKA HAPUS DATA AMAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id']) && $user_role != 'Viewer') {
    $id = intval($_POST['hapus_id']);
    $stmt = $pdo->prepare("DELETE FROM db_stock_ex_paper WHERE id = ?");
    if ($stmt->execute([$id])) {
        catatLog($pdo, $user_aktif, "Menghapus data Stock Ex Paper.", "🗑️");
        header("Location: stock_ex.php?pesan=hapus_sukses"); exit();
    }
}

// LOGIKA TANGKAP DATA EDIT
if (isset($_GET['edit']) && $user_role != 'Viewer') {
    $is_edit = true; $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM db_stock_ex_paper WHERE id = ?"); $stmt->execute([$id_edit]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $tanggal = $row['tanggal']; $shift = $row['shift'];
        $ex_kg = $row['ex_kg']; $ex_roll = $row['ex_roll'];
        $new_kg = $row['new_kg']; $new_roll = $row['new_roll'];
    }
}

// LOGIKA SIMPAN / UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_data']) && $user_role != 'Viewer') {
    $post_id = $_POST['id_edit'];
    $p_tgl = $_POST['tanggal'];
    $p_shift = $_POST['shift'];
    $p_ekg = floatval($_POST['ex_kg']); $p_eroll = intval($_POST['ex_roll']);
    $p_nkg = floatval($_POST['new_kg']); $p_nroll = intval($_POST['new_roll']);

    if (!empty($post_id)) {
        $stmt = $pdo->prepare("UPDATE db_stock_ex_paper SET tanggal=?, shift=?, ex_kg=?, ex_roll=?, new_kg=?, new_roll=? WHERE id=?");
        $stmt->execute([$p_tgl, $p_shift, $p_ekg, $p_eroll, $p_nkg, $p_nroll, $post_id]);
        catatLog($pdo, $user_aktif, "Mengupdate data Stock Ex tanggal " . date('d/m/Y', strtotime($p_tgl)) . ".", "✏️");
        header("Location: stock_ex.php?pesan=edit_sukses"); exit();
    } else {
        $stmt = $pdo->prepare("INSERT INTO db_stock_ex_paper (tanggal, shift, ex_kg, ex_roll, new_kg, new_roll) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$p_tgl, $p_shift, $p_ekg, $p_eroll, $p_nkg, $p_nroll]);
        catatLog($pdo, $user_aktif, "Menginput data Stock Ex tanggal " . date('d/m/Y', strtotime($p_tgl)) . ".", "♻️");
        header("Location: stock_ex.php?pesan=tambah_sukses"); exit();
    }
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'tambah_sukses') $pesan = "<div class='alert alert-success'>✅ Berhasil: Data Stock Ex disimpan!</div>";
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success'>✅ Berhasil: Perubahan diperbarui!</div>";
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-success'>🗑️ Data telah dihapus permanen!</div>";
}

$page_title = "Input Stock Ex Papper — H2 BASE ERP";
$active_page = "stock_ex";
require 'header.php';
?>

<style>
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px 20px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; min-width: 140px; }
    .form-group label { margin-bottom: 6px; font-weight: 700; font-size: 11px; color: #475569; text-transform: uppercase; }
    input, select { padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; background: #f8fafc; outline: none; transition: 0.2s; box-sizing: border-box; width: 100%; font-weight: bold;}
    input:focus, select:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); background: #ffffff;}
    
    .btn-group { display: flex; gap: 12px; justify-content: flex-end; }
    .btn-submit { background: #0f172a; color: white; border: none; padding: 10px 24px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: 0.2s; }
    .btn-submit:hover { background: #1e293b; }
    .btn-batal { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 9px 24px; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600; text-align: center; transition: 0.2s;}
    
    /* 🚀 CSS TABEL YANG DIRAPIKAN */
    .table-responsive { background: white; border-radius: 8px; border: 1px solid #cbd5e1; overflow-x: auto; max-height: 600px; margin-bottom: 20px; box-shadow: inset 0 0 5px rgba(0,0,0,0.02); }
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; color: #1e293b; white-space: nowrap; min-width: 1300px; }
    .table-premium th, .table-premium td { padding: 14px 16px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; vertical-align: middle; }
    
    .table-premium th { color: #ffffff; font-weight: 700; position: sticky; z-index: 10; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; text-align: center; }
    .table-premium thead tr:nth-child(1) th { top: 0; height: 42px; box-sizing: border-box; border-bottom: 2px solid #0f172a; }
    .table-premium thead tr:nth-child(2) th { top: 42px; height: 42px; box-sizing: border-box; border-bottom: 2px solid #0f172a; }
    
    .table-premium tbody tr:nth-child(even) td { background-color: #f8fafc; }
    .table-premium tbody tr:hover td { background-color: #f1f5f9 !important; }

    /* Helper Class untuk Rata Kanan Angka & Garis Pembatas Tegas */
    .text-right { text-align: right !important; padding-right: 20px !important; }
    .text-center { text-align: center !important; }
    .border-tegas { border-right: 3px solid #94a3b8 !important; }

    /* Penguncian Kolom Tanggal & Shift */
    .stk-1 { position: sticky; left: 0; z-index: 5; background: #fff; }
    .stk-2 { position: sticky; left: 100px; z-index: 5; background: #fff; border-right: 3px solid #94a3b8 !important; }
    .table-premium thead tr:nth-child(1) th.stk-1, .table-premium thead tr:nth-child(1) th.stk-2 { background: #0f172a; z-index: 12; }
    .table-premium thead tr:nth-child(2) th.stk-1, .table-premium thead tr:nth-child(2) th.stk-2 { background: #1e293b; z-index: 12; }
    .table-premium tbody tr td.stk-2 { box-shadow: 4px 0 6px -2px rgba(0,0,0,0.08); font-weight: 800; color: #0f172a; }
    .table-premium tbody tr td.stk-1 { font-weight: 800; color: #475569; }
    .table-premium tbody tr:nth-child(even) td.stk-1, .table-premium tbody tr:nth-child(even) td.stk-2 { background: #f8fafc; }

    .btn-aksi-edit { display: inline-block; background: #e0f2fe; color: #0284c7; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; border: 1px solid #bae6fd; transition: 0.2s;}
    .btn-aksi-edit:hover { background: #bae6fd; }
    .btn-aksi-hapus { display: inline-block; background: #fee2e2; color: #dc2626; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; border: 1px solid #fecaca; margin-left: 4px; transition: 0.2s;}
    .btn-aksi-hapus:hover { background: #fecaca; }
    
    .total-row td { background-color: #e2e8f0 !important; font-weight: 800; color: #0f172a; border-top: 3px solid #0f172a; position: sticky; bottom: 0; z-index: 9;}
    .total-row td.stk-1, .total-row td.stk-2 { background-color: #cbd5e1 !important; }

    /* CSS LIVE PREVIEW WIDGET */
    .live-preview { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 12px 16px; margin-top: 15px; display: flex; gap: 20px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
    .live-box { display: flex; flex-direction: column; }
    .live-title { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; }
    .live-value { font-size: 18px; font-weight: 900; color: #0f172a; }

    /* 🚀 STYLE TOGGLE FORM */
    .form-toggle-header { user-select: none; transition: background 0.2s; padding: 12px 16px; border-radius: 8px; margin: -10px -10px 15px -10px; cursor: pointer; }
    .form-toggle-header:hover { background: #f8fafc; }

    /* ?? PREMIUM BUTTONS */
    .btn-submit-modern {
        background: #10b981 !important; color: #ffffff !important; border: none !important; padding: 12px 28px !important; border-radius: 8px !important; font-size: 14px !important; font-weight: 800 !important; cursor: pointer !important; transition: all 0.2s ease-in-out !important; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.1) !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;
    }
    .btn-submit-modern:hover { background: #059669 !important; transform: translateY(-1px) !important; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2) !important; }

    .btn-search-modern {
        background: #0f172a !important; color: #ffffff !important; border: none !important; padding: 10px 20px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; cursor: pointer !important; transition: all 0.2s ease-in-out !important; height: 41px !important; box-sizing: border-box !important; display: inline-flex !important; align-items: center !important;
    }
    .btn-search-modern:hover { background: #1e293b !important; }

    .btn-batal-modern {
        background: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important; padding: 11px 24px !important; text-decoration: none !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; text-align: center !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; transition: all 0.2s !important;
    }
    .btn-batal-modern:hover { background: #f1f5f9 !important; color: #0f172a !important; }
    
    .btn-excel-modern {
        background: #16a34a !important; color: #ffffff !important; border: none !important; padding: 10px 20px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; cursor: pointer !important; transition: all 0.2s ease-in-out !important; height: 41px !important; box-sizing: border-box !important; display: inline-flex !important; align-items: center !important;
    }
    .btn-excel-modern:hover { background: #15803d !important; }

    /* ?? ISO 9001 PRINT CSS */
    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        body { background: white !important; font-family: 'Inter', sans-serif, 'Times New Roman' !important; color: black; margin: 0; padding: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        
        /* HIDE NON-PRINT ELEMENTS */
        .sidebar, .topbar-desktop, .mobile-header, .filter-box, .btn-submit-modern, .btn-excel-modern, .ajax-dropdown-container, .alert, form, .form-toggle-header, h2 { display: none !important; }
        
        /* RESET CONTAINER MARGINS */
        .container { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .card { border: none !important; box-shadow: none !important; padding: 0 !important; background: transparent !important; margin: 0 !important; }
        .table-responsive { max-height: none !important; box-shadow: none !important; border: none !important; overflow: visible !important; }
        
        /* KOP SURAT ISO */
        .iso-header { display: block !important; width: 100%; text-align: center; margin-bottom: 20px; }
        .iso-header table { width: 100%; border-collapse: collapse; border: 2px solid #000; }
        .iso-header td { border: 1px solid #000; padding: 8px; vertical-align: middle; color: #000; font-family: 'Inter', sans-serif; text-align: center !important; }
        .iso-logo { width: 80px; height: auto; margin: 0 auto; display: block; }
        .iso-title { font-size: 18px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; color: #000; text-align: center !important; }
        .iso-doc { font-size: 11px; text-align: left !important; font-weight: bold; color: #000; padding-left: 10px !important; }
        
        /* TABLE TWEAKS FOR PRINT */
        .table-premium { width: 100% !important; min-width: 0 !important; max-width: 100% !important; border-collapse: collapse !important; border: 2px solid #000 !important; font-size: 11px !important; }
        .table-premium th, .table-premium td { border: 1px solid #000 !important; padding: 8px !important; white-space: normal !important; word-wrap: break-word !important; height: auto !important; }
        .table-premium th { text-align: center !important; vertical-align: middle !important; }
        .table-premium thead tr:first-child th:last-child, .table-premium tbody td:last-child { display: none !important; } /* Sembunyikan Kolom Aksi Saja */
        .table-premium tr { page-break-inside: avoid; }
        .stk-1, .stk-2, .total-row td { position: static !important; } /* Reset sticky position saat print agar tidak melayang */
        
        /* ISO FOOTER (SIGNATURES) */
        .iso-footer { display: block !important; page-break-inside: avoid; margin-top: 30px; }
        .signature-box { width: 100%; border-collapse: collapse; border: 2px solid #000; text-align: center !important; font-size: 11px; }
        .signature-box th { border: 1px solid #000; padding: 8px; background: #e2e8f0 !important; font-weight: bold; color: #000; text-align: center !important; }
        .signature-box td { border: 1px solid #000; padding: 5px; color: #000; text-align: center !important; }
        .signature-box .sign-space { height: 75px; }
    }

    /* HIDDEN ON SCREEN */
    .iso-header, .iso-footer { display: none; }
</style>

<?= $pesan ?>

<?php if ($user_role != 'Viewer'): ?>
<div class="card" <?= $is_edit ? 'style="border: 2px solid #0ea5e9; background: #f0f9ff;"' : 'style="border-top: 4px solid #ef4444;"' ?>>
    <!-- 🚀 HEADER TOGGLE FORM -->
    <div class="form-toggle-header" onclick="toggleFormInput()">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid <?= $is_edit ? '#bae6fd' : '#fecaca' ?>; padding-bottom: 10px;">
            <h2 style="color: <?= $is_edit ? '#0284c7' : '#b91c1c' ?>; margin: 0; font-size: 18px; border-bottom: none; padding-bottom: 0;">
                <?= $is_edit ? "✏️ Edit Laporan Stock Ex" : "♻️ INPUT DATA STOCK EX PAPPER" ?>
            </h2>
            <span id="formToggleIcon" style="font-size:13px; font-weight:900; color:<?= $is_edit ? '#0284c7' : '#b91c1c' ?>; background:<?= $is_edit ? '#e0f2fe' : '#fef2f2' ?>; padding:4px 10px; border-radius:6px; border:1px solid <?= $is_edit ? '#bae6fd' : '#fca5a5' ?>;">
                ▼ KLIK UNTUK BUKA/TUTUP FORM
            </span>
        </div>
    </div>

    <!-- 🚀 WRAPPER FORM YANG BISA DI-TOGGLE -->
    <div id="formCollapsibleContent" style="display: none; margin-top: 15px;">
        <form action="" method="POST" autocomplete="off">
            <input type="hidden" name="id_edit" value="<?= $id_edit ?>">

            <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" value="<?= $tanggal ?>" required>
                </div>
                <div class="form-group">
                    <label>Shift</label>
                    <select name="shift" required>
                        <option value="1" <?= $shift == '1' ? 'selected' : '' ?>>Shift 1</option>
                        <option value="2" <?= $shift == '2' ? 'selected' : '' ?>>Shift 2</option>
                        <option value="3" <?= $shift == '3' ? 'selected' : '' ?>>Shift 3</option>
                    </select>
                </div>
            </div>
            
            <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 15px; border-radius: 8px;">
                    <h3 style="color: #dc2626; margin-top:0; font-size: 13px; text-align: center; border-bottom: 1px solid #fecaca; padding-bottom: 8px;">🔴 STOCK EX</h3>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 0;">
                        <div class="form-group">
                            <label style="color: #b91c1c;">Berat (KG)</label>
                            <input type="number" step="any" name="ex_kg" id="ex_kg" value="<?= $ex_kg ?>" onkeyup="hitungLive()" onchange="hitungLive()" required style="border-color: #fca5a5; color: #b91c1c;">
                        </div>
                        <div class="form-group">
                            <label style="color: #b91c1c;">Jumlah (ROLL)</label>
                            <input type="number" name="ex_roll" id="ex_roll" value="<?= $ex_roll ?>" onkeyup="hitungLive()" onchange="hitungLive()" required style="border-color: #fca5a5; color: #b91c1c;">
                        </div>
                    </div>
                </div>

                <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 15px; border-radius: 8px;">
                    <h3 style="color: #2563eb; margin-top:0; font-size: 13px; text-align: center; border-bottom: 1px solid #bfdbfe; padding-bottom: 8px;">🔵 STOCK NEW</h3>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 0;">
                        <div class="form-group">
                            <label style="color: #1d4ed8;">Berat (KG)</label>
                            <input type="number" step="any" name="new_kg" id="new_kg" value="<?= $new_kg ?>" onkeyup="hitungLive()" onchange="hitungLive()" required style="border-color: #93c5fd; color: #1d4ed8;">
                        </div>
                        <div class="form-group">
                            <label style="color: #1d4ed8;">Jumlah (ROLL)</label>
                            <input type="number" name="new_roll" id="new_roll" value="<?= $new_roll ?>" onkeyup="hitungLive()" onchange="hitungLive()" required style="border-color: #93c5fd; color: #1d4ed8;">
                        </div>
                    </div>
                </div>
            </div>

            <div class="live-preview">
                <div style="font-size: 12px; font-weight: 800; color: #475569; display: flex; align-items: center; gap: 6px;">
                    ⚙️ LIVE KALKULASI <span style="font-size: 9px; font-weight: normal; background: #e2e8f0; padding: 2px 6px; border-radius: 4px;">Otomatis</span>
                </div>
                <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                    <div class="live-box">
                        <span class="live-title">Total Stock (KG)</span>
                        <span class="live-value" id="live_tot_kg" style="color: #059669;">0 KG</span>
                    </div>
                    <div class="live-box">
                        <span class="live-title">Total Stock (ROLL)</span>
                        <span class="live-value" id="live_tot_roll" style="color: #059669;">0 ROLL</span>
                    </div>
                    <div class="live-box" style="border-left: 2px solid #cbd5e1; padding-left: 20px;">
                        <span class="live-title">% Stock EX (KG)</span>
                        <span class="live-value" id="live_pct_kg" style="color: #d97706;">0.00%</span>
                    </div>
                    <div class="live-box">
                        <span class="live-title">% Stock EX (ROLL)</span>
                        <span class="live-value" id="live_pct_roll" style="color: #d97706;">0.00%</span>
                    </div>
                </div>
            </div>
            
            <div class="btn-group" style="margin-top: 20px;">
                <?php if($is_edit): ?><a href="stock_ex.php" class="btn-batal-modern">Batal Edit</a><?php endif; ?>
                <button type="submit" name="simpan_data" class="btn-submit-modern" style="background: #ef4444; padding: 12px 30px; font-size: 14px;"><?= $is_edit ? "💾 Simpan Perubahan" : "Simpan Data Stock" ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card" style="padding-bottom: 20px; margin-bottom: 12px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
        <h2 style="margin:0; border:none; padding:0;">📊 RINCIAN DATA STOCK EX PAPPER (Bulan <?= $nama_bulan ?> <?= $tahun_filter ?>)</h2>
        
        <form method="GET" action="" style="display: flex; gap: 8px; align-items: center;">
            <select name="bulan" style="padding: 6px 10px; font-size: 12px; border-radius: 6px; border: 1px solid #cbd5e1; width: auto;">
                <?php foreach ($nama_bulan_list as $m_code => $m_name): ?>
                    <option value="<?= $m_code ?>" <?= $m_code == $bulan_filter ? 'selected' : '' ?>><?= $m_name ?></option>
                <?php endforeach; ?>
            </select>

            <select name="tahun" style="padding: 6px 10px; font-size: 12px; border-radius: 6px; border: 1px solid #cbd5e1; width: auto;">
                <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $tahun_filter ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn-submit-modern" style="padding: 6px 12px; font-size: 12px;">Cari Data</button>
            <button type="button" class="btn-excel-modern" style="margin-left: 10px; background: #3b82f6 !important;" onclick="window.print()">🖨️ Cetak PDF (ISO 9001)</button>
        </form>
    </div>

    <!-- KOP SURAT ISO 9001 (CETAK ONLY) -->
    <div class="iso-header">
        <table>
            <tr>
                <td rowspan="3" style="width: 20%;"><img src="logo.png" class="iso-logo" alt="Logo"></td>
                <td rowspan="3" style="width: 50%;" class="iso-title">LAPORAN STOCK EX PAPER<br><span style="font-size:12px; font-weight:normal; letter-spacing:0;">Periode: <?= $nama_bulan ?> <?= $tahun_filter ?></span></td>
                <td style="width: 30%;" class="iso-doc">No. Dok: FR-WH-01</td>
            </tr>
            <tr>
                <td class="iso-doc">Revisi: 00</td>
            </tr>
            <tr>
                <td class="iso-doc">Tanggal Cetak: <?= date('d/m/Y') ?></td>
            </tr>
        </table>
    </div>

    <div class="table-responsive">
        <table class="table-premium">
            <thead>
                <tr>
                    <th rowspan="2" class="stk-1" style="width: 100px;">TANGGAL</th>
                    <th rowspan="2" class="stk-2" style="width: 60px;">SHIFT</th>
                    <th colspan="2" style="background: #ef4444; border-right: 3px solid #94a3b8;">STOCK EX</th>
                    <th colspan="2" style="background: #3b82f6; border-right: 3px solid #94a3b8;">STOCK NEW</th>
                    <th colspan="2" style="background: #10b981; border-right: 3px solid #94a3b8;">TOTAL STOCK</th>
                    <th colspan="2" style="background: #f59e0b; border-right: 3px solid #94a3b8;">% STOCK EX</th>
                    <th rowspan="2" style="width: 140px;">AKSI</th>
                </tr>
                <tr>
                    <th style="background: #f87171;">KG</th>
                    <th class="border-tegas" style="background: #f87171;">ROLL</th>
                    <th style="background: #60a5fa;">KG</th>
                    <th class="border-tegas" style="background: #60a5fa;">ROLL</th>
                    <th style="background: #34d399;">KG</th>
                    <th class="border-tegas" style="background: #34d399;">ROLL</th>
                    <th style="background: #fbbf24; color: #78350f;">% KG</th>
                    <th class="border-tegas" style="background: #fbbf24; color: #78350f;">% ROLL</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $stmt = $pdo->prepare("SELECT * FROM db_stock_ex_paper WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal ASC, shift ASC");
                $stmt->execute([$bulan_filter, $tahun_filter]);
                $data_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $tot_ekg = 0; $tot_eroll = 0;
                $tot_nkg = 0; $tot_nroll = 0;
                $tot_tkg = 0; $tot_troll = 0;

                if (count($data_stock) > 0): 
                    foreach ($data_stock as $row): 
                        $ekg = floatval($row['ex_kg']); $eroll = intval($row['ex_roll']);
                        $nkg = floatval($row['new_kg']); $nroll = intval($row['new_roll']);
                        
                        $tkg = $ekg + $nkg;
                        $troll = $eroll + $nroll;
                        
                        $pct_kg = ($tkg > 0) ? ($ekg / $tkg) * 100 : 0;
                        $pct_roll = ($troll > 0) ? ($eroll / $troll) * 100 : 0;

                        $tot_ekg += $ekg; $tot_eroll += $eroll;
                        $tot_nkg += $nkg; $tot_nroll += $nroll;
                        $tot_tkg += $tkg; $tot_troll += $troll;
                ?>
                    <tr>
                        <td class="stk-1 text-center" style="font-weight: 800; font-size: 15px; color: #0f172a;"><?= date('d', strtotime($row['tanggal'])) ?></td>
                        <td class="stk-2 text-center"><?= htmlspecialchars($row['shift']) ?></td>
                        
                        <td class="text-right" style="color: #dc2626; font-weight: 800; background: #fef2f2;"><?= number_format($ekg, 0, ',', '.') ?></td>
                        <td class="text-right border-tegas" style="color: #dc2626; font-weight: 800; background: #fef2f2;"><?= number_format($eroll, 0, ',', '.') ?></td>

                        <td class="text-right" style="color: #2563eb; font-weight: 800; background: #eff6ff;"><?= number_format($nkg, 0, ',', '.') ?></td>
                        <td class="text-right border-tegas" style="color: #2563eb; font-weight: 800; background: #eff6ff;"><?= number_format($nroll, 0, ',', '.') ?></td>

                        <td class="text-right" style="color: #059669; font-weight: 900; background: #ecfdf5;"><?= number_format($tkg, 0, ',', '.') ?></td>
                        <td class="text-right border-tegas" style="color: #059669; font-weight: 900; background: #ecfdf5;"><?= number_format($troll, 0, ',', '.') ?></td>

                        <td class="text-right" style="color: #d97706; font-weight: 900; background: #fffbeb;"><?= number_format($pct_kg, 2, ',', '.') ?>%</td>
                        <td class="text-right border-tegas" style="color: #d97706; font-weight: 900; background: #fffbeb;"><?= number_format($pct_roll, 2, ',', '.') ?>%</td>

                        <td class="text-center">
                            <?php if ($user_role != 'Viewer'): ?>
                                <a href="stock_ex.php?edit=<?= $row['id'] ?>&bulan=<?= $bulan_filter ?>&tahun=<?= $tahun_filter ?>" class="btn-aksi-edit">Edit</a>
                                <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Hapus data ini?')" class="btn-aksi-hapus">Hapus</a>
                            <?php else: ?>
                                <span style="font-size:10px; color:#94a3b8; font-weight: bold;">TERBATAS</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; 
                    
                    // Kalkulasi Persentase Total Akumulasi
                    $tot_pct_kg = ($tot_tkg > 0) ? ($tot_ekg / $tot_tkg) * 100 : 0;
                    $tot_pct_roll = ($tot_troll > 0) ? ($tot_eroll / $tot_troll) * 100 : 0;
                ?>
                    <tr class="total-row">
                        <td class="stk-1 text-center" colspan="2" style="padding-right: 15px; border-right: 3px solid #94a3b8;">TOTAL AKUMULASI</td>
                        
                        <td class="text-right" style="color: #b91c1c;"><?= number_format($tot_ekg, 0, ',', '.') ?></td>
                        <td class="text-right border-tegas" style="color: #b91c1c;"><?= number_format($tot_eroll, 0, ',', '.') ?></td>
                        
                        <td class="text-right" style="color: #1d4ed8;"><?= number_format($tot_nkg, 0, ',', '.') ?></td>
                        <td class="text-right border-tegas" style="color: #1d4ed8;"><?= number_format($tot_nroll, 0, ',', '.') ?></td>
                        
                        <td class="text-right" style="color: #047857; font-size: 14px;"><?= number_format($tot_tkg, 0, ',', '.') ?></td>
                        <td class="text-right border-tegas" style="color: #047857; font-size: 14px;"><?= number_format($tot_troll, 0, ',', '.') ?></td>
                        
                        <td class="text-right" style="color: #b45309; font-size: 14px; background: #fef3c7 !important;"><?= number_format($tot_pct_kg, 2, ',', '.') ?>%</td>
                        <td class="text-right border-tegas" style="color: #b45309; font-size: 14px; background: #fef3c7 !important;"><?= number_format($tot_pct_roll, 2, ',', '.') ?>%</td>
                        
                        <td class="text-center">-</td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="11" class="text-center" style="padding: 40px; color: #94a3b8; font-size: 14px; font-weight: 600;">Belum ada input data Stock Ex Papper untuk bulan ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- SIGNATURE BOX ISO 9001 (CETAK ONLY) -->
    <div class="iso-footer">
        <table class="signature-box">
            <tr>
                <th style="width: 33.33%;">Dibuat Oleh,</th>
                <th style="width: 33.33%;">Diperiksa Oleh,</th>
                <th style="width: 33.33%;">Disetujui Oleh,</th>
            </tr>
            <tr>
                <td><div class="sign-space"></div>( ______________________ )<br>Admin Stock</td>
                <td><div class="sign-space"></div>( ______________________ )<br>Supervisor</td>
                <td><div class="sign-space"></div>( ______________________ )<br>Manager Plant</td>
            </tr>
        </table>
    </div>
</div>

<form id="formHapusGlobal" method="POST" style="display: none;">
    <input type="hidden" name="hapus_id" id="inputHapusId">
    <input type="hidden" name="hapus_data" value="1">
</form>

<script>
    // 🚀 FUNGSI TOGGLE SAKTI
    function toggleFormInput() {
        let content = document.getElementById('formCollapsibleContent');
        let icon = document.getElementById('formToggleIcon');
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.innerText = '▲ KLIK UNTUK TUTUP PANEL';
            localStorage.setItem('stockExFormState', 'open');
        } else {
            content.style.display = 'none';
            icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
            localStorage.setItem('stockExFormState', 'closed');
        }
    }

    // SCRIPT LIVE KALKULATOR
    function hitungLive() {
        let ex_kg = parseFloat(document.getElementById('ex_kg').value) || 0;
        let ex_roll = parseInt(document.getElementById('ex_roll').value) || 0;
        let new_kg = parseFloat(document.getElementById('new_kg').value) || 0;
        let new_roll = parseInt(document.getElementById('new_roll').value) || 0;

        let tot_kg = ex_kg + new_kg;
        let tot_roll = ex_roll + new_roll;

        let pct_kg = tot_kg > 0 ? ((ex_kg / tot_kg) * 100).toFixed(2) : "0.00";
        let pct_roll = tot_roll > 0 ? ((ex_roll / tot_roll) * 100).toFixed(2) : "0.00";

        document.getElementById('live_tot_kg').innerText = tot_kg.toLocaleString('id-ID') + " KG";
        document.getElementById('live_tot_roll').innerText = tot_roll.toLocaleString('id-ID') + " ROLL";
        document.getElementById('live_pct_kg').innerText = pct_kg + "%";
        document.getElementById('live_pct_roll').innerText = pct_roll + "%";
    }

    // Jalankan satu kali saat halaman dimuat (untuk keperluan mode Edit & state Toggle)
    window.onload = function() {
        hitungLive();

        // 🚀 BACA MEMORI LOCALSTORAGE UNTUK TOGGLE PANEL
        let formState = localStorage.getItem('stockExFormState');
        let content = document.getElementById('formCollapsibleContent');
        let icon = document.getElementById('formToggleIcon');
        
        <?php if ($is_edit): ?>
            content.style.display = 'block';
            icon.innerText = '▲ SEDANG MODE EDIT';
        <?php else: ?>
            if (formState === 'open') {
                content.style.display = 'block';
                icon.innerText = '▲ KLIK UNTUK TUTUP PANEL';
            } else {
                content.style.display = 'none';
                icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
            }
        <?php endif; ?>
    };

    function konfirmasiHapus(id, pesan) {
        if (confirm(pesan)) {
            document.getElementById('inputHapusId').value = id;
            document.getElementById('formHapusGlobal').submit();
        }
    }
</script>
<?php require_once 'footer.php'; ?>
