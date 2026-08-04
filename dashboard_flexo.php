<?php
require_once 'auth.php';
require_akses('dash_flexo');$bulan_ini = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$tahun_ini = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$nama_bulan_list = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$nama_bulan = $nama_bulan_list[$bulan_ini];

try {
    $stmt = $pdo->prepare("SELECT * FROM db_flexo_prod WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal ASC");
    $stmt->execute([$bulan_ini, $tahun_ini]);
    $data_flexo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // INISIALISASI VARIABEL 4 MESIN
    $tot_i_pcs = 0; $tot_s_pcs = 0; $tot_sa_pcs = 0; $tot_g_pcs = 0;
    $tot_i_jam = 0; $tot_s_jam = 0; $tot_sa_jam = 0; $tot_g_jam = 0;
    $tot_i_dt = 0;  $tot_s_dt = 0;  $tot_sa_dt = 0;  $tot_g_dt = 0;

    $labels_tgl = []; 
    $chart_i_pcs = []; $chart_s_pcs = []; $chart_sa_pcs = []; $chart_g_pcs = [];
    $chart_i_dt = [];  $chart_s_dt = [];  $chart_sa_dt = [];  $chart_g_dt = [];
    $chart_i_prod = [];$chart_s_prod = [];$chart_sa_prod = [];$chart_g_prod = [];

    foreach($data_flexo as $row) {
        $labels_tgl[] = date('d', strtotime($row['tanggal']));

        $tot_i_pcs += $row['inline_pcs']; $tot_s_pcs += $row['stacker_pcs'];
        $tot_sa_pcs += $row['stitch_auto_pcs'] ?? 0; $tot_g_pcs += $row['glue_pcs'] ?? 0;

        $tot_i_jam += $row['inline_jam']; $tot_s_jam += $row['stacker_jam'];
        $tot_sa_jam += $row['stitch_auto_jam'] ?? 0; $tot_g_jam += $row['glue_jam'] ?? 0;

        $tot_i_dt  += $row['inline_dt'];  $tot_s_dt  += $row['stacker_dt'];
        $tot_sa_dt += $row['stitch_auto_dt'] ?? 0; $tot_g_dt += $row['glue_dt'] ?? 0;

        $chart_i_pcs[] = $row['inline_pcs']; $chart_s_pcs[] = $row['stacker_pcs'];
        $chart_sa_pcs[] = $row['stitch_auto_pcs'] ?? 0; $chart_g_pcs[] = $row['glue_pcs'] ?? 0;

        $chart_i_dt[]  = ($row['inline_jam'] > 0) ? round(($row['inline_dt'] / ($row['inline_jam'] * 60)) * 100, 1) : 0;
        $chart_s_dt[]  = ($row['stacker_jam'] > 0) ? round(($row['stacker_dt'] / ($row['stacker_jam'] * 60)) * 100, 1) : 0;
        $chart_sa_dt[] = (!empty($row['stitch_auto_jam']) && $row['stitch_auto_jam'] > 0) ? round((($row['stitch_auto_dt'] ?? 0) / ($row['stitch_auto_jam'] * 60)) * 100, 1) : 0;
        $chart_g_dt[]  = (!empty($row['glue_jam']) && $row['glue_jam'] > 0) ? round((($row['glue_dt'] ?? 0) / ($row['glue_jam'] * 60)) * 100, 1) : 0;

        $ip = ($row['inline_jam'] > 0) ? ($row['inline_pcs'] / ($row['inline_jam'] * 60)) : 0;
        $sp = ($row['stacker_jam'] > 0) ? ($row['stacker_pcs'] / ($row['stacker_jam'] * 60)) : 0;
        $sap= (!empty($row['stitch_auto_jam']) && $row['stitch_auto_jam'] > 0) ? ($row['stitch_auto_pcs'] / ($row['stitch_auto_jam'] * 60)) : 0;
        $gp = (!empty($row['glue_jam']) && $row['glue_jam'] > 0) ? ($row['glue_pcs'] / ($row['glue_jam'] * 60)) : 0;
        
        $chart_i_prod[] = round($ip, 2); $chart_s_prod[] = round($sp, 2);
        $chart_sa_prod[] = round($sap, 2); $chart_g_prod[] = round($gp, 2);
    }

    $avg_i_prod = ($tot_i_jam > 0) ? ($tot_i_pcs / ($tot_i_jam * 60)) : 0;
    $avg_s_prod = ($tot_s_jam > 0) ? ($tot_s_pcs / ($tot_s_jam * 60)) : 0;
    $avg_sa_prod= ($tot_sa_jam > 0) ? ($tot_sa_pcs / ($tot_sa_jam * 60)) : 0;
    $avg_g_prod = ($tot_g_jam > 0) ? ($tot_g_pcs / ($tot_g_jam * 60)) : 0;

    $pct_i_dt = ($tot_i_jam > 0) ? ($tot_i_dt / ($tot_i_jam * 60)) * 100 : 0;
    $pct_s_dt = ($tot_s_jam > 0) ? ($tot_s_dt / ($tot_s_jam * 60)) * 100 : 0;
    $pct_sa_dt= ($tot_sa_jam > 0) ? ($tot_sa_dt / ($tot_sa_jam * 60)) * 100 : 0;
    $pct_g_dt = ($tot_g_jam > 0) ? ($tot_g_dt / ($tot_g_jam * 60)) * 100 : 0;

    $total_semua_pcs = $tot_i_pcs + $tot_s_pcs + $tot_sa_pcs + $tot_g_pcs;

} catch (PDOException $e) {
    $total_semua_pcs = 0;
    $tot_i_pcs = 0; $tot_s_pcs = 0; $tot_sa_pcs = 0; $tot_g_pcs = 0;
    $tot_i_jam = 0; $tot_s_jam = 0; $tot_sa_jam = 0; $tot_g_jam = 0;
    $avg_i_prod = 0; $avg_s_prod = 0; $avg_sa_prod = 0; $avg_g_prod = 0; 
    $pct_i_dt = 0; $pct_s_dt = 0; $pct_sa_dt = 0; $pct_g_dt = 0;
    $labels_tgl = []; 
    $chart_i_pcs = []; $chart_s_pcs = []; $chart_sa_pcs = []; $chart_g_pcs = [];
    $chart_i_dt = []; $chart_s_dt = []; $chart_sa_dt = []; $chart_g_dt = []; 
    $chart_i_prod = []; $chart_s_prod = []; $chart_sa_prod = []; $chart_g_prod = [];
}

$js_tgl = json_encode($labels_tgl);
$js_i_pcs = json_encode($chart_i_pcs); $js_s_pcs = json_encode($chart_s_pcs); $js_sa_pcs = json_encode($chart_sa_pcs); $js_g_pcs = json_encode($chart_g_pcs);
$js_i_dt = json_encode($chart_i_dt);   $js_s_dt = json_encode($chart_s_dt);   $js_sa_dt = json_encode($chart_sa_dt);   $js_g_dt = json_encode($chart_g_dt);
$js_i_prod = json_encode($chart_i_prod); $js_s_prod = json_encode($chart_s_prod); $js_sa_prod = json_encode($chart_sa_prod); $js_g_prod = json_encode($chart_g_prod);

$page_title = "Dashboard Flexo — H2 BASE ERP";
$active_page = "dashboard_flexo";
require 'header.php';
?>

<style>
    /* 🚀 ANIMASI ENTRANCE GLOBAL (MUNCUL MELAYANG BERURUTAN) */
    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .filter-card { 
        background: white; padding: 15px 20px; border-radius: 10px; border: 1px solid #e2e8f0; 
        margin-bottom: 24px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.02); 
        animation: slideUpFade 0.4s ease-out forwards;
    }
    .filter-card label { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; }
    .filter-card select { 
        padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; 
        color: #0f172a; background: #f8fafc; outline: none; cursor: pointer; transition: 0.2s;
    }
    .filter-card select:focus, .filter-card select:hover { border-color: #0ea5e9; }
    .btn-filter-submit { 
        background: #0f172a; color: white; border: none; padding: 9px 20px; border-radius: 6px; 
        font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; 
    }
    .btn-filter-submit:hover { background: #1e293b; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.3); transform: translateY(-1px); }
    .btn-filter-submit:active { transform: scale(0.95); }

    .summary-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; margin-bottom: 24px; }
    
    /* 🚀 EFEK ANIMASI MELAYANG (HOVER 3D + STAGGERED ENTRANCE) PADA KOTAK WIDGET */
    .summary-card { 
        background: white; padding: 18px; border-radius: 10px; border: 1px solid #e2e8f0; border-top: 4px solid #cbd5e1; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        opacity: 0; 
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* Masing-masing kotak memiliki delay berurutan */
    .summary-card:nth-child(1) { animation-delay: 0.1s; }
    .summary-card:nth-child(2) { animation-delay: 0.15s; }
    .summary-card:nth-child(3) { animation-delay: 0.2s; }
    .summary-card:nth-child(4) { animation-delay: 0.25s; }
    .summary-card:nth-child(5) { animation-delay: 0.3s; }
    .summary-card:nth-child(6) { animation-delay: 0.35s; }
    .summary-card:nth-child(7) { animation-delay: 0.4s; }
    .summary-card:nth-child(8) { animation-delay: 0.45s; }
    .summary-card:nth-child(9) { animation-delay: 0.5s; }
    .summary-card:nth-child(10) { animation-delay: 0.55s; }
    .summary-card:nth-child(11) { animation-delay: 0.6s; }
    .summary-card:nth-child(12) { animation-delay: 0.65s; }

    .summary-card:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); cursor: pointer; }
    
    .summary-card .title { font-size: 10.5px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; }
    .summary-card .value { font-size: 22px; font-weight: 900; color: #0f172a; margin: 4px 0; }
    .summary-card .unit { font-size: 11px; font-weight: 600; color: #94a3b8; margin-left: 4px; }

    /* 🚀 KOTAK GRAFIK MUNCUL MELAYANG BERURUTAN */
    .full-width-card { 
        background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 24px; 
        opacity: 0; animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; transition: all 0.3s ease;
    }
    .full-width-card:nth-of-type(3) { animation-delay: 0.7s; }
    .full-width-card:nth-of-type(4) { animation-delay: 0.8s; }
    .full-width-card:hover { box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }

    .full-width-card h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 12px; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
    .scroll-wrapper { overflow-x: auto; overflow-y: hidden; padding-top: 15px; padding-bottom: 10px; }
    .scroll-wrapper::-webkit-scrollbar { height: 8px; }
    .scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
    .scroll-wrapper::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .scroll-wrapper::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    .canvas-scroll-inner { position: relative; min-width: 1000px; height: 350px; } 

    .half-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; align-items: stretch; }
    
    .chart-card { 
        background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; flex-direction: column; min-height: 350px; 
        opacity: 0; animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; transition: all 0.3s ease;
    }
    .half-grid:nth-of-type(5) .chart-card:nth-child(1) { animation-delay: 0.9s; }
    .half-grid:nth-of-type(5) .chart-card:nth-child(2) { animation-delay: 1.0s; }
    .chart-card:hover { box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }

    .chart-card h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 16px; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
    .canvas-wrapper { position: relative; flex-grow: 1; height: 100%; min-height: 300px; width: 100%; }

    @media (max-width: 1400px) { .summary-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 992px) { .half-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } }
</style>

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
    <button type="submit" class="btn-filter-submit">🔍 Terapkan Saringan</button>
</form>

<?php if ($total_semua_pcs == 0 && empty($data_flexo)): ?>
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 40px; text-align: center; color: #b45309; margin-bottom: 24px; animation: slideUpFade 0.5s ease-out;">
        <div style="font-size: 40px; margin-bottom: 15px;">📭</div>
        <h3 style="margin: 0 0 10px 0; font-size: 18px;">Tidak Ada Data Produksi</h3>
        <p style="margin: 0; font-size: 14px; font-weight: 500;">Belum ada mesin yang melaporkan hasil produksi (Inline, Stacker, Auto, atau GLUE) pada bulan <?= $nama_bulan ?> <?= $tahun_ini ?>.</p>
    </div>
<?php else: ?>

<div class="summary-grid">
    <?php if ($tot_i_jam > 0 || $tot_i_pcs > 0): ?>
    <div class="summary-card" style="border-top-color: #0ea5e9; background: #f0f9ff;">
        <div class="title" style="color: #0284c7;">Total PCS Inline</div>
        <div class="value" style="color: #0369a1;"><?= number_format($tot_i_pcs, 0, ',', '.') ?> <span class="unit" style="color: #38bdf8;">PCS</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #0ea5e9;">
        <div class="title">Rata-rata Cepat Inline</div>
        <div class="value"><?= number_format($avg_i_prod, 2, ',', '.') ?> <span class="unit">PCS/Mnt</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #0ea5e9;">
        <div class="title">Total Downtime Inline</div>
        <div class="value" style="color: #e11d48;"><?= number_format($pct_i_dt, 2, ',', '.') ?>% <span class="unit">(<?= number_format($tot_i_dt) ?> Mnt)</span></div>
    </div>
    <?php endif; ?>

    <?php if ($tot_s_jam > 0 || $tot_s_pcs > 0): ?>
    <div class="summary-card" style="border-top-color: #16a34a; background: #f0fdf4;">
        <div class="title" style="color: #15803d;">Total PCS Stacker</div>
        <div class="value" style="color: #14532d;"><?= number_format($tot_s_pcs, 0, ',', '.') ?> <span class="unit" style="color: #4ade80;">PCS</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #10b981;">
        <div class="title">Rata-rata Cepat Stacker</div>
        <div class="value"><?= number_format($avg_s_prod, 2, ',', '.') ?> <span class="unit">PCS/Mnt</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #10b981;">
        <div class="title">Total Downtime Stacker</div>
        <div class="value" style="color: #e11d48;"><?= number_format($pct_s_dt, 2, ',', '.') ?>% <span class="unit">(<?= number_format($tot_s_dt) ?> Mnt)</span></div>
    </div>
    <?php endif; ?>

    <?php if ($tot_sa_jam > 0 || $tot_sa_pcs > 0): ?>
    <div class="summary-card" style="border-top-color: #9333ea; background: #faf5ff;">
        <div class="title" style="color: #7e22ce;">Total PCS Stitch Auto</div>
        <div class="value" style="color: #6d28d9;"><?= number_format($tot_sa_pcs, 0, ',', '.') ?> <span class="unit" style="color: #c084fc;">PCS</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #a855f7;">
        <div class="title">Rata-rata Cepat Auto</div>
        <div class="value"><?= number_format($avg_sa_prod, 2, ',', '.') ?> <span class="unit">PCS/Mnt</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #a855f7;">
        <div class="title">Total Downtime Auto</div>
        <div class="value" style="color: #e11d48;"><?= number_format($pct_sa_dt, 2, ',', '.') ?>% <span class="unit">(<?= number_format($tot_sa_dt) ?> Mnt)</span></div>
    </div>
    <?php endif; ?>

    <?php if ($tot_g_jam > 0 || $tot_g_pcs > 0): ?>
    <div class="summary-card" style="border-top-color: #e11d48; background: #fff1f2;">
        <div class="title" style="color: #be123c;">Total PCS Mesin GLUE</div>
        <div class="value" style="color: #9f1239;"><?= number_format($tot_g_pcs, 0, ',', '.') ?> <span class="unit" style="color: #fb7185;">PCS</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #f43f5e;">
        <div class="title">Rata-rata Cepat GLUE</div>
        <div class="value"><?= number_format($avg_g_prod, 2, ',', '.') ?> <span class="unit">PCS/Mnt</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #f43f5e;">
        <div class="title">Total Downtime GLUE</div>
        <div class="value" style="color: #e11d48;"><?= number_format($pct_g_dt, 2, ',', '.') ?>% <span class="unit">(<?= number_format($tot_g_dt) ?> Mnt)</span></div>
    </div>
    <?php endif; ?>
</div>

<div class="full-width-card">
    <h3>📦 Tren Total PCS Produksi Harian</h3>
    <div class="scroll-wrapper">
        <div class="canvas-scroll-inner">
            <canvas id="chartProduksiPcs"></canvas>
        </div>
    </div>
</div>

<div class="full-width-card">
    <h3>⚡ Kecepatan Mesin (Produktifitas PCS/Menit)</h3>
    <div class="scroll-wrapper">
        <div class="canvas-scroll-inner">
            <canvas id="chartProduktifitas"></canvas>
        </div>
    </div>
</div>

<div class="half-grid">
    <div class="chart-card">
        <h3>⏱️ Grafik Downtime Harian (%)</h3>
        <div class="scroll-wrapper">
            <div class="canvas-scroll-inner" style="min-width: 600px; height: 300px;">
                <canvas id="chartDowntime"></canvas>
            </div>
        </div>
    </div>
    <div class="chart-card">
        <h3>🍕 Proporsi Output Bulan Ini</h3>
        <div class="canvas-wrapper">
            <canvas id="chartProporsi"></canvas>
        </div>
    </div>
</div>

<script>
    Chart.register(ChartDataLabels);

    const labelsTgl = <?= $js_tgl ?>;
    const data_i_pcs = <?= $js_i_pcs ?>; const data_i_prod = <?= $js_i_prod ?>; const data_i_dt = <?= $js_i_dt ?>;
    const data_s_pcs = <?= $js_s_pcs ?>; const data_s_prod = <?= $js_s_prod ?>; const data_s_dt = <?= $js_s_dt ?>;
    const data_sa_pcs = <?= $js_sa_pcs ?>; const data_sa_prod = <?= $js_sa_prod ?>; const data_sa_dt = <?= $js_sa_dt ?>;
    const data_g_pcs = <?= $js_g_pcs ?>; const data_g_prod = <?= $js_g_prod ?>; const data_g_dt = <?= $js_g_dt ?>;

    // 🚀 FONT & TOOLTIP DARK MODE PREMIUM
    const fontTheme = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
    const premiumTooltip = {
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        titleFont: { size: 13, family: fontTheme, weight: 'normal' },
        bodyFont: { size: 14, weight: 'bold', family: fontTheme },
        padding: 12, cornerRadius: 8, displayColors: false, caretPadding: 10
    };
    
    const labelFont = { weight: 'bold', size: 10, family: fontTheme };

    const commonOptions = {
        responsive: true, 
        maintainAspectRatio: false,
        animation: { duration: 1500, easing: 'easeOutQuart' }, // Animasi Batang Halus Tumbuh
        interaction: { mode: 'index', intersect: false }, // Hover sensitif 1 sumbu
        layout: { padding: { top: 40, bottom: 10, left: 10, right: 10 } }, 
        plugins: { 
            legend: { position: 'top', labels: { font: { weight: 'bold', size: 12, family: fontTheme }, color: '#1e293b', usePointStyle: true } },
            tooltip: premiumTooltip
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { weight: '900', size: 12, family: fontTheme }, color: '#64748b' } },
            y: { display: false, beginAtZero: true, grace: '20%' }
        }
    };

    // 🚀 DYNAMIC DATASET UNTUK SEMUA GRAFIK DENGAN EFEK HOVER BORDER & GLOW
    let dsProduksi = [];
    let dsProduktifitas = [];
    let dsDowntime = [];
    let pieLabels = [];
    let pieData = [];
    let pieColors = [];

    if (<?= $tot_i_pcs ?> > 0) {
        dsProduksi.push({ label: 'Inline (PCS)', data: data_i_pcs, backgroundColor: '#0ea5e9', hoverBackgroundColor: '#0f172a', borderColor: 'transparent', hoverBorderColor: '#ffffff', borderWidth: 2, borderRadius: 4, barPercentage: 0.8 });
        dsProduktifitas.push({ label: 'Speed Inline', data: data_i_prod, borderColor: '#0ea5e9', backgroundColor: 'transparent', pointBackgroundColor: '#ffffff', pointBorderColor: '#0ea5e9', pointHoverBackgroundColor: '#0ea5e9', pointHoverBorderColor: '#ffffff', pointHoverRadius: 6, tension: 0.4, borderWidth: 3, datalabels: { align: 'top', offset: 5 }});
        dsDowntime.push({ label: 'DT Inline', data: data_i_dt, backgroundColor: '#0ea5e9', hoverBackgroundColor: '#0f172a', borderColor: 'transparent', hoverBorderColor: '#ffffff', borderWidth: 2, borderRadius: 4 });
        pieLabels.push('Flexo Inline'); pieData.push(<?= $tot_i_pcs ?>); pieColors.push('#0ea5e9');
    }
    
    if (<?= $tot_s_pcs ?> > 0) {
        dsProduksi.push({ label: 'Stacker (PCS)', data: data_s_pcs, backgroundColor: '#16a34a', hoverBackgroundColor: '#0f172a', borderColor: 'transparent', hoverBorderColor: '#ffffff', borderWidth: 2, borderRadius: 4, barPercentage: 0.8 });
        dsProduktifitas.push({ label: 'Speed Stacker', data: data_s_prod, borderColor: '#16a34a', backgroundColor: 'transparent', pointBackgroundColor: '#ffffff', pointBorderColor: '#16a34a', pointHoverBackgroundColor: '#16a34a', pointHoverBorderColor: '#ffffff', pointHoverRadius: 6, tension: 0.4, borderWidth: 3, datalabels: { align: 'bottom', offset: 5 }});
        dsDowntime.push({ label: 'DT Stacker', data: data_s_dt, backgroundColor: '#16a34a', hoverBackgroundColor: '#0f172a', borderColor: 'transparent', hoverBorderColor: '#ffffff', borderWidth: 2, borderRadius: 4 });
        pieLabels.push('Flexo Stacker'); pieData.push(<?= $tot_s_pcs ?>); pieColors.push('#16a34a');
    }

    if (<?= $tot_sa_pcs ?> > 0) {
        dsProduksi.push({ label: 'Stitch Auto (PCS)', data: data_sa_pcs, backgroundColor: '#9333ea', hoverBackgroundColor: '#0f172a', borderColor: 'transparent', hoverBorderColor: '#ffffff', borderWidth: 2, borderRadius: 4, barPercentage: 0.8 });
        dsProduktifitas.push({ label: 'Speed Auto', data: data_sa_prod, borderColor: '#9333ea', backgroundColor: 'transparent', pointBackgroundColor: '#ffffff', pointBorderColor: '#9333ea', pointHoverBackgroundColor: '#9333ea', pointHoverBorderColor: '#ffffff', pointHoverRadius: 6, tension: 0.4, borderWidth: 3, datalabels: { align: 'top', offset: 5 }});
        dsDowntime.push({ label: 'DT Auto', data: data_sa_dt, backgroundColor: '#9333ea', hoverBackgroundColor: '#0f172a', borderColor: 'transparent', hoverBorderColor: '#ffffff', borderWidth: 2, borderRadius: 4 });
        pieLabels.push('Stitch Auto'); pieData.push(<?= $tot_sa_pcs ?>); pieColors.push('#9333ea');
    }

    if (<?= $tot_g_pcs ?> > 0) {
        dsProduksi.push({ label: 'GLUE (PCS)', data: data_g_pcs, backgroundColor: '#e11d48', hoverBackgroundColor: '#0f172a', borderColor: 'transparent', hoverBorderColor: '#ffffff', borderWidth: 2, borderRadius: 4, barPercentage: 0.8 });
        dsProduktifitas.push({ label: 'Speed GLUE', data: data_g_prod, borderColor: '#e11d48', backgroundColor: 'transparent', pointBackgroundColor: '#ffffff', pointBorderColor: '#e11d48', pointHoverBackgroundColor: '#e11d48', pointHoverBorderColor: '#ffffff', pointHoverRadius: 6, tension: 0.4, borderWidth: 3, datalabels: { align: 'bottom', offset: 5 }});
        dsDowntime.push({ label: 'DT GLUE', data: data_g_dt, backgroundColor: '#e11d48', hoverBackgroundColor: '#0f172a', borderColor: 'transparent', hoverBorderColor: '#ffffff', borderWidth: 2, borderRadius: 4 });
        pieLabels.push('Mesin GLUE'); pieData.push(<?= $tot_g_pcs ?>); pieColors.push('#e11d48');
    }

    // 1. CHART PRODUKSI (BATANG)
    new Chart(document.getElementById('chartProduksiPcs').getContext('2d'), {
        type: 'bar',
        data: { labels: labelsTgl, datasets: dsProduksi },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.raw.toLocaleString('id-ID')}` } },
                datalabels: { 
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0,
                    color: '#ffffff', backgroundColor: (ctx) => ctx.dataset.backgroundColor,
                    borderRadius: 4, padding: { top: 3, bottom: 3, left: 5, right: 5 }, font: labelFont,
                    anchor: 'end', align: 'end', offset: 2,
                    formatter: (val) => {
                        if (val >= 1000) return (val / 1000).toFixed(1).replace('.0', '') + 'k';
                        return val;
                    }
                }
            }
        }
    });

    // 2. CHART KECEPATAN (GARIS)
    new Chart(document.getElementById('chartProduktifitas').getContext('2d'), {
        type: 'line',
        data: { labels: labelsTgl, datasets: dsProduktifitas },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.raw} PCS/Mnt` } },
                datalabels: { 
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0, 
                    color: (ctx) => ctx.dataset.borderColor, 
                    backgroundColor: '#ffffff', borderWidth: 1.5, borderColor: (ctx) => ctx.dataset.borderColor, 
                    borderRadius: 6, padding: { top: 3, bottom: 3, left: 6, right: 6 }, font: labelFont, 
                    formatter: (val) => val > 0 ? val : '' 
                }
            }
        }
    });

    // 3. CHART DOWNTIME (BATANG)
    new Chart(document.getElementById('chartDowntime').getContext('2d'), {
        type: 'bar',
        data: { labels: labelsTgl, datasets: dsDowntime },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.raw}%` } },
                datalabels: { 
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0,
                    color: '#ffffff', backgroundColor: (ctx) => ctx.dataset.backgroundColor,
                    borderRadius: 4, padding: { top: 3, bottom: 3, left: 5, right: 5 }, font: labelFont, 
                    anchor: 'end', align: 'end', offset: 2, formatter: (val) => val > 0 ? val + '%' : '' 
                }
            }
        }
    });

    // 4. CHART PROPORSI PIE
    if(pieData.length > 0) {
        new Chart(document.getElementById('chartProporsi').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{ data: pieData, backgroundColor: pieColors, borderWidth: 2, hoverOffset: 8 }] // Hover offset lebih menonjol
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                animation: { duration: 1500, easing: 'easeOutQuart' }, // Animasi memutar mulus
                plugins: {
                    tooltip: premiumTooltip,
                    legend: { position: 'bottom', labels: { font: { weight: 'bold', size: 12, family: fontTheme }, color: '#1e293b', usePointStyle: true } },
                    datalabels: {
                        color: '#ffffff', font: { weight: '900', size: 16, family: fontTheme },
                        formatter: (value, ctx) => {
                            let sum = 0; ctx.chart.data.datasets[0].data.forEach(data => { sum += Number(data); });
                            return sum > 0 ? (value * 100 / sum).toFixed(1) + "%" : "";
                        }
                    }
                }
            }
        });
    }
</script>
<?php endif; ?>
<?php require_once 'footer.php'; ?>