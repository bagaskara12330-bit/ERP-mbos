<?php
require_once 'auth.php';
require_akses('dash_prod');// 1. AMBIL FILTER DINAMIS TAHUN, BULAN, & REGU
try {
    $list_tahun = $pdo->query("SELECT DISTINCT tahun FROM mbos_regu ORDER BY tahun DESC")->fetchAll(PDO::FETCH_COLUMN);
    $list_bulan = $pdo->query("SELECT DISTINCT bulan FROM mbos_regu ORDER BY FIELD(bulan, 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember')")->fetchAll(PDO::FETCH_COLUMN);
    $list_regu  = $pdo->query("SELECT DISTINCT regu FROM mbos_regu ORDER BY regu ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $list_tahun = []; $list_bulan = []; $list_regu = ['TASKIM', 'SAMSUL'];
}

// 🚀 KAMUS BULAN GLOBAL UNTUK FILTER OTOMATIS
$nama_bulan_id = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
$bulan_sekarang = $nama_bulan_id[date('m')];
$tahun_sekarang = date('Y');

// Pastikan bulan & tahun sekarang tetap ada di pilihan dropdown meskipun belum ada data
if (!in_array($tahun_sekarang, $list_tahun)) { array_unshift($list_tahun, $tahun_sekarang); }
if (!in_array($bulan_sekarang, $list_bulan)) { $list_bulan[] = $bulan_sekarang; }

// 🚀 DEFAULT FILTER DIUBAH KE BULAN DAN TAHUN SAAT INI
$filter_tahun = isset($_GET['filter_tahun']) ? $_GET['filter_tahun'] : $tahun_sekarang;
$filter_bulan = isset($_GET['filter_bulan']) ? $_GET['filter_bulan'] : $bulan_sekarang;
$filter_regu  = isset($_GET['filter_regu']) ? $_GET['filter_regu'] : '';

// Mapping variasi nama bulan dari Excel / Input Manual
$month_map = [
    'Januari' => ['Januari', 'jan'], 'Februari' => ['Februari', 'feb'], 'Maret' => ['Maret', 'mar'],
    'April' => ['April', 'apr'], 'Mei' => ['Mei', 'may', 'Mei'], 'Juni' => ['Juni', 'jun'],
    'Juli' => ['Juli', 'jul'], 'Agustus' => ['Agustus', 'aug'], 'September' => ['September', 'sep'],
    'Oktober' => ['Oktober', 'oct'], 'November' => ['November', 'nov'], 'Desember' => ['Desember', 'dec']
];
$param_bulan = isset($month_map[$filter_bulan]) ? $month_map[$filter_bulan] : [$filter_bulan];
$in_clause = implode(',', array_fill(0, count($param_bulan), '?'));

// 2. AMBIL & HITUNG DATA KOTAK WIDGET UTAMA
$card_prod_h2 = 0;          $card_tstb_h2 = 0;          $card_waste_persen = 0;
$card_kirim_h12 = 0;        $card_tstb_h1 = 0;          $card_target_kirim = 0;
$card_delivery_persen = 0;  $total_h1_target = 0;       $total_h2_target = 0;

// Variabel Penampung Persentase Performa Grafik
$perf_h1_p = 0; $perf_h2_p = 0;

try {
    // Data KPI Bersifat Global (Tidak dipengaruhi filter regu)
    $sql_kpi_blueprint = "SELECT SUM(h12_kirim + h2_kirim) as total_kirim_gabungan, SUM(h1_tstb) as total_h1_tstb, SUM(h2_tstb) as total_h2_tstb, SUM(h1_target) as total_h1_target, SUM(h2_target) as total_h2_target FROM mbos_kpi WHERE tahun = ? AND bulan IN ($in_clause)";
    $stmt_kpi = $pdo->prepare($sql_kpi_blueprint);
    $stmt_kpi->execute(array_merge([$filter_tahun], $param_bulan));
    $res_kpi = $stmt_kpi->fetch(PDO::FETCH_ASSOC);
    
    if ($res_kpi) {
        $card_kirim_h12  = $res_kpi['total_kirim_gabungan'] ?? 0;
        $card_tstb_h1    = $res_kpi['total_h1_tstb'] ?? 0;
        $card_tstb_h2    = $res_kpi['total_h2_tstb'] ?? 0;
        $total_h1_target = $res_kpi['total_h1_target'] ?? 0;
        $total_h2_target = $res_kpi['total_h2_target'] ?? 0;

        $perf_h1_p = ($total_h1_target > 0) ? ($card_tstb_h1 / $total_h1_target) * 100 : 0;
        $perf_h2_p = ($total_h2_target > 0) ? ($card_tstb_h2 / $total_h2_target) * 100 : 0;
    }

    // Data Produksi (Terpengaruh oleh Filter Regu)
    $params_regu = array_merge([$filter_tahun], $param_bulan);
    $sql_regu_blueprint = "SELECT SUM(kg_produksi) as total_produksi, SUM(waste) as total_waste_kg, SUM(pakai_kertas) as total_pakai_kertas FROM mbos_regu WHERE tahun = ? AND bulan IN ($in_clause)";
    
    if ($filter_regu != '') {
        $sql_regu_blueprint .= " AND regu = ?";
        $params_regu[] = $filter_regu;
    }

    $stmt_regu = $pdo->prepare($sql_regu_blueprint);
    $stmt_regu->execute($params_regu);
    $res_regu = $stmt_regu->fetch(PDO::FETCH_ASSOC);
    
    if ($res_regu) {
        $card_prod_h2 = $res_regu['total_produksi'] ?? 0;
        $total_waste_kg = $res_regu['total_waste_kg'] ?? 0;
        $total_pakai_kertas = $res_regu['total_pakai_kertas'] ?? 0;
        if ($total_pakai_kertas > 0) {
            $card_waste_persen = ($total_waste_kg / $total_pakai_kertas) * 100;
        }
    }

    $card_target_kirim = $total_h1_target + $total_h2_target;
    if ($card_target_kirim > 0) {
        $card_delivery_persen = ($card_kirim_h12 / $card_target_kirim) * 100;
    }
} catch (PDOException $e) {}

// 3. AMBIL DATA GRAFIK & HITUNG TOTAL TASKIM/SAMSUL
$labels_chart_prod = []; $daily_regu_data = []; $all_regus = [];
$total_taskim = 0; $total_samsul = 0;

try {
    $params_chart = array_merge([$filter_tahun], $param_bulan);
    $sql_regu_chart = "SELECT tanggal, regu, SUM(kg_produksi) as total_prod, SUM(target_produksi) as total_tgt, SUM(waste) as total_waste, SUM(pakai_kertas) as total_kertas, SUM(dt) as total_dt FROM mbos_regu WHERE tahun = ? AND bulan IN ($in_clause)";
    
    if ($filter_regu != '') {
        $sql_regu_chart .= " AND regu = ?";
        $params_chart[] = $filter_regu;
    }
    
    $sql_regu_chart .= " GROUP BY tanggal, regu ORDER BY tanggal ASC";
    $stmt_rc = $pdo->prepare($sql_regu_chart);
    $stmt_rc->execute($params_chart);
    $rows_rc = $stmt_rc->fetchAll(PDO::FETCH_ASSOC);

    foreach($rows_rc as $r) {
        $tgl_lbl = intval(date('d', strtotime($r['tanggal'])));
        if (!isset($daily_regu_data[$tgl_lbl])) {
            $daily_regu_data[$tgl_lbl] = ['target' => 0, 'total_waste' => 0, 'total_kertas' => 0];
        }
        $daily_regu_data[$tgl_lbl][$r['regu']] = $r['total_prod'];
        $daily_regu_data[$tgl_lbl][$r['regu'] . '_dt'] = $r['total_dt'] ?? 0; 
        $daily_regu_data[$tgl_lbl]['target'] += $r['total_tgt'];
        $daily_regu_data[$tgl_lbl]['total_waste'] += $r['total_waste'] ?? 0;
        $daily_regu_data[$tgl_lbl]['total_kertas'] += $r['total_kertas'] ?? 0;

        if (!in_array($r['regu'], $all_regus)) { $all_regus[] = $r['regu']; }
        
        // Menghitung Total Spesifik Taskim & Samsul
        if (strtoupper($r['regu']) === 'TASKIM') { $total_taskim += $r['total_prod']; }
        if (strtoupper($r['regu']) === 'SAMSUL') { $total_samsul += $r['total_prod']; }
    }
    ksort($daily_regu_data); 
    $labels_chart_prod = array_keys($daily_regu_data);
} catch (PDOException $e) {}

$chart_datasets_json = []; $chart_dt_datasets_json = [];

// Palet warna untuk regu-regu tambahan di masa depan
$colorPalette = ['#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#14b8a6', '#ec4899'];
$colorIndex = 0;

foreach($all_regus as $rgName) {
    $rgData = []; $rgDtData = [];
    foreach($labels_chart_prod as $tgl) {
        $rgData[] = $daily_regu_data[$tgl][$rgName] ?? 0;
        $rgDtData[] = $daily_regu_data[$tgl][$rgName . '_dt'] ?? 0;
    }
    
    $color = '#3b82f6'; 
    if (strtoupper($rgName) === 'TASKIM') { $color = '#1e3a8a'; } 
    elseif (strtoupper($rgName) === 'SAMSUL') { $color = '#38bdf8'; }
    else { $color = $colorPalette[$colorIndex % count($colorPalette)]; $colorIndex++; }

    // 🚀 INJEKSI PROPERTI INTERAKTIF UNTUK CHART PRODUKSI (BAR)
    $chart_datasets_json[] = [
        'type' => 'bar',
        'label' => $rgName,
        'data' => $rgData,
        'backgroundColor' => $color,
        'hoverBackgroundColor' => '#0f172a', // Warna gelap saat dihover
        'borderColor' => 'transparent',
        'hoverBorderColor' => '#ffffff', // Garis putih menyala saat dihover
        'borderWidth' => 2,
        'borderRadius' => 4,
        'barPercentage' => 0.6
    ];

    // 🚀 INJEKSI PROPERTI INTERAKTIF UNTUK CHART DOWNTIME (LINE)
    $chart_dt_datasets_json[] = [
        'type' => 'line',
        'label' => $rgName,
        'data' => $rgDtData,
        'borderColor' => $color,
        'backgroundColor' => 'transparent',
        'borderWidth' => 3,
        'pointBackgroundColor' => '#ffffff', // Titik putih
        'pointBorderColor' => $color,
        'pointHoverBackgroundColor' => $color, // Titik menyala saat dihover
        'pointHoverBorderColor' => '#ffffff',
        'pointHoverRadius' => 6,
        'tension' => 0.3 // Garis luwes melengkung
    ];
}

$targetData = [];
foreach($labels_chart_prod as $tgl) { $targetData[] = $daily_regu_data[$tgl]['target'] ?? 0; }

// 🚀 INJEKSI PROPERTI INTERAKTIF UNTUK GARIS TARGET
$chart_datasets_json[] = [
    'type' => 'line',
    'label' => 'Target (KG)',
    'data' => $targetData,
    'borderColor' => '#eab308', 
    'backgroundColor' => 'transparent',
    'borderWidth' => 3,
    'pointBackgroundColor' => '#ffffff',
    'pointBorderColor' => '#eab308',
    'pointHoverBackgroundColor' => '#eab308',
    'pointHoverBorderColor' => '#ffffff',
    'pointHoverRadius' => 6,
    'tension' => 0.3,
    'stacked' => false
];

$wasteDataArr = [];
foreach($labels_chart_prod as $tgl) {
    $w = $daily_regu_data[$tgl]['total_waste'] ?? 0;
    $k = $daily_regu_data[$tgl]['total_kertas'] ?? 0;
    $wasteDataArr[] = $k > 0 ? ($w / $k) * 100 : 0; 
}

// 🚀 INJEKSI PROPERTI INTERAKTIF UNTUK CHART WASTE
$chart_waste_datasets_json = [[
    'type' => 'bar',
    'label' => 'Waste Harian (%)',
    'data' => $wasteDataArr,
    'backgroundColor' => '#dc2626', 
    'hoverBackgroundColor' => '#991b1b', // Warna merah gelap saat dihover
    'borderColor' => 'transparent',
    'hoverBorderColor' => '#ffffff',
    'borderWidth' => 2,
    'borderRadius' => 4,
    'barPercentage' => 0.6
]];

// PANGGIL MASTER HEADER
$page_title = "H2 BASE — Dashboard Utama";
$active_page = "dashboard";
require 'header.php';
?>

<style>
    /* 🚀 ANIMASI ENTRANCE GLOBAL (MUNCUL MELAYANG) */
    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(40px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* 🔥 PERBAIKAN CSS FILTER AGAR RAPI & SEJAJAR 🔥 */
    .filter-container { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; width: 100%; animation: slideUpFade 0.4s ease-out forwards;}
    .filter-container .form-group { flex: 1; min-width: 180px; margin-bottom: 0; }
    .filter-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    
    .btn-filter, .btn-reset { 
        padding: 0 20px; border-radius: 8px; font-size: 13px; font-weight: 700; 
        cursor: pointer; transition: all 0.2s ease; text-decoration: none; 
        display: inline-flex; align-items: center; justify-content: center; 
        height: 41px; box-sizing: border-box; 
    }
    .btn-filter { background: #0f172a; color: white; border: none; }
    .btn-filter:hover { background: #1e293b; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.3); transform: translateY(-2px); }
    .btn-filter:active { transform: scale(0.95); }
    
    .btn-reset { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }
    .btn-reset:hover { background: #f1f5f9; color: #0f172a; }
    /* ============================================== */

    .grid-cards { display: grid; grid-template-columns: repeat(12, 1fr); gap: 16px; margin-bottom: 24px; }
    
    .widget { 
        background: white; padding: 18px; border-radius: 10px; 
        border: 1px solid #e2e8f0; border-top: 4px solid #cbd5e1; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; 
        flex-direction: column; justify-content: center; 
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        opacity: 0; 
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    /* 🚀 STAGGERED DELAY UNTUK WIDGET */
    .widget:nth-child(1) { animation-delay: 0.1s; }
    .widget:nth-child(2) { animation-delay: 0.2s; }
    .widget:nth-child(3) { animation-delay: 0.3s; }
    .widget:nth-child(4) { animation-delay: 0.4s; }
    .widget:nth-child(5) { animation-delay: 0.5s; }
    .widget:nth-child(6) { animation-delay: 0.6s; }
    .widget:nth-child(7) { animation-delay: 0.7s; }
    .widget:nth-child(8) { animation-delay: 0.8s; }
    .widget:nth-child(9) { animation-delay: 0.9s; }

    /* 🚀 EFEK HOVER PREMIUM PADA WIDGET */
    .widget:hover {
        transform: translateY(-8px) scale(1.02);
        box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15);
        cursor: pointer;
    }
    
    /* 🚀 STRUKTUR PIRAMIDA TERBALIK (KECE BADAI) */
    .w-span-3 { grid-column: span 3; } /* 4 Kotak (Baris 1) */
    .w-span-4 { grid-column: span 4; } /* 3 Kotak (Baris 2) */
    .w-span-6 { grid-column: span 6; } /* 2 Kotak (Baris 3) */
    
    .widget .title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;}
    .widget .value { font-size: 22px; font-weight: 900; color: #0f172a; margin: 4px 0; white-space: nowrap; }
    .widget .unit { font-size: 12px; font-weight: 600; color: #94a3b8; margin-left: 4px; }
    .widget .formula { font-size: 9px; font-weight: 700; color: #0ea5e9; background: #f0f9ff; padding: 3px 6px; border-radius: 4px; width: fit-content; margin-top: auto;}
    
    .performance-section { display: grid; grid-template-columns: 1fr 2.2fr; gap: 24px; margin-bottom: 24px; align-items: stretch; }
    .equal-section { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; align-items: stretch; }
    
    .chart-card { 
        background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; 
        display: flex; flex-direction: column; min-height: 480px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        transition: all 0.3s ease;
        opacity: 0;
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    /* 🚀 STAGGERED DELAY UNTUK GRAFIK */
    .performance-section .chart-card:nth-child(1) { animation-delay: 0.5s; }
    .performance-section .chart-card:nth-child(2) { animation-delay: 0.6s; }
    .equal-section .chart-card:nth-child(1) { animation-delay: 0.7s; }
    .equal-section .chart-card:nth-child(2) { animation-delay: 0.8s; }

    .chart-card:hover { box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }

    .chart-card h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 16px; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
    .chart-wrapper { position: relative; flex-grow: 1; height: 100%; min-height: 380px; width: 100%; }

    /* RESPONSIVE CSS */
    @media (max-width: 1200px) { 
        .w-span-3, .w-span-4 { grid-column: span 6; } /* 2 Kotak per baris */
        .w-span-6 { grid-column: span 12; } 
    }
    @media (max-width: 992px) { 
        .performance-section, .equal-section { grid-template-columns: 1fr; } 
    }
    @media (max-width: 768px) { 
        .w-span-3, .w-span-4, .w-span-6 { grid-column: span 12; } /* 1 Kotak per baris */
        .chart-card { min-height: 350px; } 
    }
</style>

<div class="card" style="animation: slideUpFade 0.3s ease-out forwards;">
    <form method="GET" action="" class="filter-container">
        <div class="form-group">
            <label style="font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase;">Pilih Tahun</label>
            <select name="filter_tahun" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-weight: bold; color: #0f172a; outline: none; cursor: pointer;">
                <?php foreach ($list_tahun as $th): ?>
                    <option value="<?= $th ?>" <?= $filter_tahun == $th ? 'selected' : '' ?>><?= $th ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label style="font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase;">Pilih Bulan</label>
            <select name="filter_bulan" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-weight: bold; color: #0f172a; outline: none; cursor: pointer;">
                <?php foreach ($list_bulan as $bln): ?>
                    <option value="<?= $bln ?>" <?= $filter_bulan == $bln ? 'selected' : '' ?>><?= $bln ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label style="font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase;">Pilih Regu</label>
            <select name="filter_regu" style="width: 100%; padding: 10px 14px; border-radius: 8px; border: 1px solid #cbd5e1; font-weight: bold; color: #0f172a; outline: none; cursor: pointer;">
                <option value="">Semua Regu (Gabungan)</option>
                <?php foreach ($list_regu as $rg): ?>
                    <option value="<?= $rg ?>" <?= $filter_regu == $rg ? 'selected' : '' ?>><?= $rg ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-filter">🔍 Terapkan Parameter</button>
            <?php if(isset($_GET['filter_tahun'])): ?>
                <a href="dashboard.php" class="btn-reset">✖ Reset Default</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="grid-cards">
    <div class="widget w-span-3" style="border-top-color: #0ea5e9;"><div class="title">1. Total Produksi H2</div><div class="value"><?= number_format($card_prod_h2, 0, ',', '.') ?><span class="unit">KG</span></div><div class="formula">Lantai Operasional</div></div>
    <div class="widget w-span-3" style="border-top-color: #3b82f6;"><div class="title">2. Total TSTB H2</div><div class="value"><?= number_format($card_tstb_h2, 1, ',', '.') ?><span class="unit">TON</span></div><div class="formula">Wilayah H2</div></div>
    <div class="widget w-span-3" style="border-top-color: #f97316;"><div class="title">3. WASTE (%)</div><div class="value" style="color:#ea580c;"><?= number_format($card_waste_persen, 2, ',', '.') ?>%</div><div class="formula">WASTE/KERTAS</div></div>
    <div class="widget w-span-3" style="border-top-color: #8b5cf6;"><div class="title">4. TOTAL KIRIM H1+H2</div><div class="value"><?= number_format($card_kirim_h12, 1, ',', '.') ?><span class="unit">TON</span></div><div class="formula">Logistik Gabungan</div></div>
    
    <div class="widget w-span-4" style="border-top-color: #6366f1;"><div class="title">5. TOTAL TSTB H1</div><div class="value"><?= number_format($card_tstb_h1, 1, ',', '.') ?><span class="unit">TON</span></div><div class="formula">Wilayah H1</div></div>
    <div class="widget w-span-4" style="border-top-color: #eab308;"><div class="title">6. TARGET KIRIM</div><div class="value"><?= number_format($card_target_kirim, 1, ',', '.') ?><span class="unit">TON</span></div><div class="formula">H1 TGT + H2 TGT</div></div>
    <div class="widget w-span-4" style="border-top-color: #10b981; background: #f0fdf4;"><div class="title">7. DELIVERY (%)</div><div class="value" style="color:#16a34a; font-size:24px;"><?= number_format($card_delivery_persen, 2, ',', '.') ?>%</div><div class="formula" style="background:#dcfce7; color:#166534;">KOTAK 4 / KOTAK 6 * 100</div></div>

    <div class="widget w-span-6" style="border-top-color: #1e3a8a;"><div class="title">8. TOTAL PRODUKSI TASKIM</div><div class="value" style="color: #1e3a8a;"><?= number_format($total_taskim, 0, ',', '.') ?><span class="unit">KG</span></div><div class="formula">Sesuai Filter Tanggal/Bulan</div></div>
    <div class="widget w-span-6" style="border-top-color: #38bdf8;"><div class="title">9. TOTAL PRODUKSI SAMSUL</div><div class="value" style="color: #0284c7;"><?= number_format($total_samsul, 0, ',', '.') ?><span class="unit">KG</span></div><div class="formula">Sesuai Filter Tanggal/Bulan</div></div>
</div>

<div class="performance-section">
    <div class="chart-card">
        <h3>📊 Ringkasan Total Performa (%)</h3>
        <div class="chart-wrapper">
            <canvas id="chartSpecialSummary"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3>📊 Analisis Hasil Produksi vs Target Per Regu (KG)</h3>
        <div class="chart-wrapper">
            <canvas id="chartProduksiRegu"></canvas>
        </div>
    </div>
</div>

<div class="equal-section">
    <div class="chart-card">
        <h3>⏳ Analisis Downtime DT (Mnt) / REGU</h3>
        <div class="chart-wrapper">
            <canvas id="chartDowntimeRegu"></canvas>
        </div>
    </div>

    <div class="chart-card">
        <h3>📊 Analisis Hasil Waste Produksi Perhari (%)</h3>
        <div class="chart-wrapper">
            <canvas id="chartWasteRegu"></canvas>
        </div>
    </div>
</div>

<script>
Chart.register(ChartDataLabels);

// 🚀 FONT DAN TOOLTIP PREMIUM UNTUK SEMUA CHART
const fontTheme = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
const premiumTooltip = {
    backgroundColor: 'rgba(15, 23, 42, 0.95)',
    titleFont: { size: 13, family: fontTheme, weight: 'normal' },
    bodyFont: { size: 14, weight: 'bold', family: fontTheme },
    padding: 12,
    cornerRadius: 8,
    displayColors: false,
    caretPadding: 10
};

// 1. DIAGRAM RINGKASAN PERFORMA (BAR)
new Chart(document.getElementById('chartSpecialSummary'), {
    type: 'bar',
    data: {
        labels: [
            '<?= number_format($card_tstb_h1, 1, ',', '.') ?>', 
            '<?= number_format($card_tstb_h2, 1, ',', '.') ?>', 
            '<?= number_format($card_kirim_h12, 1, ',', '.') ?>'
        ],
        datasets: [{
            data: [<?= round($perf_h1_p, 1) ?>, <?= round($perf_h2_p, 1) ?>, <?= round($card_delivery_persen, 1) ?>],
            backgroundColor: ['#001f5b', '#00b050', '#00e5ff'],
            hoverBackgroundColor: '#0f172a',
            borderColor: 'transparent',
            hoverBorderColor: '#ffffff',
            borderWidth: 2,
            borderRadius: 6,
            barPercentage: 0.55
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        animation: { duration: 1500, easing: 'easeOutQuart' }, // Animasi mulus
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { top: 40 } }, 
        plugins: {
            legend: { display: false },
            tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `Performa: ${ctx.raw.toFixed(1)}%` } },
            datalabels: {
                anchor: 'end', align: 'top', offset: 2,
                formatter: (val) => val.toFixed(1) + '%',
                font: { weight: 'bold', size: 16, family: fontTheme }, 
                color: '#000000'
            }
        },
        scales: {
            y: { beginAtZero: true, max: 100, ticks: { callback: (val) => val + '%' } },
            x: { ticks: { font: { size: 12, weight: 'bold', family: fontTheme }, color: '#0f172a' }, grid: {display: false} }
        }
    },
    plugins: [{
        id: 'canvasTopBrandingLabels',
        afterDraw: (chart) => {
            const { ctx, chartArea: { top } } = chart;
            ctx.save();
            ctx.font = `bold 26px ${fontTheme}`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            const titles = ['H1', 'H2', 'DLV'];
            const colors = ['#001f5b', '#00b050', '#00e5ff'];
            const meta = chart.getDatasetMeta(0);
            meta.data.forEach((bar, index) => {
                ctx.fillStyle = colors[index];
                ctx.fillText(titles[index], bar.x, top - 12);
            });
            ctx.restore();
        }
    }]
});

// 2. DIAGRAM PRODUKSI VS TARGET PER REGU (STACKED BAR + LINE)
new Chart(document.getElementById('chartProduksiRegu'), {
    data: {
        labels: <?= json_encode($labels_chart_prod) ?>,
        datasets: <?= json_encode($chart_datasets_json) ?>
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        animation: { duration: 1500, easing: 'easeOutQuart' },
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { top: 35 } }, 
        plugins: {
            legend: { position: 'top', labels: {usePointStyle: true, font: {weight: 'bold', family: fontTheme}} },
            tooltip: premiumTooltip,
            datalabels: { display: false } 
        },
        scales: {
            x: { stacked: true, grid: {display: false}, ticks: { font: { weight: 'bold', size: 11, family: fontTheme } } },
            y: { stacked: true, beginAtZero: true, grace: '15%', grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { callback: (val) => Math.round(val).toLocaleString('id-ID') } }
        }
    },
    plugins: [{
        id: 'drawStackedTotalsOnly',
        afterDatasetsDraw(chart) {
            const { ctx, scales: { y } } = chart;
            ctx.save();
            ctx.font = `bold 14px ${fontTheme}`;
            ctx.fillStyle = '#0f172a'; 
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';

            const totals = [];
            chart.data.labels.forEach((label, i) => {
                let sum = 0;
                chart.data.datasets.forEach((dataset, datasetIndex) => {
                    if (dataset.type === 'bar' && chart.isDatasetVisible(datasetIndex)) {
                        sum += parseFloat(dataset.data[i]) || 0;
                    }
                });
                totals.push(sum);
            });

            chart.data.labels.forEach((label, i) => {
                const sum = totals[i];
                if (sum > 0) {
                    let targetMeta = null;
                    for (let d = 0; d < chart.data.datasets.length; d++) {
                        if (chart.isDatasetVisible(d) && chart.data.datasets[d].type === 'bar') {
                            targetMeta = chart.getDatasetMeta(d).data[i];
                            break;
                        }
                    }
                    if (!targetMeta) targetMeta = chart.getDatasetMeta(0).data[i];
                    if (targetMeta) {
                        const xPos = targetMeta.x;
                        const yPos = y.getPixelForValue(sum);
                        const formattedTotal = Math.round(sum).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                        ctx.fillText(formattedTotal, xPos, yPos - 8);
                    }
                }
            });
            ctx.restore();
        }
    }]
});

// 3. DIAGRAM DOWNTIME PER REGU (LINE)
new Chart(document.getElementById('chartDowntimeRegu'), {
    data: {
        labels: <?= json_encode($labels_chart_prod) ?>,
        datasets: <?= json_encode($chart_dt_datasets_json) ?>
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        animation: { duration: 1500, easing: 'easeOutQuart' },
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { top: 35 } }, 
        plugins: {
            legend: { position: 'top', labels: {usePointStyle: true, font: {weight: 'bold', family: fontTheme}} },
            tooltip: premiumTooltip,
            datalabels: { 
                display: true, align: 'top', anchor: 'end',
                font: { weight: 'bold', size: 12, family: fontTheme },
                color: function(ctx) { return ctx.dataset.borderColor; }, // Warna angka mengikuti warna garis
                formatter: (val) => val !== 0 ? Math.round(val).toLocaleString('id-ID') : '' 
            }
        },
        scales: {
            x: { stacked: false, grid: {display: false}, ticks: { font: { weight: 'bold', size: 11, family: fontTheme } } },
            y: { stacked: false, beginAtZero: true, grace: '15%', grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { callback: (val) => Math.round(val).toLocaleString('id-ID') } }
        }
    }
});

// 4. DIAGRAM WASTE HARIAN (BAR)
new Chart(document.getElementById('chartWasteRegu'), {
    data: {
        labels: <?= json_encode($labels_chart_prod) ?>,
        datasets: <?= json_encode($chart_waste_datasets_json) ?>
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        animation: { duration: 1500, easing: 'easeOutQuart' },
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { top: 35, bottom: 40 } }, 
        plugins: {
            legend: { position: 'top', labels: {usePointStyle: true, font: {weight: 'bold', family: fontTheme}} },
            tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `Waste: ${ctx.raw.toFixed(2)}%` } },
            datalabels: { display: false } 
        },
        scales: {
            x: { stacked: false, grid: {display: false}, ticks: { font: { weight: 'bold', size: 11, family: fontTheme } } },
            y: { stacked: false, beginAtZero: false, grace: '15%', grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { callback: (val) => Math.round(val).toLocaleString('id-ID') + '%' } } 
        }
    },
    plugins: [{
        id: 'drawWasteTotalsOnly',
        afterDatasetsDraw(chart) {
            const { ctx, scales: { y } } = chart;
            ctx.save();
            ctx.font = `bold 14px ${fontTheme}`;
            ctx.fillStyle = '#0f172a'; 
            ctx.textAlign = 'center';

            chart.data.labels.forEach((label, i) => {
                const val = chart.data.datasets[0].data[i];
                if (val !== 0) {
                    const meta = chart.getDatasetMeta(0).data[i];
                    if (meta) {
                        const xPos = meta.x;
                        const yPos = y.getPixelForValue(val);
                        const formattedTotal = Math.round(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + '%';
                        
                        if (val >= 0) {
                            ctx.textBaseline = 'bottom';
                            ctx.fillText(formattedTotal, xPos, yPos - 10);
                        } else {
                            ctx.textBaseline = 'top';
                            ctx.fillText(formattedTotal, xPos, yPos + 10);
                        }
                    }
                }
            });
            ctx.restore();
        }
    }]
});
</script>
<?php require_once 'footer.php'; ?>