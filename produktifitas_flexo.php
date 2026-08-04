<?php
require_once 'auth.php';
require_akses('prod_flexo');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
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
$id_edit = ''; $tanggal = date('Y-m-d');
$inline_jam = 0; $inline_pcs = 0;
$stacker_jam = 0; $stacker_pcs = 0;
$sauto_jam = 0; $sauto_pcs = 0;
$glue_jam = 0; $glue_pcs = 0;

//  AUTO-CREATE & ALTER TABEL DATABASE
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_flexo_prod (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        inline_jam DECIMAL(5,2) DEFAULT 0,
        inline_dt INT DEFAULT 0,
        inline_pcs INT DEFAULT 0,
        stacker_jam DECIMAL(5,2) DEFAULT 0,
        stacker_dt INT DEFAULT 0,
        stacker_pcs INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $cek_kolom1 = $pdo->query("SHOW COLUMNS FROM db_flexo_prod LIKE 'stitch_auto_jam'");
    if ($cek_kolom1->rowCount() == 0) {
        $pdo->exec("ALTER TABLE db_flexo_prod ADD COLUMN stitch_auto_jam DECIMAL(5,2) DEFAULT 0 AFTER stacker_pcs");
        $pdo->exec("ALTER TABLE db_flexo_prod ADD COLUMN stitch_auto_dt INT DEFAULT 0 AFTER stitch_auto_jam");
        $pdo->exec("ALTER TABLE db_flexo_prod ADD COLUMN stitch_auto_pcs INT DEFAULT 0 AFTER stitch_auto_dt");
    }
    
    //  INJEKSI KOLOM MESIN GLUE
    $cek_kolom_glue = $pdo->query("SHOW COLUMNS FROM db_flexo_prod LIKE 'glue_jam'");
    if ($cek_kolom_glue->rowCount() == 0) {
        $pdo->exec("ALTER TABLE db_flexo_prod ADD COLUMN glue_jam DECIMAL(5,2) DEFAULT 0");
        $pdo->exec("ALTER TABLE db_flexo_prod ADD COLUMN glue_dt INT DEFAULT 0");
        $pdo->exec("ALTER TABLE db_flexo_prod ADD COLUMN glue_pcs INT DEFAULT 0");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS master_setting_flexo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        spek_inline INT DEFAULT 180,
        spek_stacker INT DEFAULT 120
    )");

    $cek_kolom2 = $pdo->query("SHOW COLUMNS FROM master_setting_flexo LIKE 'spek_stitch_auto'");
    if ($cek_kolom2->rowCount() == 0) {
        $pdo->exec("ALTER TABLE master_setting_flexo ADD COLUMN spek_stitch_auto INT DEFAULT 80 AFTER spek_stacker");
    }
    
    //  INJEKSI KOLOM SPEK MESIN GLUE (Default 200)
    $cek_kolom_spek_glue = $pdo->query("SHOW COLUMNS FROM master_setting_flexo LIKE 'spek_glue'");
    if ($cek_kolom_spek_glue->rowCount() == 0) {
        $pdo->exec("ALTER TABLE master_setting_flexo ADD COLUMN spek_glue INT DEFAULT 200");
    }

    $stmt_cek_set = $pdo->query("SELECT COUNT(*) FROM master_setting_flexo");
    if ($stmt_cek_set->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO master_setting_flexo (id, spek_inline, spek_stacker, spek_stitch_auto, spek_glue) VALUES (1, 180, 120, 80, 200)");
    }
} catch (PDOException $e) {}

// Tarik Data spek Mesin Terbaru
$row_spek = $pdo->query("SELECT * FROM master_setting_flexo LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$spek_inline = $row_spek['spek_inline'] ?? 180;
$spek_stacker = $row_spek['spek_stacker'] ?? 120;
$spek_sauto = $row_spek['spek_stitch_auto'] ?? 80;
$spek_glue = $row_spek['spek_glue'] ?? 200;

//  LOGIKA UPDATE SPEK MESIN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_spek']) && $user_role != 'Viewer') {
    $n_inline = intval($_POST['spek_inline']);
    $n_stacker = intval($_POST['spek_stacker']);
    $n_sauto = intval($_POST['spek_stitch_auto']);
    $n_glue = intval($_POST['spek_glue']);
    $pdo->prepare("UPDATE master_setting_flexo SET spek_inline=?, spek_stacker=?, spek_stitch_auto=?, spek_glue=? WHERE id=1")->execute([$n_inline, $n_stacker, $n_sauto, $n_glue]);
    catatLog($pdo, $user_aktif, "Mengubah Target spek Mesin (IN:$n_inline, ST:$n_stacker, SA:$n_sauto, GLUE:$n_glue).", "");
    header("Location: produktifitas_flexo.php?pesan=spek_sukses"); exit();
}

//  LOGIKA HAPUS DATA AMAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id']) && $user_role != 'Viewer') {
    $id = intval($_POST['hapus_id']);
    $stmt_tgl = $pdo->prepare("SELECT tanggal FROM db_flexo_prod WHERE id=?"); $stmt_tgl->execute([$id]);
    $tgl_dihapus = date('d/m/Y', strtotime($stmt_tgl->fetchColumn()));

    $stmt = $pdo->prepare("DELETE FROM db_flexo_prod WHERE id = ?");
    if ($stmt->execute([$id])) {
        catatLog($pdo, $user_aktif, "Menghapus data produktifitas Mesin tanggal $tgl_dihapus.", "");
        header("Location: produktifitas_flexo.php?pesan=hapus_sukses"); exit();
    }
}

// LOGIKA TANGKAP DATA EDIT
if (isset($_GET['edit']) && $user_role != 'Viewer') {
    $is_edit = true; $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM db_flexo_prod WHERE id = ?"); $stmt->execute([$id_edit]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $tanggal = $row['tanggal'];
        $inline_jam = $row['inline_jam']; $inline_pcs = $row['inline_pcs'];
        $stacker_jam = $row['stacker_jam']; $stacker_pcs = $row['stacker_pcs'];
        $sauto_jam = $row['stitch_auto_jam']; $sauto_pcs = $row['stitch_auto_pcs'];
        $glue_jam = $row['glue_jam']; $glue_pcs = $row['glue_pcs'];
    }
}

//  LOGIKA SIMPAN & AUTO-CALCULATE DOWNTIME
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_flexo']) && $user_role != 'Viewer') {
    $post_id = $_POST['id_edit'];
    $p_tgl = $_POST['tanggal'];
    
    // TANGKAP INPUTAN OPERATOR (Hanya Jam & PCS)
    $p_ij = floatval($_POST['inline_jam']); $p_ipcs = intval($_POST['inline_pcs']);
    $p_sj = floatval($_POST['stacker_jam']); $p_spcs = intval($_POST['stacker_pcs']);
    $p_sajam = floatval($_POST['sauto_jam']); $p_sapcs = intval($_POST['sauto_pcs']);
    $p_gluejam = floatval($_POST['glue_jam']); $p_gluepcs = intval($_POST['glue_pcs']);

    // =========================================================================
    //  RUMUS KECERDASAN BUATAN: MENGHITUNG DOWNTIME OTOMATIS (4 MESIN)
    // =========================================================================
    $aktual_inline_mnt = ($spek_inline > 0) ? ($p_ipcs / $spek_inline) : 0;
    $p_idt = round(($p_ij * 60) - $aktual_inline_mnt);
    if ($p_idt < 0) $p_idt = 0; 

    $aktual_stacker_mnt = ($spek_stacker > 0) ? ($p_spcs / $spek_stacker) : 0;
    $p_sdt = round(($p_sj * 60) - $aktual_stacker_mnt);
    if ($p_sdt < 0) $p_sdt = 0; 

    $aktual_sauto_mnt = ($spek_sauto > 0) ? ($p_sapcs / $spek_sauto) : 0;
    $p_sadt = round(($p_sajam * 60) - $aktual_sauto_mnt);
    if ($p_sadt < 0) $p_sadt = 0; 

    $aktual_glue_mnt = ($spek_glue > 0) ? ($p_gluepcs / $spek_glue) : 0;
    $p_gluedt = round(($p_gluejam * 60) - $aktual_glue_mnt);
    if ($p_gluedt < 0) $p_gluedt = 0; 

    // Validasi Duplikat
    if (empty($post_id)) {
        $cek = $pdo->prepare("SELECT COUNT(*) FROM db_flexo_prod WHERE tanggal = ?"); $cek->execute([$p_tgl]);
        if ($cek->fetchColumn() > 0) { header("Location: produktifitas_flexo.php?pesan=gagal_duplikat"); exit(); }
    }

    if (!empty($post_id)) {
        $stmt = $pdo->prepare("UPDATE db_flexo_prod SET tanggal=?, inline_jam=?, inline_dt=?, inline_pcs=?, stacker_jam=?, stacker_dt=?, stacker_pcs=?, stitch_auto_jam=?, stitch_auto_dt=?, stitch_auto_pcs=?, glue_jam=?, glue_dt=?, glue_pcs=? WHERE id=?");
        $stmt->execute([$p_tgl, $p_ij, $p_idt, $p_ipcs, $p_sj, $p_sdt, $p_spcs, $p_sajam, $p_sadt, $p_sapcs, $p_gluejam, $p_gluedt, $p_gluepcs, $post_id]);
        catatLog($pdo, $user_aktif, "Mengupdate laporan Mesin tanggal " . date('d/m/Y', strtotime($p_tgl)) . " (Auto-DT 4 Mesin).", "");
        header("Location: produktifitas_flexo.php?pesan=edit_sukses"); exit();
    } else {
        $stmt = $pdo->prepare("INSERT INTO db_flexo_prod (tanggal, inline_jam, inline_dt, inline_pcs, stacker_jam, stacker_dt, stacker_pcs, stitch_auto_jam, stitch_auto_dt, stitch_auto_pcs, glue_jam, glue_dt, glue_pcs) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$p_tgl, $p_ij, $p_idt, $p_ipcs, $p_sj, $p_sdt, $p_spcs, $p_sajam, $p_sadt, $p_sapcs, $p_gluejam, $p_gluedt, $p_gluepcs]);
        catatLog($pdo, $user_aktif, "Merekam laporan Mesin tanggal " . date('d/m/Y', strtotime($p_tgl)) . " (Auto-DT 4 Mesin).", "");
        header("Location: produktifitas_flexo.php?pesan=tambah_sukses"); exit();
    }
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'tambah_sukses') $pesan = "<div class='alert alert-success'> Berhasil: Laporan Produktifitas disimpan! Downtime 4 mesin telah dihitung otomatis.</div>";
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success'> Berhasil: Perubahan data diperbarui! Downtime otomatis disesuaikan.</div>";
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-success'> Data laporan telah dihapus permanen!</div>";
    if ($_GET['pesan'] == 'spek_sukses') $pesan = "<div class='alert alert-success'> MANTAP! spek Target Produksi (PCS/Mnt) berhasil diperbarui! Perhitungan selanjutnya akan mengikuti nilai baru ini.</div>";
    if ($_GET['pesan'] == 'gagal_duplikat') $pesan = "<div class='alert alert-danger'> Gagal: Laporan untuk tanggal tersebut sudah ada! Gunakan fitur edit di tabel.</div>";
}

//  TARIK DATA UNTUK SEMUA TABEL
$stmt_flexo = $pdo->prepare("SELECT * FROM db_flexo_prod WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal ASC");
$stmt_flexo->execute([$bulan_filter, $tahun_filter]);
$data_flexo = $stmt_flexo->fetchAll(PDO::FETCH_ASSOC);

// Kalkulasi Total untuk Harian
$tot_ij = 0; $tot_idt = 0; $tot_ipcs = 0;
$tot_sj = 0; $tot_sdt = 0; $tot_spcs = 0;
$tot_sajam = 0; $tot_sadt = 0; $tot_sapcs = 0;
$tot_gluejam = 0; $tot_gluedt = 0; $tot_gluepcs = 0;

foreach ($data_flexo as $row) {
    $tot_ij += $row['inline_jam']; $tot_idt += $row['inline_dt']; $tot_ipcs += $row['inline_pcs'];
    $tot_sj += $row['stacker_jam']; $tot_sdt += $row['stacker_dt']; $tot_spcs += $row['stacker_pcs'];
    $tot_sajam += $row['stitch_auto_jam']; $tot_sadt += $row['stitch_auto_dt']; $tot_sapcs += $row['stitch_auto_pcs'];
    $tot_gluejam += $row['glue_jam']; $tot_gluedt += $row['glue_dt']; $tot_gluepcs += $row['glue_pcs'];
}

$page_title = "Produktifitas Mesin  H2 BASE ERP";
$active_page = "flexo";
require 'header.php';
?>

<style>
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    
    /*  GRID KANAN KIRI ATAS BAWAH (2x2) */
    .form-grid-2x2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
    @media (max-width: 768px) { .form-grid-2x2 { grid-template-columns: 1fr; } }
    
    .form-group { display: flex; flex-direction: column; min-width: 140px; }
    .form-group label { margin-bottom: 6px; font-weight: 600; font-size: 12px; color: #475569; text-transform: uppercase; }
    input, SELECT { padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; background: #f8fafc; outline: none; transition: 0.2s; box-sizing: border-box; width: 100%; }
    input:focus, SELECT:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); background: #ffffff;}
    
    /*  PREMIUM BUTTONS */
    .btn-group { display: flex; gap: 15px; justify-content: flex-end; align-items: center; margin-top: 15px; }
    .btn-submit-modern {
        background: #10b981 !important; color: #ffffff !important; border: none !important; padding: 12px 28px !important; border-radius: 8px !important; font-size: 14px !important; font-weight: 800 !important; cursor: pointer !important; transition: all 0.2s ease-in-out !important; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.1) !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;
    }
    .btn-submit-modern:hover { background: #059669 !important; transform: translateY(-1px) !important; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2) !important; }

    .btn-batal-modern {
        background: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important; padding: 11px 24px !important; text-decoration: none !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; text-align: center !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; transition: all 0.2s !important;
    }
    .btn-batal-modern:hover { background: #f1f5f9 !important; color: #0f172a !important; }
    
    /* DESAIN TABEL */
    .table-responsive { background: white; border-radius: 8px; border: 1px solid #cbd5e1; overflow-x: auto; max-height: 600px; margin-bottom: 20px; }
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12px; color: #1e293b; white-space: nowrap; min-width: 1000px; } 
    .table-premium th, .table-premium td { padding: 10px 12px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; text-align: center;}
    .table-premium th { color: #ffffff; font-weight: 600; position: sticky; top: 0; z-index: 10; text-transform: uppercase; font-size: 11px; border-bottom: 1px solid #334155;}
    .table-premium thead tr:nth-child(2) th { top: 37px; }
    .table-premium tbody tr:nth-child(even) td { background-color: #f8fafc; }
    .table-premium tbody tr:hover td { background-color: #f1f5f9 !important; }

    .stk-1 { position: sticky; left: 0; z-index: 5; background: #fff; border-right: 2px solid #cbd5e1 !important; width: 60px;}
    .table-premium thead tr:nth-child(1) th.stk-1 { background: #0f172a; z-index: 12;}
    .table-premium thead tr:nth-child(2) th.stk-1 { background: #1e293b; z-index: 12;}
    .table-premium tbody tr:nth-child(even) td.stk-1 { background: #f8fafc; }

    .btn-aksi-edit { display: inline-block; background: #e0f2fe; color: #0284c7; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 700; border: 1px solid #bae6fd; }
    .btn-aksi-hapus { display: inline-block; background: #fee2e2; color: #dc2626; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 700; border: 1px solid #fecaca; margin-left: 4px; }
    
    .total-row td { background-color: #e2e8f0 !important; font-weight: 800; color: #0f172a; border-top: 2px solid #0f172a; position: sticky; bottom: 0; z-index: 9;}

    /* LACI PENGATURAN */
    .shift-settings { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; margin-bottom: 24px; overflow: hidden; transition: 0.3s;}
    .shift-header { padding: 15px 20px; background: #f1f5f9; cursor: pointer; font-weight: 800; color: #0f172a; display: flex; justify-content: space-between; align-items: center;}
    .shift-header:hover { background: #e2e8f0; }
    .shift-content { padding: 20px; display: none; border-top: 1px solid #cbd5e1; }
    .shift-content.open { display: block; }

    /*  STYLE TOGGLE FORM (BARU DITAMBAHKAN) */
    .form-toggle-header { user-SELECT: none; transition: background 0.2s; padding: 12px 16px; border-radius: 8px; margin: -10px -10px 15px -10px; cursor: pointer; }
    .form-toggle-header:hover { background: #f8fafc; }
    
    /*  ISO 9001 PRINT CSS */
    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        body { background: white !important; font-family: 'Inter', sans-serif, 'Times New Roman' !important; color: black; margin: 0; padding: 0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        
        /* HIDE NON-PRINT ELEMENTS */
        .sidebar, .topbar-desktop, .mobile-header, .filter-box, .btn-submit-modern, .btn-excel-modern, .ajax-dropdown-container, .alert, form, .form-toggle-header, .shift-settings, h2, h3, .btn-group, span[style*="background:#fef3c7"] { display: none !important; }
        
        /* RESET CONTAINER MARGINS */
        .container { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .card { border: none !important; box-shadow: none !important; padding: 0 !important; background: transparent !important; margin: 0 !important; margin-bottom: 20px !important; }
        .table-responsive { max-height: none !important; box-shadow: none !important; border: none !important; overflow: visible !important; margin-bottom: 30px !important; }
        
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
        .table-premium thead tr:first-child th[data-noexport="true"], .table-premium tbody td[data-noexport="true"] { display: none !important; }
        .table-premium tr { page-break-inside: avoid; }
        .stk-1, .stk-2, .total-row td { position: static !important; }
        
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
<div class="card" <?= $is_edit ? 'style="border: 2px solid #0ea5e9; background: #f0f9ff;"' : '' ?>>
    <!--  HEADER TOGGLE FORM -->
    <div class="form-toggle-header" onclick="toggleFormInput()">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid <?= $is_edit ? '#bae6fd' : '#f1f5f9' ?>; padding-bottom: 12px;">
            <h2 id="formTitle" style="margin:0; font-size: 18px; color: #fff; background: <?= $is_edit ? 'linear-gradient(135deg, #f59e0b, #ea580c)' : 'linear-gradient(135deg, #0ea5e9, #2563eb)' ?>; padding: 10px 15px; border-radius: 8px;">
                <?= $is_edit ? "📝 Edit Laporan Mesin (Auto-DT)" : "📝 Form Input Produktifitas Mesin (Auto-DT)" ?>
            </h2>
            <span id="formToggleIcon" style="font-size:13px; font-weight:900; color:<?= $is_edit ? '#0284c7' : '#475569' ?>; background:<?= $is_edit ? '#e0f2fe' : '#f8fafc' ?>; padding:4px 10px; border-radius:6px; border:1px solid <?= $is_edit ? '#bae6fd' : '#cbd5e1' ?>;">
                 ↕️ KLIK UNTUK BUKA/TUTUP FORM
            </span>
        </div>
    </div>

    <!--  WRAPPER FORM YANG BISA DI-TOGGLE -->
    <div id="formCollapsibleContent" style="display: none; margin-top: 15px;">
        <form action="" method="POST" autocomplete="off">
            <input type="hidden" name="id_edit" value="<?= $id_edit ?>">
            
            <div style="margin-bottom: 20px; width: 300px; max-width: 100%;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px; text-transform: uppercase;">Tanggal Operasional</label>
                <input type="date" name="tanggal" value="<?= $tanggal ?>" required>
            </div>

            <div class="form-grid-2x2">
                
                <!-- KOTAK INLINE (Biru) -->
                <div style="background: #f0f9ff; border: 1px solid #bae6fd; padding: 20px; border-radius: 10px; border-top: 4px solid #0ea5e9;">
                    <h3 style="color: #0284c7; margin-top:0; border-bottom: 1px solid #bae6fd; padding-bottom: 10px; display:flex; justify-content:space-between; align-items:center;">
                         FLEXO INLINE
                        <span style="font-size:10px; background:#0ea5e9; color:white; padding:4px 8px; border-radius:12px;">spek: <?= $spek_inline ?>/Mnt</span>
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label>Jam Kerja (Jam)</label><input type="number" step="0.01" name="inline_jam" value="<?= $inline_jam ?>" required></div>
                        <div class="form-group"><label>Output (PCS)</label><input type="number" name="inline_pcs" value="<?= $inline_pcs ?>" required style="border-color: #0ea5e9; font-weight: bold; background:#ffffff;"></div>
                    </div>
                    <div style="margin-top: 10px; font-size: 10px; color: #0284c7; font-weight: 600; text-align: right;"> Downtime dihitung otomatis.</div>
                </div>

                <!-- KOTAK STACKER (Hijau) -->
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 20px; border-radius: 10px; border-top: 4px solid #16a34a;">
                    <h3 style="color: #166534; margin-top:0; border-bottom: 1px solid #bbf7d0; padding-bottom: 10px; display:flex; justify-content:space-between; align-items:center;">
                         FLEXO STACKER
                        <span style="font-size:10px; background:#16a34a; color:white; padding:4px 8px; border-radius:12px;">spek: <?= $spek_stacker ?>/Mnt</span>
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label>Jam Kerja (Jam)</label><input type="number" step="0.01" name="stacker_jam" value="<?= $stacker_jam ?>" required></div>
                        <div class="form-group"><label>Output (PCS)</label><input type="number" name="stacker_pcs" value="<?= $stacker_pcs ?>" required style="border-color: #16a34a; font-weight: bold; background:#ffffff;"></div>
                    </div>
                    <div style="margin-top: 10px; font-size: 10px; color: #15803d; font-weight: 600; text-align: right;"> Downtime dihitung otomatis.</div>
                </div>

                <!--  KOTAK STITCH AUTO (Ungu) -->
                <div style="background: #faf5ff; border: 1px solid #e9d5ff; padding: 20px; border-radius: 10px; border-top: 4px solid #9333ea;">
                    <h3 style="color: #7e22ce; margin-top:0; border-bottom: 1px solid #e9d5ff; padding-bottom: 10px; display:flex; justify-content:space-between; align-items:center;">
                         STITCH AUTO
                        <span style="font-size:10px; background:#9333ea; color:white; padding:4px 8px; border-radius:12px;">spek: <?= $spek_sauto ?>/Mnt</span>
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label>Jam Kerja (Jam)</label><input type="number" step="0.01" name="sauto_jam" value="<?= $sauto_jam ?>" required></div>
                        <div class="form-group"><label>Output (PCS)</label><input type="number" name="sauto_pcs" value="<?= $sauto_pcs ?>" required style="border-color: #9333ea; font-weight: bold; background:#ffffff;"></div>
                    </div>
                    <div style="margin-top: 10px; font-size: 10px; color: #7e22ce; font-weight: 600; text-align: right;"> Downtime dihitung otomatis.</div>
                </div>

                <!--  KOTAK GLUE (Merah Muda/Rose) -->
                <div style="background: #fff1f2; border: 1px solid #fecdd3; padding: 20px; border-radius: 10px; border-top: 4px solid #e11d48;">
                    <h3 style="color: #be123c; margin-top:0; border-bottom: 1px solid #fecdd3; padding-bottom: 10px; display:flex; justify-content:space-between; align-items:center;">
                         MESIN GLUE
                        <span style="font-size:10px; background:#e11d48; color:white; padding:4px 8px; border-radius:12px;">spek: <?= $spek_glue ?>/Mnt</span>
                    </h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group"><label>Jam Kerja (Jam)</label><input type="number" step="0.01" name="glue_jam" value="<?= $glue_jam ?>" required></div>
                        <div class="form-group"><label>Output (PCS)</label><input type="number" name="glue_pcs" value="<?= $glue_pcs ?>" required style="border-color: #e11d48; font-weight: bold; background:#ffffff;"></div>
                    </div>
                    <div style="margin-top: 10px; font-size: 10px; color: #be123c; font-weight: 600; text-align: right;"> Downtime dihitung otomatis.</div>
                </div>

            </div>
            
            <div class="btn-group" style="margin-top: 20px;">
                <?php if($is_edit): ?><a href="produktifitas_flexo.php" class="btn-batal-modern">❌ Batal Edit</a><?php endif; ?>
                <button type="submit" name="simpan_flexo" class="btn-submit-modern" style="background:#10b981;"><?= $is_edit ? " simpan Perubahan & Hitung Ulang DT" : " simpan & Kalkulasi Otomatis" ?></button>
            </div>
        </form>
    </div>
</div>

<!--  ACCORDION LACI PENGATURAN TARGET / SPEK 4 MESIN -->
<div class="shift-settings">
    <div class="shift-header" onclick="document.getElementById('setspekContent').classList.toggle('open')">
        <span style="display:flex; align-items:center; gap:8px;">⚙️ PENGATURAN SPEK / TARGET 4 MESIN 
            <span style="font-size:9px; background:#0f172a; color:#fff; padding:2px 6px; border-radius:4px;">Klik untuk ubah rumus Downtime</span>
        </span>
        <span></span>
    </div>
    <div class="shift-content" id="setspekContent">
        <p style="font-size: 12px; color: #475569; margin-top: 0;">Nilai <strong>PCS/Menit</strong> digunakan oleh sistem AI untuk memotong <strong>Downtime</strong> operator. Jika target berubah, silakan ubah angkanya di sini.</p>
        
        <form action="" method="POST" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="min-width: 150px;">
                <label style="color:#0284c7;">INLINE (PCS/Mnt)</label>
                <input type="number" name="spek_inline" value="<?= $spek_inline ?>" required style="font-size: 16px; font-weight: bold; color: #0284c7; text-align: center;">
            </div>
            <div class="form-group" style="min-width: 150px;">
                <label style="color:#166534;">STACKER (PCS/Mnt)</label>
                <input type="number" name="spek_stacker" value="<?= $spek_stacker ?>" required style="font-size: 16px; font-weight: bold; color: #166534; text-align: center;">
            </div>
            <div class="form-group" style="min-width: 150px;">
                <label style="color:#7e22ce;">ST. AUTO (PCS/Mnt)</label>
                <input type="number" name="spek_stitch_auto" value="<?= $spek_sauto ?>" required style="font-size: 16px; font-weight: bold; color: #7e22ce; text-align: center;">
            </div>
            <div class="form-group" style="min-width: 150px;">
                <label style="color:#e11d48;">GLUE (PCS/Mnt)</label>
                <input type="number" name="spek_glue" value="<?= $spek_glue ?>" required style="font-size: 16px; font-weight: bold; color: #e11d48; text-align: center;">
            </div>
            <button type="submit" name="update_spek" class="btn-submit-modern" style="background: #0f172a;"> UPDATE spek</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ============================================== -->
<!--  BAGIAN 1: ANALISIS PRODUKTIFITAS HARIAN -->
<!-- ============================================== -->
<div class="card" style="padding-bottom: 20px; margin-bottom: 24px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
        <h2 style="margin:0; border:none; padding:0;"> 📊 Laporan Harian (Bulan <?= $nama_bulan ?> <?= $tahun_filter ?>)</h2>
        
        <div style="display:flex; gap:10px; align-items:center;">
            <form method="GET" action="" style="display: flex; gap: 8px; align-items: center;">
                <SELECT name="bulan" style="padding: 6px 10px; font-size: 12px; border-radius: 6px; border: 1px solid #cbd5e1; width: auto;">
                    <?php foreach ($nama_bulan_list as $m_code => $m_name): ?>
                        <option value="<?= $m_code ?>" <?= $m_code == $bulan_filter ? 'selected' : '' ?>><?= $m_name ?></option>
                    <?php endforeach; ?>
                </SELECT>
                <SELECT name="tahun" style="padding: 6px 10px; font-size: 12px; border-radius: 6px; border: 1px solid #cbd5e1; width: auto;">
                    <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $tahun_filter ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </SELECT>
                <button type="submit" class="btn-submit-modern" style="padding: 6px 12px; font-size: 12px; background:#0f172a;"> 🔍 Cari</button>
                <button type="button" onclick="window.print()" class="btn-submit-modern" style="background: #eab308; color: #fff; padding: 6px 12px; font-size: 11px;">
                      🖨️ Cetak PDF (ISO 9001)
                </button>
            </form>
        </div>
    </div>

    <!--  TABEL HARIAN 1A: FLEXO INLINE & STACKER -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px; margin-top:20px;">
        <h3 style="color:#0284c7; margin:0;"> 🏭 Mesin Flexo (Inline & stacker)</h3>
        <button type="button" onclick="exportKeExcel('tabelHarianFlexo', 'Harian_Flexo')" class="btn-submit-modern" style="background: #16a34a; padding: 6px 12px; font-size: 11px;">
              📥 Export Flexo
        </button>
    </div>

    <!-- KOP SURAT ISO 9001 (CETAK ONLY) -->
    <div class="iso-header">
        <table>
            <tr>
                <td rowspan="3" style="width: 20%;"><img src="logo.png" class="iso-logo" alt="Logo"></td>
                <td rowspan="3" style="width: 50%;" class="iso-title">LAPORAN PRODUKTIVITAS FLEXO<br><span style="font-size:12px; font-weight:normal; letter-spacing:0;">Periode: <?= $nama_bulan ?> <?= $tahun_filter ?></span></td>
                <td style="width: 30%;" class="iso-doc">No. Dok: FR-PRD-01</td>
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
        <table class="table-premium" id="tabelHarianFlexo">
            <thead>
                <tr>
                    <th rowspan="2" class="stk-1" style="background: #0f172a;">TANGGAL</th>
                    <th colspan="5" style="background: #0284c7;"> FLEXO INLINE</th>
                    <th colspan="5" style="background: #15803d;"> FLEXO STACKER</th>
                    <th rowspan="2" style="background: #0f172a; width: 100px;" data-noexport="true">AKSI</th>
                </tr>
                <tr>
                    <!-- INLINE -->
                    <th style="background: #0ea5e9;">Jam Kerja</th><th style="background: #0ea5e9;">DT (Mnt)</th><th style="background: #0ea5e9;">PCS Output</th><th style="background: #0369a1; color:#fff;">% DT Inline</th><th style="background: #0369a1; color:#fff;">Prod. (PCS/Mnt)</th>
                    <!-- STACKER -->
                    <th style="background: #16a34a;">Jam Kerja</th><th style="background: #16a34a;">DT (Mnt)</th><th style="background: #16a34a;">PCS Output</th><th style="background: #166534; color:#fff;">% DT stacker</th><th style="background: #166534; color:#fff;">Prod. (PCS/Mnt)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (count($data_flexo) > 0): 
                    foreach ($data_flexo as $row): 
                        $ij = $row['inline_jam']; $idt = $row['inline_dt']; $ipcs = $row['inline_pcs'];
                        $sj = $row['stacker_jam']; $sdt = $row['stacker_dt']; $spcs = $row['stacker_pcs'];

                        $i_dt_pct = ($ij > 0) ? ($idt / ($ij * 60)) * 100 : 0;
                        $i_prod   = ($ij > 0) ? ($ipcs / ($ij * 60)) : 0;
                        $s_dt_pct = ($sj > 0) ? ($sdt / ($sj * 60)) * 100 : 0;
                        $s_prod   = ($sj > 0) ? ($spcs / ($sj * 60)) : 0;
                ?>
                    <tr>
                        <td class="stk-1" style="font-weight: 800; color: #0f172a; font-size: 13px;"><?= date('d', strtotime($row['tanggal'])) ?></td>
                        <td><?= number_format($ij, 0, ',', '.') ?></td>
                        <td style="color:#ef4444; font-weight:bold;"><?= number_format($idt, 0, ',', '.') ?></td>
                        <td style="font-weight:bold; color:#0284c7;"><?= number_format($ipcs, 0, ',', '.') ?></td>
                        <td style="background:#f0f9ff; color:#0369a1; font-weight:bold;"><?= number_format($i_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="background:#e0f2fe; color:#0284c7; font-weight:900;"><?= number_format($i_prod, 0, ',', '.') ?></td>

                        <td><?= number_format($sj, 0, ',', '.') ?></td>
                        <td style="color:#ef4444; font-weight:bold;"><?= number_format($sdt, 0, ',', '.') ?></td>
                        <td style="font-weight:bold; color:#15803d;"><?= number_format($spcs, 0, ',', '.') ?></td>
                        <td style="background:#f0fdf4; color:#166534; font-weight:bold;"><?= number_format($s_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="background:#dcfce7; color:#15803d; font-weight:900;"><?= number_format($s_prod, 0, ',', '.') ?></td>

                        <td data-noexport="true">
                            <?php if ($user_role != 'Viewer'): ?>
                                <a href="produktifitas_flexo.php?edit=<?= $row['id'] ?>&bulan=<?= $bulan_filter ?>&tahun=<?= $tahun_filter ?>" class="btn-aksi-edit">Edit</a>
                                <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Hapus laporan tanggal <?= date('d/m/Y', strtotime($row['tanggal'])) ?>?')" class="btn-aksi-hapus">Hapus</a>
                            <?php else: ?>
                                <span style="font-size:10px; color:#94a3b8;">Akses Terbatas</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; 
                    
                    // Kalkulasi Total Grand Flexo
                    $tot_i_dt_pct = ($tot_ij > 0) ? ($tot_idt / ($tot_ij * 60)) * 100 : 0;
                    $tot_i_prod   = ($tot_ij > 0) ? ($tot_ipcs / ($tot_ij * 60)) : 0;
                    $tot_s_dt_pct = ($tot_sj > 0) ? ($tot_sdt / ($tot_sj * 60)) * 100 : 0;
                    $tot_s_prod   = ($tot_sj > 0) ? ($tot_spcs / ($tot_sj * 60)) : 0;
                ?>
                    <tr class="total-row">
                        <td class="stk-1" style="text-align:right !important; padding-right:15px; font-size:11px;">TOTAL</td>
                        <td><?= number_format($tot_ij, 0, ',', '.') ?></td>
                        <td><?= number_format($tot_idt, 0, ',', '.') ?></td>
                        <td style="color:#0284c7;"><?= number_format($tot_ipcs, 0, ',', '.') ?></td>
                        <td style="color:#0369a1;"><?= number_format($tot_i_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="color:#0284c7; font-size:14px;"><?= number_format($tot_i_prod, 0, ',', '.') ?></td>

                        <td><?= number_format($tot_sj, 0, ',', '.') ?></td>
                        <td><?= number_format($tot_sdt, 0, ',', '.') ?></td>
                        <td style="color:#15803d;"><?= number_format($tot_spcs, 0, ',', '.') ?></td>
                        <td style="color:#166534;"><?= number_format($tot_s_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="color:#15803d; font-size:14px;"><?= number_format($tot_s_prod, 0, ',', '.') ?></td>
                        <td data-noexport="true">-</td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="12" style="padding: 40px; color: #94a3b8;">Belum ada laporan Flexo untuk bulan ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <hr style="border: 1px dashed #cbd5e1; margin: 30px 0;">

    <!--  TABEL HARIAN 1B: STITCH AUTO & GLUE -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
        <h3 style="color:#7e22ce; margin:0;"> Mesin Auto &  Mesin GLUE</h3>
        <button type="button" onclick="exportKeExcel('tabelHarianAutoGlue', 'Harian_Auto_GLUE')" class="btn-submit-modern" style="background: #16a34a; padding: 6px 12px; font-size: 11px;">
             Export Auto & GLUE
        </button>
    </div>
    <div class="table-responsive">
        <table class="table-premium" id="tabelHarianAutoGlue">
            <thead>
                <tr>
                    <th rowspan="2" class="stk-1" style="background: #0f172a;">TANGGAL</th>
                    <th colspan="5" style="background: #7e22ce;"> STITCH AUTO</th>
                    <th colspan="5" style="background: #e11d48;"> MESIN GLUE</th>
                    <th rowspan="2" style="background: #0f172a; width: 100px;" data-noexport="true">AKSI</th>
                </tr>
                <tr>
                    <!-- STITCH AUTO -->
                    <th style="background: #9333ea;">Jam Kerja</th><th style="background: #9333ea;">DT (Mnt)</th><th style="background: #9333ea;">PCS Output</th><th style="background: #6d28d9; color:#fff;">% DT Auto</th><th style="background: #6d28d9; color:#fff;">Prod. (PCS/Mnt)</th>
                    <!-- GLUE -->
                    <th style="background: #f43f5e;">Jam Kerja</th><th style="background: #f43f5e;">DT (Mnt)</th><th style="background: #f43f5e;">PCS Output</th><th style="background: #be123c; color:#fff;">% DT Glue</th><th style="background: #be123c; color:#fff;">Prod. (PCS/Mnt)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (count($data_flexo) > 0): 
                    foreach ($data_flexo as $row): 
                        $saj = $row['stitch_auto_jam']; $sadt = $row['stitch_auto_dt']; $sapcs = $row['stitch_auto_pcs'];
                        $gluej = $row['glue_jam']; $gluedt = $row['glue_dt']; $gluepcs = $row['glue_pcs'];

                        $sa_dt_pct = ($saj > 0) ? ($sadt / ($saj * 60)) * 100 : 0;
                        $sa_prod   = ($saj > 0) ? ($sapcs / ($saj * 60)) : 0;
                        $glue_dt_pct = ($gluej > 0) ? ($gluedt / ($gluej * 60)) * 100 : 0;
                        $glue_prod   = ($gluej > 0) ? ($gluepcs / ($gluej * 60)) : 0;
                ?>
                    <tr>
                        <td class="stk-1" style="font-weight: 800; color: #0f172a; font-size: 13px;"><?= date('d', strtotime($row['tanggal'])) ?></td>
                        
                        <!-- STITCH AUTO -->
                        <td><?= number_format($saj, 0, ',', '.') ?></td>
                        <td style="color:#ef4444; font-weight:bold;"><?= number_format($sadt, 0, ',', '.') ?></td>
                        <td style="font-weight:bold; color:#7e22ce;"><?= number_format($sapcs, 0, ',', '.') ?></td>
                        <td style="background:#faf5ff; color:#6d28d9; font-weight:bold;"><?= number_format($sa_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="background:#f3e8ff; color:#7e22ce; font-weight:900;"><?= number_format($sa_prod, 0, ',', '.') ?></td>

                        <!-- GLUE -->
                        <td><?= number_format($gluej, 0, ',', '.') ?></td>
                        <td style="color:#ef4444; font-weight:bold;"><?= number_format($gluedt, 0, ',', '.') ?></td>
                        <td style="font-weight:bold; color:#e11d48;"><?= number_format($gluepcs, 0, ',', '.') ?></td>
                        <td style="background:#fff1f2; color:#be123c; font-weight:bold;"><?= number_format($glue_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="background:#ffe4e6; color:#e11d48; font-weight:900;"><?= number_format($glue_prod, 0, ',', '.') ?></td>

                        <td data-noexport="true">
                            <?php if ($user_role != 'Viewer'): ?>
                                <a href="produktifitas_flexo.php?edit=<?= $row['id'] ?>&bulan=<?= $bulan_filter ?>&tahun=<?= $tahun_filter ?>" class="btn-aksi-edit">Edit</a>
                                <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Hapus laporan tanggal <?= date('d/m/Y', strtotime($row['tanggal'])) ?>?')" class="btn-aksi-hapus">Hapus</a>
                            <?php else: ?>
                                <span style="font-size:10px; color:#94a3b8;">Akses Terbatas</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; 
                    
                    // Kalkulasi Total Grand Auto & GLUE
                    $tot_sa_dt_pct = ($tot_sajam > 0) ? ($tot_sadt / ($tot_sajam * 60)) * 100 : 0;
                    $tot_sa_prod   = ($tot_sajam > 0) ? ($tot_sapcs / ($tot_sajam * 60)) : 0;
                    $tot_glue_dt_pct = ($tot_gluejam > 0) ? ($tot_gluedt / ($tot_gluejam * 60)) * 100 : 0;
                    $tot_glue_prod   = ($tot_gluejam > 0) ? ($tot_gluepcs / ($tot_gluejam * 60)) : 0;
                ?>
                    <tr class="total-row">
                        <td class="stk-1" style="text-align:right !important; padding-right:15px; font-size:11px;">TOTAL</td>
                        
                        <td><?= number_format($tot_sajam, 0, ',', '.') ?></td>
                        <td><?= number_format($tot_sadt, 0, ',', '.') ?></td>
                        <td style="color:#7e22ce;"><?= number_format($tot_sapcs, 0, ',', '.') ?></td>
                        <td style="color:#6d28d9;"><?= number_format($tot_sa_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="color:#7e22ce; font-size:14px;"><?= number_format($tot_sa_prod, 0, ',', '.') ?></td>

                        <td><?= number_format($tot_gluejam, 0, ',', '.') ?></td>
                        <td><?= number_format($tot_gluedt, 0, ',', '.') ?></td>
                        <td style="color:#e11d48;"><?= number_format($tot_gluepcs, 0, ',', '.') ?></td>
                        <td style="color:#be123c;"><?= number_format($tot_glue_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="color:#e11d48; font-size:14px;"><?= number_format($tot_glue_prod, 0, ',', '.') ?></td>
                        <td data-noexport="true">-</td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="12" style="padding: 40px; color: #94a3b8;">Belum ada laporan Mesin Auto & GLUE untuk bulan ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================== -->
<!--  BAGIAN 2: REKAP TOTAL AKUMULASI PER BULAN -->
<!-- ============================================== -->
<div class="card" style="padding-bottom: 20px; margin-bottom: 12px; border-top: 4px solid #f59e0b;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
        <h2 style="margin:0; border:none; padding:0; color: #d97706;"> Rekap Total Akumulasi (Tahun <?= $tahun_filter ?>)</h2>
        <span style="font-size:11px; font-weight:700; color:#64748b; background:#fef3c7; padding:4px 10px; border-radius:4px; border:1px solid #fde68a;">Data Otomatis Management</span>
    </div>

    <?php 
    //  AMBIL DATA 1 TAHUN PENUH
    $stmt_rekap = $pdo->prepare("SELECT MONTH(tanggal) as bln, SUM(inline_jam) as tot_ij, SUM(inline_dt) as tot_idt, SUM(inline_pcs) as tot_ipcs, SUM(stacker_jam) as tot_sj, SUM(stacker_dt) as tot_sdt, SUM(stacker_pcs) as tot_spcs, SUM(stitch_auto_jam) as tot_saj, SUM(stitch_auto_dt) as tot_sadt, SUM(stitch_auto_pcs) as tot_sapcs, SUM(glue_jam) as tot_gluej, SUM(glue_dt) as tot_gluedt, SUM(glue_pcs) as tot_gluepcs FROM db_flexo_prod WHERE YEAR(tanggal) = ? GROUP BY MONTH(tanggal) ORDER BY MONTH(tanggal) ASC");
    $stmt_rekap->execute([$tahun_filter]);
    $data_rekap = $stmt_rekap->fetchAll(PDO::FETCH_ASSOC);

    $rekap_bulan = [];
    foreach ($data_rekap as $row) { $rekap_bulan[$row['bln']] = $row; }

    $gt_ij_yr = 0; $gt_idt_yr = 0; $gt_ipcs_yr = 0; $gt_sj_yr = 0; $gt_sdt_yr = 0; $gt_spcs_yr = 0;
    $gt_saj_yr = 0; $gt_sadt_yr = 0; $gt_sapcs_yr = 0; $gt_gluej_yr = 0; $gt_gluedt_yr = 0; $gt_gluepcs_yr = 0;

    for ($m = 1; $m <= 12; $m++) {
        if (isset($rekap_bulan[$m])) {
            $gt_ij_yr += $rekap_bulan[$m]['tot_ij']; $gt_idt_yr += $rekap_bulan[$m]['tot_idt']; $gt_ipcs_yr += $rekap_bulan[$m]['tot_ipcs'];
            $gt_sj_yr += $rekap_bulan[$m]['tot_sj']; $gt_sdt_yr += $rekap_bulan[$m]['tot_sdt']; $gt_spcs_yr += $rekap_bulan[$m]['tot_spcs'];
            $gt_saj_yr += $rekap_bulan[$m]['tot_saj']; $gt_sadt_yr += $rekap_bulan[$m]['tot_sadt']; $gt_sapcs_yr += $rekap_bulan[$m]['tot_sapcs'];
            $gt_gluej_yr += $rekap_bulan[$m]['tot_gluej']; $gt_gluedt_yr += $rekap_bulan[$m]['tot_gluedt']; $gt_gluepcs_yr += $rekap_bulan[$m]['tot_gluepcs'];
        }
    }
    ?>

    <!--  TABEL BULANAN 2A: FLEXO -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px; margin-top:10px;">
        <h3 style="color:#0284c7; margin:0;"> Rekap Bulanan Flexo</h3>
        <button type="button" onclick="exportKeExcel('tabelBulananFlexo', 'Rekap_Bulanan_Flexo')" class="btn-submit-modern" style="background: #16a34a; padding: 6px 12px; font-size: 11px;">
             📥 Export Flexo
        </button>
    </div>
    <div class="table-responsive">
        <table class="table-premium" id="tabelBulananFlexo">
            <thead>
                <tr>
                    <th rowspan="2" class="stk-1" style="background: #0f172a;">BULAN</th>
                    <th colspan="5" style="background: #0284c7;"> FLEXO INLINE</th>
                    <th colspan="5" style="background: #15803d;"> FLEXO STACKER</th>
                </tr>
                <tr>
                    <th style="background: #0ea5e9;">Jam Kerja</th><th style="background: #0ea5e9;">DT (Mnt)</th><th style="background: #0ea5e9;">PCS Output</th><th style="background: #0369a1; color:#fff;">% DT Inline</th><th style="background: #0369a1; color:#fff;">Prod. (PCS/Mnt)</th>
                    <th style="background: #16a34a;">Jam Kerja</th><th style="background: #16a34a;">DT (Mnt)</th><th style="background: #16a34a;">PCS Output</th><th style="background: #166534; color:#fff;">% DT stacker</th><th style="background: #166534; color:#fff;">Prod. (PCS/Mnt)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                for ($m = 1; $m <= 12; $m++):
                    if (isset($rekap_bulan[$m])):
                        $rij = $rekap_bulan[$m]['tot_ij']; $ridt = $rekap_bulan[$m]['tot_idt']; $ripcs = $rekap_bulan[$m]['tot_ipcs'];
                        $rsj = $rekap_bulan[$m]['tot_sj']; $rsdt = $rekap_bulan[$m]['tot_sdt']; $rspcs = $rekap_bulan[$m]['tot_spcs'];

                        $r_i_dt_pct = ($rij > 0) ? ($ridt / ($rij * 60)) * 100 : 0;
                        $r_i_prod   = ($rij > 0) ? ($ripcs / ($rij * 60)) : 0;
                        $r_s_dt_pct = ($rsj > 0) ? ($rsdt / ($rsj * 60)) * 100 : 0;
                        $r_s_prod   = ($rsj > 0) ? ($rspcs / ($rsj * 60)) : 0;
                        
                        $nama_bln_str = $nama_bulan_list[str_pad($m, 2, '0', STR_PAD_LEFT)];
                ?>
                    <tr>
                        <td class="stk-1" style="font-weight: 800; color: #0f172a; font-size: 11px; text-transform: uppercase;"><?= $nama_bln_str ?></td>
                        <td><?= number_format($rij, 0, ',', '.') ?></td>
                        <td style="color:#ef4444;"><?= number_format($ridt, 0, ',', '.') ?></td>
                        <td style="font-weight:bold; color:#0284c7;"><?= number_format($ripcs, 0, ',', '.') ?></td>
                        <td style="background:#f0f9ff; color:#0369a1; font-weight:bold;"><?= number_format($r_i_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="background:#e0f2fe; color:#0284c7; font-weight:900;"><?= number_format($r_i_prod, 0, ',', '.') ?></td>

                        <td><?= number_format($rsj, 0, ',', '.') ?></td>
                        <td style="color:#ef4444;"><?= number_format($rsdt, 0, ',', '.') ?></td>
                        <td style="font-weight:bold; color:#15803d;"><?= number_format($rspcs, 0, ',', '.') ?></td>
                        <td style="background:#f0fdf4; color:#166534; font-weight:bold;"><?= number_format($r_s_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="background:#dcfce7; color:#15803d; font-weight:900;"><?= number_format($r_s_prod, 0, ',', '.') ?></td>
                    </tr>
                <?php 
                    endif;
                endfor; 
                
                if ($gt_ij_yr > 0 || $gt_sj_yr > 0):
                    $gt_i_dt_pct_yr = ($gt_ij_yr > 0) ? ($gt_idt_yr / ($gt_ij_yr * 60)) * 100 : 0;
                    $gt_i_prod_yr   = ($gt_ij_yr > 0) ? ($gt_ipcs_yr / ($gt_ij_yr * 60)) : 0;
                    $gt_s_dt_pct_yr = ($gt_sj_yr > 0) ? ($gt_sdt_yr / ($gt_sj_yr * 60)) * 100 : 0;
                    $gt_s_prod_yr   = ($gt_sj_yr > 0) ? ($gt_spcs_yr / ($gt_sj_yr * 60)) : 0;
                ?>
                    <tr class="total-row">
                        <td class="stk-1" style="text-align:right !important; padding-right:15px; font-size:11px; background:#f59e0b !important; color:#fff;">TOTAL TAHUNAN</td>
                        <td><?= number_format($gt_ij_yr, 0, ',', '.') ?></td>
                        <td><?= number_format($gt_idt_yr, 0, ',', '.') ?></td>
                        <td style="color:#0284c7;"><?= number_format($gt_ipcs_yr, 0, ',', '.') ?></td>
                        <td style="color:#0369a1;"><?= number_format($gt_i_dt_pct_yr, 0, ',', '.') ?>%</td>
                        <td style="color:#0284c7; font-size:14px;"><?= number_format($gt_i_prod_yr, 0, ',', '.') ?></td>

                        <td><?= number_format($gt_sj_yr, 0, ',', '.') ?></td>
                        <td><?= number_format($gt_sdt_yr, 0, ',', '.') ?></td>
                        <td style="color:#15803d;"><?= number_format($gt_spcs_yr, 0, ',', '.') ?></td>
                        <td style="color:#166534;"><?= number_format($gt_s_dt_pct_yr, 0, ',', '.') ?>%</td>
                        <td style="color:#15803d; font-size:14px;"><?= number_format($gt_s_prod_yr, 0, ',', '.') ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="11" style="padding: 40px; color: #94a3b8;">Belum ada data akumulasi Flexo tahun ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <hr style="border: 1px dashed #cbd5e1; margin: 30px 0;">

    <!--  TABEL BULANAN 2B: AUTO & GLUE -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
        <h3 style="color:#7e22ce; margin:0;"> Rekap Bulanan Auto & GLUE</h3>
        <button type="button" onclick="exportKeExcel('tabelBulananAutoGlue', 'Rekap_Bulanan_Auto_GLUE')" class="btn-submit-modern" style="background: #16a34a; padding: 6px 12px; font-size: 11px;">
             Export Auto & GLUE
        </button>
    </div>
    <div class="table-responsive">
        <table class="table-premium" id="tabelBulananAutoGlue">
            <thead>
                <tr>
                    <th rowspan="2" class="stk-1" style="background: #0f172a;">BULAN</th>
                    <th colspan="5" style="background: #7e22ce;"> STITCH AUTO</th>
                    <th colspan="5" style="background: #e11d48;"> MESIN GLUE</th>
                </tr>
                <tr>
                    <th style="background: #9333ea;">Jam Kerja</th><th style="background: #9333ea;">DT (Mnt)</th><th style="background: #9333ea;">PCS Output</th><th style="background: #6d28d9; color:#fff;">% DT Auto</th><th style="background: #6d28d9; color:#fff;">Prod. (PCS/Mnt)</th>
                    <th style="background: #f43f5e;">Jam Kerja</th><th style="background: #f43f5e;">DT (Mnt)</th><th style="background: #f43f5e;">PCS Output</th><th style="background: #be123c; color:#fff;">% DT Glue</th><th style="background: #be123c; color:#fff;">Prod. (PCS/Mnt)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                for ($m = 1; $m <= 12; $m++):
                    if (isset($rekap_bulan[$m])):
                        $rsaj = $rekap_bulan[$m]['tot_saj']; $rsadt = $rekap_bulan[$m]['tot_sadt']; $rsapcs = $rekap_bulan[$m]['tot_sapcs'];
                        $rgluej = $rekap_bulan[$m]['tot_gluej']; $rgluedt = $rekap_bulan[$m]['tot_gluedt']; $rgluepcs = $rekap_bulan[$m]['tot_gluepcs'];

                        $r_sa_dt_pct = ($rsaj > 0) ? ($rsadt / ($rsaj * 60)) * 100 : 0;
                        $r_sa_prod   = ($rsaj > 0) ? ($rsapcs / ($rsaj * 60)) : 0;
                        $r_glue_dt_pct = ($rgluej > 0) ? ($rgluedt / ($rgluej * 60)) * 100 : 0;
                        $r_glue_prod   = ($rgluej > 0) ? ($rgluepcs / ($rgluej * 60)) : 0;
                        
                        $nama_bln_str = $nama_bulan_list[str_pad($m, 2, '0', STR_PAD_LEFT)];
                ?>
                    <tr>
                        <td class="stk-1" style="font-weight: 800; color: #0f172a; font-size: 11px; text-transform: uppercase;"><?= $nama_bln_str ?></td>
                        
                        <td><?= number_format($rsaj, 0, ',', '.') ?></td>
                        <td style="color:#ef4444;"><?= number_format($rsadt, 0, ',', '.') ?></td>
                        <td style="font-weight:bold; color:#7e22ce;"><?= number_format($rsapcs, 0, ',', '.') ?></td>
                        <td style="background:#faf5ff; color:#6d28d9; font-weight:bold;"><?= number_format($r_sa_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="background:#f3e8ff; color:#7e22ce; font-weight:900;"><?= number_format($r_sa_prod, 0, ',', '.') ?></td>

                        <td><?= number_format($rgluej, 0, ',', '.') ?></td>
                        <td style="color:#ef4444;"><?= number_format($rgluedt, 0, ',', '.') ?></td>
                        <td style="font-weight:bold; color:#e11d48;"><?= number_format($rgluepcs, 0, ',', '.') ?></td>
                        <td style="background:#fff1f2; color:#be123c; font-weight:bold;"><?= number_format($r_glue_dt_pct, 0, ',', '.') ?>%</td>
                        <td style="background:#ffe4e6; color:#e11d48; font-weight:900;"><?= number_format($r_glue_prod, 0, ',', '.') ?></td>
                    </tr>
                <?php 
                    endif;
                endfor; 
                
                if ($gt_saj_yr > 0 || $gt_gluej_yr > 0):
                    $gt_sa_dt_pct_yr = ($gt_saj_yr > 0) ? ($gt_sadt_yr / ($gt_saj_yr * 60)) * 100 : 0;
                    $gt_sa_prod_yr   = ($gt_saj_yr > 0) ? ($gt_sapcs_yr / ($gt_saj_yr * 60)) : 0;
                    $gt_glue_dt_pct_yr = ($gt_gluej_yr > 0) ? ($gt_gluedt_yr / ($gt_gluej_yr * 60)) * 100 : 0;
                    $gt_glue_prod_yr   = ($gt_gluej_yr > 0) ? ($gt_gluepcs_yr / ($gt_gluej_yr * 60)) : 0;
                ?>
                    <tr class="total-row">
                        <td class="stk-1" style="text-align:right !important; padding-right:15px; font-size:11px; background:#f59e0b !important; color:#fff;">TOTAL TAHUNAN</td>
                        
                        <td><?= number_format($gt_saj_yr, 0, ',', '.') ?></td>
                        <td><?= number_format($gt_sadt_yr, 0, ',', '.') ?></td>
                        <td style="color:#7e22ce;"><?= number_format($gt_sapcs_yr, 0, ',', '.') ?></td>
                        <td style="color:#6d28d9;"><?= number_format($gt_sa_dt_pct_yr, 0, ',', '.') ?>%</td>
                        <td style="color:#7e22ce; font-size:14px;"><?= number_format($gt_sa_prod_yr, 0, ',', '.') ?></td>

                        <td><?= number_format($gt_gluej_yr, 0, ',', '.') ?></td>
                        <td><?= number_format($gt_gluedt_yr, 0, ',', '.') ?></td>
                        <td style="color:#e11d48;"><?= number_format($gt_gluepcs_yr, 0, ',', '.') ?></td>
                        <td style="color:#be123c;"><?= number_format($gt_glue_dt_pct_yr, 0, ',', '.') ?>%</td>
                        <td style="color:#e11d48; font-size:14px;"><?= number_format($gt_glue_prod_yr, 0, ',', '.') ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="11" style="padding: 40px; color: #94a3b8;">Belum ada data akumulasi Auto & GLUE tahun ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
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
            <td class="sign-space"></td>
            <td class="sign-space"></td>
            <td class="sign-space"></td>
        </tr>
        <tr>
            <td>( ______________________ )<br>Admin Flexo</td>
            <td>( ______________________ )<br>Supervisor</td>
            <td>( ______________________ )<br>Manager Plant</td>
        </tr>
    </table>
</div>

<form id="formHapusGlobal" method="POST" style="display: none;">
    <input type="hidden" name="hapus_id" id="inputHapusId">
    <input type="hidden" name="hapus_data" value="1">
</form>

<script>
    //  FUNGSI TOGGLE SAKTI (BARU DITAMBAHKAN)
    function toggleFormInput() {
        let content = document.getElementById('formCollapsibleContent');
        let icon = document.getElementById('formToggleIcon');
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.innerText = ' ↕️ KLIK UNTUK TUTUP PANEL';
            localStorage.setItem('flexoFormstate', 'open');
        } else {
            content.style.display = 'none';
            icon.innerText = ' ↕️ KLIK UNTUK BUKA PANEL INPUT';
            localStorage.setItem('flexoFormstate', 'closed');
        }
    }

    function konfirmasiHapus(id, pesan) {
        if (confirm(pesan)) {
            document.getElementById('inputHapusId').value = id;
            document.getElementById('formHapusGlobal').submit();
        }
    }

    //  FUNGSI EXPORT KE EXCEL SPESIFIK TIAP TABEL
    function exportKeExcel(tableId, fileNamePrefix) {
        let table = document.getElementById(tableId);
        if (!table) return;

        let cloneTable = table.cloneNode(true);

        // Hapus kolom aksi jika ada (data-noexport="true")
        let noExportTh = cloneTable.querySelectorAll('th[data-noexport="true"]');
        noExportTh.forEach(th => th.remove());
        let noExportTd = cloneTable.querySelectorAll('td[data-noexport="true"]');
        noExportTd.forEach(td => td.remove());

        let tglExport = document.querySelector('input[name="bulan"]').value + '-' + document.querySelector('input[name="tahun"]').value;
        let judulFile = fileNamePrefix.replace(/_/g, ' ');

        let htmlTemplate = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
                    th, td { border: 1px solid #000000; padding: 6px; text-align: center; font-size: 13px; vertical-align: middle; }
                    th { color: #ffffff; font-weight: bold; }
                </style>
            </head>
            <body>
                <h2 style="text-align: center; margin-bottom: 15px;">${judulFile} - H2 BASE</h2>
                <p><strong>Periode:</strong> ${tglExport}</p>
                ${cloneTable.outerHTML}
            </body>
            </html>
        `;

        let blob = new Blob([htmlTemplate], { type: 'application/vnd.ms-excel' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = fileNamePrefix + '_' + tglExport + '.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    //  BACA MEMORI LOCALSTORAGE UNTUK TOGGLE PANEL
    window.onload = function() {
        let formstate = localStorage.getItem('flexoFormstate');
        let content = document.getElementById('formCollapsibleContent');
        let icon = document.getElementById('formToggleIcon');
        
        <?php if ($is_edit): ?>
            if(content) content.style.display = 'block';
            if(icon) icon.innerText = ' ↕️ SEDANG MODE EDIT';
        <?php else: ?>
            if(content && icon) {
                if (formstate === 'open') {
                    content.style.display = 'block';
                    icon.innerText = ' ↕️ KLIK UNTUK TUTUP PANEL';
                } else {
                    content.style.display = 'none';
                    icon.innerText = ' ↕️ KLIK UNTUK BUKA PANEL INPUT';
                }
            }
        <?php endif; ?>
    };
</script>
<?php require_once 'footer.php'; ?>












