<?php
require_once 'auth.php';
require_akses('prod_rek');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = ""; $is_edit = false; $id_edit = "";

$tgl = date('Y-m-d'); 
$pakai_paper = 0; $avg_lbr_corr = 0; $corrugator_kg = 0; $speed_corr = 0; $speed_flexo = 0;
$flexo_st = 0; $flexo_inline = 0; $glue_pcs = 0; $slitter_pcs = 0; $stitch_pcs = 0; $stitch_auto = 0;
$lem_finishing = 0; $tstb_kg = 0; $delivery_kg = 0; $downtime_mnt = 0; $waste_persen = 0; $waste_kg = 0;
$batu_bara = 0; $tapioka = 0; $ca_additive = 0; $caustik_soda = 0; $borak = 0; $solar = 0; $tinta = 0;
$kirim_waste = 0; $kawat_auto = 0; $kawat_manual = 0; $lakban_kertas_1 = 0; $lakban_kertas_2 = 0;
$striping_band_5_ml = 0; $tali_rapiah = 0;

// 🚀 LOGIKA HAPUS DATA AMAN (MENGGUNAKAN POST METHOD BUKAN GET)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hapus_data']) && isset($_POST['hapus_id']) && $user_role != 'Viewer') {
    try {
        $id = intval($_POST['hapus_id']);
        $stmt = $pdo->prepare("DELETE FROM rekap_bulanan WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        catatLog($pdo, $user_aktif, "Menghapus satu data Rekap Bulanan.", "🗑️");
        header("Location: rekap_bulanan.php?pesan=hapus_sukses"); exit();
    } catch (PDOException $e) { $pesan = "<div class='alert alert-danger'>❌ Gagal menghapus: " . $e->getMessage() . "</div>"; }
}

if (isset($_GET['edit']) && $user_role != 'Viewer') {
    $is_edit = true; $id_edit = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM rekap_bulanan WHERE id = :id");
    $stmt->execute([':id' => $id_edit]);
    $data_lama = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($data_lama) {
        $tgl = $data_lama['tgl']; if(strtotime($tgl)) { $tgl = date('Y-m-d', strtotime($tgl)); }
        $pakai_paper = $data_lama['pakai_paper']; $avg_lbr_corr = $data_lama['avg_lbr_corr']; $corrugator_kg = $data_lama['corrugator_kg'];
        $speed_corr = $data_lama['speed_corr']; $speed_flexo = $data_lama['speed_flexo']; $flexo_st = $data_lama['flexo_st'];
        $flexo_inline = $data_lama['flexo_inline']; $glue_pcs = $data_lama['glue_pcs']; $slitter_pcs = $data_lama['slitter_pcs'];
        $stitch_pcs = $data_lama['stitch_pcs']; $stitch_auto = $data_lama['stitch_auto']; $lem_finishing = $data_lama['lem_finishing'];
        $tstb_kg = $data_lama['tstb_kg']; $delivery_kg = $data_lama['delivery_kg']; $downtime_mnt = $data_lama['downtime_mnt'];
        $waste_persen = $data_lama['waste_persen']; $waste_kg = $data_lama['waste_kg']; $batu_bara = $data_lama['batu_bara'];
        $tapioka = $data_lama['tapioka']; $ca_additive = $data_lama['ca_additive']; $caustik_soda = $data_lama['caustik_soda'];
        $borak = $data_lama['borak']; $solar = $data_lama['solar']; $tinta = $data_lama['tinta']; $kirim_waste = $data_lama['kirim_waste'];
        $kawat_auto = $data_lama['kawat_auto']; $kawat_manual = $data_lama['kawat_manual']; $lakban_kertas_1 = $data_lama['lakban_kertas_1'];
        $lakban_kertas_2 = $data_lama['lakban_kertas_2']; $striping_band_5_ml = $data_lama['striping_band_5_ml']; $tali_rapiah = $data_lama['tali_rapiah'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_manual']) && $user_role != 'Viewer') {
    $tgl = $_POST['tgl']; $tahun = intval(date('Y', strtotime($tgl)));
    $bulan_eng = date('F', strtotime($tgl));
    $bulan_map = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
    $bulan = isset($bulan_map[$bulan_eng]) ? $bulan_map[$bulan_eng] : $bulan_eng;

    $pakai_paper = floatval($_POST['pakai_paper']); $avg_lbr_corr = floatval($_POST['avg_lbr_corr']); $corrugator_kg = floatval($_POST['corrugator_kg']);
    $speed_corr = floatval($_POST['speed_corr']); $speed_flexo = floatval($_POST['speed_flexo']); $flexo_st = intval($_POST['flexo_st']);
    $flexo_inline = intval($_POST['flexo_inline']); $glue_pcs = intval($_POST['glue_pcs']); $slitter_pcs = intval($_POST['slitter_pcs']);
    $stitch_pcs = intval($_POST['stitch_pcs']); $stitch_auto = intval($_POST['stitch_auto']); $lem_finishing = floatval($_POST['lem_finishing']);
    $tstb_kg = floatval($_POST['tstb_kg']); $delivery_kg = floatval($_POST['delivery_kg']); $downtime_mnt = floatval($_POST['downtime_mnt']);
    $waste_persen = floatval($_POST['waste_persen']); $waste_kg = floatval($_POST['waste_kg']); $batu_bara = floatval($_POST['batu_bara']);
    $tapioka = floatval($_POST['tapioka']); $ca_additive = floatval($_POST['ca_additive']); $caustik_soda = floatval($_POST['caustik_soda']);
    $borak = floatval($_POST['borak']); $solar = floatval($_POST['solar']); $tinta = floatval($_POST['tinta']); $kirim_waste = floatval($_POST['kirim_waste']);
    $kawat_auto = floatval($_POST['kawat_auto']); $kawat_manual = floatval($_POST['kawat_manual']); $lakban_kertas_1 = floatval($_POST['lakban_kertas_1']);
    $lakban_kertas_2 = floatval($_POST['lakban_kertas_2']); $striping_band_5_ml = floatval($_POST['striping_band_5_ml']); $tali_rapiah = floatval($_POST['tali_rapiah']);

    try {
        if ($is_edit) {
            $sql = "UPDATE rekap_bulanan SET tahun=:tahun, tgl=:tgl, bulan=:bulan, pakai_paper=:pakai_paper, avg_lbr_corr=:avg_lbr_corr, corrugator_kg=:corrugator_kg, speed_corr=:speed_corr, speed_flexo=:speed_flexo, flexo_st=:flexo_st, flexo_inline=:flexo_inline, glue_pcs=:glue_pcs, slitter_pcs=:slitter_pcs, stitch_pcs=:stitch_pcs, stitch_auto=:stitch_auto, lem_finishing=:lem_finishing, tstb_kg=:tstb_kg, delivery_kg=:delivery_kg, downtime_mnt=:downtime_mnt, waste_persen=:waste_persen, waste_kg=:waste_kg, batu_bara=:batu_bara, tapioka=:tapioka, ca_additive=:ca_additive, caustik_soda=:caustik_soda, borak=:borak, solar=:solar, tinta=:tinta, kirim_waste=:kirim_waste, kawat_auto=:kawat_auto, kawat_manual=:kawat_manual, lakban_kertas_1=:lakban_kertas_1, lakban_kertas_2=:lakban_kertas_2, striping_band_5_ml=:striping_band_5_ml, tali_rapiah=:tali_rapiah WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':tahun'=>$tahun,':tgl'=>$tgl,':bulan'=>$bulan,':pakai_paper'=>$pakai_paper,':avg_lbr_corr'=>$avg_lbr_corr,':corrugator_kg'=>$corrugator_kg,':speed_corr'=>$speed_corr,':speed_flexo'=>$speed_flexo,':flexo_st'=>$flexo_st,':flexo_inline'=>$flexo_inline,':glue_pcs'=>$glue_pcs,':slitter_pcs'=>$slitter_pcs,':stitch_pcs'=>$stitch_pcs,':stitch_auto'=>$stitch_auto,':lem_finishing'=>$lem_finishing,':tstb_kg'=>$tstb_kg,':delivery_kg'=>$delivery_kg,':downtime_mnt'=>$downtime_mnt,':waste_persen'=>$waste_persen,':waste_kg'=>$waste_kg,':batu_bara'=>$batu_bara,':tapioka'=>$tapioka,':ca_additive'=>$ca_additive,':caustik_soda'=>$caustik_soda,':borak'=>$borak,':solar'=>$solar,':tinta'=>$tinta,':kirim_waste'=>$kirim_waste,':kawat_auto'=>$kawat_auto,':kawat_manual'=>$kawat_manual,':lakban_kertas_1'=>$lakban_kertas_1,':lakban_kertas_2'=>$lakban_kertas_2,':striping_band_5_ml'=>$striping_band_5_ml,':tali_rapiah'=>$tali_rapiah,':id'=>$id_edit]);
            
            catatLog($pdo, $user_aktif, "Mengubah Data Rekap Bulanan periode $bulan $tahun.", "✏️");
            header("Location: rekap_bulanan.php?pesan=edit_sukses"); exit();
        } else {
            $sql = "INSERT INTO rekap_bulanan (tahun, tgl, bulan, pakai_paper, avg_lbr_corr, corrugator_kg, speed_corr, speed_flexo, flexo_st, flexo_inline, glue_pcs, slitter_pcs, stitch_pcs, stitch_auto, lem_finishing, tstb_kg, delivery_kg, downtime_mnt, waste_persen, waste_kg, batu_bara, tapioka, ca_additive, caustik_soda, borak, solar, tinta, kirim_waste, kawat_auto, kawat_manual, lakban_kertas_1, lakban_kertas_2, striping_band_5_ml, tali_rapiah) VALUES (:tahun, :tgl, :bulan, :pakai_paper, :avg_lbr_corr, :corrugator_kg, :speed_corr, :speed_flexo, :flexo_st, :flexo_inline, :glue_pcs, :slitter_pcs, :stitch_pcs, :stitch_auto, :lem_finishing, :tstb_kg, :delivery_kg, :downtime_mnt, :waste_persen, :waste_kg, :batu_bara, :tapioka, :ca_additive, :caustik_soda, :borak, :solar, :tinta, :kirim_waste, :kawat_auto, :kawat_manual, :lakban_kertas_1, :lakban_kertas_2, :striping_band_5_ml, :tali_rapiah)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':tahun'=>$tahun,':tgl'=>$tgl,':bulan'=>$bulan,':pakai_paper'=>$pakai_paper,':avg_lbr_corr'=>$avg_lbr_corr,':corrugator_kg'=>$corrugator_kg,':speed_corr'=>$speed_corr,':speed_flexo'=>$speed_flexo,':flexo_st'=>$flexo_st,':flexo_inline'=>$flexo_inline,':glue_pcs'=>$glue_pcs,':slitter_pcs'=>$slitter_pcs,':stitch_pcs'=>$stitch_pcs,':stitch_auto'=>$stitch_auto,':lem_finishing'=>$lem_finishing,':tstb_kg'=>$tstb_kg,':delivery_kg'=>$delivery_kg,':downtime_mnt'=>$downtime_mnt,':waste_persen'=>$waste_persen,':waste_kg'=>$waste_kg,':batu_bara'=>$batu_bara,':tapioka'=>$tapioka,':ca_additive'=>$ca_additive,':caustik_soda'=>$caustik_soda,':borak'=>$borak,':solar'=>$solar,':tinta'=>$tinta,':kirim_waste'=>$kirim_waste,':kawat_auto'=>$kawat_auto,':kawat_manual'=>$kawat_manual,':lakban_kertas_1'=>$lakban_kertas_1,':lakban_kertas_2'=>$lakban_kertas_2,':striping_band_5_ml'=>$striping_band_5_ml,':tali_rapiah'=>$tali_rapiah]);
            
            catatLog($pdo, $user_aktif, "Menyimpan Data Rekap Bulanan baru periode $bulan $tahun.", "📊");
            $pesan = "<div class='alert alert-success'>✅ Data Rekap berhasil disimpan!</div>";
        }
    } catch (PDOException $e) { $pesan = "<div class='alert alert-danger'>❌ Gagal: " . $e->getMessage() . "</div>"; }
}

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'edit_sukses') $pesan = "<div class='alert alert-success'>✅ Perubahan data rekap berhasil diperbarui!</div>";
    elseif ($_GET['pesan'] == 'hapus_sukses') $pesan = "<div class='alert alert-success'>🗑️ Data rekap berhasil dihapus!</div>";
}

$filter_tahun = isset($_GET['filter_tahun']) ? intval($_GET['filter_tahun']) : date('Y');
try {
    $list_tahun_db = $pdo->query("SELECT DISTINCT tahun FROM rekap_bulanan ORDER BY tahun DESC")->fetchAll(PDO::FETCH_COLUMN);
    if(count($list_tahun_db) == 0) { $list_tahun_db = [date('Y')]; }
    
    // ORDER BY tgl ASC (Mengurutkan berdasarkan TANGGAL ASLI dari terkecil ke terbesar)
    $stmt = $pdo->prepare("SELECT * FROM rekap_bulanan WHERE tahun = :tahun ORDER BY tgl ASC");
    $stmt->execute([':tahun' => $filter_tahun]);
    $rekap_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { die("Gagal memuat data: " . $e->getMessage()); }

$page_title = "H2 BASE — Rekap Bulanan";
$active_page = "rekap";
require 'header.php';
?>

<style>
    /* 🚀 FILTER ACTION PANEL & FORM INPUT MODERN SAAS */
    .filter-box { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 20px; }
    .filter-box .form-group { margin-bottom: 0; min-width: 150px; }
    .filter-box .btn-group-filter { display: flex; gap: 10px; }

    /* 🚀 FORM INPUT FIELD MODERN */
    input, select { 
        background: #ffffff !important; 
        border: 1px solid #cbd5e1 !important; 
        border-radius: 8px !important; 
        padding: 10px 14px !important; 
        font-size: 13px !important; 
        color: #0f172a !important; 
        font-weight: 700 !important;
        box-sizing: border-box !important;
        transition: all 0.2s ease-in-out !important;
    }
    input:focus, select:focus { 
        border-color: #0ea5e9 !important; 
        background: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(14,165,233,0.15) !important; 
    }

    /* 🚀 RE-DESIGN TOMBOL "SIMPAN REKAP" (PREMIUM PRIMARY GREEN) */
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
    .btn-submit-modern:hover {
        background: #059669 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3) !important;
    }

    /* 🚀 RE-DESIGN TOMBOL "🔍 CARI TAHUN" (PREMIUM SLATE BLUE) */
    .btn-search-modern {
        background: #0f172a !important; 
        color: #ffffff !important;
        border: none !important;
        padding: 10px 22px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        cursor: pointer !important;
        transition: all 0.2s ease-in-out !important;
        box-shadow: 0 2px 4px rgba(15, 23, 42, 0.05) !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        height: 41px !important;
        box-sizing: border-box !important;
    }
    .btn-search-modern:hover {
        background: #1e293b !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15) !important;
    }

    /* TOMBOL BATAL MODERN */
    .btn-batal-modern {
        background: #ffffff !important;
        color: #475569 !important;
        border: 1px solid #cbd5e1 !important;
        padding: 10px 22px !important;
        border-radius: 8px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        transition: all 0.2s !important;
    }
    .btn-batal-modern:hover {
        background: #f1f5f9 !important;
        color: #0f172a !important;
    }

    /* 🚀 STYLE TOGGLE FORM */
    .form-toggle-header { user-select: none; transition: background 0.2s; padding: 12px 16px; border-radius: 8px; margin: -10px -10px 15px -10px; cursor: pointer; }
    .form-toggle-header:hover { background: #f8fafc; }

    /* 🚀 NAVIGASI TAB MODERN */
    .tab-navigation { display: flex; gap: 8px; background: #f8fafc; padding: 6px; border-radius: 12px; margin-bottom: 24px; overflow-x: auto; border: 1px solid #e2e8f0; -ms-overflow-style: none; scrollbar-width: none; }
    .tab-navigation::-webkit-scrollbar { display: none; }
    .tab-btn { flex: 1; min-width: max-content; background: transparent; border: 1px solid transparent; padding: 12px 24px; border-radius: 8px; font-size: 13px; font-weight: 700; color: #64748b; cursor: pointer; transition: all 0.2s ease; text-align: center; white-space: nowrap; letter-spacing: 0.5px; }
    .tab-btn:hover { background: #f1f5f9; color: #0f172a; }
    .tab-btn.active { background: #ffffff !important; color: #0ea5e9 !important; font-weight: 800; border: 1px solid #bae6fd !important; box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.1) !important; transform: translateY(-1px); }
    .tab-content { display: none; animation: fadeInTab 0.3s ease; }
    .tab-content.active { display: block; }
    @keyframes fadeInTab { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    /* 🚀 PERBAIKAN LAYOUT DALAM TAB (CARD BOX STYLE) */
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px 20px; }
    .input-section-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); margin-bottom: 10px; }
    .box-blue { border-top: 5px solid #0ea5e9; }
    .box-green { border-top: 5px solid #10b981; }
    .box-orange { border-top: 5px solid #f59e0b; }
    .box-purple { border-top: 5px solid #8b5cf6; }
    .input-section-box h3 { margin-top: 0; margin-bottom: 20px; font-size: 15px; font-weight: 800; border-bottom: 1px dashed #cbd5e1; padding-bottom: 12px; text-transform: uppercase;}
    .form-group label { margin-bottom: 6px; font-weight: 700; font-size: 11px; color: #64748b; text-transform: uppercase; display: block;}

    /* 🚀 LIVE CALCULATE (TETAP TERANG/LIGHT MODE) */
    .live-calc-box { display: flex; flex-wrap: wrap; gap: 12px; background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac; padding: 20px; border-radius: 12px; margin-bottom: 24px; font-size: 12px; color: #166534; align-items: center; box-shadow: 0 4px 6px -1px rgba(22, 163, 74, 0.1); }
    .live-calc-box > div { background: #ffffff; padding: 10px 16px; border-radius: 8px; border: 1px solid #bbf7d0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); font-weight: 700; display:flex; gap: 8px; align-items: center; }
    .live-calc-box > div span { font-size: 15px; color: #15803d; font-weight: 900 !important; }

    /* 🚀 LAYOUT TABEL KHUSUS REKAP BULANAN (DIPERTEGAS & DIPERJELAS) */
    .table-premium { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #0f172a; white-space: nowrap; min-width: 2500px; }
    .table-premium th, .table-premium td { padding: 12px 14px; text-align: right; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #e2e8f0; font-weight: 600; }
    .table-premium th { color: #ffffff; font-weight: 700; position: sticky; z-index: 10; font-size: 11px; text-align: center; letter-spacing: 0.5px;}
    
    .table-premium thead tr:nth-child(1) th { top: 0; z-index: 15; border-bottom: 1px solid #334155; }
    .table-premium thead tr:nth-child(2) th { top: 38px; z-index: 14; border-bottom: 2px solid #0f172a; } 
    
    .table-premium .stk-1 { position: sticky; left: 0; z-index: 16; background: #fff; width: 40px; min-width: 40px; border-right: 1px solid #cbd5e1; text-align: center !important;}
    .table-premium .stk-2 { position: sticky; left: 40px; z-index: 16; background: #fff; width: 60px; min-width: 60px; border-right: 1px solid #cbd5e1; text-align: center !important;}
    .table-premium .stk-3 { position: sticky; left: 100px; z-index: 16; background: #f0f9ff; width: 85px; min-width: 85px; color: #0284c7; border-right: 1px solid #cbd5e1; text-align: center !important;}
    .table-premium .stk-4 { position: sticky; left: 185px; z-index: 16; background: #f8fafc; width: 130px; min-width: 130px; border-right: 2px solid #94a3b8 !important; text-align: center !important;}
    
    .table-premium thead tr:nth-child(1) th.stk-1, .table-premium thead tr:nth-child(1) th.stk-2, .table-premium thead tr:nth-child(1) th.stk-3, .table-premium thead tr:nth-child(1) th.stk-4 { background: #0f172a !important; color: white; z-index: 20; border-bottom: 1px solid #334155;}
    .table-premium thead tr:nth-child(2) th.stk-1, .table-premium thead tr:nth-child(2) th.stk-2, .table-premium thead tr:nth-child(2) th.stk-3, .table-premium thead tr:nth-child(2) th.stk-4 { background: #0f172a !important; color: white; z-index: 19; border-bottom: 2px solid #1e293b;}
    
    .table-premium tbody tr:nth-child(even) td.stk-1, .table-premium tbody tr:nth-child(even) td.stk-2 { background: #f8fafc; }
    .table-premium tbody tr:nth-child(even) td.stk-3 { background: #e0f2fe; }
    .table-premium tbody tr:nth-child(even) td.stk-4 { background: #f1f5f9; }
    .table-premium tbody tr:hover td.stk-1, .table-premium tbody tr:hover td.stk-2, .table-premium tbody tr:hover td.stk-3, .table-premium tbody tr:hover td.stk-4 { background: #e2e8f0 !important; }

    .btn-aksi-edit { display: inline-block; background: #e0f2fe; color: #0284c7; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 700; transition: 0.2s; border: 1px solid #bae6fd; }
    .btn-aksi-edit:hover { background: #bae6fd; color: #0369a1; }
    .btn-aksi-hapus { display: inline-block; background: #fee2e2; color: #dc2626; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 700; transition: 0.2s; border: 1px solid #fecaca; margin-left: 4px; }
    .btn-aksi-hapus:hover { background: #fecaca; color: #b91c1c; }

    @media (max-width: 768px) {
        .filter-box { flex-direction: column; align-items: stretch; }
        .filter-box .btn-group-filter { flex-direction: column; width: 100%; }
        .btn-submit-modern, .btn-batal-modern, .btn-search-modern { width: 100%; text-align: center; }
        .tab-btn { padding: 10px 15px; font-size: 12px; }
    }
</style>

<?= $pesan ?>

<?php if ($user_role != 'Viewer'): ?>
<div class="card" <?= $is_edit ? 'style="border-top: 5px solid #0ea5e9; background: #f0f9ff;"' : 'style="border-top: 5px solid #10b981;"' ?>>
    <!-- 🚀 HEADER TOGGLE FORM -->
    <div class="form-toggle-header" onclick="toggleFormInput()">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid <?= $is_edit ? '#bae6fd' : '#a7f3d0' ?>; padding-bottom: 12px;">
            <h2 id="formTitle" style="margin:0; font-size: 18px; color: <?= $is_edit ? '#0284c7' : '#047857' ?>; border-bottom: none; padding-bottom: 0;">
                <?= $is_edit ? "✏️ Edit Log Rekap DATA BASE Excel" : "📝 Input Data Rekapitulasi DATA BASE Bulanan" ?>
            </h2>
            <span id="formToggleIcon" style="font-size:13px; font-weight:900; color:<?= $is_edit ? '#0284c7' : '#047857' ?>; background:<?= $is_edit ? '#e0f2fe' : '#f0fdf4' ?>; padding:4px 10px; border-radius:6px; border:1px solid <?= $is_edit ? '#bae6fd' : '#bbf7d0' ?>;">
                ▼ KLIK UNTUK BUKA/TUTUP FORM
            </span>
        </div>
    </div>

    <!-- 🚀 WRAPPER FORM YANG BISA DI-TOGGLE -->
    <div id="formCollapsibleContent" style="display: none; margin-top: 15px;">
        <form action="" method="POST" autocomplete="off">
            
            <div style="margin-bottom: 24px; width: 300px; max-width: 100%;">
                <label style="display: block; font-size: 12px; font-weight: 800; color: #475569; margin-bottom: 8px; text-transform: uppercase;">📅 Tanggal Operasional</label>
                <input type="date" name="tgl" value="<?= htmlspecialchars($tgl) ?>" required style="width: 100%; border-color: #0ea5e9; background: #f0f9ff; color: #0369a1;">
            </div>

            <div class="tab-navigation">
                <button type="button" class="tab-btn active" onclick="gantiTab('tab-produksi', this)">📊 1. Corrugator</button>
                <button type="button" class="tab-btn" onclick="gantiTab('tab-finishing', this)">⚙️ 2. Flexo & Finishing</button>
                <button type="button" class="tab-btn" onclick="gantiTab('tab-energi', this)">🪵 3. Kimia & Energi</button>
                <button type="button" class="tab-btn" onclick="gantiTab('tab-packing', this)">📦 4. Consumables</button>
            </div>

            <div id="tab-produksi" class="tab-content active">
                <div class="input-section-box box-blue">
                    <h3 style="color: #0284c7;">📊 Data Corrugator & Pemakaian Kertas</h3>
                    <div class="form-grid">
                        <div class="form-group"><label>Pakai paper (kg)</label><input type="number" step="0.01" name="pakai_paper" value="<?= $pakai_paper ?>"></div>
                        <div class="form-group"><label>avg LBr CORR (mm)</label><input type="number" step="0.01" name="avg_lbr_corr" value="<?= $avg_lbr_corr ?>"></div>
                        <div class="form-group"><label>CORRUGATOR (kg)</label><input type="number" step="0.01" name="corrugator_kg" value="<?= $corrugator_kg ?>"></div>
                        <div class="form-group"><label>Speed Corr (mL/mnt)</label><input type="number" step="0.01" name="speed_corr" value="<?= $speed_corr ?>"></div>
                    </div>
                </div>
            </div>

            <div id="tab-finishing" class="tab-content">
                <div class="input-section-box box-green">
                    <h3 style="color: #15803d;">⚙️ Data Flexo, Lem, & Pengiriman</h3>
                    <div class="form-grid">
                        <div class="form-group"><label>SPEED FLEXO</label><input type="number" step="0.01" name="speed_flexo" value="<?= $speed_flexo ?>"></div>
                        <div class="form-group"><label>FLEXO ST (pcs)</label><input type="number" id="flexo_st" name="flexo_st" value="<?= $flexo_st ?>"></div>
                        <div class="form-group"><label>FLEXO INLINE (pcs)</label><input type="number" id="flexo_inline" name="flexo_inline" value="<?= $flexo_inline ?>"></div>
                        <div class="form-group"><label>GLUE (pcs)</label><input type="number" name="glue_pcs" value="<?= $glue_pcs ?>"></div>
                        <div class="form-group"><label>SLITTER (pcs)</label><input type="number" name="slitter_pcs" value="<?= $slitter_pcs ?>"></div>
                        <div class="form-group"><label>STITCH (pcs)</label><input type="number" name="stitch_pcs" value="<?= $stitch_pcs ?>"></div>
                        <div class="form-group"><label>STITCH AUTO (pcs)</label><input type="number" name="stitch_auto" value="<?= $stitch_auto ?>"></div>
                        <div class="form-group"><label>LEM finishing (kg)</label><input type="number" step="0.01" id="lem_finishing" name="lem_finishing" value="<?= $lem_finishing ?>"></div>
                        <div class="form-group"><label>TSTB (kg)</label><input type="number" step="0.01" id="tstb_kg" name="tstb_kg" value="<?= $tstb_kg ?>"></div>
                        <div class="form-group"><label>DELIVERY (kg)</label><input type="number" step="0.01" name="delivery_kg" value="<?= $delivery_kg ?>"></div>
                        <div class="form-group"><label>DOWNTIME (mnt)</label><input type="number" step="0.01" name="downtime_mnt" value="<?= $downtime_mnt ?>"></div>
                        <div class="form-group"><label>WASTE (%)</label><input type="number" step="0.01" name="waste_persen" value="<?= $waste_persen ?>"></div>
                        <div class="form-group"><label>Waste (kg)</label><input type="number" step="0.01" name="waste_kg" value="<?= $waste_kg ?>"></div>
                    </div>
                </div>
            </div>

            <div id="tab-energi" class="tab-content">
                <div class="input-section-box box-orange">
                    <h3 style="color: #d97706;">🪵 Data Bahan Kimia, Energi, & Tinta</h3>
                    <div class="form-grid">
                        <div class="form-group"><label>BATU BARA (kg)</label><input type="number" step="0.01" id="batu_bara" name="batu_bara" value="<?= $batu_bara ?>"></div>
                        <div class="form-group"><label>TAPIOKA (kg)</label><input type="number" step="0.01" id="tapioka" name="tapioka" value="<?= $tapioka ?>"></div>
                        <div class="form-group"><label>CA ADDITIVE (kg)</label><input type="number" step="0.01" id="ca_additive" name="ca_additive" value="<?= $ca_additive ?>"></div>
                        <div class="form-group"><label>CAUSTIK SODA (kg)</label><input type="number" step="0.01" id="caustik_soda" name="caustik_soda" value="<?= $caustik_soda ?>"></div>
                        <div class="form-group"><label>BORAK (kg)</label><input type="number" step="0.01" id="borak" name="borak" value="<?= $borak ?>"></div>
                        <div class="form-group"><label>SOLAR (liter)</label><input type="number" step="0.01" id="solar" name="solar" value="<?= $solar ?>"></div>
                        <div class="form-group"><label>TINTA (kg)</label><input type="number" step="0.01" id="tinta" name="tinta" value="<?= $tinta ?>"></div>
                        <div class="form-group"><label>KIRIM WASTE (kg)</label><input type="number" step="0.01" name="kirim_waste" value="<?= $kirim_waste ?>"></div>
                    </div>
                </div>
            </div>

            <div id="tab-packing" class="tab-content">
                <div class="input-section-box box-purple">
                    <h3 style="color: #7e22ce;">📦 Data Consumables & Packing</h3>
                    <div class="form-grid">
                        <div class="form-group"><label>KAWAT AUTO (kg/TON)</label><input type="number" step="0.01" name="kawat_auto" value="<?= $kawat_auto ?>"></div>
                        <div class="form-group"><label>KAWAT MANUAL (kg/TON)</label><input type="number" step="0.01" name="kawat_manual" value="<?= $kawat_manual ?>"></div>
                        <div class="form-group"><label>LAKBAN KERTAS 1 (pcs/TON)</label><input type="number" step="0.01" name="lakban_kertas_1" value="<?= $lakban_kertas_1 ?>"></div>
                        <div class="form-group"><label>LAKBAN KERTAS 2 (pcs/TON)</label><input type="number" step="0.01" name="lakban_kertas_2" value="<?= $lakban_kertas_2 ?>"></div>
                        <div class="form-group"><label>STRIPING BAND (pcs/TON)</label><input type="number" step="0.01" name="striping_band_5_ml" value="<?= $striping_band_5_ml ?>"></div>
                        <div class="form-group"><label>TALI RAPIAH (pcs/TON)</label><input type="number" step="0.01" name="tali_rapiah" value="<?= $tali_rapiah ?>"></div>
                    </div>
                </div>
            </div>

            <p style="margin: 12px 0 8px 0; font-size:13px; font-weight:800; color:#0f172a; text-transform: uppercase;">📊 Live Preview Hasil Rasio Perhitungan:</p>
            <div class="live-calc-box">
                <div>🪨 BATU BARA: <span id="live_batu_bara_ton">0.00</span> kg/t</div>
                <div>🌾 TAPIOKA: <span id="live_tapioka_ton">0.00</span> kg/t</div>
                <div>🧪 CA ADDITIVE: <span id="live_ca_additive_ton">0.00</span> kg/t</div>
                <div>💧 CAUSTIK SODA: <span id="live_caustik_soda_ton">0.00</span> kg/t</div>
                <div>🧂 BORAK: <span id="live_borak_ton">0.00</span> kg/t</div>
                <div>⛽ SOLAR: <span id="live_solar_ton">0.00</span> l/t</div>
                <div>🎨 TINTA: <span id="live_tinta_pcs">0.000</span> gr/p</div>
                <div>🍯 GLUE F: <span id="live_glue_f_ton">0.00</span> kg/t</div>
            </div>

            <div style="display:flex; gap: 15px; justify-content: flex-end;">
                <?php if ($is_edit): ?><a href="rekap_bulanan.php" class="btn-batal-modern">Batal Edit</a><?php endif; ?>
                <button type="submit" name="simpan_manual" class="btn-submit-modern" style="<?= $is_edit ? 'background:#0ea5e9 !important; box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.2) !important;' : '' ?>">
                    <?= $is_edit ? "💾 Simpan Perubahan" : "💾 Simpan Rekap" ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card" style="padding: 18px 24px; margin-bottom: 12px;">
    <h2 style="margin-top:0; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px;">📊 Tabel Analisis Rekapitulasi DATABASE H2 </h2>
    <form method="GET" action="" class="filter-box" style="margin-bottom: 0;">
        <div class="form-group" style="min-width:140px;">
            <label>Filter Tahun Laporan</label>
            <select name="filter_tahun">
                <?php foreach ($list_tahun_db as $th): ?>
                    <option value="<?= $th ?>" <?= $filter_tahun == $th ? 'selected' : '' ?>><?= $th ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="btn-group-filter">
            <button type="submit" class="btn-search-modern">🔍 Cari Tahun</button>
        </div>
    </form>
</div>

<div class="table-responsive">
    <table class="table-premium">
        <thead>
            <tr>
                <th class="text-center stk-1" rowspan="2">No</th>
                <th class="text-center stk-2" rowspan="2">TAHUN</th>
                <th class="text-center stk-3" rowspan="2">BULAN</th>
                <th class="text-center stk-4" rowspan="2">AKSI DATA</th>
                <th colspan="31" class="text-center" style="background-color: #0369a1; color: #fff;">DATA INPUT UTAMA</th>
                <th colspan="8" class="text-center" style="background-color: #15803d; color: #fff;">RASIO & RUMUS EXCEL OTOMATIS</th>
            </tr>
            <tr>
                <th style="background-color:#0ea5e9;">Pakai paper</th><th style="background-color:#0ea5e9;">avg LBr CORR</th><th style="background-color:#0ea5e9;">CORRUGATOR</th><th style="background-color:#0ea5e9;">Speed Corr</th><th style="background-color:#0ea5e9;">SPEED FLEXO</th><th style="background-color:#0ea5e9;">FLEXO ST</th><th style="background-color:#0ea5e9;">FLEXO INLINE</th><th style="background-color:#0ea5e9;">GLUE</th><th style="background-color:#0ea5e9;">SLITTER</th><th style="background-color:#0ea5e9;">STITCH</th><th style="background-color:#0ea5e9;">STITCH AUTO</th><th style="background-color:#0ea5e9;">LEM finishing</th><th style="background-color:#0ea5e9;">TSTB (kg)</th><th style="background-color:#0ea5e9;">DELIVERY</th><th style="background-color:#0ea5e9;">DOWNTIME</th><th style="background-color:#0ea5e9;">WASTE (%)</th><th style="background-color:#0ea5e9;">Waste (kg)</th><th style="background-color:#0ea5e9;">BATU BARA</th><th style="background-color:#0ea5e9;">TAPIOKA</th><th style="background-color:#0ea5e9;">CA ADDITIVE</th><th style="background-color:#0ea5e9;">CAUSTIK SODA</th><th style="background-color:#0ea5e9;">BORAK</th><th style="background-color:#0ea5e9;">SOLAR</th><th style="background-color:#0ea5e9;">TINTA</th><th style="background-color:#0ea5e9;">KIRIM WASTE</th><th style="background-color:#0ea5e9;">KAWAT AUTO</th><th style="background-color:#0ea5e9;">KAWAT MANUAL</th><th style="background-color:#0ea5e9;">LAKBAN KERTAS 1</th><th style="background-color:#0ea5e9;">LAKBAN KERTAS 2</th><th style="background-color:#0ea5e9;">STRIPING BAND</th><th style="background-color:#0ea5e9;">TALI RAPIAH</th>
                <th style="background-color:#16a34a; color:#fff;">BATU BARA (kg/TON)</th><th style="background-color:#16a34a; color:#fff;">TAPIOKA (kg/TON)</th><th style="background-color:#16a34a; color:#fff;">CA ADDITIVE (kg/TON)</th><th style="background-color:#16a34a; color:#fff;">CAUSTIK SODA (kg/TON)</th><th style="background-color:#16a34a; color:#fff;">BORAK (kg/TON)</th><th style="background-color:#16a34a; color:#fff;">SOLAR (liter/TON)</th><th style="background-color:#16a34a; color:#fff;">TINTA (Gr/PCS)</th><th style="background-color:#16a34a; color:#fff;">GLUE F (kg/TON)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $tot_pakai_paper = 0; $tot_avg_lbr_corr = 0; $tot_corrugator_kg = 0; $tot_speed_corr = 0; $tot_speed_flexo = 0;
            $tot_flexo_st = 0; $tot_flexo_inline = 0; $tot_glue_pcs = 0; $tot_slitter_pcs = 0; $tot_stitch_pcs = 0; $tot_stitch_auto = 0;
            $tot_lem_finishing = 0; $tot_tstb_kg = 0; $tot_delivery_kg = 0; $tot_downtime_mnt = 0; $tot_waste_kg = 0;
            $tot_batu_bara = 0; $tot_tapioka = 0; $tot_ca_additive = 0; $tot_caustik_soda = 0; $tot_borak = 0; $tot_solar = 0; $tot_tinta = 0;
            $tot_kirim_waste = 0; $tot_kawat_auto = 0; $tot_kawat_manual = 0; $tot_lakban_kertas_1 = 0; $tot_lakban_kertas_2 = 0;
            $tot_striping_band_5_ml = 0; $tot_tali_rapiah = 0;

            if (count($rekap_data) > 0): $no = 1; ?>
                <?php foreach ($rekap_data as $row): 
                    $tot_pakai_paper += $row['pakai_paper']; $tot_avg_lbr_corr += $row['avg_lbr_corr']; $tot_corrugator_kg += $row['corrugator_kg'];
                    $tot_speed_corr += $row['speed_corr']; $tot_speed_flexo += $row['speed_flexo']; $tot_flexo_st += $row['flexo_st'];
                    $tot_flexo_inline += $row['flexo_inline']; $tot_glue_pcs += $row['glue_pcs']; $tot_slitter_pcs += $row['slitter_pcs'];
                    $tot_stitch_pcs += $row['stitch_pcs']; $tot_stitch_auto += $row['stitch_auto']; $tot_lem_finishing += $row['lem_finishing'];
                    $tot_tstb_kg += $row['tstb_kg']; $tot_delivery_kg += $row['delivery_kg']; $tot_downtime_mnt += $row['downtime_mnt'];
                    $tot_waste_kg += $row['waste_kg']; $tot_batu_bara += $row['batu_bara']; $tot_tapioka += $row['tapioka'];
                    $tot_ca_additive += $row['ca_additive']; $tot_caustik_soda += $row['caustik_soda']; $tot_borak += $row['borak'];
                    $tot_solar += $row['solar']; $tot_tinta += $row['tinta']; $tot_kirim_waste += $row['kirim_waste'];
                    $tot_kawat_auto += $row['kawat_auto']; $tot_kawat_manual += $row['kawat_manual']; $tot_lakban_kertas_1 += $row['lakban_kertas_1'];
                    $tot_lakban_kertas_2 += $row['lakban_kertas_2']; $tot_striping_band_5_ml += $row['striping_band_5_ml']; $tot_tali_rapiah += $row['tali_rapiah'];

                    $ton_tstb = $row['tstb_kg'] > 0 ? ($row['tstb_kg'] / 1000) : 0;
                    $total_pcs = $row['flexo_st'] + $row['flexo_inline'];
                    
                    $r_batu_bara   = $ton_tstb > 0 ? ($row['batu_bara'] / $ton_tstb) : 0;
                    $r_tapioka     = $ton_tstb > 0 ? ($row['tapioka'] / $ton_tstb) : 0;
                    $r_ca_additive = $ton_tstb > 0 ? ($row['ca_additive'] / $ton_tstb) : 0;
                    $r_caustik     = $ton_tstb > 0 ? ($row['caustik_soda'] / $ton_tstb) : 0;
                    $r_borak       = $ton_tstb > 0 ? ($row['borak'] / $ton_tstb) : 0;
                    $r_solar       = $ton_tstb > 0 ? ($row['solar'] / $ton_tstb) : 0;
                    $r_tinta       = $total_pcs > 0 ? (($row['tinta'] * 1000) / $total_pcs) : 0;
                    $r_glue_f      = $ton_tstb > 0 ? ($row['lem_finishing'] / $ton_tstb) : 0;
                ?>
                    <tr>
                        <td class="text-center stk-1"><?= $no++ ?></td>
                        <td class="text-center stk-2"><?= $row['tahun'] ?></td>
                        <td class="text-center stk-3"><?= htmlspecialchars($row['bulan']) ?></td>
                        <td class="text-center stk-4">
                            <?php if ($user_role != 'Viewer'): ?>
                                <a href="rekap_bulanan.php?edit=<?= $row['id'] ?>" class="btn-aksi-edit">Edit</a>
                                <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $row['id'] ?>, 'Hapus Data?')" class="btn-aksi-hapus">Hapus</a>
                            <?php else: ?>
                                <span style="font-size:10px; color:#94a3b8;">Terbatas</span>
                            <?php endif; ?>
                        </td>
                        <td><?= number_format($row['pakai_paper'], 0, ',', '.') ?></td><td><?= number_format($row['avg_lbr_corr'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['corrugator_kg'], 0, ',', '.') ?></td><td><?= number_format($row['speed_corr'], 1, ',', '.') ?></td>
                        <td><?= number_format($row['speed_flexo'], 1, ',', '.') ?></td><td><?= number_format($row['flexo_st'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['flexo_inline'], 0, ',', '.') ?></td><td><?= number_format($row['glue_pcs'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['slitter_pcs'], 0, ',', '.') ?></td><td><?= number_format($row['stitch_pcs'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['stitch_auto'], 0, ',', '.') ?></td><td><?= number_format($row['lem_finishing'], 0, ',', '.') ?></td>
                        <td style="font-weight:700; color:#0ea5e9;"><?= number_format($row['tstb_kg'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['delivery_kg'], 0, ',', '.') ?></td><td><?= number_format($row['downtime_mnt'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['waste_persen'], 1, ',', '.') ?>%</td><td><?= number_format($row['waste_kg'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['batu_bara'], 0, ',', '.') ?></td><td><?= number_format($row['tapioka'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['ca_additive'], 0, ',', '.') ?></td><td><?= number_format($row['caustik_soda'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['borak'], 0, ',', '.') ?></td><td><?= number_format($row['solar'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['tinta'], 0, ',', '.') ?></td><td><?= number_format($row['kirim_waste'], 0, ',', '.') ?></td>
                        <td><?= number_format($row['kawat_auto'], 2, ',', '.') ?></td><td><?= number_format($row['kawat_manual'], 2, ',', '.') ?></td>
                        <td><?= number_format($row['lakban_kertas_1'], 2, ',', '.') ?></td><td><?= number_format($row['lakban_kertas_2'], 2, ',', '.') ?></td>
                        <td><?= number_format($row['striping_band_5_ml'], 2, ',', '.') ?></td><td><?= number_format($row['tali_rapiah'], 2, ',', '.') ?></td>
                        
                        <td style="background-color:#f0fdf4; color:#166534;"><?= number_format($r_batu_bara, 2, ',', '.') ?></td>
                        <td style="background-color:#f0fdf4; color:#166534;"><?= number_format($r_tapioka, 2, ',', '.') ?></td>
                        <td style="background-color:#f0fdf4; color:#166534;"><?= number_format($r_ca_additive, 2, ',', '.') ?></td>
                        <td style="background-color:#f0fdf4; color:#166534;"><?= number_format($r_caustik, 2, ',', '.') ?></td>
                        <td style="background-color:#f0fdf4; color:#166534;"><?= number_format($r_borak, 2, ',', '.') ?></td>
                        <td style="background-color:#f0fdf4; color:#166534;"><?= number_format($r_solar, 2, ',', '.') ?></td>
                        <td style="background-color:#f0fdf4; color:#166534;"><?= number_format($r_tinta, 3, ',', '.') ?></td>
                        <td style="background-color:#f0fdf4; color:#166534;"><?= number_format($r_glue_f, 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php 
                    $row_count = count($rekap_data);
                    $avg_lbr_corr_val = $row_count > 0 ? $tot_avg_lbr_corr / $row_count : 0;
                    $avg_speed_corr   = $row_count > 0 ? $tot_speed_corr / $row_count : 0;
                    $avg_speed_flexo  = $row_count > 0 ? $tot_speed_flexo / $row_count : 0;
                    $avg_waste_persen = $tot_pakai_paper > 0 ? ($tot_waste_kg / $tot_pakai_paper) * 100 : 0;
                    $avg_kawat_auto   = $row_count > 0 ? $tot_kawat_auto / $row_count : 0;
                    $avg_kawat_manual = $row_count > 0 ? $tot_kawat_manual / $row_count : 0;
                    $avg_lakban_1     = $row_count > 0 ? $tot_lakban_kertas_1 / $row_count : 0;
                    $avg_lakban_2     = $row_count > 0 ? $tot_lakban_kertas_2 / $row_count : 0;
                    $avg_striping     = $row_count > 0 ? $tot_striping_band_5_ml / $row_count : 0;
                    $avg_tali         = $row_count > 0 ? $tot_tali_rapiah / $row_count : 0;

                    $ton_tstb_tot = $tot_tstb_kg > 0 ? ($tot_tstb_kg / 1000) : 0;
                    $tot_pcs_flexo = $tot_flexo_st + $tot_flexo_inline;
                    $rt_batu_bara   = $ton_tstb_tot > 0 ? ($tot_batu_bara / $ton_tstb_tot) : 0;
                    $rt_tapioka     = $ton_tstb_tot > 0 ? ($tot_tapioka / $ton_tstb_tot) : 0;
                    $rt_ca_additive = $ton_tstb_tot > 0 ? ($tot_ca_additive / $ton_tstb_tot) : 0;
                    $rt_caustik     = $ton_tstb_tot > 0 ? ($tot_caustik_soda / $ton_tstb_tot) : 0;
                    $rt_borak       = $ton_tstb_tot > 0 ? ($tot_borak / $ton_tstb_tot) : 0;
                    $rt_solar       = $ton_tstb_tot > 0 ? ($tot_solar / $ton_tstb_tot) : 0;
                    $rt_tinta       = $tot_pcs_flexo > 0 ? (($tot_tinta * 1000) / $tot_pcs_flexo) : 0;
                    $rt_glue_f      = $ton_tstb_tot > 0 ? ($tot_lem_finishing / $ton_tstb_tot) : 0;
                ?>
                
                <tr class="total-row">
                    <td class="text-center stk-1"></td>
                    <td class="text-center stk-2">TOTAL</td>
                    <td class="text-center stk-3">REKAP</td>
                    <td class="text-center stk-4"></td>
                    
                    <td><?= number_format($tot_pakai_paper, 0, ',', '.') ?></td><td><?= number_format($avg_lbr_corr_val, 0, ',', '.') ?></td>
                    <td><?= number_format($tot_corrugator_kg, 0, ',', '.') ?></td><td><?= number_format($avg_speed_corr, 1, ',', '.') ?></td>
                    <td><?= number_format($avg_speed_flexo, 1, ',', '.') ?></td><td><?= number_format($tot_flexo_st, 0, ',', '.') ?></td>
                    <td><?= number_format($tot_flexo_inline, 0, ',', '.') ?></td><td><?= number_format($tot_glue_pcs, 0, ',', '.') ?></td>
                    <td><?= number_format($tot_slitter_pcs, 0, ',', '.') ?></td><td><?= number_format($tot_stitch_pcs, 0, ',', '.') ?></td>
                    <td><?= number_format($tot_stitch_auto, 0, ',', '.') ?></td><td><?= number_format($tot_lem_finishing, 0, ',', '.') ?></td>
                    <td style="color:#0369a1;"><?= number_format($tot_tstb_kg, 0, ',', '.') ?></td>
                    <td><?= number_format($tot_delivery_kg, 0, ',', '.') ?></td><td><?= number_format($tot_downtime_mnt, 0, ',', '.') ?></td>
                    <td><?= number_format($avg_waste_persen, 1, ',', '.') ?>%</td><td><?= number_format($tot_waste_kg, 0, ',', '.') ?></td>
                    <td><?= number_format($tot_batu_bara, 0, ',', '.') ?></td><td><?= number_format($tot_tapioka, 0, ',', '.') ?></td>
                    <td><?= number_format($tot_ca_additive, 0, ',', '.') ?></td><td><?= number_format($tot_caustik_soda, 0, ',', '.') ?></td>
                    <td><?= number_format($tot_borak, 0, ',', '.') ?></td><td><?= number_format($tot_solar, 0, ',', '.') ?></td>
                    <td><?= number_format($tot_tinta, 0, ',', '.') ?></td><td><?= number_format($tot_kirim_waste, 0, ',', '.') ?></td>
                    <td><?= number_format($avg_kawat_auto, 2, ',', '.') ?></td><td><?= number_format($avg_kawat_manual, 2, ',', '.') ?></td>
                    <td><?= number_format($avg_lakban_1, 2, ',', '.') ?></td><td><?= number_format($avg_lakban_2, 2, ',', '.') ?></td>
                    <td><?= number_format($avg_striping, 2, ',', '.') ?></td><td><?= number_format($avg_tali, 2, ',', '.') ?></td>
                    
                    <td style="background-color:#dcfce7; color:#14532d;"><?= number_format($rt_batu_bara, 2, ',', '.') ?></td>
                    <td style="background-color:#dcfce7; color:#14532d;"><?= number_format($rt_tapioka, 2, ',', '.') ?></td>
                    <td style="background-color:#dcfce7; color:#14532d;"><?= number_format($rt_ca_additive, 2, ',', '.') ?></td>
                    <td style="background-color:#dcfce7; color:#14532d;"><?= number_format($rt_caustik, 2, ',', '.') ?></td>
                    <td style="background-color:#dcfce7; color:#14532d;"><?= number_format($rt_borak, 2, ',', '.') ?></td>
                    <td style="background-color:#dcfce7; color:#14532d;"><?= number_format($rt_solar, 2, ',', '.') ?></td>
                    <td style="background-color:#dcfce7; color:#14532d;"><?= number_format($rt_tinta, 3, ',', '.') ?></td>
                    <td style="background-color:#dcfce7; color:#14532d;"><?= number_format($rt_glue_f, 2, ',', '.') ?></td>
                </tr>
            <?php else: ?>
                <tr><td colspan="42" class="text-center" style="padding: 32px; color: #94a3b8;">Belum ada riwayat data rekap bulanan yang diinput untuk tahun ini.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<form id="formHapusGlobal" method="POST" style="display: none;">
    <input type="hidden" name="hapus_id" id="inputHapusId">
    <input type="hidden" name="hapus_data" value="1">
</form>

<script>
    // 🚀 FUNGSI TOGGLE SAKTI
    function toggleFormInput() {
        let content = document.getElementById('formCollapsibleContent');
        let icon = document.getElementById('formToggleIcon');
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.innerText = '▲ KLIK UNTUK TUTUP PANEL';
            localStorage.setItem('rekapFormState', 'open');
        } else {
            content.style.display = 'none';
            icon.innerText = '▼ KLIK UNTUK BUKA PANEL INPUT';
            localStorage.setItem('rekapFormState', 'closed');
        }
    }

    function konfirmasiHapus(id, pesan) {
        if (confirm(pesan)) {
            document.getElementById('inputHapusId').value = id;
            document.getElementById('formHapusGlobal').submit();
        }
    }

    function gantiTab(tabId, el) {
        document.querySelectorAll('.tab-content').forEach(e => e.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(e => e.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        el.classList.add('active');
    }

    function hitungLiveRasio() {
        const tstb_kg = parseFloat(document.getElementById('tstb_kg').value) || 0;
        const ton = tstb_kg > 0 ? (tstb_kg / 1000) : 0;
        const batu_bara = parseFloat(document.getElementById('batu_bara').value) || 0;
        const tapioka = parseFloat(document.getElementById('tapioka').value) || 0;
        const ca_additive = parseFloat(document.getElementById('ca_additive').value) || 0;
        const caustik_soda = parseFloat(document.getElementById('caustik_soda').value) || 0;
        const borak = parseFloat(document.getElementById('borak').value) || 0;
        const solar = parseFloat(document.getElementById('solar').value) || 0;
        const lem_finishing = parseFloat(document.getElementById('lem_finishing').value) || 0;
        const flexo_st = parseFloat(document.getElementById('flexo_st').value) || 0;
        const flexo_inline = parseFloat(document.getElementById('flexo_inline').value) || 0;
        const total_pcs_flexo = flexo_st + flexo_inline;
        const tinta = parseFloat(document.getElementById('tinta').value) || 0;

        document.getElementById('live_batu_bara_ton').innerText = ton > 0 ? (batu_bara / ton).toFixed(2) : "0.00";
        document.getElementById('live_tapioka_ton').innerText = ton > 0 ? (tapioka / ton).toFixed(2) : "0.00";
        document.getElementById('live_ca_additive_ton').innerText = ton > 0 ? (ca_additive / ton).toFixed(2) : "0.00";
        document.getElementById('live_caustik_soda_ton').innerText = ton > 0 ? (caustik_soda / ton).toFixed(2) : "0.00";
        document.getElementById('live_borak_ton').innerText = ton > 0 ? (borak / ton).toFixed(2) : "0.00";
        document.getElementById('live_solar_ton').innerText = ton > 0 ? (solar / ton).toFixed(2) : "0.00";
        document.getElementById('live_tinta_pcs').innerText = total_pcs_flexo > 0 ? ((tinta * 1000) / total_pcs_flexo).toFixed(3) : "0.000";
        document.getElementById('live_glue_f_ton').innerText = ton > 0 ? (lem_finishing / ton).toFixed(2) : "0.00";
    }

    window.onload = function() {
        ['tstb_kg', 'batu_bara', 'tapioka', 'ca_additive', 'caustik_soda', 'borak', 'solar', 'lem_finishing', 'flexo_st', 'flexo_inline', 'tinta'].forEach(id => {
            const el = document.getElementById(id); if(el) el.addEventListener('input', hitungLiveRasio);
        });

        // 🚀 BACA MEMORI LOCALSTORAGE UNTUK TOGGLE PANEL
        let formState = localStorage.getItem('rekapFormState');
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
