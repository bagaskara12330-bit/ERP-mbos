<?php
require_once 'auth.php';
require_akses('dash_corr');$bulan_ini = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$tahun_ini = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
// 🚀 TANGKAP FILTER SHIFT DARI URL
$shift_ini = isset($_GET['shift']) ? $_GET['shift'] : 'ALL';

$nama_bulan_list = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$nama_bulan = $nama_bulan_list[$bulan_ini];

try {
    // 🚀 LOGIKA PINTAR: INJEKSI QUERY SHIFT
    $where_shift = "";
    $params_harian = [$bulan_ini, $tahun_ini];
    $params_pie = [$bulan_ini, $tahun_ini];

    if ($shift_ini !== 'ALL') {
        $where_shift = " AND shift = ?";
        $params_harian[] = $shift_ini;
        $params_pie[] = $shift_ini;
    }

    // 1. DATA TREN HARIAN
    $stmt = $pdo->prepare("SELECT DAY(tanggal) as tgl_hari, SUM(selisih_menit) as tot_mnt, COUNT(id) as kejadian FROM db_downtime_corr WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?" . $where_shift . " GROUP BY tanggal ORDER BY tanggal ASC");
    $stmt->execute($params_harian);
    $data_harian = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. DATA TOP ALASAN DOWNTIME (PIE CHART)
    $stmt_pie = $pdo->prepare("SELECT keterangan, SUM(selisih_menit) as tot_mnt FROM db_downtime_corr WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?" . $where_shift . " GROUP BY keterangan ORDER BY tot_mnt DESC LIMIT 7");
    $stmt_pie->execute($params_pie);
    $data_pie = $stmt_pie->fetchAll(PDO::FETCH_ASSOC);

    // 3. DATA KOMPARASI VS PER SHIFT (UNTUK GRAFIK BARU)
    $stmt_vs = $pdo->prepare("SELECT tanggal, 
        SUM(CASE WHEN shift = '1' THEN selisih_menit ELSE 0 END) as s1,
        SUM(CASE WHEN shift = '2' THEN selisih_menit ELSE 0 END) as s2,
        SUM(CASE WHEN shift = '3' THEN selisih_menit ELSE 0 END) as s3,
        SUM(selisih_menit) as tot
        FROM db_downtime_corr 
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?" . $where_shift . " 
        GROUP BY tanggal ORDER BY tanggal ASC");
    $stmt_vs->execute($params_harian);
    $data_vs = $stmt_vs->fetchAll(PDO::FETCH_ASSOC);

    // 4. DATA TABEL KUMPULAN JENIS DT
    $stmt_tbl = $pdo->prepare("SELECT keterangan, SUM(selisih_menit) as tot_mnt, COUNT(id) as kejadian FROM db_downtime_corr WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?" . $where_shift . " GROUP BY keterangan ORDER BY tot_mnt DESC");
    $stmt_tbl->execute($params_pie);
    $data_tbl_dt = $stmt_tbl->fetchAll(PDO::FETCH_ASSOC);

    // INISIALISASI VARIABEL
    $tot_semua_mnt = 0; 
    $tot_semua_kejadian = 0;
    
    $labels_tgl = []; 
    $chart_mnt = []; 
    $chart_kejadian = [];

    // Looping Data Harian untuk Grafik Batang & Garis
    foreach($data_harian as $row) {
        $labels_tgl[] = $row['tgl_hari'];
        $chart_mnt[] = $row['tot_mnt'];
        $chart_kejadian[] = $row['kejadian'];

        $tot_semua_mnt += $row['tot_mnt'];
        $tot_semua_kejadian += $row['kejadian'];
    }

    // Looping Data VS Shift
    $vs_s1 = []; $vs_s2 = []; $vs_s3 = [];
    foreach($data_vs as $vs) {
        $vs_s1[] = $vs['s1'];
        $vs_s2[] = $vs['s2'];
        $vs_s3[] = $vs['s3'];
    }

    // Looping Data Alasan untuk Grafik Donat
    $pie_labels = [];
    $pie_data = [];
    foreach($data_pie as $p) {
        $pie_labels[] = strtoupper($p['keterangan']);
        $pie_data[] = $p['tot_mnt'];
    }

    $avg_mnt_per_kejadian = ($tot_semua_kejadian > 0) ? ($tot_semua_mnt / $tot_semua_kejadian) : 0;
    $tot_semua_jam = round($tot_semua_mnt / 60, 2);

} catch (PDOException $e) {
    $tot_semua_mnt = 0; $tot_semua_kejadian = 0; $tot_semua_jam = 0; $avg_mnt_per_kejadian = 0;
    $labels_tgl = []; $chart_mnt = []; $chart_kejadian = [];
    $vs_s1 = []; $vs_s2 = []; $vs_s3 = [];
    $pie_labels = []; $pie_data = []; $data_vs = []; $data_tbl_dt = [];
}

$js_tgl = json_encode($labels_tgl);
$js_mnt = json_encode($chart_mnt);
$js_kejadian = json_encode($chart_kejadian);

$js_vs_s1 = json_encode($vs_s1);
$js_vs_s2 = json_encode($vs_s2);
$js_vs_s3 = json_encode($vs_s3);

$js_pie_labels = json_encode($pie_labels);
$js_pie_data = json_encode($pie_data);

$page_title = "Dashboard Downtime Corr — H2 BASE";
$active_page = "dash_corr"; 
require 'header.php';
?>

<style>
    /* 🚀 ANIMASI ENTRANCE GLOBAL (MUNCUL MELAYANG BERURUTAN) */
    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .header-banner {
        background: #0f172a; border-radius: 10px; padding: 15px 20px; display: flex; justify-content: space-between; 
        align-items: center; flex-wrap: wrap; margin-bottom: 24px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); gap: 15px;
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
    .filter-card select:focus, .filter-card select:hover { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }
    .btn-filter-submit { 
        background: #0f172a; color: white; border: none; padding: 9px 20px; border-radius: 6px; 
        font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; 
    }
    .btn-filter-submit:hover { background: #1e293b; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.3); transform: translateY(-1px); }
    .btn-filter-submit:active { transform: scale(0.95); }

    /* 🚀 WIDGET SUMMARY INTERAKTIF */
    .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    .summary-card { 
        background: white; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; border-top: 5px solid #cbd5e1; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        opacity: 0; 
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    /* Delay animasi berurutan */
    .summary-card:nth-child(1) { animation-delay: 0.1s; }
    .summary-card:nth-child(2) { animation-delay: 0.2s; }
    .summary-card:nth-child(3) { animation-delay: 0.3s; }
    .summary-card:nth-child(4) { animation-delay: 0.4s; }

    .summary-card:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); cursor: pointer; }
    
    .summary-card .title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;}
    .summary-card .value { font-size: 26px; font-weight: 900; color: #0f172a; margin: 4px 0; }
    .summary-card .unit { font-size: 12px; font-weight: 700; color: #94a3b8; margin-left: 4px; }

    /* 🚀 KOTAK GRAFIK MUNCUL MELAYANG BERURUTAN */
    .full-width-card { 
        background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        opacity: 0; animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        animation-delay: 0.5s; transition: all 0.3s ease;
    }
    .full-width-card:hover { box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }
    
    .full-width-card h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 12px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
    .scroll-wrapper { overflow-x: auto; overflow-y: hidden; padding-top: 15px; padding-bottom: 10px; }
    .scroll-wrapper::-webkit-scrollbar { height: 8px; }
    .scroll-wrapper::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
    .scroll-wrapper::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .scroll-wrapper::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    
    .canvas-scroll-inner { position: relative; min-width: 900px; height: 350px; } 

    .half-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; align-items: stretch; }
    
    .chart-card { 
        background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; flex-direction: column; min-height: 380px; 
        opacity: 0; animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; transition: all 0.3s ease;
    }
    .half-grid .chart-card:nth-child(1) { animation-delay: 0.6s; }
    .half-grid .chart-card:nth-child(2) { animation-delay: 0.7s; }
    .chart-card:hover { box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }

    .chart-card h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 16px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
    .canvas-wrapper { position: relative; flex-grow: 1; height: 100%; min-height: 300px; width: 100%; display: flex; justify-content: center; align-items: center;}

    @media (max-width: 992px) { .half-grid { grid-template-columns: 1fr; } .summary-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 768px) { .summary-grid { grid-template-columns: 1fr; } }
</style>

<div class="header-banner">
    <h3 style="color: #ffffff; margin: 0; font-size: 15px; display: flex; align-items: center; gap: 8px;">
        🛑 EXECUTIVE DASHBOARD: DOWNTIME CORRUGATOR
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
        <select name="shift" style="border-color: #0ea5e9; color: #0369a1;">
            <option value="ALL" <?= $shift_ini == 'ALL' ? 'selected' : '' ?>>Semua Shift</option>
            <option value="1" <?= $shift_ini == '1' ? 'selected' : '' ?>>Shift 1</option>
            <option value="2" <?= $shift_ini == '2' ? 'selected' : '' ?>>Shift 2</option>
            <option value="3" <?= $shift_ini == '3' ? 'selected' : '' ?>>Shift 3</option>
        </select>
    </div>
    <button type="submit" class="btn-filter-submit">🔍 Analisis Data</button>
</form>

<?php if ($tot_semua_mnt == 0 && empty($data_harian)): ?>
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 50px; text-align: center; color: #166534; margin-bottom: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); animation: slideUpFade 0.5s ease-out forwards;">
        <div style="font-size: 50px; margin-bottom: 15px;">🎉</div>
        <h3 style="margin: 0 0 10px 0; font-size: 22px; font-weight: 900;">Luar Biasa! Mesin Lancar Jaya!</h3>
        <p style="margin: 0; font-size: 15px; font-weight: 600;">Tidak ada catatan mesin mati / downtime pada bulan <?= $nama_bulan ?> <?= $tahun_ini ?> <?= $shift_ini != 'ALL' ? '(Khusus Shift '.$shift_ini.')' : '' ?>.</p>
    </div>
<?php else: ?>

<div class="summary-grid">
    <div class="summary-card" style="border-top-color: #e11d48; background: #fff1f2;">
        <div class="title" style="color: #be123c;">Total Waktu Mati</div>
        <div class="value" style="color: #9f1239;"><?= number_format($tot_semua_mnt, 0, ',', '.') ?> <span class="unit" style="color: #fb7185;">Menit</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #f59e0b; background: #fffbeb;">
        <div class="title" style="color: #d97706;">Konversi Ke Jam</div>
        <div class="value" style="color: #b45309;"><?= number_format($tot_semua_jam, 2, ',', '.') ?> <span class="unit" style="color: #fbbf24;">Jam</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #0ea5e9; background: #f0f9ff;">
        <div class="title" style="color: #0284c7;">Total Kejadian / Insiden</div>
        <div class="value" style="color: #0369a1;"><?= number_format($tot_semua_kejadian, 0, ',', '.') ?> <span class="unit" style="color: #38bdf8;">Kali</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #8b5cf6; background: #faf5ff;">
        <div class="title" style="color: #7e22ce;">Rata-rata Waktu Perbaikan</div>
        <div class="value" style="color: #6d28d9;"><?= number_format($avg_mnt_per_kejadian, 1, ',', '.') ?> <span class="unit" style="color: #c084fc;">Mnt / Insiden</span></div>
    </div>
</div>

<div class="full-width-card">
    <h3 style="color: #be123c;">📉 Tren Durasi Downtime Harian (Menit) <?= $shift_ini != 'ALL' ? '- SHIFT '.$shift_ini : '' ?></h3>
    <div class="scroll-wrapper">
        <div class="canvas-scroll-inner">
            <canvas id="chartDurasiHarian"></canvas>
        </div>
    </div>

    <?php if (count($data_vs) > 0): ?>
    <div style="margin-top: 40px; border-top: 2px dashed #e2e8f0; padding-top: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="color: #0f172a; margin: 0; border: none; padding: 0;">📊 Komparasi Downtime VS Shift (Menit)</h3>
        </div>
        <div class="scroll-wrapper" style="overflow-x: auto; padding-bottom:10px;">
            <div class="canvas-scroll-inner" style="min-width: 900px; height: 350px;">
                <canvas id="chartVsShift"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="half-grid">
    <div class="chart-card" style="margin-bottom: 0; border-top: 4px solid #0369a1;">
        <div class="table-header-flex">
            <h3 style="margin-bottom: 0; color: #0369a1; border-bottom: none; padding-bottom: 0;">📈 Tren Frekuensi Kejadian <?= $shift_ini != 'ALL' ? '(SHIFT '.$shift_ini.')' : '' ?></h3>
        </div>
        <div class="scroll-wrapper" style="overflow-x: auto; padding-bottom:10px;">
            <div class="canvas-scroll-inner" style="min-width: 500px; height: 300px;">
                <canvas id="chartFrekuensi"></canvas>
            </div>
        </div>
    </div>
    
    <div class="chart-card" style="margin-bottom: 0; border-top: 4px solid #6d28d9;">
        <div class="table-header-flex">
            <h3 style="margin-bottom: 0; color: #6d28d9; border-bottom: none; padding-bottom: 0;">⚠️ Top 7 Alasan Downtime <?= $shift_ini != 'ALL' ? '- SHIFT '.$shift_ini : '' ?></h3>
        </div>
        <div class="canvas-wrapper">
            <canvas id="chartAlasan"></canvas>
        </div>
    </div>
</div>

<div class="full-width-card" style="margin-top: 24px; border-top: 4px solid #f59e0b;">
    <h3 style="color: #b45309;">📋 Rincian Kumpulan Jenis Downtime <?= $shift_ini != 'ALL' ? '- SHIFT '.$shift_ini : '' ?></h3>
    <div class="scroll-wrapper" style="overflow-y: auto; max-height: 400px; padding-top: 0;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead style="position: sticky; top: 0; background: #b45309; z-index: 10;">
                <tr>
                    <th style="padding: 12px; border-bottom: 2px solid #92400e; text-align: center; color: #ffffff; text-transform: uppercase;">No</th>
                    <th style="padding: 12px; border-bottom: 2px solid #92400e; text-align: center; color: #ffffff; text-transform: uppercase;">Keterangan Downtime</th>
                    <th style="padding: 12px; border-bottom: 2px solid #92400e; text-align: center; color: #ffffff; text-transform: uppercase;">Total Waktu (Menit)</th>
                    <th style="padding: 12px; border-bottom: 2px solid #92400e; text-align: center; color: #ffffff; text-transform: uppercase;">Frekuensi (Kali)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (isset($data_tbl_dt) && count($data_tbl_dt) > 0): ?>
                    <?php $no = 1; foreach ($data_tbl_dt as $row): ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 12px; text-align: center; color: #64748b; font-weight: bold;"><?= $no++ ?></td>
                            <td style="padding: 12px; text-align: center; font-weight: 700; color: #0f172a;"><?= strtoupper($row['keterangan']) ?></td>
                            <td style="padding: 12px; text-align: center; font-weight: 800; color: #be123c; background-color: #fff1f2;"><?= number_format($row['tot_mnt'], 0, ',', '.') ?> Mnt</td>
                            <td style="padding: 12px; text-align: center; font-weight: 800; color: #0369a1; background-color: #f0f9ff;"><?= number_format($row['kejadian'], 0, ',', '.') ?> Kali</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="padding: 20px; text-align: center; color: #94a3b8; font-style: italic;">Tidak ada data downtime.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    Chart.register(ChartDataLabels);

    const labelsTgl = <?= $js_tgl ?>;
    const data_mnt = <?= $js_mnt ?>; 
    const data_kejadian = <?= $js_kejadian ?>;
    
    const vs_s1 = <?= $js_vs_s1 ?? '[]' ?>;
    const vs_s2 = <?= $js_vs_s2 ?? '[]' ?>;
    const vs_s3 = <?= $js_vs_s3 ?? '[]' ?>;

    const pie_labels = <?= $js_pie_labels ?>;
    const pie_data = <?= $js_pie_data ?>;

    // 🚀 FONT & TOOLTIP DARK MODE PREMIUM
    const fontTheme = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';
    const premiumTooltip = {
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        titleFont: { size: 13, family: fontTheme, weight: 'normal' },
        bodyFont: { size: 14, weight: 'bold', family: fontTheme },
        padding: 12, cornerRadius: 8, displayColors: false, caretPadding: 10
    };

    const labelFont = { weight: 'bold', size: 11, family: fontTheme };

    const commonOptions = {
        responsive: true, 
        maintainAspectRatio: false,
        animation: { duration: 1500, easing: 'easeOutQuart' }, // Animasi Tumbuh Mulus
        interaction: { mode: 'index', intersect: false }, // Sensitif 1 Sumbu
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

    // 1. CHART DURASI (BATANG MERAH)
    new Chart(document.getElementById('chartDurasiHarian').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labelsTgl,
            datasets: [{ 
                label: 'Total Menit', 
                data: data_mnt, 
                backgroundColor: '#e11d48',
                hoverBackgroundColor: '#881337', // Gelap saat dihover
                borderColor: 'transparent',
                hoverBorderColor: '#ffffff', // Glow Border
                borderWidth: 2,
                borderRadius: 6, 
                barPercentage: 0.6 
            }]
        },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: { ...premiumTooltip, callbacks: { label: function(context) { return context.raw + ' Menit'; } } },
                datalabels: { 
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0,
                    color: '#ffffff', backgroundColor: '#e11d48',
                    borderRadius: 4, padding: { top: 4, bottom: 4, left: 6, right: 6 }, font: labelFont,
                    anchor: 'end', align: 'end', offset: 4,
                    formatter: (val) => val + ' mnt'
                }
            }
        }
    });

    // 🚀 1.5 CHART VS SHIFT (GROUPED BAR CHART)
    if (document.getElementById('chartVsShift')) {
        new Chart(document.getElementById('chartVsShift').getContext('2d'), {
            type: 'bar',
            data: {
                labels: labelsTgl,
                datasets: [
                    { label: 'Shift 1', data: vs_s1, backgroundColor: '#0ea5e9', hoverBackgroundColor: '#0f172a', borderColor:'transparent', hoverBorderColor:'#fff', borderWidth:2, borderRadius: 4, barPercentage: 0.8, categoryPercentage: 0.8 },
                    { label: 'Shift 2', data: vs_s2, backgroundColor: '#f59e0b', hoverBackgroundColor: '#0f172a', borderColor:'transparent', hoverBorderColor:'#fff', borderWidth:2, borderRadius: 4, barPercentage: 0.8, categoryPercentage: 0.8 },
                    { label: 'Shift 3', data: vs_s3, backgroundColor: '#8b5cf6', hoverBackgroundColor: '#0f172a', borderColor:'transparent', hoverBorderColor:'#fff', borderWidth:2, borderRadius: 4, barPercentage: 0.8, categoryPercentage: 0.8 }
                ]
            },
            options: {
                ...commonOptions,
                layout: { padding: { top: 25, bottom: 10, left: 10, right: 10 } },
                plugins: {
                    ...commonOptions.plugins,
                    legend: { 
                        display: true, 
                        position: 'top', 
                        labels: { font: { weight: 'bold', size: 12, family: fontTheme }, color: '#1e293b', usePointStyle: true, boxWidth: 10 } 
                    },
                    tooltip: { ...premiumTooltip, callbacks: { label: function(context) { return context.dataset.label + ': ' + context.raw + ' Mnt'; } } },
                    datalabels: { 
                        display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0,
                        color: '#475569',
                        font: { weight: '800', size: 10, family: fontTheme },
                        anchor: 'end', align: 'end', offset: 2,
                        formatter: (val) => val
                    }
                }
            }
        });
    }

    // 2. CHART FREKUENSI KEJADIAN (GARIS BIRU)
    new Chart(document.getElementById('chartFrekuensi').getContext('2d'), {
        type: 'line',
        data: {
            labels: labelsTgl,
            datasets: [{ 
                label: 'Jumlah Kejadian', 
                data: data_kejadian, 
                borderColor: '#0ea5e9', 
                backgroundColor: 'rgba(14, 165, 233, 0.1)', 
                fill: true,
                tension: 0.4, // Garis lebih melengkung halus
                borderWidth: 4, 
                pointRadius: 6, 
                pointBackgroundColor: '#ffffff',
                pointBorderWidth: 3,
                pointBorderColor: '#0ea5e9',
                pointHoverBackgroundColor: '#0ea5e9', // Menyala saat dihover
                pointHoverBorderColor: '#ffffff',
                pointHoverRadius: 8
            }]
        },
        options: {
            ...commonOptions,
            scales: {
                x: { grid: { display: false }, ticks: { font: { weight: '900', size: 12, family: fontTheme }, color: '#64748b' } },
                y: { display: false, beginAtZero: true, grace: '30%', ticks: { stepSize: 1 } }
            },
            plugins: {
                ...commonOptions.plugins,
                tooltip: { ...premiumTooltip, callbacks: { label: function(context) { return context.raw + ' Kali'; } } },
                datalabels: { 
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0, 
                    color: '#0369a1', 
                    backgroundColor: '#e0f2fe', 
                    borderWidth: 1.5, borderColor: '#0ea5e9', 
                    borderRadius: 20, padding: { top: 4, bottom: 4, left: 8, right: 8 }, 
                    font: { weight: '900', size: 12, family: fontTheme }, 
                    anchor: 'end', align: 'top', offset: 8,
                    formatter: (val) => val + 'x' 
                }
            }
        }
    });

    // 3. CHART TOP ALASAN (DOUGHNUT / PIE)
    if(pie_data.length > 0) {
        new Chart(document.getElementById('chartAlasan').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: pie_labels,
                datasets: [{ 
                    data: pie_data, 
                    backgroundColor: ['#e11d48', '#f59e0b', '#10b981', '#0ea5e9', '#8b5cf6', '#64748b', '#f43f5e'], 
                    borderWidth: 3, 
                    hoverOffset: 8 // Melonjak keluar saat dihover
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                animation: { duration: 1500, easing: 'easeOutQuart' }, // Berputar mulus
                layout: { padding: 20 },
                plugins: {
                    legend: { 
                        position: 'right', 
                        labels: { font: { weight: 'bold', size: 11, family: fontTheme }, color: '#1e293b', usePointStyle: true, boxWidth: 8 } 
                    },
                    tooltip: { ...premiumTooltip, callbacks: { label: function(context) { return ' ' + context.raw + ' Menit'; } } },
                    datalabels: {
                        color: '#ffffff', font: { weight: '900', size: 14, family: fontTheme },
                        formatter: (value, ctx) => {
                            let sum = 0; ctx.chart.data.datasets[0].data.forEach(data => { sum += Number(data); });
                            let percentage = (value * 100 / sum).toFixed(1) + "%";
                            return (value * 100 / sum) > 4 ? percentage : "";
                        }
                    }
                }
            }
        });
    }
</script>
<?php endif; ?>
<?php require_once 'footer.php'; ?>