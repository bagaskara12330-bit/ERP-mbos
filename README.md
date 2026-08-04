MBOS (Manufacturing Business Operating System)
Sistem ERP Internal untuk Pabrik Manufaktur Kemasan Karton (Corrugated Packaging)

🏭 Tentang Proyek
MBOS (Manufacturing Business Operating System) adalah sebuah sistem ERP (Enterprise Resource Planning) berbasis web yang dirancang khusus untuk mendigitalisasi operasional lantai produksi pada pabrik manufaktur karton (corrugated carton).

Sistem ini dibangun untuk menggantikan pencatatan kertas manual, mencegah hilangnya data (data loss), dan mengatasi masalah umum di pabrik seperti kesalahan ketik (typo) dari operator mesin. MBOS menyediakan dashboard real-time bagi Top Management untuk melacak bottleneck produksi dan mengambil keputusan berbasis data.

(Catatan: Repositori ini adalah versi portofolio. Beberapa fitur spesifik bisnis dan data rahasia perusahaan telah disensor/dihapus untuk alasan kerahasiaan NDA).

✨ Fitur Utama
📊 Real-time Production Dashboard: Visualisasi data menggunakan Chart.js untuk memantau performa mesin (Corrugator & Flexo) dan memecah downtime menjadi metrik yang mudah dipahami.
⚙️ Smart Downtime Tracking: Form input cerdas menggunakan teknologi Datalist yang menghilangkan 100% typo operator, memastikan integritas data downtime (seperti Putus Kertas, Tunggu Boiler, dll) tetap solid.
📦 Work-in-Progress (WIP) Management: Sistem serah-terima (handover) material secara digital dari mesin pemotong (NC Slitter) langsung ke Gudang, mengurangi selisih perhitungan inventaris.
🔍 Quality Control (QC) Module: Digitalisasi pengecekan barang masuk (Kertas Medium Liner, Kraft) untuk menjaga akuntabilitas supplier bahan baku.
🔒 Role-based Authentication: Akses sistem yang dibatasi berdasarkan divisi (Operator, QC, HRD, dan Management).
💻 Teknologi yang Digunakan
Backend: PHP (Native / PDO)
Database: MariaDB (MySQL)
Frontend: HTML5, CSS3, JavaScript (Vanilla)
Library: Chart.js (Untuk Visualisasi Data)
Server: XAMPP / Apache
🚀 Cara Instalasi (Local Development)
Untuk menjalankan proyek ini di komputer lokal Anda, ikuti langkah-langkah berikut:

Prasyarat
XAMPP atau WAMP Server terinstal di komputer.
PHP versi 7.4 atau lebih baru.
Langkah Instalasi
Clone repositori ini:
sh

git clone https://github.com/username-anda/mbos-erp.git
Pindahkan folder hasil clone ke dalam folder htdocs (jika menggunakan XAMPP).
Buka phpMyAdmin (http://localhost/phpmyadmin) dan buat database baru bernama h2_base.
Import file database_dummy.sql (jika tersedia) ke dalam database h2_base.
Sesuaikan kredensial koneksi database di dalam file koneksi.php:
php

$host = 'localhost';
$dbname = 'h2_base';
$username = 'root';
$password = ''; // Kosongkan jika default XAMPP
Buka browser dan akses sistem melalui: http://localhost/mbos-erp
📸 Tangkapan Layar (Screenshots)
(Silakan tambahkan gambar screenshot aplikasi Anda di sini. Gunakan format markdown ![Deskripsi](url-gambar))

Dashboard Utama & Grafik Downtime:
<img width="1270" height="886" alt="Screenshot 2026-08-04 101020" src="https://github.com/user-attachments/assets/7aa03a91-1455-4416-a89f-fc3c35068a6d" />
<img width="1270" height="887" alt="Screenshot 2026-08-04 101108" src="https://github.com/user-attachments/assets/0d8b9e46-a706-4030-8e5d-ae3de60ddf6e" />
Form Input Anti-Typo:
<img width="1270" height="884" alt="Screenshot 2026-08-04 101241" src="https://github.com/user-attachments/assets/76488bfc-d38f-4859-b8ce-a6cf7c7f9ac3" />
Form Input:
<img width="1270" height="885" alt="Screenshot 2026-08-04 101301" src="https://github.com/user-attachments/assets/b93c9bc2-3435-4589-a2a6-888e06d95f90" />

🤝 Kontak & Profil
[PRASETYA BAGASKARA] - Full Stack Developer / System Analyst
LinkedIn: https://www.linkedin.com/in/prasetya-bagaskara-5672771ba/
Email: 
bagaskara12330@gmail.com
