<?php
require_once 'auth.php';
require_akses('prod_kpi');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false; $id_edit = "";

$tanggal = date('Y-m-d');
$h1_tstb = ""; $h1_kirim = ""; $h2_tstb = ""; $h2_kirim = ""; $h1_target = ""; $h2_target = "";

// 🚀 LOGIKA HAPUS DATA AMAN (MENGGUNAKAN POST METHOD BUKAN GET)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id'])) {
    try {
        $id = intval($_POST['hapus_id']);
        
        // Ambil tanggal sebelum dihapus untuk direkam di log
        $stmt_tgl = $pdo->prepare("SELECT tanggal FROM mbos_kpi WHERE id = :id");
        $stmt_tgl->execute([':id' => $id]);
        $tgl_dihapus = $stmt_tgl->fetchColumn();
        $tgl_str = $tgl_dihapus ? date('d/m/Y', strtotime($tgl_dihapus)) : '';

        $stmt = $pdo->prepare("DELETE FROM mbos_kpi WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // 🔔 TEMBAK NOTIFIKASI HAPUS
        catatLog($pdo, $user_aktif, "Menghapus data KPI Harian tanggal $tgl_str secara permanen.", "🗑️");
        
        header("Location: kpi.php?pesan=hapus_sukses"); exit();
    } catch (PDOException $e) { $pesan = "<div class='alert alert-danger'>❌ Gagal menghapus: " . $e->getMessage() . "</div>"; }
}

if (isset($_GET['edit'])) {
    $is_edit = true; $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM mbos_kpi WHERE id = :id");
    $stmt->execute([':id' => $id_edit]);
    $data_lama = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data_lama) {
        $tanggal = $data_lama['tanggal']; $h1_tstb = $data_lama['h1_tstb']; $h1_kirim = $data_lama['h12_kirim']; 
        $h2_tstb = $data_lama['h2_tstb']; $h2_kirim = $data_lama['h2_kirim']; $h1_target = $data_lama['h1_target']; $h2_target = $data_lama['h2_target'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['hapus_data'])) {
    $tahun = date('Y', strtotime($_POST['tanggal'])); $tanggal = $_POST['tanggal'];
    $bulan_eng = date('F', strtotime($_POST['tanggal']));
    $bulan_map = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
    $bulan = isset($bulan_map[$bulan_eng]) ? $bulan_map[$bulan_eng] : $bulan_eng;

    $h1_tstb = floatval($_POST['h1_tstb']); $h1_kirim = floatval($_POST['h1_kirim']); $h2_tstb = floatval($_POST['h2_tstb']);
    $h2_kirim = floatval($_POST['h2_kirim']); $h1_target = floatval($_POST['h1_target']); $h2_target = floatval($_POST['h2_target']);

    $h1_persen = $h1_target > 0 ? ($h1_tstb / $h1_target) * 100 : 0;
    $h2_persen = $h2_target > 0 ? ($h2_tstb / $h2_target) * 100 : 0;
    $h12_tstb = $h1_tstb + $h2_tstb; $h12_target = $h1_target + $h2_target;
    $h12_persen = $h12_target > 0 ? ($h12_tstb / $h12_target) * 100 : 0;
    $h12_kirim = $h1_kirim; $kirim_target = $h12_target;
    $kirim_persen = $kirim_target > 0 ? ($h12_kirim / $kirim_target) * 100 : 0;

    try {
        if (isset($_POST['is_edit']) && $_POST['is_edit'] == '1') {
            $id_edit = intval($_POST['id_edit']);
            $sql = "UPDATE mbos_kpi SET tahun=:tahun, tanggal=:tanggal, bulan=:bulan, h1_tstb=:h1_tstb, h12_kirim=:h12_kirim, h2_tstb=:h2_tstb, h2_kirim=:h2_kirim, h1_target=:h1_target, h1_persen=:h1_persen, h2_target=:h2_target, h2_persen=:h2_persen, h12_tstb=:h12_tstb, h12_target=:h12_target, h12_persen=:h12_persen, kirim_target=:kirim_target, kirim_persen=:kirim_persen WHERE id=:id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':tahun'=>$tahun,':tanggal'=>$tanggal,':bulan'=>$bulan,':h1_tstb'=>$h1_tstb,':h12_kirim'=>$h1_kirim,':h2_tstb'=>$h2_tstb,':h2_kirim'=>$h2_kirim,':h1_target'=>$h1_target,':h1_persen'=>$h1_persen,':h2_target'=>$h2_target,':h2_persen'=>$h2_persen,':h12_tstb'=>$h12_tstb,':h12_target'=>$h12_target,':h12_persen'=>$h12_persen,':kirim_target'=>$kirim_target,':kirim_persen'=>$kirim_persen,':id'=>$id_edit]);
            
            // 🔔 TEMBAK NOTIFIKASI EDIT
            catatLog($pdo, $user_aktif, "Mengubah data pencapaian KPI Harian tanggal " . date('d/m/Y', strtotime($tanggal)) . ".", "✏️");
            
            header("Location: kpi.php?pesan=update_sukses"); exit();
        } else {
            $sql = "INSERT INTO mbos_kpi (tahun,tanggal,bulan,h1_tstb,h12_kirim,h2_tstb,h2_kirim,h1_target,h1_persen,h2_target,h2_persen,h12_tstb,h12_target,h12_persen,kirim_target,kirim_persen) VALUES (:tahun,:tanggal,:bulan,:h1_tstb,:h12_kirim,:h2_tstb,:h2_kirim,:h1_target,:h1_persen,:h2_target,:h2_persen,:h12_tstb,:h12_target,:h12_persen,:kirim_target,:kirim_persen)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':tahun'=>$tahun,':tanggal'=>$tanggal,':bulan'=>$bulan,':h1_tstb'=>$h1_tstb,':h12_kirim'=>$h1_kirim,':h2_tstb'=>$h2_tstb,':h2_kirim'=>$h2_kirim,':h1_target'=>$h1_target,':h1_persen'=>$h1_persen,':h2_target'=>$h2_target,':h2_persen'=>$h2_persen,':h12_tstb'=>$h12_tstb,':h12_target'=>$h12_target,':h12_persen'=>$h12_persen,':kirim_target'=>$kirim_target,':kirim_persen'=>$kirim_persen]);
            
            // 🔔 TEMBAK NOTIFIKASI INPUT BARU
            catatLog($pdo, $user_aktif, "Merekam pencapaian KPI Harian baru untuk tanggal " . date('d/m/Y', strtotime($tanggal)) . ".", "🎯");
            
            header("Location: kpi.php?pesan=simpan_sukses"); exit();
        }
    } catch (PDOException $e) { $pesan = "<div class='alert alert-danger'>❌ Gagal memproses data: " . $e->getMessage() . "</div>"; }
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'simpan_sukses') $pesan = "<div class='alert alert-success'>🎉 Data KPI berhasil disimpan!</div>";
    if ($_GET['pesan'] == 'update_sukses') $pesan = "<div class='alert alert-success'>✏️ Data KPI berhasil diperbarui!</div>";
    if ($_GET['pesan'] == 'hapus_sukses')  $pesan = "<div class='alert alert-success'>🗑️ Data KPI berhasil dihapus!</div>";
}

// 🚀 KAMUS BULAN GLOBAL UNTUK FILTER OTOMATIS
$nama_bulan_id = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];

// 🚀 DEFAULT FILTER DIUBAH KE BULAN DAN TAHUN SAAT INI
$filter_tahun = isset($_GET['filter_tahun']) ? $_GET['filter_tahun'] : date('Y');
$filter_bulan = isset($_GET['filter_bulan']) ? $_GET['filter_bulan'] : $nama_bulan_id[date('m')];

// Tarik data unik untuk combobox filter
try {
    $list_tahun = $pdo->query("SELECT DISTINCT tahun FROM mbos_kpi ORDER BY tahun DESC")->fetchAll(PDO::FETCH_COLUMN);
    $list_bulan = $pdo->query("SELECT DISTINCT bulan FROM mbos_kpi ORDER BY FIELD(bulan, 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember')")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { $list_tahun = []; $list_bulan = []; }

// 🚀 FILTER DATA KPI
try { 
    $sql = "SELECT * FROM mbos_kpi WHERE 1=1"; $params = [];
    if ($filter_tahun != '') { $sql .= " AND tahun = :tahun"; $params[':tahun'] = $filter_tahun; }
    if ($filter_bulan != '') { $sql .= " AND bulan = :bulan"; $params[':bulan'] = $filter_bulan; }
    $sql .= " ORDER BY tanggal DESC"; 
    $stmt = $pdo->prepare($sql); $stmt->execute($params); 
    $kpi_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $kpi_data = []; }

// === PANGGIL HEADER TERPUSAT ===
$page_title = "H2 BASE — Logistik & Report KPI";
$active_page = "kpi";
require 'header.php';
?>

<?= $pesan ?>

<style>
    /* 🚀 FILTER ACTION PANEL RE-DESIGN */
    .filter-box { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
    .filter-box .form-group { margin-bottom: 0; min-width: 150px; }
    .filter-box .btn-group-filter { display: flex; gap: 10px; }

    /* 🚀 FORM INPUT FIELD (DITERANGIN/DIPERBERSIH SESUAI PERINTAH) */
    input, select { 
        background: #ffffff !important; 
        border: 1px solid #cbd5e1 !important; 
        border-radius: 8px !important; 
        padding: 10px 14px !important; 
        font-size: 13px !important; 
        color: #0f172a !important; 
        font-weight: 700 !important;
        box-sizing: border-box !important;
        transition: all 0.2s ease-in-out !important;
    }
    input:focus, select:focus { 
        border-color: #0ea5e9 !important; 
        background: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(14,165,233,0.15) !important; 
    }

    /* 🚀 RE-DESIGN TOMBOL "KIRIM MASUK DATABASE" (PREMIUM PRIMARY GREEN) */
    .btn-submit-modern {
        background: #10b981 !important; 
        color: #ffffff !important;
        border: none !important;
        padding: 12px 28px !important;
        border-radius: 8px !important;
        font-size: 14px !important;
        font-weight: 800 !important;
        cursor: pointer !important;
        transition: all 0.2s ease-in-out !important;
        box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2) !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    .btn-submit-modern:hover {
        background: #059669 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3) !important;
    }

    /* 🚀 RE-DESIGN TOMBOL "🔍 CARI DATA" (PREMIUM SLATE BLUE) */
    .btn-search-modern {
        background: #0f172a !important; 
        color: #ffffff !important;
        border: none !important;
        padding: 10px 22px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        transition: all 0.2s ease-in-out !important;
        box-shadow: 0 2px 4px rgba(15, 23, 42, 0.05) !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    .btn-search-modern:hover {
        background: #1e293b !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15) !important;
    }

    /* TOMBOL BATAL MODERN */
    .btn-batal-modern {
        background: #ffffff !important;
        color: #475569 !important;
        border: 1px solid #cbd5e1 !important;
        padding: 10px 22px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: all 0.2s !important;
    }
    .btn-batal-modern:hover {
        background: #f1f5f9 !important;
        color: #0f172a !important;
    }

    /* 🚀 STYLE TOGGLE FORM (TAMBAHAN BARU) */
    .form-toggle-header { user-select: none; transition: background 0.2s; padding: 12px 16px; border-radius: 8px; margin: -10px -10px 15px -10px; cursor: pointer; }
    .form-toggle-header:hover { background: #f8fafc; }

    /* 🚀 LAYOUT KOTAK INPUT KPI - DIKEMBALIKAN KE 3 KOLOM SEJAJAR (LIGHT MODE) */
    .kpi-input-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px; }
    .kpi-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
    .kpi-box-h1 { border-top: 5px solid #0ea5e9; }
    .kpi-box-h2 { border-top: 5px solid #10b981; }
    .kpi-box-hasil { background: #fffbeb; border-top: 5px solid #f59e0b; border-color: #fde68a; }
    
    .kpi-box h3 { margin-top: 0; margin-bottom: 20px; font-size: 15px; font-weight: 800; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; text-transform: uppercase;}
    .kpi-box .form-item { margin-bottom: 16px; }
    .kpi-box .form-item:last-child { margin-bottom: 0; }
    .kpi-box label { font-size: 11px; font-weight: 800; color: #64748b; display: block; margin-bottom: 8px; letter-spacing: 0.5px;}

    /* 🚀 LAYOUT TABEL KHUSUS KPI (DIPERJELAS TULISANNYA) */
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #0f172a; white-space: nowrap; min-width: 1200px; }
    .table-premium th, .table-premium td { padding: 12px 14px; text-align: right; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; font-weight: 600; }
    .table-premium th { color: #ffffff; font-weight: 700; position: sticky; z-index: 10; font-size: 11px; border-bottom: 2px solid #0f172a; text-align: center; letter-spacing: 0.5px;}
    .table-premium thead tr:nth-child(1) th { top: 0; }
    .table-premium thead tr:nth-child(2) th { top: 38px; }
    .table-premium tbody tr:nth-child(even) td { background-color: #f8fafc; }
    .table-premium tbody tr:hover td { background-color: #f1f5f9 !important; }
    
    .stk-1 { position: sticky; left: 0; z-index: 5; background: #fff; font-weight: 800; border-right: 2px solid #cbd5e1 !important; text-align: center !important;}
    .table-premium thead th.stk-1 { background: #0f172a; z-index: 12;}
    .table-premium tbody tr:nth-child(even) td.stk-1 { background: #f8fafc; }

    @media (max-width: 768px) {
        .filter-box { flex-direction: column; align-items: stretch; }
        .filter-box .btn-group-filter { flex-direction: column; width: 100%; }
        .kpi-input-grid { grid-template-columns: 1fr; }
        .btn-submit-modern, .btn-batal-modern { width: 100%; text-align: center; }
    }
</style>

<div class="card" <?= $is_edit ? 'style="border-top: 5px solid #0ea5e9; background: #f0f9ff;"' : 'style="border-top: 5px solid #10b981;"' ?>>
    <!-- 🚀 HEADER TOGGLE FORM -->
    <div class="form-toggle-header" onclick="toggleFormInput()">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid <?= $is_edit ? '#bae6fd' : '#a7f3d0' ?>; padding-bottom: 12px;">
            <h2 id="formTitle" style="margin:0; font-size: 18px; color: <?= $is_edit ? '#0284c7' : '#047857' ?>; border-bottom: none; padding-bottom: 0;">
                <?= $is_edit ? "✏️ Perbarui Data Pencapaian KPI" : "🎯 Form Input Target & Capaian KPI Harian" ?>
            </h2>
            <span id="formToggleIcon" style="font-size:13px; font-weight:900; color:<?= $is_edit ? '#0284c7' : '#047857' ?>; background:<?= $is_edit ? '#e0f2fe' : '#f0fdf4' ?>; padding:4px 10px; border-radius:6px; border:1px solid <?= $is_edit ? '#bae6fd' : '#bbf7d0' ?>;">
                ▼ KLIK UNTUK BUKA/TUTUP FORM
            </span>
        </div>
    </div>
    
    <!-- 🚀 WRAPPER FORM YANG BISA DI-TOGGLE -->
    <div id="formCollapsibleContent" style="display: none; margin-top: 15px;">
        <form method="POST" action="">
            <?php if ($is_edit): ?><input type="hidden" name="is_edit" value="1"><input type="hidden" name="id_edit" value="<?= $id_edit ?>"><?php endif; ?>

            <div style="margin-bottom: 24px; width: 300px; max-width: 100%;">
                <label style="display: block; font-size: 12px; font-weight: 800; color: #475569; margin-bottom: 8px; text-transform: uppercase;">Tanggal Operasional</label>
                <input type="date" name="tanggal" value="<?= $tanggal ?>" required style="width: 100%;">
            </div>

            <div class="kpi-input-grid">
                <!-- WILAYAH H1 (Desain Baru) -->
                <div class="kpi-box kpi-box-h1">
                    <h3 style="color: #0369a1;">🏭 Wilayah H1</h3>
                    <div class="form-item"><label>H1 TSTB (TON)</label><input type="number" step="0.01" name="h1_tstb" id="h1_tstb" value="<?= $h1_tstb ?>" required oninput="calcKPI()"></div>
                    <div class="form-item"><label>TARGET TSTB H1 (TON)</label><input type="number" step="0.01" name="h1_target" id="h1_target" value="<?= $h1_target ?>" required oninput="calcKPI()"></div>
                    <div class="form-item"><label>KIRIM H1 (TON)</label><input type="number" step="0.01" name="h1_kirim" id="h1_kirim" value="<?= $h1_kirim ?>" required oninput="calcKPI()"></div>
                </div>

                <!-- WILAYAH H2 (Desain Baru) -->
                <div class="kpi-box kpi-box-h2">
                    <h3 style="color: #15803d;">🏭 Wilayah H2</h3>
                    <div class="form-item"><label>H2 TSTB (TON)</label><input type="number" step="0.01" name="h2_tstb" id="h2_tstb" value="<?= $h2_tstb ?>" required oninput="calcKPI()"></div>
                    <div class="form-item"><label>TARGET TSTB H2 (TON)</label><input type="number" step="0.01" name="h2_target" id="h2_target" value="<?= $h2_target ?>" required oninput="calcKPI()"></div>
                    <div class="form-item"><label>KIRIM H2 (TON)</label><input type="number" step="0.01" name="h2_kirim" id="h2_kirim" value="<?= $h2_kirim ?>" required oninput="calcKPI()"></div>
                </div>

                <!-- 🚀 KOTAK HASIL KALKULASI (Dikembalikan 3 Sejajar Light Mode) -->
                <div class="kpi-box kpi-box-hasil">
                    <h3 style="color: #d97706; border-bottom-color: #fde68a; text-align: center;">🚀 SEKTOR PENGIRIMAN</h3>
                    
                    <div style="text-align: center; margin: 15px 0 20px 0; width: 100%;">
                        <span style="display: block; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">🎯 Total Target (TON)</span>
                        <div style="background: #fff7ed; border: 1px solid #ffedd5; border-radius: 8px; padding: 10px; display: inline-block; min-width: 120px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                            <span id="view_total_target" style="display: block; font-size: 28px; font-weight: 900; color: #ea580c; line-height: 1;">0</span>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; width: 100%; background: #ffffff; border-radius: 10px; padding: 15px 0; border: 1px solid #fde68a;">
                        <div style="text-align: center; flex: 1; border-right: 1px dashed #fde68a;">
                            <span style="display: block; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">🚚 Persen Kirim</span>
                            <span id="view_persen" style="display: block; font-size: 20px; font-weight: 900; color: #10b981; margin-top: 6px;">0.0%</span>
                        </div>
                        <div style="text-align: center; flex: 1;">
                            <span style="display: block; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">📦 Persen TSTB</span>
                            <span id="view_persen_tstb" style="display: block; font-size: 20px; font-weight: 900; color: #0ea5e9; margin-top: 6px;">0.0%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div style="display:flex; gap: 15px; justify-content: flex-end;">
                <?php if ($is_edit): ?><a href="kpi.php" class="btn-batal-modern">Batal Edit</a><?php endif; ?>
                <button type="submit" class="btn-submit-modern" style="<?= $is_edit ? 'background:#0ea5e9 !important; box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.2) !important;' : '' ?>">
                    <?= $is_edit ? "💾 Simpan Perubahan Data" : "💾 Kirim Masuk Database" ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">
        <div>
            <h2 style="margin:0; border:none; padding:0;">📊 Histori Pencapaian KPI & Logistik</h2>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-top: 4px;">Data Filter: <strong style="color:#0ea5e9; font-size: 15px;"><?= $filter_bulan ?> <?= $filter_tahun ?></strong></div>
        </div>
    </div>

    <!-- FILTER PENCARIAN -->
    <form class="filter-box" method="GET" action="">
        <div class="form-group">
            <label>Tahun</label>
            <select name="filter_tahun">
                <option value="">Semua Tahun</option>
                <?php foreach ($list_tahun as $th): ?>
                    <option value="<?= $th ?>" <?= $filter_tahun == $th ? 'selected' : '' ?>><?= $th ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Bulan</label>
            <select name="filter_bulan">
                <option value="">Semua Bulan</option>
                <?php foreach ($list_bulan as $bln): ?>
                    <option value="<?= $bln ?>" <?= $filter_bulan == $bln ? 'selected' : '' ?>><?= $bln ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="btn-group-filter">
            <button type="submit" class="btn-search-modern">🔍 Cari Data</button>
            <?php if(isset($_GET['filter_tahun'])): ?>
                <a href="kpi.php" class="btn-batal-modern">✖ Reset Filter</a>
            <?php endif; ?>
        </div>
    </form>
    
    <div class="table-responsive">
        <table class="table-premium" id="tabel-data">
            <thead>
                <tr>
                    <th class="stk-1" rowspan="2" style="width:85px;">Tanggal</th>
                    <th colspan="3" style="background:#1e3a8a;">Wilayah H1</th>
                    <th colspan="3" style="background:#166534;">Wilayah H2</th>
                    <th colspan="4" style="background:#475569;">Total Gabungan</th>
                    <th colspan="2" style="background:#ea580c;">Pengiriman</th>
                    <th rowspan="2" style="width:100px; background:#0f172a;" data-noexport="true">Aksi</th>
                </tr>
                <tr>
                    <th style="background:#3b82f6;">TSTB</th><th style="background:#3b82f6;">Target</th><th style="background:#3b82f6;">Kirim</th>
                    <th style="background:#22c55e;">TSTB</th><th style="background:#22c55e;">Target</th><th style="background:#22c55e;">Kirim</th>
                    <th style="background:#64748b; color:#38bdf8;">TSTB</th><th style="background:#64748b;">Target</th><th style="background:#64748b; color:#c084fc;">Kirim</th><th style="background:#64748b;">%</th>
                    <th style="background:#f97316; color:#fef08a;">Total</th><th style="background:#f97316;">%</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tot_h1_tstb = 0; $tot_h1_target = 0; $tot_h1_kirim = 0; 
                $tot_h2_tstb = 0; $tot_h2_target = 0; $tot_h2_kirim = 0;
                $tot_gab_tstb = 0; $tot_gab_target = 0; $tot_pengiriman = 0;

                if (count($kpi_data) > 0): 
                    foreach ($kpi_data as $row):
                        $h1_tstb = $row['h1_tstb']; $h1_target = $row['h1_target']; $h1_kirim = $row['h12_kirim']; 
                        $h2_tstb = $row['h2_tstb']; $h2_target = $row['h2_target']; $h2_kirim = $row['h2_kirim'];
                        $gab_tstb = $h1_tstb + $h2_tstb; $gab_target = $h1_target + $h2_target;
                        $gab_persen = $gab_target > 0 ? ($gab_tstb / $gab_target) * 100 : 0;
                        $pengiriman_tot = $h1_kirim + $h2_kirim; 
                        $pengiriman_persen = $gab_target > 0 ? ($pengiriman_tot / $gab_target) * 100 : 0;
                        
                        $tot_h1_tstb += $h1_tstb; $tot_h1_target += $h1_target; $tot_h1_kirim += $h1_kirim;
                        $tot_h2_tstb += $h2_tstb; $tot_h2_target += $h2_target; $tot_h2_kirim += $h2_kirim;
                        $tot_gab_tstb += $gab_tstb; $tot_gab_target += $gab_target; $tot_pengiriman += $pengiriman_tot;
                ?>
                    <tr>
                        <td class="stk-1"><?= date('d', strtotime($row['tanggal'])) ?></td>
                        
                        <td><?= number_format($h1_tstb, 1, ',', '.') ?></td>
                        <td><?= number_format($h1_target, 0, ',', '.') ?></td>
                        <td><?= number_format($h1_kirim, 1, ',', '.') ?></td>
                        
                        <td><?= number_format($h2_tstb, 1, ',', '.') ?></td>
                        <td><?= number_format($h2_target, 0, ',', '.') ?></td>
                        <td><?= number_format($h2_kirim, 1, ',', '.') ?></td>
                        
                        <td style="font-weight:800; background-color:#f0f9ff; color:#0369a1;"><?= number_format($gab_tstb, 1, ',', '.') ?></td>
                        <td><?= number_format($gab_target, 0, ',', '.') ?></td>
                        <td style="font-weight:800; background-color:#faf5ff; color:#7e22ce;"><?= number_format($pengiriman_tot, 1, ',', '.') ?></td>
                        <td style="font-weight:800; background-color:#f8fafc;"><?= number_format($gab_persen, 1, ',', '.') ?>%</td>
                        
                        <td style="font-weight:800; background-color:#fffbeb; color:#b45309;"><?= number_format($pengiriman_tot, 1, ',', '.') ?></td>
                        <td style="font-weight:900; background-color:#f0fdf4; color:#15803d;"><?= number_format($pengiriman_persen, 1, ',', '.') ?>%</td>
                        
                        <td class="text-center" data-noexport="true">
                            <?php if ($user_role != 'Viewer'): ?>
                                <a href="kpi.php?edit=<?= $row['id'] ?>&filter_bulan=<?= $filter_bulan ?>&filter_tahun=<?= $filter_tahun ?>" class="btn-edit" style="color: #0ea5e9; font-weight:700; font-size:11px; padding:6px 10px; border-radius:6px; border:1px solid #bae6fd; background:#e0f2fe; text-decoration:none;">Edit</a>
                                <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Hapus permanen data KPI ini?')" class="btn-hapus" style="color: #dc2626; font-weight:700; font-size:11px; padding:6px 10px; border-radius:6px; border:1px solid #fecaca; background:#fee2e2; text-decoration:none; margin-left:4px;">Hapus</a>
                            <?php else: ?>
                                <span style="font-size:10px; color:#94a3b8;">Terbatas</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; 
                    $tot_gab_persen = $tot_gab_target > 0 ? ($tot_gab_tstb / $tot_gab_target) * 100 : 0;
                    $tot_pengiriman_persen = $tot_gab_target > 0 ? ($tot_pengiriman / $tot_gab_target) * 100 : 0;
                ?>
                    <tr class="total-row">
                        <td class="stk-1" style="background:#cbd5e1 !important; color:#0f172a; text-align:right !important; padding-right:15px;">TOTAL</td>
                        <td><?= number_format($tot_h1_tstb, 1, ',', '.') ?></td><td><?= number_format($tot_h1_target, 0, ',', '.') ?></td><td><?= number_format($tot_h1_kirim, 1, ',', '.') ?></td>
                        <td><?= number_format($tot_h2_tstb, 1, ',', '.') ?></td><td><?= number_format($tot_h2_target, 0, ',', '.') ?></td><td><?= number_format($tot_h2_kirim, 1, ',', '.') ?></td>
                        <td style="color:#0369a1; background-color: #e0f2fe; font-weight: 900;"><?= number_format($tot_gab_tstb, 1, ',', '.') ?></td><td><?= number_format($tot_gab_target, 0, ',', '.') ?></td><td style="color:#7e22ce; background-color: #f3e8ff; font-weight: 900;"><?= number_format($tot_pengiriman, 1, ',', '.') ?></td><td style="background-color: #e2e8f0; font-weight: 900;"><?= number_format($tot_gab_persen, 1, ',', '.') ?>%</td>
                        <td style="color:#b45309; background-color: #fef3c7; font-weight: 900;"><?= number_format($tot_pengiriman, 1, ',', '.') ?></td><td style="color:#16a34a; background-color:#dcfce7; font-size:14px; font-weight: 900;"><?= number_format($tot_pengiriman_persen, 1, ',', '.') ?>%</td>
                        <td class="text-center" data-noexport="true" style="background:#e2e8f0;">-</td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="14" class="text-center" style="padding:40px; color:#94a3b8; font-weight:600;">⚠️ Tidak ada data laporan KPI yang cocok dengan filter pencarian saat ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="formHapusGlobal" method="POST" style="display: none;">
    <input type="hidden" name="hapus_id" id="inputHapusId">
    <input type="hidden" name="hapus_data" value="1">
</form>

<script>
    // 🚀 FUNGSI TOGGLE SAKTI (BARU DITAMBAHKAN)
    function toggleFormInput() {
        let content = document.getElementById('formCollapsibleContent');
        let icon = document.getElementById('formToggleIcon');
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.innerText = '▲ KLIK UNTUK TUTUP PANEL';
            localStorage.setItem('kpiFormState', 'open');
        } else {
            content.style.display = 'none';
            icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
            localStorage.setItem('kpiFormState', 'closed');
        }
    }

    function konfirmasiHapus(id, pesan) {
        if (confirm(pesan)) {
            document.getElementById('inputHapusId').value = id;
            document.getElementById('formHapusGlobal').submit();
        }
    }

    function calcKPI() {
        const h1_tgt = parseFloat(document.getElementById('h1_target').value) || 0;
        const h2_tgt = parseFloat(document.getElementById('h2_target').value) || 0;
        const h1_krm = parseFloat(document.getElementById('h1_kirim').value) || 0;
        const h2_krm = parseFloat(document.getElementById('h2_kirim').value) || 0;
        const h1_tstb = parseFloat(document.getElementById('h1_tstb').value) || 0;
        const h2_tstb = parseFloat(document.getElementById('h2_tstb').value) || 0;
        
        const total_target = h1_tgt + h2_tgt; 
        const total_kirim = h1_krm + h2_krm; 
        const total_tstb = h1_tstb + h2_tstb;
        
        const persen_kirim = total_target > 0 ? (total_kirim / total_target) * 100 : 0;
        const persen_tstb = total_target > 0 ? (total_tstb / total_target) * 100 : 0;
        
        document.getElementById('view_total_target').innerText = total_target.toLocaleString('id-ID');
        document.getElementById('view_persen').innerText = persen_kirim.toFixed(1) + "%";
        document.getElementById('view_persen_tstb').innerText = persen_tstb.toFixed(1) + "%";
    }

    // 🚀 BACA MEMORI LOCALSTORAGE UNTUK TOGGLE PANEL + KALKULATOR
    window.onload = function() {
        calcKPI();
        
        let formState = localStorage.getItem('kpiFormState');
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
