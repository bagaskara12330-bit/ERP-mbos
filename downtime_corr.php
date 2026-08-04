<?php
require_once 'auth.php';
require_akses('prod_downtime');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false;

// 🚀 FILTER TANGGAL, BULAN, TAHUN
$tgl_filter = isset($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d');
$bulan_filter = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m', strtotime($tgl_filter));
$tahun_filter = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y', strtotime($tgl_filter));

$nama_bulan_list = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$nama_bulan = $nama_bulan_list[$bulan_filter];

// 🚀 AUTO-CREATE TABEL DATABASE & AUTO-PATCH KOLOM SHIFT
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_downtime_corr (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        shift VARCHAR(10) NOT NULL DEFAULT '1',
        keterangan VARCHAR(255) NOT NULL,
        waktu_dari TIME NOT NULL,
        waktu_sampai TIME NOT NULL,
        selisih_menit INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 🚀 AUTO-PATCH: Tambahkan kolom shift jika tabel lama belum punya (Tanpa hapus data lama)
    $cek_kolom_shift = $pdo->query("SHOW COLUMNS FROM db_downtime_corr LIKE 'shift'");
    if ($cek_kolom_shift->rowCount() == 0) {
        $pdo->exec("ALTER TABLE db_downtime_corr ADD COLUMN shift VARCHAR(10) NOT NULL DEFAULT '1' AFTER tanggal");
    }
} catch (PDOException $e) { die("Error Database: " . $e->getMessage()); }

// 🚀 LOGIKA HAPUS DATA AMAN (MENGGUNAKAN POST METHOD)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id']) && $user_role != 'Viewer') {
    $id = intval($_POST['hapus_id']);
    
    $stmt_cek = $pdo->prepare("SELECT tanggal, keterangan FROM db_downtime_corr WHERE id = ?");
    $stmt_cek->execute([$id]);
    $row_del = $stmt_cek->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM db_downtime_corr WHERE id = ?");
    if($stmt->execute([$id])){
        $tgl_indo = date('d/m/Y', strtotime($row_del['tanggal']));
        catatLog($pdo, $user_aktif, "Menghapus data Downtime '{$row_del['keterangan']}' (Tgl: $tgl_indo).", "🗑️");
        header("Location: downtime_corr.php?pesan=hapus_sukses&tgl=" . $row_del['tanggal']); exit();
    }
}

// Variabel Default Form
$id_edit = ''; $tanggal = $tgl_filter; $shift = '1'; $keterangan = '';
$waktu_dari = ''; $waktu_sampai = ''; $selisih_menit = 0;

// LOGIKA TANGKAP DATA UNTUK DI-EDIT
if (isset($_GET['edit']) && $user_role != 'Viewer') {
    $is_edit = true;
    $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM db_downtime_corr WHERE id = ?");
    $stmt->execute([$id_edit]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $tanggal = $row['tanggal'];
        $shift = $row['shift'];
        $keterangan = $row['keterangan'];
        $waktu_dari = date('H:i', strtotime($row['waktu_dari']));
        $waktu_sampai = date('H:i', strtotime($row['waktu_sampai']));
        $selisih_menit = $row['selisih_menit'];
    }
}

// FUNGSI PEMBERSIH JAM MURNI PHP
function cleanTimeStrict($timeStr) {
    if (empty($timeStr)) return NULL;
    $timeStr = trim($timeStr);
    if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $timeStr)) {
        return $timeStr . ':00';
    }
    return NULL;
}

// 🚀 LOGIKA SIMPAN (INSERT BARU) ATAU PERBARUI (UPDATE) DATA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_dt']) && $user_role != 'Viewer') {
    $post_id = $_POST['id_edit'];
    $p_tgl = $_POST['tanggal'];
    $p_shift = $_POST['shift'];
    $p_ket = strtoupper(trim($_POST['keterangan']));
    
    $w_dari = cleanTimeStrict($_POST['waktu_dari']);
    $w_sampai = cleanTimeStrict($_POST['waktu_sampai']);
    
    // BACKUP KALKULASI DARI SISI PHP JIKA JS GAGAL
    $p_selisih = 0;
    if ($w_dari && $w_sampai) {
        $start = strtotime($w_dari);
        $end = strtotime($w_sampai);
        $diff = ($end - $start) / 60; // Jadikan menit
        if ($diff < 0) { $diff += (24 * 60); } // Lintas hari / Lintas tengah malam
        $p_selisih = round($diff);
    }

    if (!empty($post_id)) {
        $stmt = $pdo->prepare("UPDATE db_downtime_corr SET tanggal=?, shift=?, keterangan=?, waktu_dari=?, waktu_sampai=?, selisih_menit=? WHERE id=?");
        if ($stmt->execute([$p_tgl, $p_shift, $p_ket, $w_dari, $w_sampai, $p_selisih, $post_id])) {
            catatLog($pdo, $user_aktif, "Memperbarui data Downtime Corrugator tgl " . date('d/m/Y', strtotime($p_tgl)), "✏️");
            header("Location: downtime_corr.php?pesan=edit_sukses&tgl=$p_tgl#baris-$post_id"); exit();
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO db_downtime_corr (tanggal, shift, keterangan, waktu_dari, waktu_sampai, selisih_menit) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$p_tgl, $p_shift, $p_ket, $w_dari, $w_sampai, $p_selisih])) {
            $last_id = $pdo->lastInsertId();
            catatLog($pdo, $user_aktif, "Merekam Downtime Corr: $p_ket ($p_selisih Menit).", "🛑");
            header("Location: downtime_corr.php?pesan=tambah_sukses&tgl=$p_tgl#baris-$last_id"); exit();
        }
    }
}

// TANGKAP PESAN NOTIFIKASI
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-success' style='padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 500; background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;'>🗑️ Berhasil: Data Downtime dihapus permanen.</div>";
    if ($_GET['pesan'] == 'tambah_sukses') $pesan = "<div class='alert alert-success' style='padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 500; background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;'>✅ Berhasil: Log Downtime baru berhasil direkam.</div>";
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success' style='padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 500; background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;'>✅ Berhasil: Perubahan waktu/keterangan downtime tersimpan.</div>";
}

$page_title = "Downtime Corrugator — H2 BASE ERP";
$active_page = "downtime";
require 'header.php';
?>

<style>
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; min-width: 140px; }
    .form-group label { margin-bottom: 8px; font-weight: 800; font-size: 11px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;}
    
    input, select { padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; color: #0f172a; background: #f8fafc; outline: none; transition: 0.2s; box-sizing: border-box; width: 100%; font-weight: 600;}
    input:focus, select:focus { border-color: #f43f5e; box-shadow: 0 0 0 3px rgba(244,63,94,0.1); background: #ffffff;}
    
    input.input-jam { text-align: center; font-family: monospace; font-size: 16px; letter-spacing: 2px; font-weight: 900; color: #be123c; background: #fff1f2; border-color: #fecdd3;}
    input.input-jam::placeholder { color: #fca5a5; font-weight: normal; letter-spacing: normal;}
    input.input-jam:focus { background: #ffffff; border-color: #e11d48;}

    .btn-group { display: flex; gap: 15px; justify-content: flex-end; }
    .btn-submit { background: #0f172a; color: white; border: none; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: 0.2s; }
    .btn-submit:hover { background: #1e293b; transform: translateY(-1px);}
    .btn-batal { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 12px 28px; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 700; text-align: center; transition: 0.2s; cursor: pointer;}
    .btn-batal:hover { background: #fca5a5; }

    .table-responsive { background: white; border-radius: 8px; border: 1px solid #cbd5e1; overflow-x: auto; max-height: 700px; margin-bottom: 20px; box-shadow: inset 0 0 5px rgba(0,0,0,0.02);}
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #1e293b; white-space: nowrap; min-width: 1000px; }
    
    .table-premium th, .table-premium td { padding: 14px 16px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; vertical-align: middle; box-sizing: border-box; text-align: center;}
    .table-premium th { background-color: #1e293b; color: #ffffff; font-weight: 700; position: sticky; top: 0; z-index: 10; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;}
    .table-premium th.text-left, .table-premium td.text-left { text-align: left !important; }
    
    .stk-1 { position: sticky; left: 0; z-index: 5; background: #fff; width: 50px; min-width: 50px; }
    .stk-2 { position: sticky; left: 50px; z-index: 5; background: #fff; width: 100px; border-right: 2px solid #cbd5e1 !important; }
    .table-premium th.stk-1, .table-premium th.stk-2 { background-color: #1e293b; color: white; z-index: 11; border-right-color: #334155 !important; }
    
    .table-premium tbody tr:nth-child(even) td { background-color: #f8fafc; }
    .table-premium tbody tr:nth-child(even) td.stk-1, .table-premium tbody tr:nth-child(even) td.stk-2 { background-color: #f8fafc; }
    .table-premium tbody tr:hover td { background-color: #f1f5f9 !important; }
    
    tr:target { animation: highlightFlash 3s ease-out; }
    tr:target td { background-color: #fef08a !important; }
    @keyframes highlightFlash { 0% { background-color: #fef08a; } 100% { background-color: transparent; } }

    /* 🚀 PREMIUM BUTTONS */
    .btn-submit-modern {
        background: #10b981 !important; color: #ffffff !important; border: none !important; padding: 12px 28px !important; border-radius: 8px !important; font-size: 14px !important; font-weight: 800 !important; cursor: pointer !important; transition: all 0.2s ease-in-out !important; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.1) !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;
    }
    .btn-submit-modern:hover { background: #059669 !important; transform: translateY(-1px) !important; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2) !important; }

    .btn-batal-modern {
        background: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important; padding: 11px 24px !important; text-decoration: none !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; text-align: center !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; transition: all 0.2s !important;
    }
    .btn-batal-modern:hover { background: #f1f5f9 !important; color: #0f172a !important; }
    
    .btn-edit { display: inline-block; background: #e0f2fe; color: #0284c7; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #bae6fd; cursor: pointer;}
    .btn-edit:hover { background: #bae6fd; color: #0369a1; }
    .btn-hapus { display: inline-block; background: #fee2e2; color: #dc2626; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #fecaca; margin-left: 6px; cursor: pointer;}
    .btn-hapus:hover { background: #fecaca; color: #b91c1c; }
    
    .total-row td { background-color: #f1f5f9 !important; font-weight: 800; color: #0f172a; border-top: 2px solid #0f172a; position: sticky; bottom: 0; z-index: 9;}

    /* 🚀 STYLE TOGGLE FORM (BARU DITAMBAHKAN) */
    .form-toggle-header { user-select: none; transition: background 0.2s; padding: 12px 16px; border-radius: 8px; margin: -10px -10px 15px -10px; cursor: pointer; }
    .form-toggle-header:hover { background: #f8fafc; }
</style>

<div style="background: #0f172a; border-radius: 10px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 24px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); gap: 15px;">
    <div style="display: flex; gap: 10px; align-items: center;">
        <h3 style="color: #ffffff; margin: 0; font-size: 14px; display: flex; align-items: center; gap: 8px;">
            📅 TANGGAL INPUT DOWNTIME:
        </h3>
        <form method="GET" action="" style="margin: 0;">
            <input type="date" name="tgl" value="<?= $tgl_filter ?>" style="padding: 8px 12px; font-size: 14px; border-radius: 6px; border: none; font-weight: bold; cursor: pointer;" onchange="this.form.submit()">
        </form>
    </div>
</div>

<?= $pesan ?>

<?php if ($user_role != 'Viewer'): ?>
<div class="card" id="cardInputArea" style="border-top: 5px solid #e11d48; transition: 0.3s;">
    <!-- 🚀 HEADER TOGGLE FORM -->
    <div class="form-toggle-header" onclick="toggleFormInput()">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid <?= $is_edit ? '#fecdd3' : '#fecdd3' ?>; padding-bottom: 12px;">
            <h2 id="formTitle" style="color: #be123c; border-bottom: none; padding-bottom: 0; margin-top: 0; font-size: 18px;">
                <?= $is_edit ? "✏️ Mode Edit Log Downtime" : "🛑 Form Input Log Downtime Mesin Corrugator" ?>
            </h2>
            <span id="formToggleIcon" style="font-size:13px; font-weight:900; color:#be123c; background:#ffe4e6; padding:4px 10px; border-radius:6px; border:1px solid #fecdd3;">
                ▼ KLIK UNTUK BUKA/TUTUP FORM
            </span>
        </div>
    </div>

    <!-- 🚀 WRAPPER FORM YANG BISA DI-TOGGLE -->
    <div id="formCollapsibleContent" style="display: none; margin-top: 15px;">
        <form action="" method="POST" autocomplete="off">
            <input type="hidden" name="id_edit" id="id_edit" value="<?= $id_edit ?>">
            
            <div class="form-grid" style="grid-template-columns: 1fr 1fr 3fr;">
                <div class="form-group">
                    <label>Pilih Tanggal</label>
                    <input type="date" name="tanggal" id="tanggal" value="<?= htmlspecialchars($tanggal) ?>" required>
                </div>
                <div class="form-group">
                    <label>Shift Kerja</label>
                    <select name="shift" required>
                        <option value="1" <?= $shift == '1' ? 'selected' : '' ?>>Shift 1</option>
                        <option value="2" <?= $shift == '2' ? 'selected' : '' ?>>Shift 2</option>
                        <option value="3" <?= $shift == '3' ? 'selected' : '' ?>>Shift 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Keterangan / Alasan Mesin Mati (Downtime)</label>
                    <input type="text" name="keterangan" id="keterangan" value="<?= htmlspecialchars($keterangan) ?>" required placeholder="Ketik atau pilih dari daftar..." list="daftar_alasan" autocomplete="off" style="text-transform: uppercase;">
                    <datalist id="daftar_alasan">
                        <!-- Alasan Umum Corrugator Berdasarkan Data Terbanyak -->
                        <option value="NYAMBUNG BAHAN">
                        <option value="NGUKUR BAHAN">
                        <option value="PUTUS KERTAS">
                        <option value="SETTING SCORE">
                        <option value="GANTI BAHAN">
                        <option value="GANTI ORDER">
                        <option value="TUNGGU BOILER">
                        <option value="GANTI SHIFT">
                        <option value="NC SLITTER ERROR">
                        <option value="STACKER ERROR">
                        <option value="BERSIHIN MESIN">
                        <option value="MESIN C FLUTE ERROR">
                        <option value="MESIN B FLUTE ERROR">
                        <option value="POMPA BOILER MATI">
                        <option value="KURANG ORDER">
                    </datalist>
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                <div class="form-group">
                    <label>Waktu Dari (Mulai Mati)</label>
                    <input type="text" name="waktu_dari" id="waktu_dari" class="input-jam" value="<?= $waktu_dari ?>" placeholder="Cth: 0730" maxlength="5" required oninput="formatJam(this)" onblur="validasiJam(this); hitungDurasiMenit();">
                </div>
                <div class="form-group">
                    <label>Waktu Sampai (Menyala Kembali)</label>
                    <input type="text" name="waktu_sampai" id="waktu_sampai" class="input-jam" value="<?= $waktu_sampai ?>" placeholder="Cth: 0800" maxlength="5" required oninput="formatJam(this)" onblur="validasiJam(this); hitungDurasiMenit();">
                </div>
                <div class="form-group">
                    <label style="color: #e11d48;">Selisih Waktu (Menit)</label>
                    <input type="number" name="selisih_menit" id="selisih_menit" value="<?= htmlspecialchars($selisih_menit) ?>" readonly style="background: #fff1f2; border-color: #f43f5e; font-weight: 900; color: #be123c; text-align: center; font-size: 18px; cursor: not-allowed;" title="Dihitung otomatis oleh sistem AI">
                </div>
            </div>

            <div class="btn-group" style="margin-top: 10px;">
                <button type="button" id="btnBatalEdit" class="btn-batal-modern" style="display: <?= $is_edit ? 'inline-block' : 'none' ?>;" onclick="window.location.href='downtime_corr.php?tgl=<?= $tgl_filter ?>'">Batal Edit</button>
                <button type="submit" name="simpan_dt" id="btnSubmitForm" class="btn-submit-modern" style="background:#e11d48; padding-left: 50px; padding-right: 50px; font-size: 15px;">
                    <?= $is_edit ? "💾 Update Data Downtime" : "💾 Simpan Record Downtime" ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
        <div>
            <h2 style="margin:0; border:none; padding:0; color:#0f172a;">📋 Detail Kejadian Downtime</h2>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-top: 4px;">Data Menampilkan Tanggal: <strong style="color:#e11d48; font-size: 15px;"><?= date('d F Y', strtotime($tgl_filter)) ?></strong></div>
        </div>
        
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button type="button" onclick="exportKeExcel('tabelDtHarian', 'Detail_Downtime')" class="btn-submit-modern" style="background: #16a34a; padding: 10px 16px; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                📥 Export Excel
            </button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table-premium" id="tabelDtHarian">
            <thead>
                <tr>
                    <th class="text-center stk-1">No</th>
                    <th class="text-center stk-2">Tanggal</th>
                    <th class="text-center">Shift</th>
                    <th class="text-left" style="min-width: 300px;">Keterangan Kejadian Downtime</th>
                    <th class="text-center">Jam Mulai</th>
                    <th class="text-center">Jam Selesai</th>
                    <th class="text-center" style="background: #f43f5e; color:#fff;">Total (Menit)</th>
                    <th class="text-center" style="min-width: 140px;" data-noexport="true">Aksi Manajemen</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1; $total_menit_hari = 0;
                // 🚀 UPDATE: Diurutkan berdasarkan waktu_dari DESC dan id DESC agar data terbaru di atas
                $stmt_dt = $pdo->prepare("SELECT * FROM db_downtime_corr WHERE tanggal = ? ORDER BY waktu_dari DESC, id DESC");
                $stmt_dt->execute([$tgl_filter]);
                $dt_list = $stmt_dt->fetchAll(PDO::FETCH_ASSOC);
                
                if(count($dt_list) > 0):
                    foreach ($dt_list as $row): 
                        $total_menit_hari += $row['selisih_menit'];
                ?>
                <tr id="baris-<?= $row['id'] ?>">
                    <td class="stk-1" style="color:#64748b; font-weight:bold;"><?= $no++ ?></td>
                    <td class="stk-2" style="font-weight:800; color:#0f172a; font-size:13px;"><?= date('d', strtotime($row['tanggal'])) ?></td>
                    <td style="font-weight:800; color:#0ea5e9; font-size:13px;">Shift <?= htmlspecialchars($row['shift']) ?></td>
                    <td class="text-left" style="font-weight:700; color:#334155; font-size:13px;"><?= htmlspecialchars($row['keterangan']) ?></td>
                    <td style="font-weight:900; font-family:monospace; font-size:15px; color:#0284c7;"><?= date('H:i', strtotime($row['waktu_dari'])) ?></td>
                    <td style="font-weight:900; font-family:monospace; font-size:15px; color:#16a34a;"><?= date('H:i', strtotime($row['waktu_sampai'])) ?></td>
                    <td style="font-weight:900; background:#fff1f2; color:#be123c; font-size:16px;"><?= number_format($row['selisih_menit']) ?> Mnt</td>
                    
                    <td data-noexport="true">
                        <?php if ($user_role != 'Viewer'): ?>
                            <a href="downtime_corr.php?tgl=<?= $tgl_filter ?>&edit=<?= $row['id'] ?>#cardInputArea" class="btn-edit">Edit</a>
                            <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Hapus permanen data downtime ini?')" class="btn-hapus">Hapus</a>
                        <?php else: ?>
                            <span style="font-size:10px; color:#94a3b8;">Akses Terbatas</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="6" style="text-align:right; padding-right:20px;">TOTAL AKUMULASI HARI INI:</td>
                    <td style="color:#be123c; font-size:18px;"><?= number_format($total_menit_hari) ?> Mnt</td>
                    <td data-noexport="true">-</td>
                </tr>
                <?php else: ?>
                    <tr><td colspan="8" style="padding:50px; color:#94a3b8; font-size: 15px; font-weight: 600;">Data Kosong. Belum ada catatan Downtime pada tanggal ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="border-top: 4px solid #0ea5e9;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
        <div>
            <h2 style="margin:0; border:none; padding:0; color:#0284c7;">📊 Akumulasi Total Downtime Per Hari</h2>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-top: 4px;">Data Menampilkan Bulan: <strong style="color:#0ea5e9; font-size: 15px;"><?= $nama_bulan ?> <?= $tahun_filter ?></strong></div>
        </div>
        
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button type="button" onclick="exportKeExcel('tabelAkumHarian', 'Akumulasi_Harian')" class="btn-submit-modern" style="background: #16a34a; padding: 10px 16px; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                📥 Export Excel
            </button>
            <form method="GET" action="" style="display: flex; gap: 8px; align-items: center; background: #f8fafc; padding: 10px 16px; border: 1px solid #cbd5e1; border-radius: 8px; margin: 0;">
                <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase;">Ubah Bulan:</label>
                <select name="bulan" style="padding: 6px 10px; font-size: 13px; font-weight: bold; border-color: #94a3b8; width: auto;">
                    <?php foreach ($nama_bulan_list as $m_code => $m_name): ?>
                        <option value="<?= $m_code ?>" <?= $m_code == $bulan_filter ? 'selected' : '' ?>><?= $m_name ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="tahun" style="padding: 6px 10px; font-size: 13px; font-weight: bold; border-color: #94a3b8; width: auto;">
                    <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $tahun_filter ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn-submit-modern" style="padding: 6px 12px; font-size: 12px; background:#0f172a;">Cari</button>
            </form>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table-premium" id="tabelAkumHarian">
            <thead>
                <tr>
                    <th class="stk-1">No</th>
                    <th class="stk-2">Tanggal</th>
                    <th style="background: #f43f5e; color:#fff;">Total Downtime (Menit)</th>
                    <th style="background: #e11d48; color:#fff;">Total Jam (Jam)</th>
                    <th>Jumlah Kejadian (Kali)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no2 = 1; $g_mnt_bln = 0; $g_kejadian_bln = 0;
                $stmt_rek_hari = $pdo->prepare("SELECT tanggal, SUM(selisih_menit) as tot_mnt, COUNT(id) as kejadian FROM db_downtime_corr WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? GROUP BY tanggal ORDER BY tanggal ASC");
                $stmt_rek_hari->execute([$bulan_filter, $tahun_filter]);
                $rek_hari_list = $stmt_rek_hari->fetchAll(PDO::FETCH_ASSOC);
                
                if(count($rek_hari_list) > 0):
                    foreach ($rek_hari_list as $row): 
                        $g_mnt_bln += $row['tot_mnt'];
                        $g_kejadian_bln += $row['kejadian'];
                        $jam_format = round($row['tot_mnt'] / 60, 2);
                ?>
                <tr>
                    <td class="stk-1" style="color:#64748b; font-weight:bold;"><?= $no2++ ?></td>
                    <td class="stk-2" style="font-weight:800; color:#0f172a; font-size:13px;"><?= date('d F Y', strtotime($row['tanggal'])) ?></td>
                    <td style="font-weight:900; background:#fff1f2; color:#be123c; font-size:15px;"><?= number_format($row['tot_mnt']) ?> Mnt</td>
                    <td style="font-weight:900; background:#ffe4e6; color:#9f1239; font-size:15px;"><?= number_format($jam_format, 2, ',', '.') ?> Jam</td>
                    <td style="font-weight:bold; color:#0ea5e9; font-size:14px;"><?= number_format($row['kejadian']) ?> Kali</td>
                </tr>
                <?php endforeach; 
                    $g_jam_bln = round($g_mnt_bln / 60, 2);
                ?>
                <tr class="total-row">
                    <td colspan="2" style="text-align:right; padding-right:20px;">GRAND TOTAL BULAN INI:</td>
                    <td style="color:#be123c; font-size:16px;"><?= number_format($g_mnt_bln) ?> Mnt</td>
                    <td style="color:#9f1239; font-size:16px;"><?= number_format($g_jam_bln, 2, ',', '.') ?> Jam</td>
                    <td style="color:#0ea5e9; font-size:16px;"><?= number_format($g_kejadian_bln) ?> Kali</td>
                </tr>
                <?php else: ?>
                    <tr><td colspan="5" style="padding:50px; color:#94a3b8; font-size: 15px; font-weight: 600;">Data Kosong. Tidak ada downtime di bulan ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="border-top: 4px solid #16a34a; margin-bottom: 10px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
        <div>
            <h2 style="margin:0; border:none; padding:0; color:#15803d;">📈 Akumulasi Total Downtime Per Bulan</h2>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-top: 4px;">Data Menampilkan Tahun: <strong style="color:#16a34a; font-size: 15px;"><?= $tahun_filter ?></strong></div>
        </div>
        
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button type="button" onclick="exportKeExcel('tabelAkumBulan', 'Akumulasi_Bulanan')" class="btn-submit-modern" style="background: #16a34a; padding: 10px 16px; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                📥 Export Excel
            </button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table-premium" id="tabelAkumBulan">
            <thead>
                <tr>
                    <th class="stk-1">No</th>
                    <th class="stk-2">Bulan</th>
                    <th style="background: #22c55e; color:#fff;">Total Downtime (Menit)</th>
                    <th style="background: #16a34a; color:#fff;">Total Jam (Jam)</th>
                    <th>Jumlah Kejadian (Kali)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no3 = 1; $g_mnt_thn = 0; $g_kejadian_thn = 0;
                $stmt_rek_bln = $pdo->prepare("SELECT MONTH(tanggal) as bln, SUM(selisih_menit) as tot_mnt, COUNT(id) as kejadian FROM db_downtime_corr WHERE YEAR(tanggal) = ? GROUP BY MONTH(tanggal) ORDER BY MONTH(tanggal) ASC");
                $stmt_rek_bln->execute([$tahun_filter]);
                $rek_bln_list = $stmt_rek_bln->fetchAll(PDO::FETCH_ASSOC);
                
                if(count($rek_bln_list) > 0):
                    foreach ($rek_bln_list as $row): 
                        $g_mnt_thn += $row['tot_mnt'];
                        $g_kejadian_thn += $row['kejadian'];
                        $jam_format_thn = round($row['tot_mnt'] / 60, 2);
                        $bln_nama_tbl = $nama_bulan_list[str_pad($row['bln'], 2, '0', STR_PAD_LEFT)];
                ?>
                <tr>
                    <td class="stk-1" style="color:#64748b; font-weight:bold;"><?= $no3++ ?></td>
                    <td class="stk-2" style="font-weight:800; color:#0f172a; font-size:13px; text-transform:uppercase;"><?= $bln_nama_tbl ?></td>
                    <td style="font-weight:900; background:#f0fdf4; color:#166534; font-size:15px;"><?= number_format($row['tot_mnt']) ?> Mnt</td>
                    <td style="font-weight:900; background:#dcfce7; color:#14532d; font-size:15px;"><?= number_format($jam_format_thn, 2, ',', '.') ?> Jam</td>
                    <td style="font-weight:bold; color:#0ea5e9; font-size:14px;"><?= number_format($row['kejadian']) ?> Kali</td>
                </tr>
                <?php endforeach; 
                    $g_jam_thn = round($g_mnt_thn / 60, 2);
                ?>
                <tr class="total-row">
                    <td colspan="2" style="text-align:right; padding-right:20px;">GRAND TOTAL TAHUN <?= $tahun_filter ?>:</td>
                    <td style="color:#166534; font-size:16px; background-color: #f0fdf4 !important;"><?= number_format($g_mnt_thn) ?> Mnt</td>
                    <td style="color:#14532d; font-size:16px; background-color: #dcfce7 !important;"><?= number_format($g_jam_thn, 2, ',', '.') ?> Jam</td>
                    <td style="color:#0ea5e9; font-size:16px;"><?= number_format($g_kejadian_thn) ?> Kali</td>
                </tr>
                <?php else: ?>
                    <tr><td colspan="5" style="padding:50px; color:#94a3b8; font-size: 15px; font-weight: 600;">Data Kosong. Tidak ada downtime yang terekam di tahun ini.</td></tr>
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
            localStorage.setItem('downtimeFormState', 'open');
        } else {
            content.style.display = 'none';
            icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
            localStorage.setItem('downtimeFormState', 'closed');
        }
    }

    // 🚀 BACA MEMORI LOCALSTORAGE SAAT HALAMAN DIMUAT
    window.onload = function() {
        let formState = localStorage.getItem('downtimeFormState');
        let content = document.getElementById('formCollapsibleContent');
        let icon = document.getElementById('formToggleIcon');
        
        <?php if ($is_edit): ?>
            if(content) content.style.display = 'block';
            if(icon) icon.innerText = '▲ SEDANG MODE EDIT';
        <?php else: ?>
            if(content && icon) {
                if (formState === 'open') {
                    content.style.display = 'block';
                    icon.innerText = '▲ KLIK UNTUK TUTUP PANEL';
                } else {
                    content.style.display = 'none';
                    icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
                }
            }
        <?php endif; ?>
    };

    // 🚀 FILTER INPUT KHUSUS ANGKA 
    function formatJam(obj) {
        let val = obj.value.replace(/[^0-9]/g, '');
        if (val.length > 4) val = val.substring(0, 4);
        
        if (val.length >= 3) {
            let h = val.substring(0, 2);
            let m = val.substring(2, 4);
            if (parseInt(h) > 23) h = '23';
            obj.value = h + ':' + m;
        } else if (val.length >= 1) {
            let h = val;
            if (parseInt(h) > 23) h = '23';
            obj.value = h;
        } else {
            obj.value = '';
        }
    }

    // 🚀 AI PEMBENARAN JAM OTOMATIS SAAT PINDAH KOTAK
    function validasiJam(obj) {
        let val = obj.value.replace(/[^0-9]/g, '');
        if (val.length > 0) {
            if (val.length === 3) val = '0' + val; 
            if (val.length === 4) {
                let h = val.substring(0, 2);
                let m = val.substring(2, 4);
                if (parseInt(h) > 23) h = '23';
                if (parseInt(m) > 59) m = '59';
                obj.value = h + ':' + m;
            } else {
                obj.value = ''; 
            }
        }
    }

    // 🚀 AI PENGHITUNG SELISIH WAKTU (MENDUKUNG LINTAS HARI)
    function hitungDurasiMenit() {
        let jamMulai = document.getElementById('waktu_dari').value;
        let jamSelesai = document.getElementById('waktu_sampai').value;
        
        if(jamMulai && jamSelesai && jamMulai.length === 5 && jamSelesai.length === 5) {
            let start = new Date("1970-01-01T" + jamMulai + "Z");
            let end = new Date("1970-01-01T" + jamSelesai + "Z");
            
            let diffMenit = (end - start) / 1000 / 60;
            // Jika hasilnya minus, berarti mesin menyala di hari berikutnya (lewat tengah malam)
            if(diffMenit < 0) diffMenit += (24 * 60); 
            
            document.getElementById('selisih_menit').value = Math.round(diffMenit);
        } else {
            document.getElementById('selisih_menit').value = 0;
        }
    }

    function konfirmasiHapus(id, pesan) {
        if (confirm(pesan)) {
            document.getElementById('inputHapusId').value = id;
            document.getElementById('formHapusGlobal').submit();
        }
    }

    function exportKeExcel(tableId, fileNamePrefix) {
        let table = document.getElementById(tableId);
        if (!table) return;

        let cloneTable = table.cloneNode(true);
        let noExportTh = cloneTable.querySelectorAll('th[data-noexport="true"]');
        noExportTh.forEach(th => th.remove());
        let noExportTd = cloneTable.querySelectorAll('td[data-noexport="true"]');
        noExportTd.forEach(td => td.remove());

        let tglExport = document.querySelector('input[name="tgl"]').value;
        let judulFile = fileNamePrefix.replace(/_/g, ' ');

        let htmlTemplate = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
                    th, td { border: 1px solid #000000; padding: 6px; text-align: center; font-size: 13px; vertical-align: middle; }
                    th { background-color: #1e293b; color: #ffffff; font-weight: bold; }
                </style>
            </head>
            <body>
                <h2 style="text-align: center; margin-bottom: 15px;">${judulFile} - H2 BASE</h2>
                <p><strong>Filter Terakhir:</strong> ${tglExport}</p>
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
</script>
<?php require_once 'footer.php'; ?>

