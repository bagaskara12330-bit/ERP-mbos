<?php
require_once 'auth.php';
require_akses('dash_eff');// 1. CARI TAHUN APA SAJA YANG TERSEDIA DI DATABASE...
// 1. CARI TAHUN APA SAJA YANG TERSEDIA DI DATABASE
try {
    $stmt_years = $pdo->query("SELECT DISTINCT tahun FROM rekap_bulanan ORDER BY tahun ASC");
    $years_db = $stmt_years->fetchAll(PDO::FETCH_COLUMN);
    $years_available = (count($years_db) > 0) ? $years_db : [2025, 2026];
} catch (PDOException $e) {
    $years_available = [2025, 2026];
}

// 2. TANGKAP FILTER DARI USER
$filter_tahun = isset($_GET['filter_tahun']) ? $_GET['filter_tahun'] : '';
$filter_bulan = isset($_GET['filter_bulan']) ? $_GET['filter_bulan'] : '';

$active_years = ($filter_tahun === '') ? $years_available : [intval($filter_tahun)];
$active_months = ($filter_bulan === '') ? range(0, 11) : [intval($filter_bulan)];

// Nama Bulan Bawaan
$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Mapping standar nama bulan
$monthMap = [
    'januari'=>0, 'jan'=>0, 'january'=>0,
    'februari'=>1, 'feb'=>1, 'february'=>1,
    'maret'=>2, 'mar'=>2, 'march'=>2,
    'april'=>3, 'apr'=>3,
    'mei'=>4, 'may'=>4,
    'juni'=>5, 'jun'=>5, 'june'=>5,
    'juli'=>6, 'jul'=>6, 'july'=>6,
    'agustus'=>7, 'aug'=>7, 'august'=>7,
    'september'=>8, 'sep'=>8,
    'oktober'=>9, 'oct'=>9, 'october'=>9,
    'november'=>10, 'nov'=>10,
    'desember'=>11, 'dec'=>11, 'december'=>11
];

// 3. INISIALISASI WADAH AGREGASI DATA
$agg = [];
foreach ($active_years as $y) {
    $agg[$y] = array_fill(0, 12, [
        'tstb' => 0, 'batubara' => 0, 'tapioka' => 0, 'waste_kg' => 0, 
        'pakai_paper' => 0, 'dt' => 0, 'solar' => 0, 'tinta' => 0, 'flexo_pcs' => 0
    ]);
}

// 4. TARIK DAN JUMLAHKAN DATA DARI DATABASE
try {
    $stmt = $pdo->query("SELECT * FROM rekap_bulanan");
    $all_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_data as $row) {
        $y = intval($row['tahun']);
        $b_raw = strtolower(trim($row['bulan']));
        $m_idx = isset($monthMap[$b_raw]) ? $monthMap[$b_raw] : -1;

        if ($m_idx >= 0 && in_array($y, $active_years)) {
            $agg[$y][$m_idx]['tstb'] += floatval($row['tstb_kg']);
            $agg[$y][$m_idx]['batubara'] += floatval($row['batu_bara']);
            $agg[$y][$m_idx]['tapioka'] += floatval($row['tapioka']);
            $agg[$y][$m_idx]['waste_kg'] += floatval($row['waste_kg']);
            $agg[$y][$m_idx]['pakai_paper'] += floatval($row['pakai_paper']);
            $agg[$y][$m_idx]['dt'] += floatval($row['downtime_mnt']);
            $agg[$y][$m_idx]['solar'] += floatval($row['solar']);
            $agg[$y][$m_idx]['tinta'] += floatval($row['tinta']);
            $agg[$y][$m_idx]['flexo_pcs'] += (intval($row['flexo_st']) + intval($row['flexo_inline']));
        }
    }
} catch (PDOException $e) {}

// 5. HITUNG RASIO & SIAPKAN JSON UNTUK CHART & WIDGET TOTAL
$labels_to_show = [];
foreach ($active_months as $m) {
    $labels_to_show[] = $monthNames[$m];
}

$c_tstb = []; $c_bb = []; $c_tap = []; $c_wst = []; $c_dt = []; $c_sol = []; $c_tin = [];

// Variabel Penampung Total Widget
$sum_tstb = 0; $sum_tinta = 0; $sum_batubara = 0; 
$sum_paper = 0; $sum_tapioka = 0; $sum_dt = 0; $sum_solar = 0;

foreach ($active_years as $y) {
    $c_tstb[$y] = []; $c_bb[$y] = []; $c_tap[$y] = []; $c_wst[$y] = []; 
    $c_dt[$y] = []; $c_sol[$y] = []; $c_tin[$y] = [];

    foreach ($active_months as $i) {
        $a = $agg[$y][$i];
        
        $sum_tstb += $a['tstb'];
        $sum_tinta += $a['tinta'];
        $sum_batubara += $a['batubara'];
        $sum_paper += $a['pakai_paper'];
        $sum_tapioka += $a['tapioka'];
        $sum_dt += $a['dt'];
        $sum_solar += $a['solar'];

        $ton_tstb = $a['tstb'] / 1000;
        $c_tstb[$y][] = round($a['tstb'], 0);
        $c_bb[$y][]   = $ton_tstb > 0 ? round($a['batubara'] / $ton_tstb, 0) : 0;
        $c_tap[$y][]  = $ton_tstb > 0 ? round($a['tapioka'] / $ton_tstb, 0) : 0;
        $c_wst[$y][]  = $a['pakai_paper'] > 0 ? round(($a['waste_kg'] / $a['pakai_paper']) * 100, 2) : 0;
        $c_dt[$y][]   = round($a['dt'], 0);
        $c_sol[$y][]  = $ton_tstb > 0 ? round($a['solar'] / $ton_tstb, 1) : 0;
        $c_tin[$y][]  = $a['flexo_pcs'] > 0 ? round(($a['tinta'] * 1000) / $a['flexo_pcs'], 1) : 0;
    }
}

// === PANGGIL HEADER TERPUSAT ===
$page_title = "H2 BASE — Dashboard Efisiensi";
$active_page = "dashboard2";
require 'header.php';
?>

<style>
    /* 🚀 ANIMASI ENTRANCE GLOBAL (MUNCUL MELAYANG BERURUTAN) */
    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .filter-card { 
        background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; 
        margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        animation: slideUpFade 0.4s ease-out forwards;
    }
    
    .filter-container { display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
    .form-group { display: flex; flex-direction: column; min-width: 140px; }
    .form-group label { margin-bottom: 6px; font-weight: 600; font-size: 12px; color: #475569; text-transform: uppercase; }
    select { 
        padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; 
        color: #0f172a; background: #f8fafc; outline: none; cursor: pointer; transition: 0.2s;
    }
    select:focus, select:hover { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }
    
    .btn-filter { 
        background: #0f172a; color: white; border: none; padding: 10px 24px; border-radius: 6px; 
        font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; 
    }
    .btn-filter:hover { background: #1e293b; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.3); transform: translateY(-1px); }
    .btn-filter:active { transform: scale(0.95); } /* Tombol bereaksi ditekan */
    
    .btn-reset { 
        background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 9px 24px; text-decoration: none; 
        border-radius: 6px; font-size: 13px; font-weight: 600; text-align: center; transition: 0.2s;
    }
    .btn-reset:hover { background: #e2e8f0; color: #0f172a; }
    
    .summary-grid { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
    
    /* 🚀 EFEK ANIMASI MELAYANG (HOVER 3D + STAGGERED ENTRANCE) PADA KOTAK WIDGET */
    .summary-card { 
        flex: 1 1 12%; background: white; padding: 18px; border-radius: 10px; 
        border: 1px solid #e2e8f0; border-top: 4px solid #cbd5e1; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); min-width: 120px; 
        display: flex; flex-direction: column; justify-content: center;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); /* Transisi halus & elastis */
        opacity: 0; 
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    /* Masing-masing kotak memiliki delay agar muncul berurutan seperti efek Domino */
    .summary-card:nth-child(1) { animation-delay: 0.1s; }
    .summary-card:nth-child(2) { animation-delay: 0.2s; }
    .summary-card:nth-child(3) { animation-delay: 0.3s; }
    .summary-card:nth-child(4) { animation-delay: 0.4s; }
    .summary-card:nth-child(5) { animation-delay: 0.5s; }
    .summary-card:nth-child(6) { animation-delay: 0.6s; }
    .summary-card:nth-child(7) { animation-delay: 0.7s; }

    /* EFEK HOVER 3D MAGNETIC */
    .summary-card:hover { 
        transform: translateY(-6px) scale(1.02); 
        box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); 
        cursor: pointer;
    }
    
    .summary-card .title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; }
    .summary-card .value { font-size: 20px; font-weight: 900; color: #0f172a; margin: 4px 0; }
    .summary-card .unit { font-size: 12px; font-weight: 600; color: #94a3b8; }

    /* 🚀 KOTAK GRAFIK MUNCUL MELAYANG BERURUTAN */
    .full-width-card { 
        background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        opacity: 0; animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        animation-delay: 0.3s;
        transition: all 0.3s ease;
    }
    .full-width-card:hover { box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }
    
    .half-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; align-items: stretch; }
    .chart-container { 
        background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; flex-direction: column; 
        opacity: 0; animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        transition: all 0.3s ease;
    }
    
    /* Animasi Staggered untuk kotak-kotak grafik (Grid Bawah) */
    .half-grid:nth-of-type(4) .chart-container:nth-child(1) { animation-delay: 0.4s; }
    .half-grid:nth-of-type(4) .chart-container:nth-child(2) { animation-delay: 0.5s; }
    .half-grid:nth-of-type(5) .chart-container:nth-child(1) { animation-delay: 0.6s; }
    .half-grid:nth-of-type(5) .chart-container:nth-child(2) { animation-delay: 0.7s; }
    .half-grid:nth-of-type(6) .chart-container:nth-child(1) { animation-delay: 0.8s; }
    .half-grid:nth-of-type(6) .chart-container:nth-child(2) { animation-delay: 0.9s; }

    .chart-container:hover { box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }
    .chart-title { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 16px; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; text-align: left; }

    @media (max-width: 992px) { .half-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { .summary-card { flex: 1 1 45%; } .filter-container { flex-direction: column; align-items: stretch; } }
</style>

<div class="filter-card">
    <form method="GET" action="" class="filter-container">
        <div class="form-group">
            <label>Filter Tahun</label>
            <select name="filter_tahun">
                <option value="">Semua Tahun (Bandingkan)</option>
                <?php foreach ($years_available as $th): ?>
                    <option value="<?= $th ?>" <?= $filter_tahun == $th ? 'selected' : '' ?>><?= $th ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Filter Bulan</label>
            <select name="filter_bulan">
                <option value="">Semua Bulan</option>
                <?php foreach ($monthNames as $idx => $mName): ?>
                    <option value="<?= $idx ?>" <?= $filter_bulan !== '' && $filter_bulan == $idx ? 'selected' : '' ?>><?= $mName ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-filter">🔍 Terapkan Filter</button>
        <?php if($filter_tahun != '' || $filter_bulan != ''): ?>
            <a href="dashboard2.php" class="btn-reset">✖ Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- 🚀 WIDGET SUMMARY INTERAKTIF -->
<div class="summary-grid">
    <div class="summary-card" style="border-top-color: #3b82f6;">
        <div class="title">Total TSTB</div>
        <div class="value" title="<?= number_format($sum_tstb, 0, ',', '.') ?> KG">
            <?= number_format($sum_tstb, 0, ',', '.') ?> <span class="unit">KG</span>
        </div>
    </div>
    <div class="summary-card" style="border-top-color: #10b981;">
        <div class="title">Total Kertas</div>
        <div class="value" title="<?= number_format($sum_paper, 0, ',', '.') ?> KG">
            <?= number_format($sum_paper, 0, ',', '.') ?> <span class="unit">KG</span>
        </div>
    </div>
    <div class="summary-card" style="border-top-color: #8b5cf6;">
        <div class="title">Total Tinta</div>
        <div class="value" title="<?= number_format($sum_tinta, 1, ',', '.') ?> KG">
            <?= number_format($sum_tinta, 1, ',', '.') ?> <span class="unit">KG</span>
        </div>
    </div>
    <div class="summary-card" style="border-top-color: #475569;">
        <div class="title">Total Batu Bara</div>
        <div class="value" title="<?= number_format($sum_batubara, 0, ',', '.') ?> KG">
            <?= number_format($sum_batubara, 0, ',', '.') ?> <span class="unit">KG</span>
        </div>
    </div>
    <div class="summary-card" style="border-top-color: #eab308;">
        <div class="title">Total Tapioka</div>
        <div class="value" title="<?= number_format($sum_tapioka, 0, ',', '.') ?> KG">
            <?= number_format($sum_tapioka, 0, ',', '.') ?> <span class="unit">KG</span>
        </div>
    </div>
    <div class="summary-card" style="border-top-color: #ef4444;">
        <div class="title">Total Downtime</div>
        <div class="value" title="<?= number_format($sum_dt, 0, ',', '.') ?> Mnt">
            <?= number_format($sum_dt, 0, ',', '.') ?> <span class="unit">Mnt</span>
        </div>
    </div>
    <div class="summary-card" style="border-top-color: #f97316;">
        <div class="title">Total Solar</div>
        <div class="value" title="<?= number_format($sum_solar, 1, ',', '.') ?> Ltr">
            <?= number_format($sum_solar, 1, ',', '.') ?> <span class="unit">Ltr</span>
        </div>
    </div>
</div>

<div class="full-width-card">
    <h3 class="chart-title" style="text-align: center;">TSTB H2 (kg)</h3>
    <div style="position: relative; height: 450px; width: 100%;">
        <canvas id="chartTstb"></canvas>
    </div>
</div>

<div class="half-grid">
    <div class="chart-container">
        <h3 class="chart-title">BATU BARA (kg/TON)</h3>
        <div style="position: relative; height: 350px; width: 100%;">
            <canvas id="chartBatuBara"></canvas>
        </div>
    </div>
    <div class="chart-container">
        <h3 class="chart-title">TAPIOKA (kg/TON)</h3>
        <div style="position: relative; height: 350px; width: 100%;">
            <canvas id="chartTapioka"></canvas>
        </div>
    </div>
</div>

<div class="half-grid">
    <div class="chart-container">
        <h3 class="chart-title">WASTE (%)</h3>
        <div style="position: relative; height: 350px; width: 100%;">
            <canvas id="chartWaste"></canvas>
        </div>
    </div>
    <div class="chart-container">
        <h3 class="chart-title">DOWNTIME (mnt)</h3>
        <div style="position: relative; height: 350px; width: 100%;">
            <canvas id="chartDowntime"></canvas>
        </div>
    </div>
</div>

<div class="half-grid">
    <div class="chart-container">
        <h3 class="chart-title">SOLAR (liter/TON)</h3>
        <div style="position: relative; height: 350px; width: 100%;">
            <canvas id="chartSolar"></canvas>
        </div>
    </div>
    <div class="chart-container">
        <h3 class="chart-title">TINTA (Gr/PCS)</h3>
        <div style="position: relative; height: 350px; width: 100%;">
            <canvas id="chartTinta"></canvas>
        </div>
    </div>
</div>

<script>
Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
Chart.defaults.color = '#475569';
Chart.register(ChartDataLabels);

const legendMarginPlugin = { 
    id: 'legendMargin', 
    beforeInit: function(chart) { 
        const originalFit = chart.legend.fit; 
        chart.legend.fit = function fit() { 
            originalFit.bind(chart.legend)(); 
            this.height += 25; 
        }; 
    } 
};

// 🚀 FONT DAN TOOLTIP DARK MODE PREMIUM KHUSUS CHART 
const fontTheme = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
const premiumTooltip = {
    backgroundColor: 'rgba(15, 23, 42, 0.95)',
    titleFont: { size: 13, family: fontTheme, weight: 'normal' },
    bodyFont: { size: 14, weight: 'bold', family: fontTheme },
    padding: 12, cornerRadius: 8, displayColors: false, caretPadding: 10
};

const activeYears = <?= json_encode($active_years) ?>;
const labelsXAxis = <?= json_encode($labels_to_show) ?>; 
const dataTstb = <?= json_encode($c_tstb) ?>; 
const dataBb = <?= json_encode($c_bb) ?>; 
const dataTap = <?= json_encode($c_tap) ?>; 
const dataWst = <?= json_encode($c_wst) ?>; 
const dataDt = <?= json_encode($c_dt) ?>; 
const dataSol = <?= json_encode($c_sol) ?>; 
const dataTin = <?= json_encode($c_tin) ?>;

const colorPalette = { 0: '#3b82f6', 1: '#1e3a8a', 2: '#f59e0b' };

// 🚀 RENDER MASTER CHART DENGAN HOVER & ANIMASI PREMIUM
function renderGroupedBarChart(canvasId, dataObj, suffix = '', decimals = 0, hideDataLabels = false, labelFontSize = 16) {
    const datasets = [];
    activeYears.forEach((year, index) => {
        datasets.push({
            label: year.toString(),
            data: dataObj[year],
            backgroundColor: colorPalette[index % 3],
            hoverBackgroundColor: '#0f172a', // Warna gelap saat dihover
            borderColor: 'transparent',
            hoverBorderColor: '#ffffff', // Garis putih menyala saat hover
            borderWidth: 2,
            borderRadius: 6,
            barPercentage: 0.8,
            categoryPercentage: 0.75
        });
    });

    new Chart(document.getElementById(canvasId), {
        type: 'bar',
        data: { labels: labelsXAxis, datasets: datasets },
        plugins: [legendMarginPlugin],
        options: {
            responsive: true, 
            maintainAspectRatio: false, 
            layout: { padding: { top: 35 } },
            interaction: { mode: 'index', intersect: false }, // Hover sensitif (dimana saja 1 area)
            animation: { duration: 1500, easing: 'easeOutQuart' }, // Animasi Bar Tumbuh Halus
            plugins: {
                tooltip: Object.assign({}, premiumTooltip, {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || ''; if (label) { label += ' Data: '; }
                            if (context.parsed.y !== null) {
                                if (canvasId === 'chartTstb') {
                                    let valTon = context.parsed.y / 1000;
                                    label += context.parsed.y.toLocaleString('id-ID') + ' kg (' + valTon.toLocaleString('id-ID', {minimumFractionDigits:1, maximumFractionDigits:1}) + ' Ton)';
                                } else { label += context.parsed.y.toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix; }
                            }
                            return label;
                        }
                    }
                }),
                legend: { position: 'top', align: 'center', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, font: { weight: 'bold', size: 13, family: fontTheme }, color: '#334155', padding: 15 }, title: { display: true, text: 'TAHUN', font: { weight: 'bold', size: 14, family: fontTheme }, color: '#0f172a', padding: { bottom: 5 } } },
                datalabels: {
                    display: !hideDataLabels, anchor: 'end', align: 'top', offset: 8, 
                    backgroundColor: '#ffffff', 
                    borderRadius: 6, borderWidth: 1, borderColor: '#cbd5e1', 
                    color: '#0f172a', font: { size: labelFontSize, weight: 'bold', family: fontTheme }, padding: { top: 4, bottom: 4, left: 8, right: 8 },
                    formatter: function(value) {
                        if(value === 0 || value === "0.0" || value === "0.00") return '';
                        if (canvasId === 'chartTstb') { let tonVal = value / 1000; return tonVal.toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + ' Ton'; }
                        if(value >= 1000 && suffix === '') { return (value / 1000).toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1}) + 'k'; }
                        return value.toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }) + suffix;
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 12, weight: '600', family: fontTheme }, color: '#475569', maxRotation: 0, minRotation: 0 } },
                y: { border: { display: false }, grid: { color: '#e2e8f0', drawTicks: false, borderDash: [5, 5] }, ticks: { display: false }, beginAtZero: true, grace: '20%' }
            }
        }
    });
}

renderGroupedBarChart('chartTstb', dataTstb, '', 0, false, 13); 
renderGroupedBarChart('chartBatuBara', dataBb, '', 0, false, 15);
renderGroupedBarChart('chartTapioka', dataTap, '', 0, false, 15);
renderGroupedBarChart('chartWaste', dataWst, '%', 2, false, 15); 
renderGroupedBarChart('chartDowntime', dataDt, '', 0, false, 15);
renderGroupedBarChart('chartSolar', dataSol, '', 1, false, 15); 
renderGroupedBarChart('chartTinta', dataTin, '', 1, false, 15);
</script>
<?php require_once 'footer.php'; ?>