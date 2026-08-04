<?php
require_once 'auth.php';
require_akses('prod_slitter');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false;

// 🚀 AUTO-PATCH DAN RE-CREATE DATABASE (MURNI BERSIH TANPA PARAMETER OPERATOR LOGISTIK)
try {
    $cek_tabel = $pdo->query("SHOW TABLES LIKE 'db_produksi_nc'");
    if ($cek_tabel->rowCount() > 0) {
        $cek_kolom = $pdo->query("SHOW COLUMNS FROM db_produksi_nc LIKE 'trma_sheet_pcs'");
        if ($cek_kolom->rowCount() > 0) {
            $pdo->exec("DROP TABLE db_produksi_nc");
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS db_produksi_nc (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATE NOT NULL,
        shift VARCHAR(10) NOT NULL,
        jenis_produksi VARCHAR(20) NOT NULL,
        mo VARCHAR(50) NOT NULL,
        customer VARCHAR(150) NOT NULL,
        lebar INT NOT NULL,
        
        s1_j VARCHAR(10), s1_g INT DEFAULT 0,
        s2_j VARCHAR(10), s2_g INT DEFAULT 0,
        s3_j VARCHAR(10), s3_g INT DEFAULT 0,
        s4_j VARCHAR(10), s4_g INT DEFAULT 0,
        s5_j VARCHAR(10), s5_g INT DEFAULT 0,
        s6_j VARCHAR(10), s6_g INT DEFAULT 0,
        s7_j VARCHAR(10), s7_g INT DEFAULT 0,

        ukuran_p INT NOT NULL,
        ukuran_l INT NOT NULL,
        order_customer INT DEFAULT 0,
        hasil_counter INT DEFAULT 0,
        
        m2_pcs DECIMAL(10,6) DEFAULT 0.000000,
        kg_pcs DECIMAL(10,6) DEFAULT 0.000000,
        or_l_rm DECIMAL(12,4) DEFAULT 0.0000,
        or_m2 DECIMAL(12,4) DEFAULT 0.0000,
        out_val INT DEFAULT 0,
        total_kg DECIMAL(12,4) DEFAULT 0.0000,
        
        keterangan VARCHAR(200),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) { die("Error DB: " . $e->getMessage()); }

$tgl_filter = isset($_GET['tgl']) ? $_GET['tgl'] : date('Y-m-d');
$shift_filter = isset($_GET['shift_filter']) ? $_GET['shift_filter'] : 'ALL'; // 🚀 TAMBAHAN BARU: Tangkap Shift Filter

// 🚀 LOGIKA HAPUS DATA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id']) && $user_role != 'Viewer') {
    $id = intval($_POST['hapus_id']);
    $stmt_cek = $pdo->prepare("SELECT mo, customer FROM db_produksi_nc WHERE id=?"); 
    $stmt_cek->execute([$id]); 
    $row_del = $stmt_cek->fetch(PDO::FETCH_ASSOC);
    
    if ($pdo->prepare("DELETE FROM db_produksi_nc WHERE id = ?")->execute([$id])) {
        catatLog($pdo, $user_aktif, "Menghapus data Produksi NC MO {$row_del['mo']} Pelanggan {$row_del['customer']}.", "🗑️");
        header("Location: produksi_nc.php?pesan=hapus_sukses&tgl=" . $tgl_filter . "&shift_filter=" . $shift_filter); exit();
    }
}

// 🚀 NILAI DEFAULT FORM
$id_edit = ''; $tanggal = $tgl_filter; $shift = '1'; $jenis_produksi = 'BOX'; $mo = ''; $customer = ''; 
$lebar = ''; $ukuran_p = ''; $ukuran_l = ''; $order_customer = ''; $hasil_counter = ''; $keterangan = '';
$s1_j=''; $s1_g=''; $s2_j=''; $s2_g=''; $s3_j=''; $s3_g=''; $s4_j=''; $s4_g=''; 
$s5_j=''; $s5_g=''; $s6_j=''; $s6_g=''; $s7_j=''; $s7_g='';

// 🚀 TANGKAP DATA UNTUK EDIT
if (isset($_GET['edit']) && $user_role != 'Viewer') {
    $is_edit = true; $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM db_produksi_nc WHERE id = ?"); $stmt->execute([$id_edit]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $tanggal = $row['tanggal']; $shift = $row['shift']; $jenis_produksi = $row['jenis_produksi']; 
        $mo = $row['mo']; $customer = $row['customer'];
        $lebar = $row['lebar']; $ukuran_p = $row['ukuran_p']; $ukuran_l = $row['ukuran_l']; 
        $order_customer = $row['order_customer']; $hasil_counter = $row['hasil_counter']; 
        $keterangan = $row['keterangan'];
        
        $s1_j=$row['s1_j']; $s1_g=$row['s1_g']!=0?$row['s1_g']:''; 
        $s2_j=$row['s2_j']; $s2_g=$row['s2_g']!=0?$row['s2_g']:''; 
        $s3_j=$row['s3_j']; $s3_g=$row['s3_g']!=0?$row['s3_g']:''; 
        $s4_j=$row['s4_j']; $s4_g=$row['s4_g']!=0?$row['s4_g']:''; 
        $s5_j=$row['s5_j']; $s5_g=$row['s5_g']!=0?$row['s5_g']:''; 
        $s6_j=$row['s6_j']; $s6_g=$row['s6_g']!=0?$row['s6_g']:''; 
        $s7_j=$row['s7_j']; $s7_g=$row['s7_g']!=0?$row['s7_g']:'';
    }
}

// 🚀 LOGIKA SIMPAN & UPDATE (RUMUS SINKRONISASI MUTLAK)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_nc']) && $user_role != 'Viewer') {
    $post_id = $_POST['id_edit'] ?? ''; 
    $p_tgl = $_POST['tanggal']; $p_shift = $_POST['shift']; $p_jenis = strtoupper(trim($_POST['jenis_produksi']));
    $p_mo = strtoupper(trim($_POST['mo'])); $p_cust = strtoupper(trim($_POST['customer']));
    
    $p_lebar = intval($_POST['lebar'] ?? 0); $p_up = intval($_POST['ukuran_p'] ?? 0); $p_ul = intval($_POST['ukuran_l'] ?? 0); 
    $p_order = intval($_POST['order_customer'] ?? 0); $p_hasil = intval($_POST['hasil_counter'] ?? 0);
    $p_transform_ket = trim($_POST['keterangan'] ?? '');

    $ps1_j = strtoupper(trim($_POST['s1_j'] ?? '')); $ps1_g = intval($_POST['s1_g'] ?? 0);
    $ps2_j = strtoupper(trim($_POST['s2_j'] ?? '')); $ps2_g = intval($_POST['s2_g'] ?? 0);
    $ps3_j = strtoupper(trim($_POST['s3_j'] ?? '')); $ps3_g = intval($_POST['s3_g'] ?? 0);
    $ps4_j = strtoupper(trim($_POST['s4_j'] ?? '')); $ps4_g = intval($_POST['s4_g'] ?? 0);
    $ps5_j = strtoupper(trim($_POST['s5_j'] ?? '')); $ps5_g = intval($_POST['s5_g'] ?? 0);
    $ps6_j = strtoupper(trim($_POST['s6_j'] ?? '')); $ps6_g = intval($_POST['s6_g'] ?? 0);
    $ps7_j = strtoupper(trim($_POST['s7_j'] ?? '')); $ps7_g = intval($_POST['s7_g'] ?? 0);

    // 🧮 FORMULA M2 & OUT
    $m2_pcs = ($p_up * $p_ul) / 1000000;
    $out_val = $p_ul > 0 ? floor($p_lebar / $p_ul) : 0; 
    
    // 🚀 LOGIKA TAKE UP RATIO (TUR) HARMONIS
    $berat_g1 = $ps1_g;
    $berat_g2 = $ps2_g * 1.40; // B-Flute
    $berat_g3 = $ps3_g;
    $berat_g4 = $ps4_g * 1.50; // C-Flute
    $berat_g5 = $ps5_g;
    $berat_g6 = $ps6_g * 1.25; // E-Flute
    $berat_g7 = $ps7_g;
    
    $total_gsm = $berat_g1 + $berat_g2 + $berat_g3 + $berat_g4 + $berat_g5 + $berat_g6 + $berat_g7;
    $p_kgpcs = ($total_gsm * $m2_pcs) / 1000;

    $or_l_rm = $out_val > 0 ? ($p_up * $p_hasil / 1000 / $out_val) : 0;
    $or_m2 = ($p_hasil * $m2_pcs) / 1000;
    $total_kg = $p_hasil * $p_kgpcs;

    $params = [
        $p_tgl, $p_shift, $p_jenis, $p_mo, $p_cust, $p_lebar, 
        $ps1_j, $ps1_g, $ps2_j, $ps2_g, $ps3_j, $ps3_g, $ps4_j, $ps4_g, $ps5_j, $ps5_g, $ps6_j, $ps6_g, $ps7_j, $ps7_g,
        $p_up, $p_ul, $p_order, $p_hasil, 
        $m2_pcs, $p_kgpcs, $or_l_rm, $or_m2, $out_val, $total_kg, $p_transform_ket
    ];

    try {
        if (!empty($post_id)) {
            $sql = "UPDATE db_produksi_nc SET 
                    tanggal=?, shift=?, jenis_produksi=?, mo=?, customer=?, lebar=?, 
                    s1_j=?, s1_g=?, s2_j=?, s2_g=?, s3_j=?, s3_g=?, s4_j=?, s4_g=?, s5_j=?, s5_g=?, s6_j=?, s6_g=?, s7_j=?, s7_g=?,
                    ukuran_p=?, ukuran_l=?, order_customer=?, hasil_counter=?, 
                    m2_pcs=?, kg_pcs=?, or_l_rm=?, or_m2=?, out_val=?, total_kg=?, keterangan=? 
                    WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $params[] = $post_id; 
            $stmt->execute($params);

            catatLog($pdo, $user_aktif, "Mengupdate Laporan NC ($p_jenis) MO $p_mo.", "✏️");
            header("Location: produksi_nc.php?pesan=edit_sukses&tgl=$p_tgl&shift_filter=$shift_filter"); exit();
        } else {
            $sql = "INSERT INTO db_produksi_nc (
                    tanggal, shift, jenis_produksi, mo, customer, lebar, 
                    s1_j, s1_g, s2_j, s2_g, s3_j, s3_g, s4_j, s4_g, s5_j, s5_g, s6_j, s6_g, s7_j, s7_g,
                    ukuran_p, ukuran_l, order_customer, hasil_counter, 
                    m2_pcs, kg_pcs, or_l_rm, or_m2, out_val, total_kg, keterangan) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            catatLog($pdo, $user_aktif, "Merekam Laporan NC Baru ($p_jenis) MO $p_mo.", "✂️");
            header("Location: produksi_nc.php?pesan=simpan_sukses&tgl=$p_tgl&shift_filter=$shift_filter"); exit();
        }
    } catch (Exception $e) {
        die("<div style='background:#fef2f2; color:#991b1b; padding:20px; font-family:sans-serif;'><h3>Terjadi Kesalahan Sistem Saat Menyimpan Data:</h3><p>" . $e->getMessage() . "</p></div>");
    }
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'simpan_sukses') $pesan = "<div class='alert alert-success'>🎉 Data Laporan Produksi NC Berhasil Disimpan!</div>";
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success'>✏️ Perubahan Data Produksi NC Berhasil Diperbarui!</div>";
    if ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-danger'>🗑️ Data Laporan Berhasil Dihapus Permanen!</div>";
}

$page_title = "H2 BASE — Laporan NC"; $active_page = "produksi_nc";
require 'header.php';
?>

<style>
    /* 🚀 STYLE MODERN SAAS DNA SYSTEM */
    .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
    .form-grid-top { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
    .form-group { display: flex; flex-direction: column; min-width: 140px; margin-bottom: 16px;}
    .form-group label { margin-bottom: 8px; font-weight: 800; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;}
    
    input, select { 
        background: #ffffff !important; border: 1px solid #cbd5e1 !important; border-radius: 8px !important; 
        padding: 11px 14px !important; font-size: 13px !important; color: #0f172a !important; 
        font-weight: 600 !important; box-sizing: border-box !important; transition: all 0.2s ease-in-out !important; width: 100%;
    }
    input:focus, select:focus { border-color: #0ea5e9 !important; background: #ffffff !important; box-shadow: 0 0 0 3px rgba(14,165,233,0.15) !important; outline: none; }

    .admin-input { background: #f0f9ff !important; border: 1px solid #7dd3fc !important; color: #0369a1 !important; }
    .admin-input::placeholder { color: #bae6fd !important; font-weight: normal; }

    .subs-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; background: #f0f9ff; padding: 15px; border-radius: 10px; border: 1px dashed #7dd3fc; }
    .subs-item { display: flex; flex-direction: column; gap: 6px; align-items: center;}
    .subs-item span { font-size: 10px; font-weight: 800; color: #0284c7; text-transform: uppercase;}
    .subs-item input, .subs-item select { text-align: center; padding: 8px !important; text-align-last: center; cursor: pointer; }
    
    .btn-submit-modern { background: #10b981 !important; color: #ffffff !important; border: none !important; padding: 12px 32px !important; border-radius: 8px !important; font-size: 14px !important; font-weight: 800 !important; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; width: 100%; }
    .btn-submit-modern:hover { background: #059669 !important; transform: translateY(-1px) !important; }
    .btn-batal-modern { background: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important; padding: 11px 24px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700; text-align: center; display: inline-flex; align-items: center; justify-content: center; width: 100%; box-sizing: border-box; margin-top: 10px; }
    .btn-excel-modern { background: #16a34a !important; color: #ffffff !important; border: none !important; padding: 10px 20px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; cursor: pointer !important; transition: all 0.2s ease-in-out !important; height: 41px !important; box-sizing: border-box !important; display: inline-flex !important; align-items: center; }

    .alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 13px; font-weight: 700; text-align: center;}
    .alert-success { background-color: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .alert-danger { background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    .live-calc-box { display: flex; flex-wrap: wrap; gap: 12px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #cbd5e1; padding: 20px; border-radius: 12px; margin-bottom: 24px; font-size: 12px; color: #475569; align-items: center; justify-content: flex-start;}
    .live-calc-box > div { background: #ffffff; padding: 10px 16px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); font-weight: 700; display:flex; flex-direction:column; align-items: center; gap:4px; text-align:center; min-width: 140px;}
    .live-calc-box > div span { font-size: 18px; color: #0f172a; font-weight: 900 !important; }

    /* 🚀 WIDGET SUMMARY TOTAL BOX & SHEET */
    .widget-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .widget-card { border-radius: 12px; padding: 18px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    .widget-box { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-color: #bae6fd; }
    .widget-sheet { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-color: #bbf7d0; }
    .widget-title { margin-top: 0; font-size: 14px; font-weight: 900; padding-bottom: 10px; border-bottom: 2px solid rgba(0,0,0,0.1); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
    .w-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .w-item { background: #ffffff; padding: 10px; border-radius: 8px; text-align: center; border: 1px solid rgba(0,0,0,0.05); }
    .w-label { font-size: 10px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
    .w-val { font-size: 15px; font-weight: 900; color: #0f172a; }

    /* TABEL DATA SPREADSHEET 7 (STRICT CORPORATE DARK SLATE) */
    .table-responsive { background: white; border-radius: 10px; border: 1px solid #cbd5e1; overflow-y: auto; overflow-x: auto; max-height: 650px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.01); }
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12px; color: #0f172a; white-space: nowrap; min-width: 2000px; margin-bottom: 0; }
    .table-premium th, .table-premium td { padding: 12px 14px; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; vertical-align: middle; text-align: center; font-weight: 600; }
    
    .table-premium th { background-color: #0f172a !important; color: #ffffff !important; font-weight: 700; position: sticky; top: 0; z-index: 15; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 1px solid #334155; }
    .table-premium thead tr:nth-child(2) th { top: 39px; z-index: 14; border-bottom: 2px solid #0f172a; background-color: #1e293b !important; color: #ffffff !important; }
    
    .stk-1 { position: sticky; left: 0; z-index: 5; width: 40px; min-width: 40px; background: #fff;}
    .stk-2 { position: sticky; left: 40px; z-index: 5; width: 110px; min-width: 110px; background: #fff;}
    .stk-3 { position: sticky; left: 150px; z-index: 5; width: 180px; min-width: 180px; border-right: 3px solid #cbd5e1 !important; text-align: left !important; padding-left: 15px;}
    .table-premium th.stk-1, .table-premium th.stk-2, .table-premium th.stk-3 { background-color: #0f172a !important; color: #ffffff !important; z-index: 20; border-bottom: 1px solid #334155;}

    /* COLOR CODES VERTIKAL (Hanya base, ditimpa oleh Conditional Formatting) */
    td.col-ident { background-color: #f0f9ff; color: #0369a1; }
    td.col-subs  { background-color: #f5f3ff; color: #4c1d95; }
    td.col-ukur  { background-color: #f0fdf4; color: #15803d; }
    td.col-calc  { background-color: #fffbeb; color: #b45309; }

    /* 🚀 CONDITIONAL FORMATTING ROWS (WARNA SEBARIS MUTLAK) */
    tr.row-qc td { background-color: #fce7f3 !important; color: #be123c !important; border-bottom: 1px solid #f9a8d4 !important; }
    tr.row-salah td { background-color: #fee2e2 !important; color: #991b1b !important; border-bottom: 1px solid #fca5a5 !important; }
    tr.row-lanjutan td { background-color: #fef08a !important; color: #854d0e !important; border-bottom: 1px solid #fde047 !important; }
    tr.row-box td { background-color: #e0f2fe !important; color: #0369a1 !important; border-bottom: 1px solid #bae6fd !important; }
    tr.row-slitter td { background-color: #dcfce7 !important; color: #166534 !important; border-bottom: 1px solid #bbf7d0 !important; } /* Diubah ke Hijau */
    tr.row-sheet td { background-color: #ffffff !important; color: #334155 !important; }

    /* Hover effect agar tetap responsif */
    tr.row-qc:hover td { background-color: #fbcfe8 !important; }
    tr.row-salah:hover td { background-color: #fecaca !important; }
    tr.row-lanjutan:hover td { background-color: #fde047 !important; }
    tr.row-box:hover td { background-color: #bae6fd !important; }
    tr.row-slitter:hover td { background-color: #bbf7d0 !important; } /* Hover Hijau Terang */
    tr.row-sheet:hover td { background-color: #f1f5f9 !important; }
    
    /* 🚀 KUNCI STICKY BOTTOM UNTUK BARIS TOTAL MUTLAK */
    .total-row td { background-color: #cbd5e1 !important; font-weight: 800; color: #0f172a !important; border-top: 2px solid #0f172a !important; position: sticky; bottom: 0; z-index: 12; outline: 1px solid #cbd5e1;}
    .stk-total { position: sticky; left: 0; z-index: 22 !important; border-right: 2px solid #94a3b8 !important;}

    .btn-edit { display: inline-block; background: #ffffff; color: #0284c7; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; border: 1px solid #bae6fd; cursor: pointer;}
    .btn-edit:hover { background: #e0f2fe; color: #0369a1; }
    .btn-hapus { display: inline-block; background: #ffffff; color: #dc2626; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; border: 1px solid #fecaca; margin-left: 4px; cursor: pointer;}
    .btn-hapus:hover { background: #fee2e2; color: #b91c1c; }

    .form-toggle-header { user-select: none; transition: background 0.2s; padding: 12px 16px; border-radius: 8px; margin: -10px -10px 15px -10px; cursor: pointer; }
    .form-toggle-header:hover { background: #f8fafc; }

    @media (max-width: 992px) { .subs-grid { grid-template-columns: repeat(4, 1fr); } }
    @media (max-width: 768px) { .subs-grid { grid-template-columns: repeat(2, 1fr); } .btn-submit-modern, .btn-batal-modern, .btn-search-modern, .btn-excel-modern { width: 100%; justify-content: center; margin-bottom:10px;} }
</style>

<div class="card" style="border-top: 5px solid #0f172a; padding: 18px 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; width: 100%;">
        <div style="display: flex; gap: 10px; align-items: center;">
            <label style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase;">📅 JADWAL PRODUKSI NC TANGGAL:</label>
            <form method="GET" action="" style="margin: 0; display:flex; gap:10px;">
                <input type="date" name="tgl" value="<?= $tgl_filter ?>" onchange="this.form.submit()" style="width: auto; border-color: #0ea5e9; color: #0284c7; background: #f0f9ff !important; cursor: pointer; padding: 8px 12px !important;">
                <!-- 🚀 Menahan memori state dari widget agar tgl sinkron -->
                <input type="hidden" name="shift_filter" value="<?= htmlspecialchars($shift_filter) ?>">
            </form>
        </div>
    </div>
</div>

<?= $pesan ?>

<?php if ($user_role != 'Viewer'): ?>
<div class="card" id="cardInputArea" style="border-top: 5px solid #10b981;">
    <div class="form-toggle-header" onclick="toggleFormInput()">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid <?= $is_edit ? '#bae6fd' : '#a7f3d0' ?>; padding-bottom: 8px;">
            <h2 id="formTitle" style="margin:0; font-size: 17px; color: <?= $is_edit ? '#0284c7' : '#047857' ?>;">
                <?= $is_edit ? "✏️ Mode Edit Entri Data NC" : "✂️ Form Input Admin Produksi NC (Box/Sheet)" ?>
            </h2>
            <span id="formToggleIcon" style="font-size:13px; font-weight:900; color:#047857; background:#f0fdf4; padding:4px 10px; border-radius:6px; border:1px solid #bbf7d0;">
                ▼ KLIK UNTUK BUKA/TUTUP FORM
            </span>
        </div>
        <p style="font-size: 11px; color: #64748b; margin:6px 0 0 0; font-weight:600;">Sistem kini <span style="color:#0ea5e9; font-weight:900;">Auto-Calculate Kg/Pcs</span> berdasarkan Take-Up Ratio Flute (B/C/E).</p>
    </div>
    
    <div id="formCollapsibleContent" style="display: none; margin-top: 15px;">
        <form action="" method="POST" autocomplete="off" id="formProduksi">
            <input type="hidden" name="id_edit" value="<?= htmlspecialchars($id_edit) ?>">
            
            <div class="form-grid-top" style="grid-template-columns: repeat(5, 1fr);">
                <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal" value="<?= htmlspecialchars($tanggal) ?>" class="admin-input" required></div>
                <div class="form-group">
                    <label>Shift Kerja</label>
                    <select name="shift" required class="admin-input">
                        <option value="1" <?= $shift == '1' ? 'selected' : '' ?>>Shift 1</option>
                        <option value="2" <?= $shift == '2' ? 'selected' : '' ?>>Shift 2</option>
                        <option value="3" <?= $shift == '3' ? 'selected' : '' ?>>Shift 3</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kode Produksi</label>
                    <select name="jenis_produksi" id="jenis_produksi" required class="admin-input" style="font-weight: 800;">
                        <option value="BOX" <?= $jenis_produksi == 'BOX' ? 'selected' : '' ?>>📦 BOX</option>
                        <option value="SHEET" <?= $jenis_produksi == 'SHEET' ? 'selected' : '' ?>>📄 SHEET</option>
                    </select>
                </div>
                <div class="form-group"><label>No. MO</label><input type="text" name="mo" value="<?= htmlspecialchars($mo) ?>" class="admin-input" placeholder="Cth: 26G031-1-1" required></div>
                <div class="form-group"><label>Customer</label><input type="text" name="customer" value="<?= htmlspecialchars($customer) ?>" class="admin-input" placeholder="Nama Perusahaan" required></div>
            </div>

            <div class="form-grid-top" style="grid-template-columns: repeat(5, 1fr); margin-top: 10px;">
                <div class="form-group"><label>Panjang / P (mm)</label><input type="number" min="0" id="ukuran_p" name="ukuran_p" value="<?= htmlspecialchars($ukuran_p) ?>" class="admin-input" required></div>
                <div class="form-group"><label>Lebar / L (mm)</label><input type="number" min="0" id="ukuran_l" name="ukuran_l" value="<?= htmlspecialchars($ukuran_l) ?>" class="admin-input" required></div>
                <div class="form-group"><label>Order (Cus)</label><input type="number" min="0" id="order_customer" name="order_customer" value="<?= htmlspecialchars($order_customer) ?>" class="admin-input" required></div>
                <div class="form-group"><label>Lebar Bahan (Pproll)</label><input type="number" min="0" id="lebar" name="lebar" value="<?= htmlspecialchars($lebar) ?>" class="admin-input" required></div>
                <div class="form-group"><label>Hasil (Counter)</label><input type="number" min="0" id="hasil_counter" name="hasil_counter" value="<?= htmlspecialchars($hasil_counter) ?>" class="admin-input" required style="border-color: #0ea5e9; color: #0284c7;"></div>
            </div>

            <div class="form-group" style="margin-top: 10px;">
                <label style="color: #0ea5e9;">Bahan / Substance (Mesin 1 - 7) — <span style="font-size: 9px; color:#64748b; font-weight: normal;">Kosongkan jika tidak dipakai</span></label>
                <div class="subs-grid">
                    <?php for($i=1; $i<=7; $i++): $val_j = ${"s{$i}_j"}; $val_g = ${"s{$i}_g"}; ?>
                    <div class="subs-item">
                        <span>M - <?= $i ?></span>
                        <select name="s<?= $i ?>_j" class="admin-input" style="padding: 6px !important;">
                            <option value="" <?= $val_j == '' ? 'selected' : '' ?>>-</option>
                            <option value="KB" <?= $val_j == 'KB' ? 'selected' : '' ?>>KB</option>
                            <option value="FB" <?= $val_j == 'FB' ? 'selected' : '' ?>>FB</option>
                            <option value="MB" <?= $val_j == 'MB' ? 'selected' : '' ?>>MB</option>
                            <option value="WK" <?= $val_j == 'WK' ? 'selected' : '' ?>>WK</option>
                        </select>
                        <input type="number" name="s<?= $i ?>_g" value="<?= htmlspecialchars($val_g) ?>" class="admin-input" placeholder="GSM" style="padding: 6px !important;">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-grid-top" style="margin-top: 10px; grid-template-columns: 1fr;">
                <div class="form-group">
                    <label>Keterangan Tambahan / Nama Item <span style="color:#ef4444; font-size:9px;" id="lblWajibBox">(Wajib diisi khusus untuk BOX)</span></label>
                    <input type="text" name="keterangan" id="keterangan" value="<?= htmlspecialchars($keterangan) ?>" class="admin-input" placeholder="Ketik nama item atau keterangan lainnya di sini...">
                    <!-- 🚀 TAMBAHAN: CHEAT SHEET (PANDUAN WARNA) -->
                    <div style="font-size: 10px; margin-top: 6px; color: #64748b; font-weight: 700; display: flex; gap: 12px; flex-wrap: wrap; background: #f8fafc; padding: 8px; border-radius: 6px; border: 1px dashed #cbd5e1;">
                        <span style="color: #0f172a; margin-right: 4px;">🎨 Panduan Warna Baris:</span>
                        <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:12px; height:12px; background:#fce7f3; border:1px solid #f9a8d4; border-radius:3px;"></span>Ketik "QC TEST" (Pink)</span>
                        <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:12px; height:12px; background:#fee2e2; border:1px solid #fca5a5; border-radius:3px;"></span>Ketik "SALAH PRODUKSI" (Merah)</span>
                        <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:12px; height:12px; background:#fef08a; border:1px solid #fde047; border-radius:3px;"></span>Ketik "LANJUTAN" (Kuning)</span>
                        <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:12px; height:12px; background:#e0f2fe; border:1px solid #bae6fd; border-radius:3px;"></span>Kode BOX (Biru)</span>
                        <span style="display:flex; align-items:center; gap:4px;"><span style="display:inline-block; width:12px; height:12px; background:#dcfce7; border:1px solid #bbf7d0; border-radius:3px;"></span>Ketik "SLITTER" (Hijau)</span>
                    </div>
                </div>
            </div>
            
            <p style="margin: 15px 0 8px 0; font-size:13px; font-weight:800; color:#475569; text-transform: uppercase;">📊 Kondisional Auto Calculate Sistem (Live Preview):</p>
            <div class="live-calc-box">
                <div>📐 M2 / Pcs:<span id="live_m2_pcs">0.0000</span></div>
                <div>⚖️ Kg / Pcs: <span id="live_kg_pcs" style="color: #0ea5e9;">0.0000</span></div>
                <div>📏 or L (rm):<span id="live_or_l">0.000</span></div>
                <div>📏 or m2:<span id="live_or_m2">0.000</span></div>
                <div>✂️ Out:<span id="live_out">0</span></div>
                <div>📦 Total Kg:<span id="live_total_kg" style="color: #10b981;">0.000</span></div>
            </div>
            
            <div style="display:flex; justify-content: flex-end; width: 100%;">
                <div style="width: 250px;">
                    <button type="submit" name="simpan_nc" class="btn-submit-modern" style="<?= $is_edit ? 'background:#0ea5e9 !important;' : '' ?>">
                        <?= $is_edit ? "💾 Simpan Pembaruan Data" : "💾 Simpan Laporan NC" ?>
                    </button>
                    <?php if($is_edit): ?><a href="produksi_nc.php?tgl=<?= $tgl_filter ?>&shift_filter=<?= $shift_filter ?>" class="btn-batal-modern">Batal Perubahan</a><?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- 🚀 WIDGET SUMMARY TOTAL BOX & SHEET (DI DALAM 1 KOTAK BESAR KHUSUS WIDGET) -->
<?php 
function formatBersihDesimal($angka, $max_desimal = 3) {
    $format = number_format($angka, $max_desimal, ',', '.');
    if (strpos($format, ',') !== false) {
        $format = rtrim(rtrim($format, '0'), ',');
    }
    return $format;
}

$stmt = $pdo->prepare("SELECT * FROM db_produksi_nc WHERE tanggal = ? ORDER BY shift ASC, id ASC");
$stmt->execute([$tgl_filter]);
$data_nc = $stmt->fetchAll(PDO::FETCH_ASSOC);

$box_pcs = 0; $box_kg = 0; $box_orl = 0; $box_orm2 = 0;
$sht_pcs = 0; $sht_kg = 0; $sht_orl = 0; $sht_orm2 = 0;

$data_per_shift = ['1' => [], '2' => [], '3' => []];
foreach ($data_nc as $row) {
    $data_per_shift[$row['shift']][] = $row;
    
    // 🚀 LOGIKA FILTER SHIFT KHUSUS WIDGET
    if ($shift_filter === 'ALL' || $shift_filter == $row['shift']) {
        if ($row['jenis_produksi'] == 'BOX') {
            $box_pcs += $row['hasil_counter'];
            $box_kg  += $row['total_kg'];
            $box_orl += $row['or_l_rm'];
            $box_orm2+= $row['or_m2'];
        } elseif ($row['jenis_produksi'] == 'SHEET') {
            $sht_pcs += $row['hasil_counter'];
            $sht_kg  += $row['total_kg'];
            $sht_orl += $row['or_l_rm'];
            $sht_orm2+= $row['or_m2'];
        }
    }
}

$avg_lebar_box = $box_orl > 0 ? ($box_orm2 / $box_orl) * 1000 : 0;
$avg_lebar_sht = $sht_orl > 0 ? ($sht_orm2 / $sht_orl) * 1000 : 0;
$ada_data_hari_ini = count($data_nc) > 0;
?>

<?php if ($ada_data_hari_ini): ?>
<div class="card" style="border-top: 5px solid #0ea5e9; background: #f8fafc; padding: 25px;">
    
    <!-- HEADER BOX WIDGET + DROPDOWN KHUSUS WIDGET -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px;">
        <h3 style="margin: 0; font-size: 15px; color: #0f172a; font-weight: 900; display:flex; align-items:center; gap:8px;">📊 AKUMULASI PRODUKSI (WIDGET)</h3>
        
        <form method="GET" action="" style="margin: 0; display:flex; align-items:center; gap:10px;">
            <input type="hidden" name="tgl" value="<?= $tgl_filter ?>">
            <label style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase;">Filter Shift Khusus Widget:</label>
            <select name="shift_filter" onchange="this.form.submit()" style="width: auto; border-color: #0ea5e9; color: #0284c7; background: #f0f9ff !important; cursor: pointer; padding: 6px 12px !important; font-weight: 800; border-radius: 6px; font-size: 12px !important;">
                <option value="ALL" <?= $shift_filter == 'ALL' ? 'selected' : '' ?>>Semua Shift</option>
                <option value="1" <?= $shift_filter == '1' ? 'selected' : '' ?>>Shift 1</option>
                <option value="2" <?= $shift_filter == '2' ? 'selected' : '' ?>>Shift 2</option>
                <option value="3" <?= $shift_filter == '3' ? 'selected' : '' ?>>Shift 3</option>
            </select>
        </form>
    </div>

    <!-- ISI WIDGETNYA -->
    <div class="widget-container" style="margin-bottom: 0;">
        <div class="widget-card widget-box">
            <h3 class="widget-title" style="color: #0369a1;">📦 TOTAL PRODUKSI BOX <?= $shift_filter !== 'ALL' ? "<span style='color:#ef4444; margin-left:5px;'>(SHIFT $shift_filter)</span>" : "" ?></h3>
            <div class="w-grid">
                <div class="w-item"><div class="w-label">Total Pcs</div><div class="w-val"><?= number_format($box_pcs, 0, ',', '.') ?></div></div>
                <div class="w-item"><div class="w-label">Total Kg</div><div class="w-val" style="color:#16a34a;"><?= formatBersihDesimal($box_kg, 3) ?></div></div>
                <div class="w-item"><div class="w-label">AVG Lebar</div><div class="w-val" style="color:#b45309;"><?= number_format($avg_lebar_box, 3, ',', '.') ?> <span style="font-size:10px;">mm</span></div></div>
                <div class="w-item" style="grid-column: span 1;"><div class="w-label">OR L (rm)</div><div class="w-val" style="color:#475569; font-size:13px;"><?= number_format($box_orl, 0, ',', '.') ?></div></div>
                <div class="w-item" style="grid-column: span 2;"><div class="w-label">OR m2</div><div class="w-val" style="color:#475569; font-size:13px;"><?= formatBersihDesimal($box_orm2, 3) ?></div></div>
            </div>
        </div>
        <div class="widget-card widget-sheet">
            <h3 class="widget-title" style="color: #166534;">📄 TOTAL PRODUKSI SHEET <?= $shift_filter !== 'ALL' ? "<span style='color:#ef4444; margin-left:5px;'>(SHIFT $shift_filter)</span>" : "" ?></h3>
            <div class="w-grid">
                <div class="w-item"><div class="w-label">Total Pcs</div><div class="w-val"><?= number_format($sht_pcs, 0, ',', '.') ?></div></div>
                <div class="w-item"><div class="w-label">Total Kg</div><div class="w-val" style="color:#16a34a;"><?= formatBersihDesimal($sht_kg, 3) ?></div></div>
                <div class="w-item"><div class="w-label">AVG Lebar</div><div class="w-val" style="color:#b45309;"><?= number_format($avg_lebar_sht, 3, ',', '.') ?> <span style="font-size:10px;">mm</span></div></div>
                <div class="w-item" style="grid-column: span 1;"><div class="w-label">OR L (rm)</div><div class="w-val" style="color:#475569; font-size:13px;"><?= number_format($sht_orl, 0, ',', '.') ?></div></div>
                <div class="w-item" style="grid-column: span 2;"><div class="w-label">OR m2</div><div class="w-val" style="color:#475569; font-size:13px;"><?= formatBersihDesimal($sht_orm2, 3) ?></div></div>
            </div>
        </div>
    </div>
    
</div>
<?php endif; ?>

<!-- 🚀 REPORTING DATA LOG PISAH PER SHIFT (SMART SPLIT) -->
<div class="card" style="border-top: 5px solid #0f172a;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px;">
        <div>
            <h2 style="margin: 0; border: none; padding: 0; color: #0f172a;">📋 LOG MONITORING PRODUKSI NC MASTER</h2>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-top: 4px;">Data Peninjauan Tanggal: <strong style="color:#0ea5e9; font-size: 15px;"><?= date('d F Y', strtotime($tgl_filter)) ?></strong></div>
        </div>
        <button type="button" onclick="exportKeExcel()" class="btn-excel-modern">📥 Export Excel Bersih</button>
    </div>

    <div id="exportContainer">
        <?php foreach (['1', '2', '3'] as $shift_num): ?>
            <?php if (count($data_per_shift[$shift_num]) > 0): ?>
                <h3 style="color: #0f172a; margin-bottom: 10px; margin-top: 20px; font-weight: 900; border-left: 4px solid #0ea5e9; padding-left: 10px;">▶ REKAPITULASI SHIFT <?= $shift_num ?></h3>
                <div class="table-responsive">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th class="stk-1" rowspan="2">No</th>
                                <th class="stk-2" rowspan="2">Mo</th>
                                <th class="stk-3 text-left" rowspan="2">Customer</th>
                                <th class="text-center" rowspan="2">Kode</th>
                                <th class="text-center" rowspan="2">Lebar<br><span style="font-size: 9px; color:#bae6fd;">Pproll</span></th>
                                <th class="text-center" colspan="7">Substance (M1 - M7)</th>
                                <th class="text-center" colspan="2">Ukuran</th>
                                <th class="text-center" rowspan="2">Order<br><span style="font-size: 9px; color:#bae6fd;">Cus</span></th>
                                <th class="text-center" rowspan="2">Hasil<br><span style="font-size: 9px; color:#bae6fd;">Counter</span></th>
                                
                                <th class="text-center" rowspan="2">M2/Pcs</th>
                                <th class="text-center" rowspan="2">Kg/Pcs</th>
                                <th class="text-center" rowspan="2">or L (rm)</th>
                                <th class="text-center" rowspan="2">or m2</th>
                                <th class="text-center" rowspan="2">Out</th>
                                <th class="text-center" rowspan="2" style="font-weight: 900;">Total Kg</th>
                                
                                <th class="text-left" rowspan="2" style="min-width:140px;">Keterangan / Nama Item</th>
                                <th class="text-center" rowspan="2" style="width: 100px;" data-noexport="true">Aksi</th>
                            </tr>
                            <tr>
                                <th>1</th><th>2</th><th>3</th>
                                <th>4</th><th>5</th><th>6</th><th>7</th>
                                <th>P</th><th>L</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $tot_order = 0; $tot_hasil = 0; $tot_kg = 0; $tot_orl = 0; $tot_old_m2 = 0;
                            
                            foreach ($data_per_shift[$shift_num] as $row): 
                                $tot_order += $row['order_customer'];
                                $tot_hasil += $row['hasil_counter'];
                                $tot_kg    += $row['total_kg'];
                                $tot_orl   += $row['or_l_rm'];
                                $tot_old_m2 += $row['or_m2'];

                                // 🚀 LOGIKA AUTO-COLOR (CONDITIONAL FORMATTING BARIS)
                                $row_class = 'row-sheet'; // Default putih
                                $ket_upper = strtoupper($row['keterangan']);
                                
                                // Prioritas pencarian kata kunci
                                if (strpos($ket_upper, 'QC TEST') !== false) {
                                    $row_class = 'row-qc';
                                } elseif (strpos($ket_upper, 'SALAH PRODUKSI') !== false) {
                                    $row_class = 'row-salah';
                                } elseif (strpos($ket_upper, 'LANJUTAN') !== false) {
                                    $row_class = 'row-lanjutan';
                                } elseif (strpos($ket_upper, 'SLITTER') !== false) {
                                    $row_class = 'row-slitter';
                                } elseif ($row['jenis_produksi'] == 'BOX') {
                                    $row_class = 'row-box';
                                }
                            ?>
                                <tr class="<?= $row_class ?>">
                                    <td class="text-center stk-1"><?= $no++ ?></td>
                                    <td class="text-center stk-2"><?= htmlspecialchars($row['mo']) ?></td>
                                    <td class="text-left stk-3" style="font-weight: 800;"><?= htmlspecialchars($row['customer']) ?></td>
                                    
                                    <td class="text-center" style="font-weight:900;"><?= htmlspecialchars($row['jenis_produksi']) ?></td>
                                    <!-- 🚀 EXPORT MURNI RAW TANPA KOMA/TITIK -->
                                    <td class="text-center" data-val="<?= (float)$row['lebar'] ?>"><?= number_format($row['lebar'], 0, ',', '.') ?></td>
                                    
                                    <td class="text-center" style="font-size:11px;"><?= $row['s1_j'] ? "{$row['s1_j']}<br>".($row['s1_g']>0?$row['s1_g']:'') : '-' ?></td>
                                    <td class="text-center" style="font-size:11px;"><?= $row['s2_j'] ? "{$row['s2_j']}<br>".($row['s2_g']>0?$row['s2_g']:'') : '-' ?></td>
                                    <td class="text-center" style="font-size:11px;"><?= $row['s3_j'] ? "{$row['s3_j']}<br>".($row['s3_g']>0?$row['s3_g']:'') : '-' ?></td>
                                    <td class="text-center" style="font-size:11px;"><?= $row['s4_j'] ? "{$row['s4_j']}<br>".($row['s4_g']>0?$row['s4_g']:'') : '-' ?></td>
                                    <td class="text-center" style="font-size:11px;"><?= $row['s5_j'] ? "{$row['s5_j']}<br>".($row['s5_g']>0?$row['s5_g']:'') : '-' ?></td>
                                    <td class="text-center" style="font-size:11px;"><?= $row['s6_j'] ? "{$row['s6_j']}<br>".($row['s6_g']>0?$row['s6_g']:'') : '-' ?></td>
                                    <td class="text-center" style="font-size:11px;"><?= $row['s7_j'] ? "{$row['s7_j']}<br>".($row['s7_g']>0?$row['s7_g']:'') : '-' ?></td>

                                    <td class="text-center" data-val="<?= (float)$row['ukuran_p'] ?>"><?= number_format($row['ukuran_p'], 0, ',', '.') ?></td>
                                    <td class="text-center" data-val="<?= (float)$row['ukuran_l'] ?>"><?= number_format($row['ukuran_l'], 0, ',', '.') ?></td>
                                    
                                    <td class="text-center" style="font-weight: 700;" data-val="<?= (float)$row['order_customer'] ?>"><?= number_format($row['order_customer'], 0, ',', '.') ?></td>
                                    <td class="text-center" style="font-weight: 800;" data-val="<?= (float)$row['hasil_counter'] ?>"><?= number_format($row['hasil_counter'], 0, ',', '.') ?></td>
                                    
                                    <!-- 🚀 EXPORT MURNI RAW DENGAN TITIK SEBAGAI DECIMAL -->
                                    <td class="text-center" data-val="<?= (float)$row['m2_pcs'] ?>"><?= formatBersihDesimal($row['m2_pcs'], 4) ?></td>
                                    <td class="text-center" data-val="<?= (float)$row['kg_pcs'] ?>"><?= formatBersihDesimal($row['kg_pcs'], 4) ?></td>
                                    <td class="text-center" data-val="<?= (float)$row['or_l_rm'] ?>"><?= number_format($row['or_l_rm'], 0, ',', '.') ?></td>
                                    <td class="text-center" data-val="<?= (float)$row['or_m2'] ?>"><?= formatBersihDesimal($row['or_m2'], 3) ?></td>
                                    <td class="text-center" style="font-weight: 900;"><?= $row['out_val'] ?></td>
                                    <td class="text-center" style="font-weight: 900;" data-val="<?= (float)$row['total_kg'] ?>"><?= formatBersihDesimal($row['total_kg'], 3) ?></td>
                                    
                                    <td class="text-left" style="font-size:11px; font-weight: 800; white-space:normal;"><?= htmlspecialchars($row['keterangan']) ?></td>
                                    
                                    <td class="text-center" data-noexport="true">
                                        <?php if ($user_role != 'Viewer'): ?>
                                            <a href="produksi_nc.php?tgl=<?= $tgl_filter ?>&shift_filter=<?= $shift_filter ?>&edit=<?= $row['id'] ?>#cardInputArea" class="btn-edit">Edit</a>
                                            <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Hapus data ini?')" class="btn-hapus">Hapus</a>
                                        <?php else: ?>
                                            <span style="font-size:10px; color:#94a3b8;">Terbatas</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="3" class="text-center stk-total">TOTAL SHIFT <?= $shift_num ?></td>
                                <td colspan="2"></td>
                                <td colspan="7"></td>
                                <td colspan="2"></td>
                                <td class="text-center" style="font-size: 14px;" data-val="<?= (float)$tot_order ?>"><?= number_format($tot_order, 0, ',', '.') ?></td>
                                <td class="text-center" style="font-size: 14px; color:#0284c7; background: #e0f2fe !important;" data-val="<?= (float)$tot_hasil ?>"><?= number_format($tot_hasil, 0, ',', '.') ?></td>
                                <td colspan="2"></td>
                                <td class="text-center" style="font-size:14px;" data-val="<?= (float)$tot_orl ?>"><?= number_format($tot_orl, 0, ',', '.') ?></td>
                                <td class="text-center" style="font-size:14px;" data-val="<?= (float)$tot_old_m2 ?>"><?= formatBersihDesimal($tot_old_m2, 3) ?></td>
                                <td></td>
                                <td class="text-center" style="font-size:14px; color:#be123c; background:#fff1f2 !important;" data-val="<?= (float)$tot_kg ?>"><?= formatBersihDesimal($tot_kg, 3) ?></td>
                                <td></td>
                                <td data-noexport="true"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <?php if (!$ada_data_hari_ini): ?>
            <div class="table-responsive">
                <table class="table-premium">
                    <tr><td class="text-center" style="padding: 40px; color: #94a3b8; font-weight: 600;">⚠️ Belum ada rekaman laporan produksi NC pada tanggal ini.</td></tr>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<form id="formHapus" method="POST" style="display:none;">
    <input type="hidden" name="hapus_id" id="getHapusId">
    <input type="hidden" name="hapus_data" value="1">
</form>

<script>
    function toggleFormInput() {
        let content = document.getElementById('formCollapsibleContent');
        let icon = document.getElementById('formToggleIcon');
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.innerText = '▲ KLIK UNTUK TUTUP PANEL';
            localStorage.setItem('slitterFormState', 'open');
        } else {
            content.style.display = 'none';
            icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
            localStorage.setItem('slitterFormState', 'closed');
        }
    }

    function konfirmasiHapus(id, pesan) {
        if(confirm(pesan)) {
            document.getElementById('getHapusId').value = id;
            document.getElementById('formHapus').submit();
        }
    }

    document.getElementById('formProduksi').addEventListener('submit', function(e) {
        let jenis = document.getElementById('jenis_produksi').value;
        let ket = document.getElementById('keterangan').value.trim();
        
        if (jenis === 'BOX' && ket === '') {
            e.preventDefault(); 
            alert("🛑 STOP! Untuk jenis produksi BOX, kolom 'Keterangan Tambahan / Nama Item' WAJIB diisi!");
            document.getElementById('keterangan').focus();
            document.getElementById('keterangan').style.borderColor = '#ef4444';
            document.getElementById('keterangan').style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.2)';
        }
    });

    document.getElementById('keterangan').addEventListener('input', function() {
        this.style.borderColor = '#cbd5e1';
        this.style.boxShadow = 'none';
    });

    document.getElementById('jenis_produksi').addEventListener('change', function() {
        if (this.value === 'BOX') {
            document.getElementById('lblWajibBox').style.display = 'inline';
        } else {
            document.getElementById('lblWajibBox').style.display = 'none';
            document.getElementById('keterangan').style.borderColor = '#cbd5e1';
            document.getElementById('keterangan').style.boxShadow = 'none';
        }
    });

    function hitungLiveNC() {
        const lebar = parseFloat(document.getElementById('lebar').value) || 0;
        const p = parseFloat(document.getElementById('ukuran_p').value) || 0;
        const l = parseFloat(document.getElementById('ukuran_l').value) || 0;
        const hasil = parseFloat(document.getElementById('hasil_counter').value) || 0;

        const m2Pcs = (p * l) / 1000000;
        const outVal = l > 0 ? Math.floor(lebar / l) : 0; 
        const orLrm = outVal > 0 ? (p * hasil / 1000 / outVal) : 0;
        const orM2 = (hasil * m2Pcs) / 1000;
        
        let g1 = parseFloat(document.getElementsByName('s1_g')[0].value) || 0;
        let g2 = (parseFloat(document.getElementsByName('s2_g')[0].value) || 0) * 1.40;
        let g3 = parseFloat(document.getElementsByName('s3_g')[0].value) || 0;
        let g4 = (parseFloat(document.getElementsByName('s4_g')[0].value) || 0) * 1.50; 
        let g5 = parseFloat(document.getElementsByName('s5_g')[0].value) || 0;
        let g6 = (parseFloat(document.getElementsByName('s6_g')[0].value) || 0) * 1.25; 
        let g7 = parseFloat(document.getElementsByName('s7_g')[0].value) || 0;
        
        const kgPcs = (m2Pcs * (g1+g2+g3+g4+g5+g6+g7)) / 1000;
        const totalKg = hasil * kgPcs;

        document.getElementById('live_m2_pcs').innerText = m2Pcs.toLocaleString('id-ID', {maximumFractionDigits: 4});
        document.getElementById('live_kg_pcs').innerText = kgPcs.toLocaleString('id-ID', {maximumFractionDigits: 4});
        document.getElementById('live_out').innerText = outVal;
        
        let cleanOrL = orLrm.toFixed(3).replace(/(\.0+|0+)$/, '');
        if(cleanOrL.endsWith('.')) cleanOrL = cleanOrL.slice(0, -1);
        let cleanOrM2 = orM2.toFixed(3).replace(/(\.0+|0+)$/, '');
        if(cleanOrM2.endsWith('.')) cleanOrM2 = cleanOrM2.slice(0, -1);
        let cleanTotalKg = totalKg.toFixed(3).replace(/(\.0+|0+)$/, '');
        if(cleanTotalKg.endsWith('.')) cleanTotalKg = cleanTotalKg.slice(0, -1);

        document.getElementById('live_or_l').innerText = parseFloat(cleanOrL).toLocaleString('id-ID');
        document.getElementById('live_or_m2').innerText = parseFloat(cleanOrM2).toLocaleString('id-ID');
        document.getElementById('live_total_kg').innerText = parseFloat(cleanTotalKg).toLocaleString('id-ID');
    }
    
    // 🚀 EXPORT SAKTI DENGAN FITUR TUKAR ISI HTML KE RAW DATA
    function exportKeExcel() {
        let exportContainer = document.getElementById("exportContainer");
        if (!exportContainer) return;

        let cloneContainer = exportContainer.cloneNode(true);
        let noExportTh = cloneContainer.querySelectorAll('th[data-noexport="true"]');
        noExportTh.forEach(th => th.remove());
        let noExportTd = cloneContainer.querySelectorAll('td[data-noexport="true"]');
        noExportTd.forEach(td => td.remove());

        let subCells = cloneContainer.querySelectorAll('td');
        subCells.forEach(td => {
            // Jika ada atribut rahasia data-val, pakai nilai mentahnya!
            if(td.hasAttribute('data-val')) {
                td.innerText = td.getAttribute('data-val');
            } else {
                td.innerHTML = td.innerHTML.replace(/<br\s*[\/]?>/gi, '\n');
                let smalls = td.querySelectorAll('small');
                smalls.forEach(small => small.remove());
            }
        });

        let allElements = cloneContainer.querySelectorAll('*');
        allElements.forEach(el => { el.removeAttribute('style'); });

        let tglExport = document.querySelector('input[name="tgl"]').value;
        
        let htmlTemplate = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; margin-bottom: 20px; }
                    th, td { border: 1px solid #000000; text-align: center; vertical-align: middle; white-space: pre-wrap; font-size: 11pt; mso-number-format:"\\@"; }
                    th { background-color: #d9e1f2; font-weight: bold; }
                    h3 { font-family: Calibri, Arial, sans-serif; }
                </style>
            </head>
            <body>
                <h2 style="text-align: center; margin-bottom: 15px;">LAPORAN PRODUKSI NC MASTER (PER SHIFT)</h2>
                <p><strong>TANGGAL OPERASIONAL:</strong> ${tglExport}</p>
                ${cloneContainer.innerHTML}
            </body>
            </html>
        `;

        let blob = new Blob([htmlTemplate], { type: 'application/vnd.ms-excel' });
        let url = URL.createObjectURL(blob);
        let a = document.createElement('a');
        a.href = url;
        a.download = 'Laporan_Produksi_NC_' + tglExport + '.xls';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    window.onload = function() {
        ['lebar', 'ukuran_p', 'ukuran_l', 'hasil_counter', 's1_g', 's2_g', 's3_g', 's4_g', 's5_g', 's6_g', 's7_g'].forEach(id => {
            let el = document.getElementById(id); 
            if(el) el.addEventListener('input', hitungLiveNC);
            else {
                let els = document.getElementsByName(id);
                if(els.length > 0) els[0].addEventListener('input', hitungLiveNC);
            }
        });
        hitungLiveNC();

        let jenisProd = document.getElementById('jenis_produksi');
        if(jenisProd && jenisProd.value !== 'BOX') {
            document.getElementById('lblWajibBox').style.display = 'none';
        }

        let formState = localStorage.getItem('slitterFormState');
        let content = document.getElementById('formCollapsibleContent');
        let icon = document.getElementById('formToggleIcon');
        
        <?php if ($is_edit): ?>
            content.style.display = 'block';
            icon.innerText = '▲ SEDANG MODE EDIT';
        <?php else: ?>
            if (formState === 'open') {
                content.style.display = 'block';
                icon.innerText = '▲ KLIK UNTUK TUTUP PANEL';
            } else {
                content.style.display = 'none';
                icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
            }
        <?php endif; ?>
    };
</script>
<?php require_once 'footer.php'; ?>
