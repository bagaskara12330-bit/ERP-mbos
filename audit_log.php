<?php
require 'koneksi.php';
require 'header.php';

// 🛑 KEAMANAN GANDA: Pastikan hanya ADMIN yang bisa masuk halaman ini!
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo "<div class='container'><div class='card' style='text-align:center; padding:50px;'>
          <div style='font-size: 64px;'>🛑</div>
          <h2 style='color: #ef4444;'>AKSES DITOLAK!</h2>
          <p>Halaman Jejak Digital ini adalah dokumen rahasia. Hanya level <strong>ADMIN</strong> yang memiliki wewenang untuk melihatnya.</p>
          <a href='index.php' class='btn-submit-modern'>Kembali ke Dashboard</a>
          </div></div>";
    require 'footer.php';
    exit();
}

$page_title = "Jejak Digital (Audit Log) — H2 BASE ERP";
$active_page = "audit";

// Setup Pagination & Filter Pencarian
$limit = 50; // Jumlah log per halaman
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_query = "";
$params = [];

if (isset($_GET['q']) && trim($_GET['q']) !== '') {
    $search = trim($_GET['q']);
    $search_query = "WHERE username LIKE :search OR aksi LIKE :search OR ikon LIKE :search";
    $params['search'] = "%$search%";
}

try {
    // Menghitung Total Data untuk Pagination
    $stmt_count = $pdo->prepare("SELECT COUNT(id) as total FROM db_aktivitas_log $search_query");
    $stmt_count->execute($params);
    $total_data = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_data / $limit);

    // Mengambil Data sesuai Halaman
    $sql = "SELECT * FROM db_aktivitas_log $search_query ORDER BY id DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2>🕵️ Jejak Digital Server (Audit Log)</h2>
            <p style="color: #64748b; font-size: 13px; margin-top:-15px;">Merekam semua pergerakan dan modifikasi data oleh pengguna di seluruh area H2 BASE.</p>
        </div>
    </div>

    <!-- Filter Pencarian -->
    <div class="card" style="margin-bottom: 24px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
        <form method="GET" action="audit_log.php" style="display: flex; gap: 10px; flex-grow: 1; max-width: 500px; margin: 0;">
            <input type="text" name="q" value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>" placeholder="Cari nama user atau jenis aktivitas..." style="flex-grow: 1; padding: 10px 15px; border-radius: 8px; border: 1px solid #cbd5e1; font-size: 14px; outline: none;">
            <button type="submit" class="btn-submit-modern" style="padding: 10px 20px; border-radius: 8px; font-weight: 700;">Cari Log</button>
            <?php if(isset($_GET['q']) && $_GET['q'] !== ''): ?>
                <a href="audit_log.php" class="btn-submit-modern" style="background: #f1f5f9; color: #475569; padding: 10px 15px; border-radius: 8px; text-decoration: none;">Reset</a>
            <?php endif; ?>
        </form>
        <div style="font-size: 13px; color: #64748b; background: #f8fafc; padding: 8px 16px; border-radius: 20px; border: 1px solid #e2e8f0;">
            Total Rekaman: <strong><?= number_format($total_data) ?> Aktivitas</strong>
        </div>
    </div>

    <!-- Tabel Data -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="overflow-x: auto;">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th style="width: 180px;">Waktu Sistem</th>
                        <th style="width: 150px;">Pelaku (Username)</th>
                        <th style="width: 50px;">Kategori</th>
                        <th>Rincian Aktivitas / Jejak Modifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $row): 
                            $date_obj = new DateTime($row['waktu']);
                            $waktu_format = $date_obj->format('d M Y, H:i:s');
                            $ikon = $row['ikon'] ? $row['ikon'] : '🔔';
                        ?>
                        <tr>
                            <td style="color: #64748b; font-size: 12px; font-weight: 600; font-family: monospace;"><?= $waktu_format ?></td>
                            <td>
                                <span style="background: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 12px;">
                                    @<?= htmlspecialchars($row['username']) ?>
                                </span>
                            </td>
                            <td style="text-align: center; font-size: 18px;"><?= $ikon ?></td>
                            <td style="color: #0f172a; font-weight: 500; font-size: 14px;"><?= htmlspecialchars($row['aksi']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8;">Tidak ada jejak digital yang ditemukan untuk kriteria pencarian ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Modern -->
    <?php if ($total_pages > 1): ?>
    <div style="margin-top: 24px; display: flex; justify-content: center; gap: 8px;">
        <?php 
        $query_string = isset($_GET['q']) ? "&q=" . urlencode($_GET['q']) : "";
        for ($i = 1; $i <= $total_pages; $i++): 
            // Hanya tampilkan 5 tombol pagination terdekat agar tidak kepanjangan
            if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)):
        ?>
            <a href="?page=<?= $i ?><?= $query_string ?>" class="btn-submit-modern" style="padding: 8px 14px; background: <?= ($i == $page) ? '#0ea5e9' : '#ffffff' ?>; color: <?= ($i == $page) ? '#ffffff' : '#475569' ?>; border: 1px solid <?= ($i == $page) ? '#0ea5e9' : '#cbd5e1' ?>; font-weight: <?= ($i == $page) ? '800' : '600' ?>; text-decoration: none; border-radius: 6px; box-shadow: none;">
                <?= $i ?>
            </a>
        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
            <span style="padding: 8px 5px; color: #94a3b8;">...</span>
        <?php endif; endfor; ?>
    </div>
    <?php endif; ?>
    <div style="height: 40px;"></div>
</div>

<?php require 'footer.php'; ?>
