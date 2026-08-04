<?php
require_once 'auth.php';
require_akses('hrd_data');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false;

// 🚀 FILTER TANGGAL HARIAN (Default ke Hari Ini)
$tgl_filter = isset($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d');


// 🚀 AUTO-CREATE TABEL ABSENSI & TABEL MASTER SHIFT HARIAN
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS data_absensi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        nama_karyawan VARCHAR(150) NOT NULL,
        jam_masuk TIME NULL,
        jam_keluar TIME NULL,
        status_masuk VARCHAR(50) DEFAULT 'BELUM ABSEN',
        keterangan VARCHAR(50) DEFAULT 'HADIR',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt_check_col = $pdo->query("SHOW COLUMNS FROM data_absensi LIKE 'jadwal_shift'");
    if ($stmt_check_col->rowCount() == 0) {
        $pdo->exec("ALTER TABLE data_absensi ADD COLUMN jadwal_shift VARCHAR(100) DEFAULT '' AFTER nama_karyawan");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS master_shift_harian (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        nama_shift VARCHAR(50) NOT NULL,
        jam_masuk TIME NOT NULL,
        jam_keluar TIME NOT NULL
    )");

    // 🚀 SETINGAN DEFAULT BARU
    $stmt_cek_shift = $pdo->prepare("SELECT COUNT(*) FROM master_shift_harian WHERE tanggal = ?");
    $stmt_cek_shift->execute([$tgl_filter]);
    if ($stmt_cek_shift->fetchColumn() == 0) {
        $defaults = [
            ['☀️ Shift 1', '07:00', '16:00'],
            ['🌙 Shift 2', '22:00', '07:00'],
            ['🛡️ Security 1', '08:00', '16:00'],
            ['🛡️ Security 2', '16:00', '00:00'],
            ['🛡️ Security 3', '00:00', '08:00']
        ];
        $stmt_ins = $pdo->prepare("INSERT INTO master_shift_harian (tanggal, nama_shift, jam_masuk, jam_keluar) VALUES (?, ?, ?, ?)");
        foreach($defaults as $d) {
            $stmt_ins->execute([$tgl_filter, $d[0], $d[1], $d[2]]);
        }
    }
} catch (PDOException $e) {}

// =======================================================================
// 🚀 ⚡ CORE BARU: LOGIKA AUTO-GENERATE ABSENSI MASSAL (SEMUA LIBUR)
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_massal']) && $user_role != 'Viewer') {
    $tgl_gen = $_POST['tgl_gen'];
    
    // Tarik data seluruh karyawan AKTIF yang belum melakukan absensi di tanggal tersebut
    $stmt_k_aktif = $pdo->prepare("
        SELECT nama, bagian FROM db_karyawan_h2 
        WHERE ket_status='AKTIF' 
        AND UPPER(TRIM(nama)) NOT IN (SELECT UPPER(TRIM(nama_karyawan)) FROM data_absensi WHERE tanggal = ?)
    ");
    $stmt_k_aktif->execute([$tgl_gen]);
    $karyawan_tambah = $stmt_k_aktif->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($karyawan_tambah) > 0) {
        $stmt_ins_absen = $pdo->prepare("INSERT INTO data_absensi (tanggal, nama_karyawan, jadwal_shift, jam_masuk, jam_keluar, status_masuk, keterangan) VALUES (?, ?, ?, NULL, NULL, 'TIDAK MASUK', 'LIBUR')");
        
        foreach ($karyawan_tambah as $k) {
            $nama = strtoupper(trim($k['nama']));
            $bagian = strtoupper(trim($k['bagian'] ?? ''));
            
            if ($bagian == 'SECURITY') {
                $jadwal = "🛡️ Security 1 (08:00 - 16:00)";
            } else {
                $jadwal = "☀️ Shift 1 (07:00 - 16:00)";
            }
            
            $stmt_ins_absen->execute([$tgl_gen, $nama, $jadwal]);
        }
        catatLog($pdo, $user_aktif, "Auto-Generate Absensi Massal (LIBUR) sukses untuk " . count($karyawan_tambah) . " jiwa.", "⚡");
        header("Location: karyawan_absen.php?pesan=gen_sukses&tgl=$tgl_gen"); exit();
    } else {
        header("Location: karyawan_absen.php?pesan=gen_kosong&tgl=$tgl_gen"); exit();
    }
}

// =======================================================================
// 🚀 PROSES UPDATE MASTER JAM SHIFT (PER TANGGAL)
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_master_shift']) && $user_role != 'Viewer') {
    if (isset($_POST['id_shift'])) {
        foreach($_POST['id_shift'] as $index => $id_s) {
            $ns = $_POST['nama_shift'][$index];
            $jm = $_POST['jam_masuk_shift'][$index];
            $jk = $_POST['jam_keluar_shift'][$index];
            $pdo->prepare("UPDATE master_shift_harian SET nama_shift=?, jam_masuk=?, jam_keluar=? WHERE id=? AND tanggal=?")->execute([$ns, $jm, $jk, $id_s, $tgl_filter]);
        }
        catatLog($pdo, $user_aktif, "Mengubah jadwal shift untuk tanggal " . date('d/m/Y', strtotime($tgl_filter)), "⚙️");
        header("Location: karyawan_absen.php?pesan=shift_sukses&tgl=$tgl_filter"); exit();
    }
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_shift_baru']) && $user_role != 'Viewer') {
    $pdo->prepare("INSERT INTO master_shift_harian (tanggal, nama_shift, jam_masuk, jam_keluar) VALUES (?, ?, ?, ?)")
        ->execute([$tgl_filter, 'Shift Baru', '00:00', '00:00']);
    header("Location: karyawan_absen.php?tgl=$tgl_filter"); exit();
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_shift']) && $user_role != 'Viewer') {
    $pdo->prepare("DELETE FROM master_shift_harian WHERE id=? AND tanggal=?")->execute([$_POST['hapus_shift_id'], $tgl_filter]);
    header("Location: karyawan_absen.php?tgl=$tgl_filter"); exit();
}

// =======================================================================
// 🚀 LOGIKA HAPUS DATA ABSEN 
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id']) && $user_role != 'Viewer') {
    $id = intval($_POST['hapus_id']);
    $n_stmt = $pdo->prepare("SELECT nama_karyawan, tanggal FROM data_absensi WHERE id=?"); 
    $n_stmt->execute([$id]); 
    $row_del = $n_stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("DELETE FROM data_absensi WHERE id = ?");
    if ($stmt->execute([$id])) {
        $tgl_indo = date('d/m/Y', strtotime($row_del['tanggal']));
        catatLog($pdo, $user_aktif, "Menghapus data absensi {$row_del['nama_karyawan']} (Tgl: $tgl_indo).", "🗑️");
        header("Location: karyawan_absen.php?pesan=hapus_sukses&tgl=" . $row_del['tanggal']); exit();
    }
}

// Nilai Default Form Input 
$id_edit = ''; $tanggal = $tgl_filter; $nama_karyawan = ''; $jadwal_shift_edit = '';
$jam_masuk = ''; $jam_keluar = ''; $keterangan = 'HADIR';

// PROSES TANGKAP DATA EDIT
if (isset($_GET['edit']) && $user_role != 'Viewer') {
    $is_edit = true; $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM data_absensi WHERE id = ?"); $stmt->execute([$id_edit]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $tanggal = $row['tanggal']; $nama_karyawan = $row['nama_karyawan']; 
        $jadwal_shift_edit = $row['jadwal_shift'];
        $jam_masuk = $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : ''; 
        $jam_keluar = $row['jam_keluar'] ? date('H:i', strtotime($row['jam_keluar'])) : ''; 
        $keterangan = $row['keterangan'];
    }
}

// =======================================================================
// 🚀 PROSES SIMPAN / UPDATE DATA ABSEN
// =======================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_absen']) && $user_role != 'Viewer') {
    $post_id = $_POST['id_edit'];
    $tanggal_input = $_POST['tanggal'];
    $nama_karyawan = strtoupper(trim($_POST['nama_karyawan']));
    $shift_raw = $_POST['jadwal_shift']; 
    $keterangan = $_POST['keterangan'];
    $jam_masuk = empty($_POST['jam_masuk']) ? NULL : $_POST['jam_masuk'] . ':00';
    $jam_keluar = empty($_POST['jam_keluar']) ? NULL : $_POST['jam_keluar'] . ':00';
    $status_masuk = 'BELUM ABSEN';

    $shift_parts = explode('|', $shift_raw);
    $nama_shift_db = $shift_parts[0];
    $target_masuk_db = isset($shift_parts[1]) ? $shift_parts[1] : '07:00';
    $target_keluar_db = isset($shift_parts[2]) ? $shift_parts[2] : '16:00';
    $jadwal_shift_simpan = "$nama_shift_db ($target_masuk_db - $target_keluar_db)";

    if (!in_array($keterangan, ['HADIR', 'ON'])) {
        $status_masuk = 'TIDAK MASUK';
        $jam_masuk = NULL; 
        $jam_keluar = NULL;
    } elseif ($jam_masuk !== NULL) {
        $waktu_masuk_aktual = strtotime($jam_masuk);
        $waktu_target = strtotime($target_masuk_db);
        
        $jm_hr = (int)date('H', $waktu_masuk_aktual);
        $tg_hr = (int)date('H', $waktu_target);
        if ($tg_hr >= 18 && $jm_hr < 12) { $waktu_masuk_aktual = strtotime('+1 day', $waktu_masuk_aktual); }
        if ($tg_hr < 12 && $jm_hr >= 18) { $waktu_target = strtotime('+1 day', $waktu_target); }

        $waktu_telat = strtotime('+10 minutes', $waktu_target);
        $waktu_awal = strtotime('-20 minutes', $waktu_target);

        if ($waktu_masuk_aktual > $waktu_telat) {
            $status_masuk = 'TERLAMBAT';
        } elseif ($waktu_masuk_aktual < $waktu_awal) {
            $status_masuk = 'LEBIH AWAL';
        } else {
            $status_masuk = 'TEPAT WAKTU';
        }
    }

    if (!empty($post_id)) {
        $stmt_cek = $pdo->prepare("SELECT id FROM data_absensi WHERE tanggal = ? AND UPPER(nama_karyawan) = ? AND id != ?");
        $stmt_cek->execute([$tanggal_input, $nama_karyawan, $post_id]);
        if ($stmt_cek->rowCount() > 0) {
            header("Location: karyawan_absen.php?pesan=gagal_double&tgl=$tanggal_input&nama=" . urlencode($nama_karyawan)); exit();
        }

        $stmt = $pdo->prepare("UPDATE data_absensi SET tanggal=?, nama_karyawan=?, jadwal_shift=?, jam_masuk=?, jam_keluar=?, status_masuk=?, keterangan=? WHERE id=?");
        $stmt->execute([$tanggal_input, $nama_karyawan, $jadwal_shift_simpan, $jam_masuk, $jam_keluar, $status_masuk, $keterangan, $post_id]);
        catatLog($pdo, $user_aktif, "Mengupdate absen: $nama_karyawan.", "✏️");
        header("Location: karyawan_absen.php?pesan=edit_sukses&tgl=$tanggal_input#baris-$post_id"); exit();
    } else {
        $stmt_cek = $pdo->prepare("SELECT id FROM data_absensi WHERE tanggal = ? AND UPPER(nama_karyawan) = ?");
        $stmt_cek->execute([$tanggal_input, $nama_karyawan]);
        if ($stmt_cek->rowCount() > 0) {
            header("Location: karyawan_absen.php?pesan=gagal_double&tgl=$tanggal_input&nama=" . urlencode($nama_karyawan)); exit();
        }

        $stmt = $pdo->prepare("INSERT INTO data_absensi (tanggal, nama_karyawan, jadwal_shift, jam_masuk, jam_keluar, status_masuk, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tanggal_input, $nama_karyawan, $jadwal_shift_simpan, $jam_masuk, $jam_keluar, $status_masuk, $keterangan]);
        $last_id = $pdo->lastInsertId();
        catatLog($pdo, $user_aktif, "Menginput absen: $nama_karyawan ($status_masuk).", "⏱️");
        header("Location: karyawan_absen.php?pesan=simpan_sukses&tgl=$tanggal_input#baris-$last_id"); exit();
    }
}

// 🚀 TAMPILKAN PESAN NOTIFIKASI
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'simpan_sukses') $pesan = "<div class='alert alert-success' style='padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 500; background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;'>✅ Data absensi berhasil disimpan! Status telat dihitung otomatis.</div>";
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success' style='padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 500; background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;'>✅ Perubahan absen berhasil diperbarui!</div>";
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-success' style='padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 500; background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;'>🗑️ Riwayat absen dihapus permanen!</div>";
    if ($_GET['pesan'] == 'shift_sukses') $pesan = "<div class='alert alert-success' style='padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 500; background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;'>⚙️ Pengaturan Jam Shift KHUSUS TANGGAL INI berhasil disimpan!</div>";
    if ($_GET['pesan'] == 'gen_sukses') $pesan = "<div class='alert alert-success' style='padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 500; background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;'>🏖️ <strong>BERHASIL!</strong> Semua karyawan aktif telah terdaftar sebagai <strong>LIBUR</strong>. Silakan klik tombol <strong>Edit</strong> di bawah jika ada karyawan yang masuk kerja/lembur!</div>";
    if ($_GET['pesan'] == 'gen_kosong') $pesan = "<div class='alert alert-warning' style='padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 500; background-color: #fffbeb; color: #b45309; border: 1px solid #fde68a;'>⚠️ Perhatian: Seluruh karyawan aktif di tanggal ini sudah terdaftar absensinya.</div>";
    if ($_GET['pesan'] == 'gagal_double') {
        $nm = isset($_GET['nama']) ? htmlspecialchars($_GET['nama']) : 'Karyawan ini';
        $pesan = "<div class='alert alert-danger' style='background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 14px;'>❌ <strong>GAGAL DISIMPAN (DOUBLE INPUT):</strong> <u>$nm</u> sudah memiliki data absensi pada tanggal tersebut! Tidak boleh memasukkan nama yang sama 2 kali dalam 1 hari.</div>";
    }
}

$list_karyawan = [];
$karyawan_belum_absen = [];
$jumlah_belum_absen = 0;

try {
    $list_karyawan = $pdo->query("SELECT nama FROM db_karyawan_h2 WHERE ket_status='AKTIF' ORDER BY nama ASC")->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt_master_shift = $pdo->prepare("SELECT * FROM master_shift_harian WHERE tanggal = ? ORDER BY id ASC");
    $stmt_master_shift->execute([$tgl_filter]);
    $master_shift_list = $stmt_master_shift->fetchAll(PDO::FETCH_ASSOC);

    $stmt_belum = $pdo->prepare("
        SELECT nama FROM db_karyawan_h2 
        WHERE ket_status='AKTIF' 
        AND UPPER(TRIM(nama)) NOT IN (SELECT UPPER(TRIM(nama_karyawan)) FROM data_absensi WHERE tanggal = ?)
        ORDER BY nama ASC
    ");
    $stmt_belum->execute([$tgl_filter]);
    $karyawan_belum_absen = $stmt_belum->fetchAll(PDO::FETCH_ASSOC);
    $jumlah_belum_absen = count($karyawan_belum_absen);
} catch (PDOException $e) {}

$page_title = "Absensi Harian — H2 BASE ERP";
$active_page = "karyawan_absen";
require 'header.php';
?>

<style>
    /* 🚀 RE-DESIGN TOTAL DNA MODERN SAAS (WHITE CLEAN FIELD) */
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 20px; }
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
    
    input.input-jam { text-align: center; font-family: monospace; font-size: 15px; letter-spacing: 2px; font-weight: 900; color: #1e40af; border-color: #cbd5e1;}
    input.input-jam:focus { border-color: #3b82f6;}

    /* 🚀 PREMIUM BUTTONS */
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

    /* WIDGET LIVE COUNTER LIGHT MODE */
    .live-counter-box { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 18px; display: flex; flex-wrap: wrap; gap: 15px; font-size: 12px; font-weight: 700; color: #475569; box-shadow: 0 1px 3px rgba(0,0,0,0.01);}
    .live-counter-item span { font-size: 16px; font-weight: 900; color: #0f172a; margin-left: 6px;}

    /* LACI JAM SHIFT */
    .shift-settings { background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; margin-bottom: 24px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.01); }
    .shift-header { padding: 16px 20px; background: #fef3c7; cursor: pointer; font-weight: 800; color: #92400e; display: flex; justify-content: space-between; align-items: center; font-size: 13px;}
    .shift-header:hover { background: #fde68a; }
    .shift-content { padding: 24px; display: none; border-top: 1px solid #fde68a; }
    .shift-content.open { display: block; }
    .shift-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px;}
    .shift-table th, .shift-table td { padding: 10px; border-bottom: 1px solid #fde68a; font-weight: 600; color: #78350f; text-align: center;}
    .shift-table th { background: #fef3c7; font-weight: 800; text-transform: uppercase; font-size: 11px;}

    /* RADAR RE-DESIGN */
    .radar-box { background: #fff1f2; border: 1px solid #fecaca; border-radius: 12px; margin-bottom: 24px; padding: 20px; }
    .radar-title { font-size: 14px; font-weight: 800; color: #be123c; display: flex; align-items: center; justify-content: space-between; margin-top: 0; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #fca5a5;}
    .radar-tag { display: inline-block; background: #ffffff; border: 1px solid #fca5a5; color: #9f1239; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; margin: 0 6px 8px 0; cursor: pointer; transition: all 0.2s;}
    .radar-tag:hover { background: #ffe4e6; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(225, 29, 72, 0.1); }

    /* TABEL ABSEN SHARP TEXT */
    .table-responsive { background: white; border-radius: 10px; border: 1px solid #cbd5e1; overflow-x: auto; max-height: 700px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.01);}
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #0f172a; white-space: nowrap; min-width: 1100px; }
    .table-premium th, .table-premium td { padding: 12px 14px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; vertical-align: middle; font-weight: 600; }
    .table-premium th { background-color: #0f172a; color: #ffffff; font-weight: 700; position: sticky; top: 0; z-index: 10; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 2px solid #1e293b; text-align: center;}
    
    .stk-1 { position: sticky; left: 0; z-index: 5; width: 40px; min-width: 40px; text-align: center !important; background: #fff;}
    .stk-2 { position: sticky; left: 40px; z-index: 5; width: 50px; min-width: 50px; text-align: center !important; background: #fff;}
    .stk-3 { position: sticky; left: 90px; z-index: 5; width: 240px; min-width: 240px; border-right: 3px solid #cbd5e1 !important; text-align: left !important; background: #fff; padding-left: 15px;}
    .table-premium th.stk-1, .table-premium th.stk-2, .table-premium th.stk-3 { background-color: #0f172a; color: white; z-index: 11; }
    
    /* TR COLORS */
    tr.row-hadir:nth-child(even) td, tr.row-hadir:nth-child(even) td.stk-1, tr.row-hadir:nth-child(even) td.stk-2, tr.row-hadir:nth-child(even) td.stk-3 { background-color: #f8fafc; }
    tr.row-hadir:hover td, tr.row-hadir:hover td.stk-1, tr.row-hadir:hover td.stk-2, tr.row-hadir:hover td.stk-3 { background-color: #f1f5f9 !important; }
    tr.row-off td, tr.row-off td.stk-1, tr.row-off td.stk-2, tr.row-off td.stk-3 { background-color: #f1f5f9 !important; color: #475569;}
    tr.row-libur td, tr.row-libur td.stk-1, tr.row-libur td.stk-2, tr.row-libur td.stk-3 { background-color: #f8fafc !important; color: #64748b;}
    tr.row-cuti td, tr.row-cuti td.stk-1, tr.row-cuti td.stk-2, tr.row-cuti td.stk-3 { background-color: #faf5ff !important; color: #6b21a8; }
    tr.row-alpha td, tr.row-alpha td.stk-1, tr.row-alpha td.stk-2, tr.row-alpha td.stk-3 { background-color: #fef2f2 !important; color: #991b1b; }
    tr.row-warning td, tr.row-warning td.stk-1, tr.row-warning td.stk-2, tr.row-warning td.stk-3 { background-color: #fffbeb !important; color: #92400e; }
    tr.row-telat td, tr.row-telat td.stk-1, tr.row-telat td.stk-2, tr.row-telat td.stk-3 { background-color: #fff5f5 !important; color: #9f1239; }

    .badge { padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; display: inline-block; text-align: center;}
    .search-box-modern { padding: 11px 16px; width: 350px; border: 2px solid #cbd5e1; border-radius: 20px; font-size: 13px; outline: none; transition: 0.2s; font-weight: 600;}
    .search-box-modern:focus { border-color: #0ea5e9; background: #fff;}
    .btn-edit { display: inline-block; background: #e0f2fe; color: #0284c7; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #bae6fd; cursor: pointer;}
    .btn-edit:hover { background: #bae6fd; color: #0369a1; }
    .btn-hapus { display: inline-block; background: #fee2e2; color: #dc2626; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #fecaca; margin-left: 6px; cursor: pointer;}
    .btn-hapus:hover { background: #fecaca; color: #b91c1c; }
</style>

<div class="card" style="border-top: 5px solid #0f172a; padding: 18px 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; width: 100%;">
        <div style="display: flex; gap: 10px; align-items: center;">
            <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase;">📅 DATA ABSENSI TANGGAL:</label>
            <form method="GET" action="" style="margin: 0;">
                <input type="date" name="tgl" value="<?= $tgl_filter ?>" onchange="this.form.submit()" style="width: auto; border-color: #0ea5e9; color: #0284c7; background: #f0f9ff !important;">
            </form>
        </div>

        <?php if ($user_role != 'Viewer'): ?>
        <form method="POST" action="" style="margin: 0;">
            <input type="hidden" name="tgl_gen" value="<?= $tgl_filter ?>">
            <button type="submit" name="generate_massal" class="btn-submit-modern" style="background: #eab308 !important; color: #78350f !important; box-shadow: 0 4px 6px -1px rgba(234, 179, 8, 0.2) !important; font-size: 13px;" onclick="return confirm('🏖️ EKSEKUSI DATA LIBUR MASSAL?\n\nSistem akan otomatis mendaftarkan SEMUA karyawan aktif hari ini sebagai LIBUR.\n\nLanjutkan?')">
                🏖️ Auto-Generate Semua Karyawan LIBUR
            </button>
        </form>
        <?php endif; ?>
        

    </div>
</div>

<?= $pesan ?>

<?php if ($user_role != 'Viewer'): ?>
<div class="card" id="cardInputArea" style="border-top: 5px solid #10b981; transition: 0.3s;">
    <h2 id="formTitle" style="color: #047857; border-bottom: 2px solid #a7f3d0; padding-bottom: 12px; margin-top: 0; font-size: 17px;">
        ⏱️ Form Input Pengecualian Absensi Karyawan
    </h2>
    
    <form action="" method="POST" autocomplete="off">
        <input type="hidden" name="id_edit" id="id_edit" value="">
        
        <div class="form-grid" style="grid-template-columns: 1fr 2fr 1fr;">
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

            <div class="form-group">
                <label style="color: #0f172a;">Keterangan Kehadiran</label>
                <select name="keterangan" id="keterangan" onchange="cekKeterangan()" required style="font-weight: 800;">
                    <option value="HADIR">✔️ HADIR (Kerja Normal)</option>
                    <option value="ON">🔛 ON (Kerja Shift)</option>
                    <option value="OFF">📴 OFF (Libur Shift)</option>
                    <option value="LIBUR">🏖️ LIBUR (Hari Libur/Minggu)</option>
                    <option value="CUTI">🍇 CUTI TAHUNAN</option>
                    <option value="SAKIT">🏥 SAKIT SURAT DOKTER</option>
                    <option value="IZIN">📝 IZIN KEPENTINGAN</option>
                    <option value="ALPHA">❌ ALPHA (MANGKIR)</option>
                </select>
            </div>
        </div>

        <div class="form-grid" style="grid-template-columns: 2fr 1fr 1fr;">
            <div class="form-group">
                <label style="color: #1d4ed8;">Jadwal Shift Pekerja Hari Ini</label>
                <select name="jadwal_shift" id="jadwal_shift" required style="border-color: #93c5fd; background: #eff6ff; color: #1e3a8a;">
                    <?php foreach($master_shift_list as $ms): 
                        $jm = date('H:i', strtotime($ms['jam_masuk']));
                        $jk = date('H:i', strtotime($ms['jam_keluar']));
                        $val = htmlspecialchars($ms['nama_shift']) . "|$jm|$jk";
                        $label = htmlspecialchars($ms['nama_shift']) . " ($jm - $jk)";
                        
                        $selected = '';
                        if ($is_edit && strpos($jadwal_shift_edit, $ms['nama_shift']) !== false) {
                            $selected = 'selected';
                        }
                    ?>
                        <option value="<?= $val ?>" <?= $selected ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Jam Masuk (Format 24 Jam)</label>
                <input type="text" name="jam_masuk" id="jam_masuk" class="input-jam" value="<?= $jam_masuk ?>" placeholder="07:00" maxlength="5" oninput="formatJam(this)">
            </div>
            <div class="form-group">
                <label>Jam Keluar (Format 24 Jam)</label>
                <input type="text" name="jam_keluar" id="jam_keluar" class="input-jam" value="<?= $jam_keluar ?>" placeholder="16:00" maxlength="5" oninput="formatJam(this)">
            </div>
        </div>

        <div class="btn-group" style="margin-top: 15px;">
            <button type="button" id="btnBatalEdit" class="btn-batal-modern" style="display: <?= $is_edit ? 'inline-flex' : 'none' ?>;" onclick="window.location.href='karyawan_absen.php?tgl=<?= $tgl_filter ?>'">Batal Edit</button>
            <button type="submit" name="simpan_absen" id="btnSubmitForm" class="btn-submit-modern">💾 Simpan Data Absensi</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="radar-box">
    <div class="radar-title">
        <span>🚨 RADAR MONITOR: Karyawan Belum Melakukan Absen Hari Ini</span>
        <span style="background:#be123c; color:white; padding:2px 8px; border-radius:4px; font-size:12px;"><?= $jumlah_belum_absen ?> Jiwa</span>
    </div>
    <div style="max-height: 150px; overflow-y: auto; padding-right:5px;">
        <?php if($jumlah_belum_absen > 0): foreach($karyawan_belum_absen as $kb): ?>
            <div class="radar-tag" onclick="pilihKaryawanOtomatis('<?= htmlspecialchars(addslashes($kb['nama'])) ?>')" title="Klik untuk langsung input!">
                👤 <?= htmlspecialchars($kb['nama']) ?>
            </div>
        <?php endforeach; else: ?>
            <div style="font-size:13px; color:#059669; font-weight:bold; text-align:center; padding:10px 0;">🎉 Sempurna! Seluruh karyawan aktif sudah terdata absensinya hari ini.</div>
        <?php endif; ?>
    </div>
</div>

<div class="shift-settings">
    <div class="shift-header" onclick="document.getElementById('laciShift').classList.toggle('open')">
        <span>⚙️ PENGATURAN ATURAN JAM SHIFT MASUK (KHUSUS TANGGAL INI)</span>
        <span>▼ KLIK UNTUK BUKA/TUTUP</span>
    </div>
    <div class="shift-content" id="laciShift">
        <form method="POST" action="">
            <table class="shift-table">
                <thead>
                    <tr>
                        <th>Nama Kelompok Shift</th>
                        <th>Jam Masuk (Target)</th>
                        <th>Jam Selesai (Target)</th>
                        <th data-noexport="true">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($master_shift_list as $ms): ?>
                    <tr>
                        <td>
                            <input type="hidden" name="id_shift[]" value="<?= $ms['id'] ?>">
                            <input type="text" name="nama_shift[]" value="<?= htmlspecialchars($ms['nama_shift']) ?>" required>
                        </td>
                        <td><input type="text" name="jam_masuk_shift[]" value="<?= date('H:i', strtotime($ms['jam_masuk'])) ?>" required style="text-align:center; font-family:monospace;"></td>
                        <td><input type="text" name="jam_keluar_shift[]" value="<?= date('H:i', strtotime($ms['jam_keluar'])) ?>" required style="text-align:center; font-family:monospace;"></td>
                        <td data-noexport="true">
                            <button type="submit" name="hapus_shift" value="1" class="btn-hapus" style="padding:4px 8px; margin:0;" onclick="document.getElementById('id_shift_hapus').value='<?= $ms['id'] ?>'; return confirm('Hapus shift ini?')">Hapus</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="display:flex; justify-content:space-between; margin-top:15px; flex-wrap:wrap; gap:10px;">
                <button type="submit" name="tambah_shift_baru" class="btn-batal-modern" style="padding:8px 16px; font-size:12px;">➕ Tambah Baris Shift Baru</button>
                <button type="submit" name="update_master_shift" class="btn-submit-modern" style="padding:8px 24px; font-size:12px; background:#d97706 !important; box-shadow:none !important;">💾 Simpan Aturan Jam Kerja</button>
            </div>
        </form>
        <form id="formHapusShift" method="POST" style="display:none;">
            <input type="hidden" name="hapus_shift" value="1">
            <input type="hidden" name="hapus_shift_id" id="id_shift_hapus">
        </form>
    </div>
</div>

<div class="card" style="padding: 20px 25px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px;">
        <div>
            <h2 style="margin: 0; border: none; padding: 0; color: #0f172a;">📋 DAFTAR HADIR PERSONALIA</h2>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-top: 4px;">Log Absensi Tanggal: <strong style="color:#0ea5e9; font-size: 15px;"><?= date('d F Y', strtotime($tgl_filter)) ?></strong></div>
        </div>
        
        <div style="display:flex; gap:10px;">
            <button type="button" onclick="exportKeExcel()" class="btn-search-modern" style="background: #16a34a !important;">📥 Export Excel</button>
        </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
        <input type="text" id="cariAbsen" class="search-box-modern" placeholder="🔍 Cari nama karyawan..." onkeyup="filterAbsen()">
        
        <div class="live-counter-box">
            <div class="live-counter-item" style="color: #334155;">Total Data: <span id="cnt_all">0</span></div>
            <div class="live-counter-item" style="color: #059669; border-left: 2px solid #cbd5e1; padding-left: 10px;">Hadir/ON: <span id="cnt_hadir">0</span></div>
            <div class="live-counter-item" style="color: #475569; border-left: 2px solid #cbd5e1; padding-left: 10px;">OFF/Libur: <span id="cnt_off">0</span></div>
            <div class="live-counter-item" style="color: #d97706; border-left: 2px solid #cbd5e1; padding-left: 10px;">Sakit/Izin/Cuti: <span id="cnt_absen">0</span></div>
            <div class="live-counter-item" style="color: #dc2626; border-left: 2px solid #cbd5e1; padding-left: 10px;">Alpha/Telat: <span id="cnt_bad">0</span></div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-premium" id="tabelAbsenData">
            <thead>
                <tr>
                    <th class="stk-1">No</th>
                    <th class="stk-2">TGL</th>
                    <th class="stk-3 text-left">NAMA KARYAWAN</th>
                    <th class="text-center">Jadwal Shift</th>
                    <th class="text-center">Jam Masuk</th>
                    <th class="text-center">Jam Keluar</th>
                    <th class="text-center">Status Masuk</th>
                    <th class="text-center">Keterangan</th>
                    <th class="text-center" style="width:120px;" data-noexport="true">Aksi HRD</th>
                </tr>
            </thead>
            <tbody id="tabelAbsensi">
                <?php 
                $no = 1;
                $stmt_list = $pdo->prepare("SELECT * FROM data_absensi WHERE tanggal = ? ORDER BY nama_karyawan ASC");
                $stmt_list->execute([$tgl_filter]);
                $absen_hari_ini = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

                if (count($absen_hari_ini) > 0):
                    foreach ($absen_hari_ini as $row):
                        $ket = strtoupper($row['keterangan']);
                        $st_masuk = strtoupper($row['status_masuk']);
                        $jd_shift = $row['jadwal_shift'] ?? '-';
                        
                        $row_class = "row-hadir";
                        if ($ket == 'LIBUR') { $row_class = "row-libur"; }
                        elseif (in_array($ket, ['SAKIT', 'IZIN'])) { $row_class = "row-warning"; }
                        elseif ($ket == 'ALPHA') { $row_class = "row-alpha"; }
                        elseif ($st_masuk == 'TERLAMBAT') { $row_class = "row-telat"; }
                        elseif ($ket == 'OFF') { $row_class = "row-off"; }
                        elseif ($ket == 'CUTI') { $row_class = "row-cuti"; }
                        
                        $st_color = "#16a34a";
                        if ($st_masuk == 'TERLAMBAT') $st_color = "#dc2626";
                        if ($st_masuk == 'TIDAK MASUK') $st_color = "#64748b";
                        if ($st_masuk == 'BELUM ABSEN') $st_color = "#be123c";

                        if ($st_masuk == 'LEBIH AWAL') { $st_bg = '#dbeafe'; $st_co = '#1d4ed8'; }
                        elseif ($st_masuk == 'TEPAT WAKTU') { $st_bg = '#dcfce7'; $st_co = '#15803d'; }
                        elseif ($st_masuk == 'TERLAMBAT') { $st_bg = '#fee2e2'; $st_co = '#b91c1c'; }
                        else { $st_bg = '#f1f5f9'; $st_co = '#475569'; }

                        if ($ket == 'HADIR') { $ket_bg = '#ffffff'; $ket_co = '#0f172a'; $ket_border = '#cbd5e1';}
                        elseif ($ket == 'ON') { $ket_bg = '#e0f2fe'; $ket_co = '#0369a1'; $ket_border = '#bae6fd';}
                        elseif ($ket == 'OFF') { $ket_bg = '#f1f5f9'; $ket_co = '#475569'; $ket_border = '#cbd5e1';}
                        elseif ($ket == 'LIBUR') { $ket_bg = '#f8fafc'; $ket_co = '#64748b'; $ket_border = '#e2e8f0';}
                        elseif ($ket == 'CUTI') { $ket_bg = '#fdf4ff'; $ket_co = '#a21caf'; $ket_border = '#f5d0fe';}
                        elseif ($ket == 'ALPHA') { $ket_bg = '#fef2f2'; $ket_co = '#b91c1c'; $ket_border = '#fecaca';}
                        else { $ket_bg = '#fffbeb'; $ket_co = '#b45309'; $ket_border = '#fde68a';}
                ?>
                <tr class="<?= $row_class ?> baris-data" id="baris-<?= $row['id'] ?>" data-status="<?= htmlspecialchars($st_masuk) ?>" data-ket="<?= htmlspecialchars($ket) ?>">
                    <td class="stk-1" style="color:#64748b; font-weight:bold;"><?= $no++ ?></td>
                    <td class="stk-2" style="font-weight:700; color:#475569; font-size:12px;"><?= date('d/m/y', strtotime($row['tanggal'])) ?></td>
                    <td class="stk-3 text-left" style="font-weight:800; color:#0f172a; font-size:14px;"><?= htmlspecialchars($row['nama_karyawan']) ?></td>
                    <td class="text-center" style="font-weight:700; color:#1d4ed8; font-size:12px;"><?= htmlspecialchars($jd_shift) ?></td>
                    <td class="text-center" style="font-family:monospace; font-size:14px; font-weight:800; color:#0284c7;"><?= $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : '-' ?></td>
                    <td class="text-center" style="font-family:monospace; font-size:14px; font-weight:800; color:#16a34a;"><?= $row['jam_keluar'] ? date('H:i', strtotime($row['jam_keluar'])) : '-' ?></td>
                    <td class="text-center"><span class="badge" style="background:<?= $st_bg ?>; color:<?= $st_co ?>; font-size:11px; padding: 6px 10px; border: 1px solid <?= str_replace('bg', 'border', $st_bg)?>;"><?= $st_masuk ?></span></td>
                    <td class="text-center"><span class="badge" style="background:<?= $ket_bg ?>; color:<?= $ket_co ?>; border: 1px solid <?= $ket_border ?>; font-size:11px; padding: 6px 10px;"><?= $ket ?></span></td>
                    <td class="action-links" data-noexport="true" style="text-align:center;">
                        <?php if ($user_role != 'Viewer'): ?>
                            <a href="javascript:void(0);" onclick="editAbsenFast(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_karyawan'])) ?>', '<?= htmlspecialchars(addslashes(explode(' (', $jd_shift)[0] ?? '')) ?>', '<?= $row['jam_masuk'] ? date('H:i', strtotime($row['jam_masuk'])) : '' ?>', '<?= $row['jam_keluar'] ? date('H:i', strtotime($row['jam_keluar'])) : '' ?>', '<?= htmlspecialchars(addslashes($row['keterangan'])) ?>')" class="btn-edit" style="color: #0ea5e9; font-weight:700; font-size:11px; padding:4px 8px; border-radius:4px; border:1px solid #bae6fd; background:#e0f2fe; text-decoration:none;">Edit</a>
                            <a href="javascript:void(0);" onclick="konfirmasiHapusAbsen(<?= $row['id'] ?>)" class="btn-hapus" style="padding:4px 8px; font-size:11px; margin-left:2px;">Hapus</a>
                        <?php else: ?>
                            <span style="font-size:10px; color:#94a3b8;">Terbatas</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="9" class="text-center" style="padding:40px; color:#94a3b8; font-weight:600;">⚠️ Belum ada riwayat absensi yang terdata pada tanggal ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="formHapusAbsen" method="POST" style="display: none;">
    <input type="hidden" name="hapus_id" id="inputHapusAbsenId">
    <input type="hidden" name="hapus_data" value="1">
</form>

<script>
    function pilihKaryawanOtomatis(nama) {
        let selNama = document.getElementById('nama_karyawan');
        if(selNama) { selNama.value = nama; }
        
        document.getElementById('cardInputArea').style.borderColor = '#0ea5e9';
        setTimeout(() => { document.getElementById('cardInputArea').style.borderColor = '#10b981'; }, 1500);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        document.getElementById('jam_masuk').focus();
    }

    function formatJam(obj) {
        let val = obj.value.replace(/[^0-9]/g, '');
        if (val.length >= 3) { val = val.substring(0, 2) + ':' + val.substring(2, 4); }
        obj.value = val;
    }

    function editAbsenFast(id, nama, shift_nama, masuk, keluar, ket) {
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

        let selShift = document.getElementById('jadwal_shift');
        let found = false;
        for (let i = 0; i < selShift.options.length; i++) {
            if (selShift.options[i].value.startsWith(shift_nama + '|')) {
                selShift.selectedIndex = i; found = true; break;
            }
        }
        if(!found) {
            let newOpt = new Option(shift_nama + ' (Shift Lama)', shift_nama + '|00:00|00:00');
            selShift.add(newOpt);
            selShift.value = newOpt.value;
        }

        document.getElementById('keterangan').value = ket;
        document.getElementById('jam_masuk').value = masuk;
        document.getElementById('jam_keluar').value = keluar;
        
        cekKeterangan();

        let btnSubmit = document.getElementById('btnSubmitForm');
        btnSubmit.innerHTML = '💾 Update Data Absen';
        btnSubmit.style.background = '#0ea5e9';
        
        document.getElementById('btnBatalEdit').style.display = 'inline-block';
        
        let title = document.getElementById('formTitle');
        title.innerHTML = '✏️ Mode Edit Absensi';
        title.style.color = '#0284c7';
        
        document.getElementById('cardInputArea').style.borderColor = '#0ea5e9';
        document.getElementById('cardInputArea').style.backgroundColor = '#f0f9ff';
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function cekKeterangan() {
        let ket = document.getElementById('keterangan').value;
        let jMasuk = document.getElementById('jam_masuk');
        let jKeluar = document.getElementById('jam_keluar');

        if(ket === 'HADIR' || ket === 'ON') {
            jMasuk.readOnly = false; jMasuk.style.background = '#ffffff';
            jKeluar.readOnly = false; jKeluar.style.background = '#ffffff';
        } else {
            jMasuk.value = ''; jMasuk.readOnly = true; jMasuk.style.background = '#e2e8f0';
            jKeluar.value = ''; jKeluar.readOnly = true; jKeluar.style.background = '#e2e8f0';
        }
    }
    
    // 🚀 BUG FIX COUNTER: LOGIKA DIJAMIN TIDAK AKAN DOBEL
    function updateCounter() {
        let tr = document.getElementsByClassName("baris-data");
        let visible = 0, hadir = 0, off = 0, absen = 0, bad = 0;
        
        for (let i = 0; i < tr.length; i++) {
            if (tr[i].style.display !== "none") {
                visible++;
                let status = tr[i].getAttribute("data-status");
                let ket = tr[i].getAttribute("data-ket");
                
                // Urutan Mutually Exclusive (Hanya 1 yang bisa bertambah per baris)
                if (ket === 'ALPHA') {
                    bad++;
                } else if ((ket === 'HADIR' || ket === 'ON') && status === 'TERLAMBAT') {
                    bad++;
                } else if (ket === 'HADIR' || ket === 'ON') {
                    hadir++;
                } else if (ket === 'OFF' || ket === 'LIBUR') {
                    off++;
                } else if (ket === 'SAKIT' || ket === 'IZIN' || ket === 'CUTI') {
                    absen++;
                }
            }
        }
        
        document.getElementById('cnt_all').innerText = visible;
        document.getElementById('cnt_hadir').innerText = hadir;
        document.getElementById('cnt_off').innerText = off;
        document.getElementById('cnt_absen').innerText = absen;
        document.getElementById('cnt_bad').innerText = bad;
    }

    function filterAbsen() {
        let input = document.getElementById("cariAbsen").value.toLowerCase();
        let tr = document.getElementsByClassName("baris-data");
        for (let i = 0; i < tr.length; i++) {
            let textKandungan = tr[i].innerText.toLowerCase();
            tr[i].style.display = textKandungan.includes(input) ? "" : "none";
        }
        updateCounter();
    }

    function konfirmasiHapusAbsen(id) {
        if (confirm('Hapus permanen data absensi ini?')) {
            document.getElementById('inputHapusAbsenId').value = id;
            document.getElementById('formHapusAbsen').submit();
        }
    }

    function exportKeExcel() {
        let table = document.getElementById("tabelAbsenData");
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
                    th { background-color: #0f172a; color: #ffffff; font-weight: bold; }
                </style>
            </head>
            <body>
                <h2 style="text-align: center; margin-bottom: 15px;">Laporan Absensi Karyawan H2 BASE</h2>
                <p><strong>Tanggal:</strong> ${tglExport}</p>
                ${cloneTable.outerHTML}
            </body>
            </html>
        `;

        let blob = new Blob([htmlTemplate], { type: 'application/vnd.ms-excel' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'Data_Absen_' + tglExport + '.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    window.onload = function() { 
        cekKeterangan(); 
        updateCounter();
    };
</script>
<?php require_once 'footer.php'; ?>

