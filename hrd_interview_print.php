<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require 'koneksi.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_role = $_SESSION['role'] ?? 'Viewer';
$user_akses = isset($_SESSION['akses_menu']) ? explode(',', $_SESSION['akses_menu']) : [];
$is_admin = ($user_role === 'Admin');
$s_hrd_i = $is_admin || in_array('hrd_interview', $user_akses);

if (!$s_hrd_i) { die("Akses Ditolak."); }
if (!isset($_GET['id'])) { die("ID Laporan tidak ditemukan."); }

$id = intval($_GET['id']);
$stmt = $pdo->prepare("SELECT * FROM db_hrd_interview WHERE id = ?");
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) { die("Data Laporan tidak ditemukan."); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Form Laporan Interview - <?= htmlspecialchars($r['nama_kandidat']) ?></title>
    <style>
        @page { size: A4; margin: 5mm 10mm; }
        body { font-family: 'Calibri', 'Arial', sans-serif; font-size: 10pt; line-height: 1.25; color: #1e293b; margin: 0; padding: 0; }
        
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; border: none; border-top: 4px solid #1e40af; border-bottom: 2px solid #1e40af; background: #f8fafc;}
        .header-table td { padding: 8px; }
        .logo-col { width: 120px; text-align: center; border-right: 2px solid #cbd5e1; }
        .title-col { text-align: center; font-weight: 800; font-size: 13pt; text-transform: uppercase; color: #0f172a; letter-spacing: 0.5px;}
        
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 9.5pt;}
        .info-table td { padding: 2px 0; vertical-align: top; }
        .lbl { font-weight: 700; width: 160px; color: #475569;}
        .titik2 { width: 10px; text-align: center; }

        .section-title { 
            font-weight: 800; text-transform: uppercase; margin-top: 8px; margin-bottom: 4px; 
            background: #dbeafe; color: #1e40af; padding: 4px 8px; font-size: 9.5pt;
            border-left: 4px solid #1d4ed8; border-radius: 2px;
        }
        
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 9.5pt;}
        .data-table th, .data-table td { border: 1px solid #cbd5e1; padding: 3px 5px; vertical-align: top;}
        .data-table th { background: #f1f5f9; font-weight: 700; text-align: left; color: #334155; }
        .text-center { text-align: center !important; }

        .signature-table { width: 100%; margin-top: 15px; text-align: center; font-size: 9.5pt;}
        .signature-table td { width: 33.33%; padding-bottom: 40px; vertical-align: bottom; }
        .sign-line { display: inline-block; width: 70%; border-bottom: 1px solid #0f172a; }

        .rek-box { border: 2px solid #1e40af; padding: 6px 10px; border-radius: 4px; margin-bottom: 10px; background: #f8fafc;}
        .rek-box b { color: #1e40af; font-size: 9.5pt;}
        .rek-box .rek-badge { font-size: 11pt; font-weight: 800; display: inline-block; margin-bottom: 2px; color: #b91c1c; }
        .rek-box .rek-badge.lanjut { color: #047857; }
        .rek-box .rek-badge.cadangan { color: #b45309; }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none; }
        }
        .btn-print { background: #1e40af; color: #fff; padding: 10px 20px; border: none; cursor: pointer; position: fixed; top: 20px; right: 20px; border-radius: 6px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1);}
    </style>
</head>
<body>

<button class="no-print btn-print" onclick="window.print()">🖨️ Cetak Dokumen</button>

<table class="header-table">
    <tr>
        <td class="logo-col" rowspan="2">
            <img src="logo.png" alt="Logo H2 BASE" style="max-height: 45px; max-width: 100%;">
        </td>
        <td class="title-col">FORM LAPORAN INTERVIEW KANDIDAT</td>
    </tr>
</table>

<table class="info-table">
    <tr>
        <td class="lbl">Tanggal Interview</td> <td class="titik2">:</td> <td><?= date('d F Y', strtotime($r['tgl_interview'])) ?></td>
    </tr>
    <tr>
        <td class="lbl">Nama Kandidat</td> <td class="titik2">:</td> <td><b><?= htmlspecialchars($r['nama_kandidat']) ?></b></td>
    </tr>
    <tr>
        <td class="lbl">Posisi Yang Dilamar</td> <td class="titik2">:</td> <td style="font-weight:700;"><?= htmlspecialchars($r['posisi_dilamar']) ?></td>
    </tr>
    <tr>
        <td class="lbl">Pewawancara</td> <td class="titik2">:</td> <td><?= htmlspecialchars($r['pewawancara']) ?></td>
    </tr>
</table>

<div class="section-title">A. PROFIL & HASIL TES KANDIDAT</div>
<table class="data-table">
    <tr>
        <th style="width: 25%;">Pendidikan Terakhir</th> <td><?= htmlspecialchars($r['pendidikan'] ?? '-') ?></td>
        <th style="width: 20%;">Alat Tes IQ</th> <td><?= htmlspecialchars($r['alat_tes'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Total Pengalaman</th> <td><?= htmlspecialchars($r['total_pengalaman'] ?? '-') ?></td>
        <th>Skor IQ</th> <td><?= htmlspecialchars($r['skor_iq'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Perusahaan Terakhir</th> <td><?= htmlspecialchars($r['perusahaan_terakhir'] ?? '-') ?></td>
        <th>Kategori IQ</th> <td><?= htmlspecialchars($r['kategori_iq'] ?? '-') ?></td>
    </tr>
    <tr>
        <th>Jabatan Terakhir</th> <td colspan="3"><?= htmlspecialchars($r['jabatan_terakhir'] ?? '-') ?></td>
    </tr>
</table>

<div class="section-title">B. PENILAIAN KOMPETENSI (Skala 1-5)</div>
<table class="data-table">
    <tr>
        <th style="width: 30%;">Aspek Penilaian</th>
        <th style="width: 10%; text-align:center;">Skor</th>
        <th>Catatan / Keterangan</th>
    </tr>
    <tr>
        <td>Teknis (Hard Skill)</td>
        <td class="text-center"><?= htmlspecialchars($r['skor_teknis']) ?></td>
        <td><?= htmlspecialchars($r['cat_teknis']) ?></td>
    </tr>
    <tr>
        <td>Komunikasi</td>
        <td class="text-center"><?= htmlspecialchars($r['skor_komunikasi']) ?></td>
        <td><?= htmlspecialchars($r['cat_komunikasi']) ?></td>
    </tr>
    <tr>
        <td>Sikap (Attitude)</td>
        <td class="text-center"><?= htmlspecialchars($r['skor_sikap']) ?></td>
        <td><?= htmlspecialchars($r['cat_sikap']) ?></td>
    </tr>
    <tr>
        <td>Kesesuaian Budaya</td>
        <td class="text-center"><?= htmlspecialchars($r['skor_budaya']) ?></td>
        <td><?= htmlspecialchars($r['cat_budaya']) ?></td>
    </tr>
    <tr>
        <td>Problem Solving</td>
        <td class="text-center"><?= htmlspecialchars($r['skor_problem']) ?></td>
        <td><?= htmlspecialchars($r['cat_problem']) ?></td>
    </tr>
</table>

<div class="section-title">C. KOMPENSASI & KETERSEDIAAN</div>
<table class="info-table">
    <tr>
        <td class="lbl" style="width: 150px;">Gaji Terakhir</td> <td class="titik2">:</td> <td><?= htmlspecialchars($r['gaji_terakhir'] ?? '-') ?></td>
    </tr>
    <tr>
        <td class="lbl">Ekspektasi Gaji</td> <td class="titik2">:</td> <td><?= htmlspecialchars($r['gaji_ekspektasi'] ?? '-') ?></td>
    </tr>
    <tr>
        <td class="lbl">Notice Period</td> <td class="titik2">:</td> <td><?= htmlspecialchars($r['notice_period'] ?? '-') ?></td>
    </tr>
    <tr>
        <td class="lbl">Tanggal Siap Kerja</td> <td class="titik2">:</td> <td><?= (!empty($r['tgl_siap'])) ? date('d F Y', strtotime($r['tgl_siap'])) : '-' ?></td>
    </tr>
</table>

<div class="section-title">D. KEUNGGULAN & RED FLAGS</div>
<table class="data-table">
    <tr>
        <th style="width:50%;">Keunggulan Utama</th>
        <th style="width:50%;">Potensi Risiko / Catatan Merah</th>
    </tr>
    <tr>
        <td style="vertical-align: top; min-height: 35px;"><?= nl2br(htmlspecialchars($r['keunggulan'] ?? '-')) ?></td>
        <td style="vertical-align: top; min-height: 35px; color: #991b1b;"><?= nl2br(htmlspecialchars($r['risiko'] ?? '-')) ?></td>
    </tr>
</table>

<div class="section-title">E. KESIMPULAN & REKOMENDASI</div>
<?php
    $rek_cls = 'cadangan';
    if(strpos(strtoupper($r['rekomendasi']), 'LANJUT') !== false) $rek_cls = 'lanjut';
    if(strpos(strtoupper($r['rekomendasi']), 'TIDAK') !== false) $rek_cls = '';
?>
<div class="rek-box">
    <b>Keputusan Rekomendasi:</b> <br>
    <span class="rek-badge <?= $rek_cls ?>"><?= htmlspecialchars($r['rekomendasi']) ?></span>
    <br><br>
    <b>Alasan / Catatan Akhir:</b> <br>
    <span style="font-style: italic;"><?= nl2br(htmlspecialchars($r['alasan_rekomendasi'])) ?></span>
</div>

<table class="signature-table">
    <tr>
        <td>
            Dibuat Oleh,<br><br><br>
            <span class="sign-line"></span><br>
            <b><?= htmlspecialchars($r['pewawancara']) ?></b><br>HRD / Interviewer
        </td>
        <td>
            Mengetahui,<br><br><br>
            <span class="sign-line"></span><br>
            <br>User / SPV
        </td>
        <td>
            Disetujui Oleh,<br><br><br>
            <span class="sign-line"></span><br>
            <br>Direktur / HR Manager
        </td>
    </tr>
</table>

</body>
</html>
