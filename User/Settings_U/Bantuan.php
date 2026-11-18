<?php
// User/Settings_U/Bantuan.php - Pusat Bantuan & FAQ Pelanggan LN Digital
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../Login/Login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Bantuan &amp; FAQ - LN Digital</title>
    
    <link rel="stylesheet" href="../sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="bantuan.css?v=<?= time() ?>">
</head>
<body class="user-layout">
    <?php include '../sidebar.php'; ?>

    <main class="user-main-content">
        <div class="help-header">
            <h1>❓ Pusat Bantuan &amp; FAQ</h1>
            <p>Pertanyaan umum seputar pemesanan, kustomisasi desain, dan panduan membagikan undangan digital LilinNawa.</p>
        </div>

        <div class="faq-container">
            <div class="faq-card">
                <h3>1. Berapa lama proses pengerjaan undangan digital?</h3>
                <p>Setelah Anda mengisi rincian data acara dan konfirmasi pembayaran, pengerjaan undangan umumnya memakan waktu <strong>1x24 jam kerja</strong> (Maksimal 2 hari kerja untuk desain custom).</p>
            </div>

            <div class="faq-card">
                <h3>2. Apakah bisa mengajukan revisi nama atau tanggal?</h3>
                <p>Tentu bisa! Anda mendapatkan kesempatan <strong>revisi minor tanpa batas</strong> untuk perbaikan typo nama, perubahan jam akad/resepsi, dan pembaruan link Google Maps lokasi.</p>
            </div>

            <div class="faq-card">
                <h3>3. Bagaimana cara membagikan undangan ke tamu WhatsApp?</h3>
                <p>Setiap pesanan yang berstatus <em>Undangan Jadi</em> atau <em>Dikirim</em> memiliki tautan unik. Anda cukup menyalin tautan dan menambahkan parameter nama tamu (misalnya <code>?to=Bapak+Ahmad</code>) agar nama tamu muncul eksklusif di amplop depan.</p>
            </div>

            <div class="faq-card">
                <h3>4. Butuh bantuan khusus atau konsultasi mendesak?</h3>
                <p>Tim Customer Support kami siap mendampingi Anda setiap hari (08:00 - 22:00 WIB). Klik menu <strong>Inbox CS</strong> pada menu samping atau hubungi WhatsApp resmi kami.</p>
                <div style="margin-top: 14px;">
                    <a href="../Communication_U/Inbox CS.php" style="background:#721B2F; color:#FFFFFF; padding:9px 18px; border-radius:10px; text-decoration:none; font-size:13px; font-weight:700; display:inline-block;">
                        💬 Buka Chat CS Sekarang
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
