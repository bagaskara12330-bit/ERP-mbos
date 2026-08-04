<?php
require_once 'auth.php';
require_akses('inv_stok');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit_roll = false;

// VARIABEL DEFAULT UNTUK STOK ROLL HARIAN
$id_edit_roll = ''; $tgl_roll = date('Y-m-d'); 
$stok_awal = ''; $penerimaan = ''; $pemakaian = ''; $stok_akhir = ''; $stok_aktual = ''; $selisih_roll = '';
$is_readonly_awal = false;

try {
    // 🚀 TABEL MASTER: STOK ROLL HARIAN
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_stok_roll_harian (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        stok_awal DECIMAL(12,2) DEFAULT 0,
        stok_akhir DECIMAL(12,2) DEFAULT 0,
        selisih DECIMAL(12,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Auto-migrate tabel untuk penambahan kolom baru
    try { $pdo->exec("ALTER TABLE db_stok_roll_harian ADD COLUMN penerimaan DECIMAL(12,2) DEFAULT 0 AFTER stok_awal"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE db_stok_roll_harian ADD COLUMN pemakaian DECIMAL(12,2) DEFAULT 0 AFTER penerimaan"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE db_stok_roll_harian ADD COLUMN stok_aktual DECIMAL(12,2) DEFAULT 0 AFTER stok_akhir"); } catch (PDOException $e) {}
} catch (PDOException $e) {}

// ====================================================================================
// 🚀 LOGIKA BACKEND: STOK ROLL HARIAN
// ====================================================================================
if (isset($_GET['edit_roll']) && $user_role != 'Viewer') {
    $is_edit_roll = true; $id_edit_roll = intval($_GET['edit_roll']);
    $stmt = $pdo->prepare("SELECT * FROM db_stok_roll_harian WHERE id = ?"); $stmt->execute([$id_edit_roll]);
    $rowR = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($rowR) {
        $tgl_roll = $rowR['tanggal']; $stok_awal = $rowR['stok_awal']; $penerimaan = $rowR['penerimaan'] ?? 0;
        $pemakaian = $rowR['pemakaian'] ?? 0; $stok_akhir = $rowR['stok_akhir']; $stok_aktual = $rowR['stok_aktual'] ?? 0;
        $selisih_roll = $rowR['selisih'];
        
        // Cek apakah ini input pertama kali (ID paling kecil)
        $stmt_min = $pdo->query("SELECT MIN(id) FROM db_stok_roll_harian");
        $min_id = $stmt_min->fetchColumn();
        $is_readonly_awal = ($id_edit_roll != $min_id); // Readonly kecuali ini ID pertama
    }
} else {
    // Mode Tambah Baru: Ambil stok_akhir hari sebelumnya
    $stmt_last = $pdo->query("SELECT stok_akhir FROM db_stok_roll_harian ORDER BY tanggal DESC, id DESC LIMIT 1");
    $last_record = $stmt_last->fetch(PDO::FETCH_ASSOC);
    if ($last_record) {
        $stok_awal = $last_record['stok_akhir'];
        $is_readonly_awal = true; // Kunci input karena sudah ada data sebelumnya
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_stok_roll']) && $user_role != 'Viewer') {
    $post_id_roll = $_POST['id_edit_roll'];
    $p_tgl = $_POST['tgl_roll'];
    
    // Walaupun readonly, nilainya dikirim. Namun untuk amannya kita ambil dari POST.
    $p_awal = floatval($_POST['stok_awal'] ?? 0);
    $p_penerimaan = floatval($_POST['penerimaan'] ?? 0);
    $p_pemakaian = floatval($_POST['pemakaian'] ?? 0);
    $p_aktual = floatval($_POST['stok_aktual'] ?? 0);
    
    // Kalkulasi Live Backend (Biar tidak cuma dari frontend)
    $p_akhir = $p_awal + $p_penerimaan - $p_pemakaian;
    $p_selisih = $p_aktual - $p_akhir; // Selisih = Aktual vs Sistem

    if (!empty($post_id_roll)) {
        $stmt = $pdo->prepare("UPDATE db_stok_roll_harian SET tanggal=?, stok_awal=?, penerimaan=?, pemakaian=?, stok_akhir=?, stok_aktual=?, selisih=? WHERE id=?");
        $stmt->execute([$p_tgl, $p_awal, $p_penerimaan, $p_pemakaian, $p_akhir, $p_aktual, $p_selisih, $post_id_roll]);
        catatLog($pdo, $user_aktif, "Mengupdate Laporan Stok Roll tgl " . date('d/m/Y', strtotime($p_tgl)), "📝");
        header("Location: stok_opname.php?pesan=edit_roll_sukses"); exit();
    } else {
        $stmt = $pdo->prepare("INSERT INTO db_stok_roll_harian (tanggal, stok_awal, penerimaan, pemakaian, stok_akhir, stok_aktual, selisih) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$p_tgl, $p_awal, $p_penerimaan, $p_pemakaian, $p_akhir, $p_aktual, $p_selisih]);
        catatLog($pdo, $user_aktif, "Menambahkan Laporan Stok Roll tgl " . date('d/m/Y', strtotime($p_tgl)), "📝");
        header("Location: stok_opname.php?pesan=tambah_roll_sukses"); exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_roll_data']) && isset($_POST['hapus_roll_id']) && $user_role != 'Viewer') {
    $id = intval($_POST['hapus_roll_id']);
    $pdo->prepare("DELETE FROM db_stok_roll_harian WHERE id = ?")->execute([$id]);
    catatLog($pdo, $user_aktif, "Menghapus Laporan Stok Roll Harian.", "🗑️");
    header("Location: stok_opname.php?pesan=hapus_roll_sukses"); exit();
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'tambah_roll_sukses') $pesan = "<div class='alert alert-success'>✅ Berhasil: Laporan Harian Stok Roll telah dicatat.</div>";
    if ($_GET['pesan'] == 'edit_roll_sukses') $pesan = "<div class='alert alert-success'>✅ Berhasil: Perubahan Laporan Stok Roll disimpan!</div>";
    if ($_GET['pesan'] == 'hapus_roll_sukses') $pesan = "<div class='alert alert-danger'>🗑️ Berhasil: Laporan Stok Roll dihapus permanen.</div>";
}

$page_title = "Stok Roll Harian — H2 BASE ERP";
$active_page = "stok_opname";
require 'header.php';
?>

<style>
    /* 🚀 ANIMASI MELAYANG */
    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); animation: slideUpFade 0.4s ease-out forwards;}
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; }
    .form-group label { margin-bottom: 6px; font-weight: 600; font-size: 12px; color: #475569; text-transform: uppercase; }
    input, select { padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; width: 100%; box-sizing: border-box; }
    input:focus, select:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,0.1); outline: none; }
    
    .btn-submit { background: #8b5cf6; color: white; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; justify-content: center; align-items: center;}
    .btn-submit:hover { background: #7c3aed; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3);}
    .btn-batal { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 9px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-flex; justify-content: center; align-items: center; transition: 0.2s;}
    .btn-batal:hover { background: #fee2e2; }
    
    /* 🚀 PREMIUM TABLE SAAS STYLE */
    .table-responsive { 
        background: white; border-radius: 10px; border: 1px solid #cbd5e1; 
        overflow-x: auto; max-height: 600px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
        margin-top: 10px;
    }
    .table-premium { 
        width: 100%; border-collapse: separate; border-spacing: 0; 
        font-size: 13px; color: #334155; white-space: nowrap; 
    }
    .table-premium th, .table-premium td { 
        text-align: center !important; vertical-align: middle !important; 
        padding: 14px 16px; border-bottom: 1px solid #e2e8f0; 
        border-right: 1px solid #f1f5f9;
        transition: background-color 0.2s ease;
    }
    .table-premium th:last-child, .table-premium td:last-child { border-right: none; }
    
    .table-premium th { 
        background-color: #0f172a; color: #f8fafc; position: sticky; top: 0; z-index: 10; 
        font-size: 11.5px; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;
    }
    
    /* Zebra Cross Baris Halus */
    .table-premium tbody tr:nth-child(even) td { background-color: #f8fafc; }
    .table-premium tbody tr:hover td { background-color: #f1f5f9 !important; color: #0f172a; }

    /* Tombol Aksi dalam Tabel */
    .btn-edit { 
        background: #e0f2fe; color: #0284c7; padding: 6px 12px; border-radius: 20px; 
        text-decoration: none; font-size: 11px; font-weight: 800; border: 1px solid #bae6fd; 
        transition: all 0.2s; display: inline-block;
    }
    .btn-edit:hover { background: #bae6fd; color: #0369a1; transform: scale(1.05); }
    
    .btn-hapus { 
        background: #fee2e2; color: #dc2626; padding: 6px 12px; border-radius: 20px; 
        text-decoration: none; font-size: 11px; font-weight: 800; border: 1px solid #fecaca; 
        margin-left: 4px; transition: all 0.2s; display: inline-block;
    }
    .btn-hapus:hover { background: #fecaca; color: #b91c1c; transform: scale(1.05); }
    
    .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 700; text-align: center;}
    .alert-success { background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .alert-danger { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    /* STYLE TOGGLE FORM BUKA TUTUP */
    .form-toggle-header { user-select: none; transition: background 0.2s; padding: 12px 16px; border-radius: 8px; margin: -10px -10px 15px -10px; cursor: pointer; }
    .form-toggle-header:hover { background: #f8fafc; }

    /* WIDGET LIVE CALCULATE */
    .widget-container {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin-top: 10px;
        margin-bottom: 20px;
        padding: 15px;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
    }
    .widget-box {
        background: white;
        padding: 15px;
        border-radius: 6px;
        border-left: 5px solid #8b5cf6;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        display: flex;
        flex-direction: column;
        justify-content: center;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }
    .widget-box:hover {
        transform: translateY(-6px) scale(1.02); 
        box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); 
    }
    .widget-box.danger { border-left-color: #ef4444; }
    .widget-box.success { border-left-color: #10b981; }
    
    .widget-title {
        font-size: 11px;
        font-weight: 800;
        color: #64748b;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    .widget-value {
        font-size: 20px;
        font-weight: 900;
        color: #0f172a;
    }
    .widget-value.danger { color: #dc2626; }
    .widget-value.success { color: #166534; }

    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
        .widget-container { grid-template-columns: 1fr; }
        .btn-submit, .btn-batal { width: 100%; margin-bottom: 10px; }
    }

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
</style>

<?= $pesan ?>

<!-- ====================================================================================== -->
<!-- 🚀 FITUR UTAMA: LAPORAN STOK ROLL HARIAN -->
<!-- ====================================================================================== -->
<?php if ($user_role != 'Viewer'): ?>
<div class="card" <?= $is_edit_roll ? 'style="border-top: 5px solid #8b5cf6; background: #faf5ff;"' : 'style="border-top: 5px solid #8b5cf6;"' ?>>
    <div class="form-toggle-header" onclick="toggleFormRoll()">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid <?= $is_edit_roll ? '#ddd6fe' : '#e2e8f0' ?>; padding-bottom: 12px;">
            <h2 style="margin:0; font-size: 18px; color: #6d28d9; border-bottom: none; padding-bottom: 0;">
                <?= $is_edit_roll ? "✏️ Edit Laporan Stok Roll" : "📝 Form Input Stok Roll Harian" ?>
            </h2>
            <span id="formToggleIconRoll" style="font-size:13px; font-weight:900; color:#6d28d9; background:#f5f3ff; padding:4px 10px; border-radius:6px; border:1px solid #ddd6fe;">
                ▼ KLIK UNTUK BUKA/TUTUP FORM
            </span>
        </div>
    </div>

    <div id="formCollapsibleRoll" style="display: none; margin-top: 15px;">
        <form action="" method="POST" autocomplete="off">
            <input type="hidden" name="id_edit_roll" value="<?= htmlspecialchars($id_edit_roll) ?>">
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="tgl_roll" value="<?= htmlspecialchars($tgl_roll) ?>" required style="border-color: #8b5cf6;">
                </div>
                <div class="form-group">
                    <label>Stok Awal (KG)</label>
                    <input type="number" step="0.01" id="stok_awal" name="stok_awal" value="<?= htmlspecialchars($stok_awal) ?>" required <?= $is_readonly_awal ? 'readonly style="background:#f1f5f9; color:#94a3b8;"' : '' ?>>
                </div>
                <div class="form-group">
                    <label>Penerimaan (KG)</label>
                    <input type="number" step="0.01" id="penerimaan" name="penerimaan" value="<?= htmlspecialchars($penerimaan) ?>" required>
                </div>
                <div class="form-group">
                    <label>Pemakaian (KG)</label>
                    <input type="number" step="0.01" id="pemakaian" name="pemakaian" value="<?= htmlspecialchars($pemakaian) ?>" required>
                </div>
                <div class="form-group">
                    <label>Stok Aktual Harian (KG)</label>
                    <input type="number" step="0.01" id="stok_aktual" name="stok_aktual" value="<?= htmlspecialchars($stok_aktual) ?>" required>
                </div>
            </div>
            
            <!-- LIVE CALCULATE WIDGETS -->
            <div class="widget-container">
                <div class="widget-box">
                    <div class="widget-title">Stok Akhir Sistem</div>
                    <div class="widget-value" id="widget_stok_akhir">0.00 KG</div>
                </div>
                <div class="widget-box" id="box_selisih">
                    <div class="widget-title">Selisih Aktual vs Sistem</div>
                    <div class="widget-value" id="widget_selisih">0.00 KG</div>
                </div>
                <div class="widget-box" id="box_persen">
                    <div class="widget-title">Persentase Selisih</div>
                    <div class="widget-value" id="widget_persen">0.00 %</div>
                </div>
            </div>
            
            <div style="display:flex; justify-content: flex-end; gap: 15px; margin-top: 10px; flex-wrap: wrap;">
                <?php if($is_edit_roll): ?><a href="stok_opname.php" class="btn-batal-modern">Batal Edit</a><?php endif; ?>
                <button type="submit" name="simpan_stok_roll" class="btn-submit-modern">
                    <?= $is_edit_roll ? "💾 Update Stok Roll" : "💾 Simpan Laporan Harian" ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom: 35px; border-top: 5px solid #0f172a;">
    <h2 style="margin-top: 0; color: #0f172a; margin-bottom: 10px; font-weight: 800; font-size: 18px;">📊 Riwayat Pemakaian Stok Roll Harian</h2>
    <div class="table-responsive">
        <table class="table-premium">
            <thead>
                <tr>
                    <th style="width: 50px;">NO</th>
                    <th style="width: 140px;">TANGGAL</th>
                    <th>AWAL (KG)</th>
                    <th>PENERIMAAN</th>
                    <th>PEMAKAIAN</th>
                    <th>AKHIR SISTEM</th>
                    <th>AKTUAL (KG)</th>
                    <th>SELISIH</th>
                    <th style="width: 100px;">% SELISIH</th>
                    <th style="width: 120px;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no_roll = 1;
                $formatVal = function($val) {
                    return str_replace(',00', '', number_format((float)$val, 2, ',', '.'));
                };
                $stok_roll_list = $pdo->query("SELECT * FROM db_stok_roll_harian ORDER BY tanggal DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
                foreach($stok_roll_list as $rRoll): 
                    // Kalkulasi Persentase Selisih On The Fly (Selisih dari Stok Akhir Sistem)
                    $stok_akhir_sistem = $rRoll['stok_akhir'];
                    $persentase = ($stok_akhir_sistem > 0) ? ($rRoll['selisih'] / $stok_akhir_sistem) * 100 : 0;
                    
                    // Highlight selisih jika tidak 0
                    $style_selisih = ($rRoll['selisih'] != 0) ? 'color:#dc2626; font-weight:900;' : 'color:#15803d; font-weight:900;';
                ?>
                <tr>
                    <td style="font-weight:900; color:#94a3b8;"><?= $no_roll++ ?></td>
                    <td style="font-weight:800; font-size:13px; color:#1e293b;"><?= date('d / m / Y', strtotime($rRoll['tanggal'])) ?></td>
                    <td style="font-size: 13px; color:#475569; font-weight: 700;"><?= $formatVal($rRoll['stok_awal']) ?></td>
                    <td style="font-size: 13px; color:#475569; font-weight: 700;"><?= $formatVal($rRoll['penerimaan'] ?? 0) ?></td>
                    <td style="font-size: 13px; color:#475569; font-weight: 700;"><?= $formatVal($rRoll['pemakaian'] ?? 0) ?></td>
                    <td style="font-size: 13px; color:#475569; font-weight: 700; background:#f1f5f9;"><?= $formatVal($rRoll['stok_akhir']) ?></td>
                    <td style="font-weight: 900; font-size: 14px; color: #475569;"><?= $formatVal($rRoll['stok_aktual'] ?? 0) ?></td>
                    <td style="font-size: 14px; <?= $style_selisih ?>"><?= $formatVal($rRoll['selisih']) ?></td>
                    <td style="font-size: 14px; <?= $style_selisih ?>"><?= $formatVal($persentase) ?> %</td>
                    <td>
                        <?php if ($user_role != 'Viewer'): ?>
                            <a href="stok_opname.php?edit_roll=<?= $rRoll['id'] ?>" class="btn-edit">Edit</a>
                            <a href="javascript:void(0);" onclick="konfirmasiHapusRoll(<?= $rRoll['id'] ?>, 'Hapus riwayat stok harian ini secara permanen?')" class="btn-hapus">Hapus</a>
                        <?php else: ?>
                            <span style="font-size:10px; color:#94a3b8; font-weight: bold;">Akses Terbatas</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(count($stok_roll_list) == 0): ?>
                    <tr><td colspan="7" style="padding:40px; color:#94a3b8; text-align:center; font-weight:700; font-size:14px;">Belum ada inputan riwayat stok roll harian saat ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="formHapusRoll" method="POST" style="display: none;">
    <input type="hidden" name="hapus_roll_id" id="inputHapusRollId">
    <input type="hidden" name="hapus_roll_data" value="1">
</form>

<script>
    // Auto-Calculate Selisih Roll
    function hitungSelisihRoll() {
        const awal = parseFloat(document.getElementById('stok_awal').value) || 0;
        const penerimaan = parseFloat(document.getElementById('penerimaan').value) || 0;
        const pemakaian = parseFloat(document.getElementById('pemakaian').value) || 0;
        
        const formatID = (val) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(val);
        
        // Stok Akhir (Sistem)
        const akhir = awal + penerimaan - pemakaian;
        document.getElementById('widget_stok_akhir').innerText = formatID(akhir) + " KG";
        
        // Stok Aktual (Fisik)
        const aktual = parseFloat(document.getElementById('stok_aktual').value) || 0;
        
        // Selisih
        const selisih = aktual - akhir;
        const elSelisih = document.getElementById('widget_selisih');
        elSelisih.innerText = formatID(selisih) + " KG";
        
        // Persentase
        let persen = 0;
        if (akhir > 0) {
            persen = (selisih / akhir) * 100;
        }
        const elPersen = document.getElementById('widget_persen');
        elPersen.innerText = formatID(persen) + " %";
        
        // Styling box warnanya
        const boxSelisih = document.getElementById('box_selisih');
        const boxPersen = document.getElementById('box_persen');
        
        // Reset class
        boxSelisih.className = 'widget-box';
        boxPersen.className = 'widget-box';
        elSelisih.className = 'widget-value';
        elPersen.className = 'widget-value';

        if (selisih !== 0 && aktual !== 0) {
            boxSelisih.classList.add('danger');
            boxPersen.classList.add('danger');
            elSelisih.classList.add('danger');
            elPersen.classList.add('danger');
        } else if (selisih === 0 && aktual !== 0) {
            boxSelisih.classList.add('success');
            boxPersen.classList.add('success');
            elSelisih.classList.add('success');
            elPersen.classList.add('success');
        }
    }
    
    // Toggle Form Roll
    function toggleFormRoll() {
        let content = document.getElementById('formCollapsibleRoll');
        let icon = document.getElementById('formToggleIconRoll');
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.innerText = '▲ KLIK UNTUK TUTUP PANEL';
            localStorage.setItem('formRollState', 'open');
        } else {
            content.style.display = 'none';
            icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
            localStorage.setItem('formRollState', 'closed');
        }
    }

    // Konfirmasi Hapus Data Roll
    function konfirmasiHapusRoll(id, pesan) {
        if (confirm(pesan)) {
            document.getElementById('inputHapusRollId').value = id;
            document.getElementById('formHapusRoll').submit();
        }
    }

    window.onload = function() {
        // Init kalkulasi Roll Live
        let inAwal = document.getElementById('stok_awal');
        let inTerima = document.getElementById('penerimaan');
        let inPakai = document.getElementById('pemakaian');
        let inAktual = document.getElementById('stok_aktual');
        
        if(inAwal) inAwal.addEventListener('input', hitungSelisihRoll);
        if(inTerima) inTerima.addEventListener('input', hitungSelisihRoll);
        if(inPakai) inPakai.addEventListener('input', hitungSelisihRoll);
        if(inAktual) inAktual.addEventListener('input', hitungSelisihRoll);
        
        // Trigger calculate on load
        hitungSelisihRoll();

        // Pertahankan State Buka/Tutup Form Roll setelah halaman refresh
        let formRollState = localStorage.getItem('formRollState');
        let contentRoll = document.getElementById('formCollapsibleRoll');
        let iconRoll = document.getElementById('formToggleIconRoll');
        
        <?php if ($is_edit_roll): ?>
            if(contentRoll) contentRoll.style.display = 'block';
            if(iconRoll) iconRoll.innerText = '▲ SEDANG MODE EDIT';
        <?php else: ?>
            if(contentRoll && iconRoll) {
                if (formRollState === 'open') {
                    contentRoll.style.display = 'block';
                    iconRoll.innerText = '▲ KLIK UNTUK TUTUP PANEL';
                } else {
                    contentRoll.style.display = 'none';
                    iconRoll.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
                }
            }
        <?php endif; ?>
    };
</script>
<?php require_once 'footer.php'; ?>
