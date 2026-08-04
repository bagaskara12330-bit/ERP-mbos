<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require 'koneksi.php';

// CEK LOGIN
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_role = $_SESSION['role'] ?? 'Viewer';
$user_akses = isset($_SESSION['akses_menu']) ? explode(',', $_SESSION['akses_menu']) : [];
$is_admin = ($user_role === 'Admin');

// HAK AKSES HALAMAN
$s_hrd_i = $is_admin || in_array('hrd_interview', $user_akses);
if (!$s_hrd_i) {
    echo "<script>alert('🛑 AKSES DITOLAK! Anda tidak memiliki izin untuk modul Report Interview.'); window.location.href='index.php';</script>";
    exit();
}

$user_aktif = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : 'SISTEM';
$pesan = "";

// PROSES TAMBAH / EDIT
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan'])) {
    $id = $_POST['id'] ?? '';
    $tgl = $_POST['tgl_interview'];
    $nama = $_POST['nama_kandidat'];
    
    $data = [
        $_POST['tgl_interview'], $_POST['nama_kandidat'], $_POST['posisi_dilamar'], $_POST['pewawancara'],
        $_POST['pendidikan'], $_POST['perusahaan_terakhir'], $_POST['jabatan_terakhir'], $_POST['total_pengalaman'],
        $_POST['alat_tes'], $_POST['skor_iq'], $_POST['kategori_iq'],
        $_POST['skor_teknis'], $_POST['cat_teknis'], $_POST['skor_komunikasi'], $_POST['cat_komunikasi'],
        $_POST['skor_sikap'], $_POST['cat_sikap'], $_POST['skor_budaya'], $_POST['cat_budaya'],
        $_POST['skor_problem'], $_POST['cat_problem'],
        $_POST['gaji_terakhir'], $_POST['gaji_ekspektasi'], $_POST['notice_period'], $_POST['tgl_siap'],
        $_POST['keunggulan'], $_POST['risiko'], $_POST['rekomendasi'], $_POST['alasan_rekomendasi']
    ];

    if (empty($id)) { // SIMPAN BARU
        $sql = "INSERT INTO db_hrd_interview (
            tgl_interview, nama_kandidat, posisi_dilamar, pewawancara, 
            pendidikan, perusahaan_terakhir, jabatan_terakhir, total_pengalaman, 
            alat_tes, skor_iq, kategori_iq, 
            skor_teknis, cat_teknis, skor_komunikasi, cat_komunikasi, 
            skor_sikap, cat_sikap, skor_budaya, cat_budaya, 
            skor_problem, cat_problem, 
            gaji_terakhir, gaji_ekspektasi, notice_period, tgl_siap, 
            keunggulan, risiko, rekomendasi, alasan_rekomendasi
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $pdo->prepare($sql)->execute($data);
        catatLog($pdo, $user_aktif, "Menambahkan laporan interview baru: $nama", "📑");
        $pesan = "<div class='alert alert-success'>✅ Laporan Interview $nama berhasil disimpan!</div>";
    } else { // EDIT
        $sql = "UPDATE db_hrd_interview SET 
            tgl_interview=?, nama_kandidat=?, posisi_dilamar=?, pewawancara=?, 
            pendidikan=?, perusahaan_terakhir=?, jabatan_terakhir=?, total_pengalaman=?, 
            alat_tes=?, skor_iq=?, kategori_iq=?, 
            skor_teknis=?, cat_teknis=?, skor_komunikasi=?, cat_komunikasi=?, 
            skor_sikap=?, cat_sikap=?, skor_budaya=?, cat_budaya=?, 
            skor_problem=?, cat_problem=?, 
            gaji_terakhir=?, gaji_ekspektasi=?, notice_period=?, tgl_siap=?, 
            keunggulan=?, risiko=?, rekomendasi=?, alasan_rekomendasi=? WHERE id=?";
        $data[] = $id;
        $pdo->prepare($sql)->execute($data);
        catatLog($pdo, $user_aktif, "Mengedit laporan interview: $nama", "✏️");
        $pesan = "<div class='alert alert-success'>✅ Laporan Interview $nama berhasil diperbarui!</div>";
    }
}

// PROSES HAPUS
if (isset($_GET['hapus'])) {
    if ($user_role == 'Admin' || $user_role == 'Editor' || $user_role == 'Operator') {
        $id = intval($_GET['hapus']);
        $stmt = $pdo->prepare("SELECT nama_kandidat FROM db_hrd_interview WHERE id = ?");
        $stmt->execute([$id]); $nm = $stmt->fetchColumn();
        $pdo->prepare("DELETE FROM db_hrd_interview WHERE id=?")->execute([$id]);
        catatLog($pdo, $user_aktif, "Menghapus laporan interview: $nm", "🗑️");
        header("Location: hrd_interview.php?msg=hapus"); exit;
    }
}
if (isset($_GET['msg']) && $_GET['msg']=='hapus') $pesan = "<div class='alert alert-success'>🗑️ Laporan berhasil dihapus!</div>";

// DATA
$query = $pdo->query("SELECT * FROM db_hrd_interview ORDER BY id DESC");
$laporan = $query->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Report Interview Kandidat"; $active_page = "hrd_interview";
require 'header.php';
?>

<style>
    .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 24px; }
    .table-responsive { background: white; border-radius: 10px; border: 1px solid #cbd5e1; overflow-x: auto; box-shadow: 0 1px 3px rgba(0,0,0,0.01);}
    table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; color: #0f172a; white-space: nowrap; }
    th, td { padding: 12px 14px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
    th { background-color: #0f172a; color: white; position: sticky; top: 0; text-transform: uppercase; font-size: 11px; font-weight: 700; border-bottom: 2px solid #1e293b;}
    tbody tr:hover td { background-color: #f8fafc; }
    
    .badge { padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 10px; display: inline-block; }
    .b-lanjut { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .b-cadangan { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .b-tidak { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    .btn-action { display: inline-block; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; font-weight: 800; cursor: pointer; transition: 0.2s; border: 1px solid transparent; }
    .btn-print { background: #e0e7ff; color: #3730a3; border-color: #c7d2fe; } .btn-print:hover { background: #c7d2fe; color: #312e81; }
    .btn-edit { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; } .btn-edit:hover { background: #bae6fd; color: #0369a1; }
    .btn-del { background: #fee2e2; color: #dc2626; border-color: #fecaca; } .btn-del:hover { background: #fca5a5; color: #b91c1c; }
    .btn-tambah { background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;}

    /* MODAL */
    .modal { display: none; position: fixed; top:0; left:0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); z-index: 9999; justify-content: center; align-items: center; padding: 20px; box-sizing: border-box; backdrop-filter: blur(2px);}
    .modal-content { background: white; width: 100%; max-width: 900px; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; max-height: 95vh; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);}
    .modal-content form { display: flex; flex-direction: column; flex-grow: 1; overflow: hidden; }
    .modal-header { background: #0f172a; padding: 16px 24px; color: white; display: flex; justify-content: space-between; align-items: center;}
    .modal-header h3 { margin: 0; font-size: 16px; font-weight: 700;}
    .close-btn { background: none; border: none; color: white; font-size: 24px; cursor: pointer; opacity: 0.7;} .close-btn:hover {opacity:1;}
    .modal-body { padding: 24px; overflow-y: auto; flex-grow: 1;}
    .modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px;}

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-grid.full { grid-template-columns: 1fr; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 6px; text-transform: uppercase;}
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 13px;}
    .form-group textarea { height: 60px; resize: vertical; }
    
    .section-title { font-size: 13px; font-weight: 800; color: #0ea5e9; border-bottom: 2px dashed #e2e8f0; padding-bottom: 8px; margin: 24px 0 16px; text-transform: uppercase;}
    .skor-grid { display: grid; grid-template-columns: 80px 1fr; gap: 10px; align-items: start; }

    /* CUSTOM RADIO BUTTONS */
    .radio-group { display: flex; flex-direction: column; gap: 8px; }
    .radio-option { display: block; position: relative; cursor: pointer; }
    .radio-option input[type="radio"] { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }
    .radio-label { display: block; padding: 12px 16px; border: 2px solid #cbd5e1; border-radius: 8px; font-family: "Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji", sans-serif; font-weight: 700; font-size: 13px; text-align: center; transition: all 0.2s; background: white; color: #64748b; }
    .radio-option input[type="radio"]:checked + .radio-label.rek-lanjut { border-color: #10b981; background: #ecfdf5; color: #047857; }
    .radio-option input[type="radio"]:checked + .radio-label.rek-cadangan { border-color: #f59e0b; background: #fffbeb; color: #b45309; }
    .radio-option input[type="radio"]:checked + .radio-label.rek-tidak { border-color: #ef4444; background: #fef2f2; color: #b91c1c; }
    .radio-option input[type="radio"]:hover + .radio-label { border-color: #94a3b8; color: #0f172a; }
</style>

<?= $pesan ?>

<div class="card" style="border-top: 5px solid #8b5cf6;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin:0; font-size: 18px; color: #0f172a;">👥 Laporan Interview Kandidat</h2>
        <?php if ($user_role != 'Viewer'): ?>
            <button class="btn-tambah" onclick="openModal('modalLaporan')">➕ Tambah Laporan Baru</button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table id="tblLaporan" class="display">
            <thead>
                <tr>
                    <th style="width:50px;">NO</th>
                    <th>TGL INTERVIEW</th>
                    <th>NAMA KANDIDAT</th>
                    <th>POSISI</th>
                    <th>PEWAWANCARA</th>
                    <th>REKOMENDASI</th>
                    <th style="text-align:center;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php $n=1; foreach($laporan as $r): 
                    $rek_cls = 'b-cadangan';
                    if(strpos(strtoupper($r['rekomendasi']), 'LANJUT') !== false) $rek_cls = 'b-lanjut';
                    if(strpos(strtoupper($r['rekomendasi']), 'TIDAK') !== false) $rek_cls = 'b-tidak';
                ?>
                <tr>
                    <td style="text-align:center; font-weight:700; color:#64748b;"><?= $n++ ?></td>
                    <td><?= date('d-M-Y', strtotime($r['tgl_interview'])) ?></td>
                    <td style="font-weight:700; color:#0f172a;"><?= htmlspecialchars($r['nama_kandidat']) ?></td>
                    <td><?= htmlspecialchars($r['posisi_dilamar']) ?></td>
                    <td><?= htmlspecialchars($r['pewawancara']) ?></td>
                    <td><span class="badge <?= $rek_cls ?>"><?= htmlspecialchars($r['rekomendasi']) ?></span></td>
                    <td style="text-align:center; display:flex; gap:6px; justify-content:center;">
                        <a href="hrd_interview_print.php?id=<?= $r['id'] ?>" target="_blank" class="btn-action btn-print">🖨️ Cetak Form</a>
                        <?php if ($user_role != 'Viewer'): ?>
                        <button class="btn-action btn-edit" onclick='editData(<?= json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                        <a href="hrd_interview.php?hapus=<?= $r['id'] ?>" class="btn-action btn-del" onclick="return confirm('Yakin hapus data laporan ini?')">Hapus</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL FORM -->
<div id="modalLaporan" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">➕ Tambah Laporan Interview</h3>
            <button class="close-btn" onclick="closeModal('modalLaporan')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="id" id="lap_id">
            <div class="modal-body">
                
                <div class="section-title">1. DATA UMUM & JADWAL</div>
                <div class="form-grid">
                    <div class="form-group"><label>Nama Kandidat</label><input type="text" name="nama_kandidat" id="nama_kandidat" required></div>
                    <div class="form-group"><label>Tgl Interview</label><input type="date" name="tgl_interview" id="tgl_interview" required value="<?= date('Y-m-d') ?>"></div>
                    <div class="form-group"><label>Posisi Dilamar</label><input type="text" name="posisi_dilamar" id="posisi_dilamar" required></div>
                    <div class="form-group"><label>Pewawancara (HRD)</label><input type="text" name="pewawancara" id="pewawancara" required></div>
                </div>

                <div class="section-title">2. PROFIL & HASIL TES</div>
                <div class="form-grid">
                    <div class="form-group"><label>Pendidikan Terakhir</label><input type="text" name="pendidikan" id="pendidikan"></div>
                    <div class="form-group"><label>Total Pengalaman</label><input type="text" name="total_pengalaman" id="total_pengalaman" placeholder="Contoh: 3.5 Tahun"></div>
                    <div class="form-group"><label>Perusahaan Terakhir</label><input type="text" name="perusahaan_terakhir" id="perusahaan_terakhir"></div>
                    <div class="form-group"><label>Jabatan Terakhir</label><input type="text" name="jabatan_terakhir" id="jabatan_terakhir"></div>
                    <div class="form-group"><label>Alat Tes IQ</label>
                        <select name="alat_tes" id="alat_tes">
                            <option value="">-- Pilih --</option> <option value="CFIT">CFIT</option> <option value="IST">IST</option> <option value="SPM">SPM</option> <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Skor IQ</label><input type="text" name="skor_iq" id="skor_iq"></div>
                    <div class="form-group"><label>Kategori IQ</label>
                        <select name="kategori_iq" id="kategori_iq">
                            <option value="">-- Pilih --</option>
                            <option value=">120 (Superior)">>120 (Superior)</option>
                            <option value="110-119 (Rata-rata Atas)">110-119 (Rata-rata Atas)</option>
                            <option value="90-109 (Rata-rata)">90-109 (Rata-rata)</option>
                            <option value="<90 (Rata-rata Bawah)"><90 (Rata-rata Bawah)</option>
                        </select>
                    </div>
                </div>

                <div class="section-title">3. PENILAIAN KOMPETENSI (1-5)</div>
                <div class="form-grid full">
                    <div class="skor-grid">
                        <div class="form-group"><label>Skor Teknis</label><input type="number" min="1" max="5" name="skor_teknis" id="skor_teknis" oninput="limitSkor(this)"></div>
                        <div class="form-group"><label>Catatan Teknis (Hard Skill)</label><input type="text" name="cat_teknis" id="cat_teknis"></div>
                    </div>
                    <div class="skor-grid">
                        <div class="form-group"><label>Skor Komun.</label><input type="number" min="1" max="5" name="skor_komunikasi" id="skor_komunikasi" oninput="limitSkor(this)"></div>
                        <div class="form-group"><label>Catatan Komunikasi</label><input type="text" name="cat_komunikasi" id="cat_komunikasi"></div>
                    </div>
                    <div class="skor-grid">
                        <div class="form-group"><label>Skor Sikap</label><input type="number" min="1" max="5" name="skor_sikap" id="skor_sikap" oninput="limitSkor(this)"></div>
                        <div class="form-group"><label>Catatan Sikap (Attitude)</label><input type="text" name="cat_sikap" id="cat_sikap"></div>
                    </div>
                    <div class="skor-grid">
                        <div class="form-group"><label>Skor Budaya</label><input type="number" min="1" max="5" name="skor_budaya" id="skor_budaya" oninput="limitSkor(this)"></div>
                        <div class="form-group"><label>Catatan Kesesuaian Budaya</label><input type="text" name="cat_budaya" id="cat_budaya"></div>
                    </div>
                    <div class="skor-grid">
                        <div class="form-group"><label>Skor Prob.</label><input type="number" min="1" max="5" name="skor_problem" id="skor_problem" oninput="limitSkor(this)"></div>
                        <div class="form-group"><label>Catatan Problem Solving</label><input type="text" name="cat_problem" id="cat_problem"></div>
                    </div>
                </div>

                <div class="section-title">4. KOMPENSASI & KETERSEDIAAN</div>
                <div class="form-grid">
                    <div class="form-group"><label>Gaji Terakhir (Rp)</label><input type="text" name="gaji_terakhir" id="gaji_terakhir"></div>
                    <div class="form-group"><label>Ekspektasi Gaji (Rp)</label><input type="text" name="gaji_ekspektasi" id="gaji_ekspektasi"></div>
                    <div class="form-group"><label>Notice Period</label><input type="text" name="notice_period" id="notice_period" placeholder="Contoh: 1 Bulan / Segera"></div>
                    <div class="form-group"><label>Tgl Siap Kerja</label><input type="date" name="tgl_siap" id="tgl_siap"></div>
                </div>

                <div class="section-title">5. KEUNGGULAN & RED FLAGS</div>
                <div class="form-grid">
                    <div class="form-group"><label>Keunggulan Utama</label><textarea name="keunggulan" id="keunggulan"></textarea></div>
                    <div class="form-group"><label>Potensi Risiko / Catatan</label><textarea name="risiko" id="risiko"></textarea></div>
                </div>

                <div class="section-title">6. REKOMENDASI AKHIR HRD</div>
                <div class="form-grid">
                    <div class="form-group"><label>Rekomendasi</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="rekomendasi" value="REKOMENDASI (Lanjut User)" required>
                                <span class="radio-label rek-lanjut">✅ REKOMENDASI (Lanjut User)</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="rekomendasi" value="CADANGAN / PERTIMBANGAN" required>
                                <span class="radio-label rek-cadangan">⚠️ CADANGAN / PERTIMBANGAN</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="rekomendasi" value="TIDAK DIREKOMENDASIKAN" required>
                                <span class="radio-label rek-tidak">❌ TIDAK DIREKOMENDASIKAN</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group"><label>Alasan Rekomendasi</label><textarea name="alasan_rekomendasi" id="alasan_rekomendasi" style="height:100%;" required></textarea></div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-del" style="padding:10px 20px;" onclick="closeModal('modalLaporan')">Batal</button>
                <button type="submit" name="simpan" class="btn-action btn-tambah" style="margin:0;">💾 Simpan Laporan</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script>
    $(document).ready(function() {
        $('#tblLaporan').DataTable({ "order": [[0, "desc"]], "pageLength": 10 });

        // Auto kalkulasi Kategori IQ
        $('#skor_iq').on('input', function() {
            let val = parseInt($(this).val());
            if (isNaN(val)) {
                $('#kategori_iq').val('');
                return;
            }
            if (val >= 120) $('#kategori_iq').val('>120 (Superior)');
            else if (val >= 110) $('#kategori_iq').val('110-119 (Rata-rata Atas)');
            else if (val >= 90) $('#kategori_iq').val('90-109 (Rata-rata)');
            else $('#kategori_iq').val('<90 (Rata-rata Bawah)');
        });
    });

    function limitSkor(el) {
        if(el.value > 5) el.value = 5;
        if(el.value < 1 && el.value !== '') el.value = 1;
    }

    function openModal(id) {
        document.getElementById('lap_id').value = '';
        document.querySelector('#modalLaporan form').reset();
        document.getElementById('modalTitle').innerText = '➕ Tambah Laporan Interview';
        document.getElementById(id).style.display = 'flex';
    }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    
    function editData(data) {
        document.getElementById('modalTitle').innerText = '✏️ Edit Laporan Interview';
        Object.keys(data).forEach(key => {
            let el = document.getElementById(key);
            if(el) {
                el.value = data[key];
            } else {
                let radios = document.querySelectorAll(`input[name="${key}"]`);
                if(radios.length > 0) {
                    radios.forEach(r => { if(r.value === data[key]) r.checked = true; });
                }
            }
        });
        document.getElementById('lap_id').value = data.id;
        document.getElementById('modalLaporan').style.display = 'flex';
    }
</script>
<?php require_once 'footer.php'; ?>
