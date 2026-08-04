<?php
require_once 'auth.php';
require_akses('dash_mtc');$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';

$bulan_ini = date('m');
$tahun_ini = date('Y');

$nama_bulan_list = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$nama_bulan = $nama_bulan_list[$bulan_ini];

try {
    // 1. KOTAK WIDGET ATAS
    $tot_item = $pdo->query("SELECT COUNT(*) FROM db_mtc_sparepart")->fetchColumn() ?: 0;
    $stok_kritis = $pdo->query("SELECT COUNT(*) FROM db_mtc_sparepart WHERE qty_stok <= limit_stok")->fetchColumn() ?: 0;
    $pakai_bln_ini = $pdo->query("SELECT SUM(qty) FROM db_mtc_history WHERE jenis = 'KELUAR' AND MONTH(tanggal) = '$bulan_ini' AND YEAR(tanggal) = '$tahun_ini'")->fetchColumn() ?: 0;
    $masuk_bln_ini = $pdo->query("SELECT SUM(qty) FROM db_mtc_history WHERE jenis = 'MASUK' AND MONTH(tanggal) = '$bulan_ini' AND YEAR(tanggal) = '$tahun_ini'")->fetchColumn() ?: 0;

    // 2. DATA GRAFIK BAR: TREN PEMAKAIAN HARIAN (BULAN INI)
    $stmt_trend = $pdo->query("SELECT DAY(tanggal) as tgl, SUM(qty) as total FROM db_mtc_history WHERE jenis = 'KELUAR' AND MONTH(tanggal) = '$bulan_ini' AND YEAR(tanggal) = '$tahun_ini' GROUP BY tanggal ORDER BY tanggal ASC");
    $data_trend = $stmt_trend->fetchAll(PDO::FETCH_ASSOC);
    
    $label_harian = []; $val_harian = [];
    // Bikin array 1-31 hari (kosongkan dulu)
    for($i=1; $i<=31; $i++) { $label_harian[] = $i; $val_harian[$i] = 0; }
    // Isi dengan data riil
    foreach($data_trend as $row) { $val_harian[$row['tgl']] = floatval($row['total']); }
    $val_harian_final = array_values($val_harian);

    // 3. DATA GRAFIK PIE: TOP 5 SPAREPART PALING BANYAK DIPAKAI
    $stmt_top = $pdo->query("SELECT s.nama_part, SUM(h.qty) as total FROM db_mtc_history h JOIN db_mtc_sparepart s ON h.id_part = s.id WHERE h.jenis = 'KELUAR' GROUP BY h.id_part ORDER BY total DESC LIMIT 5");
    $data_top = $stmt_top->fetchAll(PDO::FETCH_ASSOC);
    
    $label_top = []; $val_top = [];
    foreach($data_top as $row) {
        $label_top[] = $row['nama_part'];
        $val_top[] = floatval($row['total']);
    }

    // 4. TABEL ALERT: BARANG KRITIS
    $stmt_kritis = $pdo->query("SELECT kode_part, nama_part, qty_stok, limit_stok, satuan FROM db_mtc_sparepart WHERE qty_stok <= limit_stok ORDER BY qty_stok ASC LIMIT 10");
    $tabel_kritis = $stmt_kritis->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Jika tabel kosong atau belum ada, set nilai 0
    $tot_item = 0; $stok_kritis = 0; $pakai_bln_ini = 0; $masuk_bln_ini = 0;
    $label_harian = []; $val_harian_final = []; $label_top = []; $val_top = []; $tabel_kritis = [];
}

$page_title = "Dashboard Analitik MTC — H2 BASE ERP";
$active_page = "mtc_dashboard";
require 'header.php';
?>

<style>
    /* 🚀 ANIMASI ENTRANCE GLOBAL (MUNCUL MELAYANG) */
    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(40px); }
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

    /* 🚀 CSS WIDGET DASHBOARD PREMIUM INTERAKTIF */
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px; margin-bottom: 24px; }
    
    .widget-box { 
        background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); 
        display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; border: 1px solid #e2e8f0; 
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        opacity: 0; 
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    /* Delay Staggered Entrance Widget */
    .widget-box:nth-child(1) { animation-delay: 0.1s; }
    .widget-box:nth-child(2) { animation-delay: 0.2s; }
    .widget-box:nth-child(3) { animation-delay: 0.3s; }
    .widget-box:nth-child(4) { animation-delay: 0.4s; }

    /* Hover Effect WIDGET */
    .widget-box:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); cursor: pointer; }
    
    .widget-box.blue { border-top: 5px solid #3b82f6; background: #eff6ff; }
    .widget-box.red { border-top: 5px solid #ef4444; background: #fef2f2; }
    .widget-box.orange { border-top: 5px solid #f59e0b; background: #fffbeb; }
    .widget-box.green { border-top: 5px solid #10b981; background: #f0fdf4; }
    
    .widget-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
    .widget-value { font-size: 32px; font-weight: 900; color: #0f172a; margin-bottom: 4px; display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; line-height: 1;}
    .widget-unit { font-size: 13px; font-weight: 700; color: #94a3b8; }
    .widget-icon { position: absolute; right: -15px; bottom: -20px; font-size: 90px; opacity: 0.05; }

    /* 🚀 CSS CHART CARDS INTERAKTIF */
    .half-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; align-items: stretch; }
    
    .chart-card { 
        background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); 
        border: 1px solid #e2e8f0; margin-bottom: 24px; display: flex; flex-direction: column;
        transition: all 0.3s ease;
        opacity: 0;
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    /* Delay untuk kotak Grafik dan Tabel */
    .half-grid .chart-card:nth-child(1) { animation-delay: 0.5s; }
    .half-grid .chart-card:nth-child(2) { animation-delay: 0.6s; }
    #alert-table-card { animation-delay: 0.7s; }

    .chart-card:hover { box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }

    .chart-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;}
    .chart-header h3 { margin: 0; font-size: 15px; color: #1e293b; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;}
    .canvas-wrapper { position: relative; flex-grow: 1; height: 100%; min-height: 300px; width: 100%; }

    /* 🚀 PREMIUM TABLE ALERT STYLING */
    .table-wrapper { width: 100%; overflow-x: auto; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.01);}
    .table-premium { width: 100%; border-collapse: collapse; font-size: 13px; color: #334155; white-space: nowrap; }
    .table-premium th, .table-premium td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9; }
    .table-premium th { background-color: #0f172a; color: #ffffff; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-bottom: 2px solid #1e293b;}
    .table-premium tbody tr:nth-child(even) td { background-color: #f8fafc; } 
    .table-premium tbody tr:hover td { background-color: #f1f5f9 !important; }

    @media (max-width: 992px) { .half-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) {
        .filter-box { flex-direction: column; align-items: stretch; width: 100%; }
    }

    /* ?? PREMIUM BUTTONS */
    .btn-submit-modern {
        background: #10b981 !important; color: #ffffff !important; border: none !important; padding: 12px 28px !important; border-radius: 8px !important; font-size: 14px !important; font-weight: 800 !important; cursor: pointer !important; transition: all 0.2s ease-in-out !important; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.1) !important; display: inline-flex !important; align-items: center !important; justify-content: center !important;
    }
    .btn-submit-modern:hover { background: #059669 !important; transform: translateY(-1px) !important; box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2) !important; }

    .btn-search-modern {
        background: #0f172a !important; color: #ffffff !important; border: none !important; padding: 10px 20px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; cursor: pointer !important; transition: all 0.2s ease-in-out !important; height: 41px !important; box-sizing: border-box !important; display: inline-flex !important; align-items: center !important;
    }
    .btn-search-modern:hover { background: #1e293b !important; }

    .btn-batal-modern {
        background: #ffffff !important; color: #475569 !important; border: 1px solid #cbd5e1 !important; padding: 11px 24px !important; text-decoration: none !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; text-align: center !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; transition: all 0.2s !important;
    }
    .btn-batal-modern:hover { background: #f1f5f9 !important; color: #0f172a !important; }
    
    .btn-excel-modern {
        background: #16a34a !important; color: #ffffff !important; border: none !important; padding: 10px 20px !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 700 !important; cursor: pointer !important; transition: all 0.2s ease-in-out !important; height: 41px !important; box-sizing: border-box !important; display: inline-flex !important; align-items: center !important;
    }
    .btn-excel-modern:hover { background: #15803d !important; }
</style>

<div class="card animated-card" style="border-top: 5px solid #0ea5e9; background: #ffffff; border-radius: 12px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 24px;">
    <div class="filter-box" style="justify-content:space-between; align-items:center; margin-bottom: 0;">
        <div>
            <h2 style="margin:0; border:none; padding:0; font-size: 18px; color: #0f172a;">🛠️ DASHBOARD ANALITIK MTC</h2>
            <div style="font-size: 13px; color: #64748b; font-weight: 600; margin-top: 6px;">Pemantauan Real-time Pemakaian Sparepart & Limit Stok</div>
        </div>
        
        <div style="display: flex; gap: 12px; align-items: center; background: #f8fafc; padding: 10px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
            <span style="font-size: 12px; font-weight: 800; color: #475569; text-transform: uppercase;">Menampilkan Data:</span>
            <span style="font-size: 14px; font-weight: 900; color: #0ea5e9;"><?= $nama_bulan ?> <?= $tahun_ini ?></span>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="widget-box blue">
        <div class="widget-title">Total Master Item</div>
        <div class="widget-value" style="color: #2563eb;"><?= number_format($tot_item) ?> <span class="widget-unit">SKU</span></div>
        <div class="widget-icon">⚙️</div>
    </div>
    
    <div class="widget-box red">
        <div class="widget-title" style="color: #ef4444;">🚨 Status Stok Kritis</div>
        <div class="widget-value" style="color: #dc2626;"><?= number_format($stok_kritis) ?> <span class="widget-unit" style="color: #fca5a5;">Item</span></div>
        <div class="widget-icon">⚠️</div>
    </div>
    
    <div class="widget-box orange">
        <div class="widget-title">Pemakaian (Keluar) Bulan Ini</div>
        <div class="widget-value" style="color: #d97706;"><?= number_format($pakai_bln_ini, 2, ',', '.') ?> <span class="widget-unit" style="color: #fcd34d;">Unit</span></div>
        <div class="widget-icon">🔧</div>
    </div>

    <div class="widget-box green">
        <div class="widget-title">Barang Masuk (In) Bulan Ini</div>
        <div class="widget-value" style="color: #059669;"><?= number_format($masuk_bln_ini, 2, ',', '.') ?> <span class="widget-unit" style="color: #6ee7b7;">Unit</span></div>
        <div class="widget-icon">📦</div>
    </div>
</div>

<div class="half-grid">
    <div class="chart-card">
        <div class="chart-header">
            <h3>📈 Tren Pemakaian Sparepart Harian</h3>
            <span style="font-size:11px; font-weight:800; color:#0ea5e9; background:#e0f2fe; padding:6px 12px; border-radius:6px; border: 1px solid #bae6fd;">Bulan Ini</span>
        </div>
        <div class="canvas-wrapper">
            <canvas id="chartTrendMtc"></canvas>
        </div>
    </div>
    
    <div class="chart-card">
        <div class="chart-header">
            <h3>🍕 Top 5 Sparepart Sering Keluar</h3>
        </div>
        <div class="canvas-wrapper" style="min-height: 250px;">
            <canvas id="chartTopMtc"></canvas>
        </div>
    </div>
</div>

<div class="chart-card" id="alert-table-card" style="min-height: auto;">
    <div class="chart-header" style="border-bottom: 2px solid #fecaca; margin-bottom: 16px;">
        <h3 style="color: #dc2626; margin:0;">🚨 Peringatan Dini: Stok Menipis (Harus Beli)</h3>
    </div>
    <div class="table-wrapper">
        <table class="table-premium">
            <thead>
                <tr>
                    <th style="width: 15%;">KODE PART</th>
                    <th style="width: 40%;">NAMA SPAREPART</th>
                    <th style="width: 20%; text-align: center;">STOK TERSISA</th>
                    <th style="width: 25%; text-align: center;">BATAS AMAN (LIMIT)</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($tabel_kritis) > 0): foreach($tabel_kritis as $row): ?>
                <tr style="transition: background 0.2s;">
                    <td style="font-weight: 800; color: #64748b;"><?= htmlspecialchars($row['kode_part']) ?></td>
                    <td style="font-weight: 800; color: #0f172a; font-size: 14px;"><?= htmlspecialchars($row['nama_part']) ?></td>
                    <td style="text-align: center; font-size: 16px; font-weight: 900; color: #dc2626; background: #fef2f2;">
                        <?= floatval($row['qty_stok']) ?> <span style="font-size: 11px; color: #f87171;"><?= $row['satuan'] ?></span>
                    </td>
                    <td style="text-align: center; font-weight: 800; color: #0ea5e9; font-size: 15px;"><?= floatval($row['limit_stok']) ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="4" style="text-align: center; padding: 30px; color: #059669; font-weight: 800; font-size: 16px; background: #f0fdf4;">Hore! Semua stok sparepart dalam batas aman. ✅</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    Chart.register(ChartDataLabels);

    // 🚀 FONT & KONFIGURASI TOOLTIP DARK MODE PREMIUM
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

    const labelFont = { weight: 'bold', size: 10, family: fontTheme };
    const commonOptions = {
        responsive: true, maintainAspectRatio: false,
        animation: { duration: 1500, easing: 'easeOutQuart' }, // Animasi Batang Halus
        interaction: { mode: 'index', intersect: false },
        layout: { padding: { top: 25 } },
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { weight: '800', size: 11, family: fontTheme }, color: '#64748b' } },
            y: { display: false, beginAtZero: true, grace: '20%' }
        }
    };

    // 1. CHART TREN HARIAN (BAR CHART INTERAKTIF)
    new Chart(document.getElementById('chartTrendMtc').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($label_harian) ?>,
            datasets: [{ 
                label: 'Qty Dipakai', 
                data: <?= json_encode($val_harian_final) ?>, 
                backgroundColor: '#0ea5e9', 
                hoverBackgroundColor: '#0284c7', // Warna gelap saat hover
                borderColor: 'transparent',
                hoverBorderColor: '#ffffff', // Garis putih menyala
                borderWidth: 2,
                borderRadius: 4, 
                barPercentage: 0.7 
            }]
        },
        options: {
            ...commonOptions,
            plugins: {
                ...commonOptions.plugins,
                tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `Pemakaian: ${ctx.raw} Unit` } },
                datalabels: { 
                    display: (ctx) => ctx.dataset.data[ctx.dataIndex] > 0,
                    color: '#ffffff', backgroundColor: '#0284c7', borderRadius: 4, 
                    padding: {top: 2, bottom: 2, left: 4, right: 4}, font: labelFont, 
                    anchor: 'end', align: 'top', offset: 2,
                    formatter: (val) => val
                }
            }
        }
    });

    // 2. CHART PIE TOP 5 (INTERAKTIF & ANIMATIF)
    new Chart(document.getElementById('chartTopMtc').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($label_top) ?>,
            datasets: [{ 
                data: <?= json_encode($val_top) ?>, 
                backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6'], 
                borderWidth: 2, 
                hoverOffset: 6 // Pie akan melonjak keluar saat dihover
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: 1500, easing: 'easeOutQuart' }, // Efek memutar mulus
            plugins: {
                tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `Total Dipakai: ${ctx.raw} Unit` } },
                legend: { position: 'bottom', labels: { font: { weight: 'bold', size: 10, family: fontTheme }, color: '#1e293b', usePointStyle: true } },
                datalabels: {
                    color: '#ffffff', font: { weight: '900', size: 14, family: fontTheme },
                    formatter: (value, ctx) => { return value > 0 ? value : ""; }
                }
            }
        }
    });
</script>
<?php require_once 'footer.php'; ?>
