<?php
require_once 'auth.php';
require_akses('qc_dash');$bulan_ini = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$tahun_ini = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$nama_bulan_list = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$nama_bulan = $nama_bulan_list[$bulan_ini];

try {
    // 1. DATA SUMMARY GLOBAL - INCOMING QC
    $stmt_inc_sum = $pdo->prepare("
        SELECT 
            COUNT(id) as total_masuk,
            SUM(CASE WHEN status != 'PASS' THEN 1 ELSE 0 END) as total_bermasalah,
            SUM(CASE WHEN status = 'PASS' THEN 1 ELSE 0 END) as total_pass,
            SUM(CASE WHEN status = 'HOLD' THEN 1 ELSE 0 END) as total_hold,
            SUM(CASE WHEN status = 'REJECT' THEN 1 ELSE 0 END) as total_reject
        FROM db_qc_incoming 
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?
    ");
    $stmt_inc_sum->execute([$bulan_ini, $tahun_ini]);
    $inc_sum = $stmt_inc_sum->fetch(PDO::FETCH_ASSOC);

    // 2. DATA SUMMARY GLOBAL - QC LAPORAN (PELANGGAN)
    $stmt_lap_sum = $pdo->prepare("
        SELECT 
            COUNT(id) as total_keluhan,
            SUM(jumlah_reject) as total_pcs_reject
        FROM db_qc_laporan 
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?
    ");
    $stmt_lap_sum->execute([$bulan_ini, $tahun_ini]);
    $lap_sum = $stmt_lap_sum->fetch(PDO::FETCH_ASSOC);

    // 3. TREN HARIAN INCOMING QC
    $stmt_inc_harian = $pdo->prepare("
        SELECT 
            DAY(tanggal) as tgl_hari, 
            SUM(CASE WHEN status = 'PASS' THEN 1 ELSE 0 END) as pass_harian,
            SUM(CASE WHEN status != 'PASS' THEN 1 ELSE 0 END) as fail_harian
        FROM db_qc_incoming 
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? 
        GROUP BY tanggal ORDER BY tanggal ASC
    ");
    $stmt_inc_harian->execute([$bulan_ini, $tahun_ini]);
    $data_inc_harian = $stmt_inc_harian->fetchAll(PDO::FETCH_ASSOC);

    $lbl_inc_harian = [];
    $data_pass = [];
    $data_fail = [];
    foreach($data_inc_harian as $row) {
        $lbl_inc_harian[] = $row['tgl_hari'];
        $data_pass[] = $row['pass_harian'];
        $data_fail[] = $row['fail_harian'];
    }

    // 4. TREN HARIAN QC LAPORAN (REJECT PCS)
    $stmt_lap_harian = $pdo->prepare("
        SELECT DAY(tanggal) as tgl_hari, SUM(jumlah_reject) as pcs_harian
        FROM db_qc_laporan 
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? 
        GROUP BY tanggal ORDER BY tanggal ASC
    ");
    $stmt_lap_harian->execute([$bulan_ini, $tahun_ini]);
    $data_lap_harian = $stmt_lap_harian->fetchAll(PDO::FETCH_ASSOC);

    $lbl_lap_harian = [];
    $data_pcs_reject = [];
    foreach($data_lap_harian as $row) {
        $lbl_lap_harian[] = $row['tgl_hari'];
        $data_pcs_reject[] = $row['pcs_harian'];
    }

    // 5. TOP 5 JENIS REJECT PELANGGAN (Berdasarkan Pcs)
    $stmt_top_reject = $pdo->prepare("
        SELECT jenis_reject, SUM(jumlah_reject) as total 
        FROM db_qc_laporan 
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? 
        GROUP BY jenis_reject ORDER BY total DESC LIMIT 5
    ");
    $stmt_top_reject->execute([$bulan_ini, $tahun_ini]);
    $data_top_reject = $stmt_top_reject->fetchAll(PDO::FETCH_ASSOC);

    $lbl_top_reject = [];
    $val_top_reject = [];
    foreach($data_top_reject as $row) {
        $lbl_top_reject[] = $row['jenis_reject'];
        $val_top_reject[] = $row['total'];
    }

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

// Convert variables to JSON for Chart.js
$js_lbl_inc = json_encode($lbl_inc_harian);
$js_data_pass = json_encode($data_pass);
$js_data_fail = json_encode($data_fail);

$js_lbl_lap = json_encode($lbl_lap_harian);
$js_data_pcs = json_encode($data_pcs_reject);

$js_lbl_top = json_encode($lbl_top_reject);
$js_val_top = json_encode($val_top_reject);

$js_inc_donut = json_encode([$inc_sum['total_pass'] ?? 0, $inc_sum['total_hold'] ?? 0, $inc_sum['total_reject'] ?? 0]);

$page_title = "Dashboard QC — H2 BASE";
$active_page = "qc_dashboard"; 
require 'header.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* Styling mengikuti tema premium aplikasi (menyamai dashboard_produksi_nc.php) */
    .filter-card { background: white; padding: 15px 20px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 24px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
    .filter-card label { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; }
    .filter-card select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; background: #f8fafc; outline: none; font-weight: 600;}
    
    .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
    .summary-card { background: white; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; border-top: 5px solid #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); }
    .summary-card:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); cursor: pointer; }
    .summary-card .title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;}
    .summary-card .value { font-size: 26px; font-weight: 900; color: #0f172a; margin: 4px 0; }
    .summary-card .unit { font-size: 12px; font-weight: 700; color: #94a3b8; margin-left: 4px; }

    .half-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; align-items: stretch; }
    .chart-card { background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; flex-direction: column; min-height: 380px; }
    .chart-card h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 16px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
    .canvas-wrapper { position: relative; flex-grow: 1; height: 100%; min-height: 300px; width: 100%; display: flex; justify-content: center; align-items: center;}

    @media (max-width: 992px) { .half-grid { grid-template-columns: 1fr; } .summary-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 768px) { .summary-grid { grid-template-columns: 1fr; } }
</style>

<div style="background: #3b82f6; border-radius: 10px; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 24px; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2); gap: 15px;">
    <h3 style="color: #ffffff; margin: 0; font-size: 15px; display: flex; align-items: center; gap: 8px;">
        📊 EXECUTIVE DASHBOARD: QUALITY CONTROL
    </h3>
    <form method="GET" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 0;">
        <select name="bulan" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: none; outline: none; font-weight: 700; color: #1e293b; background: white;">
            <?php foreach ($nama_bulan_list as $m_code => $m_name): ?>
                <option value="<?= $m_code ?>" <?= $m_code == $bulan_ini ? 'selected' : '' ?>><?= $m_name ?></option>
            <?php endforeach; ?>
        </select>
        <select name="tahun" onchange="this.form.submit()" style="padding: 6px 12px; border-radius: 6px; border: none; outline: none; font-weight: 700; color: #1e293b; background: white;">
            <?php for($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $y == $tahun_ini ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </form>
</div>

<?php 
// Periksa apakah ada data di bulan ini
if ($inc_sum['total_masuk'] == 0 && $lap_sum['total_keluhan'] == 0): 
?>
    <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 50px; text-align: center; color: #64748b; margin-bottom: 24px;">
        <div style="font-size: 40px; margin-bottom: 15px;">📭</div>
        <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 800;">Data QC Kosong</h3>
        <p style="margin: 0; font-size: 14px; font-weight: 600;">Belum ada laporan Quality Control yang direkam pada bulan <?= $nama_bulan ?> <?= $tahun_ini ?>.</p>
    </div>
<?php else: ?>

<!-- 🚀 WIDGET SUMMARY KUSTOM -->
<div class="summary-grid">
    <div class="summary-card" style="border-top-color: #0ea5e9; background: #e0f2fe;">
        <div class="title" style="color: #0284c7;">Total Roll Masuk (Inc)</div>
        <div class="value" style="color: #0369a1;"><?= number_format($inc_sum['total_masuk'] ?? 0, 0, ',', '.') ?> <span class="unit" style="color: #38bdf8;">ROLL</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #f43f5e; background: #fff1f2;">
        <div class="title" style="color: #be123c;">Roll Bermasalah (Inc)</div>
        <div class="value" style="color: #9f1239;"><?= number_format($inc_sum['total_bermasalah'] ?? 0, 0, ',', '.') ?> <span class="unit" style="color: #fb7185;">ROLL</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #f59e0b; background: #fef3c7;">
        <div class="title" style="color: #b45309;">Keluhan Pelanggan (Lap)</div>
        <div class="value" style="color: #92400e;"><?= number_format($lap_sum['total_keluhan'] ?? 0, 0, ',', '.') ?> <span class="unit" style="color: #fbbf24;">TIKET</span></div>
    </div>
    <div class="summary-card" style="border-top-color: #8b5cf6; background: #f3e8ff;">
        <div class="title" style="color: #6d28d9;">Total Reject (Lap)</div>
        <div class="value" style="color: #5b21b6;"><?= number_format($lap_sum['total_pcs_reject'] ?? 0, 0, ',', '.') ?> <span class="unit" style="color: #a78bfa;">PCS</span></div>
    </div>
</div>

<div class="half-grid">
    <div class="chart-card">
        <h3 style="color: #0369a1;">📉 Tren Harian Incoming QC</h3>
        <div class="canvas-wrapper">
            <canvas id="incHarianChart"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <h3 style="color: #92400e;">📊 Tren Harian Reject Pelanggan</h3>
        <div class="canvas-wrapper">
            <canvas id="lapHarianChart"></canvas>
        </div>
    </div>
</div>

<div class="half-grid">
    <div class="chart-card">
        <h3 style="color: #15803d;">🍩 Proporsi Status Incoming QC</h3>
        <div class="canvas-wrapper">
            <canvas id="incProporsiChart"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <h3 style="color: #6d28d9;">🏆 Top 5 Masalah Pelanggan (Reject Pcs)</h3>
        <div class="canvas-wrapper">
            <canvas id="lapTopRejectChart"></canvas>
        </div>
    </div>
</div>

<script>
    Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
    Chart.defaults.color = '#64748b';
    Chart.defaults.font.size = 12;
    Chart.defaults.font.weight = '600';

    // 1. Chart Tren Harian Incoming QC (Bar Stacked / Grouped)
    new Chart(document.getElementById('incHarianChart'), {
        type: 'bar',
        data: {
            labels: <?= $js_lbl_inc ?>,
            datasets: [
                {
                    label: 'Roll PASS',
                    data: <?= $js_data_pass ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderRadius: 4
                },
                {
                    label: 'Roll HOLD/REJECT',
                    data: <?= $js_data_fail ?>,
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, border: { dash: [4, 4] }, grid: { color: '#f1f5f9' }, beginAtZero: true }
            },
            plugins: { tooltip: { mode: 'index', intersect: false } }
        }
    });

    // 2. Chart Tren Harian Laporan QC (Line Chart)
    new Chart(document.getElementById('lapHarianChart'), {
        type: 'line',
        data: {
            labels: <?= $js_lbl_lap ?>,
            datasets: [{
                label: 'Total Pcs Reject',
                data: <?= $js_data_pcs ?>,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#f59e0b',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { grid: { display: false } },
                y: { border: { dash: [4, 4] }, grid: { color: '#f1f5f9' }, beginAtZero: true }
            },
            plugins: {
                tooltip: { callbacks: { label: function(c) { return c.parsed.y.toLocaleString('id-ID') + ' Pcs'; } } }
            }
        }
    });

    // 3. Chart Proporsi Status Incoming (Doughnut)
    new Chart(document.getElementById('incProporsiChart'), {
        type: 'doughnut',
        data: {
            labels: ['PASS', 'HOLD', 'REJECT'],
            datasets: [{
                data: <?= $js_inc_donut ?>,
                backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true } }
            }
        }
    });

    // 4. Chart Top 5 Jenis Reject (Doughnut)
    new Chart(document.getElementById('lapTopRejectChart'), {
        type: 'doughnut',
        data: {
            labels: <?= $js_lbl_top ?>,
            datasets: [{
                data: <?= $js_val_top ?>,
                backgroundColor: ['#8b5cf6', '#3b82f6', '#06b6d4', '#10b981', '#f43f5e'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'right', labels: { padding: 15, usePointStyle: true } },
                tooltip: { callbacks: { label: function(c) { return ' ' + c.parsed.toLocaleString('id-ID') + ' Pcs'; } } }
            }
        }
    });
</script>

<?php endif; ?>
<?php require_once 'footer.php'; ?>
