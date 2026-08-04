<?php
require_once 'auth.php';
require_akses('qc_lap');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false;

// 🚀 AUTO-PATCH DAN RE-CREATE DATABASE KHUSUS INCOMING QC
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_qc_incoming (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        supplier VARCHAR(150) NOT NULL,
        no_roll VARCHAR(100) NOT NULL,
        jenis_kertas VARCHAR(50) NOT NULL,
        gsm_standar INT NOT NULL DEFAULT 0,
        gsm_aktual INT NOT NULL DEFAULT 0,
        moisture DECIMAL(5,2) DEFAULT 0.00,
        status VARCHAR(20) NOT NULL,
        keterangan VARCHAR(200),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) { die("Error DB: " . $e->getMessage()); }

$tgl_filter = isset($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d');

// 🚀 LOGIKA HAPUS DATA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id']) && $user_role != 'Viewer') {
    $id = intval($_POST['hapus_id']);
    $stmt_cek = $pdo->prepare("SELECT no_roll, supplier FROM db_qc_incoming WHERE id=?"); 
    $stmt_cek->execute([$id]); 
    $row_del = $stmt_cek->fetch(PDO::FETCH_ASSOC);
    
    if ($pdo->prepare("DELETE FROM db_qc_incoming WHERE id = ?")->execute([$id])) {
        catatLog($pdo, $user_aktif, "Menghapus data Incoming QC Roll {$row_del['no_roll']} (Supplier: {$row_del['supplier']}).", "🗑️");
        header("Location: qc_incoming.php?pesan=hapus_sukses&tgl=" . $tgl_filter); exit();
    }
}

// 🚀 NILAI DEFAULT FORM
$id_edit = ''; $tanggal = $tgl_filter; $supplier = ''; $no_roll = ''; $jenis_kertas = 'KRAFT'; 
$gsm_standar = ''; $gsm_aktual = ''; $moisture = ''; $status = 'PASS'; $keterangan = '';

// 🚀 TANGKAP DATA UNTUK EDIT
if (isset($_GET['edit']) && $user_role != 'Viewer') {
    $is_edit = true; $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM db_qc_incoming WHERE id = ?"); $stmt->execute([$id_edit]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $tanggal = $row['tanggal']; 
        $supplier = $row['supplier'];
        $no_roll = $row['no_roll'];
        $jenis_kertas = $row['jenis_kertas'];
        $gsm_standar = $row['gsm_standar'];
        $gsm_aktual = $row['gsm_aktual'];
        $moisture = $row['moisture'];
        $status = $row['status'];
        $keterangan = $row['keterangan'];
    }
}

// 🚀 LOGIKA SIMPAN & UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_in']) && $user_role != 'Viewer') {
    $post_id = $_POST['id_edit'] ?? ''; 
    $p_tgl = $_POST['tanggal']; 
    $p_supplier = strtoupper(trim($_POST['supplier']));
    $p_no_roll = strtoupper(trim($_POST['no_roll']));
    $p_jenis = strtoupper(trim($_POST['jenis_kertas']));
    $p_gsm_std = intval($_POST['gsm_standar'] ?? 0);
    $p_gsm_act = intval($_POST['gsm_aktual'] ?? 0);
    $p_moisture = floatval($_POST['moisture'] ?? 0);
    $p_status = strtoupper(trim($_POST['status']));
    $p_keterangan = trim($_POST['keterangan']);

    $params = [
        $p_tgl, $p_supplier, $p_no_roll, $p_jenis, 
        $p_gsm_std, $p_gsm_act, $p_moisture, $p_status, $p_keterangan
    ];

    try {
        if (!empty($post_id)) {
            $sql = "UPDATE db_qc_incoming SET 
                    tanggal=?, supplier=?, no_roll=?, jenis_kertas=?, 
                    gsm_standar=?, gsm_aktual=?, moisture=?, status=?, keterangan=?
                    WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $params[] = $post_id; 
            $stmt->execute($params);
            catatLog($pdo, $user_aktif, "Mengupdate Inspeksi Bahan Baku Roll $p_no_roll.", "✏️");
            header("Location: qc_incoming.php?pesan=edit_sukses&tgl=$p_tgl"); exit();
        } else {
            $sql = "INSERT INTO db_qc_incoming (
                    tanggal, supplier, no_roll, jenis_kertas, 
                    gsm_standar, gsm_aktual, moisture, status, keterangan) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            catatLog($pdo, $user_aktif, "Merekam Inspeksi Kedatangan Roll $p_no_roll ($p_status).", "🧻");
            header("Location: qc_incoming.php?pesan=simpan_sukses&tgl=$p_tgl"); exit();
        }
    } catch (Exception $e) {
        die("<div style='background:#fef2f2; color:#991b1b; padding:20px; font-family:sans-serif;'><h3>Terjadi Kesalahan Sistem Saat Menyimpan Data:</h3><p>" . $e->getMessage() . "</p></div>");
    }
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'simpan_sukses') $pesan = "<div class='alert alert-success'>🎉 Inspeksi Kedatangan Roll Berhasil Disimpan!</div>";
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success'>✏️ Perubahan Data Inspeksi Roll Berhasil Diperbarui!</div>";
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-danger'>🗑️ Data Inspeksi QC Berhasil Dihapus Permanen!</div>";
}

$page_title = "H2 BASE — Incoming QC"; $active_page = "qc_incoming";
require 'header.php';
?>

<style>
    /* 🚀 STYLE MODERN SAAS DNA SYSTEM */
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
    .form-grid-top { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
    .form-group { display: flex; flex-direction: column; min-width: 140px; margin-bottom: 16px;}
    .form-group label { margin-bottom: 8px; font-weight: 800; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;}
    
    input, select { 
        background: #ffffff !important; border: 1px solid #cbd5e1 !important; border-radius: 8px !important; 
        padding: 11px 14px !important; font-size: 13px !important; color: #0f172a !important; 
        font-weight: 600 !important; box-sizing: border-box !important; transition: all 0.2s ease-in-out !important; width: 100%;
    }
    input:focus, select:focus { border-color: #ec4899 !important; background: #ffffff !important; box-shadow: 0 0 0 3px rgba(236,72,153,0.15) !important; outline: none; }

    .admin-input { background: #fdf2f8 !important; border: 1px solid #fbcfe8 !important; color: #be123c !important; }
    .admin-input::placeholder { color: #f9a8d4 !important; font-weight: normal; }
    
    .btn-submit-modern { background: #ec4899 !important; color: #ffffff !important; border: none !important; padding: 12px 32px !important; border-radius: 8px !important; font-size: 14px !important; font-weight: 800 !important; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; width: 100%; }
    .btn-submit-modern:hover { background: #db2777 !important; transform: translateY(-1px) !important; }
    .btn-batal-modern { background: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important; padding: 11px 24px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700; text-align: center; display: inline-flex; align-items: center; justify-content: center; width: 100%; box-sizing: border-box; margin-top: 10px; }
    .btn-excel-modern { background: #16a34a !important; color: #ffffff !important; border: none !important; padding: 10px 20px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; cursor: pointer !important; transition: all 0.2s ease-in-out !important; height: 41px !important; box-sizing: border-box !important; display: inline-flex !important; align-items: center; }

    .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 700; text-align: center;}
    .alert-success { background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .alert-danger { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    .live-calc-box { display: flex; flex-wrap: wrap; gap: 12px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #cbd5e1; padding: 20px; border-radius: 12px; margin-bottom: 24px; font-size: 12px; color: #475569; align-items: center; justify-content: flex-start;}
    .live-calc-box > div { background: #ffffff; padding: 10px 16px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); font-weight: 700; display:flex; flex-direction:column; align-items: center; gap:4px; text-align:center; min-width: 140px;}
    .live-calc-box > div span { font-size: 18px; color: #0f172a; font-weight: 900 !important; }

    /* WIDGET SUMMARY TOTAL QC */
    .widget-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .widget-card { border-radius: 12px; padding: 18px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    .widget-title { margin-top: 0; font-size: 12px; font-weight: 900; padding-bottom: 10px; border-bottom: 2px solid rgba(0,0,0,0.1); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; text-transform: uppercase; }
    .w-val { font-size: 24px; font-weight: 900; }

    /* TABEL DATA SPREADSHEET */
    .table-responsive { width: 100%; display: block; background: white; border-radius: 10px; border: 1px solid #cbd5e1; overflow-y: auto; overflow-x: auto; max-height: 650px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.01); }
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #0f172a; white-space: nowrap; min-width: 1300px; margin-bottom: 0; }
    .table-premium th, .table-premium td { padding: 12px 14px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; vertical-align: middle; text-align: center; font-weight: 600; }
    
    .table-premium th { background-color: #0f172a !important; color: #ffffff !important; font-weight: 700; position: sticky; top: 0; z-index: 15; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 1px solid #334155; }
    
    .stk-1 { position: sticky; left: 0; z-index: 5; width: 40px; min-width: 40px; background-color: #ffffff;}
    .stk-2 { position: sticky; left: 40px; z-index: 5; width: 180px; min-width: 180px; border-right: 3px solid #cbd5e1 !important; text-align: left !important; padding-left: 15px; background-color: #ffffff;}
    .table-premium th.stk-1, .table-premium th.stk-2 { background-color: #0f172a !important; color: #ffffff !important; z-index: 20; border-bottom: 1px solid #334155;}

    tbody tr:nth-child(even) td { background-color: #f8fafc; }
    tbody tr:nth-child(even) td.stk-1, tbody tr:nth-child(even) td.stk-2 { background-color: #f8fafc; }
    tbody tr:hover td { background-color: #fce7f3 !important; }

    /* 🚀 CONDITIONAL FORMATTING ROWS (STATUS QC) */
    tr.row-pass td { background-color: #f0fdf4 !important; color: #166534 !important; border-bottom: 1px solid #bbf7d0 !important; }
    tr.row-reject td { background-color: #fef2f2 !important; color: #991b1b !important; border-bottom: 1px solid #fecaca !important; }
    tr.row-hold td { background-color: #fffbeb !important; color: #b45309 !important; border-bottom: 1px solid #fde68a !important; }

    tr.row-pass:hover td { background-color: #dcfce7 !important; }
    tr.row-reject:hover td { background-color: #fee2e2 !important; }
    tr.row-hold:hover td { background-color: #fef3c7 !important; }

    .btn-edit { display: inline-block; background: #ffffff; color: #0284c7; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; border: 1px solid #bae6fd; cursor: pointer;}
    .btn-edit:hover { background: #e0f2fe; color: #0369a1; }
    .btn-hapus { display: inline-block; background: #ffffff; color: #dc2626; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; border: 1px solid #fecaca; margin-left: 4px; cursor: pointer;}
    .btn-hapus:hover { background: #fee2e2; color: #b91c1c; }

    .form-toggle-header { user-select: none; transition: background 0.2s; padding: 12px 16px; border-radius: 8px; margin: -10px -10px 15px -10px; cursor: pointer; }
    .form-toggle-header:hover { background: #f8fafc; }
</style>

<div class="card" style="border-top: 5px solid #0f172a; padding: 18px 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; width: 100%;">
        <div style="display: flex; gap: 10px; align-items: center;">
            <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase;">📅 TINJAUAN INCOMING QC TANGGAL:</label>
            <form method="GET" action="" style="margin: 0;">
                <input type="date" name="tgl" value="<?= $tgl_filter ?>" onchange="this.form.submit()" style="width: auto; border-color: #ec4899; color: #be123c; background: #fdf2f8 !important; cursor: pointer; padding: 8px 12px !important;">
            </form>
        </div>
    </div>
</div>

<?= $pesan ?>

<?php if ($user_role != 'Viewer'): ?>
<div class="card" id="cardInputArea" style="border-top: 5px solid #ec4899;">
    <div class="form-toggle-header" onclick="toggleFormInput()">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid <?= $is_edit ? '#f9a8d4' : '#fbcfe8' ?>; padding-bottom: 8px;">
            <h2 id="formTitle" style="margin:0; font-size: 17px; color: <?= $is_edit ? '#be123c' : '#db2777' ?>;">
                <?= $is_edit ? "✏️ Mode Edit Inspeksi Roll" : "🧻 Form Inspeksi Kertas Masuk (Incoming QC)" ?>
            </h2>
            <span id="formToggleIcon" style="font-size:13px; font-weight:900; color:#be123c; background:#fdf2f8; padding:4px 10px; border-radius:6px; border:1px solid #fbcfe8;">
                ▼ KLIK UNTUK BUKA/TUTUP FORM
            </span>
        </div>
    </div>
    
    <div id="formCollapsibleContent" style="display: none; margin-top: 15px;">
        <form action="" method="POST" autocomplete="off">
            <input type="hidden" name="id_edit" value="<?= htmlspecialchars($id_edit) ?>">
            
            <div class="form-grid-top" style="grid-template-columns: repeat(4, 1fr);">
                <div class="form-group"><label>Tgl. Kedatangan</label><input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" class="admin-input" required></div>
                <div class="form-group"><label>Nama Supplier</label><input type="text" name="supplier" value="<?= htmlspecialchars($supplier) ?>" class="admin-input" placeholder="Cth: PT. KERTAS NUSANTARA" required></div>
                <div class="form-group"><label>Nomor Roll / Batch</label><input type="text" name="no_roll" value="<?= htmlspecialchars($no_roll) ?>" class="admin-input" placeholder="Barcode Roll" required></div>
                <div class="form-group">
                    <label>Jenis Kertas</label>
                    <select name="jenis_kertas" class="admin-input" required>
                        <option value="KRAFT" <?= $jenis_kertas == 'KRAFT' ? 'selected' : '' ?>>KRAFT</option>
                        <option value="MEDIUM" <?= $jenis_kertas == 'MEDIUM' ? 'selected' : '' ?>>MEDIUM</option>
                        <option value="MEDIUM LINER" <?= $jenis_kertas == 'MEDIUM LINER' ? 'selected' : '' ?>>MEDIUM LINER</option>
                        <option value="WHITE KRAFT" <?= $jenis_kertas == 'WHITE KRAFT' ? 'selected' : '' ?>>WHITE KRAFT</option>
                    </select>
                </div>
            </div>

            <div class="form-grid-top" style="grid-template-columns: repeat(4, 1fr); margin-top: 10px;">
                <div class="form-group"><label>GSM Standar (Sesuai PO)</label><input type="number" id="gsm_std" name="gsm_standar" value="<?= htmlspecialchars($gsm_standar) ?>" required></div>
                <div class="form-group"><label>GSM Aktual (Hasil Tes)</label><input type="number" id="gsm_act" name="gsm_aktual" value="<?= htmlspecialchars($gsm_aktual) ?>" required></div>
                <div class="form-group"><label>Kelembaban / Moisture (%)</label><input type="number" step="0.01" name="moisture" value="<?= htmlspecialchars($moisture) ?>" placeholder="Cth: 8.5" required></div>
                <div class="form-group">
                    <label>Status Inspeksi</label>
                    <select name="status" id="status_qc" required style="font-weight: 900; font-size: 14px;">
                        <option value="PASS" <?= $status == 'PASS' ? 'selected' : '' ?> style="color: #15803d;">✅ PASS (LOLOS)</option>
                        <option value="HOLD" <?= $status == 'HOLD' ? 'selected' : '' ?> style="color: #b45309;">⚠️ HOLD (DITAHAN)</option>
                        <option value="REJECT" <?= $status == 'REJECT' ? 'selected' : '' ?> style="color: #be123c;">🛑 REJECT (TOLAK)</option>
                    </select>
                </div>
            </div>

            <div class="form-grid-top" style="margin-top: 10px; grid-template-columns: 1fr;">
                <div class="form-group">
                    <label>Keterangan Tambahan / Alasan Reject</label>
                    <input type="text" name="keterangan" value="<?= htmlspecialchars($keterangan) ?>" placeholder="Opsional jika PASS. Wajib diisi jika HOLD / REJECT...">
                </div>
            </div>

            <p style="margin: 15px 0 8px 0; font-size:13px; font-weight:800; color:#475569; text-transform: uppercase;">📊 Live Kalkulasi Deviasi Mutu:</p>
            <div class="live-calc-box">
                <div>📏 GSM Standar:<span id="live_gsm_std">0</span></div>
                <div>⚖️ GSM Aktual: <span id="live_gsm_act" style="color: #0ea5e9;">0</span></div>
                <div>📉 Deviasi (Selisih):<span id="live_deviasi" style="color: #be123c;">0</span></div>
            </div>
            
            <div style="display:flex; justify-content: flex-end; width: 100%; margin-top: 15px;">
                <div style="width: 250px;">
                    <button type="submit" name="simpan_in" class="btn-submit-modern" style="<?= $is_edit ? 'background:#be123c !important;' : '' ?>">
                        <?= $is_edit ? "💾 Simpan Pembaruan" : "💾 Simpan Inspeksi" ?>
                    </button>
                    <?php if($is_edit): ?><a href="qc_incoming.php?tgl=<?= $tgl_filter ?>" class="btn-batal-modern">Batal Perubahan</a><?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- 🚀 WIDGET SUMMARY INCOMING QC -->
<?php 
$stmt = $pdo->prepare("SELECT * FROM db_qc_incoming WHERE tanggal = ? ORDER BY id ASC");
$stmt->execute([$tgl_filter]);
$data_qc = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tot_roll = count($data_qc);
$tot_pass = 0; $tot_reject = 0; $tot_hold = 0;
$sum_moisture = 0;

foreach ($data_qc as $row) {
    if ($row['status'] == 'PASS') $tot_pass++;
    elseif ($row['status'] == 'REJECT') $tot_reject++;
    elseif ($row['status'] == 'HOLD') $tot_hold++;
    
    $sum_moisture += $row['moisture'];
}

$avg_moisture = $tot_roll > 0 ? ($sum_moisture / $tot_roll) : 0;
$ada_data_hari_ini = $tot_roll > 0;
?>

<?php if ($ada_data_hari_ini): ?>
<div class="widget-container">
    <div class="widget-card" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-color: #bae6fd;">
        <h3 class="widget-title" style="color: #0369a1;">🧻 TOTAL ROLL DIPERIKSA</h3>
        <div class="w-val" style="color: #0284c7;"><?= number_format($tot_roll, 0, ',', '.') ?> <span style="font-size:12px; color:#38bdf8;">ROLL</span></div>
    </div>
    <div class="widget-card" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-color: #bbf7d0;">
        <h3 class="widget-title" style="color: #15803d;">✅ STATUS: PASS (LOLOS)</h3>
        <div class="w-val" style="color: #16a34a;"><?= number_format($tot_pass, 0, ',', '.') ?> <span style="font-size:12px; color:#4ade80;">ROLL</span></div>
    </div>
    <div class="widget-card" style="background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%); border-color: #fecdd3;">
        <h3 class="widget-title" style="color: #be123c;">🛑 STATUS: REJECT / HOLD</h3>
        <div class="w-val" style="color: #9f1239;"><?= number_format($tot_reject + $tot_hold, 0, ',', '.') ?> <span style="font-size:12px; color:#f43f5e;">ROLL</span></div>
    </div>
    <div class="widget-card" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-color: #cbd5e1;">
        <h3 class="widget-title" style="color: #475569;">💧 RATA-RATA MOISTURE</h3>
        <div class="w-val" style="color: #334155;"><?= number_format($avg_moisture, 2, ',', '.') ?> <span style="font-size:12px; color:#94a3b8;">%</span></div>
    </div>
</div>
<?php endif; ?>

<!-- 🚀 REPORTING DATA LOG -->
<div class="card" style="border-top: 5px solid #0f172a;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
        <div>
            <h2 style="margin: 0; border: none; padding: 0; color: #0f172a;">📋 DATABASE INSPEKSI KEDATANGAN BAHAN BAKU</h2>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-top: 4px;">Data Peninjauan Tanggal: <strong style="color:#ec4899; font-size: 15px;"><?= date('d F Y', strtotime($tgl_filter)) ?></strong></div>
        </div>
        <button type="button" onclick="exportKeExcel()" class="btn-excel-modern">📥 Export Excel Bersih</button>
    </div>

    <div id="exportContainer">
        <?php if ($ada_data_hari_ini): ?>
            <div class="table-responsive">
                <table class="table-premium">
                    <thead>
                        <tr>
                            <th class="stk-1">No</th>
                            <th class="stk-2 text-left">No Roll / Batch</th>
                            <th class="text-left">Nama Supplier</th>
                            <th class="text-center">Jenis Kertas</th>
                            <th class="text-center">GSM Standar</th>
                            <th class="text-center">GSM Aktual</th>
                            <th class="text-center">Deviasi (Selisih)</th>
                            <th class="text-center">Moisture (%)</th>
                            <th class="text-center" style="font-weight: 900;">Status QC</th>
                            <th class="text-left">Keterangan / Alasan</th>
                            <th class="text-center" style="width: 100px;" data-noexport="true">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($data_qc as $row): 
                            $deviasi = $row['gsm_aktual'] - $row['gsm_standar'];
                            $row_class = 'row-' . strtolower($row['status']); // Auto apply class row-pass, row-reject, row-hold
                            
                            $dev_color = $deviasi == 0 ? '#15803d' : ($deviasi < 0 ? '#be123c' : '#0369a1');
                        ?>
                            <tr class="<?= $row_class ?>">
                                <td class="text-center stk-1"><?= $no++ ?></td>
                                <td class="text-left stk-2" style="font-weight: 900; font-family: monospace; font-size: 14px;"><?= htmlspecialchars($row['no_roll']) ?></td>
                                <td class="text-left" style="font-weight: 800;"><?= htmlspecialchars($row['supplier']) ?></td>
                                <td class="text-center" style="font-weight: 800;"><?= htmlspecialchars($row['jenis_kertas']) ?></td>
                                <td class="text-center"><?= $row['gsm_standar'] ?></td>
                                <td class="text-center" style="font-weight: 800;"><?= $row['gsm_aktual'] ?></td>
                                <td class="text-center" style="font-weight: 900; color: <?= $dev_color ?>;"><?= $deviasi > 0 ? '+'.$deviasi : $deviasi ?></td>
                                <td class="text-center" style="font-weight: 800;"><?= number_format($row['moisture'], 2, ',', '.') ?>%</td>
                                <td class="text-center" style="font-weight: 900; font-size: 14px;"><?= htmlspecialchars($row['status']) ?></td>
                                <td class="text-left" style="font-size: 11px; white-space: normal;"><?= htmlspecialchars($row['keterangan']) ?></td>
                                
                                <td class="text-center" data-noexport="true">
                                    <?php if ($user_role != 'Viewer'): ?>
                                        <a href="qc_incoming.php?tgl=<?= $tgl_filter ?>&edit=<?= $row['id'] ?>#cardInputArea" class="btn-edit">Edit</a>
                                        <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Hapus data inspeksi roll ini?')" class="btn-hapus">Hapus</a>
                                    <?php else: ?>
                                        <span style="font-size:10px; color:#94a3b8;">Terbatas</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table-premium">
                    <tr><td class="text-center" style="padding: 40px; color: #94a3b8; font-weight: 600;">⚠️ Belum ada rekaman inspeksi bahan baku pada tanggal ini.</td></tr>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<form id="formHapus" method="POST" style="display:none;">
    <input type="hidden" name="hapus_id" id="getHapusId">
    <input type="hidden" name="hapus_data" value="1">
</form>

<script>
    function toggleFormInput() {
        let content = document.getElementById('formCollapsibleContent');
        let icon = document.getElementById('formToggleIcon');
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.innerText = '▲ KLIK UNTUK TUTUP PANEL';
            localStorage.setItem('qcInFormState', 'open');
        } else {
            content.style.display = 'none';
            icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
            localStorage.setItem('qcInFormState', 'closed');
        }
    }

    function konfirmasiHapus(id, pesan) {
        if(confirm(pesan)) {
            document.getElementById('getHapusId').value = id;
            document.getElementById('formHapus').submit();
        }
    }

    function hitungLiveDeviasi() {
        const std = parseInt(document.getElementById('gsm_std').value) || 0;
        const act = parseInt(document.getElementById('gsm_act').value) || 0;
        const deviasi = act - std;

        document.getElementById('live_gsm_std').innerText = std;
        document.getElementById('live_gsm_act').innerText = act;
        
        let elDeviasi = document.getElementById('live_deviasi');
        elDeviasi.innerText = deviasi > 0 ? '+' + deviasi : deviasi;
        
        if(deviasi < 0) { elDeviasi.style.color = '#be123c'; }
        else if(deviasi > 0) { elDeviasi.style.color = '#0369a1'; }
        else { elDeviasi.style.color = '#15803d'; }
    }

    document.getElementById('status_qc').addEventListener('change', function() {
        let status = this.value;
        if(status === 'PASS') {
            this.style.color = '#15803d';
        } else if(status === 'HOLD') {
            this.style.color = '#b45309';
        } else {
            this.style.color = '#be123c';
        }
    });

    // 🚀 FUNGSI EXPORT EXCEL
    function exportKeExcel() {
        let exportContainer = document.getElementById("exportContainer");
        if (!exportContainer) return;

        let cloneContainer = exportContainer.cloneNode(true);
        let noExportTh = cloneContainer.querySelectorAll('th[data-noexport="true"]');
        noExportTh.forEach(th => th.remove());
        let noExportTd = cloneContainer.querySelectorAll('td[data-noexport="true"]');
        noExportTd.forEach(td => td.remove());

        let allElements = cloneContainer.querySelectorAll('*');
        allElements.forEach(el => { el.removeAttribute('style'); });

        let tglExport = document.querySelector('input[name="tgl"]').value;
        
        let htmlTemplate = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; margin-bottom: 20px; }
                    th, td { border: 1px solid #000000; text-align: center; vertical-align: middle; white-space: pre-wrap; font-size: 11pt; mso-number-format:"\\@"; }
                    th { background-color: #0f172a; color: #ffffff; font-weight: bold; }
                </style>
            </head>
            <body>
                <h2 style="text-align: center; margin-bottom: 15px;">LAPORAN INSPEKSI KEDATANGAN KERTAS (INCOMING QC)</h2>
                <p><strong>TANGGAL PENINJAUAN:</strong> ${tglExport}</p>
                ${cloneContainer.innerHTML}
            </body>
            </html>
        `;

        let blob = new Blob([htmlTemplate], { type: 'application/vnd.ms-excel' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'Laporan_Incoming_QC_' + tglExport + '.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    window.onload = function() {
        document.getElementById('gsm_std').addEventListener('input', hitungLiveDeviasi);
        document.getElementById('gsm_act').addEventListener('input', hitungLiveDeviasi);
        hitungLiveDeviasi();

        let formState = localStorage.getItem('qcInFormState');
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
</script>
<?php require_once 'footer.php'; ?>

