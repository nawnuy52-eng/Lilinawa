<?php
// Admin/SETTINGS_A/Help.php - Pusat Bantuan & Dokumentasi Admin LilinNawa
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Login/Login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bantuan &amp; Panduan - Admin LilinNawa</title>
    <link rel="stylesheet" href="../sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-wrapper">
    <?php include '../sidebar.php'; ?>

    <main class="admin-main">
        <div class="page-title-box">
            <h2>Pusat Bantuan &amp; Panduan Admin</h2>
            <p>Petunjuk praktis pengelolaan template, verifikasi pesanan, room chat CS, dan pengaturan sistem LilinNawa.</p>
        </div>

        <div style="display:flex; flex-direction:column; gap:16px; max-width:960px;">
            <!-- Panduan 1: Template Status -->
            <div class="table-card">
                <h3 style="font-family:'Playfair Display',serif; font-size:17px; color:#701C2F; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                    <span>✨</span> 1. Fungsi Aktif &amp; Nonaktif Katalog Template
                </h3>
                <p style="font-size:13px; color:#4B5563; line-height:1.6; margin-bottom:10px;">
                    Di menu <strong>Katalog Template (MAIN_A/Template.php)</strong>, setiap desain undangan memiliki kontrol visibilitas:
                </p>
                <ul style="font-size:12px; color:#4B5563; line-height:1.8; padding-left:20px;">
                    <li><strong>Aktif (Tayang Publik):</strong> Template langsung dapat dipilih dan dilihat oleh pelanggan di katalog beranda utama.</li>
                    <li><strong>Nonaktif (Draft / Arsip):</strong> Template disembunyikan sementara tanpa menghapus riwayat pesanan yang sudah ada. Sangat berguna saat desain sedang direvisi atau stok promo habis.</li>
                </ul>
            </div>

            <!-- Panduan 2: 6 Siklus Tahapan Pesanan -->
            <div class="table-card">
                <h3 style="font-family:'Playfair Display',serif; font-size:17px; color:#701C2F; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                    <span>📦</span> 2. Siklus 6 Langkah Pengerjaan Pesanan Undangan
                </h3>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:12px; font-size:12px;">
                    <div style="background:#FAF3DE; padding:12px; border-radius:8px; border:1px solid #E5D7B7;">
                        <strong style="color:#701C2F;">1. Pilih Template</strong>
                        <p style="color:#6B7280; margin-top:4px;">Pelanggan memilih desain tema akad &amp; resepsi di web.</p>
                    </div>
                    <div style="background:#FAF3DE; padding:12px; border-radius:8px; border:1px solid #E5D7B7;">
                        <strong style="color:#701C2F;">2. Konsultasi CS</strong>
                        <p style="color:#6B7280; margin-top:4px;">Diskusi detail paket, warna tema, lagu, dan fitur khusus.</p>
                    </div>
                    <div style="background:#FAF3DE; padding:12px; border-radius:8px; border:1px solid #E5D7B7;">
                        <strong style="color:#701C2F;">3. Kirim Data Acara</strong>
                        <p style="color:#6B7280; margin-top:4px;">Pelanggan mengirim nama mempelai, tanggal, lokasi map, dan foto.</p>
                    </div>
                    <div style="background:#FAF3DE; padding:12px; border-radius:8px; border:1px solid #E5D7B7;">
                        <strong style="color:#701C2F;">4. Sedang Diproses</strong>
                        <p style="color:#6B7280; margin-top:4px;">Admin tim desain menyusun HTML, audio, galeri, &amp; RSVP.</p>
                    </div>
                    <div style="background:#FAF3DE; padding:12px; border-radius:8px; border:1px solid #E5D7B7;">
                        <strong style="color:#701C2F;">5. Web Preview Live</strong>
                        <p style="color:#6B7280; margin-top:4px;">Link website dikirim ke pemesan untuk direview &amp; revisi.</p>
                    </div>
                    <div style="background:#FAF3DE; padding:12px; border-radius:8px; border:1px solid #E5D7B7;">
                        <strong style="color:#701C2F;">6. Selesai Kirim</strong>
                        <p style="color:#6B7280; margin-top:4px;">Undangan final siap disebar via link WhatsApp ke tamu.</p>
                    </div>
                </div>
            </div>

            <!-- Panduan 3: Verifikasi Pembayaran & Ulasan -->
            <div class="table-card">
                <h3 style="font-family:'Playfair Display',serif; font-size:17px; color:#701C2F; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                    <span>💳</span> 3. Verifikasi Pembayaran &amp; Moderasi Ulasan
                </h3>
                <p style="font-size:13px; color:#4B5563; line-height:1.6;">
                    - Cek bukti transfer pelanggan pada tabel pesanan. Jika dana sudah masuk ke rekening BCA, ubah status pembayaran menjadi <strong>Lunas</strong>.<br>
                    - Ulasan pelanggan yang baru masuk akan ditandai dengan dot kuning di sidebar. Buka menu <strong>Ulasan Pelanggan</strong> untuk memberikan balasan resmi admin.
                </p>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin.js"></script>
</body>
</html>