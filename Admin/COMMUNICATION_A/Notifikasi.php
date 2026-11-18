<?php
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Login/Login.php");
    exit;
}

// Ambil event pesanan terkini untuk dijadikan daftar notifikasi otomatis
$latest_orders = mysqli_query($conn, "SELECT * FROM orders ORDER BY id DESC LIMIT 10");
$latest_reviews = mysqli_query($conn, "SELECT * FROM reviews ORDER BY id DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Notifikasi Sistem - Admin LilinNawa</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-wrapper">
    <?php include '../sidebar.php'; ?>

    <main class="admin-main">
       
        <div class="page-title-box">
            <h2>Notifikasi &amp; Log Aktivitas Sistem</h2>
            <p>Pemberitahuan real-time tentang pesanan baru, revisi data pengantin, dan ulasan yang masuk.</p>
        </div>

        <div class="table-card">
            <h3 style="font-family:'Playfair Display',serif; font-size:18px; color:#701C2F; margin-bottom:16px;">Log Aktivitas Pesanan</h3>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php while($o = mysqli_fetch_assoc($latest_orders)): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#FAF3DE; border:1px solid #E2D3AF; border-radius:12px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span style="font-size:20px;">🔔</span>
                        <div>
                            <strong style="color:#701C2F; font-size:13px;">Pesanan Baru Dibuat: <?= $o['order_number'] ?></strong>
                            <p style="font-size:12px; color:#4A101E; margin-top:2px;">
                                <?= htmlspecialchars($o['customer_name']) ?> memilih template <?= htmlspecialchars($o['template_name']) ?> (Rp <?= number_format($o['total_price'], 0, ',', '.') ?>)
                            </p>
                        </div>
                    </div>
                    <span class="admin-badge admin-badge-maroon"><?= htmlspecialchars($o['status']) ?></span>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <div class="table-card">
            <h3 style="font-family:'Playfair Display',serif; font-size:18px; color:#701C2F; margin-bottom:16px;">Log Ulasan Masuk</h3>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php while($rev = mysqli_fetch_assoc($latest_reviews)): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#FFFFFF; border:1px solid #E5E7EB; border-radius:12px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span style="font-size:20px;">⭐</span>
                        <div>
                            <strong style="font-size:13px; color:#1F2937;">Ulasan dari <?= htmlspecialchars($rev['customer_name']) ?></strong>
                            <p style="font-size:12px; color:#6B7280; margin-top:2px;">
                                Rating: <?= $rev['rating'] ?>/5 • "<?= htmlspecialchars($rev['comment']) ?>"
                            </p>
                        </div>
                    </div>
                    <a href="../MAIN_A/Ulasan.php" class="btn-admin btn-secondary" style="font-size:11px;">Buka Ulasan</a>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin.js"></script>
</body>
</html>