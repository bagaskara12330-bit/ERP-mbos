<?php
require_once 'auth.php';
require_akses('inv_keluar');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';

// TANGKAP FILTER BULAN DAN TAHUN
$bulan_filter = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$tahun_filter = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$nama_bulan_list = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$nama_bulan = $nama_bulan_list[$bulan_filter];

// 🚀 TARIK DATA DARI TABEL STOCK EX PAPPER
try {
    $stmt = $pdo->prepare("SELECT tanggal, shift, SUM(ex_kg) as tot_ex_kg, SUM(ex_roll) as tot_ex_roll, SUM(new_kg) as tot_new_kg, SUM(new_roll) as tot_new_roll FROM db_stock_ex_paper WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? GROUP BY tanggal, shift ORDER BY tanggal ASC, shift ASC");
    $stmt->execute([$bulan_filter, $tahun_filter]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $results = []; 
}

// 🧮 LOGIKA PINTAR PEMROSESAN DATA
$grouped_by_date = [];
$daily_accumulation = [];

// Variabel untuk menangkap data TERAKHIR (Terupdate)
$latest_ex_kg = 0; $latest_ex_roll = 0;
$latest_new_kg = 0; $latest_new_roll = 0;
$tanggal_terakhir = "Belum ada data";

// 🚀 Deteksi Pintar Shift 3
$has_shift_3 = false;

foreach($results as $row) {
    $tgl = $row['tanggal'];
    $sft = $row['shift'];
    
    // Cek jika ada shift 3
    if ($sft == '3' || $sft == 3) {
        $has_shift_3 = true;
    }
    
    // Grouping per Shift
    $grouped_by_date[$tgl][$sft] = $row;
    
    // 💡 UPDATE KE DATA TERAKHIR
    $latest_ex_kg = floatval($row['tot_ex_kg']);
    $latest_ex_roll = floatval($row['tot_ex_roll']);
    $latest_new_kg = floatval($row['tot_new_kg']);
    $latest_new_roll = floatval($row['tot_new_roll']);
    $tanggal_terakhir = date('d/m/Y', strtotime($tgl)) . " (Shift " . htmlspecialchars($sft) . ")";
}

// Persiapan Data Chart.js
$dates = array_keys($grouped_by_date);
$labels = [];

$shift1_kg = []; $shift2_kg = []; $shift3_kg = [];
$shift1_pct_kg = []; $shift1_pct_roll = [];
$shift2_pct_kg = []; $shift2_pct_roll = [];
$shift3_pct_kg = []; $shift3_pct_roll = [];

foreach($dates as $d) {
    $labels[] = date('d', strtotime($d)); // Ambil angka harinya (1-31)
    
    for($s=1; $s<=3; $s++) {
        $ex_kg   = isset($grouped_by_date[$d][$s]) ? floatval($grouped_by_date[$d][$s]['tot_ex_kg']) : 0;
        $ex_roll = isset($grouped_by_date[$d][$s]) ? floatval($grouped_by_date[$d][$s]['tot_ex_roll']) : 0;
        $new_kg  = isset($grouped_by_date[$d][$s]) ? floatval($grouped_by_date[$d][$s]['tot_new_kg']) : 0;
        $new_roll= isset($grouped_by_date[$d][$s]) ? floatval($grouped_by_date[$d][$s]['tot_new_roll']) : 0;
        
        $tot_s_kg   = $ex_kg + $new_kg;
        $tot_s_roll = $ex_roll + $new_roll;
        
        $pct_s_kg   = ($tot_s_kg > 0) ? ($ex_kg / $tot_s_kg) * 100 : 0;
        $pct_s_roll = ($tot_s_roll > 0) ? ($ex_roll / $tot_s_roll) * 100 : 0;
        
        if($s==1) { 
            $shift1_kg[] = round($ex_kg, 2); 
            $shift1_pct_kg[] = round($pct_s_kg, 2); 
            $shift1_pct_roll[] = round($pct_s_roll, 2); 
        }
        if($s==2) { 
            $shift2_kg[] = round($ex_kg, 2); 
            $shift2_pct_kg[] = round($pct_s_kg, 2); 
            $shift2_pct_roll[] = round($pct_s_roll, 2); 
        }
        if($s==3) { 
            $shift3_kg[] = round($ex_kg, 2); 
            $shift3_pct_kg[] = round($pct_s_kg, 2); 
            $shift3_pct_roll[] = round($pct_s_roll, 2); 
        }
    }
}

// Convert ke JSON untuk JavaScript
$json_labels = json_encode($labels);
$json_s1_kg = json_encode($shift1_kg);
$json_s2_kg = json_encode($shift2_kg);
$json_s3_kg = json_encode($shift3_kg);

$json_s1_pct_kg = json_encode($shift1_pct_kg);
$json_s1_pct_roll = json_encode($shift1_pct_roll);
$json_s2_pct_kg = json_encode($shift2_pct_kg);
$json_s2_pct_roll = json_encode($shift2_pct_roll);
$json_s3_pct_kg = json_encode($shift3_pct_kg);
$json_s3_pct_roll = json_encode($shift3_pct_roll);

// ==========================================
// DATA STOK OPNAME (PENGGABUNGAN DARI DASHBOARD LAMA)
// ==========================================
$latest = [
    'tanggal' => date('Y-m-d'), 'stok_akhir' => 0, 'stok_aktual' => 0, 'selisih' => 0, 'persentase' => 0
];
try {
    $stmt_latest = $pdo->query("SELECT * FROM db_stok_roll_harian ORDER BY tanggal DESC LIMIT 1");
    $row_latest = $stmt_latest->fetch(PDO::FETCH_ASSOC);
    if ($row_latest) {
        $latest['tanggal'] = $row_latest['tanggal'];
        $latest['stok_akhir'] = floatval($row_latest['stok_akhir']);
        $latest['stok_aktual'] = floatval($row_latest['stok_aktual']);
        $latest['selisih'] = floatval($row_latest['selisih']);
        if ($latest['stok_akhir'] > 0) { $latest['persentase'] = ($latest['selisih'] / $latest['stok_akhir']) * 100; }
    }
} catch (PDOException $e) {}

$is_selisih = round($latest['selisih'], 2) != 0;
$box_bg = $is_selisih ? '#fef2f2' : '#f0fdf4';
$box_border = $is_selisih ? '#ef4444' : '#22c55e';
$box_text = $is_selisih ? '#b91c1c' : '#15803d';

$chart_op_labels = []; $chart_op_sistem = []; $chart_op_aktual = [];
try {
    $stmt_chart = $pdo->query("SELECT * FROM (SELECT * FROM db_stok_roll_harian ORDER BY tanggal DESC LIMIT 30) sub ORDER BY tanggal ASC");
    $rows_chart = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows_chart as $rc) {
        $chart_op_labels[] = date('d', strtotime($rc['tanggal']));
        $chart_op_sistem[] = floatval($rc['stok_akhir']);
        $chart_op_aktual[] = floatval($rc['stok_aktual']);
    }
} catch (PDOException $e) {}

$page_title = "Dashboard Analitik Stock Ex (Proll) — H2 BASE ERP";
$active_page = "dashboard_proll";
require 'header.php';
?>

<style>
    /* 🚀 ANIMASI ENTRANCE GLOBAL (MUNCUL MELAYANG) */
    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animated-card {
        animation: slideUpFade 0.4s ease-out forwards;
    }

    /* 🚀 FILTER ACTION PANEL & FORM INPUT MODERN SAAS */
    .filter-box { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
    .filter-box .form-group { margin-bottom: 0; min-width: 150px; }
    
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
        cursor: pointer;
    }
    input:focus, select:focus { 
        border-color: #0ea5e9 !important; 
        background: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(14,165,233,0.15) !important; 
    }

    /* 🚀 RE-DESIGN TOMBOL "🔍 CARI DATA" (PREMIUM SLATE BLUE) */
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
    .btn-search-modern:active { transform: scale(0.95) !important; }

    /* 🚀 CSS WIDGET DASHBOARD PREMIUM INTERAKTIF */
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 24px; }
    
    .widget-box { 
        background: #ffffff; border-radius: 12px; padding: 24px; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: center; 
        border: 1px solid #e2e8f0; 
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        opacity: 0; 
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    .widget-box:nth-child(1) { animation-delay: 0.1s; border-top: 5px solid #ef4444; background: #fef2f2; }
    .widget-box:nth-child(2) { animation-delay: 0.2s; border-top: 5px solid #f59e0b; background: #fffbeb; }
    .widget-box:nth-child(3) { animation-delay: 0.3s; border-top: 5px solid #10b981; background: #f0fdf4; }

    .widget-box:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); cursor: pointer; }
    
    .widget-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
    .widget-value { font-size: 28px; font-weight: 900; color: #0f172a; margin-bottom: 6px; display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;}
    .widget-desc { font-size: 12px; font-weight: 700; color: #94a3b8; }
    
    /* 🚀 CSS CHART CARDS INTERAKTIF */
    .chart-card { 
        background: #ffffff; border-radius: 12px; padding: 24px; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 24px; 
        transition: all 0.3s ease;
        opacity: 0;
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    .chart-card:nth-of-type(3) { animation-delay: 0.4s; }
    .chart-card:nth-of-type(4) { animation-delay: 0.5s; }
    .chart-card:nth-of-type(5) { animation-delay: 0.6s; }

    .chart-card:hover { box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }

    .chart-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;}
    .chart-header h3 { margin: 0; font-size: 15px; color: #1e293b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;}
    .canvas-container { position: relative; height: 350px; width: 100%; }

    /* STOK OPNAME CSS */
    .opname-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 30px; }
    .opname-card { 
        background: white; padding: 24px; border-radius: 12px; 
        border: 1px solid #e2e8f0; border-top: 4px solid #cbd5e1; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;
    }
    .opname-card:nth-child(1) { border-top-color: #3b82f6; }
    .opname-card:nth-child(2) { border-top-color: #6366f1; }
    .opname-card .title { font-size: 13px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px;}
    .opname-card .value { font-size: 32px; font-weight: 900; color: #0f172a; margin: 4px 0; }
    .opname-card .unit { font-size: 16px; font-weight: 600; color: #94a3b8; }
    .opname-card .date-badge { background: #f1f5f9; color: #475569; font-size: 11px; padding: 4px 10px; border-radius: 20px; font-weight: 700; margin-bottom: 16px; border: 1px solid #cbd5e1; }

    @media (max-width: 768px) {
        .filter-box { flex-direction: column; align-items: stretch; width: 100%; }
        .filter-box form { flex-direction: column; align-items: stretch; width: 100%; }
        .filter-box .form-group { width: 100%; }
        .btn-search-modern { width: 100%; }
    }
</style>

<div class="card animated-card" style="border-top: 5px solid #0ea5e9; background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 24px;">
    <div class="filter-box" style="justify-content:space-between; align-items:center; margin-bottom: 0;">
        <div>
            <h2 style="margin:0; border:none; padding:0; font-size: 18px; color: #0f172a;">📈 DASHBOARD ANALITIK PROLL (STOCK EX)</h2>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-top: 6px;">Pemantauan Real-time Volume & Persentase Pemakaian</div>
        </div>
        
        <form method="GET" action="" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase;">Pilih Bulan</label>
                <select name="bulan">
                    <?php foreach ($nama_bulan_list as $m_code => $m_name): ?>
                        <option value="<?= $m_code ?>" <?= $m_code == $bulan_filter ? 'selected' : '' ?>><?= $m_name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label style="font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 6px; display: block; text-transform: uppercase;">Pilih Tahun</label>
                <select name="tahun">
                    <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $tahun_filter ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn-search-modern">🔍 Tampilkan Analitik</button>
        </form>
    </div>
</div>

<?php 
    $tot_kg_latest = $latest_ex_kg + $latest_new_kg;
    $tot_roll_latest = $latest_ex_roll + $latest_new_roll;
    $pct_kg_latest = ($tot_kg_latest > 0) ? ($latest_ex_kg / $tot_kg_latest) * 100 : 0;
    $pct_roll_latest = ($tot_roll_latest > 0) ? ($latest_ex_roll / $tot_roll_latest) * 100 : 0;
?>

<div class="dashboard-grid">
    <div class="widget-box red">
        <div class="widget-title">STOCK EX (DATA TERUPDATE)</div>
        <div class="widget-value" style="color: #dc2626;">
            <?= number_format($latest_ex_kg, 0, ',', '.') ?> <span style="font-size:14px; color:#ef4444;">KG</span> 
            <span style="font-size:18px; color:#fca5a5;">|</span> 
            <?= number_format($latest_ex_roll, 0, ',', '.') ?> <span style="font-size:14px; color:#ef4444;">ROLL</span>
        </div>
        <div class="widget-desc">Update Terakhir: <strong style="color: #7f1d1d;"><?= $tanggal_terakhir ?></strong></div>
    </div>
    
    <div class="widget-box orange">
        <div class="widget-title">% STOCK KG (DATA TERUPDATE)</div>
        <div class="widget-value" style="color: #d97706;"><?= number_format($pct_kg_latest, 2, ',', '.') ?>%</div>
        <div class="widget-desc" style="color: #b45309;">Persentase KG pada inputan terakhir</div>
    </div>

    <div class="widget-box green">
        <div class="widget-title">% STOCK ROLL (DATA TERUPDATE)</div>
        <div class="widget-value" style="color: #059669;"><?= number_format($pct_roll_latest, 2, ',', '.') ?>%</div>
        <div class="widget-desc" style="color: #047857;">Persentase ROLL pada inputan terakhir</div>
    </div>
</div>

<div class="chart-card">
    <div class="chart-header">
        <h3>📦 1. SUMMARY STOK OPNAME HARIAN (ROLL EX)</h3>
        <span style="font-size:12px; font-weight:800; color:#3b82f6; background:#eff6ff; padding:6px 12px; border-radius:6px; border: 1px solid #bfdbfe;">30 Hari Terakhir</span>
    </div>
    
    <div class="opname-grid">
        <div class="opname-card">
            <div class="date-badge">📅 Update: <?= date('d M Y', strtotime($latest['tanggal'])) ?></div>
            <div class="title">STOCK AKHIR SISTEM</div>
            <div class="value"><?= number_format($latest['stok_akhir'], 2, ',', '.') ?> <span class="unit">KG</span></div>
        </div>
        <div class="opname-card">
            <div class="date-badge">📅 Update: <?= date('d M Y', strtotime($latest['tanggal'])) ?></div>
            <div class="title">STOCK AKTUAL HARIAN</div>
            <div class="value"><?= number_format($latest['stok_aktual'], 2, ',', '.') ?> <span class="unit">KG</span></div>
        </div>
        <div class="opname-card" style="border-top-color: <?= $box_border ?>; background: <?= $box_bg ?>;">
            <div class="date-badge" style="background: white;">🚨 STATUS SELISIH</div>
            <div class="title" style="color: <?= $box_text ?>;">SELISIH TONASE | PERSENTASE</div>
            <div class="value" style="color: <?= $box_text ?>;">
                <?= number_format($latest['selisih'], 2, ',', '.') ?> <span class="unit" style="color: <?= $box_text ?>;">KG</span> 
                <span style="opacity: 0.3; margin: 0 10px;">|</span> 
                <?= number_format($latest['persentase'], 2, ',', '.') ?>%
            </div>
        </div>
    </div>
    <div class="canvas-container" style="height: 380px;">
        <canvas id="chartTrenStok"></canvas>
    </div>
</div>

<div class="chart-card">
    <div class="chart-header">
        <h3>📊 2. Volume STOCK EX (KG) Berdasarkan Shift</h3>
        <span style="font-size:12px; font-weight:800; color:#0ea5e9; background:#e0f2fe; padding:6px 12px; border-radius:6px; border: 1px solid #bae6fd;">Bulan <?= $nama_bulan ?> <?= $tahun_filter ?></span>
    </div>
    <div class="canvas-container">
        <canvas id="chartVolumeKG"></canvas>
    </div>
</div>

<div class="chart-card">
    <div class="chart-header">
        <h3>📉 3. Tren Harian % STOCK EX (KG) Berdasarkan Shift</h3>
        <span style="font-size:12px; font-weight:800; color:#0ea5e9; background:#e0f2fe; padding:6px 12px; border-radius:6px; border: 1px solid #bae6fd;">Bulan <?= $nama_bulan ?> <?= $tahun_filter ?></span>
    </div>
    <div class="canvas-container">
        <canvas id="chartTrendPctKG"></canvas>
    </div>
</div>

<div class="chart-card">
    <div class="chart-header">
        <h3>📉 4. Analisis Fluktuasi Pemakaian ROLL (%)</h3>
        <span style="font-size:12px; font-weight:800; color:#0ea5e9; background:#e0f2fe; padding:6px 12px; border-radius:6px; border: 1px solid #bae6fd;">Bulan <?= $nama_bulan ?> <?= $tahun_filter ?></span>
    </div>
    <div class="canvas-container">
        <canvas id="chartTrendPctRoll"></canvas>
    </div>
</div>


<script>
    // Konfigurasi Standar Font & Grid Premium
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
    Chart.defaults.color = '#64748b';
    
    // Status Pengecekan Shift 3
    const hasShift3 = <?= $has_shift_3 ? 'true' : 'false' ?>;
    const labelsTgl = <?= $json_labels ?>;
    
    // 🚀 TOOLTIP DARK MODE PREMIUM
    const premiumTooltip = {
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        titleFont: { size: 12, weight: 'normal' },
        bodyFont: { size: 14, weight: 'bold' },
        padding: 12,
        cornerRadius: 8,
        displayColors: false,
        caretPadding: 10
    };
    
    // FUNGSI UMUM UNTUK MENGHILANGKAN ANGKA 0 DI DATALABELS
    const datalabelsConfig = {
        display: function(context) { return context.dataset.data[context.dataIndex] > 0; },
        font: { weight: 'bold', size: 10 },
        anchor: 'end', align: 'end', offset: 2
    };

    // ==========================================
    // 1. RENDER CHART BATANG (VOLUME KG PER SHIFT)
    // ==========================================
    let datasetKG = [
        { label: 'Shift 1', data: <?= $json_s1_kg ?>, backgroundColor: '#0ea5e9', hoverBackgroundColor: '#0284c7', borderColor: 'transparent', hoverBorderColor: '#ffffff', borderWidth: 2, borderRadius: 4, barPercentage: 0.7 },
        { label: 'Shift 2', data: <?= $json_s2_kg ?>, backgroundColor: '#f59e0b', hoverBackgroundColor: '#d97706', borderColor: 'transparent', hoverBorderColor: '#ffffff', borderWidth: 2, borderRadius: 4, barPercentage: 0.7 }
    ];
    if (hasShift3) {
        datasetKG.push({ label: 'Shift 3', data: <?= $json_s3_kg ?>, backgroundColor: '#10b981', hoverBackgroundColor: '#059669', borderColor: 'transparent', hoverBorderColor: '#ffffff', borderWidth: 2, borderRadius: 4, barPercentage: 0.7 });
    }

    new Chart(document.getElementById('chartVolumeKG').getContext('2d'), {
        type: 'bar',
        data: { labels: labelsTgl, datasets: datasetKG },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 1500, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, font: { weight: '700' }, color: '#0f172a' } },
                tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.raw.toLocaleString('id-ID')} KG` } },
                datalabels: { ...datalabelsConfig, color: '#475569', formatter: (val) => (val/1000).toFixed(1) + 'k' }
            },
            scales: {
                x: { title: { display: true, text: 'Tanggal', font: {weight:'bold'} }, grid: { display: false }, ticks: { font: { weight: 'bold' } } },
                y: { beginAtZero: true, grace: '15%', grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { callback: function(value) { return (value/1000) + 'k'; } } }
            }
        }
    });

    // ==========================================
    // 2. RENDER CHART GARIS (TREN % KG PER SHIFT)
    // ==========================================
    let datasetPctKG = [
        { label: 'Shift 1', data: <?= $json_s1_pct_kg ?>, borderColor: '#0ea5e9', backgroundColor: 'rgba(14, 165, 233, 0.05)', borderWidth: 3, pointBackgroundColor: '#fff', hoverBorderColor: '#ffffff', fill: true, tension: 0.3 },
        { label: 'Shift 2', data: <?= $json_s2_pct_kg ?>, borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.05)', borderWidth: 3, pointBackgroundColor: '#fff', hoverBorderColor: '#ffffff', fill: true, tension: 0.3 }
    ];
    if (hasShift3) {
        datasetPctKG.push({ label: 'Shift 3', data: <?= $json_s3_pct_kg ?>, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.05)', borderWidth: 3, pointBackgroundColor: '#fff', hoverBorderColor: '#ffffff', fill: true, tension: 0.3 });
    }

    new Chart(document.getElementById('chartTrendPctKG').getContext('2d'), {
        type: 'line',
        data: { labels: labelsTgl, datasets: datasetPctKG },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 1500, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, font: { weight: '700', size: 11 }, color: '#0f172a' } },
                tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.raw}%` } },
                datalabels: { ...datalabelsConfig, color: function(ctx) { return ctx.dataset.borderColor; }, formatter: (val) => val + '%' }
            },
            scales: {
                x: { title: { display: true, text: 'Tanggal', font: {weight:'bold'} }, grid: { display: false }, ticks: { font: { weight: 'bold' } } },
                y: { beginAtZero: true, grace: '15%', grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { callback: function(value) { return value + '%'; } } }
            }
        }
    });

    // ==========================================
    // 3. RENDER CHART GARIS (TREN % ROLL PER SHIFT)
    // ==========================================
    let datasetPctRoll = [
        { label: 'Shift 1', data: <?= $json_s1_pct_roll ?>, borderColor: '#38bdf8', backgroundColor: 'rgba(56, 189, 248, 0.05)', borderWidth: 3, pointBackgroundColor: '#fff', hoverBorderColor: '#ffffff', fill: true, tension: 0.3 },
        { label: 'Shift 2', data: <?= $json_s2_pct_roll ?>, borderColor: '#fbbf24', backgroundColor: 'rgba(251, 191, 36, 0.05)', borderWidth: 3, pointBackgroundColor: '#fff', hoverBorderColor: '#ffffff', fill: true, tension: 0.3 }
    ];
    if (hasShift3) {
        datasetPctRoll.push({ label: 'Shift 3', data: <?= $json_s3_pct_roll ?>, borderColor: '#34d399', backgroundColor: 'rgba(52, 211, 153, 0.05)', borderWidth: 3, pointBackgroundColor: '#fff', hoverBorderColor: '#ffffff', fill: true, tension: 0.3 });
    }

    new Chart(document.getElementById('chartTrendPctRoll').getContext('2d'), {
        type: 'line',
        data: { labels: labelsTgl, datasets: datasetPctRoll },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 1500, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, font: { weight: '700', size: 11 }, color: '#0f172a' } },
                tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.raw}%` } },
                datalabels: { ...datalabelsConfig, color: function(ctx) { return ctx.dataset.borderColor; }, formatter: (val) => val + '%' }
            },
            scales: {
                x: { title: { display: true, text: 'Tanggal', font: {weight:'bold'} }, grid: { display: false }, ticks: { font: { weight: 'bold' } } },
                y: { beginAtZero: true, grace: '15%', grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { callback: function(value) { return value + '%'; } } }
            }
        }
    });

    // ==========================================
    // 4. RENDER CHART TREN STOK OPNAME
    // ==========================================
    new Chart(document.getElementById('chartTrenStok').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_op_labels) ?>,
            datasets: [
                {
                    label: 'Stok Sistem (KG)', data: <?= json_encode($chart_op_sistem) ?>,
                    borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3, pointBackgroundColor: '#fff', pointBorderColor: '#3b82f6',
                    fill: true, tension: 0.3
                },
                {
                    label: 'Stok Aktual (KG)', data: <?= json_encode($chart_op_aktual) ?>,
                    borderColor: '#6366f1', backgroundColor: 'transparent',
                    borderWidth: 3, pointBackgroundColor: '#fff', pointBorderColor: '#6366f1',
                    borderDash: [5, 5], tension: 0.3
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, font: { weight: 'bold' } } },
                tooltip: premiumTooltip,
                datalabels: { display: false }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { weight: 'bold' } } },
                y: { grace: '15%', grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { callback: function(value) { return (value/1000) + 'k'; } } }
            }
        }
    });
</script>

<?php require_once 'footer.php'; ?>