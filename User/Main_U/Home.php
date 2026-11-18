<?php
// User/Main_U/Home.php - Beranda Dashboard Pelanggan LilinNawa
// Tampilan Mewah & Selaras Sesuai Admin Dashboard
session_start();
require_once '../../Config/Database.php';

// Wajib login. Jangan membuat sesi/akun demo otomatis.
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'customer')) {
    header("Location: ../../Login/Login.php");
    exit;
}

$user_id    = (int) $_SESSION['user_id'];
$user_phone = (string) ($_SESSION['phone'] ?? '');
$user_name  = (string) ($_SESSION['name'] ?? 'Pelanggan');
$user_email = (string) ($_SESSION['email'] ?? '');

$all_user_orders = [];

// Ambil hanya pesanan yang terhubung ke ID akun yang sedang login.
// Tidak ada data pesanan demo/fallback: akun baru akan melihat 0 pesanan.
if (isset($conn) && $conn instanceof mysqli) {
    $sql = "SELECT o.*, t.image_url AS template_image
            FROM orders o
            LEFT JOIN templates t ON o.template_id = t.id
            WHERE o.user_id = ?
            ORDER BY o.id DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $orders_list = mysqli_stmt_get_result($stmt);
            if ($orders_list) {
                while ($row = mysqli_fetch_assoc($orders_list)) {
                    $all_user_orders[] = $row;
                }
            }
        }
        mysqli_stmt_close($stmt);
    }
}

$total_orders = count($all_user_orders);
$active_order = $all_user_orders[0] ?? null;

// Hitung order selesai vs proses
$completed_orders = 0;
$process_orders = 0;
foreach ($all_user_orders as $ord) {
    if ($ord['status'] === 'dikirim' || (int)($ord['step_index'] ?? 0) >= 5) {
        $completed_orders++;
    } else {
        $process_orders++;
    }
}

// Hitung pesan unread dari CS
$unread_chat = 0;
if (!empty($conn)) {
    try {
        $unread_chat_q = @mysqli_query($conn, "SELECT COUNT(*) as unread FROM inbox WHERE room_id = 'room-$user_phone' AND sender_role = 'admin' AND is_read = 0");
        if ($unread_chat_q) {
            $row_chat = mysqli_fetch_assoc($unread_chat_q);
            $unread_chat = (int)($row_chat['unread'] ?? 0);
        }
    } catch (Throwable $e) {
        $unread_chat = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda Klien - LilinNawa</title>
    <!-- Stylesheets with Cache Buster -->
    <link rel="stylesheet" href="../sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="home.css?v=<?= time() ?>">
    <style>
        /* Core Inlined Luxury Styles for User Home.php */
        body.user-layout {
            margin: 0;
            padding: 0;
            background-color: #FAF4E4 !important;
            display: flex;
            min-height: 100vh;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            color: #1F2937;
        }
        .user-main-content {
            margin-left: 290px !important;
            flex: 1 !important;
            padding: 28px 36px !important;
            max-width: 1400px !important;
            min-height: 100vh !important;
            background-color: #FAF4E4 !important;
            box-sizing: border-box !important;
        }
        @media (max-width: 900px) {
            .user-main-content {
                margin-left: 70px !important;
                padding: 16px !important;
            }
        }
        .folder-path {
            font-family: monospace;
            font-size: 11px;
            color: #721B2F;
            background: #FFFFFF;
            border: 1px solid #E2D4B0;
            padding: 6px 14px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 18px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            font-weight: 700;
        }
        .welcome-header {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 20px;
            padding: 24px 28px;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .welcome-header h2 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 26px;
            color: #721B2F;
            font-weight: 800;
            margin: 0;
        }
        .welcome-header p {
            font-size: 13px;
            color: #6B7280;
            margin-top: 4px;
        }
        .welcome-banner {
            position: relative !important;
            width: 100% !important;
            height: 220px !important;
            border-radius: 20px !important;
            overflow: hidden !important;
            margin-bottom: 24px !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1) !important;
        }
        .welcome-banner img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            object-position: center !important;
            display: block !important;
        }
        .banner-overlay {
            position: absolute !important;
            inset: 0 !important;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.25) 60%, transparent 100%) !important;
            display: flex !important;
            align-items: flex-end !important;
            padding: 24px !important;
        }
        .banner-badge {
            background: #D4AF37 !important;
            color: #111827 !important;
            font-size: 11px !important;
            font-weight: 800 !important;
            padding: 3px 10px !important;
            border-radius: 9999px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            display: inline-block !important;
            margin-bottom: 6px !important;
        }
        .banner-title {
            font-family: 'Playfair Display', Georgia, serif !important;
            font-size: 22px !important;
            color: #FFFFFF !important;
            font-weight: 700 !important;
            margin: 0 !important;
        }
        .banner-sub {
            font-size: 12px !important;
            color: #FDE68A !important;
            margin-top: 4px !important;
        }
        .stats-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)) !important;
            gap: 16px !important;
            margin-bottom: 24px !important;
        }
        .card-stat {
            background: #FFFFFF !important;
            border: 1px solid #E5E7EB !important;
            border-radius: 16px !important;
            padding: 18px 20px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02) !important;
        }
        .card-stat.stat-emerald {
            background: #ECFDF5 !important;
            border: 1.5px solid #A7F3D0 !important;
        }
        .card-stat.stat-amber {
            background: #FFFBEB !important;
            border: 1.5px solid #FDE68A !important;
        }
        .stat-icon-wrap {
            width: 44px !important;
            height: 44px !important;
            border-radius: 12px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 20px !important;
            flex-shrink: 0 !important;
        }
        .stat-label {
            font-size: 11px !important;
            font-weight: 700 !important;
            color: #6B7280 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            display: block !important;
        }
        .stat-value {
            font-size: 24px !important;
            font-weight: 800 !important;
            color: #111827 !important;
            margin: 2px 0 !important;
            display: block !important;
        }
        .stat-sub {
            font-size: 11px !important;
            color: #9CA3AF !important;
        }
        .table-card {
            background: #FFFFFF !important;
            border-radius: 18px !important;
            border: 1px solid #E5E7EB !important;
            padding: 22px !important;
            margin-bottom: 24px !important;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02) !important;
        }
        .table-header-box {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 16px !important;
            flex-wrap: wrap !important;
            gap: 10px !important;
        }
        .table-header-box h3 {
            font-size: 16px !important;
            font-weight: 700 !important;
            color: #111827 !important;
            margin: 0 !important;
        }
        .table-responsive {
            overflow-x: auto !important;
        }
        .user-table {
            width: 100% !important;
            border-collapse: collapse !important;
            font-size: 12px !important;
            text-align: left !important;
        }
        .user-table th {
            background: #FAF3DE !important;
            color: #721B2F !important;
            font-weight: 800 !important;
            padding: 12px 14px !important;
            text-transform: uppercase !important;
            font-size: 10px !important;
            letter-spacing: 0.5px !important;
            border-bottom: 1.5px solid #E2D4B0 !important;
        }
        .user-table td {
            padding: 14px !important;
            border-bottom: 1px solid #F3F4F6 !important;
            vertical-align: middle !important;
        }
        .badge-tag {
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            padding: 3px 10px !important;
            border-radius: 9999px !important;
            font-size: 10px !important;
            font-weight: 800 !important;
        }
        .badge-emerald { background: #D1FAE5 !important; color: #065F46 !important; border: 1px solid #A7F3D0 !important; }
        .badge-amber { background: #FEF3C7 !important; color: #92400E !important; border: 1px solid #FDE68A !important; }
    </style>
</head>
<body class="user-layout">
    <!-- Sidebar User -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Workspace -->
    <main class="user-main-content">
        <!-- Folder Tracker -->
       
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div>
                <h2>Selamat datang, <?= htmlspecialchars($user_name) ?>! 👋</h2>
                <p>Kelola pesanan undangan pernikahan digital, pantau proses pengerjaan desainer, dan hubungi CS LilinNawa dari sini.</p>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="Katalog Template.php" style="background:#FAF3DE; color:#721B2F; border:1px solid #E3D5B4; padding:8px 16px; border-radius:12px; font-weight:700; font-size:12px; text-decoration:none;">🎨 Katalog Template</a>
                <a href="Layanan Custom.php" style="background:#FAF3DE; color:#721B2F; border:1.5px solid #D4AF37; padding:8px 16px; border-radius:12px; font-weight:700; font-size:12px; text-decoration:none;">✨ Layanan Custom</a>
                <a href="../Communication_U/Inbox CS.php" style="background:#721B2F; color:#FFFFFF; padding:8px 16px; border-radius:12px; font-weight:700; font-size:12px; text-decoration:none;">💬 Chat CS</a>
            </div>
        </div>

        <!-- Banner Workspace Pelanggan (Sesuai Layout Admin) -->
        <div class="welcome-banner">
            <img src="https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=1200&q=80" alt="LilinNawa">
            <div class="banner-overlay">
                <div>
                    <span class="banner-badge">Portal Pelanggan Aktif</span>
                    <h3 class="banner-title">Platform Undangan Digital &amp; Custom Eksklusif</h3>
                    <p class="banner-sub">Wujudkan momen sakral pernikahan impian dengan undangan website modern dan elegan.</p>
                </div>
            </div>
        </div>

        <!-- 4 Kartu Statistik (Sesuai Format Admin) -->
        <div class="stats-grid">
            <div class="card-stat">
                <div>
                    <span class="stat-label">Total Pesanan</span>
                    <h3 class="stat-value"><?= $total_orders ?></h3>
                    <small class="stat-sub">Akun <?= htmlspecialchars($user_name) ?></small>
                </div>
                <div class="stat-icon-wrap" style="background:#FAF3DE; color:#721B2F;">📦</div>
            </div>

            <div class="card-stat stat-emerald">
                <div>
                    <span class="stat-label" style="color:#065F46;">✓ Selesai (Jadi)</span>
                    <h3 class="stat-value" style="color:#065F46;"><?= $completed_orders ?></h3>
                    <small class="stat-sub" style="color:#047857;">Link web siap sebar</small>
                </div>
                <div class="stat-icon-wrap" style="background:#D1FAE5; color:#065F46;">✓</div>
            </div>

            <div class="card-stat stat-amber">
                <div>
                    <span class="stat-label" style="color:#92400E;">⏳ Belum Selesai</span>
                    <h3 class="stat-value" style="color:#92400E;"><?= $process_orders ?></h3>
                    <small class="stat-sub" style="color:#B45309;">Perlu proses / review</small>
                </div>
                <div class="stat-icon-wrap" style="background:#FEF3C7; color:#92400E;">⏳</div>
            </div>

            <div class="card-stat">
                <div>
                    <span class="stat-label">Layanan CS</span>
                    <h3 class="stat-value" style="color:#10B981; font-size:20px;">Online</h3>
                    <small class="stat-sub"><?= $unread_chat ?> pesan baru belum dibaca</small>
                </div>
                <div class="stat-icon-wrap" style="background:#F3E8FF; color:#7E22CE;">💬</div>
            </div>
        </div>

        <!-- Tabel Daftar Pesanan Saya (Sesuai Tabel Admin) -->
        <div class="table-card">
            <div class="table-header-box">
                <div>
                    <h3>Daftar Pesanan &amp; Status Pengerjaan Saya</h3>
                    <p style="font-size:12px; color:#6B7280;">Pantau rincian undangan yang sudah selesai maupun yang masih dalam proses pengerjaan.</p>
                </div>
                <a href="Status & TrackingOrder.php" style="color:#721B2F; font-size:12px; font-weight:700; text-decoration:none;">Buka Pelacakan Order →</a>
            </div>

            <div class="table-responsive">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Mempelai / Acara</th>
                            <th>Template / Layanan</th>
                            <th>Tanda Status Undangan</th>
                            <th>Pembayaran</th>
                            <th style="text-align:right;">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_user_orders)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:24px; color:#9CA3AF;">Belum ada pesanan aktif. Silakan pilih desain di Katalog Template!</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($all_user_orders as $ord): 
                            $is_done = ($ord['status'] === 'dikirim' || (int)($ord['step_index'] ?? 0) >= 5);
                        ?>
                        <tr>
                            <td><strong style="color:#721B2F;"><?= htmlspecialchars($ord['order_number']) ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($ord['nama_acara'] ?? $ord['customer_name']) ?></strong><br>
                                <small style="color:#6B7280; font-family:monospace;"><?= htmlspecialchars($ord['tanggal_acara'] ?? $ord['created_at'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($ord['template_name'] ?? 'Undangan Digital') ?></td>
                            <td>
                                <?php
                                    $st = $ord['status'] ?? '';
                                    if ($st === 'dibatalkan'):
                                ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:#FFE4E6; color:#9F1239; border:1px solid #FECDD3;">🚫 Dibatalkan</span>
                                <?php elseif ($st === 'dikirim' || $st === 'selesai' || $is_done): ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0;">✓ Step 6: Selesai</span>
                                <?php elseif ($st === 'pilih_template'): ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:#FEF3C7; color:#92400E; border:1px solid #FCD34D;">⏳ Step 1: Pilih Template</span>
                                <?php elseif ($st === 'konsultasi'): ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:#E0F2FE; color:#0369A1; border:1px solid #BAE6FD;">💬 Step 2: Konsultasi</span>
                                <?php elseif ($st === 'data_terkirim'): ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:#F3E8FF; color:#6B21A8; border:1px solid #E9D5FF;">📝 Step 3: Data Acara</span>
                                <?php elseif ($st === 'diproses'): ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:#DBEAFE; color:#1E40AF; border:1px solid #BFDBFE;">⚙️ Step 4: Pengerjaan</span>
                                <?php elseif ($st === 'undangan_jadi'): ?>
                                    <span style="display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; background:#CCFBF1; color:#0F766E; border:1px solid #99F6E4;">✨ Step 5: Review Web</span>
                                <?php else: ?>
                                    <span class="badge-tag badge-amber">Tahap <?= (int)($ord['step_index'] ?? 0) + 1 ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(($ord['payment_status'] ?? '') === 'lunas'): ?>
                                    <span class="badge-tag badge-emerald">✓ Lunas</span>
                                <?php else: ?>
                                    <span class="badge-tag badge-amber">⏳ Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:flex; justify-content:flex-end; gap:6px; align-items:center;">
                                    <a href="Invoice.php?order=<?= urlencode($ord['order_number']) ?>" style="background:#FAF3DE; color:#701C2F; border:1px solid #E5D7B7; padding:5px 8px; border-radius:8px; font-weight:700; font-size:11px; text-decoration:none;" title="Lihat Invoice">🧾</a>
                                    <?php if(!empty($ord['result_link'])): ?>
                                        <a href="<?= htmlspecialchars($ord['result_link']) ?>" target="_blank" style="background:#059669; color:#FFFFFF; padding:5px 12px; border-radius:8px; font-weight:700; font-size:11px; text-decoration:none;" title="Buka Website Undangan">Buka Web ↗</a>
                                    <?php endif; ?>
                                    <a href="Status & TrackingOrder.php?order=<?= urlencode($ord['order_number']) ?>" style="background:#701C2F; color:#FFFFFF; padding:5px 12px; border-radius:8px; font-weight:700; font-size:11px; text-decoration:none;">Tracking →</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Active Order Spotlight (Jika ada pesanan) -->
        <?php if ($active_order): 
            $step = (int)($active_order['step_index'] ?? 3);
        ?>
        <div class="order-spotlight">
            <div class="spotlight-header">
                <h2>
                    <span>Pesanan Aktif:</span>
                    <span class="badge-order-no"><?= htmlspecialchars($active_order['order_number']) ?></span>
                </h2>
                <a href="Status & TrackingOrder.php" style="color: #721B2F; font-size: 13px; font-weight: 700; text-decoration: none;">
                    Buka Detail &amp; Edit Acara →
                </a>
            </div>

            <div style="font-size: 13px; color: #4B5563; margin-bottom: 12px;">
                Template: <strong><?= htmlspecialchars($active_order['template_name'] ?? 'Custom Wedding Invitation') ?></strong> | 
                Total: <strong>Rp <?= number_format($active_order['total_price'], 0, ',', '.') ?></strong> (Status: <strong style="color: <?= ($active_order['payment_status'] ?? '') === 'lunas' ? '#10B981' : '#F59E0B' ?>"><?= strtoupper((string)($active_order['payment_status'] ?? 'pending')) ?></strong>)
            </div>

            <?php if (!empty($active_order['result_link'])): ?>
            <div style="background:#ECFDF5; border:1.5px solid #A7F3D0; padding:10px 14px; border-radius:12px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:18px;">🌐</span>
                    <div>
                        <span style="font-size:10px; font-weight:800; color:#065F46; text-transform:uppercase;">Link Website Undangan Jadi:</span>
                        <a href="<?= htmlspecialchars($active_order['result_link']) ?>" target="_blank" style="display:block; font-size:13px; font-weight:700; color:#059669; text-decoration:underline;">
                            <?= htmlspecialchars($active_order['result_link']) ?>
                        </a>
                    </div>
                </div>
                <div style="display:flex; gap:6px;">
                    <a href="<?= htmlspecialchars($active_order['result_link']) ?>" target="_blank" style="background:#059669; color:#FFFFFF; padding:6px 14px; border-radius:8px; font-weight:700; font-size:11px; text-decoration:none;">Buka Web ↗</a>
                    <a href="Status & TrackingOrder.php?order=<?= urlencode($active_order['order_number']) ?>" style="background:#FAF3DE; color:#701C2F; border:1px solid #E5D7B7; padding:6px 12px; border-radius:8px; font-weight:700; font-size:11px; text-decoration:none;">Tracking &amp; Share</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- 6-Step Visual Bar -->
            <div class="progress-steps-row">
                <div class="step-node <?= $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' ?>">
                    <div class="step-circle">1</div>
                    <div class="step-label">Pilih Desain</div>
                </div>
                <div class="step-node <?= $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' ?>">
                    <div class="step-circle">2</div>
                    <div class="step-label">Konsultasi</div>
                </div>
                <div class="step-node <?= $step >= 3 ? ($step > 3 ? 'completed' : 'active') : '' ?>">
                    <div class="step-circle">3</div>
                    <div class="step-label">Data Acara</div>
                </div>
                <div class="step-node <?= $step >= 4 ? ($step > 4 ? 'completed' : 'active') : '' ?>">
                    <div class="step-circle">4</div>
                    <div class="step-label">Pengerjaan</div>
                </div>
                <div class="step-node <?= $step >= 5 ? ($step > 5 ? 'completed' : 'active') : '' ?>">
                    <div class="step-circle">5</div>
                    <div class="step-label">Review Web</div>
                </div>
                <div class="step-node <?= $step >= 6 ? 'completed' : '' ?>">
                    <div class="step-circle">6</div>
                    <div class="step-label">Selesai</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Action Grid Quick Access -->
        <div class="action-grid">
            <a href="Katalog Template.php" class="action-card">
                <div>
                    <span style="font-size: 26px;">🎨</span>
                    <h3>Katalog Template</h3>
                    <p>Lihat koleksi desain premium, coba live preview demo web, dan lakukan pemesanan.</p>
                </div>
                <span style="font-weight: 700; font-size: 12px; color: #721B2F; margin-top: 14px;">Buka Katalog →</span>
            </a>

            <a href="Status & TrackingOrder.php" class="action-card">
                <div>
                    <span style="font-size: 26px;">📦</span>
                    <h3>Status &amp; Tracking Order</h3>
                    <p>Pantau status tahap pengerjaan undangan dan perbarui informasi rincian acara.</p>
                </div>
                <span style="font-weight: 700; font-size: 12px; color: #721B2F; margin-top: 14px;">Buka Tracking →</span>
            </a>

            <a href="../Communication_U/Inbox CS.php" class="action-card">
                <div>
                    <span style="font-size: 26px;">💬</span>
                    <h3>Inbox CS (Diskusi)</h3>
                    <p>Hubungi admin desainer langsung melalui room chat mandiri akun Anda.</p>
                </div>
                <span style="font-weight: 700; font-size: 12px; color: #721B2F; margin-top: 14px;">Mulai Diskusi →</span>
            </a>
        </div>
    </main>
</body>
</html>
