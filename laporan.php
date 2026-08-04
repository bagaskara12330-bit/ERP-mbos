<?php
require_once 'auth.php';
require_akses('prod_lap');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false;

// 🚀 KAMUS BULAN GLOBAL (Agar bisa dipakai untuk Filter Otomatis dan Simpan Data)
$nama_bulan_id = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];

// Variabel default dideklarasikan SATU KALI saja agar bersih
$id_edit = ""; $tanggal = date('Y-m-d'); $regu = ""; $shift = "1"; $jam_kerja = ""; $dt = "0"; $target_produksi = ""; $kg_produksi = ""; $tstb = ""; $pakai_kertas = "";

// 🚀 LOGIKA HAPUS DATA AMAN (MANDIRI)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id']) && $user_role != 'Viewer') {
    $id = intval($_POST['hapus_id']);
    
    // Tarik nama regu untuk dicatat di log notifikasi
    $stmt_nama = $pdo->prepare("SELECT regu FROM mbos_regu WHERE id = ?"); 
    $stmt_nama->execute([$id]); $nama_rg = $stmt_nama->fetchColumn();
    
    $pdo->prepare("DELETE FROM mbos_regu WHERE id = ?")->execute([$id]);
    catatLog($pdo, $user_aktif, "Menghapus Laporan Produksi regu $nama_rg.", "🗑️");
    header("Location: laporan.php?pesan=hapus_sukses"); exit();
}

if (isset($_GET['edit']) && $user_role != 'Viewer') {
    $is_edit = true; $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM mbos_regu WHERE id = :id"); $stmt->execute([':id' => $id_edit]);
    $data_lama = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data_lama) {
        $tanggal = $data_lama['tanggal']; $regu = $data_lama['regu']; $shift = isset($data_lama['shift']) ? $data_lama['shift'] : "1"; $jam_kerja = $data_lama['jam_kerja']; $dt = $data_lama['dt'];
        $target_produksi = $data_lama['target_produksi']; $kg_produksi = $data_lama['kg_produksi']; $tstb = $data_lama['tstb']; $pakai_kertas = $data_lama['pakai_kertas'];
    }
}

// 🚀 LOGIKA SIMPAN & UPDATE DATA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tanggal']) && $user_role != 'Viewer') {
    $tanggal = $_POST['tanggal']; $regu = $_POST['regu']; $shift = $_POST['shift']; $jam_kerja = floatval($_POST['jam_kerja']); $dt = floatval($_POST['dt']);
    $target_produksi = floatval($_POST['target_produksi']); $kg_produksi = floatval($_POST['kg_produksi']); $tstb = intval($_POST['tstb']); $pakai_kertas = floatval($_POST['pakai_kertas']);

    $cek_sql = "SELECT COUNT(*) FROM mbos_regu WHERE tanggal = :tanggal AND regu = :regu AND shift = :shift";
    $cek_params = [':tanggal' => $tanggal, ':regu' => $regu, ':shift' => $shift];
    if ($is_edit) { $cek_sql .= " AND id != :id"; $cek_params[':id'] = $id_edit; }
    
    $stmt_cek = $pdo->prepare($cek_sql); $stmt_cek->execute($cek_params);
    if ($stmt_cek->fetchColumn() > 0) {
        $pesan = "<div class='alert alert-danger'>❌ Gagal: Laporan Regu $regu (Shift $shift) tgl " . date('d-m-Y', strtotime($tanggal)) . " sudah ada!</div>";
    } else {
        $waste = $pakai_kertas - $kg_produksi;
        $w_persen = ($pakai_kertas > 0) ? ($waste / $pakai_kertas) * 100 : 0;
        $capaian = ($target_produksi > 0) ? ($kg_produksi / $target_produksi) * 100 : 0;
        
        $split_tgl = explode('-', $tanggal); $tahun = intval($split_tgl[0]); $bulan = $nama_bulan_id[$split_tgl[1]];

        try {
            if ($is_edit) {
                $sql = "UPDATE mbos_regu SET tahun=:tahun, tanggal=:tanggal, bulan=:bulan, regu=:regu, shift=:shift, jam_kerja=:jam_kerja, dt=:dt, target_produksi=:target_produksi, kg_produksi=:kg_produksi, tstb=:tstb, pakai_kertas=:pakai_kertas, waste=:waste, w_persen=:w_persen, capaian=:capaian WHERE id=:id";
                $pdo->prepare($sql)->execute([':tahun'=>$tahun,':tanggal'=>$tanggal,':bulan'=>$bulan,':regu'=>$regu,':shift'=>$shift,':jam_kerja'=>$jam_kerja,':dt'=>$dt,':target_produksi'=>$target_produksi,':kg_produksi'=>$kg_produksi,':tstb'=>$tstb,':pakai_kertas'=>$pakai_kertas,':waste'=>$waste,':w_persen'=>$w_persen,':capaian'=>$capaian, ':id'=>$id_edit]);
                
                catatLog($pdo, $user_aktif, "Mengubah Laporan Produksi Mesin/Regu $regu (Shift $shift) untuk tanggal $tanggal.", "✏️");
                header("Location: laporan.php?pesan=edit_sukses"); exit();
            } else {
                $sql = "INSERT INTO mbos_regu (tahun,tanggal,bulan,regu,shift,jam_kerja,dt,target_produksi,kg_produksi,tstb,pakai_kertas,waste,w_persen,capaian) VALUES (:tahun,:tanggal,:bulan,:regu,:shift,:jam_kerja,:dt,:target_produksi,:kg_produksi,:tstb,:pakai_kertas,:waste,:w_persen,:capaian)";
                $pdo->prepare($sql)->execute([':tahun'=>$tahun,':tanggal'=>$tanggal,':bulan'=>$bulan,':regu'=>$regu,':shift'=>$shift,':jam_kerja'=>$jam_kerja,':dt'=>$dt,':target_produksi'=>$target_produksi,':kg_produksi'=>$kg_produksi,':tstb'=>$tstb,':pakai_kertas'=>$pakai_kertas,':waste'=>$waste,':w_persen'=>$w_persen,':capaian'=>$capaian]);
                
                catatLog($pdo, $user_aktif, "Menambahkan Laporan Produksi baru untuk Mesin/Regu $regu (Shift $shift) dengan Capaian ".number_format($capaian,1)."%.", "⚙️");
                $pesan = "<div class='alert alert-success'>✅ Laporan produksi berhasil disimpan!</div>";
                $jam_kerja = ""; $dt = "0"; $target_produksi = ""; $kg_produksi = ""; $tstb = ""; $pakai_kertas = "";
            }
        } catch (PDOException $e) { $pesan = "<div class='alert alert-danger'>❌ Gagal: " . $e->getMessage() . "</div>"; }
    }
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success'>✅ Perubahan data berhasil diperbarui!</div>";
    elseif ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-success'>🗑️ Data laporan berhasil dihapus!</div>";
}

try {
    $list_tahun = $pdo->query("SELECT DISTINCT tahun FROM mbos_regu ORDER BY tahun DESC")->fetchAll(PDO::FETCH_COLUMN);
    $list_bulan = $pdo->query("SELECT DISTINCT bulan FROM mbos_regu ORDER BY FIELD(bulan, 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember')")->fetchAll(PDO::FETCH_COLUMN);
    $list_regu  = $pdo->query("SELECT DISTINCT regu FROM mbos_regu ORDER BY regu ASC")->fetchAll(PDO::FETCH_COLUMN);
    $master_regu = $pdo->query("SELECT nama_regu FROM master_regu_nama WHERE status = 'Aktif' ORDER BY nama_regu ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { $master_regu = ['TASKIM', 'SAMSUL']; }

// 🚀 DEFAULT FILTER DIUBAH KE BULAN DAN TAHUN SAAT INI (Agar data tidak menumpuk)
$filter_tahun = isset($_GET['filter_tahun']) ? $_GET['filter_tahun'] : date('Y');
$filter_bulan = isset($_GET['filter_bulan']) ? $_GET['filter_bulan'] : $nama_bulan_id[date('m')];
$filter_regu  = isset($_GET['filter_regu']) ? $_GET['filter_regu'] : '';

// 🛡️ LIMITASI DIHAPUS (Biar data nggak tiba-tiba hilang saat ditarik HRD)
try {
    $sql = "SELECT * FROM mbos_regu WHERE 1=1"; $params = [];
    if ($filter_tahun != '') { $sql .= " AND tahun = :tahun"; $params[':tahun'] = $filter_tahun; }
    if ($filter_bulan != '') { $sql .= " AND bulan = :bulan"; $params[':bulan'] = $filter_bulan; }
    if ($filter_regu != '')  { $sql .= " AND regu = :regu";   $params[':regu'] = $filter_regu; }
    $sql .= " ORDER BY tanggal DESC, regu ASC"; 
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $daftar_regu = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$total_jam_kerja = 0; $total_dt = 0; $total_produksi = 0; $total_tstb = 0; $total_kertas = 0; $total_target = 0;
foreach ($daftar_regu as $row) {
    $total_jam_kerja += $row['jam_kerja']; $total_dt += $row['dt']; $total_produksi += $row['kg_produksi'];
    $total_tstb += $row['tstb']; $total_kertas += $row['pakai_kertas']; $total_target += $row['target_produksi'];
}
$total_waste = $total_kertas - $total_produksi;
$total_w_persen = ($total_kertas > 0) ? ($total_waste / $total_kertas) * 100 : 0;
$total_capaian = ($total_target > 0) ? ($total_produksi / $total_target) * 100 : 0;
$total_badge_style = $total_capaian >= 100 ? 'color: #16a34a; background: #dcfce7;' : ($total_capaian >= 85 ? 'color: #ea580c; background: #ffedd5;' : 'color: #dc2626; background: #fee2e2;');
$total_warna_waste = ($total_waste > 2000 || $total_w_persen > 10) ? '#dc2626' : '#16a34a';

$page_title = "H2 BASE — Input Laporan Harian";
$active_page = "laporan";
require 'header.php';
?>

<style>
    /* 🚀 RE-DESIGN TOTAL DNA MODERN SAAS (WHITE CLEAN FIELD) */
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; min-width: 140px; }
    .form-group label { margin-bottom: 8px; font-weight: 800; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;}
    
    input, select { 
        background: #ffffff !important; 
        border: 1px solid #cbd5e1 !important; 
        border-radius: 8px !important; 
        padding: 11px 14px !important; 
        font-size: 13px !important; 
        color: #0f172a !important; 
        font-weight: 600 !important;
        box-sizing: border-box !important;
        transition: all 0.2s ease-in-out !important;
        width: 100%;
    }
    input:focus, select:focus { 
        border-color: #0ea5e9 !important; 
        background: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(14,165,233,0.15) !important; 
        outline: none;
    }

    /* 🚀 PREMIUM BUTTONS */
    .btn-group { display: flex; gap: 15px; justify-content: flex-end; align-items: center; margin-top: 15px; }
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

    .btn-edit { display: inline-block; background: #e0f2fe; color: #0284c7; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #bae6fd; cursor: pointer;}
    .btn-edit:hover { background: #bae6fd; color: #0369a1; }
    .btn-hapus { display: inline-block; background: #fee2e2; color: #dc2626; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #fecaca; margin-left: 4px; cursor: pointer;}
    .btn-hapus:hover { background: #fecaca; color: #b91c1c; }

    /* 🚀 STYLE TOGGLE FORM (SAMA DENGAN NC) */
    .form-toggle-header { user-select: none; transition: background 0.2s; padding: 12px 16px; border-radius: 8px; margin: -10px -10px 15px -10px; cursor: pointer; }
    .form-toggle-header:hover { background: #f8fafc; }

    /* 🚀 FILTER AREA MODERN */
    .filter-box { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
    .filter-box .form-group { margin-bottom: 0; min-width: 150px; }
    .filter-box .btn-group-filter { display: flex; gap: 10px; }

    /* WIDGET LIVE COUNTER LIGHT MODE */
    .live-calc-box { display: flex; flex-wrap: wrap; gap: 12px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac; padding: 20px; border-radius: 12px; margin-bottom: 24px; font-size: 12px; color: #166534; align-items: center; box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.1); }
    .live-calc-box > div { background: #ffffff; padding: 10px 16px; border-radius: 8px; border: 1px solid #bbf7d0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); font-weight: 700; display:flex; gap: 8px; align-items: center; }
    .live-calc-box > div span { font-size: 15px; color: #15803d; font-weight: 900 !important; }

    /* 🚀 FIX SAKTI: TABEL LAPORAN (RESPONSIVE STICKY COLUMN) */
    .table-responsive { background: white; border-radius: 10px; border: 1px solid #cbd5e1; overflow-x: auto; overflow-y: auto; max-height: 700px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03);}
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #0f172a; white-space: nowrap; min-width: 1300px; }
    .table-premium th, .table-premium td { padding: 12px 14px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; vertical-align: middle; font-weight: 600; }
    
    .table-premium th { background-color: #0f172a; color: #ffffff; font-weight: 700; position: sticky; top: 0; z-index: 10; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 2px solid #1e293b; text-align: center;}
    
    .stk-1 { position: sticky; left: 0; z-index: 5; width: 40px; min-width: 40px; text-align: center !important; background: #ffffff;}
    .stk-2 { position: sticky; left: 40px; z-index: 5; width: 50px; min-width: 50px; text-align: center !important; background: #ffffff;}
    .stk-3 { position: sticky; left: 90px; z-index: 5; width: 150px; min-width: 150px; border-right: 3px solid #cbd5e1 !important; text-align: left !important; background: #f0f9ff; padding-left: 15px; color: #0284c7;}
    
    .table-premium th.stk-1, .table-premium th.stk-2, .table-premium th.stk-3 { background-color: #0f172a; color: white; z-index: 11; border-right-color: #334155 !important;}
    
    tbody tr:nth-child(even) td { background-color: #f8fafc; }
    tbody tr:nth-child(even) td.stk-1, tbody tr:nth-child(even) td.stk-2 { background-color: #f8fafc; }
    tbody tr:nth-child(even) td.stk-3 { background-color: #e0f2fe; }
    tbody tr:hover td { background-color: #f1f5f9 !important; }
    
    .badge { padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 11px; display: inline-block; text-align: center; border: 1px solid transparent;}
    
    /* FIX SAKTI: TOTAL ROW STICKY Z-INDEX */
    .total-row td { background-color: #e2e8f0 !important; font-weight: 800; color: #0f172a; border-top: 2px solid #94a3b8; position: sticky; bottom: 0; z-index: 9;}
    .total-row td.stk-1, .total-row td.stk-2, .total-row td.stk-3 { z-index: 12 !important; } /* Persimpangan antara bottom dan left harus z-index tertinggi */
    
    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr !important; }
        .btn-group { flex-direction: column; width: 100%; }
        .filter-box { flex-direction: column; align-items: stretch; }
        .filter-box .btn-group-filter { flex-direction: column; width: 100%; }
        .btn-submit-modern, .btn-batal-modern, .btn-search-modern, .btn-excel-modern { width: 100%; text-align: center; justify-content: center; }
    }
</style>

<?= $pesan ?>

<?php if ($user_role != 'Viewer'): ?>
<div class="card" <?= $is_edit ? 'style="border-top: 5px solid #0ea5e9; background: #f0f9ff;"' : 'style="border-top: 5px solid #10b981;"' ?>>
    <!-- 🚀 HEADER TOGGLE FORM -->
    <div class="form-toggle-header" onclick="toggleFormInput()">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid <?= $is_edit ? '#bae6fd' : '#a7f3d0' ?>; padding-bottom: 12px;">
            <h2 id="formTitle" style="margin:0; font-size: 18px; color: <?= $is_edit ? '#0284c7' : '#047857' ?>; border-bottom: none; padding-bottom: 0;">
                <?= $is_edit ? "✏️ Edit Laporan Produksi" : "📝 Form Input Laporan Harian" ?>
            </h2>
            <span id="formToggleIcon" style="font-size:13px; font-weight:900; color:<?= $is_edit ? '#0284c7' : '#047857' ?>; background:<?= $is_edit ? '#e0f2fe' : '#f0fdf4' ?>; padding:4px 10px; border-radius:6px; border:1px solid <?= $is_edit ? '#bae6fd' : '#bbf7d0' ?>;">
                ▼ KLIK UNTUK BUKA/TUTUP FORM
            </span>
        </div>
    </div>

    <!-- 🚀 WRAPPER FORM YANG BISA DI-TOGGLE -->
    <div id="formCollapsibleContent" style="display: none; margin-top: 15px;">
        <form action="" method="POST" autocomplete="off">
            <?php if($is_edit): ?><input type="hidden" name="id_edit" value="<?= $id_edit ?>"><?php endif; ?>
            
            <div class="form-grid">
                <div class="form-group"><label>Tanggal Operasional</label><input type="date" name="tanggal" id="tanggal" value="<?= htmlspecialchars($tanggal) ?>" required></div>
                <div class="form-group"><label>Nama Regu / Mesin</label><select name="regu" required><option value="">-- Pilih Regu --</option><?php foreach($master_regu as $rg): ?><option value="<?= htmlspecialchars($rg) ?>" <?= $regu == $rg ? 'selected' : '' ?>><?= htmlspecialchars($rg) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label>Pilih Shift</label><select name="shift" required><option value="1" <?= $shift == '1' ? 'selected' : '' ?>>Shift 1</option><option value="2" <?= $shift == '2' ? 'selected' : '' ?>>Shift 2</option></select></div>
                <div class="form-group"><label>Jam Kerja (Menit)</label><input type="number" min="0" name="jam_kerja" value="<?= htmlspecialchars($jam_kerja) ?>" required></div>
                <div class="form-group"><label>Downtime (Menit)</label><input type="number" min="0" name="dt" value="<?= htmlspecialchars($dt) ?>" required></div>
                <div class="form-group"><label>Target Produksi (KG)</label><input type="number" min="0" step="0.01" id="target_produksi" name="target_produksi" value="<?= htmlspecialchars($target_produksi) ?>" required></div>
                <div class="form-group"><label>KG Produksi</label><input type="number" min="0" step="0.01" id="kg_produksi" name="kg_produksi" value="<?= htmlspecialchars($kg_produksi) ?>" required></div>
                <div class="form-group"><label>TSTB (Sheet/Box)</label><input type="number" min="0" name="tstb" value="<?= htmlspecialchars($tstb) ?>" required></div>
                <div class="form-group"><label>Pakai Kertas (KG)</label><input type="number" min="0" step="0.01" id="pakai_kertas" name="pakai_kertas" value="<?= htmlspecialchars($pakai_kertas) ?>" required></div>
            </div>
            
            <p style="margin: 12px 0 8px 0; font-size:13px; font-weight:800; color:#0f172a; text-transform: uppercase;">📊 Live Preview Hasil Produksi:</p>
            <div class="live-calc-box">
                <div>🗑️ Waste: <span id="live_waste">0 KG</span></div>
                <div>📉 Waste (%): <span id="live_w_persen">0.00%</span></div>
                <div>🎯 Capaian Target: <span id="live_capaian">0.0%</span></div>
            </div>
            
            <div class="btn-group">
                <?php if($is_edit): ?><a href="laporan.php" class="btn-batal-modern">Batal Edit</a><?php endif; ?>
                <button type="submit" class="btn-submit-modern" style="<?= $is_edit ? 'background:#0ea5e9 !important; box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.2) !important;' : '' ?>">
                    <?= $is_edit ? "💾 Simpan Perubahan" : "💾 Simpan Laporan Masuk" ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card" style="padding-bottom: 20px; margin-bottom: 12px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
        <h2 style="margin: 0; border: none; padding: 0; color: #0f172a;">📊 Tabel Riwayat Hasil Produksi <?= ($filter_bulan != '' || $filter_tahun != '') ? "<span style='color:#0ea5e9;'>($filter_bulan $filter_tahun)</span>" : "" ?></h2>
        
        <div class="btn-group-filter">
            <button type="button" onclick="exportKeExcel()" class="btn-excel-modern">📥 Export Excel</button>
        </div>
    </div>

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
        <div class="form-group">
            <label>Regu Mesin</label>
            <select name="filter_regu">
                <option value="">Semua Regu (Gabungan)</option>
                <?php foreach ($list_regu as $rg): ?>
                    <option value="<?= $rg ?>" <?= $filter_regu == $rg ? 'selected' : '' ?>><?= $rg ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="btn-group-filter">
            <button type="submit" class="btn-search-modern">🔍 Cari Data</button>
            <?php if(isset($_GET['filter_tahun'])): ?>
                <a href="laporan.php" class="btn-batal-modern">✖ Reset Filter</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-responsive">
    <table class="table-premium" id="tabelDataLaporan">
        <thead>
            <tr>
                <th class="text-center stk-1">No</th>
                <th class="text-center stk-2">Tanggal</th>
                <th class="text-left stk-3">Regu / Mesin</th>
                <th class="text-center">Shift</th>
                <th class="text-center">Jam Kerja</th>
                <th class="text-center">Downtime</th>
                <th class="text-center" style="background:#0ea5e9; color:#ffffff;">KG Produksi</th>
                <th class="text-center">TSTB</th>
                <th class="text-center">Pakai Kertas</th>
                <th class="text-center">Target (KG)</th>
                <th class="text-center">Waste (KG)</th>
                <th class="text-center">Waste (%)</th>
                <th class="text-center">Capaian</th>
                <th class="text-center" style="width: 140px;" data-noexport="true">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($daftar_regu) > 0): $no = 1; ?>
                <?php foreach ($daftar_regu as $row): 
                    $badge_style = $row['capaian'] >= 100 ? 'color: #16a34a; background: #dcfce7; border-color: #bbf7d0;' : ($row['capaian'] >= 85 ? 'color: #d97706; background: #fef3c7; border-color: #fde68a;' : 'color: #dc2626; background: #fee2e2; border-color: #fecaca;');
                    $warna_waste = ($row['waste'] > 2000 || $row['w_persen'] > 10) ? '#dc2626' : '#16a34a';
                ?>
                    <tr>
                        <td class="text-center stk-1" style="color: #64748b; font-weight: 800;"><?= $no++ ?></td>
                        <td class="text-center stk-2" style="font-weight: 700; color: #475569;"><?= date('d', strtotime($row['tanggal'])) ?></td>
                        <td class="text-left stk-3" style="font-weight: 800; color: #0f172a;"><?= htmlspecialchars($row['regu']) ?></td>
                        <td class="text-center" style="font-weight: 700; color: #475569;">Shift <?= htmlspecialchars($row['shift'] ?? '1') ?></td>
                        <td class="text-center"><?= number_format($row['jam_kerja'], 0, ',', '.') ?></td>
                        <td class="text-center"><?= number_format($row['dt'], 0, ',', '.') ?></td>
                        <td class="text-center" style="font-weight: 900; color: #0ea5e9; font-size: 14px; background: #f0f9ff;"><?= number_format($row['kg_produksi'], 0, ',', '.') ?></td>
                        <td class="text-center"><?= number_format($row['tstb'], 0, ',', '.') ?></td>
                        <td class="text-center"><?= number_format($row['pakai_kertas'], 0, ',', '.') ?></td>
                        <td class="text-center" style="color: #475569; font-weight: 700;"><?= number_format($row['target_produksi'], 0, ',', '.') ?></td>
                        <td class="text-center" style="color: <?= $warna_waste ?>; font-weight: 800;"><?= number_format($row['waste'], 0, ',', '.') ?></td>
                        <td class="text-center" style="color: <?= $warna_waste ?>; font-weight: 800;"><?= number_format($row['w_persen'], 2, ',', '.') ?>%</td>
                        <td class="text-center"><span class="badge" style="<?= $badge_style ?>"><?= number_format($row['capaian'], 1, ',', '.') ?>%</span></td>
                        <td class="text-center" data-noexport="true">
                            <?php if ($user_role != 'Viewer'): ?>
                                <a href="laporan.php?edit=<?= $row['id'] ?>&filter_tahun=<?= $filter_tahun ?>&filter_bulan=<?= $filter_bulan ?>&filter_regu=<?= $filter_regu ?>" class="btn-edit">Edit</a>
                                <a href="javascript:void(0);" onclick="konfirmasiHapusLaporan(<?= $row['id'] ?>, '<?= addslashes($row['regu']) ?>', 'Yakin ingin menghapus data Laporan ini secara permanen?')" class="btn-hapus">Hapus</a>
                            <?php else: ?>
                                <span style="font-size:10px; color:#94a3b8;">Terbatas</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                
                <!-- 🚀 PERBAIKAN STICKY PADA BARIS TOTAL (MEMISAHKAN COLSPAN) -->
                <tr class="total-row">
                    <td class="stk-1"></td>
                    <td class="stk-2"></td>
                    <td class="stk-3" style="text-align:right !important; padding-right:15px; color:#0f172a;">TOTAL RINGKASAN DATA</td>
                    <td></td>
                    <td class="text-center"><?= number_format($total_jam_kerja, 0, ',', '.') ?></td>
                    <td class="text-center"><?= number_format($total_dt, 0, ',', '.') ?></td>
                    <td class="text-center" style="color:#0ea5e9; font-size:15px; background: #e0f2fe !important;"><?= number_format($total_produksi, 0, ',', '.') ?></td>
                    <td class="text-center"><?= number_format($total_tstb, 0, ',', '.') ?></td>
                    <td class="text-center"><?= number_format($total_kertas, 0, ',', '.') ?></td>
                    <td class="text-center"><?= number_format($total_target, 0, ',', '.') ?></td>
                    <td class="text-center" style="color: <?= $total_warna_waste ?>;"><?= number_format($total_waste, 0, ',', '.') ?></td>
                    <td class="text-center" style="color: <?= $total_warna_waste ?>;"><?= number_format($total_w_persen, 2, ',', '.') ?>%</td>
                    <td class="text-center"><span class="badge" style="<?= $total_badge_style ?>; font-size:12px; padding: 6px 8px;"><?= number_format($total_capaian, 1, ',', '.') ?>%</span></td>
                    <td class="text-center" style="background-color: #e2e8f0 !important;" data-noexport="true">-</td>
                </tr>
            <?php else: ?>
                <tr><td colspan="14" class="text-center" style="padding: 40px; color: #94a3b8; font-size: 14px; font-weight: 600;">⚠️ Tidak ada data laporan yang cocok dengan filter pencarian saat ini.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<form id="formHapusLaporan" method="POST" style="display:none;">
    <input type="hidden" name="hapus_id" id="hapusIdLaporan">
    <input type="hidden" name="hapus_data" value="1">
</form>

<script>
    // 🚀 FUNGSI TOGGLE SAKTI (PERSIS SEPERTI DI NC)
    function toggleFormInput() {
        let content = document.getElementById('formCollapsibleContent');
        let icon = document.getElementById('formToggleIcon');
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.innerText = '▲ KLIK UNTUK TUTUP PANEL';
            localStorage.setItem('laporanFormState', 'open');
        } else {
            content.style.display = 'none';
            icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
            localStorage.setItem('laporanFormState', 'closed');
        }
    }

    function konfirmasiHapusLaporan(id, rg, pesan) {
        if(confirm(pesan)) {
            document.getElementById('hapusIdLaporan').value = id;
            document.getElementById('formHapusLaporan').submit();
        }
    }

    function hitungLiveProduksi() {
        const targetVal = parseFloat(document.getElementById('target_produksi').value) || 0;
        const kgProd = parseFloat(document.getElementById('kg_produksi').value) || 0;
        const pakaiKertas = parseFloat(document.getElementById('pakai_kertas').value) || 0;
        
        const waste = pakaiKertas - kgProd;
        const wPersen = pakaiKertas > 0 ? ((waste / pakaiKertas) * 100).toFixed(2) : "0.00";
        const capaian = targetVal > 0 ? ((kgProd / targetVal) * 100).toFixed(1) : "0.0";
        
        document.getElementById('live_waste').innerText = waste.toLocaleString('id-ID') + " KG";
        document.getElementById('live_w_persen').innerText = wPersen + "%";
        document.getElementById('live_capaian').innerText = capaian + "%";
    }
    
    // 🚀 FUNGSI EXPORT KE EXCEL BERSIH (TANPA KOLOM AKSI)
    function exportKeExcel() {
        let table = document.getElementById("tabelDataLaporan");
        if (!table) return;

        let cloneTable = table.cloneNode(true);

        let noExportTh = cloneTable.querySelectorAll('th[data-noexport="true"]');
        noExportTh.forEach(th => th.remove());
        
        let noExportTd = cloneTable.querySelectorAll('td[data-noexport="true"]');
        noExportTd.forEach(td => td.remove());

        let badges = cloneTable.querySelectorAll('.badge');
        badges.forEach(badge => {
            let spanTxt = document.createTextNode(badge.innerText);
            badge.parentNode.replaceChild(spanTxt, badge);
        });

        let htmlTemplate = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
                    th, td { border: 1px solid #000000; padding: 6px; text-align: right; font-size: 13px; vertical-align: middle; }
                    th { background-color: #0f172a; color: #ffffff; font-weight: bold; text-align: center; }
                    .text-left { text-align: left; }
                </style>
            </head>
            <body>
                <h2 style="text-align: center; margin-bottom: 15px;">Input Laporan Produksi - H2 BASE</h2>
                <p><strong>Diekspor pada:</strong> ${new Date().toLocaleDateString('id-ID')}</p>
                ${cloneTable.outerHTML}
            </body>
            </html>
        `;

        let blob = new Blob([htmlTemplate], { type: 'application/vnd.ms-excel' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'Laporan_Produksi_H2_' + new Date().toISOString().slice(0,10) + '.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    window.onload = function() {
        ['target_produksi', 'kg_produksi', 'pakai_kertas'].forEach(id => {
            const el = document.getElementById(id); 
            if(el) el.addEventListener('input', hitungLiveProduksi);
        });
        
        // Jalankan kalkulasi perdana saat halaman dimuat
        hitungLiveProduksi();

        // 🚀 BACA MEMORI LOCALSTORAGE UNTUK TOGGLE PANEL
        let formState = localStorage.getItem('laporanFormState');
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
