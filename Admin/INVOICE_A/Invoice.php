<?php
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Login/Login.php");
    exit;
}

// Ambil data toko dari database
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

// Parameter invoice / order yang dipilih
$selected_order_number = $_GET['order'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
if (!empty($search)) {
    $search_safe = mysqli_real_escape_string($conn, $search);
    $where .= " AND (o.order_number LIKE '%$search_safe%' OR o.customer_name LIKE '%$search_safe%' OR o.customer_phone LIKE '%$search_safe%')";
}

// Ambil daftar order untuk list invoice
$orders_list_q = mysqli_query($conn, "SELECT o.*, d.nama_acara, d.tanggal_acara 
                                       FROM orders o 
                                       LEFT JOIN order_event_details d ON o.id = d.order_id 
                                       $where 
                                       ORDER BY o.id DESC");

$selected_order = null;
if (!empty($selected_order_number)) {
    $ord_safe = mysqli_real_escape_string($conn, $selected_order_number);
    $sel_q = mysqli_query($conn, "SELECT o.*, d.nama_acara, d.tanggal_acara, d.lokasi_acara, d.alamat_lengkap, d.nama_mempelai_pria, d.nama_mempelai_wanita 
                                  FROM orders o 
                                  LEFT JOIN order_event_details d ON o.id = d.order_id 
                                  WHERE o.order_number = '$ord_safe' LIMIT 1");
    if ($sel_q && mysqli_num_rows($sel_q) > 0) {
        $selected_order = mysqli_fetch_assoc($sel_q);
    }
} elseif ($orders_list_q && mysqli_num_rows($orders_list_q) > 0) {
    // Default ambil yang paling atas
    mysqli_data_seek($orders_list_q, 0);
    $selected_order = mysqli_fetch_assoc($orders_list_q);
    mysqli_data_seek($orders_list_q, 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Pembelian - Admin LilinNawa</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="invoice.css">
    <style>
        @media print {
            body * { visibility: hidden; }
            .printable-invoice, .printable-invoice * { visibility: visible; }
            .printable-invoice { position: absolute; left: 0; top: 0; width: 100%; border: none !important; box-shadow: none !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="admin-wrapper">
    <div class="no-print">
        <?php include '../sidebar.php'; ?>
    </div>

    <main class="admin-main">
        <div class="no-print">
        
            <div class="page-title-box" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                <div>
                    <h2>🧾 Invoice &amp; Faktur Pembelian</h2>
                    <p>Cetak faktur resmi pesanan undangan digital klien LilinNawa, kelola rincian pembayaran &amp; bukti transaksi.</p>
                </div>
                <div style="display:flex; gap:8px;">
                    <?php if ($selected_order): ?>
                    <button onclick="window.print()" class="btn-admin btn-primary" style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                        <span>🖨️</span>
                        <span>Cetak / Download PDF</span>
                    </button>
                    <?php endif; ?>
                    <a href="../MAIN_A/Order.php" class="btn-admin btn-secondary" style="display:flex; align-items:center; gap:6px;">
                        <span>📦</span>
                        <span>Ke Manajemen Order</span>
                    </a>
                </div>
            </div>

            <!-- Filter Pencarian Cepat Invoice (Live Search Real-Time) -->
            <div style="background:#FFF; padding:14px 18px; border-radius:14px; border:1px solid #E5D7B7; margin-bottom:20px; display:flex; gap:12px; align-items:center;">
                <div style="position:relative; flex:1;">
                    <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:14px; color:#9CA3AF;">🔍</span>
                    <input type="text" id="invoiceLiveSearch" oninput="filterInvoicesLive(this.value)" placeholder="Pencarian live: ketik No. Order, Nama Pelanggan, atau No. WhatsApp..." style="width:100%; padding:10px 14px 10px 36px; border:1.5px solid #D1D5DB; border-radius:10px; font-size:12.5px; outline:none; box-sizing:border-box; transition:border-color 0.2s;" onfocus="this.style.borderColor='#701C2F'" onblur="this.style.borderColor='#D1D5DB'">
                </div>
                <button type="button" onclick="document.getElementById('invoiceLiveSearch').value=''; filterInvoicesLive('');" class="btn-admin btn-secondary" style="padding:10px 14px; font-size:12px; cursor:pointer;" title="Bersihkan Pencarian">✕ Reset</button>
            </div>
        </div>

        <div class="invoice-layout" style="display:grid; grid-template-columns: 320px 1fr; gap:20px; align-items:start;">
            <!-- Kolom Kiri: Daftar Invoice Terkini (No-Print) -->
            <div class="no-print table-card" style="padding:16px; max-height:800px; overflow-y:auto;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h4 style="font-size:13px; font-weight:bold; color:#701C2F; margin:0; text-transform:uppercase; letter-spacing:0.5px;">Daftar Pesanan &amp; Invoice</h4>
                    <span id="invoiceCountBadge" style="font-size:10px; background:#FAF3DE; color:#701C2F; font-weight:bold; padding:2px 8px; border-radius:99px; border:1px solid #E5D7B7;"><?= mysqli_num_rows($orders_list_q) ?> Total</span>
                </div>
                
                <div id="noInvoiceMatch" style="display:none; padding:20px; text-align:center; color:#9CA3AF; font-size:12px; background:#F9FAFB; border-radius:10px; border:1px dashed #D1D5DB;">
                    Tidak ditemukan invoice yang cocok dengan pencarian.
                </div>

                <?php if (!$orders_list_q || mysqli_num_rows($orders_list_q) === 0): ?>
                    <div style="padding:20px; text-align:center; color:#9CA3AF; font-size:12px;">
                        Belum ada data pesanan untuk invoice.
                    </div>
                <?php else: ?>
                    <div id="invoiceItemsList" style="display:flex; flex-direction:column; gap:8px;">
                    <?php while($row = mysqli_fetch_assoc($orders_list_q)): ?>
                        <?php 
                            $is_active = $selected_order && ($selected_order['id'] == $row['id']);
                            $is_lunas = ($row['payment_status'] === 'lunas');
                            $search_data = strtolower($row['order_number'] . ' ' . $row['customer_name'] . ' ' . $row['customer_phone'] . ' ' . $row['template_name']);
                        ?>
                        <a href="Invoice.php?order=<?= urlencode($row['order_number']) ?>" 
                           class="invoice-sidebar-item"
                           data-search="<?= htmlspecialchars($search_data) ?>"
                           style="display:block; padding:12px; border-radius:10px; text-decoration:none; transition:all 0.15s ease; border: 1.5px solid <?= $is_active ? '#701C2F' : '#E5E7EB' ?>; background: <?= $is_active ? '#FAF3DE' : '#FFFFFF' ?>;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                <strong style="color:#701C2F; font-size:12px;"><?= htmlspecialchars($row['order_number']) ?></strong>
                                <span style="font-size:10px; font-weight:bold; padding:2px 6px; border-radius:6px; background:<?= $is_lunas ? '#D1FAE5' : '#FEF3C7' ?>; color:<?= $is_lunas ? '#065F46' : '#92400E' ?>;">
                                    <?= $is_lunas ? 'LUNAS' : 'PENDING' ?>
                                </span>
                            </div>
                            <div style="font-size:12px; font-weight:600; color:#1F2937; margin-bottom:2px;"><?= htmlspecialchars($row['customer_name']) ?></div>
                            <div style="font-size:11px; color:#6B7280;"><?= htmlspecialchars($row['template_name'] ?: 'Custom Invitation') ?></div>
                            <div style="font-size:11px; font-weight:bold; color:#059669; margin-top:4px;">Rp <?= number_format($row['total_price'], 0, ',', '.') ?></div>
                        </a>
                    <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Kolom Kanan: Tampilan Lembar Invoice Resmi (Printable) -->
            <div class="printable-invoice" style="background:#FFFFFF; border-radius:18px; border:1px solid #E5D7B7; padding:36px; box-shadow:0 8px 24px rgba(112,28,47,0.06);">
                <?php if (!$selected_order): ?>
                    <div style="padding:60px; text-align:center; color:#9CA3AF;">
                        <span style="font-size:40px; display:block; margin-bottom:12px;">🧾</span>
                        <h3 style="font-size:16px; color:#374151;">Pilih pesanan di sebelah kiri untuk melihat rincian faktur invoice.</h3>
                    </div>
                <?php else: ?>
                    <!-- Header Invoice -->
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #FAF3DE; padding-bottom:24px; margin-bottom:24px;">
                        <div>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:38px; height:38px; border-radius:10px; background:#701C2F; color:#FAF3DE; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:18px;">LN</div>
                                <div>
                                    <h1 style="font-family:'Playfair Display',serif; font-size:22px; color:#701C2F; margin:0; line-height:1.2;"><?= htmlspecialchars($store['store_name']) ?></h1>
                                    <p style="font-size:11px; color:#6B7280; margin:2px 0 0 0;">Spesialis Undangan Digital Web Eksklusif &amp; Modern</p>
                                </div>
                            </div>
                            <div style="font-size:11px; color:#4B5563; margin-top:12px; line-height:1.6;">
                                📍 <?= htmlspecialchars($store['address_studio']) ?><br>
                                💬 WhatsApp: <b><?= htmlspecialchars($store['phone_whatsapp']) ?></b> | ✉️ <?= htmlspecialchars($store['email_official']) ?>
                            </div>
                        </div>

                        <div style="text-align:right;">
                            <div style="font-size:24px; font-weight:900; color:#701C2F; letter-spacing:1px; font-family:'Playfair Display',serif;">INVOICE</div>
                            <div style="font-size:13px; font-weight:bold; color:#1F2937; margin-top:4px;">No: INV-<?= preg_replace('/[^0-9]/', '', $selected_order['order_number']) ?></div>
                            <div style="font-size:11px; color:#6B7280; margin-top:2px;">Tanggal: <?= date('d F Y', strtotime($selected_order['created_at'])) ?></div>
                            <div style="margin-top:10px;">
                                <?php if ($selected_order['payment_status'] === 'lunas'): ?>
                                    <span style="display:inline-block; padding:4px 14px; border-radius:999px; background:#D1FAE5; color:#065F46; font-size:12px; font-weight:800; border:1.5px solid #A7F3D0;">
                                        ✓ LUNAS (PAID)
                                    </span>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:4px 14px; border-radius:999px; background:#FEF3C7; color:#92400E; font-size:12px; font-weight:800; border:1.5px solid #FCD34D;">
                                        ⏳ MENUNGGU PEMBAYARAN
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Pembeli & Acara -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:28px;">
                        <div style="background:#FAF3DE; padding:16px 20px; border-radius:12px; border:1px solid #E5D7B7;">
                            <span style="font-size:10px; font-weight:800; color:#701C2F; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px;">Ditagihkan Kepada (Klien):</span>
                            <div style="font-size:14px; font-weight:bold; color:#1F2937;"><?= htmlspecialchars($selected_order['customer_name']) ?></div>
                            <div style="font-size:12px; color:#4B5563; margin-top:2px;">📱 WhatsApp: <?= htmlspecialchars($selected_order['customer_phone']) ?></div>
                            <div style="font-size:12px; color:#4B5563;">✉️ Email: <?= htmlspecialchars($selected_order['customer_email']) ?></div>
                        </div>

                        <div style="background:#FAF3DE; padding:16px 20px; border-radius:12px; border:1px solid #E5D7B7;">
                            <span style="font-size:10px; font-weight:800; color:#701C2F; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:8px;">Detail Acara Undangan:</span>
                            <div style="font-size:13px; font-weight:bold; color:#1F2937;">
                                <?= htmlspecialchars($selected_order['nama_acara'] ?: ($selected_order['template_name'] ?? 'Undangan Digital')) ?>
                            </div>
                            <?php if(!empty($selected_order['tanggal_acara'])): ?>
                            <div style="font-size:12px; color:#4B5563; margin-top:2px;">📅 Tanggal Acara: <?= date('d M Y', strtotime($selected_order['tanggal_acara'])) ?></div>
                            <?php endif; ?>
                            <?php if(!empty($selected_order['lokasi_acara'])): ?>
                            <div style="font-size:12px; color:#4B5563;">📍 Lokasi: <?= htmlspecialchars($selected_order['lokasi_acara']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rincian Item Invoice -->
                    <table style="width:100%; border-collapse:collapse; margin-bottom:24px; font-size:12px;">
                        <thead>
                            <tr style="background:#701C2F; color:#FFFFFF; text-align:left;">
                                <th style="padding:10px 14px; border-radius:8px 0 0 8px;">Deskripsi Layanan / Item</th>
                                <th style="padding:10px 14px; text-align:center;">Jenis</th>
                                <th style="padding:10px 14px; text-align:center;">Qty</th>
                                <th style="padding:10px 14px; text-align:right; border-radius:0 8px 8px 0;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody style="border-bottom:1px solid #E5E7EB;">
                            <tr style="border-bottom:1px solid #F3F4F6;">
                                <td style="padding:14px;">
                                    <strong style="color:#1F2937; font-size:13px;"><?= htmlspecialchars($selected_order['template_name'] ?: 'Paket Undangan Digital') ?></strong>
                                    <p style="color:#6B7280; font-size:11px; margin:4px 0 0 0;">
                                        Fitur Lengkap: Responsive Mobile, RSVP WhatsApp, Petunjuk Arah Google Maps, Hitung Mundur, Background Musik.
                                    </p>
                                </td>
                                <td style="padding:14px; text-align:center;">
                                    <span style="background:#FAF3DE; color:#701C2F; padding:2px 8px; border-radius:6px; font-weight:bold; font-size:11px;">
                                        <?= strtoupper($selected_order['type'] ?? 'TEMPLATE') ?>
                                    </span>
                                </td>
                                <td style="padding:14px; text-align:center; font-weight:600;">1 Paket</td>
                                <td style="padding:14px; text-align:right; font-weight:bold; color:#1F2937;">
                                    Rp <?= number_format($selected_order['total_price'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align:right; padding:10px 14px; font-weight:600; color:#6B7280;">Subtotal:</td>
                                <td style="text-align:right; padding:10px 14px; font-weight:bold; color:#1F2937;">Rp <?= number_format($selected_order['total_price'], 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align:right; padding:8px 14px; font-weight:600; color:#6B7280;">Biaya Pemeliharaan &amp; Hosting Server:</td>
                                <td style="text-align:right; padding:8px 14px; font-weight:bold; color:#059669;">GRATIS</td>
                            </tr>
                            <tr style="border-top:2px solid #701C2F;">
                                <td colspan="3" style="text-align:right; padding:14px; font-size:14px; font-weight:bold; color:#701C2F;">TOTAL PEMBAYARAN:</td>
                                <td style="text-align:right; padding:14px; font-size:16px; font-weight:900; color:#701C2F;">Rp <?= number_format($selected_order['total_price'], 0, ',', '.') ?></td>
                            </tr>
                        </tfoot>
                    </table>

                    <!-- Rekening Pembayaran Resmi LilinNawa -->
                    <div style="background:#FAF3DE; border:1px solid #E5D7B7; border-radius:12px; padding:16px 20px; margin-bottom:28px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                        <div>
                            <span style="font-size:11px; font-weight:bold; color:#701C2F; text-transform:uppercase;">Metode Pembayaran Resmi:</span>
                            <div style="font-size:13px; font-weight:bold; color:#1F2937; margin-top:2px;">
                                Bank <?= htmlspecialchars($store['bank_name']) ?> : <?= htmlspecialchars($store['bank_account_no']) ?>
                            </div>
                            <div style="font-size:11px; color:#4B5563;">Atas Nama: <b><?= htmlspecialchars($store['bank_account_holder']) ?></b></div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:11px; color:#6B7280;">Konfirmasi Pembayaran Cepat:</span>
                            <div style="font-size:12px; font-weight:bold; color:#059669;">WhatsApp: <?= htmlspecialchars($store['phone_whatsapp']) ?></div>
                        </div>
                    </div>

                    <!-- Footer & Catatan -->
                    <div style="display:flex; justify-content:space-between; align-items:flex-end; font-size:11px; color:#6B7280; border-top:1px solid #E5E7EB; padding-top:16px;">
                        <div>
                            <p style="margin:0;">• Terima kasih atas kepercayaan Anda memilih <b><?= htmlspecialchars($store['store_name']) ?></b>.</p>
                            <p style="margin:2px 0 0 0;">• Faktur ini sah dan diterbitkan secara digital oleh sistem LilinNawa.</p>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-weight:bold; color:#701C2F; margin-bottom:30px;">Hormat Kami,</div>
                            <div style="border-top:1px solid #9CA3AF; padding-top:4px; font-weight:bold; color:#1F2937;">LilinNawa Digital Team</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin.js"></script>
    <script>
    function filterInvoicesLive(query) {
        var val = (query !== undefined ? query : (document.getElementById('invoiceLiveSearch').value || '')).toLowerCase().trim();
        var items = document.querySelectorAll('.invoice-sidebar-item');
        var foundCount = 0;
        
        items.forEach(function(item) {
            var text = (item.getAttribute('data-search') || item.innerText || item.textContent).toLowerCase();
            if (!val || text.indexOf(val) > -1) {
                item.style.display = 'block';
                foundCount++;
            } else {
                item.style.display = 'none';
            }
        });

        var noMatch = document.getElementById('noInvoiceMatch');
        if (noMatch) {
            noMatch.style.display = (foundCount === 0 && val !== '') ? 'block' : 'none';
        }

        var badge = document.getElementById('invoiceCountBadge');
        if (badge) {
            badge.textContent = foundCount + ' Ditemukan';
        }
    }
    </script>
</body>
</html>
