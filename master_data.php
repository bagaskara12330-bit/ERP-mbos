<?php
require_once 'auth.php';
require_akses('set_master');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = "";

// Lanjutkan ke logika // AUTO-CREATE TABEL atau simpan...

// 🛡️ KUNCI KEAMANAN: Hanya Admin yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    echo "<script>alert('🛑 AKSES DITOLAK! Hanya Admin yang diizinkan mengatur Master Data.'); window.location.href='dashboard.php';</script>";
    exit();
}

$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS master_regu_nama (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_regu VARCHAR(100) NOT NULL,
        status ENUM('Aktif', 'Nonaktif') DEFAULT 'Aktif'
    )");
    
    $cek = $pdo->query("SELECT COUNT(*) FROM master_regu_nama")->fetchColumn();
    if ($cek == 0) {
        $pdo->exec("INSERT INTO master_regu_nama (nama_regu, status) VALUES ('TASKIM', 'Aktif'), ('SAMSUL', 'Aktif')");
    }
} catch (PDOException $e) { die("Gagal membuat tabel database Master Data: " . $e->getMessage()); }

$pesan = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_regu'])) {
    $nama = strtoupper(trim($_POST['nama_regu']));
    if (!empty($nama)) {
        $stmt_cek = $pdo->prepare("SELECT COUNT(*) FROM master_regu_nama WHERE nama_regu = ?");
        $stmt_cek->execute([$nama]);
        if ($stmt_cek->fetchColumn() > 0) {
            $pesan = "<div class='alert alert-danger'>❌ Gagal: Regu/Mesin <strong>$nama</strong> sudah ada di database!</div>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO master_regu_nama (nama_regu) VALUES (?)");
            if ($stmt->execute([$nama])) {
                catatLog($pdo, $user_aktif, "Menambahkan Master Data Mesin/Regu baru: $nama.", "⚙️");
                $pesan = "<div class='alert alert-success'>✅ Berhasil: Master Data <strong>$nama</strong> telah ditambahkan!</div>";
            }
        }
    }
}

// 🚀 LOGIKA HAPUS DATA AMAN (MENGGUNAKAN POST METHOD BUKAN GET)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id'])) {
    $id = intval($_POST['hapus_id']);
    $n_stmt = $pdo->prepare("SELECT nama_regu FROM master_regu_nama WHERE id=?"); $n_stmt->execute([$id]); $n_regu = $n_stmt->fetchColumn();

    $stmt = $pdo->prepare("DELETE FROM master_regu_nama WHERE id = ?");
    $stmt->execute([$id]);
    catatLog($pdo, $user_aktif, "Menghapus Master Data Mesin/Regu ($n_regu) secara permanen.", "🗑️");
    header("Location: master_data.php?pesan=hapus_sukses"); exit();
}

if (isset($_GET['aksi'])) {
    if ($_GET['aksi'] == 'toggle' && isset($_GET['id']) && isset($_GET['status'])) {
        $id = intval($_GET['id']);
        $status_baru = $_GET['status'] == 'Aktif' ? 'Aktif' : 'Nonaktif';
        $n_stmt = $pdo->prepare("SELECT nama_regu FROM master_regu_nama WHERE id=?"); $n_stmt->execute([$id]); $n_regu = $n_stmt->fetchColumn();

        $stmt = $pdo->prepare("UPDATE master_regu_nama SET status = ? WHERE id = ?");
        $stmt->execute([$status_baru, $id]);
        catatLog($pdo, $user_aktif, "Mengubah status Mesin/Regu $n_regu menjadi $status_baru.", "🔄");
        header("Location: master_data.php?pesan=toggle_sukses"); exit();
    }
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'toggle_sukses') $pesan = "<div class='alert alert-success'>✅ Status regu berhasil diperbarui!</div>";
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-success'>🗑️ Master Data regu telah dihapus permanen!</div>";
}

$page_title = "H2 BASE — Master Data Regu";
$active_page = "master";
require 'header.php';
?>

<style>
    .half-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; align-items: start; }
    @media (max-width: 992px) { .half-grid { grid-template-columns: 1fr; } }
    
    .table-wrapper { width: 100%; overflow-x: auto; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.01); }
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #334155; white-space: nowrap; }
    .table-premium th, .table-premium td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; text-align: left; font-weight: 600;}
    .table-premium th { background-color: #0f172a; color: #ffffff; font-weight: 700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 2px solid #1e293b; text-align: center; }
    .table-premium tbody tr:nth-child(even) td { background-color: #f8fafc; }
    .table-premium tbody tr:hover td { background-color: #f1f5f9 !important; }

    .badge-aktif { background: #dcfce7; color: #166534; padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 11px; border: 1px solid #bbf7d0; display: inline-block; }
    .badge-nonaktif { background: #fee2e2; color: #991b1b; padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 11px; border: 1px solid #fecaca; display: inline-block; }

    .btn-hapus { background: #fee2e2; color: #dc2626; padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 11px; border: 1px solid #fecaca; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-block; }
    .btn-hapus:hover { background: #fca5a5; color: #991b1b; transform: translateY(-1px); }
    
    .btn-toggle-aktif { background: #fef3c7; color: #d97706; padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 11px; border: 1px solid #fde68a; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-block; }
    .btn-toggle-aktif:hover { background: #fde68a; color: #b45309; transform: translateY(-1px); }

    .btn-toggle-non { background: #dcfce7; color: #16a34a; padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 11px; border: 1px solid #bbf7d0; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-block; }
    .btn-toggle-non:hover { background: #bbf7d0; color: #15803d; transform: translateY(-1px); }

    .btn-submit-modern { background: #0ea5e9; color: white; border: none; padding: 12px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: 0.2s; width: 100%; box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.2); }
    .btn-submit-modern:hover { background: #0284c7; transform: translateY(-1px); }
</style>

<?= $pesan ?>

<div class="half-grid">
    <div class="card">
        <h2>➕ Tambah Master Regu / Mesin</h2>
        <p style="font-size: 12px; color: #64748b; margin-top: -10px; margin-bottom: 20px;">
            Data yang dimasukkan di sini akan otomatis muncul di pilihan dropdown pada menu Input Laporan Produksi.
        </p>
        <form action="" method="POST" autocomplete="off">
            <div class="form-group">
                <label style="font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase; margin-bottom: 8px; display: block;">Nama Regu atau Mesin</label>
                <input type="text" name="nama_regu" placeholder="Cth: REGU 3 / MESIN CORR A" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; outline: none; transition: 0.2s;">
            </div>
            <button type="submit" name="tambah_regu" class="btn-submit-modern" style="margin-top: 15px;">Simpan Master Data</button>
        </form>
    </div>

    <div class="card">
        <h2>📋 Daftar Master Regu & Status</h2>
        <div class="table-wrapper" style="max-height: 400px; overflow-y: auto;">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th style="width: 50px;">NO</th>
                        <th style="text-align: left;">NAMA REGU / MESIN</th>
                        <th style="width: 120px;">STATUS</th>
                        <th style="width: 180px;">AKSI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $master_list = $pdo->query("SELECT * FROM master_regu_nama ORDER BY status ASC, nama_regu ASC")->fetchAll(PDO::FETCH_ASSOC);
                    if (count($master_list) > 0):
                        foreach ($master_list as $row): 
                    ?>
                    <tr>
                        <td style="text-align: center; font-weight:bold; color:#64748b;"><?= $no++ ?></td>
                        <td style="font-weight:800; color:#0f172a; font-size:14px; text-align: left;"><?= htmlspecialchars($row['nama_regu']) ?></td>
                        <td style="text-align: center;">
                            <span class="<?= $row['status'] == 'Aktif' ? 'badge-aktif' : 'badge-nonaktif' ?>">
                                <?= $row['status'] ?>
                            </span>
                        </td>
                        <td style="text-align: center; display: flex; gap: 8px; justify-content: center; border-bottom: none;">
                            <?php if ($row['status'] == 'Aktif'): ?>
                                <a href="master_data.php?aksi=toggle&id=<?= $row['id'] ?>&status=Nonaktif" class="btn-toggle-aktif">Nonaktifkan</a>
                            <?php else: ?>
                                <a href="master_data.php?aksi=toggle&id=<?= $row['id'] ?>&status=Aktif" class="btn-toggle-non">Aktifkan</a>
                            <?php endif; ?>
                            <a href="javascript:void(0);" class="btn-hapus" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Yakin ingin menghapus Master Data ini secara permanen?')">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; padding: 20px; color:#94a3b8;">Belum ada master data.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 🚀 FORM TERSEMBUNYI UNTUK PENGHAPUSAN AMAN -->
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
</script>
<?php require_once 'footer.php'; ?>
