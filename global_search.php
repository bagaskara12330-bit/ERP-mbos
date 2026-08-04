<?php
require_once 'auth.php';

$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$hasil_produksi = [];
$hasil_corr = [];
$hasil_flexo = [];
$hasil_hrd = [];
$hasil_mtc = [];

// Fungsi Highlighting Teks
function highlightKeyword($text, $keyword) {
    if ($keyword === '') return htmlspecialchars($text);
    $text = htmlspecialchars($text);
    return preg_replace('/(' . preg_quote(htmlspecialchars($keyword), '/') . ')/i', '<mark style="background-color: #fef08a; padding: 2px 4px; border-radius: 4px; color: #854d0e; font-weight: bold;">$1</mark>', $text);
}

if ($q !== '') {
    $search_term = "%$q%";

    // Hybrid FULLTEXT Logic
    $is_fulltext = strlen($q) >= 3;
    $ft_query = "";
    if ($is_fulltext) {
        $words = explode(' ', $q);
        foreach($words as $w) {
            $w = trim($w);
            if ($w !== '') {
                $w = preg_replace('/[^a-zA-Z0-9_]/', '', $w);
                if ($w !== '') {
                    $ft_query .= "+" . $w . "* ";
                }
            }
        }
        $ft_query = trim($ft_query);
        if ($ft_query === "") {
            $is_fulltext = false;
        }
    }

    // 1. Modul Produksi NC/Slitter (Akses: prod_lap, prod_rek, dash_nc)
    if (in_array('prod_lap', $user_akses) || in_array('prod_rek', $user_akses) || in_array('dash_nc', $user_akses) || $is_admin) {
        try {
            if ($is_fulltext) {
                $stmt = $pdo->prepare("SELECT id, tanggal, shift, mo, customer, jenis_produksi FROM db_produksi_nc WHERE MATCH(mo, customer) AGAINST(:ft IN BOOLEAN MODE) OR tanggal LIKE :q ORDER BY tanggal DESC LIMIT 15");
                $stmt->execute(['ft' => $ft_query, 'q' => $search_term]);
            } else {
                $stmt = $pdo->prepare("SELECT id, tanggal, shift, mo, customer, jenis_produksi FROM db_produksi_nc WHERE mo LIKE :q OR customer LIKE :q OR tanggal LIKE :q ORDER BY tanggal DESC LIMIT 15");
                $stmt->execute(['q' => $search_term]);
            }
            $hasil_produksi = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { }
    }

    // 1b. Modul Produksi Corrugator (Akses: prod_lap, prod_rek, dash_corr)
    if (in_array('prod_lap', $user_akses) || in_array('prod_rek', $user_akses) || in_array('dash_corr', $user_akses) || $is_admin) {
        try {
            if ($is_fulltext) {
                $stmt = $pdo->prepare("SELECT id, tanggal, regu, shift, kg_produksi, target_produksi FROM mbos_regu WHERE MATCH(regu) AGAINST(:ft IN BOOLEAN MODE) OR tanggal LIKE :q ORDER BY tanggal DESC LIMIT 15");
                $stmt->execute(['ft' => $ft_query, 'q' => $search_term]);
            } else {
                $stmt = $pdo->prepare("SELECT id, tanggal, regu, shift, kg_produksi, target_produksi FROM mbos_regu WHERE regu LIKE :q OR tanggal LIKE :q ORDER BY tanggal DESC LIMIT 15");
                $stmt->execute(['q' => $search_term]);
            }
            $hasil_corr = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { }
    }

    // 1c. Modul Produksi Flexo (Akses: prod_lap, prod_rek, dash_flexo)
    if (in_array('prod_lap', $user_akses) || in_array('prod_rek', $user_akses) || in_array('dash_flexo', $user_akses) || $is_admin) {
        try {
            $stmt = $pdo->prepare("SELECT id, tanggal, inline_pcs, stacker_pcs, stitch_auto_pcs, glue_pcs FROM db_flexo_prod WHERE tanggal LIKE :q ORDER BY tanggal DESC LIMIT 15");
            $stmt->execute(['q' => $search_term]);
            $hasil_flexo = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { }
    }

    // 2. Modul HRD (Akses: hrd_data)
    if (in_array('hrd_data', $user_akses) || $is_admin) {
        try {
            if ($is_fulltext) {
                $stmt = $pdo->prepare("SELECT id, no_id, nama, bagian, posisi, ket_status FROM db_karyawan_h2 WHERE MATCH(nama, no_id, posisi) AGAINST(:ft IN BOOLEAN MODE) LIMIT 15");
                $stmt->execute(['ft' => $ft_query]);
            } else {
                $stmt = $pdo->prepare("SELECT id, no_id, nama, bagian, posisi, ket_status FROM db_karyawan_h2 WHERE nama LIKE :q OR no_id LIKE :q OR posisi LIKE :q LIMIT 15");
                $stmt->execute(['q' => $search_term]);
            }
            $hasil_hrd = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { }
    }

    // 3. Modul Maintenance (Akses: mtc_sparepart)
    if (in_array('mtc_sparepart', $user_akses) || $is_admin) {
        try {
            if ($is_fulltext) {
                $stmt = $pdo->prepare("SELECT id, kode_part, nama_part, kategori, qty_stok, satuan, rak_lokasi FROM db_mtc_sparepart WHERE MATCH(kode_part, nama_part, kategori) AGAINST(:ft IN BOOLEAN MODE) LIMIT 15");
                $stmt->execute(['ft' => $ft_query]);
            } else {
                $stmt = $pdo->prepare("SELECT id, kode_part, nama_part, kategori, qty_stok, satuan, rak_lokasi FROM db_mtc_sparepart WHERE nama_part LIKE :q OR kode_part LIKE :q OR kategori LIKE :q LIMIT 15");
                $stmt->execute(['q' => $search_term]);
            }
            $hasil_mtc = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { }
    }
}

$page_title = "Pencarian Global — H2 BASE ERP";
$active_page = ""; // tidak ada sidebar aktif
require 'header.php';
?>

    <!-- STYLING KHUSUS UNTUK HALAMAN PENCARIAN -->
    <style>
        .search-hero { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); color: #0f172a; padding: 50px 20px; border-radius: 24px; margin-bottom: 30px; text-align: center; border: 1px solid #bae6fd; position: relative; overflow: hidden; }
        .search-hero::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.8) 0%, transparent 60%); pointer-events: none; }
        .search-input-wrapper { position: relative; max-width: 650px; margin: 0 auto; z-index: 10; }
        .search-input { width: 100%; padding: 22px 160px 22px 55px; font-size: 18px; border-radius: 100px; border: 2px solid #bae6fd; outline: none; box-sizing: border-box; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-weight:600; color:#0f172a; box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
        .search-input:focus { border-color: #0ea5e9; box-shadow: 0 0 0 6px rgba(14,165,233,0.2), 0 15px 35px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .search-icon { position: absolute; left: 22px; top: 50%; transform: translateY(-50%); font-size: 22px; color: #94a3b8; }
        .search-btn { position: absolute; right: 8px; top: 8px; bottom: 8px; background: #0ea5e9; color: white; border: none; padding: 0 35px; border-radius: 100px; font-weight: 700; font-size: 16px; cursor: pointer; transition: all 0.2s; }
        .search-btn:hover { background: #0284c7; transform: scale(1.02); }
        
        /* SEGMENTED TABS */
        .segmented-control { display: inline-flex; background: #ffffff; padding: 6px; border-radius: 100px; margin-top: 35px; position: relative; z-index: 10; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .seg-btn { background: transparent; border: none; color: #64748b; padding: 12px 28px; border-radius: 100px; font-size: 14px; font-weight: 700; cursor: pointer; transition: all 0.3s; position: relative; }
        .seg-btn:hover { color: #0f172a; }
        .seg-btn.active { background: #0ea5e9; color: white; box-shadow: 0 4px 15px rgba(14,165,233,0.3); }

        /* FLOATING CARDS */
        .result-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; padding: 0; }
        .result-card:hover { transform: translateY(-6px); box-shadow: 0 25px 50px -12px rgba(15,23,42,0.15) !important; border-color: #cbd5e1; }
        .result-card h3 { background: #f8fafc; margin: 0; padding: 18px 24px; border-bottom: 1px solid #e2e8f0; }
        .result-card table { margin: 0; border: none; }
    </style>

    <div class="search-hero">
        <h2 style="margin-top:0; font-size: 36px; font-weight: 900; letter-spacing: -1px; margin-bottom: 12px; color:#0c4a6e;">Pencarian Sistem</h2>
        <p style="color: #334155; font-size: 16px; margin-bottom: 40px; font-weight:500;">Telusuri seluruh data transaksi, stok, dan karyawan dalam satu tempat.</p>
        
        <form method="GET" action="global_search.php" class="search-input-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" name="q" class="search-input" value="<?= htmlspecialchars($q) ?>" placeholder="Lacak Baut, Karyawan, atau SPK Produksi..." autocomplete="off" autofocus>
            <button type="submit" class="search-btn">Telusuri</button>
        </form>

        <?php if ($q !== ''): ?>
        <div class="segmented-control" id="filterPills">
            <button class="seg-btn active" onclick="filterResults('all', this)">Semua Modul</button>
            <button class="seg-btn" onclick="filterResults('produksi', this)">🏭 Produksi</button>
            <button class="seg-btn" onclick="filterResults('hrd', this)">👥 HRD</button>
            <button class="seg-btn" onclick="filterResults('mtc', this)">🛠️ MTC</button>
        </div>
        
        <script>
            function filterResults(mod, btn) {
                document.querySelectorAll('.seg-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                let visibleCount = 0;
                document.querySelectorAll('.result-card').forEach(card => {
                    if (mod === 'all' || card.getAttribute('data-mod') === mod) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                const emptyState = document.getElementById('filterEmptyState');
                if (visibleCount === 0) {
                    if (!emptyState) {
                        const wrap = document.getElementById('resultsWrapper');
                        wrap.insertAdjacentHTML('beforeend', `<div id="filterEmptyState" style="text-align: center; padding: 80px 20px; color: #64748b; background: linear-gradient(to bottom, #ffffff, #f8fafc); border: 2px dashed #cbd5e1; border-radius:24px; margin-top:20px; box-shadow: inset 0 0 20px rgba(0,0,0,0.02);"><div style="font-size:64px; margin-bottom:20px; filter: drop-shadow(0 10px 10px rgba(0,0,0,0.1));">🏜️</div><h3 style="color:#0f172a; margin:0; font-size:24px; font-weight:800;">Area Kosong</h3><p style="margin:10px 0 0 0; font-size:16px;">Tidak ada hasil yang sesuai dengan kategori ini.</p></div>`);
                    } else {
                        emptyState.style.display = 'block';
                    }
                } else if (emptyState) {
                    emptyState.style.display = 'none';
                }
            }
        </script>
        <?php endif; ?>
    </div>

<?php if ($q !== ''): ?>
    <div style="margin-bottom: 20px; font-size: 14px; color: #475569; display: flex; justify-content: space-between; align-items: center;">
        <span>Menampilkan hasil pencarian untuk: <strong style="color: #0f172a; font-size: 16px;">"<?= htmlspecialchars($q) ?>"</strong></span>
        <?php 
            $total = count($hasil_produksi) + count($hasil_corr) + count($hasil_flexo) + count($hasil_hrd) + count($hasil_mtc); 
            if ($total == 0) {
                echo '<span class="badge-nonaktif" style="font-size:13px; padding:6px 12px;">Data Tidak Ditemukan</span>';
            } else {
                echo '<span class="badge-aktif" style="font-size:13px; padding:6px 12px; background:#dcfce7; color:#15803d;">Ditemukan '.$total.' Data Terkait</span>';
            }
        ?>
    </div>

    <!-- TAMPILAN EMPTY STATE (KOSONG) YANH LEBIH INDAH -->
    <?php if ($total == 0): ?>
        <div style="text-align: center; padding: 80px 20px; background: linear-gradient(to bottom, #ffffff, #f8fafc); border-radius: 24px; border: 2px dashed #e2e8f0; box-shadow: inset 0 0 20px rgba(0,0,0,0.02);">
            <div style="font-size: 72px; margin-bottom: 24px; filter: drop-shadow(0 10px 10px rgba(0,0,0,0.1));">🛸</div>
            <h3 style="color: #0f172a; font-size: 28px; margin-top: 0; margin-bottom: 16px; font-weight: 900; letter-spacing: -0.5px;">Data Tidak Ditemukan</h3>
            <p style="color: #64748b; font-size: 16px; max-width: 500px; margin: 0 auto 30px auto; line-height: 1.6;">Sistem tidak mendeteksi satupun data yang cocok dengan kata kunci <strong style="color:#0f172a; background:#f1f5f9; padding:2px 8px; border-radius:6px;">"<?= htmlspecialchars($q) ?>"</strong>. Silakan periksa kembali ejaan atau gunakan kata kunci lain.</p>
            <a href="global_search.php" class="btn-submit-modern" style="padding: 14px 28px; font-size: 15px; font-weight: 700; background: #0ea5e9 !important; text-decoration:none; border-radius:100px; box-shadow: 0 10px 20px rgba(14,165,233,0.2);">🔄 Ulangi Pencarian</a>
        </div>
    <?php endif; ?>

    <div style="display: grid; gap: 24px;" id="resultsWrapper">

        <!-- HASIL CORRUGATOR -->
        <?php if (count($hasil_corr) > 0 && (in_array('prod_lap', $user_akses) || in_array('prod_rek', $user_akses) || in_array('dash_corr', $user_akses) || $is_admin)): ?>
            <div class="card result-card" data-mod="produksi">
                <h3 style="display: flex; justify-content: space-between; align-items:center; font-size: 17px; font-weight: 800; color: #0f172a;">
                    <span>🏭 Hasil Produksi Corrugator (Mesin Besar)</span>
                    <div>
                        <button onclick="exportTableToExcel('tableCorr', 'Data_Corrugator_<?= date('Ymd') ?>')" class="btn-submit-modern" style="padding:4px 10px; font-size:11px; background:#10b981 !important; margin-right:8px; border-radius:6px; box-shadow:none;">⬇️ Excel</button>
                        <span style="font-size:12px; font-weight:normal; background:#f1f5f9; padding:4px 8px; border-radius:6px; color:#475569; border:1px solid #e2e8f0;"><?= count($hasil_corr) ?> Baris</span>
                    </div>
                </h3>
                <div style="overflow-x: auto;">
                    <table class="table-premium" id="tableCorr">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Regu / Shift</th>
                                <th>Capaian KG</th>
                                <th>Target KG</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($hasil_corr as $row): ?>
                            <tr>
                                <td><strong><?= highlightKeyword($row['tanggal'], $q) ?></strong></td>
                                <td>Regu <?= highlightKeyword($row['regu'], $q) ?> / Shift <?= htmlspecialchars($row['shift']) ?></td>
                                <td><span style="font-weight: 800; color: #10b981;"><?= number_format($row['kg_produksi']) ?></span> KG</td>
                                <td><?= number_format($row['target_produksi']) ?> KG</td>
                                <td>
                                    <a href="laporan.php?edit_id=<?= $row['id'] ?>" class="btn-submit-modern" style="padding: 6px 14px; font-size: 12px; background: #0ea5e9 !important; text-decoration:none; box-shadow:none;">Lihat Laporan</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- HASIL FLEXO -->
        <?php if (count($hasil_flexo) > 0 && (in_array('prod_lap', $user_akses) || in_array('prod_rek', $user_akses) || in_array('dash_flexo', $user_akses) || $is_admin)): ?>
            <div class="card result-card" data-mod="produksi">
                <h3 style="display: flex; justify-content: space-between; align-items:center; font-size: 17px; font-weight: 800; color: #0f172a;">
                    <span>🏭 Hasil Produksi Flexo (Mesin Cetak)</span>
                    <div>
                        <button onclick="exportTableToExcel('tableFlexo', 'Data_Flexo_<?= date('Ymd') ?>')" class="btn-submit-modern" style="padding:4px 10px; font-size:11px; background:#10b981 !important; margin-right:8px; border-radius:6px; box-shadow:none;">⬇️ Excel</button>
                        <span style="font-size:12px; font-weight:normal; background:#f1f5f9; padding:4px 8px; border-radius:6px; color:#475569; border:1px solid #e2e8f0;"><?= count($hasil_flexo) ?> Baris</span>
                    </div>
                </h3>
                <div style="overflow-x: auto;">
                    <table class="table-premium" id="tableFlexo">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Inline (Pcs)</th>
                                <th>Stacker (Pcs)</th>
                                <th>Stitch (Pcs)</th>
                                <th>Glue (Pcs)</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($hasil_flexo as $row): ?>
                            <tr>
                                <td><strong><?= highlightKeyword($row['tanggal'], $q) ?></strong></td>
                                <td><?= number_format($row['inline_pcs']) ?></td>
                                <td><?= number_format($row['stacker_pcs']) ?></td>
                                <td><?= number_format($row['stitch_auto_pcs']) ?></td>
                                <td><?= number_format($row['glue_pcs']) ?></td>
                                <td>
                                    <a href="produktifitas_flexo.php?edit=<?= $row['id'] ?>" class="btn-submit-modern" style="padding: 6px 14px; font-size: 12px; background: #0ea5e9 !important; text-decoration:none; box-shadow:none;">Detail Flexo</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- HASIL PRODUKSI (NC) -->
        <?php if (count($hasil_produksi) > 0 && (in_array('prod_lap', $user_akses) || in_array('prod_rek', $user_akses) || in_array('dash_nc', $user_akses) || $is_admin)): ?>
            <div class="card result-card" data-mod="produksi">
                <h3 style="display: flex; justify-content: space-between; align-items:center; font-size: 17px; font-weight: 800; color: #0f172a;">
                    <span>🏭 Hasil Produksi Slitter (NC / Mesin Potong)</span>
                    <div>
                        <button onclick="exportTableToExcel('tableNC', 'Data_NC_<?= date('Ymd') ?>')" class="btn-submit-modern" style="padding:4px 10px; font-size:11px; background:#10b981 !important; margin-right:8px; border-radius:6px; box-shadow:none;">⬇️ Excel</button>
                        <span style="font-size:12px; font-weight:normal; background:#f1f5f9; padding:4px 8px; border-radius:6px; color:#475569; border:1px solid #e2e8f0;"><?= count($hasil_produksi) ?> Baris</span>
                    </div>
                </h3>
                <div style="overflow-x: auto;">
                    <table class="table-premium" id="tableNC">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Shift</th>
                                <th>MO (SPK)</th>
                                <th>Customer</th>
                                <th>Jenis</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($hasil_produksi as $row): ?>
                            <tr>
                                <td><?= highlightKeyword($row['tanggal'], $q) ?></td>
                                <td><span style="background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:4px; font-weight:bold; font-size:12px;">S-<?= htmlspecialchars($row['shift']) ?></span></td>
                                <td><strong><?= highlightKeyword($row['mo'], $q) ?></strong></td>
                                <td><?= highlightKeyword($row['customer'], $q) ?></td>
                                <td><?= htmlspecialchars($row['jenis_produksi']) ?></td>
                                <td>
                                    <a href="produksi_nc.php?tgl=<?= $row['tanggal'] ?>&edit=<?= $row['id'] ?>" class="btn-submit-modern" style="padding: 6px 14px; font-size: 12px; background: #0ea5e9 !important; text-decoration:none; box-shadow:none;">Lihat / Edit</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- HASIL HRD -->
        <?php if (count($hasil_hrd) > 0 && (in_array('hrd_data', $user_akses) || $is_admin)): ?>
            <div class="card result-card" data-mod="hrd">
                <h3 style="display: flex; justify-content: space-between; align-items:center; font-size: 17px; font-weight: 800; color: #0f172a;">
                    <span>👥 Database Karyawan</span>
                    <div>
                        <button onclick="exportTableToExcel('tableHRD', 'Data_HRD_<?= date('Ymd') ?>')" class="btn-submit-modern" style="padding:4px 10px; font-size:11px; background:#10b981 !important; margin-right:8px; border-radius:6px; box-shadow:none;">⬇️ Excel</button>
                        <span style="font-size:12px; font-weight:normal; background:#f1f5f9; padding:4px 8px; border-radius:6px; color:#475569; border:1px solid #e2e8f0;"><?= count($hasil_hrd) ?> Pegawai</span>
                    </div>
                </h3>
                <div style="overflow-x: auto;">
                    <table class="table-premium" id="tableHRD">
                        <thead>
                            <tr>
                                <th>ID Pegawai</th>
                                <th>Nama Lengkap</th>
                                <th>Bagian</th>
                                <th>Posisi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($hasil_hrd as $row): ?>
                            <tr>
                                <td><?= highlightKeyword($row['no_id'], $q) ?></td>
                                <td><strong><?= highlightKeyword($row['nama'], $q) ?></strong></td>
                                <td><?= htmlspecialchars($row['bagian']) ?></td>
                                <td><?= highlightKeyword($row['posisi'], $q) ?></td>
                                <td>
                                    <?php if($row['ket_status'] == 'Aktif'): ?>
                                        <span class="badge-aktif">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge-nonaktif"><?= htmlspecialchars($row['ket_status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="karyawan_data.php?edit=<?= $row['id'] ?>" class="btn-submit-modern" style="padding: 6px 14px; font-size: 12px; background: #0ea5e9 !important; text-decoration:none; box-shadow:none;">Profil HRD</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- HASIL MAINTENANCE -->
        <?php if (count($hasil_mtc) > 0 && (in_array('mtc_sparepart', $user_akses) || $is_admin)): ?>
            <div class="card result-card" data-mod="mtc">
                <h3 style="display: flex; justify-content: space-between; align-items:center; font-size: 17px; font-weight: 800; color: #0f172a;">
                    <span>⚙️ Inventori Suku Cadang (Sparepart)</span>
                    <div>
                        <button onclick="exportTableToExcel('tableMTC', 'Data_Sparepart_<?= date('Ymd') ?>')" class="btn-submit-modern" style="padding:4px 10px; font-size:11px; background:#10b981 !important; margin-right:8px; border-radius:6px; box-shadow:none;">⬇️ Excel</button>
                        <span style="font-size:12px; font-weight:normal; background:#f1f5f9; padding:4px 8px; border-radius:6px; color:#475569; border:1px solid #e2e8f0;"><?= count($hasil_mtc) ?> Part</span>
                    </div>
                </h3>
                <div style="overflow-x: auto;">
                    <table class="table-premium" id="tableMTC">
                        <thead>
                            <tr>
                                <th>Kode Part</th>
                                <th>Nama Part</th>
                                <th>Kategori</th>
                                <th>Sisa Stok</th>
                                <th>Lokasi Rak</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($hasil_mtc as $row): ?>
                            <tr>
                                <td><?= highlightKeyword($row['kode_part'], $q) ?></td>
                                <td><strong><?= highlightKeyword($row['nama_part'], $q) ?></strong></td>
                                <td><?= highlightKeyword($row['kategori'], $q) ?></td>
                                <td>
                                    <span style="font-size: 16px; font-weight: 800; color: #0ea5e9;"><?= number_format($row['qty_stok']) ?></span>
                                    <span style="font-size: 12px; color: #64748b;"><?= htmlspecialchars($row['satuan']) ?></span>
                                </td>
                                <td><span style="background:#f8fafc; border:1px solid #e2e8f0; padding:2px 6px; border-radius:4px; font-size:12px;"><?= htmlspecialchars($row['rak_lokasi']) ?></span></td>
                                <td>
                                    <a href="mtc_sparepart.php?edit=<?= $row['id'] ?>" class="btn-submit-modern" style="padding: 6px 14px; font-size: 12px; background: #0ea5e9 !important; text-decoration:none; box-shadow:none;">Update Stok</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
<?php endif; ?>

<?php require 'footer.php'; ?>

<script>
function exportTableToExcel(tableID, filename = ''){
    let downloadLink;
    let dataType = 'application/vnd.ms-excel';
    let tableSelect = document.getElementById(tableID);
    
    // Hapus kolom "Aksi" dari hasil ekspor dengan clone
    let tableClone = tableSelect.cloneNode(true);
    let rows = tableClone.rows;
    for (let i = 0; i < rows.length; i++) {
        rows[i].deleteCell(-1); // Hapus kolom terakhir (Aksi)
    }

    let tableHTML = tableClone.outerHTML.replace(/ /g, '%20');
    
    filename = filename?filename+'.xls':'excel_data.xls';
    downloadLink = document.createElement("a");
    document.body.appendChild(downloadLink);
    
    if(navigator.msSaveOrOpenBlob){
        let blob = new Blob(['\ufeff', tableHTML], {
            type: dataType
        });
        navigator.msSaveOrOpenBlob( blob, filename);
    }else{
        downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
        downloadLink.download = filename;
        downloadLink.click();
    }
}
</script>

<?php require_once 'footer.php'; ?>
