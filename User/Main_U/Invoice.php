<?php
// User/Main_U/Invoice.php - Faktur & Invoice Pembelian Klien LilinNawa
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../Login/Login.php");
    exit;
}

$user_id    = $_SESSION['user_id'] ?? 0;
$user_name  = $_SESSION['name'] ?? 'Pelanggan LilinNawa';
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';

// Ambil info toko
$store_q = @mysqli_query($conn, "SELECT * FROM store_settings WHERE id = 1");
$store = ($store_q && mysqli_num_rows($store_q) > 0) ? mysqli_fetch_assoc($store_q) : [
    'store_name' => 'LilinNawa Digital Invitation',
    'phone_whatsapp' => '081252200386',
    'email_official' => 'admin@lilinawa.com',
    'address_studio' => 'Jl. Terusan Kiara Condong No. 45, Bandung, Jawa Barat',
    'bank_name' => 'BCA',
    'bank_account_no' => '8691522003',
    'bank_account_holder' => 'LilinNawa Studio'
];

$selected_order_number = $_GET['order'] ?? '';

// Ambil semua order milik customer ini
$my_orders_q = mysqli_query($conn, "SELECT o.*, d.nama_acara, d.tanggal_acara 
                                     FROM orders o 
                                     LEFT JOIN order_event_details d ON o.id = d.order_id 
                                     WHERE o.user_id = $user_id OR o.customer_phone = '$user_phone' OR o.customer_email = '$user_email' 
                                     ORDER BY o.id DESC");

$selected_order = null;
if (!empty($selected_order_number)) {
    $ord_safe = mysqli_real_escape_string($conn, $selected_order_number);
    $sel_q = mysqli_query($conn, "SELECT o.*, d.nama_acara, d.tanggal_acara, d.lokasi_acara, d.alamat_lengkap 
                                  FROM orders o 
                                  LEFT JOIN order_event_details d ON o.id = d.order_id 
                                  WHERE o.order_number = '$ord_safe' AND (o.user_id = $user_id OR o.customer_phone = '$user_phone' OR o.customer_email = '$user_email') 
                                  LIMIT 1");
    if ($sel_q && mysqli_num_rows($sel_q) > 0) {
        $selected_order = mysqli_fetch_assoc($sel_q);
    }
} elseif ($my_orders_q && mysqli_num_rows($my_orders_q) > 0) {
    mysqli_data_seek($my_orders_q, 0);
    $selected_order = mysqli_fetch_assoc($my_orders_q);
    mysqli_data_seek($my_orders_q, 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Pembelian Saya - LilinNawa</title>
    <link rel="stylesheet" href="../sidebar.css">
    <style>
        .invoice-card {
            background: #FFFFFF;
            border-radius: 18px;
            border: 1px solid #E5D7B7;
            padding: 36px;
            box-shadow: 0 8px 24px rgba(112, 28, 47, 0.05);
            max-width: 820px;
            margin: 0 auto;
        }
        @media print {
            body * { visibility: hidden; }
            .printable-invoice, .printable-invoice * { visibility: visible; }
            .printable-invoice { position: absolute; left: 0; top: 0; width: 100%; border: none !important; box-shadow: none !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="user-layout">
    <div class="no-print">
        <?php include '../sidebar.php'; ?>
    </div>

    <main class="user-main-content">
        <div class="no-print" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h1 style="font-family:'Playfair Display',serif; font-size:24px; color:#701C2F; margin:0 0 6px 0;">🧾 Invoice &amp; Bukti Pembelian</h1>
                <p style="font-size:13px; color:#6B7280; margin:0;">Faktur pembayaran sah untuk pesanan undangan digital Anda di LilinNawa.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <?php if ($selected_order): ?>
                <button onclick="window.print()" style="padding:10px 18px; background:#701C2F; color:#FFF; border:none; border-radius:12px; font-weight:bold; font-size:12px; cursor:pointer; display:flex; align-items:center; gap:6px;">
                    <span>🖨️</span>
                    <span>Cetak Invoice</span>
                </button>
                <?php endif; ?>
                <a href="Status & TrackingOrder.php" style="padding:10px 16px; background:#FAF3DE; color:#701C2F; border:1px solid #E5D7B7; border-radius:12px; font-weight:bold; font-size:12px; text-decoration:none; display:flex; align-items:center; gap:6px;">
                    <span>📦</span>
                    <span>Tracking Order</span>
                </a>
            </div>
        </div>

        <?php if ($my_orders_q && mysqli_num_rows($my_orders_q) > 1): ?>
        <div class="no-print" style="background:#FFF; border:1px solid #E5D7B7; border-radius:14px; padding:12px 18px; margin-bottom:20px; display:flex; align-items:center; gap:12px; overflow-x:auto;">
            <span style="font-size:12px; font-weight:bold; color:#701C2F; white-space:nowrap;">Pilih Pesanan:</span>
            <?php while($ord_item = mysqli_fetch_assoc($my_orders_q)): ?>
                <a href="Invoice.php?order=<?= urlencode($ord_item['order_number']) ?>" 
                   style="padding:6px 12px; border-radius:8px; font-size:11px; font-weight:bold; text-decoration:none; white-space:nowrap; <?= ($selected_order && $selected_order['id'] == $ord_item['id']) ? 'background:#701C2F; color:#FFF;' : 'background:#FAF3DE; color:#701C2F; border:1px solid #E5D7B7;' ?>">
                    <?= htmlspecialchars($ord_item['order_number']) ?> (<?= $ord_item['payment_status'] === 'lunas' ? 'Lunas' : 'Pending' ?>)
                </a>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <?php if (!$selected_order): ?>
            <div style="background:#FFF; border-radius:18px; border:1px solid #E5D7B7; padding:60px; text-align:center;">
                <span style="font-size:44px; display:block; margin-bottom:12px;">🧾</span>
                <h3 style="color:#1F2937; margin-bottom:6px;">Belum Ada Transaksi atau Pesanan</h3>
                <p style="color:#6B7280; font-size:13px; max-width:400px; margin:0 auto 20px auto;">Silakan pilih tema di Katalog Template untuk membuat pesanan undangan digital Anda.</p>
                <a href="Katalog Template.php" style="display:inline-block; padding:10px 20px; background:#701C2F; color:#FFF; border-radius:12px; text-decoration:none; font-weight:bold; font-size:13px;">
                    🎨 Buka Katalog Template
                </a>
            </div>
        <?php else: ?>
            <div class="printable-invoice invoice-card">
                <!-- Header Invoice -->
                <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #FAF3DE; padding-bottom:24px; margin-bottom:24px;">
                    <div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:36px; height:36px; border-radius:10px; background:#701C2F; color:#FAF3DE; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:16px;">LN</div>
                            <div>
                                <h2 style="font-family:'Playfair Display',serif; font-size:20px; color:#701C2F; margin:0; line-height:1.2;"><?= htmlspecialchars($store['store_name']) ?></h2>
                                <p style="font-size:11px; color:#6B7280; margin:2px 0 0 0;">Spesialis Undangan Digital Modern &amp; Eksklusif</p>
                            </div>
                        </div>
                        <div style="font-size:11px; color:#4B5563; margin-top:12px; line-height:1.6;">
                            📍 <?= htmlspecialchars($store['address_studio']) ?><br>
                            💬 WhatsApp Resmi: <b><?= htmlspecialchars($store['phone_whatsapp']) ?></b>
                        </div>
                    </div>

                    <div style="text-align:right;">
                        <div style="font-size:22px; font-weight:900; color:#701C2F; letter-spacing:1px; font-family:'Playfair Display',serif;">INVOICE</div>
                        <div style="font-size:12px; font-weight:bold; color:#1F2937; margin-top:4px;">No: INV-<?= preg_replace('/[^0-9]/', '', $selected_order['order_number']) ?></div>
                        <div style="font-size:11px; color:#6B7280; margin-top:2px;">Tanggal: <?= date('d F Y', strtotime($selected_order['created_at'])) ?></div>
                        <div style="margin-top:10px;">
                            <?php if ($selected_order['payment_status'] === 'lunas'): ?>
                                <span style="display:inline-block; padding:4px 12px; border-radius:999px; background:#D1FAE5; color:#065F46; font-size:11px; font-weight:800; border:1.5px solid #A7F3D0;">
                                    ✓ LUNAS (PAID)
                                </span>
                            <?php else: ?>
                                <span style="display:inline-block; padding:4px 12px; border-radius:999px; background:#FEF3C7; color:#92400E; font-size:11px; font-weight:800; border:1.5px solid #FCD34D;">
                                    ⏳ MENUNGGU PEMBAYARAN
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Info Customer & Acara -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px;">
                    <div style="background:#FAF3DE; padding:16px; border-radius:12px; border:1px solid #E5D7B7;">
                        <span style="font-size:10px; font-weight:800; color:#701C2F; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;">Pemesan (Klien):</span>
                        <div style="font-size:13px; font-weight:bold; color:#1F2937;"><?= htmlspecialchars($selected_order['customer_name']) ?></div>
                        <div style="font-size:11px; color:#4B5563; margin-top:2px;">📱 <?= htmlspecialchars($selected_order['customer_phone']) ?></div>
                        <div style="font-size:11px; color:#4B5563;">✉️ <?= htmlspecialchars($selected_order['customer_email']) ?></div>
                    </div>

                    <div style="background:#FAF3DE; padding:16px; border-radius:12px; border:1px solid #E5D7B7;">
                        <span style="font-size:10px; font-weight:800; color:#701C2F; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;">Rincian Acara:</span>
                        <div style="font-size:13px; font-weight:bold; color:#1F2937;">
                            <?= htmlspecialchars($selected_order['nama_acara'] ?: ($selected_order['template_name'] ?? 'Undangan Digital')) ?>
                        </div>
                        <?php if(!empty($selected_order['tanggal_acara'])): ?>
                        <div style="font-size:11px; color:#4B5563; margin-top:2px;">📅 Acara: <?= date('d M Y', strtotime($selected_order['tanggal_acara'])) ?></div>
                        <?php endif; ?>
                        <?php if(!empty($selected_order['lokasi_acara'])): ?>
                        <div style="font-size:11px; color:#4B5563;">📍 Lokasi: <?= htmlspecialchars($selected_order['lokasi_acara']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tabel Item Invoice -->
                <table style="width:100%; border-collapse:collapse; margin-bottom:24px; font-size:12px;">
                    <thead>
                        <tr style="background:#701C2F; color:#FFFFFF; text-align:left;">
                            <th style="padding:10px 14px; border-radius:8px 0 0 8px;">Deskripsi Layanan</th>
                            <th style="padding:10px 14px; text-align:center;">Tipe</th>
                            <th style="padding:10px 14px; text-align:right; border-radius:0 8px 8px 0;">Total</th>
                        </tr>
                    </thead>
                    <tbody style="border-bottom:1px solid #E5E7EB;">
                        <tr>
                            <td style="padding:14px;">
                                <strong style="color:#1F2937; font-size:13px;"><?= htmlspecialchars($selected_order['template_name'] ?: 'Paket Undangan Digital') ?></strong>
                                <p style="color:#6B7280; font-size:11px; margin:4px 0 0 0;">
                                    Fitur: RSVP WhatsApp, Lokasi Maps, Countdown Acara, Galeri Foto, Background Musik.
                                </p>
                            </td>
                            <td style="padding:14px; text-align:center;">
                                <span style="background:#FAF3DE; color:#701C2F; padding:2px 8px; border-radius:6px; font-weight:bold; font-size:11px;">
                                    <?= strtoupper($selected_order['type'] ?? 'TEMPLATE') ?>
                                </span>
                            </td>
                            <td style="padding:14px; text-align:right; font-weight:bold; color:#1F2937;">
                                Rp <?= number_format($selected_order['total_price'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="text-align:right; padding:10px 14px; font-weight:600; color:#6B7280;">Subtotal:</td>
                            <td style="text-align:right; padding:10px 14px; font-weight:bold; color:#1F2937;">Rp <?= number_format($selected_order['total_price'], 0, ',', '.') ?></td>
                        </tr>
                        <tr style="border-top:2px solid #701C2F;">
                            <td colspan="2" style="text-align:right; padding:14px; font-size:13px; font-weight:bold; color:#701C2F;">TOTAL:</td>
                            <td style="text-align:right; padding:14px; font-size:16px; font-weight:900; color:#701C2F;">Rp <?= number_format($selected_order['total_price'], 0, ',', '.') ?></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Info Pembayaran -->
                <div style="background:#FAF3DE; border:1px solid #E5D7B7; border-radius:12px; padding:16px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                    <div>
                        <span style="font-size:10px; font-weight:bold; color:#701C2F; text-transform:uppercase;">Tujuan Transfer:</span>
                        <div style="font-size:13px; font-weight:bold; color:#1F2937; margin-top:2px;">
                            Bank <?= htmlspecialchars($store['bank_name']) ?>: <?= htmlspecialchars($store['bank_account_no']) ?>
                        </div>
                        <div style="font-size:11px; color:#4B5563;">a.n. <?= htmlspecialchars($store['bank_account_holder']) ?></div>
                    </div>
                    <div style="text-align:right;">
                        <span style="font-size:10px; color:#6B7280;">Butuh Bantuan?</span>
                        <div style="font-size:12px; font-weight:bold; color:#059669;">WA: <?= htmlspecialchars($store['phone_whatsapp']) ?></div>
                    </div>
                </div>

                <!-- Footer -->
                <div style="text-align:center; font-size:11px; color:#9CA3AF; border-top:1px solid #F3F4F6; padding-top:14px;">
                    Terima kasih telah mempercayakan momen bahagia Anda kepada <b><?= htmlspecialchars($store['store_name']) ?></b>.
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
