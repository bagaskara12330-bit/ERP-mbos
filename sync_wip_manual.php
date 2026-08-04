<?php
require 'auth.php';
try {
    // Cari data BOX hari ini di NC Slitter
    $stmt = $pdo->query("SELECT * FROM db_produksi_nc WHERE jenis_produksi = 'BOX' AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)");
    $data_nc = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $synced = 0;
    foreach ($data_nc as $nc) {
        // Cek apakah sudah ada di WIP
        $cek = $pdo->prepare("SELECT id FROM db_wip_produksi WHERE nc_id = ?");
        $cek->execute([$nc['id']]);
        if ($cek->rowCount() == 0) {
            // Belum ada, masukkan
            $spek = "P" . $nc['ukuran_p'] . " x L" . $nc['ukuran_l'] . " " . $nc['keterangan'];
            $pdo->prepare("INSERT INTO db_wip_produksi (nc_id, tanggal_masuk, no_spk, customer, spesifikasi, jumlah_masuk, sisa_wip, tonase, sisa_tonase, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Antri')")
                ->execute([
                    $nc['id'], $nc['tanggal'], $nc['mo'], $nc['customer'], $spek, 
                    $nc['hasil_counter'], $nc['hasil_counter'], $nc['total_kg'], $nc['total_kg']
                ]);
            $synced++;
        }
    }
    echo "SUCCESS: $synced records synced.";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
