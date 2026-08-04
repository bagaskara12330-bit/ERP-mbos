<?php
require_once 'auth.php';
require_akses('dash_nc');$bulan_ini = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$tahun_ini = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$shift_ini = isset($_GET['shift']) ? $_GET['shift'] : 'ALL';

$nama_bulan_list = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$nama_bulan = $nama_bulan_list[$bulan_ini];

// 🚀 FUNGSI PINTAR PHP (SMART DECIMAL)
function formatBersihDesimal($angka, $max_desimal = 3) {
    $format = number_format($angka, $max_desimal, ',', '.');
    if (strpos($format, ',') !== false) {
        $format = rtrim(rtrim($format, '0'), ',');
    }
    return $format;
}

try {
    // 🚀 LOGIKA PINTAR: INJEKSI QUERY SHIFT
    $where_shift = "";
    $params = [$bulan_ini, $tahun_ini];

    if ($shift_ini !== 'ALL') {
        $where_shift = " AND shift = ?";
        $params[] = $shift_ini;
    }

    // 1. DATA SUMMARY GLOBAL & HARIAN DALAM SATU TARIKAN NAFAS
    $stmt_harian = $pdo->prepare("
        SELECT DAY(tanggal) as tgl_hari, 
        SUM(order_customer) as tot_order, 
        SUM(hasil_counter) as tot_counter,
        SUM(or_l_rm) as tot_lrm,
        SUM(or_m2) as tot_m2,
        SUM(total_kg) as tot_kg
        FROM db_produksi_nc 
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?" . $where_shift . " 
        GROUP BY tanggal ORDER BY tanggal ASC
    ");
    $stmt_harian->execute($params);
    $data_harian = $stmt_harian->fetchAll(PDO::FETCH_ASSOC);

    // INISIALISASI VARIABEL METRIK GRAND TOTAL
    $grand_order = 0; 
    $grand_counter = 0;
    $grand_lrm = 0; 
    $grand_m2 = 0;
    $grand_kg = 0;

    // INISIALISASI ARRAY UNTUK CHART JAVASCRIPT
    $labels_tgl = []; 
    $chart_lrm = []; 
    $chart_m2 = []; 
    $chart_kg = [];

    foreach($data_harian as $row) {
        // Kalkulasi Grand Total Widget
        $grand_order += $row['tot_order'];
        $grand_counter += $row['tot_counter'];
        $grand_lrm += $row['tot_lrm'];
        $grand_m2 += $row['tot_m2'];
        $grand_kg += $row['tot_kg'];

        // Push ke Array Chart
        $labels_tgl[] = $row['tgl_hari'];
        
        // 🚀 UPDATE SAKTI: Pembulatan
        $chart_lrm[] = round($row['tot_lrm'], 0); 
        $chart_m2[] = round($row['tot_m2'], 2);
        $chart_kg[] = round($row['tot_kg'], 2);
    }

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

$js_tgl = json_encode($labels_tgl);
$js_lrm = json_encode($chart_lrm);
$js_m2 = json_encode($chart_m2);
$js_kg = json_encode($chart_kg);

$page_title = "Dashboard Produksi NC — H2 BASE";
$active_page = "dash_prod"; 
require 'header.php';
?>

<style>
    /* 🚀 ANIMASI ENTRANCE GLOBAL BERURUTAN */
    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .header-banner {
        background: #10b981; border-radius: 10px; padding: 15px 20px; display: flex; justify-content: space-between; 
        align-items: center; flex-wrap: wrap; margin-bottom: 24px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); gap: 15px;
        animation: slideUpFade 0.4s ease-out forwards;
    }

    .filter-card { 
        background: white; padding: 15px 20px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 24px; 
        display: flex; align-items: center; gap: 15px; flex-wrap: wrap; box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
        animation: slideUpFade 0.5s ease-out forwards; 
    }
    .filter-card label { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; }
    .filter-card select { 
        padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; 
        background: #f8fafc; outline: none; font-weight: 600; cursor: pointer; transition: 0.2s;
    }
    .filter-card select:hover, .filter-card select:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }
    
    .btn-filter-submit { 
        background: #10b981; color: white; border: none; padding: 9px 20px; border-radius: 6px; 
        font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; 
    }
    .btn-filter-submit:hover { background: #059669; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); transform: translateY(-1px); }
    .btn-filter-submit:active { transform: scale(0.95); } /* Efek Fisik Klik */

    /* 🚀 WIDGET SUMMARY INTERAKTIF */
    .summary-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 24px; }
    .summary-card { 
        background: white; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); /* Transisi Mulus */
        opacity: 0; 
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    /* Delay Animasi agar muncul berurutan seperti efek Domino */
    .summary-card:nth-child(1) { animation-delay: 0.1s; border-top: 5px solid #0ea5e9; } /* Order -> Biru */
    .summary-card:nth-child(2) { animation-delay: 0.2s; border-top: 5px solid #10b981; } /* Counter -> Hijau */
    .summary-card:nth-child(3) { animation-delay: 0.3s; border-top: 5px solid #f59e0b; } /* KG -> Orange */
    .summary-card:nth-child(4) { animation-delay: 0.4s; border-top: 5px solid #8b5cf6; } /* LRM -> Ungu */
    .summary-card:nth-child(5) { animation-delay: 0.5s; border-top: 5px solid #f43f5e; } /* M2 -> Merah */

    /* 🚀 EFEK HOVER 3D PADA WIDGET */
    .summary-card:hover { 
        transform: translateY(-6px) scale(1.02); 
        box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); 
        cursor: pointer;
    }
    
    .summary-card .title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;}
    .summary-card .value { font-size: 24px; font-weight: 900; color: #0f172a; margin: 4px 0; }
    .summary-card .unit { font-size: 12px; font-weight: 700; color: #94a3b8; margin-left: 4px; }

    /* 🚀 GRID 3 GRAFIK INTERAKTIF */
    .three-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 24px; align-items: stretch; }
    .chart-card { 
        background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; flex-direction: column; min-height: 380px; 
        width: 100%; box-sizing: border-box;
        transition: all 0.3s ease;
        opacity: 0;
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    /* Delay Animasi Grafik */
    .three-grid .chart-card:nth-child(1) { animation-delay: 0.6s; }
    .three-grid .chart-card:nth-child(2) { animation-delay: 0.7s; }
    .three-grid .chart-card:nth-child(3) { animation-delay: 0.8s; }

    .chart-card:hover { box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }
    .chart-card h3 { font-size: 14px; font-weight: 800; margin-top: 0; margin-bottom: 16px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
    
    .canvas-wrapper { position: relative; flex-grow: 1; min-height: 300px; width: 100%; }

    /* RESPONSIVE LAYOUT */
    @media (max-width: 1300px) { .three-grid { grid-template-columns: repeat(2, 1fr); } .summary-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 992px) { .three-grid { grid-template-columns: 1fr; } .summary-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 768px) { .summary-grid { grid-template-columns: 1fr; } }
</style>

<div class="header-banner">
    <h3 style="color: #ffffff; margin: 0; font-size: 15px; display: flex; align-items: center; gap: 8px;">
        📊 EXECUTIVE DASHBOARD: PRODUKSI NC
    </h3>
</div>

<form method="GET" action="" class="filter-card">
    <div>
        <label>Periode Bulan:</label>
        <select name="bulan">
            <?php foreach ($nama_bulan_list as $m_code => $m_name): ?>
                <option value="<?= $m_code ?>" <?= $m_code == $bulan_ini ? 'selected' : '' ?>><?= $m_name ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Tahun:</label>
        <select name="tahun">
            <?php $tahun_sekarang = date('Y'); for ($y = $tahun_sekarang; $y >= 2024; $y--): ?>
                <option value="<?= $y ?>" <?= $y == $tahun_ini ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div>
        <label>Shift Kerja:</label>
        <select name="shift" style="border-color: #10b981; color: #047857;">
            <option value="ALL" <?= $shift_ini == 'ALL' ? 'selected' : '' ?>>Semua Shift</option>
            <option value="1" <?= $shift_ini == '1' ? 'selected' : '' ?>>Shift 1</option>
            <option value="2" <?= $shift_ini == '2' ? 'selected' : '' ?>>Shift 2</option>
            <option value="3" <?= $shift_ini == '3' ? 'selected' : '' ?>>Shift 3</option>
        </select>
    </div>
    <button type="submit" class="btn-filter-submit">🔍 Analisis Data</button>
</form>

<?php if ($grand_order == 0 && empty($data_harian)): ?>
    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 50px; text-align: center; color: #64748b; margin-bottom: 24px; animation: slideUpFade 0.5s ease-out forwards;">
        <div style="font-size: 40px; margin-bottom: 15px;">📭</div>
        <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 800;">Data Produksi Kosong</h3>
        <p style="margin: 0; font-size: 14px; font-weight: 600;">Belum ada laporan produksi yang direkam pada bulan <?= $nama_bulan ?> <?= $tahun_ini ?> <?= $shift_ini != 'ALL' ? '(Khusus Shift '.$shift_ini.')' : '' ?>.</p>
    </div>
<?php else: ?>

<!-- 🚀 WIDGET SUMMARY INTERAKTIF (WARNA DISINKRONKAN DENGAN GRAFIK) -->
<div class="summary-grid">
    <div class="summary-card" style="background: #f0f9ff;">
        <div class="title" style="color: #0284c7;">Total Order Customer</div>
        <div class="value" style="color: #0369a1;"><?= number_format($grand_order, 0, ',', '.') ?> <span class="unit" style="color: #38bdf8;">PCS</span></div>
    </div>
    
    <div class="summary-card" style="background: #f0fdf4;">
        <div class="title" style="color: #15803d;">Total Hasil Counter</div>
        <div class="value" style="color: #14532d;"><?= number_format($grand_counter, 0, ',', '.') ?> <span class="unit" style="color: #4ade80;">PCS</span></div>
    </div>

    <div class="summary-card" style="background: #fffbeb;">
        <div class="title" style="color: #d97706;">Total KG</div>
        <div class="value" style="color: #b45309;"><?= formatBersihDesimal($grand_kg, 3) ?> <span class="unit" style="color: #fbbf24;">KG</span></div>
    </div>
    
    <div class="summary-card" style="background: #faf5ff;">
        <div class="title" style="color: #7e22ce;">Total L / RM</div>
        <div class="value" style="color: #6d28d9;"><?= number_format($grand_lrm, 0, ',', '.') ?> <span class="unit" style="color: #a78bfa;">M</span></div>
    </div>
    
    <div class="summary-card" style="background: #fff1f2;">
        <div class="title" style="color: #be123c;">Total M²</div>
        <div class="value" style="color: #9f1239;"><?= formatBersihDesimal($grand_m2, 3) ?> <span class="unit" style="color: #fb7185;">M²</span></div>
    </div>
</div>

<!-- 🚀 GRID 3 KOLOM GRAFIK -->
<div class="three-grid">
    <!-- CHART 1: L/RM (Garis Ungu Sinkron dengan Widget L/RM) -->
    <div class="chart-card">
        <h3 style="color: #7e22ce;">📏 Tren Total L/RM Harian <?= $shift_ini != 'ALL' ? '(SHIFT '.$shift_ini.')' : '' ?></h3>
        <div class="canvas-wrapper">
            <canvas id="chartLRM"></canvas>
        </div>
    </div>

    <!-- CHART 2: KG (Batang Orange/Amber Sinkron dengan Widget KG) -->
    <div class="chart-card">
        <h3 style="color: #d97706;">⚖️ Tren Total KG Harian <?= $shift_ini != 'ALL' ? '(SHIFT '.$shift_ini.')' : '' ?></h3>
        <div class="canvas-wrapper">
            <canvas id="chartKG"></canvas>
        </div>
    </div>
    
    <!-- CHART 3: M2 (Batang Merah/Rose Sinkron dengan Widget M2) -->
    <div class="chart-card">
        <h3 style="color: #be123c;">📐 Tren Total M² Harian <?= $shift_ini != 'ALL' ? '(SHIFT '.$shift_ini.')' : '' ?></h3>
        <div class="canvas-wrapper">
            <canvas id="chartM2"></canvas>
        </div>
    </div>
</div>

<script>
    Chart.register(ChartDataLabels);

    const labelsTgl = <?= $js_tgl ?>;
    const data_lrm = <?= $js_lrm ?>; 
    const data_m2 = <?= $js_m2 ?>;
    const data_kg = <?= $js_kg ?>;

    // 🚀 FONT & TOOLTIP DARK MODE PREMIUM
    const fontTheme = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
    const premiumTooltip = {
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        titleFont: { size: 13, family: fontTheme, weight: 'normal' },
        bodyFont: { size: 14, weight: 'bold', family: fontTheme },
        padding: 12, cornerRadius: 8, displayColors: false, caretPadding: 10
    };

    const labelFont = { weight: 'bold', size: 11, family: fontTheme };

    // 🚀 KONFIGURASI INTERAKTIF & ANIMASI CHART.JS PREMIUM
    const commonOptions = {
        responsive: true, 
        maintainAspectRatio: false,
        animation: {
            duration: 1500,
            easing: 'easeOutQuart' // Animasi Bar Naik Mulus
        },
        interaction: {
            mode: 'index',
            intersect: false, // Hover dimana saja dalam garis akan bereaksi
        },
        layout: { padding: { top: 35, bottom: 10, left: 10, right: 10 } }, 
        plugins: { 
            legend: { display: false },
            tooltip: premiumTooltip
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { weight: '900', size: 12, family: fontTheme }, color: '#64748b' } },
            y: { display: false, beginAtZero: true, grace: '15%' }
        }
    };

    // 1. CHART L/RM (GARIS UNGU - Sinkron dengan Widget)
    new Chart(document.getElementById('chartLRM').getContext('2d'), {
        type: 'line',
        data: {
            labels: labelsTgl,
            datasets: [{
                label: 'Total L/RM',
                data: data_lrm,
                borderColor: '#8b5cf6', 
                backgroundColor: 'rgba(139, 92, 246, 0.1)', 
                fill: true,
                tension: 0.4, 
                borderWidth: 4, 
                pointRadius: 6, 
                pointBackgroundColor: '#ffffff',
                pointBorderWidth: 3,
                pointBorderColor: '#8b5cf6',
                pointHoverBackgroundColor: '#8b5cf6', 
                pointHoverBorderColor: '#ffffff',
                pointHoverRadius: 8
            }]
        },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: { ...premiumTooltip, callbacks: { label: function(context) { return 'Panjang: ' + context.raw.toLocaleString('id-ID') + ' Meter'; } } },
                datalabels: { 
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0, 
                    color: '#5b21b6', 
                    backgroundColor: '#f3e8ff', 
                    borderWidth: 1.5, borderColor: '#8b5cf6', 
                    borderRadius: 20, padding: { top: 4, bottom: 4, left: 8, right: 8 }, 
                    font: { weight: '900', size: 12, family: fontTheme }, 
                    anchor: 'end', align: 'top', offset: 8,
                    formatter: (val) => val.toLocaleString('id-ID') 
                }
            }
        }
    });

    // 2. CHART KG (BATANG ORANGE/AMBER - Sinkron dengan Widget)
    new Chart(document.getElementById('chartKG').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labelsTgl,
            datasets: [{
                label: 'Total KG',
                data: data_kg,
                backgroundColor: '#f59e0b',
                hoverBackgroundColor: '#d97706',
                borderWidth: 2,
                borderColor: 'transparent',
                hoverBorderColor: '#ffffff',
                borderRadius: 4
            }]
        },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: { ...premiumTooltip, callbacks: { label: function(context) { return 'Berat: ' + context.raw.toLocaleString('id-ID') + ' KG'; } } },
                datalabels: { 
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0, 
                    color: '#ffffff', backgroundColor: '#f59e0b', 
                    borderRadius: 4, padding: { top: 4, bottom: 4, left: 6, right: 6 }, font: labelFont, 
                    anchor: 'end', align: 'top', offset: 4,
                    formatter: (val) => val.toLocaleString('id-ID') 
                }
            }
        }
    });

    // 3. CHART M2 (BATANG MERAH/ROSE - Sinkron dengan Widget)
    new Chart(document.getElementById('chartM2').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labelsTgl,
            datasets: [{
                label: 'Total M²',
                data: data_m2,
                backgroundColor: '#f43f5e',
                hoverBackgroundColor: '#e11d48',
                borderWidth: 2,
                borderColor: 'transparent',
                hoverBorderColor: '#ffffff',
                borderRadius: 4
            }]
        },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: { ...premiumTooltip, callbacks: { label: function(context) { return 'Luas: ' + context.raw.toLocaleString('id-ID') + ' M²'; } } },
                datalabels: { 
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0, 
                    color: '#ffffff', backgroundColor: '#f43f5e', 
                    borderRadius: 4, padding: { top: 4, bottom: 4, left: 6, right: 6 }, font: labelFont, 
                    anchor: 'end', align: 'top', offset: 4,
                    formatter: (val) => val.toLocaleString('id-ID') 
                }
            }
        }
    });
</script>
<?php endif; ?>
<?php require_once 'footer.php'; ?>