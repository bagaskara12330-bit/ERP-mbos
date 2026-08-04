<?php
require_once 'auth.php';
require_akses('qc_lap');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false;

// 🚀 AUTO-PATCH DAN RE-CREATE DATABASE KHUSUS QC
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_qc_laporan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        customer VARCHAR(150) NOT NULL,
        jenis_reject VARCHAR(200) NOT NULL,
        jumlah_reject INT DEFAULT 0,
        tgl_produksi DATE NOT NULL,
        shift VARCHAR(10) NOT NULL,
        kirim INT DEFAULT 0,
        retur INT DEFAULT 0,
        kirim_kembali INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) { die("Error DB: " . $e->getMessage()); }

$tgl_filter = isset($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d');

// 🚀 LOGIKA HAPUS DATA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id']) && $user_role != 'Viewer') {
    $id = intval($_POST['hapus_id']);
    $stmt_cek = $pdo->prepare("SELECT customer, jenis_reject FROM db_qc_laporan WHERE id=?"); 
    $stmt_cek->execute([$id]); 
    $row_del = $stmt_cek->fetch(PDO::FETCH_ASSOC);
    
    if ($pdo->prepare("DELETE FROM db_qc_laporan WHERE id = ?")->execute([$id])) {
        catatLog($pdo, $user_aktif, "Menghapus data QC Pelanggan {$row_del['customer']} ({$row_del['jenis_reject']}).", "🗑️");
        header("Location: qc_laporan.php?pesan=hapus_sukses&tgl=" . $tgl_filter); exit();
    }
}

// 🚀 NILAI DEFAULT FORM
$id_edit = ''; $tanggal = $tgl_filter; $customer = ''; $jenis_reject = ''; 
$jumlah_reject = ''; $tgl_produksi = $tgl_filter; $shift = '1'; 
$kirim = ''; $retur = ''; $kirim_kembali = '';

// 🚀 TANGKAP DATA UNTUK EDIT
if (isset($_GET['edit']) && $user_role != 'Viewer') {
    $is_edit = true; $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM db_qc_laporan WHERE id = ?"); $stmt->execute([$id_edit]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $tanggal = $row['tanggal']; 
        $customer = $row['customer'];
        $jenis_reject = $row['jenis_reject'];
        $jumlah_reject = $row['jumlah_reject'];
        $tgl_produksi = $row['tgl_produksi'];
        $shift = $row['shift'];
        $kirim = $row['kirim'];
        $retur = $row['retur'];
        $kirim_kembali = $row['kirim_kembali'];
    }
}

// 🚀 LOGIKA SIMPAN & UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_qc']) && $user_role != 'Viewer') {
    $post_id = $_POST['id_edit'] ?? ''; 
    $p_tgl = $_POST['tanggal']; 
    $p_cust = strtoupper(trim($_POST['customer']));
    $p_jenis_reject = strtoupper(trim($_POST['jenis_reject']));
    $p_jumlah_reject = intval($_POST['jumlah_reject'] ?? 0);
    $p_tgl_prod = $_POST['tgl_produksi'];
    $p_shift = $_POST['shift'];
    $p_kirim = intval($_POST['kirim'] ?? 0);
    $p_retur = intval($_POST['retur'] ?? 0);
    $p_kirim_kembali = intval($_POST['kirim_kembali'] ?? 0);

    $params = [
        $p_tgl, $p_cust, $p_jenis_reject, $p_jumlah_reject, 
        $p_tgl_prod, $p_shift, $p_kirim, $p_retur, $p_kirim_kembali
    ];

    try {
        if (!empty($post_id)) {
            $sql = "UPDATE db_qc_laporan SET 
                    tanggal=?, customer=?, jenis_reject=?, jumlah_reject=?, 
                    tgl_produksi=?, shift=?, kirim=?, retur=?, kirim_kembali=?
                    WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $params[] = $post_id; 
            $stmt->execute($params);
            catatLog($pdo, $user_aktif, "Mengupdate Laporan QC Customer $p_cust.", "✏️");
            header("Location: qc_laporan.php?pesan=edit_sukses&tgl=$p_tgl"); exit();
        } else {
            $sql = "INSERT INTO db_qc_laporan (
                    tanggal, customer, jenis_reject, jumlah_reject, 
                    tgl_produksi, shift, kirim, retur, kirim_kembali) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            catatLog($pdo, $user_aktif, "Merekam Laporan QC Baru Customer $p_cust.", "🔎");
            header("Location: qc_laporan.php?pesan=simpan_sukses&tgl=$p_tgl"); exit();
        }
    } catch (Exception $e) {
        die("<div style='background:#fef2f2; color:#991b1b; padding:20px; font-family:sans-serif;'><h3>Terjadi Kesalahan Sistem Saat Menyimpan Data:</h3><p>" . $e->getMessage() . "</p></div>");
    }
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'simpan_sukses') $pesan = "<div class='alert alert-success'>🎉 Data Laporan QC Berhasil Disimpan!</div>";
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success'>✏️ Perubahan Data Laporan QC Berhasil Diperbarui!</div>";
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-danger'>🗑️ Data Laporan QC Berhasil Dihapus Permanen!</div>";
}

$page_title = "H2 BASE — Laporan QC"; $active_page = "qc_laporan";
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

    /* WIDGET SUMMARY TOTAL QC */
    .widget-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .widget-card { border-radius: 12px; padding: 18px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    .widget-title { margin-top: 0; font-size: 14px; font-weight: 900; padding-bottom: 10px; border-bottom: 2px solid rgba(0,0,0,0.1); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
    .w-val { font-size: 24px; font-weight: 900; }

    /* TABEL DATA SPREADSHEET */
    .table-responsive { width: 100%; display: block; background: white; border-radius: 10px; border: 1px solid #cbd5e1; overflow-y: auto; overflow-x: auto; max-height: 650px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.01); }
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #0f172a; white-space: nowrap; min-width: 1200px; margin-bottom: 0; }
    .table-premium th, .table-premium td { padding: 12px 14px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; vertical-align: middle; text-align: center; font-weight: 600; }
    
    .table-premium th { background-color: #0f172a !important; color: #ffffff !important; font-weight: 700; position: sticky; top: 0; z-index: 15; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 1px solid #334155; }
    
    /* 🚀 FIX NEMBUS: background warna solid pada stk-1 dan stk-2 */
    .stk-1 { position: sticky; left: 0; z-index: 5; width: 40px; min-width: 40px; background-color: #ffffff;}
    .stk-2 { position: sticky; left: 40px; z-index: 5; width: 200px; min-width: 200px; border-right: 3px solid #cbd5e1 !important; text-align: left !important; padding-left: 15px; background-color: #ffffff;}
    .table-premium th.stk-1, .table-premium th.stk-2 { background-color: #0f172a !important; color: #ffffff !important; z-index: 20; border-bottom: 1px solid #334155;}

    tbody tr:nth-child(even) td { background-color: #f8fafc; }
    tbody tr:nth-child(even) td.stk-1, tbody tr:nth-child(even) td.stk-2 { background-color: #f8fafc; }
    tbody tr:hover td { background-color: #fce7f3 !important; }

    .total-row td { background-color: #cbd5e1 !important; font-weight: 800; color: #0f172a !important; border-top: 2px solid #0f172a !important; position: sticky; bottom: 0; z-index: 12; outline: 1px solid #cbd5e1;}
    .stk-total { position: sticky; left: 0; z-index: 22 !important; border-right: 2px solid #94a3b8 !important;}

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
            <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase;">📅 TINJAUAN LAPORAN QC TANGGAL:</label>
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
                <?= $is_edit ? "✏️ Mode Edit Laporan QC" : "🔎 Form Input Inspector QC" ?>
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
                <div class="form-group"><label>Tanggal Input QC</label><input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" class="admin-input" required></div>
                <div class="form-group"><label>Nama Customer</label><input type="text" name="customer" value="<?= htmlspecialchars($customer) ?>" class="admin-input" placeholder="Cth: PT. MAJU JAYA" required></div>
                <div class="form-group"><label>Jenis Reject / Cacat</label><input type="text" name="jenis_reject" value="<?= htmlspecialchars($jenis_reject) ?>" class="admin-input" placeholder="Cth: Sobek, Miring, Warna Pudar..." required></div>
                <div class="form-group"><label style="color: #be123c;">Jumlah Reject (Pcs)</label><input type="number" min="0" name="jumlah_reject" value="<?= htmlspecialchars($jumlah_reject) ?>" class="admin-input" style="font-size: 16px !important; font-weight: 900 !important; color: #be123c !important;" required></div>
            </div>

            <div class="form-grid-top" style="grid-template-columns: repeat(5, 1fr); margin-top: 10px;">
                <div class="form-group"><label>Tanggal Produksi</label><input type="date" name="tgl_produksi" value="<?= htmlspecialchars($tgl_produksi) ?>" required></div>
                <div class="form-group">
                    <label>Shift Produksi</label>
                    <select name="shift" required>
                        <option value="1" <?= $shift == '1' ? 'selected' : '' ?>>Shift 1</option>
                        <option value="2" <?= $shift == '2' ? 'selected' : '' ?>>Shift 2</option>
                        <option value="3" <?= $shift == '3' ? 'selected' : '' ?>>Shift 3</option>
                    </select>
                </div>
                <div class="form-group"><label>Qty Kirim (Pcs)</label><input type="number" min="0" name="kirim" value="<?= htmlspecialchars($kirim) ?>"></div>
                <div class="form-group"><label>Qty Retur (Pcs)</label><input type="number" min="0" name="retur" value="<?= htmlspecialchars($retur) ?>"></div>
                <div class="form-group"><label>Kirim Kembali (Pcs)</label><input type="number" min="0" name="kirim_kembali" value="<?= htmlspecialchars($kirim_kembali) ?>"></div>
            </div>
            
            <div style="display:flex; justify-content: flex-end; width: 100%; margin-top: 15px;">
                <div style="width: 250px;">
                    <button type="submit" name="simpan_qc" class="btn-submit-modern" style="<?= $is_edit ? 'background:#be123c !important;' : '' ?>">
                        <?= $is_edit ? "💾 Simpan Pembaruan Data" : "💾 Simpan Laporan QC" ?>
                    </button>
                    <?php if($is_edit): ?><a href="qc_laporan.php?tgl=<?= $tgl_filter ?>" class="btn-batal-modern">Batal Perubahan</a><?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- 🚀 WIDGET SUMMARY TOTAL QC -->
<?php 
$stmt = $pdo->prepare("SELECT * FROM db_qc_laporan WHERE tanggal = ? ORDER BY id ASC");
$stmt->execute([$tgl_filter]);
$data_qc = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tot_reject = 0; $tot_kirim = 0; $tot_retur = 0; $tot_kirim_kembali = 0;

foreach ($data_qc as $row) {
    $tot_reject += $row['jumlah_reject'];
    $tot_kirim += $row['kirim'];
    $tot_retur += $row['retur'];
    $tot_kirim_kembali += $row['kirim_kembali'];
}
$ada_data_hari_ini = count($data_qc) > 0;
?>

<?php if ($ada_data_hari_ini): ?>
<div class="widget-container">
    <div class="widget-card" style="background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%); border-color: #fecdd3;">
        <h3 class="widget-title" style="color: #be123c;">🛑 TOTAL REJECT / CACAT</h3>
        <div class="w-val" style="color: #9f1239;"><?= number_format($tot_reject, 0, ',', '.') ?> <span style="font-size:12px; color:#f43f5e;">PCS</span></div>
    </div>
    <div class="widget-card" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-color: #bae6fd;">
        <h3 class="widget-title" style="color: #0369a1;">📦 TOTAL QTY KIRIM</h3>
        <div class="w-val" style="color: #0284c7;"><?= number_format($tot_kirim, 0, ',', '.') ?> <span style="font-size:12px; color:#38bdf8;">PCS</span></div>
    </div>
    <div class="widget-card" style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border-color: #fde68a;">
        <h3 class="widget-title" style="color: #b45309;">🔄 TOTAL QTY RETUR</h3>
        <div class="w-val" style="color: #d97706;"><?= number_format($tot_retur, 0, ',', '.') ?> <span style="font-size:12px; color:#fbbf24;">PCS</span></div>
    </div>
    <div class="widget-card" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-color: #bbf7d0;">
        <h3 class="widget-title" style="color: #15803d;">✅ TOTAL KIRIM KEMBALI</h3>
        <div class="w-val" style="color: #16a34a;"><?= number_format($tot_kirim_kembali, 0, ',', '.') ?> <span style="font-size:12px; color:#4ade80;">PCS</span></div>
    </div>
</div>
<?php endif; ?>

<!-- 🚀 REPORTING DATA LOG -->
<div class="card" style="border-top: 5px solid #0f172a;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
        <div>
            <h2 style="margin: 0; border: none; padding: 0; color: #0f172a;">📋 DATABASE LAPORAN QUALITY CONTROL</h2>
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
                            <th class="stk-2 text-left">Nama Customer</th>
                            <th class="text-left">Jenis Reject / Cacat</th>
                            <th class="text-center" style="background: #be123c; color: #fff;">Jml Reject (Pcs)</th>
                            <th class="text-center">Tgl Produksi</th>
                            <th class="text-center">Shift Prod</th>
                            <th class="text-center" style="background: #0284c7; color: #fff;">Qty Kirim (Pcs)</th>
                            <th class="text-center" style="background: #d97706; color: #fff;">Qty Retur (Pcs)</th>
                            <th class="text-center" style="background: #16a34a; color: #fff;">Kirim Kembali (Pcs)</th>
                            <th class="text-center" style="width: 100px;" data-noexport="true">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($data_qc as $row): 
                        ?>
                            <tr>
                                <td class="text-center stk-1"><?= $no++ ?></td>
                                <td class="text-left stk-2" style="font-weight: 800; color:#0f172a;"><?= htmlspecialchars($row['customer']) ?></td>
                                <td class="text-left" style="color: #be123c; font-weight: 800;"><?= htmlspecialchars($row['jenis_reject']) ?></td>
                                <td class="text-center" style="font-size: 15px; font-weight: 900; background: #fff1f2; color: #be123c;"><?= number_format($row['jumlah_reject'], 0, ',', '.') ?></td>
                                <td class="text-center" style="font-family: monospace; font-size: 14px; font-weight: bold;"><?= date('d/m/Y', strtotime($row['tgl_produksi'])) ?></td>
                                <td class="text-center" style="font-weight: 800; color: #0ea5e9;">Shift <?= htmlspecialchars($row['shift']) ?></td>
                                <td class="text-center" style="font-size: 14px; font-weight: 800; background: #f0f9ff; color: #0369a1;"><?= number_format($row['kirim'], 0, ',', '.') ?></td>
                                <td class="text-center" style="font-size: 14px; font-weight: 800; background: #fffbeb; color: #d97706;"><?= number_format($row['retur'], 0, ',', '.') ?></td>
                                <td class="text-center" style="font-size: 14px; font-weight: 800; background: #f0fdf4; color: #15803d;"><?= number_format($row['kirim_kembali'], 0, ',', '.') ?></td>
                                
                                <td class="text-center" data-noexport="true">
                                    <?php if ($user_role != 'Viewer'): ?>
                                        <a href="qc_laporan.php?tgl=<?= $tgl_filter ?>&edit=<?= $row['id'] ?>#cardInputArea" class="btn-edit">Edit</a>
                                        <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Hapus data QC ini?')" class="btn-hapus">Hapus</a>
                                    <?php else: ?>
                                        <span style="font-size:10px; color:#94a3b8;">Terbatas</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <!-- 🚀 BARIS TOTAL AKUMULASI -->
                        <tr class="total-row">
                            <td colspan="2" class="text-center stk-total" style="text-align:right !important; padding-right:15px;">TOTAL AKUMULASI HARIAN</td>
                            <td></td>
                            <td class="text-center" style="font-size: 16px; color:#be123c; background: #ffe4e6 !important;"><?= number_format($tot_reject, 0, ',', '.') ?></td>
                            <td colspan="2"></td>
                            <td class="text-center" style="font-size: 15px; color:#0369a1; background: #e0f2fe !important;"><?= number_format($tot_kirim, 0, ',', '.') ?></td>
                            <td class="text-center" style="font-size: 15px; color:#b45309; background: #fef3c7 !important;"><?= number_format($tot_retur, 0, ',', '.') ?></td>
                            <td class="text-center" style="font-size: 15px; color:#15803d; background: #dcfce7 !important;"><?= number_format($tot_kirim_kembali, 0, ',', '.') ?></td>
                            <td data-noexport="true"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table-premium">
                    <tr><td class="text-center" style="padding: 40px; color: #94a3b8; font-weight: 600;">⚠️ Belum ada rekaman laporan Quality Control pada tanggal ini.</td></tr>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 🚀 FIX BUG POST: Penambahan method="POST" pada Tag Form -->
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
            localStorage.setItem('qcFormState', 'open');
        } else {
            content.style.display = 'none';
            icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
            localStorage.setItem('qcFormState', 'closed');
        }
    }

    function konfirmasiHapus(id, pesan) {
        if(confirm(pesan)) {
            document.getElementById('getHapusId').value = id;
            document.getElementById('formHapus').submit();
        }
    }

    // 🚀 FUNGSI EXPORT EXCEL DENGAN PERLINDUNGAN FORMAT NUMBER TEXT
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
                    h3 { font-family: Calibri, Arial, sans-serif; }
                </style>
            </head>
            <body>
                <h2 style="text-align: center; margin-bottom: 15px;">LAPORAN QUALITY CONTROL (QC)</h2>
                <p><strong>TANGGAL PENINJAUAN:</strong> ${tglExport}</p>
                ${cloneContainer.innerHTML}
            </body>
            </html>
        `;

        let blob = new Blob([htmlTemplate], { type: 'application/vnd.ms-excel' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'Laporan_QC_' + tglExport + '.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    window.onload = function() {
        let formState = localStorage.getItem('qcFormState');
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

