<?php
session_start();
require_once '../../Config/Database.php';

// Pastikan yang login adalah admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Login/Login.php");
    exit;
}

// Hitung statistik aktual dari database
$total_orders     = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM orders"));
$order_baru       = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM orders WHERE status IN ('pilih_template','konsultasi','data_terkirim')"));
$order_proses     = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM orders WHERE status IN ('diproses','undangan_jadi')"));
$order_selesai    = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM orders WHERE status IN ('dikirim','selesai')"));
$total_customers  = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE role = 'customer'"));
$total_templates  = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM templates"));

$rev_q = mysqli_query($conn, "SELECT SUM(total_price) as total_rev FROM orders WHERE payment_status = 'lunas'");
$rev_row = mysqli_fetch_assoc($rev_q);
$total_omset = (float)($rev_row['total_rev'] ?? 0);

// Hitung Rasio Template vs Custom
$count_template = 0;
$count_custom   = 0;
$ratio_q = @mysqli_query($conn, "SELECT type, COUNT(*) as cnt FROM orders GROUP BY type");
if ($ratio_q) {
    while ($r = mysqli_fetch_assoc($ratio_q)) {
        if ($r['type'] === 'custom') {
            $count_custom += (int)$r['cnt'];
        } else {
            $count_template += (int)$r['cnt'];
        }
    }
}
$total_count = $count_template + $count_custom;
$pct_template = $total_count > 0 ? round(($count_template / $total_count) * 100) : 75;
$pct_custom   = $total_count > 0 ? (100 - $pct_template) : 25;

// Rata-rata waktu proses
$est_time = '1.4 Hari (24 - 48 Jam)';
$store_q = @mysqli_query($conn, "SELECT est_process_time FROM store_settings WHERE id = 1");
if ($store_q && $st = mysqli_fetch_assoc($store_q)) {
    if (!empty($st['est_process_time'])) $est_time = $st['est_process_time'];
}

// Fungsi Helper untuk Warna Step yang Berbeda-beda
function getHomeStepBadge($status, $step_index) {
    switch ($status) {
        case 'pilih_template':
            return [
                'label' => 'Step 1: Pilih Template',
                'style' => 'background:#FEF3C7; color:#92400E; border:1px solid #FCD34D;',
                'dot'   => '#F59E0B'
            ];
        case 'konsultasi':
            return [
                'label' => 'Step 2: Konsultasi Desain',
                'style' => 'background:#E0F2FE; color:#0369A1; border:1px solid #BAE6FD;',
                'dot'   => '#0EA5E9'
            ];
        case 'data_terkirim':
            return [
                'label' => 'Step 3: Data Acara Terkirim',
                'style' => 'background:#F3E8FF; color:#6B21A8; border:1px solid #E9D5FF;',
                'dot'   => '#A855F7'
            ];
        case 'diproses':
            return [
                'label' => 'Step 4: Pengerjaan Desain',
                'style' => 'background:#DBEAFE; color:#1E40AF; border:1px solid #BFDBFE;',
                'dot'   => '#3B82F6'
            ];
        case 'undangan_jadi':
            return [
                'label' => 'Step 5: Review & Revisi',
                'style' => 'background:#CCFBF1; color:#0F766E; border:1px solid #99F6E4;',
                'dot'   => '#14B8A6'
            ];
        case 'dikirim':
        case 'selesai':
            return [
                'label' => 'Step 6: Selesai / Siap Sebar',
                'style' => 'background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0;',
                'dot'   => '#10B981'
            ];
        case 'dibatalkan':
            return [
                'label' => 'Dibatalkan',
                'style' => 'background:#FFE4E6; color:#9F1239; border:1px solid #FECDD3;',
                'dot'   => '#F43F5E'
            ];
        default:
            return [
                'label' => 'Tahap ' . ($step_index + 1),
                'style' => 'background:#F3F4F6; color:#374151; border:1px solid #E5E7EB;',
                'dot'   => '#6B7280'
            ];
    }
}

$recent_orders = mysqli_query($conn, "SELECT * FROM orders ORDER BY id DESC LIMIT 5");
$recent_reviews = mysqli_query($conn, "SELECT * FROM reviews ORDER BY id DESC LIMIT 3");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Dashboard - Admin LilinNawa</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="home.css">
</head>
<body class="admin-wrapper">
    <?php include '../sidebar.php'; ?>

    <main class="admin-main">
      
        <div class="welcome-header">
            <div>
                <h2>Selamat datang, <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?> 👋</h2>
                <p>Pantau seluruh siklus pengerjaan pesanan undangan digital, omset usaha, dan layanan pelanggan LilinNawa.</p>
            </div>
            <div class="quick-header-actions">
                <a href="Order.php" class="btn-admin btn-primary">📦 Kelola Order</a>
                <a href="../INVOICE_A/Invoice.php" class="btn-admin btn-secondary">🧾 Faktur Invoice</a>
                <a href="../COMMUNICATION_A/Inbox.php" class="btn-admin btn-gold">💬 Chat CS</a>
            </div>
        </div>

        <!-- 4 KPI Summary Cards (Total Pendapatan, Rasio Template/Custom, Waktu Proses, Undangan Terlaris) Sesuai Preview -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
            <!-- 1. Total Pendapatan Usaha -->
            <div class="card-stat" style="border-left: 4px solid #701C2F;">
                <div class="stat-icon-wrap" style="background:#FAF3DE; color:#701C2F;">💰</div>
                <div>
                    <span class="stat-label">Total Pendapatan Usaha</span>
                    <h3 class="stat-value" style="color:#701C2F;">Rp <?= number_format($total_omset, 0, ',', '.') ?></h3>
                    <small class="stat-sub" style="color:#059669; font-weight:600;">↑ Omset akumulasi pesanan</small>
                </div>
            </div>

            <!-- 2. Rasio Template vs Custom -->
            <div class="card-stat" style="border-left: 4px solid #F59E0B;">
                <div class="stat-icon-wrap" style="background:#FEF3C7; color:#B45309;">📊</div>
                <div>
                    <span class="stat-label">Rasio Template / Custom</span>
                    <h3 class="stat-value"><?= $pct_template ?>% / <?= $pct_custom ?>%</h3>
                    <small class="stat-sub"><?= $count_template ?> Template &bull; <?= $count_custom ?> Custom</small>
                </div>
            </div>

            <!-- 3. Rata-rata Waktu Proses -->
            <div class="card-stat" style="border-left: 4px solid #10B981;">
                <div class="stat-icon-wrap" style="background:#D1FAE5; color:#065F46;">⚡</div>
                <div>
                    <span class="stat-label">Rata-rata Waktu Proses</span>
                    <h3 class="stat-value" style="color:#701C2F; font-size:14px;"><?= htmlspecialchars($est_time) ?></h3>
                    <small class="stat-sub" style="color:#059669; font-weight:600;">Target selesai &lt; 48 jam</small>
                </div>
            </div>

        </div>

        <!-- Tabel 5 Pesanan Masuk Terkini dengan Warna Badge Berbeda-beda Tiap Step -->
        <div class="table-card">
            <div class="table-header-box">
                <div>
                    <h3>Pesanan Terbaru Masuk</h3>
                    <p style="font-size:12px; color:#6B7280;">Daftar 5 pesanan terakhir yang masuk ke sistem LilinNawa.</p>
                </div>
                <a href="Order.php" class="btn-admin btn-secondary" style="font-size:11px;">Lihat Semua Order →</a>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Pelanggan</th>
                            <th>Template / Layanan</th>
                            <th>Tahapan Status (Beda Warna)</th>
                            <th>Pembayaran</th>
                            <th>Total Biaya</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($recent_orders) === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:28px; color:#9CA3AF;">Belum ada pesanan masuk. Silakan buat pesanan percobaan dari portal klien.</td>
                        </tr>
                        <?php else: ?>
                        <?php while($o = mysqli_fetch_assoc($recent_orders)): ?>
                        <?php 
                            $badge = getHomeStepBadge($o['status'], (int)$o['step_index']);
                        ?>
                        <tr>
                            <td><strong style="color:#701C2F; font-size:12px;"><?= $o['order_number'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($o['customer_name']) ?></strong><br>
                                <small style="color:#6B7280;"><?= htmlspecialchars($o['customer_phone']) ?></small>
                            </td>
                            <td>
                                <span><?= htmlspecialchars($o['template_name'] ?: 'Custom Bespoke') ?></span>
                                <span style="display:block; font-size:10px; color:#9CA3AF; text-transform:uppercase;"><?= htmlspecialchars($o['type']) ?></span>
                            </td>
                            <td>
                                <span style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:8px; font-size:11px; font-weight:700; <?= $badge['style'] ?>">
                                    <span style="width:6px; height:6px; border-radius:50%; background:<?= $badge['dot'] ?>;"></span>
                                    <span><?= $badge['label'] ?></span>
                                </span>
                            </td>
                            <td>
                                <?php if($o['payment_status'] === 'lunas'): ?>
                                    <span class="admin-badge admin-badge-success">✓ Lunas</span>
                                <?php elseif($o['payment_status'] === 'pending'): ?>
                                    <span class="admin-badge admin-badge-warning">⏳ Pending</span>
                                <?php else: ?>
                                    <span class="admin-badge admin-badge-danger"><?= htmlspecialchars($o['payment_status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><strong>Rp <?= number_format($o['total_price'], 0, ',', '.') ?></strong></td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a href="Order.php?search=<?= urlencode($o['order_number']) ?>" class="btn-admin btn-primary" style="padding:4px 10px; font-size:11px;">Kelola</a>
                                    <a href="../INVOICE_A/Invoice.php?order=<?= urlencode($o['order_number']) ?>" class="btn-admin btn-secondary" style="padding:4px 8px; font-size:11px;" title="Lihat Invoice">🧾</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ulasan Masuk Terkini -->
        <div class="table-card">
            <div class="table-header-box">
                <div>
                    <h3>Ulasan &amp; Rating Terbaru</h3>
                    <p style="font-size:12px; color:#6B7280;">Testimoni yang baru diberikan oleh pasangan pengantin.</p>
                </div>
                <a href="Ulasan.php" class="btn-admin btn-secondary" style="font-size:11px;">Buka Semua Ulasan →</a>
            </div>

            <div class="reviews-grid-mini">
                <?php if (mysqli_num_rows($recent_reviews) === 0): ?>
                    <p style="color:#9CA3AF; font-size:12px; padding:12px;">Belum ada ulasan masuk.</p>
                <?php else: ?>
                    <?php while($r = mysqli_fetch_assoc($recent_reviews)): ?>
                    <div class="mini-review-card">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                            <strong><?= htmlspecialchars($r['customer_name']) ?></strong>
                            <span style="color:#F59E0B; font-weight:bold; font-size:13px;"><?= str_repeat('★', (int)$r['rating']) ?></span>
                        </div>
                        <div style="font-size:11px; color:#6B7280; margin-bottom:8px;"><?= htmlspecialchars($r['order_number'] ?? '#LN') ?> • <?= htmlspecialchars($r['template_name'] ?? 'Undangan') ?></div>
                        <p style="font-size:12px; color:#374151; font-style:italic;">"<?= htmlspecialchars($r['comment']) ?>"</p>
                    </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin.js"></script>
</body>
</html>
