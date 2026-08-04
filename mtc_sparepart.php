<?php
require_once 'auth.php';
require_akses('mtc_sparepart');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = "";

// 🚀 AUTO-CREATE TABEL MASTER SPAREPART & KARTU STOK (HISTORI)
try {
    // Tabel 1: Master Fisik Sparepart
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_mtc_sparepart (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode_part VARCHAR(50),
        nama_part VARCHAR(150),
        kategori VARCHAR(50),
        satuan VARCHAR(20) DEFAULT 'PCS',
        qty_stok DECIMAL(10,2) DEFAULT 0,
        limit_stok DECIMAL(10,2) DEFAULT 5,
        rak_lokasi VARCHAR(50),
        last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Tabel 2: Kartu Stok (Histori Keluar Masuk)
    $pdo->exec("CREATE TABLE IF NOT EXISTS db_mtc_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE,
        id_part INT,
        jenis ENUM('MASUK', 'KELUAR') DEFAULT 'KELUAR',
        qty DECIMAL(10,2),
        saldo_akhir DECIMAL(10,2),
        teknisi VARCHAR(100),
        mesin VARCHAR(100),
        keterangan VARCHAR(200),
        operator_sistem VARCHAR(100),
        waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// 1️⃣ LOGIKA: TAMBAH MASTER SPAREPART BARU
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_master'])) {
    $kode = strtoupper(trim($_POST['kode_part']));
    $nama = strtoupper(trim($_POST['nama_part']));
    $kat = strtoupper($_POST['kategori']);
    $satuan = strtoupper($_POST['satuan']);
    $limit = floatval($_POST['limit_stok']);
    $rak = strtoupper(trim($_POST['rak_lokasi']));

    $sql = "INSERT INTO db_mtc_sparepart (kode_part, nama_part, kategori, satuan, limit_stok, rak_lokasi) VALUES (?, ?, ?, ?, ?, ?)";
    $pdo->prepare($sql)->execute([$kode, $nama, $kat, $satuan, $limit, $rak]);
    header("Location: mtc_sparepart.php?pesan=master_sukses"); exit();
}

// 2️⃣ LOGIKA: TRANSAKSI IN / OUT (KARTU STOK)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_transaksi'])) {
    $tgl = $_POST['tanggal'];
    $jenis = $_POST['jenis']; // MASUK atau KELUAR
    $id_part = intval($_POST['id_part']);
    $qty_transaksi = floatval($_POST['qty_transaksi']);
    $teknisi = strtoupper(trim($_POST['teknisi']));
    $mesin = strtoupper(trim($_POST['mesin']));
    $keterangan = trim($_POST['keterangan']);

    // Cek stok master saat ini
    $stmt = $pdo->prepare("SELECT qty_stok, nama_part FROM db_mtc_sparepart WHERE id = ?");
    $stmt->execute([$id_part]);
    $part = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($part) {
        $stok_lama = floatval($part['qty_stok']);
        $stok_baru = ($jenis == 'MASUK') ? ($stok_lama + $qty_transaksi) : ($stok_lama - $qty_transaksi);

        // Update Stok di Master
        $pdo->prepare("UPDATE db_mtc_sparepart SET qty_stok = ? WHERE id = ?")->execute([$stok_baru, $id_part]);

        // Simpan ke Buku Besar / Kartu Stok
        $sql_hist = "INSERT INTO db_mtc_history (tanggal, id_part, jenis, qty, saldo_akhir, teknisi, mesin, keterangan, operator_sistem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql_hist)->execute([$tgl, $id_part, $jenis, $qty_transaksi, $stok_baru, $teknisi, $mesin, $keterangan, $user_aktif]);
        
        $ikon = ($jenis == 'MASUK') ? "📦" : "🔧";
        $teks_log = ($jenis == 'MASUK') ? "Stok MASUK" : "Pemakaian";
        catatLog($pdo, $user_aktif, "$teks_log $qty_transaksi {$part['nama_part']}", $ikon);

        header("Location: mtc_sparepart.php?pesan=transaksi_sukses"); exit();
    }
}

// 3️⃣ LOGIKA: HAPUS MASTER ITEM
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_master'])) {
    $id = intval($_POST['hapus_id']);
    $pdo->prepare("DELETE FROM db_mtc_sparepart WHERE id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM db_mtc_history WHERE id_part = ?")->execute([$id]); // Hapus histori terkait
    header("Location: mtc_sparepart.php?pesan=hapus_sukses"); exit();
}

// 🚀 NOTIFIKASI
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'master_sukses') $pesan = "<div class='alert alert-primary'>✅ Master Sparepart Baru Berhasil Ditambahkan!</div>";
    if ($_GET['pesan'] == 'transaksi_sukses') $pesan = "<div class='alert alert-success'>✅ Transaksi Berhasil! Kartu Stok terupdate otomatis.</div>";
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-danger'>🗑️ Data Sparepart & Histori dihapus permanen.</div>";
}

// 🚀 AMBIL METRIK DASHBOARD
$tot_item = $pdo->query("SELECT COUNT(*) FROM db_mtc_sparepart")->fetchColumn() ?: 0;
$stok_kritis = $pdo->query("SELECT COUNT(*) FROM db_mtc_sparepart WHERE qty_stok <= limit_stok")->fetchColumn() ?: 0;
$pakai_bulan_ini = $pdo->query("SELECT SUM(qty) FROM db_mtc_history WHERE jenis = 'KELUAR' AND MONTH(tanggal) = MONTH(CURRENT_DATE()) AND YEAR(tanggal) = YEAR(CURRENT_DATE())")->fetchColumn() ?: 0;

$page_title = "MTC Utility & Sparepart — H2 BASE";
$active_page = "mtc_sparepart";
require 'header.php';
?>

<style>
    /* 🚀 FILTER ACTION PANEL & FORM INPUT MODERN SAAS */
    .filter-box { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
    .filter-box .form-group { margin-bottom: 0; min-width: 150px; }
    
    input, select, textarea { 
        background: #ffffff !important; 
        border: 1px solid #cbd5e1 !important; 
        border-radius: 8px !important; 
        padding: 10px 14px !important; 
        font-size: 13px !important; 
        color: #0f172a !important; 
        font-weight: 700 !important;
        box-sizing: border-box !important;
        transition: all 0.2s ease-in-out !important;
        font-family: inherit;
        width: 100%;
    }
    input:focus, select:focus, textarea:focus { 
        border-color: #0ea5e9 !important; 
        background: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(14,165,233,0.15) !important; 
        outline: none;
    }

    /* 🚀 BUTTONS MODERN SAAS */
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
    .btn-submit-modern:hover { background: #059669 !important; transform: translateY(-2px) !important; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3) !important; }

    .btn-tambah-modern {
        background: #0ea5e9 !important; 
        color: #ffffff !important;
        border: none !important;
        padding: 10px 16px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 800 !important;
        cursor: pointer !important;
        transition: all 0.2s ease-in-out !important;
        box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.2) !important;
        display: inline-flex !important; align-items: center !important; gap: 6px;
    }
    .btn-tambah-modern:hover { background: #0284c7 !important; transform: translateY(-1px) !important; }

    .btn-excel-modern {
        background: #16a34a !important; 
        color: #ffffff !important;
        border: none !important;
        padding: 10px 16px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 800 !important;
        cursor: pointer !important;
        transition: all 0.2s ease-in-out !important;
        box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.2) !important;
        display: inline-flex !important; align-items: center !important; gap: 6px;
    }
    .btn-excel-modern:hover { background: #15803d !important; transform: translateY(-1px) !important; }

    .btn-batal-modern {
        background: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important;
        padding: 12px 28px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important;
        cursor: pointer !important; transition: all 0.2s !important;
    }
    .btn-batal-modern:hover { background: #f1f5f9 !important; color: #0f172a !important; }

    .btn-hapus { background: #fee2e2; color: #dc2626; padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 11px; border: 1px solid #fecaca; cursor: pointer; transition: 0.2s;}
    .btn-hapus:hover { background: #fca5a5; color: #991b1b; }

    /* 🚀 CSS WIDGET DASHBOARD PREMIUM */
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin-bottom: 24px; }
    
    .widget-box { background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; border: 1px solid #e2e8f0; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); }
    .widget-box:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); cursor: pointer; }
    
    .widget-box.blue { border-top: 5px solid #0ea5e9; background: #f0f9ff; }
    .widget-box.red { border-top: 5px solid #ef4444; background: #fef2f2; }
    .widget-box.orange { border-top: 5px solid #f59e0b; background: #fffbeb; }
    
    .widget-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
    .widget-value { font-size: 32px; font-weight: 900; color: #0f172a; margin-bottom: 4px; display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; line-height: 1;}
    .widget-unit { font-size: 13px; font-weight: 700; color: #94a3b8; }
    .widget-icon { position: absolute; right: -15px; bottom: -20px; font-size: 90px; opacity: 0.05; }

    /* 🚀 CSS LAYOUT FORM & MODAL */
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px 20px; margin-bottom: 16px; }
    .form-group { display: flex; flex-direction: column; }
    .form-group label { margin-bottom: 8px; font-weight: 800; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;}

    .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.85); z-index: 9999; justify-content: center; align-items: center; padding: 20px; backdrop-filter: blur(8px); }
    .modal-box { background: #ffffff; padding: 30px; border-radius: 12px; width: 100%; max-width: 550px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid #cbd5e1;}

    /* 🚀 PREMIUM TABLE STYLING */
    .table-wrapper { width: 100%; overflow-x: auto; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.01); max-height: 500px;}
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #334155; white-space: nowrap; min-width: 900px;}
    .table-premium th, .table-premium td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9; font-weight: 600;}
    .table-premium th { background-color: #0f172a; color: #ffffff; font-weight: 700; position: sticky; top: 0; z-index: 10; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 2px solid #1e293b; text-align: center;}
    .table-premium tbody tr:nth-child(even) td { background-color: #f8fafc; } 
    .table-premium tbody tr:hover td { background-color: #f1f5f9 !important; }
    
    .badge-in { background: #dcfce7; color: #166534; padding: 6px 10px; border-radius: 6px; font-weight: 900; font-size: 11px; border: 1px solid #bbf7d0; display: inline-block;}
    .badge-out { background: #fee2e2; color: #b91c1c; padding: 6px 10px; border-radius: 6px; font-weight: 900; font-size: 11px; border: 1px solid #fecaca; display: inline-block;}
    
    /* Highlight Row Kritis */
    .row-kritis td { background-color: #fff1f2 !important; color: #9f1239;}

    @media (max-width: 768px) {
        .form-grid { grid-template-columns: 1fr; }
        .btn-submit-modern, .btn-batal-modern, .btn-tambah-modern, .btn-excel-modern { width: 100%; justify-content: center; margin-bottom: 10px;}
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

<div class="dashboard-grid">
    <div class="widget-box blue">
        <div class="widget-title">Total Item Sparepart</div>
        <div class="widget-value" style="color: #0284c7;"><?= number_format($tot_item) ?> <span class="widget-unit">SKU</span></div>
        <div class="widget-icon">⚙️</div>
    </div>
    <div class="widget-box red">
        <div class="widget-title" style="color: #ef4444;">🚨 Stok Kritis / Menipis</div>
        <div class="widget-value" style="color: #dc2626;"><?= number_format($stok_kritis) ?> <span class="widget-unit" style="color: #fca5a5;">Item</span></div>
        <div class="widget-icon">⚠️</div>
    </div>
    <div class="widget-box orange">
        <div class="widget-title">Pemakaian Bulan Ini</div>
        <div class="widget-value" style="color: #d97706;"><?= number_format($pakai_bulan_ini, 2, ',', '.') ?> <span class="widget-unit" style="color: #fcd34d;">Unit</span></div>
        <div class="widget-icon">🔧</div>
    </div>
</div>

<div class="card" style="border-top: 5px solid #10b981; background: #f0fdf4;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; margin-bottom: 20px; border-bottom: 1px dashed #bbf7d0; padding-bottom: 15px;">
        <h2 style="color: #065f46; margin: 0; border: none; padding: 0;">📝 INPUT TRANSAKSI SPAREPART</h2>
    </div>
    
    <form method="POST" action="">
        <div class="form-grid" style="grid-template-columns: 1fr 1fr 2fr;">
            <div class="form-group">
                <label>Tanggal Transaksi</label>
                <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Jenis Mutasi</label>
                <select name="jenis" id="jenisMutasi" onchange="cekJenisMutasi()" required style="font-weight: 900; color: #1e293b;">
                    <option value="KELUAR" style="color: #dc2626; font-weight: bold;">🔴 PEMAKAIAN (KELUAR)</option>
                    <option value="MASUK" style="color: #16a34a; font-weight: bold;">🟢 TAMBAH STOK (MASUK)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Pilih Item / Sparepart</label>
                <select name="id_part" required style="border-color: #10b981;">
                    <option value="">-- Pilih Sparepart (Ketik untuk mencari) --</option>
                    <?php 
                    $parts = $pdo->query("SELECT id, kode_part, nama_part, qty_stok, satuan FROM db_mtc_sparepart ORDER BY nama_part ASC")->fetchAll(PDO::FETCH_ASSOC);
                    foreach($parts as $p): 
                    ?>
                        <option value="<?= $p['id'] ?>"> <?= htmlspecialchars($p['nama_part']) ?> [<?= $p['kode_part'] ?>] - Stok Sisa: <?= floatval($p['qty_stok']) ?> <?= $p['satuan'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-grid" style="grid-template-columns: 1fr 1fr 1.5fr;">
            <div class="form-group">
                <label>Jumlah (QTY)</label>
                <input type="number" step="any" name="qty_transaksi" required style="font-size: 20px; font-weight: 900; color: #0f172a; text-align: center; border-color: #10b981;">
            </div>
            <div class="form-group">
                <label id="labelTeknisi">Nama Teknisi / Pemakai</label>
                <input type="text" name="teknisi" placeholder="Cth: Pak Budi" required>
            </div>
            <div class="form-group" id="grupMesin">
                <label>Dipakai Untuk Mesin / Area</label>
                <input type="text" name="mesin" id="inputMesin" placeholder="Cth: Corrugator, Boiler, Dll" required>
            </div>
        </div>
        
        <div class="form-group" style="margin-bottom: 20px;">
            <label>Keterangan Tambahan</label>
            <input type="text" name="keterangan" placeholder="Cth: Ganti bearing putus, Beli dari Toko Jaya, dll">
        </div>

        <div style="display:flex; justify-content: flex-end;">
            <button type="submit" name="simpan_transaksi" class="btn-submit-modern">💾 Simpan Transaksi & Update Master</button>
        </div>
    </form>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
        <h2 style="margin: 0; border: none; padding: 0;">📦 MASTER STOK FISIK (LIVE)</h2>
        <div style="display:flex; gap: 10px; flex-wrap: wrap;">
            <button type="button" onclick="exportKeExcel('tabelMaster', 'Master_Sparepart')" class="btn-excel-modern">📥 Export Excel</button>
            <button type="button" onclick="bukaModalMaster()" class="btn-tambah-modern">➕ Tambah Master Baru</button>
        </div>
    </div>
    
    <div class="table-wrapper">
        <table class="table-premium" id="tabelMaster">
            <thead>
                <tr>
                    <th style="width: 15%;">KODE / RAK</th>
                    <th style="width: 35%; text-align: left;">NAMA SPAREPART</th>
                    <th style="width: 10%;">KATEGORI</th>
                    <th style="width: 15%;">STOK SISA</th>
                    <th style="width: 10%;">BATAS LIMIT</th>
                    <th style="width: 15%;" data-noexport="true">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                if (count($parts) > 0):
                    $stmt_master = $pdo->query("SELECT * FROM db_mtc_sparepart ORDER BY nama_part ASC");
                    while($row = $stmt_master->fetch(PDO::FETCH_ASSOC)):
                        $is_kritis = (floatval($row['qty_stok']) <= floatval($row['limit_stok']));
                        $class_row = $is_kritis ? "row-kritis" : "";
                ?>
                <tr class="<?= $class_row ?>">
                    <td style="text-align: center;">
                        <strong style="color: #0f172a; font-size: 14px;"><?= htmlspecialchars($row['kode_part']) ?></strong><br>
                        <span style="font-size: 11px; color: #64748b;">Rak: <?= htmlspecialchars($row['rak_lokasi']) ?></span>
                    </td>
                    <td style="text-align: left; font-weight: 800; font-size: 14px; color: #1e293b;"><?= htmlspecialchars($row['nama_part']) ?></td>
                    <td style="text-align: center;"><span style="background: #e2e8f0; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 800; color: #475569;"><?= $row['kategori'] ?></span></td>
                    
                    <td style="text-align: center; font-size: 20px; font-weight: 900; color: <?= $is_kritis ? '#dc2626' : '#0284c7' ?>;">
                        <?= floatval($row['qty_stok']) ?> <span style="font-size: 12px; color: #64748b;"><?= $row['satuan'] ?></span>
                        <?php if($is_kritis): ?><br><span style="font-size: 10px; color: #ef4444; background: #fee2e2; padding: 2px 6px; border-radius: 4px; border: 1px solid #fecaca; display: inline-block; margin-top: 4px;">⚠️ KRITIS</span><?php endif; ?>
                    </td>
                    
                    <td style="text-align: center; font-weight: 800; color: #94a3b8; font-size: 16px;"><?= floatval($row['limit_stok']) ?></td>
                    <td style="text-align: center;" data-noexport="true">
                        <form method="POST" onsubmit="return confirm('Hapus item ini? Seluruh riwayatnya juga akan hilang!');">
                            <input type="hidden" name="hapus_id" value="<?= $row['id'] ?>"><input type="hidden" name="hapus_master" value="1">
                            <button type="submit" class="btn-hapus">HAPUS ITEM</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="6" style="text-align: center; padding: 30px; color: #94a3b8;">Belum ada master sparepart terdaftar.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="border-top: 5px solid #f59e0b;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
        <h2 style="color: #b45309; margin: 0; border: none; padding: 0;">📖 KARTU STOK & HISTORI PEMAKAIAN</h2>
        <button type="button" onclick="exportKeExcel('tabelHistori', 'Histori_Sparepart')" class="btn-excel-modern">📥 Export Excel</button>
    </div>
    
    <div class="table-wrapper">
        <table class="table-premium" id="tabelHistori">
            <thead>
                <tr>
                    <th style="width: 10%;">TANGGAL</th>
                    <th style="width: 10%;">JENIS</th>
                    <th style="width: 25%; text-align: left;">NAMA PART / KODE</th>
                    <th style="width: 10%;">QTY</th>
                    <th style="width: 15%;">SALDO STOK</th>
                    <th style="width: 15%; text-align: left;">MESIN / TEKNISI</th>
                    <th style="width: 15%; text-align: left;">KETERANGAN</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sql_histori = "SELECT h.*, s.nama_part, s.kode_part, s.satuan 
                                FROM db_mtc_history h 
                                JOIN db_mtc_sparepart s ON h.id_part = s.id 
                                ORDER BY h.waktu DESC, h.id DESC LIMIT 200";
                $histori = $pdo->query($sql_histori)->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($histori) > 0):
                    foreach($histori as $h):
                        $badge = ($h['jenis'] == 'MASUK') ? "badge-in" : "badge-out";
                        $simbol = ($h['jenis'] == 'MASUK') ? "+" : "-";
                ?>
                <tr>
                    <td style="text-align: center; font-weight: 800; color: #475569;"><?= date('d/m/Y', strtotime($h['tanggal'])) ?></td>
                    <td style="text-align: center;"><span class="<?= $badge ?>"><?= $h['jenis'] ?></span></td>
                    <td style="text-align: left;">
                        <strong style="color: #0f172a; font-size: 13px;"><?= htmlspecialchars($h['nama_part']) ?></strong><br>
                        <span style="font-size: 11px; color: #94a3b8;">[<?= htmlspecialchars($h['kode_part']) ?>]</span>
                    </td>
                    <td style="text-align: center; font-size: 18px; font-weight: 900; color: <?= ($h['jenis'] == 'MASUK') ? '#16a34a' : '#dc2626' ?>;">
                        <?= $simbol . floatval($h['qty']) ?>
                    </td>
                    <td style="text-align: center; font-size: 18px; font-weight: 900; color: #0f172a; background: #f8fafc; border-left: 2px solid #e2e8f0; border-right: 2px solid #e2e8f0;">
                        = <?= floatval($h['saldo_akhir']) ?> <span style="font-size: 11px; color: #64748b;"><?= $h['satuan'] ?></span>
                    </td>
                    <td style="text-align: left; font-size: 12px;">
                        <?php if($h['jenis'] == 'KELUAR'): ?>
                            <strong style="color: #d97706;">M:</strong> <?= htmlspecialchars($h['mesin']) ?><br>
                        <?php endif; ?>
                        <strong style="color: #0284c7;">T:</strong> <?= htmlspecialchars($h['teknisi']) ?>
                    </td>
                    <td style="text-align: left; font-size: 12px; color: #475569; max-width: 250px; white-space: normal; line-height: 1.4;">
                        <?= htmlspecialchars($h['keterangan']) ?>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">Belum ada histori transaksi terekam.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalMaster" class="modal-overlay">
    <div class="modal-box">
        <h3 style="margin-top: 0; color: #0f172a; font-size: 18px; font-weight: 800; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 20px;">➕ Tambah Sparepart Baru</h3>
        <form method="POST" action="">
            <div class="form-grid" style="grid-template-columns: 1fr 2fr;">
                <div class="form-group">
                    <label>Kode Part</label>
                    <input type="text" name="kode_part" placeholder="Cth: BRG-001" required>
                </div>
                <div class="form-group">
                    <label>Nama Part / Item</label>
                    <input type="text" name="nama_part" placeholder="Cth: Bearing SKF 6205" required>
                </div>
            </div>
            <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="kategori">
                        <option value="Mekanikal">Mekanikal</option>
                        <option value="Elektrikal">Elektrikal</option>
                        <option value="Pelumas/Chemical">Pelumas & Chemical</option>
                        <option value="Pneumatik">Pneumatik</option>
                        <option value="Utility/Umum">Utility / Umum</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Satuan</label>
                    <input type="text" name="satuan" value="PCS" required>
                </div>
            </div>
            <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                <div class="form-group">
                    <label>Batas Stok Minimum (Limit)</label>
                    <input type="number" step="any" name="limit_stok" value="5" required style="color: #ef4444; border-color: #fca5a5; background: #fef2f2;">
                </div>
                <div class="form-group">
                    <label>Lokasi Rak / Penyimpanan</label>
                    <input type="text" name="rak_lokasi" placeholder="Cth: Rak A2">
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 25px; justify-content: flex-end;">
                <button type="button" onclick="tutupModalMaster()" class="btn-batal-modern">Batal</button>
                <button type="submit" name="tambah_master" class="btn-submit-modern" style="background: #0ea5e9 !important; box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.2) !important;">💾 Simpan Data Master</button>
            </div>
        </form>
    </div>
</div>

<script>
    // 🚀 FUNGSI UNTUK MENGATUR FORM TRANSAKSI SESUAI JENIS (MASUK/KELUAR)
    function cekJenisMutasi() {
        let jenis = document.getElementById('jenisMutasi').value;
        let grupMesin = document.getElementById('grupMesin');
        let inputMesin = document.getElementById('inputMesin');
        let labelTeknisi = document.getElementById('labelTeknisi');

        if (jenis === 'MASUK') {
            grupMesin.style.display = 'none';
            inputMesin.removeAttribute('required');
            labelTeknisi.innerText = "Nama Supplier / Pembeli";
        } else {
            grupMesin.style.display = 'flex';
            inputMesin.setAttribute('required', 'true');
            labelTeknisi.innerText = "Nama Teknisi / Pemakai";
        }
    }

    function bukaModalMaster() {
        document.getElementById('modalMaster').style.display = 'flex';
    }
    
    function tutupModalMaster() {
        document.getElementById('modalMaster').style.display = 'none';
    }

    // 🚀 FUNGSI EXPORT KE EXCEL BERSIH (TANPA KOLOM AKSI)
    function exportKeExcel(tableId, fileNamePrefix) {
        let table = document.getElementById(tableId);
        if (!table) return;

        let cloneTable = table.cloneNode(true);

        // Buang th dan td yang ada flag noexport (Biasanya kolom Aksi)
        let noExportTh = cloneTable.querySelectorAll('th[data-noexport="true"]');
        noExportTh.forEach(th => th.remove());
        
        let noExportTd = cloneTable.querySelectorAll('td[data-noexport="true"]');
        noExportTd.forEach(td => td.remove());

        // Ratakan badge agar tampil rapi tanpa styling aneh di Excel
        let badges = cloneTable.querySelectorAll('.badge-in, .badge-out');
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
                    th { background-color: #0f172a; color: #ffffff; font-weight: bold; text-align: center; }
                
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
                <h2 style="text-align: center; margin-bottom: 15px;">${fileNamePrefix.replace(/_/g, ' ')} - H2 BASE</h2>
                <p><strong>Diekspor pada:</strong> ${new Date().toLocaleDateString('id-ID')}</p>
                ${cloneTable.outerHTML}
            </body>
            </html>
        `;

        let blob = new Blob([htmlTemplate], { type: 'application/vnd.ms-excel' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = fileNamePrefix + '_' + new Date().toISOString().slice(0,10) + '.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Jalankan pengecekan pertama kali halaman dimuat
    window.onload = function() {
        cekJenisMutasi();
    };
</script>
<?php require_once 'footer.php'; ?>

