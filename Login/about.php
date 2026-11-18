<?php
// Login/about.php - Profil Usaha Resmi LilinNawa
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - LilinNawa Invitations</title>
    <!-- CSS File Sendiri Khusus About -->
    <link rel="stylesheet" href="about.css">
</head>
<body class="about-body">
    <div class="about-container">
        <!-- Header Navigasi -->
        <header class="about-header">
            <div class="brand-info">
                <span class="brand-badge">LilinNawa</span>
                <h1>Tentang LilinNawa</h1>
                <p>Platform &amp; Jasa Pembuatan Undangan Digital Interaktif Modern di Indonesia</p>
            </div>
        </header>

        <!-- Profil Owner & Pendiri Usaha -->
        <section class="profile-card">
            <div class="profile-badge">Super Admin &amp; Manajemen</div>
            <div class="profile-content">
                <div class="avatar-box">
                    <img src="../PV/Logo%20LN.png" alt="LilinNawa Logo">
                </div>
                <div class="profile-text">
                    <h2>Admin LilinNawa</h2>
                    <p class="role-title">Super Administrator &amp; Manajemen Operasional LilinNawa</p>
                    <p class="bio">
                        "LilinNawa hadir untuk memberikan solusi undangan pernikahan dan acara spesial yang tidak hanya elegan dan memukau, namun juga memudahkan proses berbagi momen istimewa bersama keluarga, sahabat, dan kerabat di seluruh penjuru dunia dengan sentuhan teknologi modern dan harga yang sangat bersahabat."
                    </p>
                    <div class="owner-contact">
                        <span>📱 WhatsApp: <strong>081252200386</strong></span>
                        <span>✉️ Email: <strong>admin@lilinawa.com</strong></span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Nilai & Keunggulan Layanan -->
        <section class="services-grid">
            <div class="service-card">
                <div class="service-icon">✨</div>
                <h3>Desain Mewah &amp; Responsif</h3>
                <p>Setiap tema undangan dirancang eksklusif, responsif di semua perangkat smartphone, tablet, maupun laptop dengan animasi yang mulus.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">⚡</div>
                <h3>Pengerjaan Cepat &amp; Tepat</h3>
                <p>Proses kilat 1-2 hari kerja setelah data acara lengkap. Dilengkapi sistem pemantauan status pengerjaan (order tracking) secara realtime.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">📍</div>
                <h3>Peta Google Maps Presisi</h3>
                <p>Navigasi lokasi acara langsung terhubung ke Google Maps sehingga tamu undangan dapat menemukan venue acara dengan sangat mudah.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">💌</div>
                <h3>RSVP &amp; Buku Tamu WhatsApp</h3>
                <p>Konfirmasi kehadiran otomatis masuk ke WhatsApp penyelenggara dan tertera di buku tamu digital undangan.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">🎵</div>
                <h3>Musik Latar &amp; Amplop Digital</h3>
                <p>Bisa memilih lagu favorit latar belakang serta menerima amplop digital aman melalui QRIS dan rekening bank langsung.</p>
            </div>
            <div class="service-card">
                <div class="service-icon">🤝</div>
                <h3>Konsultasi Ramah &amp; Garansi Revisi</h3>
                <p>Tim Customer Service dan Desainer LilinNawa siap membantu mendampingi penyesuaian detail acara hingga Anda puas.</p>
            </div>
        </section>

        <!-- Info Alamat & Operasional -->
        <section class="info-section">
            <div class="info-box">
                <h3>📍 Kantor Pusat LilinNawa</h3>
                <p>Jalan Timun Mas No. 15, Kota Sidoarjo, Jawa Timur</p>
                <p>Jam Layanan: Setiap Hari (08.00 - 21.00 WIB)</p>
            </div>
            <div class="info-cta">
                <h3>Siap Mewujudkan Undangan Impian Anda?</h3>
                <p>Pilih template favorit Anda sekarang atau konsultasikan konsep undangan custom bersama kami.</p>
                <div class="cta-buttons">
                    <a href="Register.php" class="btn-primary">Masuk ke Portal Akun</a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="about-footer">
            <p>&copy; <?= date('Y'); ?> <strong>LilinNawa Invitations</strong>. Hak Cipta Dilindungi Undang-Undang.</p>
            <p><small>Dikembangkan untuk operasional bisnis resmi LilinNawa.</small></p>
        </footer>
    </div>
</body>
</html>