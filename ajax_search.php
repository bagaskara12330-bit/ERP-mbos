<?php
require_once 'auth.php';

$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 2) {
    echo ""; // Minimal 2 karakter agar server tidak berat
    exit();
}

$search_term = "%$q%";
$html = "";

// Hybrid FULLTEXT Logic
$is_fulltext = strlen($q) >= 3;
$ft_query = "";
if ($is_fulltext) {
    $words = explode(' ', $q);
    foreach($words as $w) {
        $w = trim($w);
        if ($w !== '') {
            // Hilangkan karakter aneh yang bisa bikin SQL Error di BOOLEAN MODE
            $w = preg_replace('/[^a-zA-Z0-9_]/', '', $w);
            if ($w !== '') {
                $ft_query .= "+" . $w . "* ";
            }
        }
    }
    $ft_query = trim($ft_query);
    // Jika ternyata hasil filter kata kosong, fallback ke LIKE
    if ($ft_query === "") {
        $is_fulltext = false;
    }
}

function highlightAjax($text, $keyword) {
    if ($keyword === '') return htmlspecialchars($text);
    $text = htmlspecialchars($text);
    return preg_replace('/(' . preg_quote(htmlspecialchars($keyword), '/') . ')/i', '<strong style="color:#0ea5e9;">$1</strong>', $text);
}

// 1. Modul NC
if (in_array('prod_lap', $user_akses) || in_array('prod_rek', $user_akses) || in_array('dash_nc', $user_akses) || $is_admin) {
    try {
        if ($is_fulltext) {
            $stmt = $pdo->prepare("SELECT id, tanggal, mo, customer FROM db_produksi_nc WHERE MATCH(mo, customer) AGAINST(:ft IN BOOLEAN MODE) OR tanggal LIKE :q ORDER BY tanggal DESC LIMIT 3");
            $stmt->execute(['ft' => $ft_query, 'q' => $search_term]);
        } else {
            $stmt = $pdo->prepare("SELECT id, tanggal, mo, customer FROM db_produksi_nc WHERE mo LIKE :q OR customer LIKE :q OR tanggal LIKE :q ORDER BY tanggal DESC LIMIT 3");
            $stmt->execute(['q' => $search_term]);
        }
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($res) > 0) {
            $html .= "<div class='ajax-group'>Mesin Slitter (NC)</div>";
            foreach($res as $r) {
                $html .= "<a href='produksi_nc.php?edit={$r['id']}' class='ajax-item'>
                            <span class='ajax-icon'>✂️</span>
                            <div class='ajax-text'>
                                <div class='ajax-title'>".highlightAjax($r['mo'], $q)." - ".highlightAjax($r['customer'], $q)."</div>
                                <div class='ajax-subtitle'>Tgl: {$r['tanggal']}</div>
                            </div>
                          </a>";
            }
        }
    } catch (PDOException $e) { }
}

// 2. Modul Corrugator
if (in_array('prod_lap', $user_akses) || in_array('prod_rek', $user_akses) || in_array('dash_corr', $user_akses) || $is_admin) {
    try {
        if ($is_fulltext) {
            $stmt = $pdo->prepare("SELECT id, tanggal, regu FROM mbos_regu WHERE MATCH(regu) AGAINST(:ft IN BOOLEAN MODE) OR tanggal LIKE :q ORDER BY tanggal DESC LIMIT 3");
            $stmt->execute(['ft' => $ft_query, 'q' => $search_term]);
        } else {
            $stmt = $pdo->prepare("SELECT id, tanggal, regu FROM mbos_regu WHERE regu LIKE :q OR tanggal LIKE :q ORDER BY tanggal DESC LIMIT 3");
            $stmt->execute(['q' => $search_term]);
        }
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($res) > 0) {
            $html .= "<div class='ajax-group'>Corrugator</div>";
            foreach($res as $r) {
                $html .= "<a href='laporan.php?edit={$r['id']}' class='ajax-item'>
                            <span class='ajax-icon'>🏭</span>
                            <div class='ajax-text'>
                                <div class='ajax-title'>Regu: ".highlightAjax($r['regu'], $q)."</div>
                                <div class='ajax-subtitle'>Tgl: {$r['tanggal']}</div>
                            </div>
                          </a>";
            }
        }
    } catch (PDOException $e) { }
}

// 3. Modul Flexo
if (in_array('prod_lap', $user_akses) || in_array('prod_rek', $user_akses) || in_array('dash_flexo', $user_akses) || $is_admin) {
    try {
        $stmt = $pdo->prepare("SELECT id, tanggal FROM db_flexo_prod WHERE tanggal LIKE :q ORDER BY tanggal DESC LIMIT 3");
        $stmt->execute(['q' => $search_term]);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($res) > 0) {
            $html .= "<div class='ajax-group'>Mesin Flexo</div>";
            foreach($res as $r) {
                $html .= "<a href='produktifitas_flexo.php?edit={$r['id']}' class='ajax-item'>
                            <span class='ajax-icon'>🖨️</span>
                            <div class='ajax-text'>
                                <div class='ajax-title'>Produksi Tgl: ".highlightAjax($r['tanggal'], $q)."</div>
                            </div>
                          </a>";
            }
        }
    } catch (PDOException $e) { }
}

// 4. Modul HRD
if (in_array('hrd_data', $user_akses) || $is_admin) {
    try {
        if ($is_fulltext) {
            $stmt = $pdo->prepare("SELECT id, no_id, nama, posisi FROM db_karyawan_h2 WHERE MATCH(nama, no_id, posisi) AGAINST(:ft IN BOOLEAN MODE) LIMIT 3");
            $stmt->execute(['ft' => $ft_query]);
        } else {
            $stmt = $pdo->prepare("SELECT id, no_id, nama, posisi FROM db_karyawan_h2 WHERE nama LIKE :q OR no_id LIKE :q OR posisi LIKE :q LIMIT 3");
            $stmt->execute(['q' => $search_term]);
        }
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($res) > 0) {
            $html .= "<div class='ajax-group'>HRD & Karyawan</div>";
            foreach($res as $r) {
                $html .= "<a href='karyawan_data.php?edit={$r['id']}' class='ajax-item'>
                            <span class='ajax-icon'>👤</span>
                            <div class='ajax-text'>
                                <div class='ajax-title'>".highlightAjax($r['nama'], $q)." ({$r['no_id']})</div>
                                <div class='ajax-subtitle'>Posisi: ".highlightAjax($r['posisi'], $q)."</div>
                            </div>
                          </a>";
            }
        }
    } catch (PDOException $e) { }
}

// 5. Modul MTC
if (in_array('mtc_sparepart', $user_akses) || $is_admin) {
    try {
        if ($is_fulltext) {
            $stmt = $pdo->prepare("SELECT id, kode_part, nama_part, qty_stok FROM db_mtc_sparepart WHERE MATCH(kode_part, nama_part, kategori) AGAINST(:ft IN BOOLEAN MODE) LIMIT 3");
            $stmt->execute(['ft' => $ft_query]);
        } else {
            $stmt = $pdo->prepare("SELECT id, kode_part, nama_part, qty_stok FROM db_mtc_sparepart WHERE nama_part LIKE :q OR kode_part LIKE :q OR kategori LIKE :q LIMIT 3");
            $stmt->execute(['q' => $search_term]);
        }
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($res) > 0) {
            $html .= "<div class='ajax-group'>Maintenance (MTC)</div>";
            foreach($res as $r) {
                $html .= "<a href='mtc_sparepart.php?edit={$r['id']}' class='ajax-item'>
                            <span class='ajax-icon'>⚙️</span>
                            <div class='ajax-text'>
                                <div class='ajax-title'>".highlightAjax($r['nama_part'], $q)."</div>
                                <div class='ajax-subtitle'>Kode: ".highlightAjax($r['kode_part'], $q)." | Stok: <strong>{$r['qty_stok']}</strong></div>
                            </div>
                          </a>";
            }
        }
    } catch (PDOException $e) { }
}

if ($html === "") {
    echo "<div style='padding: 20px; text-align: center; color: #64748b; font-size: 13px;'>
            <i>🔍 Tidak ditemukan hasil untuk \"".htmlspecialchars($q)."\". Coba kata kunci lain.</i>
          </div>";
} else {
    echo $html;
    echo "<a href='global_search.php?q=".urlencode($q)."' class='ajax-see-all'>Tampilkan Semua Hasil...</a>";
}
?>
