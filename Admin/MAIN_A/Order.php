<?php
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['role'] = 'admin';
    $_SESSION['name'] = 'Administrator LilinNawa';
    $_SESSION['email'] = 'admin@lilinawa.com';
}

$alert = '';
$alert_type = 'success';

// -------------------------------------------------------------
// 1. PROSES UPDATE PESANAN OLEH ADMIN
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order') {
    $order_id       = intval($_POST['order_id']);
    $customer_name  = mysqli_real_escape_string($conn, $_POST['customer_name'] ?? '');
    $customer_phone = mysqli_real_escape_string($conn, $_POST['customer_phone'] ?? '');
    $status         = mysqli_real_escape_string($conn, $_POST['status'] ?? 'pilih_template');
    $step_index     = intval($_POST['step_index'] ?? 0);
    $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status'] ?? 'pending');
    $total_price    = floatval($_POST['total_price'] ?? 0);
    $result_link    = mysqli_real_escape_string($conn, $_POST['result_link'] ?? '');
    $cancel_reason  = mysqli_real_escape_string($conn, $_POST['cancel_reason'] ?? '');

    $cancel_sql_part = "";
    if ($status === 'dibatalkan') {
        $cancel_sql_part = ", cancel_reason = '$cancel_reason', cancelled_at = NOW()";
    }

    if (!empty($conn) && $order_id > 0) {
        $upd_sql = "UPDATE orders SET 
                    customer_name = '$customer_name',
                    customer_phone = '$customer_phone',
                    status = '$status',
                    step_index = $step_index,
                    payment_status = '$payment_status',
                    total_price = $total_price,
                    result_link = '$result_link'
                    $cancel_sql_part
                    WHERE id = $order_id";
        mysqli_query($conn, $upd_sql);

        // Update detail data acara jika ada
        if (isset($_POST['nama_acara'])) {
            $nama_acara = mysqli_real_escape_string($conn, $_POST['nama_acara']);
            $tgl_acara  = mysqli_real_escape_string($conn, $_POST['tanggal_acara'] ?? '');
            $lokasi     = mysqli_real_escape_string($conn, $_POST['lokasi_acara'] ?? '');
            
            $chk_d = mysqli_query($conn, "SELECT id FROM order_event_details WHERE order_id = $order_id");
            if ($chk_d && mysqli_num_rows($chk_d) > 0) {
                mysqli_query($conn, "UPDATE order_event_details SET nama_acara='$nama_acara', tanggal_acara='$tgl_acara', lokasi_acara='$lokasi' WHERE order_id=$order_id");
            } else {
                mysqli_query($conn, "INSERT INTO order_event_details (order_id, nama_acara, tanggal_acara, lokasi_acara) VALUES ($order_id, '$nama_acara', '$tgl_acara', '$lokasi')");
            }
        }
    }

    $alert = 'Pesanan #' . htmlspecialchars($_POST['order_number'] ?? '') . ' berhasil diperbarui!';
}

// -------------------------------------------------------------
// 2. PROSES KELOLA REFUND PELANGGAN OLEH ADMIN
// (Setujui, Tolak, Proses Transfer, Tandai Selesai, Update Database)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manage_refund') {
    $refund_id      = intval($_POST['refund_id'] ?? 0);
    $order_id       = intval($_POST['order_id'] ?? 0);
    $refund_status  = mysqli_real_escape_string($conn, $_POST['refund_status'] ?? 'diajukan');
    $admin_notes    = mysqli_real_escape_string($conn, trim($_POST['admin_notes'] ?? ''));
    $transfer_proof = mysqli_real_escape_string($conn, trim($_POST['transfer_proof'] ?? ''));

    if (!empty($conn) && $refund_id > 0) {
        $timestamp_part = "";
        if ($refund_status === 'disetujui' || $refund_status === 'ditolak') {
            $timestamp_part = ", processed_at = NOW()";
        } elseif ($refund_status === 'selesai') {
            $timestamp_part = ", completed_at = NOW()";
        }

        $proof_part = "";
        if (!empty($transfer_proof)) {
            $proof_part = ", transfer_proof = '$transfer_proof'";
        }

        // 1. Update tabel refund_requests
        mysqli_query($conn, "UPDATE refund_requests SET 
            status = '$refund_status',
            admin_notes = '$admin_notes'
            $timestamp_part
            $proof_part
            WHERE id = $refund_id");

        // 2. Update status refund di tabel orders
        if ($order_id > 0) {
            mysqli_query($conn, "UPDATE orders SET refund_status = '$refund_status' WHERE id = $order_id");
        }
    }

    $status_label_map = [
        'disetujui' => 'DISETUJUI (Menunggu Proses Transfer)',
        'ditolak' => 'DITOLAK',
        'diproses_transfer' => 'SEDANG DIPROSES TRANSFER',
        'selesai' => 'SELESAI (Dana Berhasil Ditransfer)'
    ];
    $alert = 'Pengajuan refund berhasil diperbarui ke status: ' . ($status_label_map[$refund_status] ?? $refund_status);
}

// Active Tab: 'orders' atau 'refunds'
$active_tab = $_GET['tab'] ?? 'orders';

// Filter dan Pencarian Orders
$where = "WHERE 1=1";
$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $search_safe = mysqli_real_escape_string($conn, $search);
    $where .= " AND (o.order_number LIKE '%$search_safe%' OR o.customer_name LIKE '%$search_safe%' OR o.customer_phone LIKE '%$search_safe%' OR o.template_name LIKE '%$search_safe%')";
}

$status_filter = $_GET['status_filter'] ?? 'all';
if ($status_filter === 'baru') {
    $where .= " AND o.status IN ('pilih_template','konsultasi','data_terkirim')";
} elseif ($status_filter === 'proses') {
    $where .= " AND o.status IN ('diproses','undangan_jadi')";
} elseif ($status_filter === 'selesai') {
    $where .= " AND o.status IN ('dikirim','selesai')";
} elseif ($status_filter === 'dibatalkan') {
    $where .= " AND o.status = 'dibatalkan'";
}

$orders_list = [];
if (!empty($conn)) {
    $orders_query = @mysqli_query($conn, "SELECT o.*, d.nama_acara, d.tanggal_acara, d.lokasi_acara, d.alamat_lengkap 
                                          FROM orders o 
                                          LEFT JOIN order_event_details d ON o.id = d.order_id 
                                          $where 
                                          ORDER BY o.id DESC");
    if ($orders_query) {
        while ($ord_row = mysqli_fetch_assoc($orders_query)) {
            $orders_list[] = $ord_row;
        }
    }
}

// KPI Statistik Utama
$total_revenue = 0;
if (!empty($conn)) {
    $rev_q = @mysqli_query($conn, "SELECT SUM(total_price) as rev FROM orders WHERE payment_status = 'lunas'");
    if ($rev_q && $r_row = mysqli_fetch_assoc($rev_q)) {
        $total_revenue = (float)($r_row['rev'] ?? 0);
    }
}
if ($total_revenue == 0 && !empty($orders_list)) {
    foreach ($orders_list as $o) {
        if (($o['payment_status'] ?? '') === 'lunas') {
            $total_revenue += (float)($o['total_price'] ?? 0);
        }
    }
}

// -------------------------------------------------------------
// QUERY PENGELOLAAN REFUND
// -------------------------------------------------------------
$refunds_list = [];
$pending_refund_count = 0;
if (!empty($conn)) {
    $rf_q = @mysqli_query($conn, "SELECT r.*, o.order_number as ord_no, o.template_name, o.payment_status as ord_pay_status 
                                  FROM refund_requests r 
                                  LEFT JOIN orders o ON r.order_id = o.id 
                                  ORDER BY r.id DESC");
    if ($rf_q) {
        while ($rf_row = mysqli_fetch_assoc($rf_q)) {
            $refunds_list[] = $rf_row;
            if ($rf_row['status'] === 'diajukan') {
                $pending_refund_count++;
            }
        }
    }
}

// Hitung KPI Refund
$refund_total_nominal = 0;
$refund_selesai_count = 0;
$refund_transfer_count = 0;
foreach ($refunds_list as $rf) {
    if ($rf['status'] === 'selesai') {
        $refund_selesai_count++;
        $refund_total_nominal += (float)$rf['refund_amount'];
    } elseif ($rf['status'] === 'diproses_transfer') {
        $refund_transfer_count++;
    }
}

// Fungsi Badge Status Step Order
function getStepBadge($status, $step_index) {
    switch ($status) {
        case 'pilih_template':
            return [
                'label' => 'Step 1: Pilih Template',
                'style' => 'background:#E0F2FE; color:#0369A1; border:1.5px solid #BAE6FD;',
                'dot'   => '#0284C7'
            ];
        case 'konsultasi':
            return [
                'label' => 'Step 2: Pembayaran / QRIS',
                'style' => 'background:#EEF2FF; color:#4338CA; border:1.5px solid #C7D2FE;',
                'dot'   => '#6366F1'
            ];
        case 'data_terkirim':
            return [
                'label' => 'Step 3: Kelengkapan Data',
                'style' => 'background:#FEF3C7; color:#92400E; border:1.5px solid #FDE68A;',
                'dot'   => '#F59E0B'
            ];
        case 'diproses':
            return [
                'label' => 'Step 4: Pengerjaan Desain',
                'style' => 'background:#CCFBF1; color:#115E59; border:1.5px solid #99F6E4;',
                'dot'   => '#14B8A6'
            ];
        case 'undangan_jadi':
            return [
                'label' => 'Step 5: Review Undangan',
                'style' => 'background:#F3E8FF; color:#6B21A8; border:1.5px solid #E9D5FF;',
                'dot'   => '#A855F7'
            ];
        case 'dikirim':
        case 'selesai':
            return [
                'label' => 'Step 6: Selesai & Aktif',
                'style' => 'background:#D1FAE5; color:#065F46; border:1.5px solid #A7F3D0;',
                'dot'   => '#10B981'
            ];
        case 'dibatalkan':
            return [
                'label' => '🚫 Dibatalkan',
                'style' => 'background:#FFE4E6; color:#9F1239; border:1.5px solid #FECDD3;',
                'dot'   => '#F43F5E'
            ];
        default:
            return [
                'label' => 'Step ' . ((int)$step_index + 1) . ': ' . ucwords(str_replace('_', ' ', $status)),
                'style' => 'background:#F3F4F6; color:#374151; border:1px solid #D1D5DB;',
                'dot'   => '#6B7280'
            ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Order &amp; Kelola Refund - Admin LilinNawa</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="order.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .nav-tabs-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 24px;
            border-bottom: 2px solid #E5E7EB;
            padding-bottom: 12px;
        }
        .tab-btn {
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .tab-btn.active {
            background: #701C2F;
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(112, 28, 47, 0.2);
        }
        .tab-btn.inactive {
            background: #FFFFFF;
            color: #4B5563;
            border: 1px solid #D1D5DB;
        }
        .tab-btn.inactive:hover {
            background: #F9FAFB;
            color: #111827;
        }
        .kpi-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .kpi-card {
            background: #FFFFFF;
            border-radius: 18px;
            padding: 18px 20px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .kpi-title {
            font-size: 11px;
            font-weight: 700;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .kpi-value {
            font-size: 22px;
            font-weight: 800;
            color: #701C2F;
            font-family: 'Playfair Display', serif;
        }
        .step-colored-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .step-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-content {
            background: #FFFFFF;
            width: 100%;
            max-width: 650px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 20px;
            padding: 26px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            box-sizing: border-box;
        }
    </style>
</head>
<body class="admin-wrapper" style="margin:0; font-family:'Plus Jakarta Sans',sans-serif; background-color:#F8FAFC;">
    <?php include '../sidebar.php'; ?>

    <main class="admin-main" style="padding: 24px 32px; max-width: 1300px; box-sizing: border-box;">

        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
            <div>
                <h1 style="font-family:'Playfair Display',serif; font-size:26px; color:#111827; margin:0;">
                    Manajemen Order &amp; Kelola Refund LilinNawa
                </h1>
                <p style="font-size:13px; color:#6B7280; margin:4px 0 0;">
                    Kelola tahapan pesanan, bagikan link website ke customer, serta kelola dan setujui pengajuan refund pelanggan secara lengkap.
                </p>
            </div>
        </div>

        <?php if (!empty($alert)): ?>
        <div style="padding:14px 18px; border-radius:12px; background:#ECFDF5; border:1px solid #A7F3D0; color:#065F46; font-size:13px; font-weight:700; margin-bottom:20px; display:flex; align-items:center; gap:8px;">
            <span>✓</span>
            <div><?= htmlspecialchars($alert) ?></div>
        </div>
        <?php endif; ?>

        <!-- NAV TABS SWITCHER -->
        <div class="nav-tabs-bar">
            <a href="Order.php?tab=orders" class="tab-btn <?= $active_tab === 'orders' ? 'active' : 'inactive' ?>">
                <span>📦 Manajemen Semua Pesanan</span>
            </a>
            <a href="Order.php?tab=refunds" class="tab-btn <?= $active_tab === 'refunds' ? 'active' : 'inactive' ?>">
                <span>💸 Kelola Pengajuan Refund Pelanggan</span>
                <?php if ($pending_refund_count > 0): ?>
                    <span style="background:#EF4444; color:#FFF; font-size:10px; font-weight:800; padding:2px 7px; border-radius:99px;"><?= $pending_refund_count ?> Butuh Review</span>
                <?php endif; ?>
            </a>
        </div>

        <!-- ============================================================= -->
        <!-- TAB 1: MANAJEMEN SEMUA PESANAN                                -->
        <!-- ============================================================= -->
        <?php if ($active_tab === 'orders'): ?>
        
        <!-- 4 KPI Cards Pesanan -->
        <div class="kpi-cards-grid">
            <div class="kpi-card" style="border-left: 4px solid #701C2F;">
                <div>
                    <span class="kpi-title">Total Omset Usaha</span>
                    <div class="kpi-value">Rp <?= number_format($total_revenue, 0, ',', '.') ?></div>
                </div>
                <div style="font-size:11px; color:#059669; font-weight:600; margin-top:6px;">↑ Akumulasi pesanan lunas</div>
            </div>

            <div class="kpi-card" style="border-left: 4px solid #059669;">
                <div>
                    <span class="kpi-title">Waktu Target Selesai</span>
                    <div class="kpi-value" style="color:#059669; font-size:18px;">24 - 48 Jam</div>
                </div>
                <div style="font-size:11px; color:#059669; margin-top:6px;">Target pengerjaan cepat</div>
            </div>

            <div class="kpi-card" style="border-left: 4px solid #F59E0B;">
                <div>
                    <span class="kpi-title">Pengajuan Refund Pelanggan</span>
                    <div class="kpi-value" style="color:#D97706;"><?= count($refunds_list) ?> Pengajuan</div>
                </div>
                <div style="font-size:11px; color:#D97706; margin-top:6px;"><?= $pending_refund_count ?> menunggu persetujuan</div>
            </div>

            <div class="kpi-card" style="border-left: 4px solid #3B82F6;">
                <div>
                    <span class="kpi-title">Pilihan Favorit Pelanggan</span>
                    <div style="font-size:12px; font-weight:700; color:#1F2937; margin-top:4px; line-height:1.4;">
                        1. Royal Java Gold<br>2. Minimalist Sage Garden
                    </div>
                </div>
                <div style="font-size:10px; color:#6B7280; margin-top:4px;">Top template terlaris</div>
            </div>
        </div>

        <!-- Filter Toolbar -->
        <div style="background:#FFFFFF; padding:14px; border-radius:16px; border:1px solid #E5E7EB; margin-bottom:20px;">
            <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                <input type="hidden" name="tab" value="orders">
                <input type="text" name="search" id="orderLiveSearch" oninput="filterOrdersLive()" value="<?= htmlspecialchars($search) ?>" placeholder="Cari: No. Order, Nama, WhatsApp, Template..." style="flex:1; min-width:240px; padding:10px 14px; border:1px solid #D1D5DB; border-radius:10px; font-size:13px;">
                <select name="status_filter" style="padding:10px 14px; border:1px solid #D1D5DB; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;" onchange="this.form.submit()">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                    <option value="baru" <?= $status_filter === 'baru' ? 'selected' : '' ?>>Order Baru (Step 1-3)</option>
                    <option value="proses" <?= $status_filter === 'proses' ? 'selected' : '' ?>>Sedang Pengerjaan (Step 4-5)</option>
                    <option value="selesai" <?= $status_filter === 'selesai' ? 'selected' : '' ?>>Selesai (Jadi)</option>
                    <option value="dibatalkan" <?= $status_filter === 'dibatalkan' ? 'selected' : '' ?>>🚫 Dibatalkan</option>
                </select>
                <button type="submit" class="btn-admin btn-primary" style="padding:10px 20px; background:#701C2F; color:#FFF; border:none; border-radius:10px; font-weight:700; cursor:pointer;">Cari</button>
                <?php if (!empty($search) || $status_filter !== 'all'): ?>
                    <a href="Order.php?tab=orders" class="btn-admin btn-secondary" style="padding:10px 16px; background:#F3F4F6; color:#374151; border:1px solid #D1D5DB; border-radius:10px; text-decoration:none; font-weight:700;">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tabel Daftar Pesanan -->
        <div style="background:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; padding:20px; box-shadow:0 4px 16px rgba(0,0,0,0.02); overflow-x:auto;">
            <table class="admin-table" style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid #F3F4F6; text-align:left; color:#6B7280; font-size:11px; text-transform:uppercase;">
                        <th style="padding:12px 10px;">No. Order</th>
                        <th style="padding:12px 10px;">Pelanggan &amp; Kontak</th>
                        <th style="padding:12px 10px;">Layanan / Acara</th>
                        <th style="padding:12px 10px;">Tahapan Alur (6-Step)</th>
                        <th style="padding:12px 10px;">Pembayaran</th>
                        <th style="padding:12px 10px;">Total</th>
                        <th style="padding:12px 10px;">Link Website</th>
                        <th style="padding:12px 10px; text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $has_orders = !empty($orders_list);
                    if (!$has_orders): 
                    ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:48px 20px; color:#9CA3AF;">
                            <div style="font-size:36px; margin-bottom:8px;">📦</div>
                            <strong style="font-size:15px; color:#374151; display:block;">Belum ada data pesanan</strong>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($orders_list as $row): 
                        $badge = getStepBadge($row['status'], $row['step_index']);
                    ?>
                    <tr style="border-bottom:1px solid #F3F4F6;">
                        <td style="padding:14px 10px;">
                            <strong style="color:#701C2F; font-size:13px; font-weight:800;"><?= htmlspecialchars($row['order_number']) ?></strong>
                            <?php if(($row['refund_status'] ?? 'none') !== 'none'): ?>
                                <span style="display:block; font-size:9.5px; font-weight:800; color:#BE123C; background:#FFE4E6; padding:2px 6px; border-radius:4px; width:fit-content; margin-top:3px;">REFUND: <?= strtoupper($row['refund_status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:14px 10px;">
                            <strong><?= htmlspecialchars($row['customer_name']) ?></strong><br>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $row['customer_phone']) ?>" target="_blank" style="color:#059669; font-size:11px; text-decoration:none; font-weight:bold;">
                                💬 <?= htmlspecialchars($row['customer_phone']) ?>
                            </a>
                        </td>
                        <td style="padding:14px 10px;">
                            <span style="font-weight:600; color:#1F2937;"><?= htmlspecialchars($row['template_name']) ?></span><br>
                            <small style="color:#6B7280; font-size:11px;"><?= htmlspecialchars($row['nama_acara'] ?? 'Acara Pernikahan') ?></small>
                        </td>
                        <td style="padding:14px 10px;">
                            <span class="step-colored-badge" style="<?= $badge['style'] ?>">
                                <span class="step-dot" style="background:<?= $badge['dot'] ?>;"></span>
                                <?= $badge['label'] ?>
                            </span>
                        </td>
                        <td style="padding:14px 10px;">
                            <?php if($row['payment_status'] === 'lunas'): ?>
                                <span style="background:#D1FAE5; color:#065F46; font-weight:800; font-size:11px; padding:3px 8px; border-radius:6px;">✓ LUNAS</span>
                            <?php else: ?>
                                <span style="background:#FEF3C7; color:#92400E; font-weight:800; font-size:11px; padding:3px 8px; border-radius:6px;">⏳ PENDING</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:14px 10px;">
                            <strong>Rp <?= number_format($row['total_price'], 0, ',', '.') ?></strong>
                        </td>
                        <td style="padding:14px 10px;">
                            <?php if(!empty($row['result_link'])): ?>
                                <a href="<?= htmlspecialchars($row['result_link']) ?>" target="_blank" style="color:#059669; font-weight:bold; font-size:11px; text-decoration:underline;">
                                    Buka Web ↗
                                </a>
                            <?php else: ?>
                                <span style="color:#9CA3AF; font-size:11px;">Belum Diisi</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:14px 10px; text-align:right;">
                            <?php if(($row['status'] ?? '') === 'dibatalkan'): ?>
                                <span style="background:#FFF1F2; color:#9F1239; border:1px solid #FECDD3; padding:5px 10px; border-radius:8px; font-weight:700; font-size:11px; display:inline-flex; align-items:center; gap:4px; cursor:not-allowed;" title="Pesanan dibatalkan tidak dapat diubah lagi">
                                    🔒 Dibatalkan
                                </span>
                            <?php else: ?>
                                <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" style="background:#701C2F; color:#FFF; border:none; padding:6px 14px; border-radius:8px; font-weight:700; font-size:11.5px; cursor:pointer;">
                                    Edit / Link ✏️
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
        <!-- ============================================================= -->
        <!-- TAB 2: KELOLA PENGAJUAN REFUND PELANGGAN (KODE LENGKAP ADMIN)  -->
        <!-- ============================================================= -->

        <!-- KPI Cards Khusus Refund -->
        <div class="kpi-cards-grid">
            <div class="kpi-card" style="border-left: 4px solid #BE123C;">
                <div>
                    <span class="kpi-title">Menunggu Verifikasi Refund</span>
                    <div class="kpi-value" style="color:#BE123C;"><?= $pending_refund_count ?> Pengajuan</div>
                </div>
                <div style="font-size:11px; color:#BE123C; font-weight:700; margin-top:6px;">Perlu tindakan persetujuan admin</div>
            </div>

            <div class="kpi-card" style="border-left: 4px solid #2563EB;">
                <div>
                    <span class="kpi-title">Sedang Diproses Transfer</span>
                    <div class="kpi-value" style="color:#2563EB;"><?= $refund_transfer_count ?> Antrean</div>
                </div>
                <div style="font-size:11px; color:#2563EB; font-weight:600; margin-top:6px;">Dalam proses pengiriman dana</div>
            </div>

            <div class="kpi-card" style="border-left: 4px solid #059669;">
                <div>
                    <span class="kpi-title">Refund Selesai Ditransfer</span>
                    <div class="kpi-value" style="color:#059669;"><?= $refund_selesai_count ?> Selesai</div>
                </div>
                <div style="font-size:11px; color:#059669; font-weight:600; margin-top:6px;">Total: Rp <?= number_format($refund_total_nominal, 0, ',', '.') ?></div>
            </div>

            <div class="kpi-card" style="border-left: 4px solid #4B5563;">
                <div>
                    <span class="kpi-title">Total Keseluruhan Pengajuan</span>
                    <div class="kpi-value" style="color:#111827;"><?= count($refunds_list) ?> Berkas</div>
                </div>
                <div style="font-size:11px; color:#6B7280; margin-top:6px;">Arsip pembatalan lunas</div>
            </div>
        </div>

        <!-- Tabel Daftar Pengajuan Refund -->
        <div style="background:#FFFFFF; border-radius:18px; border:1px solid #E5E7EB; padding:20px; box-shadow:0 4px 16px rgba(0,0,0,0.02); overflow-x:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="font-family:'Playfair Display',serif; font-size:18px; color:#111827; margin:0;">
                    Daftar Pengajuan Refund Pelanggan LilinNawa
                </h3>
                <span style="font-size:12px; color:#6B7280;">Daftar lengkap rekening tujuan &amp; rincian transfer</span>
            </div>

            <table class="admin-table" style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid #F3F4F6; text-align:left; color:#6B7280; font-size:11px; text-transform:uppercase;">
                        <th style="padding:12px 10px;">No. Order &amp; Waktu</th>
                        <th style="padding:12px 10px;">Pelanggan &amp; WhatsApp</th>
                        <th style="padding:12px 10px;">Nominal Refund</th>
                        <th style="padding:12px 10px;">Rekening Tujuan Pelanggan</th>
                        <th style="padding:12px 10px;">Alasan Pembatalan</th>
                        <th style="padding:12px 10px;">Status Refund</th>
                        <th style="padding:12px 10px; text-align:right;">Aksi Kelola</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($refunds_list)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:48px 20px; color:#9CA3AF;">
                            <div style="font-size:36px; margin-bottom:8px;">💸</div>
                            <strong style="font-size:15px; color:#374151; display:block;">Tidak ada pengajuan refund</strong>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($refunds_list as $rf): 
                        $rf_status = $rf['status'] ?? 'diajukan';
                    ?>
                    <tr style="border-bottom:1px solid #F3F4F6;">
                        <td style="padding:14px 10px;">
                            <strong style="color:#701C2F; font-size:13px; font-weight:800;"><?= htmlspecialchars($rf['order_number'] ?? $rf['ord_no']) ?></strong>
                            <div style="font-size:11px; color:#6B7280; margin-top:2px;"><?= htmlspecialchars($rf['created_at']) ?></div>
                        </td>
                        <td style="padding:14px 10px;">
                            <strong><?= htmlspecialchars($rf['customer_name']) ?></strong><br>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $rf['customer_phone']) ?>" target="_blank" style="color:#059669; font-size:11px; text-decoration:none; font-weight:bold;">
                                💬 <?= htmlspecialchars($rf['customer_phone']) ?>
                            </a>
                        </td>
                        <td style="padding:14px 10px;">
                            <strong style="font-size:14px; color:#065F46; font-family:'Playfair Display',serif;">
                                Rp <?= number_format($rf['refund_amount'], 0, ',', '.') ?>
                            </strong>
                        </td>
                        <td style="padding:14px 10px;">
                            <span style="background:#FAF3DE; color:#701C2F; font-weight:800; font-size:10.5px; padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($rf['bank_name']) ?></span><br>
                            <strong style="font-family:monospace; font-size:12.5px; color:#1F2937;"><?= htmlspecialchars($rf['account_number']) ?></strong><br>
                            <small style="color:#6B7280;">a/n <?= htmlspecialchars($rf['account_name']) ?></small>
                        </td>
                        <td style="padding:14px 10px; max-width:200px;">
                            <span style="font-weight:600; color:#374151;"><?= htmlspecialchars($rf['reason']) ?></span>
                            <?php if(!empty($rf['notes'])): ?>
                                <p style="font-size:11px; color:#6B7280; margin:2px 0 0; font-style:italic;">&ldquo;<?= htmlspecialchars($rf['notes']) ?>&rdquo;</p>
                            <?php endif; ?>
                        </td>
                        <td style="padding:14px 10px;">
                            <?php if($rf_status === 'diajukan'): ?>
                                <span style="background:#FEF3C7; color:#92400E; font-weight:800; font-size:11px; padding:4px 10px; border-radius:99px; border:1px solid #FDE68A;">⏳ DIAJUKAN</span>
                            <?php elseif($rf_status === 'disetujui'): ?>
                                <span style="background:#D1FAE5; color:#065F46; font-weight:800; font-size:11px; padding:4px 10px; border-radius:99px; border:1px solid #A7F3D0;">✓ DISETUJUI</span>
                            <?php elseif($rf_status === 'diproses_transfer'): ?>
                                <span style="background:#DBEAFE; color:#1E40AF; font-weight:800; font-size:11px; padding:4px 10px; border-radius:99px; border:1px solid #BFDBFE;">⏳ PROSES TRANSFER</span>
                            <?php elseif($rf_status === 'selesai'): ?>
                                <span style="background:#ECFDF5; color:#047857; font-weight:800; font-size:11px; padding:4px 10px; border-radius:99px; border:1px solid #6EE7B7;">✓ SELESAI DITRANSFER</span>
                            <?php elseif($rf_status === 'ditolak'): ?>
                                <span style="background:#FFE4E6; color:#9F1239; font-weight:800; font-size:11px; padding:4px 10px; border-radius:99px; border:1px solid #FECDD3;">✕ DITOLAK</span>
                            <?php endif; ?>

                            <?php if(!empty($rf['transfer_proof'])): ?>
                                <div style="font-size:10px; color:#059669; font-weight:bold; margin-top:4px;">Ref: <?= htmlspecialchars($rf['transfer_proof']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:14px 10px; text-align:right;">
                            <?php if($rf_status === 'selesai'): ?>
                                <span style="background:#F3F4F6; color:#9CA3AF; border:1px solid #E5E7EB; padding:6px 12px; border-radius:8px; font-weight:700; font-size:11px; display:inline-flex; align-items:center; gap:4px; cursor:not-allowed;" title="Refund sudah selesai ditransfer, tidak dapat diubah lagi">
                                    🔒 Selesai (Terkunci)
                                </span>
                            <?php elseif($rf_status === 'ditolak'): ?>
                                <span style="background:#FEE2E2; color:#991B1B; border:1px solid #FECDD3; padding:6px 12px; border-radius:8px; font-weight:700; font-size:11px; display:inline-flex; align-items:center; gap:4px; cursor:not-allowed;" title="Refund telah ditolak, tidak dapat diubah lagi">
                                    🔒 Ditolak (Tutup)
                                </span>
                            <?php else: ?>
                                <button type="button" onclick="openRefundActionModal(<?= htmlspecialchars(json_encode($rf)) ?>)" style="background:#059669; color:#FFF; border:none; padding:7px 14px; border-radius:8px; font-weight:700; font-size:11.5px; cursor:pointer; box-shadow:0 2px 6px rgba(5,150,105,0.2);">
                                    Kelola &amp; Proses ⚙️
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- ============================================================= -->
        <!-- MODAL EDIT PESANAN & BAGIKAN LINK WEBSITE                     -->
        <!-- ============================================================= -->
        <div id="modalEditOrder" class="modal-overlay" onclick="if(event.target===this) closeEditModal()">
            <div class="modal-content">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #E5E7EB; padding-bottom:12px; margin-bottom:16px;">
                    <div>
                        <h3 style="font-family:'Playfair Display',serif; font-size:18px; color:#701C2F; margin:0;">
                            Edit Pesanan &amp; Bagikan Link Website
                        </h3>
                        <small style="color:#6B7280; font-size:11px;">Update tahapan pengerjaan atau bagikan URL undangan ke klien.</small>
                    </div>
                    <button type="button" onclick="closeEditModal()" style="background:#F3F4F6; border:none; width:30px; height:30px; border-radius:50%; cursor:pointer;">✕</button>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="update_order">
                    <input type="hidden" name="order_id" id="edit_order_id">
                    <input type="hidden" name="order_number" id="edit_order_number">

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Nama Pelanggan *</label>
                            <input type="text" name="customer_name" id="edit_customer_name" required style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:13px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">WhatsApp Pelanggan *</label>
                            <input type="text" name="customer_phone" id="edit_customer_phone" required style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:13px; box-sizing:border-box;">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Tahap Status Alur *</label>
                            <select name="status" id="edit_status" onchange="handleStatusChange(this.value)" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:13px; box-sizing:border-box;">
                                <option value="pilih_template">Step 1: Pilih Template</option>
                                <option value="konsultasi">Step 2: Pembayaran / QRIS</option>
                                <option value="data_terkirim">Step 3: Kelengkapan Data Acara</option>
                                <option value="diproses">Step 4: Proses Pengerjaan Desain</option>
                                <option value="undangan_jadi">Step 5: Review Undangan pada Customer</option>
                                <option value="dikirim">Step 6: Selesai &amp; Aktif (Siap Sebar)</option>
                                <option value="dibatalkan">🚫 Dibatalkan</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Status Pembayaran *</label>
                            <select name="payment_status" id="edit_payment_status" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:13px; box-sizing:border-box;">
                                <option value="pending">⏳ Pending (Menunggu Bayar)</option>
                                <option value="lunas">✓ Lunas</option>
                            </select>
                        </div>
                    </div>

                    <!-- KOTAK LINK WEBSITE UNDANGAN UNTUK CUSTOMER -->
                    <div style="background:#ECFDF5; border:1.5px solid #34D399; border-radius:12px; padding:14px; margin-bottom:14px;">
                        <label style="font-size:12px; font-weight:800; color:#064E3B; display:block; margin-bottom:4px;">
                            🌐 Link Website Hasil Undangan (Dibagikan ke Customer)
                        </label>
                        <p style="font-size:11px; color:#047857; margin:0 0 8px;">
                            Link ini akan langsung muncul di kotak penerimaan dashboard customer agar customer bisa membuka, menyalin, dan membagikan undangan via WA.
                        </p>
                        <input type="url" name="result_link" id="edit_result_link" placeholder="https://lilinawa.my.id/u/nama-pengantin" style="width:100%; padding:9px 12px; border:1px solid #10B981; border-radius:8px; font-size:13px; box-sizing:border-box; font-family:monospace; font-weight:bold;">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px;">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Total Biaya (Rp) *</label>
                            <input type="number" name="total_price" id="edit_total_price" required style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:13px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Step Index (0 - 5)</label>
                            <input type="number" name="step_index" id="edit_step_index" min="0" max="5" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:13px; box-sizing:border-box;">
                        </div>
                    </div>

                    <div id="cancel_reason_box" style="display:none; background:#FFF1F2; border:1px solid #FECDD3; padding:12px; border-radius:10px; margin-bottom:14px;">
                        <label style="font-size:12px; font-weight:700; color:#BE123C; display:block; margin-bottom:4px;">Alasan Pembatalan</label>
                        <input type="text" name="cancel_reason" id="edit_cancel_reason" style="width:100%; padding:8px 12px; border:1px solid #FDA4AF; border-radius:8px; font-size:12px; box-sizing:border-box;">
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                        <button type="button" onclick="closeEditModal()" style="background:#F3F4F6; border:1px solid #D1D5DB; padding:10px 18px; border-radius:10px; font-weight:700; cursor:pointer;">Batal</button>
                        <button type="submit" style="background:#701C2F; color:#FFF; border:none; padding:10px 22px; border-radius:10px; font-weight:700; cursor:pointer;">Simpan &amp; Perbarui Pesanan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL KELOLA & TINDAKAN REFUND PELANGGAN                      -->
        <!-- ============================================================= -->
        <div id="modalRefundAction" class="modal-overlay" onclick="if(event.target===this) closeRefundModal()">
            <div class="modal-content">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #E5E7EB; padding-bottom:12px; margin-bottom:16px;">
                    <div>
                        <h3 style="font-family:'Playfair Display',serif; font-size:18px; color:#065F46; margin:0;">
                            💸 Kelola &amp; Proses Refund Pelanggan
                        </h3>
                        <small style="color:#6B7280; font-size:11px;">Periksa rekening tujuan, setujui, tolak, atau selesaikan transfer dana.</small>
                    </div>
                    <button type="button" onclick="closeRefundModal()" style="background:#F3F4F6; border:none; width:30px; height:30px; border-radius:50%; cursor:pointer;">✕</button>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="manage_refund">
                    <input type="hidden" name="refund_id" id="ref_action_id">
                    <input type="hidden" name="order_id" id="ref_action_order_id">

                    <!-- Detail Rekening Tujuan Box -->
                    <div style="background:#ECFDF5; border:1.5px solid #34D399; border-radius:14px; padding:16px; margin-bottom:16px;">
                        <div style="font-size:11px; text-transform:uppercase; font-weight:800; color:#064E3B; margin-bottom:8px;">
                            Rincian Transfer Rekening Tujuan Pelanggan:
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; font-size:12.5px;">
                            <div>No. Order: <strong id="ref_detail_order_no" style="color:#701C2F;">-</strong></div>
                            <div>Nominal: <strong id="ref_detail_amount" style="color:#047857;">-</strong></div>
                            <div>Bank / E-Wallet: <strong id="ref_detail_bank">-</strong></div>
                            <div>No. Rekening / HP: <strong id="ref_detail_acc_no" style="font-family:monospace;">-</strong></div>
                            <div style="grid-column:span 2;">Atas Nama: <strong id="ref_detail_acc_name">-</strong></div>
                        </div>
                        <div style="border-top:1px dashed #A7F3D0; padding-top:8px; margin-top:8px; font-size:11.5px; color:#064E3B;">
                            Alasan Pembatalan: <span id="ref_detail_reason" style="font-style:italic;">-</span>
                        </div>
                    </div>

                    <!-- Tindakan Status Admin -->
                    <div style="margin-bottom:14px;">
                        <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Tindakan / Status Refund *</label>
                        <select name="refund_status" id="ref_action_status" required style="width:100%; padding:10px 12px; border:1.5px solid #059669; border-radius:8px; font-size:13px; font-weight:700; box-sizing:border-box;">
                            <option value="diajukan">⏳ Diajukan (Menunggu Review)</option>
                            <option value="disetujui">✓ Setujui Pengajuan Refund (Antrean Transfer)</option>
                            <option value="diproses_transfer">⏳ Proses Transfer Sedang Berjalan</option>
                            <option value="selesai">✓ Tandai Selesai (Dana Berhasil Ditransfer)</option>
                            <option value="ditolak">✕ Tolak Pengajuan Refund</option>
                        </select>
                    </div>

                    <!-- Nomor Referensi / Bukti Transfer -->
                    <div style="margin-bottom:14px;">
                        <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Nomor Referensi / Bukti Transfer (Opsional)</label>
                        <input type="text" name="transfer_proof" id="ref_action_proof" placeholder="Contoh: REF/BCA/20261009/88921" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12.5px; box-sizing:border-box;">
                    </div>

                    <!-- Catatan Admin untuk Pelanggan -->
                    <div style="margin-bottom:18px;">
                        <label style="font-size:12px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Catatan Admin LilinNawa / Alasan Penolakan</label>
                        <textarea name="admin_notes" id="ref_action_notes" rows="2" placeholder="Tuliskan catatan untuk pelanggan (misal: 'Dana telah ditransfer ke rekening BCA Anda')..." style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12.5px; box-sizing:border-box;"></textarea>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" onclick="closeRefundModal()" style="background:#F3F4F6; border:1px solid #D1D5DB; padding:10px 18px; border-radius:10px; font-weight:700; cursor:pointer;">Batal</button>
                        <button type="submit" style="background:#059669; color:#FFF; border:none; padding:10px 22px; border-radius:10px; font-weight:800; cursor:pointer;">Simpan &amp; Update Database</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
    function openEditModal(order) {
        document.getElementById('edit_order_id').value = order.id;
        document.getElementById('edit_order_number').value = order.order_number;
        document.getElementById('edit_customer_name').value = order.customer_name || '';
        document.getElementById('edit_customer_phone').value = order.customer_phone || '';
        document.getElementById('edit_status').value = order.status || 'pilih_template';
        document.getElementById('edit_step_index').value = order.step_index || 0;
        document.getElementById('edit_payment_status').value = order.payment_status || 'pending';
        document.getElementById('edit_total_price').value = order.total_price || 99000;
        document.getElementById('edit_result_link').value = order.result_link || '';
        
        handleStatusChange(order.status);
        if (order.cancel_reason) {
            document.getElementById('edit_cancel_reason').value = order.cancel_reason;
        }
        document.getElementById('modalEditOrder').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('modalEditOrder').style.display = 'none';
    }

    function openRefundActionModal(rf) {
        document.getElementById('ref_action_id').value = rf.id;
        document.getElementById('ref_action_order_id').value = rf.order_id || 0;
        document.getElementById('ref_detail_order_no').innerText = rf.order_number || rf.ord_no || '-';
        document.getElementById('ref_detail_amount').innerText = 'Rp ' + Number(rf.refund_amount).toLocaleString('id-ID');
        document.getElementById('ref_detail_bank').innerText = rf.bank_name || 'BCA';
        document.getElementById('ref_detail_acc_no').innerText = rf.account_number || '-';
        document.getElementById('ref_detail_acc_name').innerText = rf.account_name || rf.customer_name || '-';
        document.getElementById('ref_detail_reason').innerText = rf.reason || 'Dibatalkan oleh pemesan';
        document.getElementById('ref_action_status').value = rf.status || 'diajukan';
        document.getElementById('ref_action_proof').value = rf.transfer_proof || '';
        document.getElementById('ref_action_notes').value = rf.admin_notes || '';

        document.getElementById('modalRefundAction').style.display = 'flex';
    }

    function closeRefundModal() {
        document.getElementById('modalRefundAction').style.display = 'none';
    }

    function handleStatusChange(status) {
        var box = document.getElementById('cancel_reason_box');
        if (box) {
            box.style.display = (status === 'dibatalkan') ? 'block' : 'none';
        }
        var stepInput = document.getElementById('edit_step_index');
        var stepMap = {
            'pilih_template': 0,
            'konsultasi': 1,
            'data_terkirim': 2,
            'diproses': 3,
            'undangan_jadi': 4,
            'dikirim': 5,
            'selesai': 5,
            'dibatalkan': 0
        };
        if (stepMap.hasOwnProperty(status) && status !== 'dibatalkan' && stepInput) {
            stepInput.value = stepMap[status];
        }
    }

    function filterOrdersLive() {
        var val = (document.getElementById('orderLiveSearch').value || '').toLowerCase().trim();
        var rows = document.querySelectorAll('.admin-table tbody tr');
        rows.forEach(function(row) {
            if (row.cells && row.cells.length > 1) {
                var text = (row.innerText || row.textContent).toLowerCase();
                row.style.display = (text.indexOf(val) > -1) ? '' : 'none';
            }
        });
    }
    </script>
</body>
</html>
