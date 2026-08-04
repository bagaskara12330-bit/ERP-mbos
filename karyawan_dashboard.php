<?php
require_once 'auth.php';
require_akses('dash_hrd');


// 🚀 AMBIL TANGGAL TERAKHIR UPDATE ABSENSI
$tgl_update = "Belum Ada Data";
try {
    $last_date = $pdo->query("SELECT MAX(tanggal) FROM data_absensi")->fetchColumn();
    if ($last_date) {
        $tgl_update = date('d F Y', strtotime($last_date));
    }
} catch(Exception $e) {}

// 🚀 TANGKAP FILTER BULAN DAN TAHUN
$bulan_ini = isset($_GET['bulan']) ? str_pad($_GET['bulan'], 2, '0', STR_PAD_LEFT) : date('m');
$tahun_ini = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

$nama_bulan_list = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni',
    '07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$nama_bulan = $nama_bulan_list[$bulan_ini];

try {
    // 1. KOTAK WIDGET RINGKASAN
    $total_aktif = $pdo->query("SELECT COUNT(*) FROM db_karyawan_h2 WHERE ket_status='AKTIF'")->fetchColumn();
    $total_tetap = $pdo->query("SELECT COUNT(*) FROM db_karyawan_h2 WHERE status_pkwt = 'TETAP' AND ket_status='AKTIF'")->fetchColumn();
    $total_kontrak = $pdo->query("SELECT COUNT(*) FROM db_karyawan_h2 WHERE status_pkwt = 'KONTRAK' AND ket_status='AKTIF'")->fetchColumn();
    $total_hl = $pdo->query("SELECT COUNT(*) FROM db_karyawan_h2 WHERE status_pkwt = 'HL' AND ket_status='AKTIF'")->fetchColumn();
    
    // Hitung total jam lembur berdasarkan filter
    $stmt_lembur = $pdo->prepare("SELECT SUM(durasi_jam) FROM data_lembur WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt_lembur->execute([$bulan_ini, $tahun_ini]);
    $total_lembur_bln = $stmt_lembur->fetchColumn() ?: 0;

    // 2. GRAFIK SEBARAN BAGIAN
    $q_dept = $pdo->query("SELECT bagian, COUNT(*) as total FROM db_karyawan_h2 WHERE ket_status='AKTIF' GROUP BY bagian ORDER BY total DESC");
    $l_dept = []; $a_dept = [];
    foreach($q_dept->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $l_dept[] = empty($row['bagian']) ? 'Belum Diinput' : $row['bagian'];
        $a_dept[] = $row['total'];
    }

    // 3. GRAFIK STATUS PKWT
    $q_pkwt = $pdo->query("SELECT status_pkwt, COUNT(*) as total FROM db_karyawan_h2 WHERE ket_status='AKTIF' GROUP BY status_pkwt ORDER BY total DESC");
    $l_pkwt = []; $a_pkwt = [];
    foreach($q_pkwt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $l_pkwt[] = empty($row['status_pkwt']) ? 'Belum Diinput' : $row['status_pkwt'];
        $a_pkwt[] = $row['total'];
    }

    // 3B. GRAFIK KARYAWAN LOKAL VS LUAR (LINGKUNGAN)
    $q_ling = $pdo->query("SELECT lingkungan, COUNT(*) as total FROM db_karyawan_h2 WHERE ket_status='AKTIF' GROUP BY lingkungan ORDER BY total DESC");
    $l_ling = []; $a_ling = [];
    foreach($q_ling->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $nama_ling = trim($row['lingkungan'] ?? '');
        if (empty($nama_ling) || $nama_ling == '-') {
            $nama_ling = 'Belum Diisi';
        }
        $index = array_search($nama_ling, $l_ling);
        if ($index !== false) {
            $a_ling[$index] += $row['total'];
        } else {
            $l_ling[] = $nama_ling;
            $a_ling[] = $row['total'];
        }
    }
    if (count($l_ling) == 0) {
        $l_ling[] = "Belum Diisi";
        $a_ling[] = 1;
    }

    // 4. GRAFIK TOP 7 KARYAWAN LEMBUR TERBANYAK
    $q_top_lembur = $pdo->prepare("SELECT nama_karyawan, SUM(durasi_jam) as total_jam FROM data_lembur WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? GROUP BY nama_karyawan ORDER BY total_jam DESC LIMIT 7");
    $q_top_lembur->execute([$bulan_ini, $tahun_ini]);
    $l_top_lembur = []; $a_top_lembur = [];
    foreach($q_top_lembur->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $l_top_lembur[] = $row['nama_karyawan'];
        $a_top_lembur[] = floatval($row['total_jam']);
    }

    // 🚀 4B. GRAFIK TOP 7 KARYAWAN SERING TERLAMBAT (SUDAH DIUPGRADE SESUAI MASTER SHIFT)
    $q_top_telat = $pdo->prepare("
        SELECT nama_karyawan, COUNT(*) as total_telat 
        FROM data_absensi
        WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?
        AND status_masuk = 'TERLAMBAT'
        GROUP BY nama_karyawan 
        ORDER BY total_telat DESC 
        LIMIT 7
    ");
    $q_top_telat->execute([$bulan_ini, $tahun_ini]);
    $l_top_telat = []; $a_top_telat = [];
    foreach($q_top_telat->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $l_top_telat[] = $row['nama_karyawan'];
        $a_top_telat[] = intval($row['total_telat']);
    }

    // 🚀 [UPDATE BARU] 4C. GRAFIK TOP 7 KARYAWAN ABSEN DENGAN BREAKDOWN (SAKIT, IZIN, ALPHA)
    // Cari Top 7 karyawan paling sering absen dulu
    $q_top_absen = $pdo->prepare("SELECT nama_karyawan, COUNT(*) as total_absen FROM data_absensi WHERE keterangan IN ('SAKIT', 'IJIN', 'ALPHA') AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? GROUP BY nama_karyawan ORDER BY total_absen DESC LIMIT 7");
    $q_top_absen->execute([$bulan_ini, $tahun_ini]);
    $top_karyawan_absen = $q_top_absen->fetchAll(PDO::FETCH_ASSOC);

    $l_top_absen = []; 
    $a_sakit = []; $a_ijin = []; $a_alpha = [];
    
    if (count($top_karyawan_absen) > 0) {
        $stmt_absen_detail = $pdo->prepare("SELECT keterangan, COUNT(*) as jml FROM data_absensi WHERE nama_karyawan = ? AND keterangan IN ('SAKIT', 'IJIN', 'ALPHA') AND MONTH(tanggal) = ? AND YEAR(tanggal) = ? GROUP BY keterangan");
        foreach($top_karyawan_absen as $row) {
            $nama_kar = $row['nama_karyawan'];
            $l_top_absen[] = $nama_kar;
            
            // Tarik rincian untuk karyawan tersebut
            $stmt_absen_detail->execute([$nama_kar, $bulan_ini, $tahun_ini]);
            $detail = $stmt_absen_detail->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $a_sakit[] = isset($detail['SAKIT']) ? intval($detail['SAKIT']) : 0;
            $a_ijin[] = isset($detail['IJIN']) ? intval($detail['IJIN']) : 0;
            $a_alpha[] = isset($detail['ALPHA']) ? intval($detail['ALPHA']) : 0;
        }
    }

    // 5. GRAFIK BEBAN LEMBUR PER DEPARTEMEN
    $q_lembur_dept = $pdo->prepare("SELECT bagian, SUM(durasi_jam) as total_jam FROM data_lembur WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? GROUP BY bagian ORDER BY total_jam DESC LIMIT 7");
    $q_lembur_dept->execute([$bulan_ini, $tahun_ini]);
    $l_lembur_dept = []; $a_lembur_dept = [];
    foreach($q_lembur_dept->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $l_lembur_dept[] = $row['bagian'];
        $a_lembur_dept[] = floatval($row['total_jam']);
    }

    // =====================================================================================
    // 6. AKUMULASI PRODUKTIVITAS PER BAGIAN (RUMUS SUPER AKURAT INTEGRASI ABSENSI)
    // =====================================================================================
    
    // Ambil jumlah karyawan aktif per bagian
    $q_kar_dept = $pdo->query("SELECT UPPER(TRIM(bagian)) as bagian_rapi, COUNT(*) as jml FROM db_karyawan_h2 WHERE ket_status='AKTIF' GROUP BY UPPER(TRIM(bagian))");
    $arr_kar_dept = [];
    foreach($q_kar_dept->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $arr_kar_dept[$r['bagian_rapi']] = intval($r['jml']);
    }

    // Ambil TOTAL JAM LEMBUR sebulan berdasarkan filter
    $stmt_lemb_bln = $pdo->prepare("SELECT UPPER(TRIM(bagian)) as bagian_rapi, SUM(durasi_jam) as lembur FROM data_lembur WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? GROUP BY UPPER(TRIM(bagian))");
    $stmt_lemb_bln->execute([$bulan_ini, $tahun_ini]);
    $arr_lemb_bln = [];
    foreach($stmt_lemb_bln->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $arr_lemb_bln[$r['bagian_rapi']] = floatval($r['lembur']);
    }

    // 🚀 FIX BUG MAN-HOURS: Ambil jumlah orang yang TIDAK HADIR (Sakit, Izin, Alpha) untuk dipotong dari Jam Reguler
    $stmt_absen = $pdo->prepare("
        SELECT UPPER(TRIM(k.bagian)) as bagian_rapi, COUNT(a.id) as jml_absen_hari 
        FROM data_absensi a 
        JOIN db_karyawan_h2 k ON UPPER(TRIM(a.nama_karyawan)) = UPPER(TRIM(k.nama)) 
        WHERE a.keterangan != 'HADIR' AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ? 
        GROUP BY UPPER(TRIM(k.bagian))
    ");
    $stmt_absen->execute([$bulan_ini, $tahun_ini]);
    $arr_absen = [];
    foreach($stmt_absen->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $arr_absen[$r['bagian_rapi']] = intval($r['jml_absen_hari']);
    }

    // Ambil TOTAL TSTB (Produksi) sebulan berdasarkan filter dari tabel mbos_regu
    $tot_tstb_sebulan = 0;
    try {
        $stmt_tstb = $pdo->prepare("SELECT SUM(kg_produksi) FROM mbos_regu WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
        $stmt_tstb->execute([$bulan_ini, $tahun_ini]);
        $tot_tstb_sebulan = floatval($stmt_tstb->fetchColumn() ?: 0);
    } catch (Exception $e) { $tot_tstb_sebulan = 0; }

    // Hitung Hari Kerja Aktual (Berdasarkan jadwal operasional mesin jalan)
    $stmt_hari = $pdo->prepare("SELECT COUNT(DISTINCT tanggal) FROM mbos_regu WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
    $stmt_hari->execute([$bulan_ini, $tahun_ini]);
    $hari_berjalan = intval($stmt_hari->fetchColumn() ?: 1); 

    // FILTER HANYA BAGIAN INTI
    $departemen_target = ['CORR', 'BOILER', 'GA', 'PROLL', 'PPROL'];
    $data_produktivitas = [];

    // Penampung Baris Grand Total
    $tot_karyawan = 0;
    $tot_jam_kerja = 0;
    $tot_jam_lembur = 0;
    $tot_total_jam = 0;

    foreach ($arr_kar_dept as $nama_bagian => $jml_karyawan) {
        if (!in_array($nama_bagian, $departemen_target)) continue;

        // 🚀 RUMUS JAM AKTUAL (Menyerap Data Absensi)
        $jam_kotor_asumsi = $jml_karyawan * 7 * $hari_berjalan; 
        $potongan_jam_absen = ($arr_absen[$nama_bagian] ?? 0) * 7;
        
        $jam_kerja_reguler = $jam_kotor_asumsi - $potongan_jam_absen;
        if($jam_kerja_reguler < 0) { $jam_kerja_reguler = 0; } // Jaga-jaga jika salah input HRD
        
        $jam_lembur = $arr_lemb_bln[$nama_bagian] ?? 0;
        $total_jam = $jam_kerja_reguler + $jam_lembur;
        
        $output_kg_jam_orang = 0;
        if ($tot_tstb_sebulan > 0 && $total_jam > 0) {
            $output_kg_jam_orang = $tot_tstb_sebulan / $total_jam;
        }

        $bagian_display = ($nama_bagian == 'PPROL') ? 'PROLL' : $nama_bagian;

        $data_produktivitas[] = [
            'bagian' => $bagian_display,
            'jumlah_karyawan' => $jml_karyawan,
            'jam_kerja' => $jam_kerja_reguler,
            'jam_lembur' => $jam_lembur,
            'total_jam' => $total_jam,
            'output_kg_jam_orang' => $output_kg_jam_orang
        ];

        $tot_karyawan += $jml_karyawan;
        $tot_jam_kerja += $jam_kerja_reguler;
        $tot_jam_lembur += $jam_lembur;
        $tot_total_jam += $total_jam;
    }

    usort($data_produktivitas, function($a, $b) {
        return $b['output_kg_jam_orang'] <=> $a['output_kg_jam_orang'];
    });

    $tot_output_global = 0;
    if ($tot_tstb_sebulan > 0 && $tot_total_jam > 0) {
        $tot_output_global = $tot_tstb_sebulan / $tot_total_jam;
    }

    // =====================================================================================
    // 7. DATA GRAFIK TREND TAHUNAN (BULAN 1 S.D 12)
    // =====================================================================================
    $trend_bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
    $trend_output = array_fill(0, 12, 0);

    // Ambil TSTB dan Hari Kerja (setahun)
    $stmt_tstb_yr = $pdo->prepare("SELECT MONTH(tanggal) as bln, SUM(kg_produksi) as tstb_total, COUNT(DISTINCT tanggal) as hari_kerja FROM mbos_regu WHERE YEAR(tanggal) = ? GROUP BY MONTH(tanggal)");
    $stmt_tstb_yr->execute([$tahun_ini]);
    $data_tstb_yr = [];
    foreach($stmt_tstb_yr->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $data_tstb_yr[intval($r['bln'])] = ['tstb' => floatval($r['tstb_total']), 'hari' => intval($r['hari_kerja'])];
    }

    // Ambil Lembur (setahun)
    $stmt_lemb_yr = $pdo->prepare("SELECT MONTH(tanggal) as bln, UPPER(TRIM(bagian)) as bagian_rapi, SUM(durasi_jam) as lembur FROM data_lembur WHERE YEAR(tanggal) = ? GROUP BY MONTH(tanggal), UPPER(TRIM(bagian))");
    $stmt_lemb_yr->execute([$tahun_ini]);
    $data_lemb_yr = [];
    foreach($stmt_lemb_yr->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $data_lemb_yr[intval($r['bln'])][$r['bagian_rapi']] = floatval($r['lembur']);
    }
    
    // Ambil Absensi (Sakit/Izin/Alpha) Setahun
    $stmt_absen_yr = $pdo->prepare("SELECT MONTH(a.tanggal) as bln, UPPER(TRIM(k.bagian)) as bagian_rapi, COUNT(a.id) as jml_absen_hari FROM data_absensi a JOIN db_karyawan_h2 k ON UPPER(TRIM(a.nama_karyawan)) = UPPER(TRIM(k.nama)) WHERE a.keterangan != 'HADIR' AND YEAR(a.tanggal) = ? GROUP BY MONTH(a.tanggal), UPPER(TRIM(k.bagian))");
    $stmt_absen_yr->execute([$tahun_ini]);
    $data_absen_yr = [];
    foreach($stmt_absen_yr->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $data_absen_yr[intval($r['bln'])][$r['bagian_rapi']] = intval($r['jml_absen_hari']);
    }

    // Kalkulasi Output Global per Bulan untuk Grafik
    for ($m = 1; $m <= 12; $m++) {
        $m_tstb = $data_tstb_yr[$m]['tstb'] ?? 0;
        $m_hari = $data_tstb_yr[$m]['hari'] ?? 1;
        
        $m_tot_jam = 0;
        foreach ($arr_kar_dept as $nama_bagian => $jml_karyawan) {
            if (!in_array($nama_bagian, $departemen_target)) continue;
            $jam_kotor = $jml_karyawan * 7 * $m_hari;
            $pot_absen = ($data_absen_yr[$m][$nama_bagian] ?? 0) * 7;
            
            $jam_reguler = $jam_kotor - $pot_absen;
            if($jam_reguler < 0) $jam_reguler = 0;
            
            $jam_lembur = $data_lemb_yr[$m][$nama_bagian] ?? 0;
            $m_tot_jam += ($jam_reguler + $jam_lembur);
        }

        if ($m_tot_jam > 0 && $m_tstb > 0) {
            $trend_output[$m-1] = round($m_tstb / $m_tot_jam, 2);
        }
    }

} catch (PDOException $e) {
    $total_aktif = 0; $total_tetap = 0; $total_kontrak = 0; $total_hl = 0; $total_lembur_bln = 0;
    $l_dept = []; $a_dept = []; $l_pkwt = []; $a_pkwt = []; $data_produktivitas = [];
    $tot_karyawan = 0; $tot_jam_kerja = 0; $tot_jam_lembur = 0; $tot_total_jam = 0; $tot_tstb_sebulan = 0; $tot_output_global = 0; $hari_berjalan = 0;
    $trend_bulan = []; $trend_output = [];
    $l_top_lembur = []; $a_top_lembur = []; $l_top_telat = []; $a_top_telat = []; 
    $l_top_absen = []; $a_sakit = []; $a_ijin = []; $a_alpha = []; 
    $l_lembur_dept = []; $a_lembur_dept = [];
}

// Format ke JSON untuk Chart
$json_l_dept = json_encode($l_dept); $json_a_dept = json_encode($a_dept);
$json_l_pkwt = json_encode($l_pkwt); $json_a_pkwt = json_encode($a_pkwt);
$json_l_ling = json_encode($l_ling ?? []); $json_a_ling = json_encode($a_ling ?? []);
$json_l_top_l = json_encode($l_top_lembur); $json_a_top_l = json_encode($a_top_lembur);
$json_l_top_telat = json_encode($l_top_telat); $json_a_top_telat = json_encode($a_top_telat);
$json_l_l_dept = json_encode($l_lembur_dept); $json_a_l_dept = json_encode($a_lembur_dept);
$json_trend_bulan = json_encode($trend_bulan); $json_trend_output = json_encode($trend_output);

// 🚀 DATA UNTUK GRAFIK PROGRES INPUT ABSENSI
$l_progres_tgl = []; $a_progres_input = [];
try {
    $stmt_prog = $pdo->query("SELECT tanggal, COUNT(id) as total_input FROM data_absensi WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY tanggal ORDER BY tanggal ASC");
    $data_prog = $stmt_prog->fetchAll(PDO::FETCH_ASSOC);
    foreach($data_prog as $row) {
        $l_progres_tgl[] = date('d', strtotime($row['tanggal']));
        $a_progres_input[] = $row['total_input'];
    }
} catch(Exception $e) {}
$json_l_progres = json_encode($l_progres_tgl); $json_a_progres = json_encode($a_progres_input);

// JSON Kategori Breakdown Absen
$json_l_top_absen = json_encode($l_top_absen); 
$json_a_sakit = json_encode($a_sakit);
$json_a_ijin = json_encode($a_ijin);
$json_a_alpha = json_encode($a_alpha);

$page_title = "Dashboard Karyawan — H2 BASE ERP";
$active_page = "karyawan_dashboard";
require 'header.php';
?>

<style>
    /* 🚀 ANIMASI ENTRANCE GLOBAL (MUNCUL MELAYANG) */
    @keyframes slideUpFade {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .filter-card { background: white; padding: 15px 20px; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 24px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; box-shadow: 0 2px 4px rgba(0,0,0,0.02); animation: slideUpFade 0.4s ease-out forwards; }
    .filter-card label { font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; }
    .filter-card select { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; background: #f8fafc; outline: none; min-width: 130px; cursor: pointer; transition: 0.2s; }
    .filter-card select:hover { border-color: #0f172a; }
    .filter-card select:focus { border-color: #0ea5e9; background: white; }
    
    .btn-filter-submit { background: #0f172a; color: white; border: none; padding: 9px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
    .btn-filter-submit:hover { background: #1e293b; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.3); transform: translateY(-1px); }
    .btn-filter-submit:active { transform: scale(0.95); }

    /* 🚀 WIDGET SUMMARY INTERAKTIF */
    .summary-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; margin-bottom: 24px; }
    .summary-card { 
        background: white; padding: 18px; border-radius: 10px; border: 1px solid #e2e8f0; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: center; 
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        opacity: 0; 
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    /* Delay Staggered Entrance Widget */
    .summary-card:nth-child(1) { animation-delay: 0.1s; border-top: 4px solid #0ea5e9; }
    .summary-card:nth-child(2) { animation-delay: 0.2s; border-top: 4px solid #16a34a; }
    .summary-card:nth-child(3) { animation-delay: 0.3s; border-top: 4px solid #f59e0b; }
    .summary-card:nth-child(4) { animation-delay: 0.4s; border-top: 4px solid #ef4444; }
    .summary-card:nth-child(5) { animation-delay: 0.5s; border-top: 4px solid #8b5cf6; background: #faf5ff; }
    .summary-card:nth-child(6) { animation-delay: 0.6s; border-top: 4px solid #0284c7; background: #f0f9ff; }

    .summary-card:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 15px 25px -5px rgba(0,0,0,0.15); cursor: pointer; }
    .summary-card .title { font-size: 10.5px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 8px; }
    .summary-card .value { font-size: 22px; font-weight: 900; color: #0f172a; margin: 4px 0; }
    .summary-card .unit { font-size: 11px; font-weight: 600; color: #94a3b8; margin-left: 4px; }

    /* 🚀 KOTAK GRAFIK & TABEL INTERAKTIF */
    .half-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; align-items: stretch; }
    .chart-card, .full-width-card { 
        background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); display: flex; flex-direction: column; 
        transition: all 0.3s ease;
        opacity: 0;
        animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    
    /* Delay untuk kotak-kotak bawah */
    .half-grid:nth-of-type(3) .chart-card { animation-delay: 0.5s; }
    .half-grid:nth-of-type(4) .chart-card { animation-delay: 0.6s; }
    .half-grid:nth-of-type(5) .chart-card { animation-delay: 0.7s; }
    .full-width-card { animation-delay: 0.8s; }

    .chart-card:hover, .full-width-card:hover { box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1); }
    
    .chart-card { min-height: 380px; }
    .chart-card h3 { font-size: 15px; font-weight: 800; color: #1e293b; margin-top: 0; margin-bottom: 16px; text-transform: uppercase; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
    .canvas-wrapper { position: relative; flex-grow: 1; height: 100%; min-height: 300px; width: 100%; }

    .full-width-card { margin-bottom: 24px; }
    .table-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; }
    .table-header-flex h3 { margin: 0; text-transform: uppercase; font-size: 15px; font-weight: 800; color: #1e293b; }
    
    .row-grand-total td { position: sticky; bottom: 0; z-index: 12; background: #0f172a !important; color: #ffffff !important; font-weight: 900 !important; font-size: 13px; border-top: 3px solid #00f0ff; padding: 12px; box-shadow: 0 -4px 10px rgba(0,0,0,0.2); }
    .row-grand-total td.highlight-avg { background: #0ea5e9 !important; color: #ffffff !important; }

    @media (max-width: 1400px) { .summary-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 992px) { .half-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } .chart-card { min-height: 350px; } }
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
    <div style="display: flex; align-items: center; gap: 15px;">
        <button type="submit" class="btn-filter-submit">🔍 Terapkan Saringan</button>
        <div style="font-size: 13px; color: #64748b; font-weight: 600; padding: 8px 14px; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 16px;">⏱️</span>
            <span>Update Terakhir: <strong style="color: #0f172a; font-weight: 800;"><?= $tgl_update ?></strong></span>
        </div>
    </div>

</form>

<div class="summary-grid">
    <div class="summary-card">
        <div class="title">Total Karyawan Aktif</div>
        <div class="value"><?= number_format($total_aktif) ?> <span class="unit">Orang</span></div>
    </div>
    <div class="summary-card">
        <div class="title">Karyawan Tetap</div>
        <div class="value"><?= number_format($total_tetap) ?> <span class="unit">Orang</span></div>
    </div>
    <div class="summary-card">
        <div class="title">Karyawan Kontrak</div>
        <div class="value"><?= number_format($total_kontrak) ?> <span class="unit">Orang</span></div>
    </div>
    <div class="summary-card">
        <div class="title">Harian Lepas (HL)</div>
        <div class="value"><?= number_format($total_hl) ?> <span class="unit">Orang</span></div>
    </div>
    <div class="summary-card">
        <div class="title">Jam Lembur (<?= $nama_bulan ?>)</div>
        <div class="value" style="color: #7e22ce;"><?= number_format($total_lembur_bln, 2, ',', '.') ?> <span class="unit" style="color:#a855f7;">Jam</span></div>
    </div>
    <div class="summary-card">
        <div class="title" style="color: #0284c7;">Output Global Pabrik</div>
        <div class="value" style="color: #0369a1;"><?= number_format($tot_output_global, 2, ',', '.') ?> <span class="unit" style="color:#0ea5e9; font-size: 10px;">KG/Jam/Org</span></div>
    </div>
</div>

<div class="half-grid">
    <div class="chart-card">
        <h3>📊 Sebaran Karyawan per Bagian</h3>
        <div class="canvas-wrapper">
            <canvas id="chartDept"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <h3>🔥 Top 7 Karyawan Paling Sering Lembur (Bulan <?= $nama_bulan ?>)</h3>
        <div class="canvas-wrapper">
            <canvas id="chartTopLembur"></canvas>
        </div>
    </div>
</div>

<div class="half-grid">
    <div class="chart-card">
        <h3>🏢 Distribusi Jam Lembur per Departemen (<?= $nama_bulan ?>)</h3>
        <div class="canvas-wrapper">
            <canvas id="chartLemburDept"></canvas>
        </div>
    </div>
    <div class="chart-card" style="border-top: 4px solid #ea580c;">
        <h3>🛑 Top 7 Karyawan Paling Sering Terlambat (Bulan <?= $nama_bulan ?>)</h3>
        <div class="canvas-wrapper">
            <canvas id="chartTopTelat"></canvas>
        </div>
    </div>
</div>

<div class="half-grid">
    <div class="chart-card">
        <h3>📈 Rasio Status Pekerja (PKWT)</h3>
        <div class="canvas-wrapper">
            <canvas id="chartPkwt"></canvas>
        </div>
    </div>
    <div class="chart-card" style="border-top: 4px solid #b91c1c;">
        <h3>⚠️ Top 7 Karyawan Paling Sering Absen (Bulan <?= $nama_bulan ?>)</h3>
        <div class="canvas-wrapper">
            <canvas id="chartTopAbsen"></canvas>
        </div>
    </div>
</div>

<div style="display: flex; justify-content: center; margin-bottom: 24px;">
    <div class="chart-card" style="border-top: 4px solid #14b8a6; width: 100%; max-width: 600px;">
        <h3 style="margin-bottom: 5px; text-align: center;">🌍 Distribusi Karyawan (Lokal vs Luar)</h3>
        <p style="font-size: 11px; color: #94a3b8; margin-top: 0; margin-bottom: 12px; font-weight: 600; text-align: center;">*Berdasarkan kolom 'Lingkungan' di Master Karyawan</p>
        <div class="canvas-wrapper">
            <canvas id="chartLingkungan"></canvas>
        </div>
    </div>
</div>

<div class="full-width-card" style="margin-bottom: 24px; border-top: 4px solid #10b981;">
    <div class="table-header-flex">
        <h3 style="margin-bottom: 0;">📊 Monitoring Progres Input Absensi (14 Hari Terakhir)</h3>
    </div>
    <div class="canvas-wrapper" style="height: 300px;">
        <canvas id="chartProgresInput"></canvas>
    </div>
</div>

<div class="full-width-card" style="margin-bottom: 24px;">
    <div class="table-header-flex">
        <h3 style="margin-bottom: 0;">📈 Trend Pergerakan Akumulasi Global Pabrik (Tahun <?= $tahun_ini ?>)</h3>
        <div style="font-size: 11px; color: #64748b; font-weight: 600;">*Data menyajikan perbandingan rasio Output KG / Jam / Orang dari bulan ke bulan secara akurat (Potong Absensi).</div>
    </div>
    <div class="canvas-wrapper" style="height: 350px;">
        <canvas id="chartTrendGlobal"></canvas>
    </div>
</div>

<div class="full-width-card">
    <div class="table-header-flex">
        <h3 style="margin-bottom: 4px;">📊 Ringkasan Kinerja Output Karyawan (Periode: <?= $nama_bulan ?> <?= $tahun_ini ?>)</h3>
        <div style="font-size: 11px; color: #16a34a; font-weight: 700;">*HORE! Jam kerja otomatis dipotong jika karyawan Sakit/Izin/Alpha dari Log Absensi.</div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 50px;">No</th>
                    <th class="text-left">Bagian Produksi</th>
                    <th class="text-center">Total Karyawan</th>
                    <th class="text-center">Jam Reguler (Net Aktual)</th>
                    <th class="text-center">Jam Lembur (Akumulasi)</th>
                    <th class="text-center" style="background:#f1f5f9; color:#0f172a; border-bottom:2px solid #cbd5e1;">Total Jam Kerja (Man-Hours)</th>
                    <th class="text-center">Total Produksi H2 (KG)</th>
                    <th class="text-center" style="background:#e0f2fe; color:#0369a1; border-bottom:2px solid #0284c7;">Output (KG/Jam/Orang)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no_prod = 1;
                if (count($data_produktivitas) > 0):
                    foreach ($data_produktivitas as $p):
                ?>
                <tr style="transition: background 0.2s;">
                    <td class="text-center" style="color:#64748b; font-weight:bold;"><?= $no_prod++ ?></td>
                    <td class="text-left" style="font-weight:800; color:#0f172a; font-size:14px;">	&#128392; <?= htmlspecialchars($p['bagian']) ?></td>
                    <td class="text-center" style="font-weight:600;"><?= number_format($p['jumlah_karyawan']) ?> Orang</td>
                    <td class="text-center" style="color:#64748b; font-family:monospace;"><?= number_format($p['jam_kerja'], 1, ',', '.') ?> Jam</td>
                    <td class="text-center" style="color:#f59e0b; font-weight:600; font-family:monospace;"><?= number_format($p['jam_lembur'], 1, ',', '.') ?> Jam</td>
                    <td class="text-center" style="font-weight:800; font-family:monospace; background:#f8fafc; color:#0f172a;"><?= number_format($p['total_jam'], 1, ',', '.') ?> Jam</td>
                    <td class="text-center" style="font-weight:700; color:#16a34a;"><?= number_format($tot_tstb_sebulan, 0, ',', '.') ?> KG</td>
                    <td class="text-center" style="font-weight:900; background:#f0f9ff; color:#0369a1; font-size:14px;">
                        <?= number_format($p['output_kg_jam_orang'], 2, ',', '.') ?> <span style="font-size:10px; font-weight:700; color:#0ea5e9;">KG/Jam/Org</span>
                    </td>
                </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                <tr><td colspan="8" class="text-center" style="padding:24px; color:#94a3b8;">Tidak ada record aktivitas lembur atau data produksi untuk bagian operasi inti pada periode terpilih.</td></tr>
                <?php endif; ?>
            </tbody>
            
            <tfoot>
                <tr class="row-grand-total">
                    <td colspan="2" class="text-right" style="padding-right: 15px;">AKUMULASI GLOBAL PABRIK :</td>
                    <td class="text-center"><?= number_format($tot_karyawan) ?> Orang</td>
                    <td class="text-center"><?= number_format($tot_jam_kerja, 1, ',', '.') ?> Jam</td>
                    <td class="text-center"><?= number_format($tot_jam_lembur, 1, ',', '.') ?> Jam</td>
                    <td class="text-center"><?= number_format($tot_total_jam, 1, ',', '.') ?> Jam</td>
                    <td class="text-center"><?= number_format($tot_tstb_sebulan, 0, ',', '.') ?> KG</td>
                    <td class="text-center highlight-avg"><?= number_format($tot_output_global, 2, ',', '.') ?> KG/Jam/Org</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
    Chart.register(ChartDataLabels);

    const colorsPie = ['#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#64748b', '#ec4899', '#14b8a6'];
    const fontTheme = "'Inter', -apple-system, BlinkMacSystemFont, sans-serif";

    // 🚀 TOOLTIP DARK MODE PREMIUM UNTUK SEMUA CHART
    const premiumTooltip = {
        backgroundColor: 'rgba(15, 23, 42, 0.95)',
        titleFont: { size: 12, family: fontTheme, weight: 'normal' },
        bodyFont: { size: 14, weight: 'bold', family: fontTheme },
        padding: 12,
        cornerRadius: 8,
        displayColors: false,
        caretPadding: 10
    };

    const pieOptions = {
        responsive: true, maintainAspectRatio: false,
        animation: { duration: 1500, easing: 'easeOutQuart' }, // Animasi memutar mulus
        plugins: { 
            tooltip: premiumTooltip,
            legend: { position: 'right', labels: { font: {size: 11, weight: '600', family: fontTheme} } },
            datalabels: { 
                color: '#fff', 
                font: { weight: 'bold', size: 11, family: fontTheme }, 
                formatter: (value, ctx) => {
                    let sum = 0; 
                    ctx.chart.data.datasets[0].data.forEach(data => { sum += Number(data); });
                    let percentage = (value * 100 / sum).toFixed(1) + "%"; 
                    return value > 0 ? percentage : ""; 
                }
            }
        }
    };

    new Chart(document.getElementById('chartDept').getContext('2d'), { type: 'doughnut', data: { labels: <?= $json_l_dept ?>, datasets: [{ data: <?= $json_a_dept ?>, backgroundColor: colorsPie, borderWidth: 2, hoverOffset: 4 }] }, options: pieOptions });
    new Chart(document.getElementById('chartPkwt').getContext('2d'), { type: 'pie', data: { labels: <?= $json_l_pkwt ?>, datasets: [{ data: <?= $json_a_pkwt ?>, backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6'], borderWidth: 2, hoverOffset: 4 }] }, options: pieOptions });
    
    if (document.getElementById('chartLingkungan')) {
        new Chart(document.getElementById('chartLingkungan').getContext('2d'), { type: 'doughnut', data: { labels: <?= $json_l_ling ?>, datasets: [{ data: <?= $json_a_ling ?>, backgroundColor: ['#14b8a6', '#f43f5e', '#64748b'], borderWidth: 2, hoverOffset: 4 }] }, options: pieOptions });
    }

    // 🚀 CONFIG MASTER CHART BAR HORIZONTAL (DIUPDATE DENGAN HOVER & ANIMASI)
    const barHorizontalOptions = (unitStr) => ({
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        animation: { duration: 1500, easing: 'easeOutQuart' },
        interaction: { mode: 'index', intersect: false }, // Hover lebih sensitif
        plugins: { 
            tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `${ctx.raw.toLocaleString('id-ID')} ${unitStr}` } },
            legend: { display: false }, 
            datalabels: { color: '#ffffff', font: {weight: 'bold', family: fontTheme}, anchor: 'center', align: 'center', formatter: (val) => val + ' ' + unitStr } 
        },
        scales: { 
            x: { beginAtZero: true, grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { precision: 0 } }, 
            y: { grid: { display: false }, ticks: { font: {weight: 'bold', size: 11, family: fontTheme} } } 
        }
    });

    // 1. RENDER TOP LEMBUR
    new Chart(document.getElementById('chartTopLembur').getContext('2d'), { type: 'bar', data: { labels: <?= $json_l_top_l ?>, datasets: [{ label: 'Total Jam', data: <?= $json_a_top_l ?>, backgroundColor: '#8b5cf6', hoverBackgroundColor: '#7e22ce', borderColor: 'transparent', hoverBorderColor: '#fff', borderWidth: 2, borderRadius: 4, barPercentage: 0.7 }] }, options: barHorizontalOptions('Jam') });

    // 2. RENDER TOP TERLAMBAT
    new Chart(document.getElementById('chartTopTelat').getContext('2d'), { type: 'bar', data: { labels: <?= $json_l_top_telat ?>, datasets: [{ label: 'Frekuensi Telat', data: <?= $json_a_top_telat ?>, backgroundColor: '#ea580c', hoverBackgroundColor: '#c2410c', borderColor: 'transparent', hoverBorderColor: '#fff', borderWidth: 2, borderRadius: 4, barPercentage: 0.7 }] }, options: barHorizontalOptions('Kali') });

    // 3. RENDER TOP ABSEN (STACKED BAR)
    new Chart(document.getElementById('chartTopAbsen').getContext('2d'), { 
        type: 'bar', 
        data: { 
            labels: <?= $json_l_top_absen ?>, 
            datasets: [
                { label: 'Sakit', data: <?= $json_a_sakit ?>, backgroundColor: '#f59e0b', hoverBackgroundColor: '#d97706', borderColor: 'transparent', hoverBorderColor: '#fff', borderWidth: 2, borderRadius: 4 },
                { label: 'Izin', data: <?= $json_a_ijin ?>, backgroundColor: '#3b82f6', hoverBackgroundColor: '#2563eb', borderColor: 'transparent', hoverBorderColor: '#fff', borderWidth: 2, borderRadius: 4 },
                { label: 'Alpha', data: <?= $json_a_alpha ?>, backgroundColor: '#ef4444', hoverBackgroundColor: '#dc2626', borderColor: 'transparent', hoverBorderColor: '#fff', borderWidth: 2, borderRadius: 4 }
            ] 
        }, 
        options: {
            indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            animation: { duration: 1500, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            plugins: { 
                tooltip: premiumTooltip,
                legend: { display: true, position: 'top', labels: { boxWidth: 12, font: {size: 11, weight: 'bold', family: fontTheme} } }, 
                datalabels: { 
                    color: '#ffffff', font: {weight: 'bold', size: 11, family: fontTheme}, anchor: 'center', align: 'center', 
                    formatter: (val) => val > 0 ? val + ' Hr' : '' 
                } 
            },
            scales: { 
                x: { stacked: true, beginAtZero: true, grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { precision: 0 } }, 
                y: { stacked: true, grid: { display: false }, ticks: { font: {weight: 'bold', size: 11, family: fontTheme} } } 
            }
        }
    });

    // 4. CHART DEPARTEMEN LEMBUR
    new Chart(document.getElementById('chartLemburDept').getContext('2d'), { 
        type: 'bar', 
        data: { labels: <?= $json_l_l_dept ?>, datasets: [{ label: 'Total Jam Lembur', data: <?= $json_a_l_dept ?>, backgroundColor: ['#3b82f6', '#f59e0b', '#10b981', '#ef4444', '#14b8a6', '#ec4899', '#64748b'], hoverBackgroundColor: '#0f172a', borderColor: 'transparent', hoverBorderColor: '#fff', borderWidth: 2, borderRadius: 6, barPercentage: 0.6 }] }, 
        options: { 
            responsive: true, maintainAspectRatio: false, animation: { duration: 1500, easing: 'easeOutQuart' }, interaction: { mode: 'index', intersect: false },
            plugins: { tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `${ctx.raw.toLocaleString('id-ID')} Jam` } }, legend: { display: false }, datalabels: { color: '#0f172a', font: {weight: 'bold', family: fontTheme}, anchor: 'end', align: 'top', formatter: (val) => val + ' Jam' } }, 
            scales: { y: { beginAtZero: true, grace: '15%', grid: { color: '#e2e8f0', borderDash: [5, 5] } }, x: { grid: { display: false }, ticks: { font: {weight: 'bold', family: fontTheme} } } } 
        } 
    });

    // 5. CHART TREND GLOBAL
    new Chart(document.getElementById('chartTrendGlobal').getContext('2d'), {
        type: 'bar', 
        data: { labels: <?= $json_trend_bulan ?>, datasets: [{ label: 'Output (KG/Jam/Org)', data: <?= $json_trend_output ?>, backgroundColor: '#0ea5e9', hoverBackgroundColor: '#0284c7', borderColor: 'transparent', hoverBorderColor: '#fff', borderWidth: 2, borderRadius: 4, barPercentage: 0.6 }] },
        options: { 
            responsive: true, maintainAspectRatio: false, animation: { duration: 1500, easing: 'easeOutQuart' }, interaction: { mode: 'index', intersect: false },
            plugins: { tooltip: { ...premiumTooltip, callbacks: { label: (ctx) => `${ctx.raw.toLocaleString('id-ID')} KG/Jam/Orang` } }, legend: { display: false }, datalabels: { color: '#0f172a', font: {weight: '900', size: 12, family: fontTheme}, anchor: 'end', align: 'top', offset: 4, formatter: (val) => val > 0 ? val : '' } }, 
            scales: { y: { beginAtZero: true, grace: '20%', grid: { color: '#e2e8f0', borderDash: [5, 5] }, ticks: { font: {weight: 'bold', family: fontTheme} } }, x: { grid: { display: false }, ticks: { font: {weight: 'bold', color: '#64748b', family: fontTheme} } } } 
        }
    });

    // 6. CHART PROGRES INPUT ABSENSI
    new Chart(document.getElementById('chartProgresInput').getContext('2d'), {
        type: 'bar',
        data: { labels: <?= $json_l_progres ?>, datasets: [{ label: 'Total Input Data', data: <?= $json_a_progres ?>, backgroundColor: '#10b981', hoverBackgroundColor: '#059669', borderColor: 'transparent', hoverBorderColor: '#fff', borderWidth: 2, borderRadius: 4, barPercentage: 0.6 }] },
        options: { 
            responsive: true, maintainAspectRatio: false, animation: { duration: 1500, easing: 'easeOutQuart' }, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { display: false }, tooltip: premiumTooltip },
            scales: { x: { grid: { display: false }, ticks: { font: { family: "'Inter', sans-serif", weight: '600' }, color: '#64748b' } }, y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { family: "'Inter', sans-serif", weight: '600' }, color: '#64748b' } } }
        }
    });
</script>
<?php require_once 'footer.php'; ?>