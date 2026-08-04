<?php
require_once 'auth.php';
require_akses('hrd_lemb');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false;

// 🚀 FILTER TANGGAL HARIAN (Default ke Hari Ini)
$tgl_filter = isset($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d');

// 🚀 LOGIKA HAPUS DATA AMAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id'])) {
    $id = intval($_POST['hapus_id']);
    $n_stmt = $pdo->prepare("SELECT nama_karyawan, tanggal FROM data_lembur WHERE id=?"); 
    $n_stmt->execute([$id]); 
    $row_del = $n_stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM data_lembur WHERE id = ?");
    if ($stmt->execute([$id])) {
        $tgl_indo = date('d/m/Y', strtotime($row_del['tanggal']));
        catatLog($pdo, $user_aktif, "Menghapus data lembur milik {$row_del['nama_karyawan']} (Tgl: $tgl_indo) secara permanen.", "🗑️");
        header("Location: karyawan_lembur.php?pesan=hapus_sukses&tgl=" . $row_del['tanggal']); exit();
    }
}

// Variabel Form Default
$id_edit = ''; $tanggal = $tgl_filter; $bagian = ''; $nama_karyawan = '';
$jam_mulai = ''; $jam_selesai = ''; $durasi_jam = ''; $alasan_lembur = '';

// PROSES TANGKAP DATA EDIT
if (isset($_GET['edit'])) {
    $is_edit = true; $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM data_lembur WHERE id = ?");
    $stmt->execute([$id_edit]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $tanggal = $row['tanggal']; $bagian = $row['bagian']; $nama_karyawan = $row['nama_karyawan'];
        $jam_mulai = $row['jam_mulai'] ? date('H:i', strtotime($row['jam_mulai'])) : ''; 
        $jam_selesai = $row['jam_selesai'] ? date('H:i', strtotime($row['jam_selesai'])) : ''; 
        $durasi_jam = $row['durasi_jam']; $alasan_lembur = $row['alasan_lembur'];
    }
}

// FUNGSI PEMBERSIH JAM MURNI PHP (Cegah Typo)
function cleanTimeStrict($timeStr) {
    if (empty($timeStr)) return NULL;
    $timeStr = trim($timeStr);
    if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $timeStr)) {
        return $timeStr . ':00';
    }
    return NULL;
}

// 🚀 PROSES SIMPAN / UPDATE DATA LEMBUR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_lembur'])) {
    $post_id = $_POST['id_edit'];
    $tanggal_input = $_POST['tanggal'];
    $nama_karyawan = strtoupper(trim($_POST['nama_karyawan']));
    $alasan_lembur = strtoupper(trim($_POST['alasan_lembur']));
    
    // Auto-detect Bagian berdasarkan Nama Karyawan
    $stmt_b = $pdo->prepare("SELECT bagian FROM db_karyawan_h2 WHERE nama = ?");
    $stmt_b->execute([$nama_karyawan]);
    $bagian = strtoupper(trim($stmt_b->fetchColumn() ?: ''));
    if(empty($bagian)) $bagian = "OFFICE";
    
    // Validasi Jam Super Ketat dari PHP
    $jam_mulai = cleanTimeStrict($_POST['jam_mulai'] ?? '');
    $jam_selesai = cleanTimeStrict($_POST['jam_selesai'] ?? '');
    
    $durasi_jam = floatval($_POST['durasi_jam']); 
    
    if ($jam_mulai !== NULL && $jam_selesai !== NULL) {
        $start = strtotime($jam_mulai);
        $end = strtotime($jam_selesai);
        $diff = ($end - $start) / 3600; 
        if ($diff < 0) { $diff += 24; } 
        $durasi_jam = round($diff, 2);  
    }

    if (!empty($post_id)) {
        $stmt = $pdo->prepare("UPDATE data_lembur SET tanggal=?, bagian=?, nama_karyawan=?, jam_mulai=?, jam_selesai=?, durasi_jam=?, alasan_lembur=? WHERE id=?");
        $stmt->execute([$tanggal_input, $bagian, $nama_karyawan, $jam_mulai, $jam_selesai, $durasi_jam, $alasan_lembur, $post_id]);
        catatLog($pdo, $user_aktif, "Memperbarui data jam lembur $nama_karyawan tanggal " . date('d/m/Y', strtotime($tanggal_input)) . ".", "✏️");
        header("Location: karyawan_lembur.php?pesan=edit_sukses&tgl=$tanggal_input#baris-$post_id"); exit();
    } else {
        $stmt = $pdo->prepare("INSERT INTO data_lembur (tanggal, bagian, nama_karyawan, jam_mulai, jam_selesai, durasi_jam, alasan_lembur) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tanggal_input, $bagian, $nama_karyawan, $jam_mulai, $jam_selesai, $durasi_jam, $alasan_lembur]);
        $last_id = $pdo->lastInsertId();
        catatLog($pdo, $user_aktif, "Menginput data lembur baru untuk $nama_karyawan selama $durasi_jam Jam.", "⏳");
        header("Location: karyawan_lembur.php?pesan=tambah_sukses&tgl=$tanggal_input#baris-$last_id"); exit();
    }
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'tambah_sukses') $pesan = "<div class='alert alert-success'>✅ Berhasil: Data Lembur telah ditambahkan ke database.</div>";
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success'>✅ Berhasil: Perubahan Data Lembur disimpan!</div>";
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-success'>🗑️ Data Lembur telah dihapus permanen!</div>";
}

$list_karyawan = [];
try {
    $list_karyawan = $pdo->query("SELECT nama FROM db_karyawan_h2 WHERE ket_status='AKTIF' ORDER BY nama ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

$page_title = "H2 BASE — Log Lembur Karyawan";
$active_page = "karyawan_lembur";
require 'header.php';
?>

<style>
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; min-width: 140px; }
    .form-group label { margin-bottom: 8px; font-weight: 800; font-size: 11px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;}
    
    input, select { padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; color: #0f172a; background: #f8fafc; outline: none; transition: 0.2s; box-sizing: border-box; width: 100%; font-weight: 600;}
    input:focus, select:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); background: #ffffff;}
    
    input.input-jam { text-align: center; font-family: monospace; font-size: 16px; letter-spacing: 2px; font-weight: 900; color: #1e40af; background: #eff6ff; border-color: #bfdbfe;}
    input.input-jam::placeholder { color: #93c5fd; font-weight: normal; letter-spacing: normal;}
    input.input-jam:focus { background: #ffffff; border-color: #3b82f6;}

    .btn-group { display: flex; gap: 15px; justify-content: flex-end; }
    .btn-submit { background: #0f172a; color: white; border: none; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: 0.2s; }
    .btn-submit:hover { background: #1e293b; transform: translateY(-1px);}
    .btn-batal { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 12px 28px; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 700; text-align: center; transition: 0.2s; cursor: pointer;}
    .btn-batal:hover { background: #fca5a5; }

    .table-responsive { background: white; border-radius: 8px; border: 1px solid #cbd5e1; overflow-x: auto; max-height: 700px; margin-bottom: 20px; -webkit-overflow-scrolling: touch; box-shadow: inset 0 0 5px rgba(0,0,0,0.02);}
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #1e293b; white-space: nowrap; min-width: 1100px; }
    
    .table-premium th, .table-premium td { padding: 14px 16px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; vertical-align: middle; box-sizing: border-box; }
    .table-premium th { background-color: #1e293b; color: #ffffff; font-weight: 700; position: sticky; top: 0; z-index: 10; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;}
    
    .stk-1 { position: sticky; left: 0; z-index: 5; width: 50px; min-width: 50px; max-width: 50px; }
    .stk-2 { position: sticky; left: 50px; z-index: 5; width: 60px; min-width: 60px; max-width: 60px; }
    .stk-3 { position: sticky; left: 110px; z-index: 5; width: 250px; min-width: 250px; max-width: 250px; border-right: 3px solid #cbd5e1 !important; }
    
    .table-premium th.stk-1, .table-premium th.stk-2, .table-premium th.stk-3 { background-color: #1e293b; color: white; z-index: 11; border-right-color: #334155; }
    
    tr.baris-data td { background-color: #ffffff; }
    tr.baris-data:nth-child(even) td { background-color: #f8fafc; }
    tr.baris-data:nth-child(even) td.stk-1, tr.baris-data:nth-child(even) td.stk-2, tr.baris-data:nth-child(even) td.stk-3 { background-color: #f8fafc; }
    tr.baris-data:hover td, tr.baris-data:hover td.stk-1, tr.baris-data:hover td.stk-2, tr.baris-data:hover td.stk-3 { background-color: #f1f5f9 !important; }

    tr:target { animation: highlightFlash 3s ease-out; }
    tr:target td, tr:target td.stk-1, tr:target td.stk-2, tr:target td.stk-3 { background-color: #fef08a !important; }
    @keyframes highlightFlash { 0% { background-color: #fef08a; } 100% { background-color: transparent; } }

    .btn-edit { display: inline-block; background: #e0f2fe; color: #0284c7; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #bae6fd; cursor: pointer;}
    .btn-edit:hover { background: #bae6fd; color: #0369a1; }
    .btn-hapus { display: inline-block; background: #fee2e2; color: #dc2626; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #fecaca; margin-left: 6px; cursor: pointer;}
    .btn-hapus:hover { background: #fecaca; color: #b91c1c; }
    
    .live-counter-box { background: #ffffff; border: 2px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; display: flex; flex-wrap: wrap; gap: 15px; font-size: 12px; font-weight: 700; color: #475569; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
    .live-counter-item span { font-size: 15px; font-weight: 900; color: #0f172a; margin-left: 6px;}

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

<div style="background: #0f172a; border-radius: 10px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 24px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); gap: 15px;">
    <div style="display: flex; gap: 10px; align-items: center;">
        <h3 style="color: #ffffff; margin: 0; font-size: 14px; display: flex; align-items: center; gap: 8px;">
            📅 DATA LEMBUR TANGGAL:
        </h3>
        <form method="GET" action="" style="margin: 0;">
            <input type="date" name="tgl" value="<?= $tgl_filter ?>" style="padding: 8px 12px; font-size: 14px; border-radius: 6px; border: none; font-weight: bold; cursor: pointer;" onchange="this.form.submit()">
        </form>
    </div>
</div>

<?= $pesan ?>

<div class="card" id="cardInputArea" style="border-top: 5px solid #8b5cf6; transition: 0.3s;">
    <h2 id="formTitle" style="color: #6d28d9; border-bottom: 2px solid #e9d5ff; padding-bottom: 12px; margin-top: 0; font-size: 18px;">
        ⏳ Form Input Log Lembur
    </h2>
    
    <form action="" method="POST" autocomplete="off">
        <input type="hidden" name="id_edit" id="id_edit" value="">
        
        <div class="form-grid" style="grid-template-columns: 1fr 2fr;">
            <div class="form-group">
                <label>Pilih Tanggal</label>
                <input type="date" name="tanggal" id="tanggal" value="<?= htmlspecialchars($tanggal) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Nama Karyawan</label>
                <select name="nama_karyawan" id="nama_karyawan" required>
                    <option value="">-- Ketik / Pilih Nama Karyawan --</option>
                    <?php foreach($list_karyawan as $nk): ?>
                        <option value="<?= htmlspecialchars($nk) ?>"><?= htmlspecialchars($nk) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr 2fr;">
            <div class="form-group">
                <label>Jam Mulai (Ketik Angka Saja)</label>
                <input type="text" name="jam_mulai" id="jam_mulai" class="input-jam" placeholder="Cth: 1600" maxlength="5" oninput="formatJam(this)" onblur="validasiJam(this); hitungDurasi();">
            </div>
            
            <div class="form-group">
                <label>Jam Selesai (Ketik Angka Saja)</label>
                <input type="text" name="jam_selesai" id="jam_selesai" class="input-jam" placeholder="Cth: 2000" maxlength="5" oninput="formatJam(this)" onblur="validasiJam(this); hitungDurasi();">
            </div>

            <div class="form-group">
                <label style="color: #d97706;">Durasi (Jam)</label>
                <input type="number" step="0.01" name="durasi_jam" id="durasi_jam" value="<?= htmlspecialchars($durasi_jam) ?>" required style="background: #fffbeb; border-color: #f59e0b; font-weight: 900; color: #b45309; text-align: center; font-size: 16px;">
            </div>

            <div class="form-group">
                <label>Alasan Lembur</label>
                <input type="text" name="alasan_lembur" id="alasan_lembur" value="<?= htmlspecialchars($alasan_lembur) ?>" placeholder="Cth: PRODUKSI / MT MESIN CORR" required>
            </div>
        </div>

        <div class="btn-group" style="margin-top: 10px;">
            <button type="button" id="btnBatalEdit" class="btn-batal-modern" style="display:none;" onclick="batalEdit()">Batal Edit</button>
            <button type="submit" name="simpan_lembur" id="btnSubmitForm" class="btn-submit-modern" style="background:#8b5cf6; padding-left: 50px; padding-right: 50px; font-size: 15px;">💾 Simpan Data Lembur</button>
        </div>
    </form>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
        <div>
            <h2 style="margin:0; border:none; padding:0;">📋 Riwayat Lembur Harian</h2>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-top: 4px;">Data Menampilkan Tanggal: <strong style="color:#8b5cf6; font-size: 15px;"><?= date('d F Y', strtotime($tgl_filter)) ?></strong></div>
        </div>
        
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <button type="button" onclick="exportKeExcel()" class="btn-submit-modern" style="background: #16a34a; padding: 10px 16px; font-size: 13px; display: flex; align-items: center; gap: 6px;">
                📥 Export Excel
            </button>
            
            <form method="GET" action="" style="display: flex; gap: 10px; align-items: center; background: #f8fafc; padding: 10px 16px; border: 1px solid #cbd5e1; border-radius: 8px; margin: 0;">
                <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase;">Ganti Tanggal:</label>
                <input type="date" name="tgl" value="<?= $tgl_filter ?>" style="padding: 6px 10px; font-size: 13px; width: auto; font-weight: bold; border-color: #94a3b8;" onchange="this.form.submit()">
            </form>
        </div>
    </div>
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
        <input type="text" id="cariLembur" placeholder="🔍 Cari nama / bagian..." style="width: 350px; padding: 12px 16px; border-radius:20px; border: 2px solid #cbd5e1; outline:none; font-size: 14px;" onkeyup="filterLembur()">
        
        <div class="live-counter-box">
            <div class="live-counter-item" style="color: #334155;">Total Data: <span id="cnt_all">0</span></div>
            <div class="live-counter-item" style="color: #6d28d9; border-left: 2px solid #cbd5e1; padding-left: 10px;">Total Jam Hari Ini: <span id="cnt_jam">0</span></div>
            <div class="live-counter-item" style="color: #059669; border-left: 2px solid #cbd5e1; padding-left: 10px;">Rata-rata: <span id="cnt_avg">0</span> Jam</div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-premium" id="tabelLemburData">
            <thead>
                <tr>
                    <th class="text-center stk-1">No</th>
                    <th class="text-center stk-2">TGL</th>
                    <th class="text-center stk-3">NAMA KARYAWAN</th>
                    <th class="text-center">Bagian</th>
                    <th class="text-center">Jam Mulai</th>
                    <th class="text-center">Jam Selesai</th>
                    <th class="text-center" style="background-color: #f59e0b; color: #fff;">Durasi (Jam)</th>
                    <th class="text-center">Alasan Lembur</th>
                    <th class="text-center" style="min-width: 160px;" data-noexport="true">Aksi Manajemen</th>
                </tr>
            </thead>
            <tbody id="tabelLemburBody">
                <?php 
                $no = 1;
                $stmt_lembur = $pdo->prepare("SELECT * FROM data_lembur WHERE tanggal = ? ORDER BY bagian ASC, nama_karyawan ASC");
                $stmt_lembur->execute([$tgl_filter]);
                $lembur_list = $stmt_lembur->fetchAll(PDO::FETCH_ASSOC);
                
                if(count($lembur_list) > 0):
                    foreach ($lembur_list as $row): 
                ?>
                <tr id="baris-<?= $row['id'] ?>" class="baris-data" data-durasi="<?= $row['durasi_jam'] ?>">
                    <td class="text-center stk-1" style="color:#64748b; font-weight:bold;"><?= $no++ ?></td>
                    <td class="text-center stk-2" style="font-weight:800; color:#8b5cf6; font-size:14px;"><?= date('d', strtotime($row['tanggal'])) ?></td>
                    <td class="text-center stk-3" style="font-weight:800; color:#0f172a; font-size:14px;"><?= htmlspecialchars($row['nama_karyawan']) ?></td>
                    <td class="text-center"><span class="badge" style="background:#e0f2fe; color:#0284c7; padding: 4px 10px;"><?= htmlspecialchars($row['bagian']) ?></span></td>
                    <td class="text-center" style="font-weight:900; font-family:monospace; font-size:14px; color:#475569;"><?= $row['jam_mulai'] ? date('H:i', strtotime($row['jam_mulai'])) : '-' ?></td>
                    <td class="text-center" style="font-weight:900; font-family:monospace; font-size:14px; color:#475569;"><?= $row['jam_selesai'] ? date('H:i', strtotime($row['jam_selesai'])) : '-' ?></td>
                    <td class="text-center" style="font-weight:900; background:#fffbeb; color:#d97706; font-size:15px;"><?= number_format($row['durasi_jam'], 2, '.', '') ?></td>
                    <td class="text-left" style="font-size:12px; color:#475569; font-weight:600;"><?= htmlspecialchars($row['alasan_lembur']) ?></td>
                    <td class="text-center action-links" data-noexport="true">
                        <a href="javascript:void(0);" onclick="editLemburFast(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_karyawan'])) ?>', '<?= $row['jam_mulai'] ? date('H:i', strtotime($row['jam_mulai'])) : '' ?>', '<?= $row['jam_selesai'] ? date('H:i', strtotime($row['jam_selesai'])) : '' ?>', '<?= $row['durasi_jam'] ?>', '<?= htmlspecialchars(addslashes($row['alasan_lembur'])) ?>')" class="btn-edit">Edit</a>
                        <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Hapus permanen data lembur ini?')" class="btn-hapus">Hapus</a>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="9" class="text-center" style="padding:50px; color:#94a3b8; font-size: 15px; font-weight: 600;">Data Kosong. Belum ada karyawan yang lembur di tanggal ini.</td></tr>
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

    function hitungDurasi() {
        let jamMulai = document.getElementById('jam_mulai').value;
        let jamSelesai = document.getElementById('jam_selesai').value;
        if(jamMulai && jamSelesai && jamMulai.length === 5 && jamSelesai.length === 5) {
            let start = new Date("1970-01-01T" + jamMulai + "Z");
            let end = new Date("1970-01-01T" + jamSelesai + "Z");
            let diff = (end - start) / 1000 / 60 / 60;
            if(diff < 0) diff += 24; 
            document.getElementById('durasi_jam').value = diff.toFixed(2);
        }
    }

    function editLemburFast(id, nama, masuk, keluar, durasi, alasan) {
        document.getElementById('id_edit').value = id;
        
        let selNama = document.getElementById('nama_karyawan');
        if(selNama) {
            let options = Array.from(selNama.options).map(opt => opt.value);
            if (!options.includes(nama)) {
                let newOption = new Option(nama + ' (Nonaktif)', nama);
                selNama.add(newOption);
            }
            selNama.value = nama;
        }

        document.getElementById('jam_mulai').value = masuk;
        document.getElementById('jam_selesai').value = keluar;
        document.getElementById('durasi_jam').value = durasi;
        document.getElementById('alasan_lembur').value = alasan;

        let btnSubmit = document.getElementById('btnSubmitForm');
        btnSubmit.innerHTML = '💾 Update Data Lembur';
        btnSubmit.style.background = '#8b5cf6';
        
        document.getElementById('btnBatalEdit').style.display = 'inline-block';
        
        let title = document.getElementById('formTitle');
        title.innerHTML = '✏️ Mode Edit Data Lembur';
        title.style.color = '#6d28d9';
        
        document.getElementById('cardInputArea').style.borderColor = '#8b5cf6';
        document.getElementById('cardInputArea').style.backgroundColor = '#faf5ff';
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function batalEdit() {
        document.getElementById('id_edit').value = '';
        document.getElementById('nama_karyawan').value = '';
        document.getElementById('jam_mulai').value = '';
        document.getElementById('jam_selesai').value = '';
        document.getElementById('durasi_jam').value = '';
        document.getElementById('alasan_lembur').value = '';

        let btnSubmit = document.getElementById('btnSubmitForm');
        btnSubmit.innerHTML = '💾 Simpan Data Lembur';
        btnSubmit.style.background = '#8b5cf6';
        
        document.getElementById('btnBatalEdit').style.display = 'none';
        
        let title = document.getElementById('formTitle');
        title.innerHTML = '⏳ Form Input Log Lembur';
        title.style.color = '#6d28d9';
        
        document.getElementById('cardInputArea').style.borderColor = '#8b5cf6';
        document.getElementById('cardInputArea').style.backgroundColor = '#ffffff';
    }

    function konfirmasiHapus(id, pesan) {
        if (confirm(pesan)) {
            document.getElementById('inputHapusId').value = id;
            document.getElementById('formHapusGlobal').submit();
        }
    }
    
    function updateCounterLembur() {
        let tr = document.getElementsByClassName("baris-data");
        let visible = 0, totJam = 0;
        
        for (let i = 0; i < tr.length; i++) {
            if (tr[i].style.display !== "none") {
                visible++;
                let durasi = parseFloat(tr[i].getAttribute("data-durasi")) || 0;
                totJam += durasi;
            }
        }
        
        let avg = visible > 0 ? (totJam / visible).toFixed(2) : 0;
        
        document.getElementById('cnt_all').innerText = visible;
        document.getElementById('cnt_jam').innerText = totJam.toFixed(2);
        document.getElementById('cnt_avg').innerText = avg;
    }

    function filterLembur() {
        let input = document.getElementById("cariLembur").value.toLowerCase();
        let tr = document.getElementsByClassName("baris-data");
        for (let i = 0; i < tr.length; i++) {
            let textKandungan = tr[i].innerText.toLowerCase();
            tr[i].style.display = textKandungan.includes(input) ? "" : "none";
        }
        updateCounterLembur();
    }

    function exportKeExcel() {
        let table = document.getElementById("tabelLemburData");
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

        let tglExport = document.querySelector('input[name="tgl"]').value;
        let htmlTemplate = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
                    th, td { border: 1px solid #000000; padding: 6px; text-align: center; font-size: 13px; vertical-align: middle; }
                    th { background-color: #1e293b; color: #ffffff; font-weight: bold; }
                
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
            </head>
            <body>
                <h2 style="text-align: center; margin-bottom: 15px;">Laporan Lembur Karyawan H2 BASE</h2>
                <p><strong>Tanggal:</strong> ${tglExport}</p>
                ${cloneTable.outerHTML}
            </body>
            </html>
        `;

        let blob = new Blob([htmlTemplate], { type: 'application/vnd.ms-excel' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'Data_Lembur_' + tglExport + '.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    window.onload = function() { 
        updateCounterLembur();
    };
</script>
<?php require_once 'footer.php'; ?>

