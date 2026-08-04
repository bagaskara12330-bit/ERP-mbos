<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require 'koneksi.php';

// 🚀 WAJIB LOGIN
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

// 🚀 GEMBOK MUTLAK: Hanya Role Admin yang boleh menginjakkan kaki di sini!
$user_role = $_SESSION['role'] ?? 'Viewer';

if ($user_role != 'Admin') {
    echo "<script>alert('🛑 AKSES DITOLAK! Hanya Admin Level Tertinggi yang diizinkan mengatur Akun & Hak Akses Sistem.'); window.location.href='index.php';</script>";
    exit();
}

$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false;

// Variabel default form agar tidak error "undefined variable"
$id_edit = ''; $u_edit = ''; $r_edit = 'Viewer'; $a_edit = [];

if (isset($_GET['edit'])) {
    $is_edit = true; $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM master_akun WHERE id = ?"); $stmt->execute([$id_edit]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $u_edit = $row['username']; $r_edit = $row['role']; 
        $a_edit = explode(',', $row['akses_menu'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_akun'])) {
    $post_id = $_POST['id_edit'];
    $username = strtolower(trim($_POST['username'])); 
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // 🚀 TANGKAP CENTANGAN CHECKBOX HALAMAN SPESIFIK
    $akses_str = isset($_POST['akses']) ? implode(',', $_POST['akses']) : '';

    if (!empty($post_id)) {
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE master_akun SET role=?, password=?, akses_menu=? WHERE id=?");
            $stmt->execute([$role, $hashed_password, $akses_str, $post_id]);
            catatLog($pdo, $user_aktif, "Mengubah hak akses @$username dan mereset passwordnya.", "🔑");
        } else {
            $stmt = $pdo->prepare("UPDATE master_akun SET role=?, akses_menu=? WHERE id=?");
            $stmt->execute([$role, $akses_str, $post_id]);
            catatLog($pdo, $user_aktif, "Mengubah/Update checklist hak akses spesifik untuk @$username.", "🔄");
        }
        header("Location: akun.php?pesan=edit_sukses"); exit();
    } else {
        if (!empty($username) && !empty($password)) {
            $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM master_akun WHERE username = ?"); $stmt_cek->execute([$username]);
            if ($stmt_cek->fetchColumn() > 0) { $pesan = "<div class='alert alert-danger'>❌ Username <strong>@$username</strong> sudah dipakai!</div>"; } 
            else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO master_akun (username, password, role, akses_menu) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $role, $akses_str]);
                catatLog($pdo, $user_aktif, "Mendaftarkan pengguna baru: @$username ($role).", "👤");
                header("Location: akun.php?pesan=tambah_sukses"); exit();
            }
        }
    }
}

// 🚀 LOGIKA AKSI AMAN MENGGUNAKAN POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['aksi_khusus'])) {
    if ($_POST['aksi_khusus'] == 'toggle' && isset($_POST['id']) && isset($_POST['status'])) {
        $id = intval($_POST['id']); $status_baru = $_POST['status'] == 'Aktif' ? 'Aktif' : 'Nonaktif';
        $n_stmt = $pdo->prepare("SELECT username FROM master_akun WHERE id=?"); $n_stmt->execute([$id]); $n_user = $n_stmt->fetchColumn();
        if ($id == $_SESSION['user_id']) { $pesan = "<div class='alert alert-danger'>❌ Ditolak: Tidak bisa blokir diri sendiri!</div>"; } 
        else {
            $pdo->prepare("UPDATE master_akun SET status = ? WHERE id = ?")->execute([$status_baru, $id]);
            catatLog($pdo, $user_aktif, "Memblokir/mengaktifkan kembali akses login akun @$n_user.", "🛑");
            header("Location: akun.php?pesan=toggle_sukses"); exit();
        }
    }
    if ($_POST['aksi_khusus'] == 'hapus' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $n_stmt = $pdo->prepare("SELECT username FROM master_akun WHERE id=?"); $n_stmt->execute([$id]); $n_user = $n_stmt->fetchColumn();
        if ($id == $_SESSION['user_id']) { $pesan = "<div class='alert alert-danger'>❌ Ditolak: Jangan bunuh diri!</div>"; } 
        else {
            $pdo->prepare("DELETE FROM master_akun WHERE id = ?")->execute([$id]);
            catatLog($pdo, $user_aktif, "Membakar akun @$n_user secara permanen.", "🔥");
            header("Location: akun.php?pesan=hapus_sukses"); exit();
        }
    }
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'tambah_sukses') $pesan = "<div class='alert alert-success'>✅ Berhasil: Akun baru telah siap digunakan.</div>";
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success'>✅ Berhasil: Hak akses role & menu diperbarui!</div>";
    if ($_GET['pesan'] == 'toggle_sukses') $pesan = "<div class='alert alert-success'>✅ Status blokir/aktif akun berhasil diperbarui!</div>";
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-success'>🗑️ Akun telah dihapus permanen!</div>";
}

// 🚀 KAMUS TERJEMAHAN KODE AKSES UNTUK DITAMPILKAN DI TABEL
$map_akses = [
    'inv_masuk' => 'Stock Ex Papper', 'inv_keluar' => 'Dashboard Proll', 'inv_stok' => 'Stok Opname', 'inv_wip' => 'Gudang WIP',
    'prod_lap' => 'Input Lap. Harian', 'prod_rek' => 'Rekap Bln', 'prod_kpi' => 'Report KPI', 'prod_flexo' => 'Prod. Flexo', 
    'prod_downtime' => 'Downtime Corrugator', 'prod_slitter' => 'Produksi NC', 
    'qc_lap' => 'Laporan QC', 'qc_inc' => 'QC Incoming', 'qc_dash' => 'Dashboard QC',
    'dash_prod' => 'Dash. Corr', 'dash_nc' => 'Dash. Produksi NC', 'dash_eff' => 'Dashboard Efisiensi', 'dash_hrd' => 'Dashboard Karyawan', 'dash_flexo' => 'Dashboard Flexo',
    'dash_corr' => 'Dashboard Downtime', 'dash_stok' => 'Dashboard Stok Opname',
    'dash_mtc' => 'Dashboard MTC', 'mtc_sparepart' => 'Sparepart & Transaksi', 
    'hrd_data' => 'Data & Absensi', 'hrd_lemb' => 'Log Lembur', 'hrd_interview' => 'Report Interview', 'set_master' => 'Master Regu'
];

$page_title = "H2 BASE — Manajemen Akun"; $active_page = "akun";
require 'header.php';
?>

<style>
    /* 🚀 RE-DESIGN TOTAL DNA MODERN SAAS (WHITE CLEAN FIELD) */
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
    
    .form-grid-top { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
    .form-group { display: flex; flex-direction: column; min-width: 140px; margin-bottom: 16px;}
    .form-group label { margin-bottom: 8px; font-weight: 800; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;}
    
    input[type="text"], input[type="password"], select { 
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
    input[type="text"]:focus, input[type="password"]:focus, select:focus { 
        border-color: #0ea5e9 !important; 
        background: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(14,165,233,0.15) !important; 
        outline: none;
    }

    /* 🚀 PREMIUM BUTTONS */
    .btn-submit-modern {
        background: #10b981 !important; color: #ffffff !important; border: none !important; padding: 12px 32px !important; border-radius: 8px !important; font-size: 14px !important; font-weight: 800 !important; cursor: pointer !important; transition: all 0.2s ease-in-out !important; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.1) !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;
    }
    .btn-submit-modern:hover { background: #059669 !important; transform: translateY(-1px) !important; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2) !important; }

    .btn-batal-modern {
        background: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important; padding: 11px 24px !important; text-decoration: none !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; text-align: center !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; transition: all 0.2s !important;
    }
    .btn-batal-modern:hover { background: #f1f5f9 !important; color: #0f172a !important; }

    /* 🚀 DESIGN CHECKLIST AREA SUPER RAPI */
    .akses-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-top: 12px; }
    .kategori-box { background: #ffffff; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); }
    .kategori-box:hover { border-color: #cbd5e1; transform: translateY(-6px) scale(1.02); box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); cursor: pointer; }
    
    .kategori-box.box-orange { border-top: 4px solid #f59e0b; } /* Inventory */
    .kategori-box.box-green { border-top: 4px solid #10b981; }  /* Produksi */
    .kategori-box.box-pink { border-top: 4px solid #ec4899; }   /* QC */
    .kategori-box.box-blue { border-top: 4px solid #0ea5e9; }   /* Dashboard */
    .kategori-box.box-red { border-top: 4px solid #f43f5e; }    /* MTC */
    .kategori-box.box-purple { border-top: 4px solid #8b5cf6; } /* HRD */
    .kategori-box.box-slate { border-top: 4px solid #64748b; }  /* Pengaturan */

    .kategori-title { font-size: 12px; font-weight: 800; color: #0f172a; margin-bottom: 12px; text-transform: uppercase; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px; letter-spacing: 0.5px;}
    
    .check-label { background: transparent; border: none; padding: 6px 0; border-radius: 0; font-size: 12px; font-weight: 600; color: #475569; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s;}
    .check-label:hover { color: #0ea5e9; }
    .check-label input { width: 16px; height: 16px; cursor: pointer; accent-color: #0ea5e9; }
    
    /* 🚀 TABEL AKUN SHARP TEXT */
    .table-responsive { background: white; border-radius: 10px; border: 1px solid #cbd5e1; overflow-x: auto; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.01);}
    table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #0f172a; white-space: nowrap; min-width: 1000px; }
    th, td { padding: 14px 16px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; vertical-align: middle; font-weight: 600; }
    th { background-color: #0f172a; color: #ffffff; font-weight: 700; position: sticky; top: 0; z-index: 10; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 2px solid #1e293b; text-align: center;}
    
    tbody tr:nth-child(even) td { background-color: #f8fafc; }
    tbody tr:hover td { background-color: #f1f5f9 !important; }
    
    .badge-role { padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 11px; display: inline-block; text-align: center; border: 1px solid transparent;}
    .badge-akses { background:#ffffff; padding:4px 8px; margin:2px 2px; border-radius:6px; border:1px solid #cbd5e1; display:inline-block; font-size:10px; color:#475569; font-weight:700; box-shadow: 0 1px 2px rgba(0,0,0,0.02);}

    .btn-edit { display: inline-block; background: #e0f2fe; color: #0284c7; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #bae6fd; cursor: pointer;}
    .btn-edit:hover { background: #bae6fd; color: #0369a1; }
    .btn-hapus { display: inline-block; background: #fee2e2; color: #dc2626; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; transition: 0.2s; border: 1px solid #fecaca; margin-left: 4px; cursor: pointer;}
    .btn-hapus:hover { background: #fca5a5; color: #b91c1c; }
</style>

<?= $pesan ?>

<div class="card" <?= $is_edit ? 'style="border-top: 5px solid #0ea5e9; background: #f0f9ff;"' : 'style="border-top: 5px solid #10b981;"' ?>>
    <h2 style="color: <?= $is_edit ? '#0284c7' : '#047857' ?>; border-bottom: 2px solid <?= $is_edit ? '#bae6fd' : '#a7f3d0' ?>; padding-bottom: 12px; margin-top: 0; font-size: 16px;">
        <?= $is_edit ? '✏️ Edit Akses Akun Spesifik' : '➕ Tambah Akun Baru' ?>
    </h2>
    <form action="" method="POST" autocomplete="off">
        <input type="hidden" name="id_edit" value="<?= $id_edit ?>">
        
        <div class="form-grid-top">
            <div class="form-group">
                <label>Username (ID Login)</label>
                <input type="text" name="username" value="<?= htmlspecialchars($u_edit) ?>" placeholder="Tanpa spasi..." <?= $is_edit ? 'readonly style="background:#e2e8f0 !important; color:#94a3b8 !important; cursor:not-allowed;"' : 'required pattern="[a-zA-Z0-9_]+"' ?>>
            </div>
            
            <div class="form-group">
                <label>Password <?= $is_edit ? '<span style="color:#ef4444; font-size:10px; font-weight:bold;">(Kosongkan jika tak ingin ganti)</span>' : '' ?></label>
                <input type="password" name="password" placeholder="<?= $is_edit ? 'Ketik password baru jika mereset...' : 'Minimal 6 karakter...' ?>" <?= $is_edit ? '' : 'required' ?> minlength="6">
            </div>
            
            <div class="form-group">
                <label>Kasta Pekerja (Role Dasar)</label>
                <select name="role" id="roleSelect" onchange="syncAksesOtomatis(true)" required style="border-color: #0ea5e9; color: #0369a1;">
                    <option value="Admin" <?= $r_edit == 'Admin' ? 'selected' : '' ?>>👑 ADMIN (Akses Penuh Mutlak)</option>
                    <option value="Editor" <?= ($r_edit == 'Editor' || $r_edit == 'Operator') ? 'selected' : '' ?>>✍️ EDITOR (Input/Edit Data)</option>
                    <option value="Viewer" <?= $r_edit == 'Viewer' ? 'selected' : '' ?>>👁️ VIEWER (Hanya Membaca)</option>
                </select>
            </div>
        </div>

        <div style="margin-top:10px; border-top: 2px dashed #e2e8f0; padding-top: 20px;">
            <label style="color:#0f172a; font-size:14px; font-weight: 800; display:block; margin-bottom: 4px;">✅ Distribusi Hak Akses Halaman (Sesuai Sidebar)</label>
            <p style="font-size:12px; color:#64748b; font-weight:600; margin-top:0;">Centang laci menu apa saja yang boleh dibuka oleh akun ini.</p>
            
            <div class="akses-grid">
                
                <div class="kategori-box box-orange">
                    <div class="kategori-title">📦 INVENTORY & GUDANG</div>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="inv_masuk" <?= in_array('inv_masuk', $a_edit) ? 'checked' : '' ?>> Stock Ex Papper</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="inv_stok" <?= in_array('inv_stok', $a_edit) ? 'checked' : '' ?>> Stok Opname</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="inv_wip" <?= in_array('inv_wip', $a_edit) ? 'checked' : '' ?>> Gudang WIP (Sheet)</label>
                </div>

                <div class="kategori-box box-green">
                    <div class="kategori-title">⚙️ PRODUKSI & PABRIK</div>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="prod_lap" <?= in_array('prod_lap', $a_edit) ? 'checked' : '' ?>> Laporan Harian</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="prod_rek" <?= in_array('prod_rek', $a_edit) ? 'checked' : '' ?>> Rekap Bulanan</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="prod_kpi" <?= in_array('prod_kpi', $a_edit) ? 'checked' : '' ?>> Report KPI</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="prod_flexo" <?= in_array('prod_flexo', $a_edit) ? 'checked' : '' ?>> Prod. Flexo</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="prod_downtime" <?= in_array('prod_downtime', $a_edit) ? 'checked' : '' ?>> Down. Corrugator</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="prod_slitter" <?= in_array('prod_slitter', $a_edit) ? 'checked' : '' ?>> Produksi NC</label>
                </div>

                <div class="kategori-box box-pink">
                    <div class="kategori-title">🔎 QUALITY CONTROL</div>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="qc_inc" <?= in_array('qc_inc', $a_edit) ? 'checked' : '' ?>> QC Incoming</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="qc_lap" <?= in_array('qc_lap', $a_edit) ? 'checked' : '' ?>> Laporan QC</label>
                </div>

                <div class="kategori-box box-blue">
                    <div class="kategori-title">📊 EXECUTIVE DASHBOARD</div>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="qc_dash" <?= in_array('qc_dash', $a_edit) ? 'checked' : '' ?>> Dashboard QC</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="dash_prod" <?= in_array('dash_prod', $a_edit) ? 'checked' : '' ?>> Dash. Corr</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="dash_nc" <?= in_array('dash_nc', $a_edit) ? 'checked' : '' ?>> Dash. Produksi NC</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="dash_eff" <?= in_array('dash_eff', $a_edit) ? 'checked' : '' ?>> Dashboard Efisiensi</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="dash_hrd" <?= in_array('dash_hrd', $a_edit) ? 'checked' : '' ?>> Dashboard Karyawan</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="dash_flexo" <?= in_array('dash_flexo', $a_edit) ? 'checked' : '' ?>> Dashboard Flexo</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="dash_corr" <?= in_array('dash_corr', $a_edit) ? 'checked' : '' ?>> Dashboard Downtime</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="dash_stok" <?= in_array('dash_stok', $a_edit) ? 'checked' : '' ?>> Dashboard Stok</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="inv_keluar" <?= in_array('inv_keluar', $a_edit) ? 'checked' : '' ?>> Dashboard Proll</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="dash_mtc" <?= in_array('dash_mtc', $a_edit) ? 'checked' : '' ?>> Dashboard MTC</label>
                </div>

                <div class="kategori-box box-red">
                    <div class="kategori-title">🛠️ MTC & UTILITY</div>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="mtc_sparepart" <?= in_array('mtc_sparepart', $a_edit) ? 'checked' : '' ?>> Sparepart & Transaksi</label>
                </div>

                <div class="kategori-box box-purple">
                    <div class="kategori-title">👥 HRD & PERSONALIA</div>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="hrd_data" <?= in_array('hrd_data', $a_edit) ? 'checked' : '' ?>> Data & Absensi</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="hrd_lemb" <?= in_array('hrd_lemb', $a_edit) ? 'checked' : '' ?>> Log Lembur</label>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="hrd_interview" <?= in_array('hrd_interview', $a_edit) ? 'checked' : '' ?>> Report Interview</label>
                </div>

                <div class="kategori-box box-slate">
                    <div class="kategori-title">🔐 PENGATURAN</div>
                    <label class="check-label"><input type="checkbox" name="akses[]" value="set_master" <?= in_array('set_master', $a_edit) ? 'checked' : '' ?>> Master Data Regu</label>
                </div>

            </div>
        </div>
        
        <div style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 15px;">
            <?php if($is_edit): ?><a href="akun.php" class="btn-batal-modern">Batal Edit Akses</a><?php endif; ?>
            <button type="submit" name="simpan_akun" class="btn-submit-modern" style="<?= $is_edit ? 'background:#0ea5e9 !important;' : '' ?>">
                <?= $is_edit ? '💾 Update Akses Akun' : '💾 Buat Akun Login' ?>
            </button>
        </div>
    </form>
</div>

<div class="card" style="border-top: 5px solid #0f172a;">
    <h2 style="color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; margin-top: 0; font-size: 16px;">
        👥 Daftar Pengguna Sistem & Kasta Role
    </h2>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 50px;">NO</th>
                    <th class="text-left" style="width: 150px;">USERNAME</th>
                    <th class="text-center" style="width: 120px;">ROLE</th>
                    <th class="text-left" style="min-width: 350px;">HAK AKSES HALAMAN (GRANULAR)</th> 
                    <th class="text-center" style="width: 100px;">STATUS</th>
                    <th class="text-center" style="width: 200px;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                $akun_list = $pdo->query("SELECT * FROM master_akun ORDER BY FIELD(role, 'Admin', 'Editor', 'Viewer', 'Operator') ASC, username ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($akun_list as $row): 
                    if ($row['role'] == 'Admin') { $role_bg = '#fef08a'; $role_co = '#854d0e'; $role_bd = '#fde047'; }
                    elseif ($row['role'] == 'Viewer') { $role_bg = '#f1f5f9'; $role_co = '#475569'; $role_bd = '#cbd5e1';}
                    else { $role_bg = '#e0f2fe'; $role_co = '#0284c7'; $role_bd = '#bae6fd';} 
                    
                    $status_bg = $row['status'] == 'Aktif' ? '#dcfce7' : '#fee2e2';
                    $status_co = $row['status'] == 'Aktif' ? '#166534' : '#991b1b';
                    $status_bd = $row['status'] == 'Aktif' ? '#bbf7d0' : '#fecaca';
                ?>
                <tr>
                    <td class="text-center" style="color:#64748b; font-weight:800;"><?= $no++ ?></td>
                    <td style="text-align:left;">
                        <strong style="color:#0f172a; font-size:15px; display:block;">@<?= htmlspecialchars($row['username']) ?></strong>
                        <?php if($row['id'] == $_SESSION['user_id']) echo "<span style='color:#0ea5e9; font-size:11px; font-weight:bold;'>(Ini Anda)</span>"; ?>
                    </td>
                    <td class="text-center"><span class="badge-role" style="background:<?= $role_bg ?>; color:<?= $role_co ?>; border-color:<?= $role_bd ?>;"><?= strtoupper($row['role']) ?></span></td>
                    
                    <td class="text-left" style="white-space: normal; line-height: 1.8;">
                        <?php
                        if ($row['role'] == 'Admin') {
                            echo '<span style="color:#16a34a; font-weight:900; font-size:12px;">👑 TIDAK TERBATAS (All Access)</span>';
                        } else {
                            $arr_acc = explode(',', $row['akses_menu'] ?? '');
                            if (empty($arr_acc) || $row['akses_menu'] == '') {
                                echo '<span style="color:#ef4444; font-weight:800; font-size:12px;">🚫 Tidak ada akses sama sekali</span>';
                            } else {
                                foreach ($arr_acc as $ak) {
                                    if (isset($map_akses[$ak])) {
                                        echo '<span class="badge-akses">'. $map_akses[$ak] .'</span>';
                                    }
                                }
                            }
                        }
                        ?>
                    </td>

                    <td class="text-center"><span class="badge-role" style="background:<?= $status_bg ?>; color:<?= $status_co ?>; border-color:<?= $status_bd ?>;"><?= strtoupper($row['status']) ?></span></td>
                    <td class="text-center" style="white-space: nowrap;">
                        <div style="display: flex; gap: 6px; justify-content: center; margin-bottom: 6px;">
                            <a href="akun.php?edit=<?= $row['id'] ?>" class="btn-edit" style="flex:1;">Edit Akses</a> 
                            <?php if ($row['status'] == 'Aktif'): ?>
                                <a href="javascript:void(0);" style="flex:1; color:#d97706; background:#fef3c7; border:1px solid #fde68a; padding:6px 12px; border-radius:6px; font-size:11px; font-weight:800; text-decoration:none;" onclick="konfirmasiAksi('toggle', <?= $row['id'] ?>, 'Nonaktif', 'Blokir akses login user ini?')">Blokir</a>
                            <?php else: ?>
                                <a href="javascript:void(0);" style="flex:1; color:#16a34a; background:#dcfce7; border:1px solid #bbf7d0; padding:6px 12px; border-radius:6px; font-size:11px; font-weight:800; text-decoration:none;" onclick="konfirmasiAksi('toggle', <?= $row['id'] ?>, 'Aktif', 'Aktifkan kembali user ini?')">Aktifkan</a>
                            <?php endif; ?>
                        </div>
                        <a href="javascript:void(0);" class="btn-hapus" style="display: block; margin:0;" onclick="konfirmasiAksi('hapus', <?= $row['id'] ?>, '', 'Yakin ingin membakar akun ini secara permanen?')">Hapus Permanen</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<form id="formAksiGlobal" method="POST" style="display: none;">
    <input type="hidden" name="aksi_khusus" id="inputAksi">
    <input type="hidden" name="id" id="inputId">
    <input type="hidden" name="status" id="inputStatus">
</form>

<script>
    // 🚀 LOGIKA AUTO-CENTANG YANG SUPER AKURAT (SINKRONISASI 100%)
    function syncAksesOtomatis(isUserAction = false) {
        let role = document.getElementById('roleSelect').value;
        let cbs = document.querySelectorAll('.check-label input[type="checkbox"]');
        
        // JANGAN KOSONGKAN/TIMPA CHECKBOX JIKA LAGI MODE EDIT! (Kecuali user sengaja ganti dropdown Role)
        if (!isUserAction && <?= $is_edit ? 'true' : 'false' ?>) {
            return; 
        }

        cbs.forEach(cb => cb.checked = false); 

        if (role === 'Admin') {
            cbs.forEach(cb => cb.checked = true);
        } 
        else if (role === 'Editor' || role === 'Operator') {
            let editorAkses = ['inv_masuk', 'inv_stok', 'inv_wip', 'prod_lap', 'prod_rek', 'prod_kpi', 'prod_flexo', 'prod_downtime', 'prod_slitter', 'qc_lap', 'qc_inc', 'qc_dash', 'mtc_sparepart', 'hrd_data', 'hrd_lemb', 'hrd_interview'];
            cbs.forEach(cb => { if(editorAkses.includes(cb.value)) cb.checked = true; });
        } 
        else if (role === 'Viewer') {
            let viewerAkses = ['dash_prod', 'dash_nc', 'dash_eff', 'dash_hrd', 'dash_flexo', 'dash_corr', 'dash_stok', 'inv_keluar', 'dash_mtc'];
            cbs.forEach(cb => { if(viewerAkses.includes(cb.value)) cb.checked = true; });
        }
    }

    // Eksekusi hanya saat pertama kali halaman diload (untuk Akun Baru)
    window.addEventListener('DOMContentLoaded', (event) => {
        <?php if(!$is_edit): ?> syncAksesOtomatis(true); <?php endif; ?>
    });

    function konfirmasiAksi(aksi, id, status, pesan) {
        if (confirm(pesan)) {
            document.getElementById('inputAksi').value = aksi;
            document.getElementById('inputId').value = id;
            document.getElementById('inputStatus').value = status;
            document.getElementById('formAksiGlobal').submit();
        }
    }
</script>
<?php require_once 'footer.php'; ?>
