<?php
require_once 'auth.php';
require_akses('hrd_data');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false;

// Variabel default form kosong
$id_edit = ''; $no_id = ''; $nama = ''; $bagian = ''; $posisi = ''; $status_pkwt = 'KONTRAK';
$alamat = ''; $lingkungan = ''; $status_kawin = ''; $no_hp = ''; $nik_ktp = ''; $iq = ''; $tgl_lahir = ''; $ket_status = 'AKTIF';

// AUTO-CREATE TABEL KARYAWAN
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_karyawan_h2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        no_id VARCHAR(50),
        nama VARCHAR(150) NOT NULL,
        bagian VARCHAR(100),
        posisi VARCHAR(100),
        status_pkwt VARCHAR(50),
        alamat TEXT,
        lingkungan VARCHAR(50),
        status_kawin VARCHAR(50),
        no_hp VARCHAR(50),
        nik_ktp VARCHAR(50),
        iq VARCHAR(20),
        tgl_lahir VARCHAR(150),
        ket_status VARCHAR(50) DEFAULT 'AKTIF'
    )");
} catch (PDOException $e) { die("Error Database: " . $e->getMessage()); }

// LOGIKA TANGKAP DATA UNTUK DI-EDIT
if (isset($_GET['edit'])) {
    $is_edit = true;
    $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM db_karyawan_h2 WHERE id = ?");
    $stmt->execute([$id_edit]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $no_id = $row['no_id']; $nama = $row['nama']; $bagian = $row['bagian']; $posisi = $row['posisi'];
        $status_pkwt = $row['status_pkwt']; $alamat = $row['alamat']; $lingkungan = $row['lingkungan'];
        $status_kawin = $row['status_kawin']; $no_hp = $row['no_hp']; $nik_ktp = $row['nik_ktp'];
        $iq = $row['iq']; $tgl_lahir = $row['tgl_lahir']; $ket_status = $row['ket_status'];
    }
}

// LOGIKA SIMPAN (INSERT BARU) ATAU PERBARUI (UPDATE) DATA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_karyawan'])) {
    $post_id = $_POST['id_edit'];
    $p_noid = trim($_POST['no_id']); $p_nama = strtoupper(trim($_POST['nama']));
    $p_bagian = strtoupper(trim($_POST['bagian'])); $p_posisi = strtoupper(trim($_POST['posisi']));
    $p_pkwt = strtoupper(trim($_POST['status_pkwt'])); $p_alamat = strtoupper(trim($_POST['alamat']));
    $p_lingk = strtoupper(trim($_POST['lingkungan'])); $p_kawin = strtoupper(trim($_POST['status_kawin']));
    $p_hp = trim($_POST['no_hp']); $p_nik = trim($_POST['nik_ktp']); $p_iq = trim($_POST['iq']);
    $p_tgl = strtoupper(trim($_POST['tgl_lahir'])); $p_ket = strtoupper(trim($_POST['ket_status']));

    if (!empty($post_id)) {
        // PROSES UPDATE DATA LAMA
        $stmt = $pdo->prepare("UPDATE db_karyawan_h2 SET no_id=?, nama=?, bagian=?, posisi=?, status_pkwt=?, alamat=?, lingkungan=?, status_kawin=?, no_hp=?, nik_ktp=?, iq=?, tgl_lahir=?, ket_status=? WHERE id=?");
        if ($stmt->execute([$p_noid, $p_nama, $p_bagian, $p_posisi, $p_pkwt, $p_alamat, $p_lingk, $p_kawin, $p_hp, $p_nik, $p_iq, $p_tgl, $p_ket, $post_id])) {
            
            catatLog($pdo, $user_aktif, "Memperbarui profil / data karyawan: $p_nama ($p_bagian).", "✏️");
            header("Location: karyawan_data.php?pesan=edit_sukses"); exit();
        }
    } else {
        // PROSES TAMBAH DATA BARU
        $stmt = $pdo->prepare("INSERT INTO db_karyawan_h2 (no_id, nama, bagian, posisi, status_pkwt, alamat, lingkungan, status_kawin, no_hp, nik_ktp, iq, tgl_lahir, ket_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$p_noid, $p_nama, $p_bagian, $p_posisi, $p_pkwt, $p_alamat, $p_lingk, $p_kawin, $p_hp, $p_nik, $p_iq, $p_tgl, $p_ket])) {
            
            catatLog($pdo, $user_aktif, "Mendaftarkan karyawan baru: $p_nama ($p_bagian).", "👤");
            header("Location: karyawan_data.php?pesan=tambah_sukses"); exit();
        }
    }
}

// LOGIKA HAPUS DATA AMAN (MENGGUNAKAN POST METHOD)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id'])) {
    $id = intval($_POST['hapus_id']);
    
    // Tarik nama karyawan sebelum dihapus agar bisa dilacak di Log
    $stmt_cek = $pdo->prepare("SELECT nama FROM db_karyawan_h2 WHERE id = :id");
    $stmt_cek->execute([':id' => $id]);
    $nama_dihapus = $stmt_cek->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM db_karyawan_h2 WHERE id = :id");
    if($stmt->execute([':id' => $id])){
        catatLog($pdo, $user_aktif, "Menghapus data karyawan ($nama_dihapus) secara permanen.", "🗑️");
        header("Location: karyawan_data.php?pesan=hapus_sukses"); exit();
    }
}

// TANGKAP PESAN NOTIFIKASI
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-success'>✅ Berhasil: Data karyawan dihapus permanen.</div>";
    if ($_GET['pesan'] == 'tambah_sukses') $pesan = "<div class='alert alert-success'>✅ Berhasil: Karyawan baru ditambahkan ke database.</div>";
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success'>✅ Berhasil: Pembaruan data karyawan telah disimpan!</div>";
}

$page_title = "Data Karyawan — H2 BASE ERP";
$active_page = "karyawan_data";
require 'header.php';
?>

<style>
    /* DESAIN KARTU DAN FORM LEGA (ESTETIK) */
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .form-group { display: flex; flex-direction: column; min-width: 140px; }
    .form-group label { margin-bottom: 8px; font-weight: 800; font-size: 11px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;}
    
    input, select { padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; color: #0f172a; background: #f8fafc; outline: none; transition: 0.2s; box-sizing: border-box; width: 100%; font-weight: 600;}
    input:focus, select:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); background: #ffffff;}

    .btn-group { display: flex; gap: 15px; justify-content: flex-end; }
    .btn-submit { background: #0f172a; color: white; border: none; padding: 12px 28px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; transition: 0.2s; }
    .btn-submit:hover { background: #1e293b; transform: translateY(-1px);}
    .btn-batal { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 12px 28px; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 700; text-align: center; transition: 0.2s; cursor: pointer;}
    .btn-batal:hover { background: #fca5a5; }

    /* WIDGET LIVE COUNTER */
    .live-counter-box { background: #ffffff; border: 2px solid #cbd5e1; border-radius: 8px; padding: 12px 15px; display: flex; flex-wrap: wrap; gap: 15px; font-size: 12px; font-weight: 700; color: #475569; box-shadow: 0 2px 4px rgba(0,0,0,0.02);}
    .live-counter-item span { font-size: 15px; font-weight: 900; color: #0f172a; margin-left: 6px;}

    /* DESAIN TABEL */
    .table-responsive { background: white; border-radius: 8px; border: 1px solid #cbd5e1; overflow-x: auto; max-height: 700px; margin-bottom: 20px; -webkit-overflow-scrolling: touch; box-shadow: inset 0 0 5px rgba(0,0,0,0.02);}
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #1e293b; white-space: nowrap; min-width: 1200px; }
    
    .table-premium th, .table-premium td { padding: 14px 16px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; vertical-align: middle; box-sizing: border-box; text-align: center;}
    .table-premium th { background-color: #1e293b; color: #ffffff; font-weight: 700; position: sticky; top: 0; z-index: 10; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;}
    .table-premium th.text-left, .table-premium td.text-left { text-align: left !important; }

    /* KONFIGURASI 3 KOLOM STICKY MENEMPEL DI KIRI */
    .table-premium .stk-1 { position: sticky; left: 0; z-index: 5; background: #ffffff; width: 50px; min-width: 50px; box-sizing: border-box; }
    .table-premium .stk-2 { position: sticky; left: 50px; z-index: 5; background: #ffffff; width: 80px; min-width: 80px; box-sizing: border-box; }
    .table-premium .stk-3 { position: sticky; left: 130px; z-index: 5; background: #ffffff; min-width: 250px; border-right: 3px solid #cbd5e1 !important; box-sizing: border-box; }

    .table-premium th.stk-1, .table-premium th.stk-2, .table-premium th.stk-3 { background: #1e293b; color: white; z-index: 11; }

    /* WARNA BACKGROUND AGAR BELANG-BELANG TABEL TIDAK RUSAK SAAT STICKY */
    .table-premium tbody tr:nth-child(even) td { background: #f8fafc; }
    .table-premium tbody tr:nth-child(even) td.stk-1,
    .table-premium tbody tr:nth-child(even) td.stk-2,
    .table-premium tbody tr:nth-child(even) td.stk-3 { background: #f8fafc; }
    
    .table-premium tbody tr:hover td,
    .table-premium tbody tr:hover td.stk-1,
    .table-premium tbody tr:hover td.stk-2,
    .table-premium tbody tr:hover td.stk-3 { background: #f1f5f9 !important; }

    .btn-edit { display: inline-block; background: #e0f2fe; color: #0284c7; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #bae6fd; cursor: pointer;}
    .btn-edit:hover { background: #bae6fd; color: #0369a1; }
    .btn-hapus { display: inline-block; background: #fee2e2; color: #dc2626; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #fecaca; margin-left: 6px; cursor: pointer;}
    .btn-hapus:hover { background: #fecaca; color: #b91c1c; }

    .search-box { padding: 12px 16px; width: 350px; border: 2px solid #cbd5e1; border-radius: 20px; font-size: 14px; outline: none; transition: 0.2s; }
    .search-box:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }

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

<div class="card" <?= $is_edit ? 'style="border-top: 5px solid #0ea5e9; background: #f0f9ff;"' : 'style="border-top: 5px solid #10b981;"' ?>>
    <h2 style="<?= $is_edit ? 'color: #0284c7; border-bottom: 2px solid #bae6fd;' : 'color: #047857; border-bottom: 2px solid #a7f3d0;' ?> padding-bottom: 12px; margin-top: 0; font-size: 18px;">
        <?= $is_edit ? '✏️ Mode Edit Data Karyawan' : '➕ Form Input Data Karyawan Baru' ?>
    </h2>
    
    <form action="" method="POST" autocomplete="off">
        <input type="hidden" name="id_edit" value="<?= $id_edit ?>">
        
        <div class="form-grid">
            <div class="form-group"><label>No ID</label><input type="text" name="no_id" value="<?= htmlspecialchars($no_id) ?>" placeholder="Cth: ID-001"></div>
            <div class="form-group" style="grid-column: span 2;"><label>Nama Lengkap</label><input type="text" name="nama" value="<?= htmlspecialchars($nama) ?>" required placeholder="Ketik nama lengkap..."></div>
            <div class="form-group"><label>Bagian</label><input type="text" name="bagian" value="<?= htmlspecialchars($bagian) ?>" required placeholder="Cth: CORR"></div>
            <div class="form-group"><label>Posisi / Jabatan</label><input type="text" name="posisi" value="<?= htmlspecialchars($posisi) ?>" required placeholder="Cth: OPERATOR"></div>
            
            <div class="form-group">
                <label>Status PKWT</label>
                <select name="status_pkwt" required>
                    <option value="KONTRAK" <?= $status_pkwt == 'KONTRAK' ? 'selected' : '' ?>>KONTRAK</option>
                    <option value="HL" <?= $status_pkwt == 'HL' ? 'selected' : '' ?>>HARIAN LEPAS (HL)</option>
                    <option value="TETAP" <?= $status_pkwt == 'TETAP' ? 'selected' : '' ?>>KARYAWAN TETAP</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Lingkungan</label>
                <select name="lingkungan">
                    <option value="" <?= $lingkungan == '' ? 'selected' : '' ?>>- Kosong -</option>
                    <option value="LOKAL" <?= $lingkungan == 'LOKAL' ? 'selected' : '' ?>>LOKAL</option>
                    <option value="LUAR" <?= $lingkungan == 'LUAR' ? 'selected' : '' ?>>LUAR</option>
                </select>
            </div>
            
            <div class="form-group"><label>Status Kawin</label><input type="text" name="status_kawin" value="<?= htmlspecialchars($status_kawin) ?>" placeholder="Cth: K/1"></div>
            <div class="form-group"><label>No HP / WA</label><input type="text" name="no_hp" value="<?= htmlspecialchars($no_hp) ?>" placeholder="Cth: 0812..."></div>
            <div class="form-group"><label>NIK (KTP)</label><input type="text" name="nik_ktp" value="<?= htmlspecialchars($nik_ktp) ?>" placeholder="Cth: 320..."></div>
            <div class="form-group"><label>IQ / Tes Kognitif</label><input type="text" name="iq" value="<?= htmlspecialchars($iq) ?>" placeholder="Cth: 110"></div>
            <div class="form-group" style="grid-column: span 2;"><label>Tempat, Tgl Lahir</label><input type="text" name="tgl_lahir" value="<?= htmlspecialchars($tgl_lahir) ?>" placeholder="Cth: JAKARTA, 17 AGUSTUS 1990"></div>
            
            <div class="form-group">
                <label>Keterangan Status</label>
                <select name="ket_status" required style="<?= $ket_status != 'AKTIF' ? 'background:#fee2e2; color:#b91c1c; border-color:#fca5a5;' : 'background:#dcfce7; color:#166534; border-color:#86efac;' ?>">
                    <option value="AKTIF" <?= $ket_status == 'AKTIF' ? 'selected' : '' ?>>✅ AKTIF</option>
                    <option value="PHK" <?= $ket_status == 'PHK' ? 'selected' : '' ?>>❌ PHK</option>
                    <option value="RESIGN" <?= $ket_status == 'RESIGN' ? 'selected' : '' ?>>❌ RESIGN</option>
                </select>
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Alamat Domisili Lengkap</label>
                <input type="text" name="alamat" value="<?= htmlspecialchars($alamat) ?>" placeholder="Ketik alamat lengkap...">
            </div>
        </div>
        
        <div class="btn-group" style="margin-top: 15px;">
            <?php if($is_edit): ?>
                <a href="karyawan_data.php" class="btn-batal-modern" style="background:#fef2f2; color:#991b1b; border-color:#fecaca;">Batal Edit</a>
                <button type="submit" name="simpan_karyawan" class="btn-submit-modern" style="background:#0ea5e9;">💾 Update Data Karyawan</button>
            <?php else: ?>
                <button type="reset" class="btn-batal-modern">Bersihkan</button>
                <button type="submit" name="simpan_karyawan" class="btn-submit-modern">Simpan ke Database</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card" style="padding-bottom: 0; box-shadow:none; border:none; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <h2 style="margin: 0; border: none; padding: 0;">📋 Database Karyawan H2</h2>
    
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <input type="text" id="pencarianKaryawan" class="search-box" onkeyup="cariKaryawanLive()" placeholder="🔍 Cari Nama, Bagian, atau NIK KTP...">
        <button type="button" onclick="exportKeExcel()" class="btn-submit-modern" style="background: #16a34a; padding: 12px 16px; font-size: 13px; display: flex; align-items: center; gap: 6px;">
            📥 Export Excel
        </button>
    </div>
</div>

<div style="margin-bottom: 15px;">
    <div class="live-counter-box" style="display:inline-flex;">
        <div class="live-counter-item" style="color: #334155;">Total Ditampilkan: <span id="cnt_all">0</span></div>
        <div class="live-counter-item" style="color: #059669; border-left: 2px solid #cbd5e1; padding-left: 10px;">🟢 Aktif: <span id="cnt_aktif">0</span></div>
        <div class="live-counter-item" style="color: #dc2626; border-left: 2px solid #cbd5e1; padding-left: 10px;">🔴 Nonaktif: <span id="cnt_nonaktif">0</span></div>
        <div class="live-counter-item" style="color: #0284c7; border-left: 2px solid #cbd5e1; padding-left: 10px;">Tetap: <span id="cnt_tetap">0</span></div>
        <div class="live-counter-item" style="color: #d97706; border-left: 2px solid #cbd5e1; padding-left: 10px;">Kontrak: <span id="cnt_kontrak">0</span></div>
        <div class="live-counter-item" style="color: #ea580c; border-left: 2px solid #cbd5e1; padding-left: 10px;">HL: <span id="cnt_hl">0</span></div>
    </div>
</div>

<div class="table-responsive">
    <table class="table-premium" id="tabelKaryawan">
        <thead>
            <tr>
                <th class="stk-1">NO</th>
                <th class="stk-2">NO ID</th>
                <th class="stk-3 text-left">NAMA LENGKAP</th>
                <th>BAGIAN</th>
                <th>POSISI</th>
                <th>STATUS PKWT</th>
                <th class="text-left" style="min-width: 280px;">ALAMAT LENGKAP</th>
                <th>LINGKUNGAN</th>
                <th>ST. KAWIN</th>
                <th>NO HP</th>
                <th>NIK KTP</th>
                <th>IQ</th>
                <th style="min-width: 160px;">TGL LAHIR</th>
                <th>STATUS</th>
                <th style="min-width: 100px;" data-noexport="true">AKSI</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            // 🚀 PERUBAHAN SAKTI: Auto-Sort HANYA berdasarkan Abjad Nama (A-Z)
            $karyawan_list = $pdo->query("SELECT * FROM db_karyawan_h2 ORDER BY nama ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach($karyawan_list as $row): 
                $pkwt_bg = ($row['status_pkwt'] == 'TETAP') ? '#dcfce7' : (($row['status_pkwt'] == 'KONTRAK') ? '#e0f2fe' : '#fef08a');
                $pkwt_co = ($row['status_pkwt'] == 'TETAP') ? '#166534' : (($row['status_pkwt'] == 'KONTRAK') ? '#0284c7' : '#854d0e');
                
                $ket_bg = ($row['ket_status'] == 'AKTIF') ? '#dcfce7' : '#fee2e2';
                $ket_co = ($row['ket_status'] == 'AKTIF') ? '#166534' : '#991b1b';
            ?>
            <tr class="baris-data" data-status="<?= htmlspecialchars($row['ket_status']) ?>" data-pkwt="<?= htmlspecialchars($row['status_pkwt']) ?>">
                <td class="stk-1" style="font-weight:bold; color:#64748b;"><?= $no++ ?></td>
                <td class="stk-2" style="font-weight:600;"><?= htmlspecialchars($row['no_id']) ?></td>
                <td class="stk-3 text-left" style="font-weight:800; color:#0f172a; font-size:14px;"><?= htmlspecialchars($row['nama']) ?></td>
                <td style="font-weight:700; color:#0284c7;"><?= htmlspecialchars($row['bagian']) ?></td>
                <td style="font-weight:600; color:#475569;"><?= htmlspecialchars($row['posisi']) ?></td>
                <td><span class="badge" style="background:<?= $pkwt_bg ?>; color:<?= $pkwt_co ?>; padding: 6px 10px;"><?= htmlspecialchars($row['status_pkwt']) ?></span></td>
                <td class="text-left" style="white-space: normal; font-size: 11px; line-height: 1.4; color:#334155;"><?= htmlspecialchars($row['alamat']) ?></td>
                <td><?= htmlspecialchars($row['lingkungan']) ?></td>
                <td style="font-weight:bold;"><?= htmlspecialchars($row['status_kawin']) ?></td>
                <td style="font-family:monospace; font-weight:600;"><?= htmlspecialchars($row['no_hp']) ?></td>
                <td style="font-family:monospace;"><?= htmlspecialchars($row['nik_ktp']) ?></td>
                <td style="font-weight:bold; color:#0ea5e9;"><?= htmlspecialchars($row['iq']) ?></td>
                <td style="font-size:12px; color:#475569;"><?= htmlspecialchars($row['tgl_lahir']) ?></td>
                <td><span class="badge" style="background:<?= $ket_bg ?>; color:<?= $ket_co ?>; padding: 6px 10px;"><?= htmlspecialchars($row['ket_status']) ?></span></td>
                <td class="action-links" data-noexport="true">
                    <a href="karyawan_data.php?edit=<?= $row['id'] ?>" class="btn-edit" style="color: #0ea5e9;">Edit</a> 
                    <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Yakin menghapus permanen data karyawan <?= htmlspecialchars(addslashes($row['nama'])) ?>?')" class="btn-hapus">Hapus</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<form id="formHapusGlobal" method="POST" style="display: none;">
    <input type="hidden" name="hapus_id" id="inputHapusId">
    <input type="hidden" name="hapus_data" value="1">
</form>

<script>
    function konfirmasiHapus(id, pesan) {
        if (confirm(pesan)) {
            document.getElementById('inputHapusId').value = id;
            document.getElementById('formHapusGlobal').submit();
        }
    }

    // 🚀 FUNGSI UPDATE LIVE COUNTER WIDGET YANG SUDAH DIPISAH KONTRAK & HL
    function updateCounterKaryawan() {
        let tr = document.getElementsByClassName("baris-data");
        let visible = 0, aktif = 0, nonaktif = 0, tetap = 0, kontrak = 0, hl = 0;
        
        for (let i = 0; i < tr.length; i++) {
            if (tr[i].style.display !== "none") {
                visible++;
                let status = tr[i].getAttribute("data-status");
                let pkwt = tr[i].getAttribute("data-pkwt");
                
                if (status === 'AKTIF') { aktif++; } else { nonaktif++; }
                if (pkwt === 'TETAP') { tetap++; } 
                else if (pkwt === 'KONTRAK') { kontrak++; }
                else if (pkwt === 'HL') { hl++; }
            }
        }
        
        document.getElementById('cnt_all').innerText = visible;
        document.getElementById('cnt_aktif').innerText = aktif;
        document.getElementById('cnt_nonaktif').innerText = nonaktif;
        document.getElementById('cnt_tetap').innerText = tetap;
        document.getElementById('cnt_kontrak').innerText = kontrak;
        document.getElementById('cnt_hl').innerText = hl;
    }

    // 🚀 PENCARIAN LIVE DENGAN UPDATE COUNTER
    function cariKaryawanLive() {
        let input = document.getElementById("pencarianKaryawan").value.toLowerCase();
        let table = document.getElementById("tabelKaryawan");
        let tr = table.getElementsByClassName("baris-data");
        for (let i = 0; i < tr.length; i++) {
            let textKandungan = tr[i].innerText.toLowerCase();
            tr[i].style.display = textKandungan.includes(input) ? "" : "none"; 
        }
        updateCounterKaryawan();
    }

    // 🚀 FUNGSI EXPORT KE EXCEL BERSIH (TANPA KOLOM AKSI)
    function exportKeExcel() {
        let table = document.getElementById("tabelKaryawan");
        if (!table) return;

        let cloneTable = table.cloneNode(true);

        // Buang th dan td yang ada flag noexport
        let noExportTh = cloneTable.querySelectorAll('th[data-noexport="true"]');
        noExportTh.forEach(th => th.remove());
        
        let noExportTd = cloneTable.querySelectorAll('td[data-noexport="true"]');
        noExportTd.forEach(td => td.remove());

        // Ratakan badge agar tampil rapi tanpa styling aneh di Excel
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
                    th, td { border: 1px solid #000000; padding: 6px; text-align: left; font-size: 13px; vertical-align: middle; }
                    th { background-color: #1e293b; color: #ffffff; font-weight: bold; text-align: center; }
                
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
                <h2 style="text-align: center; margin-bottom: 15px;">Database Karyawan H2 BASE</h2>
                <p><strong>Diekspor pada:</strong> ${new Date().toLocaleDateString('id-ID')}</p>
                ${cloneTable.outerHTML}
            </body>
            </html>
        `;

        let blob = new Blob([htmlTemplate], { type: 'application/vnd.ms-excel' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'Database_Karyawan_H2_' + new Date().toISOString().slice(0,10) + '.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Jalankan counter saat pertama kali halaman diload
    window.onload = function() { 
        updateCounterKaryawan(); 
    };
</script>
<?php require_once 'footer.php'; ?>

