<?php
// User/Main_U/Status & TrackingOrder.php - Status & Pelacakan Pengerjaan Undangan LilinNawa
// Mendukung Alur 6-Step, Kotak Penerimaan Link Website, Pengajuan Refund Pelanggan & Form Data Sesuai Jenis Acara
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../Login/Login.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$user_phone = $_SESSION['phone'] ?? '081234567890';
$user_name  = $_SESSION['name'] ?? 'Pelanggan LilinNawa';
$user_email = $_SESSION['email'] ?? 'pelanggan@lilinawa.com';

// Ambil data toko untuk rekening pembayaran
$store = [
    'store_name' => 'LilinNawa Digital Invitation',
    'phone_whatsapp' => '081252200386',
    'email_official' => 'admin@lilinawa.com',
    'address_studio' => 'Jl. Terusan Kiara Condong No. 45, Bandung, Jawa Barat',
    'bank_name' => 'BCA',
    'bank_account_no' => '8691522003',
    'bank_account_holder' => 'LilinNawa Studio',
    'bank_mandiri_no' => '130005220038',
    'bank_bri_no' => '001201005220038'
];
if (!empty($conn)) {
    try {
        $store_res = @mysqli_query($conn, "SELECT * FROM store_settings LIMIT 1");
        if ($store_res && $st = mysqli_fetch_assoc($store_res)) {
            $store = array_merge($store, $st);
        }
    } catch (Throwable $e) {}
}

$success_toast = '';
$error_toast = '';

// Ambil semua order milik customer ini
$orders = [];
if (!empty($conn)) {
    try {
        $q = mysqli_query($conn, "SELECT o.*, d.kategori_acara, d.nama_acara, d.nama_mempelai_pria, d.nama_mempelai_wanita, d.nama_orang_tua, d.tanggal_acara, d.waktu_acara, d.waktu_acara_2, d.lokasi_acara, d.alamat_lengkap, d.link_google_maps, d.info_khusus_1, d.info_khusus_2, d.catatan_khusus 
                                  FROM orders o 
                                  LEFT JOIN order_event_details d ON o.id = d.order_id 
                                  WHERE o.user_id = $user_id OR o.customer_phone = '$user_phone' 
                                  ORDER BY o.id DESC");
        if ($q) {
            while ($row = mysqli_fetch_assoc($q)) {
                $orders[] = $row;
            }
        }
    } catch (Throwable $e) {}
}

// Tentukan order aktif yang sedang dilihat
$selected_order_no = $_GET['order'] ?? '';
$active_order = null;
if ($selected_order_no && count($orders) > 0) {
    foreach ($orders as $ord) {
        if ($ord['order_number'] === $selected_order_no) {
            $active_order = $ord;
            break;
        }
    }
}
if (!$active_order && count($orders) > 0) {
    $active_order = $orders[0];
}

// -------------------------------------------------------------------------
// 1. Tangani Konfirmasi Pembayaran oleh Pelanggan (Tempat Melakukan Pembayaran)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'konfirmasi_pembayaran') {
    $order_id = intval($_POST['order_id']);
    if (!empty($conn) && $order_id > 0) {
        mysqli_query($conn, "UPDATE orders SET payment_status = 'lunas', step_index = GREATEST(step_index, 1) WHERE id = $order_id");
        @mysqli_query($conn, "UPDATE invoices SET payment_status = 'paid', paid_at = NOW() WHERE order_id = $order_id");
        $success_toast = 'Terima kasih! Pembayaran QRIS Anda berhasil dikonfirmasi.';
    }
    if ($active_order && (int)$active_order['id'] === $order_id) {
        $active_order['payment_status'] = 'lunas';
        $active_order['step_index'] = max((int)$active_order['step_index'], 1);
    }
}

// -------------------------------------------------------------------------
// 2. Tangani Pembatalan Pesanan & Pengajuan Refund Pelanggan
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batal_pesanan') {
    $order_id = (int)$_POST['order_id'];
    $alasan   = mysqli_real_escape_string($conn, trim($_POST['alasan_batal'] ?? 'Dibatalkan oleh pemesan'));
    $catatan  = mysqli_real_escape_string($conn, trim($_POST['catatan_batal'] ?? ''));

    // Rekening refund (jika pesanan sudah lunas)
    $bank_name      = mysqli_real_escape_string($conn, trim($_POST['bank_name'] ?? 'BCA'));
    $account_number = mysqli_real_escape_string($conn, trim($_POST['account_number'] ?? ''));
    $account_name   = mysqli_real_escape_string($conn, trim($_POST['account_name'] ?? ''));

    $target_ord = null;
    foreach ($orders as $o) {
        if ((int)$o['id'] === $order_id) {
            $target_ord = $o;
            break;
        }
    }

    if ($target_ord) {
        $step_idx = (int)($target_ord['step_index'] ?? 0);
        $curr_stat = $target_ord['status'] ?? 'pilih_template';

        // Aturan Resmi: Mulai Step 4 (proses pengerjaan) TIDAK BISA DIBATALKAN
        if ($step_idx >= 3 || in_array($curr_stat, ['diproses', 'undangan_jadi', 'dikirim', 'selesai'])) {
            $error_toast = 'Pesanan tidak dapat dibatalkan karena telah memasuki Step 4 (Proses Pengerjaan oleh Desainer).';
        } else {
            $is_paid = ($target_ord['payment_status'] === 'lunas');

            if ($is_paid) {
                // JIKA SUDAH SELESAI MELAKUKAN PEMBAYARAN NAMUN MELAKUKAN PEMBATALAN:
                // SIMPAN PENGAJUAN REFUND KE TABEL refund_requests & UPDATE MySQL!
                $total_p = (float)($target_ord['total_price'] ?? 0);
                $ord_no  = mysqli_real_escape_string($conn, $target_ord['order_number']);
                $c_name  = mysqli_real_escape_string($conn, $target_ord['customer_name']);
                $c_phone = mysqli_real_escape_string($conn, $target_ord['customer_phone']);
                $c_email = mysqli_real_escape_string($conn, $target_ord['customer_email'] ?? '');

                if (!empty($conn)) {
                    @mysqli_query($conn, "INSERT INTO refund_requests 
                        (order_id, order_number, user_id, customer_name, customer_phone, customer_email, refund_amount, bank_name, account_number, account_name, reason, notes, status, created_at)
                        VALUES 
                        ($order_id, '$ord_no', $user_id, '$c_name', '$c_phone', '$c_email', $total_p, '$bank_name', '$account_number', '$account_name', '$alasan', '$catatan', 'diajukan', NOW())");
                    
                    mysqli_query($conn, "UPDATE orders SET 
                        status = 'dibatalkan', 
                        refund_status = 'diajukan', 
                        refund_amount = $total_p, 
                        cancel_reason = '$alasan', 
                        cancel_details = '$catatan', 
                        cancelled_at = NOW() 
                        WHERE id = $order_id");

                    @mysqli_query($conn, "UPDATE invoices SET payment_status = 'cancelled' WHERE order_id = $order_id");
                }

                if ($active_order && (int)$active_order['id'] === $order_id) {
                    $active_order['status'] = 'dibatalkan';
                    $active_order['refund_status'] = 'diajukan';
                    $active_order['refund_amount'] = $total_p;
                    $active_order['cancel_reason'] = $alasan;
                    $active_order['cancel_details'] = $catatan;
                    $active_order['refund_bank_name'] = $bank_name;
                    $active_order['refund_account_no'] = $account_number;
                    $active_order['refund_account_name'] = $account_name;
                }

                $success_toast = '✓ Pesanan ' . $target_ord['order_number'] . ' berhasil dibatalkan dan Pengajuan Refund sebesar Rp ' . number_format($total_p, 0, ',', '.') . ' ke rekening ' . htmlspecialchars($bank_name) . ' (' . htmlspecialchars($account_number) . ') telah berhasil disimpan ke database!';
            } else {
                // Belum bayar: batalkan biasa tanpa refund
                if (!empty($conn)) {
                    mysqli_query($conn, "UPDATE orders SET status = 'dibatalkan', cancel_reason = '$alasan', cancel_details = '$catatan', cancelled_at = NOW() WHERE id = $order_id");
                    @mysqli_query($conn, "UPDATE invoices SET payment_status = 'cancelled' WHERE order_id = $order_id");
                }
                if ($active_order && (int)$active_order['id'] === $order_id) {
                    $active_order['status'] = 'dibatalkan';
                    $active_order['cancel_reason'] = $alasan;
                    $active_order['cancel_details'] = $catatan;
                }
                $success_toast = 'Pesanan ' . $target_ord['order_number'] . ' berhasil dibatalkan.';
            }
        }
    }
}

// -------------------------------------------------------------------------
// 3. Tangani Pengisian / Kelengkapan Data Acara Sesuai Kategori
// Aturan: Berhenti di Step 3 jika belum lengkap, jika sudah lengkap all langsung ke Step 4!
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simpan_acara') {
    $order_id       = (int)$_POST['order_id'];
    $kategori_acara = mysqli_real_escape_string($conn, $_POST['kategori_acara'] ?? 'Pernikahan');
    $nama_acara     = mysqli_real_escape_string($conn, trim($_POST['nama_acara'] ?? ''));
    $mempelai_p     = mysqli_real_escape_string($conn, trim($_POST['nama_mempelai_pria'] ?? ''));
    $mempelai_w     = mysqli_real_escape_string($conn, trim($_POST['nama_mempelai_wanita'] ?? ''));
    $nama_ortu      = mysqli_real_escape_string($conn, trim($_POST['nama_orang_tua'] ?? ''));
    $tgl_acara      = mysqli_real_escape_string($conn, trim($_POST['tanggal_acara'] ?? ''));
    $wkt_acara      = mysqli_real_escape_string($conn, trim($_POST['waktu_acara'] ?? ''));
    $wkt_resepsi    = mysqli_real_escape_string($conn, trim($_POST['waktu_acara_2'] ?? ''));
    $lokasi         = mysqli_real_escape_string($conn, trim($_POST['lokasi_acara'] ?? ''));
    $alamat         = mysqli_real_escape_string($conn, trim($_POST['alamat_lengkap'] ?? ''));
    $maps           = mysqli_real_escape_string($conn, trim($_POST['link_google_maps'] ?? ''));
    $catatan        = mysqli_real_escape_string($conn, trim($_POST['catatan_khusus'] ?? ''));
    $info1          = mysqli_real_escape_string($conn, trim($_POST['info_khusus_1'] ?? ''));
    $info2          = mysqli_real_escape_string($conn, trim($_POST['info_khusus_2'] ?? ''));

    // Validasi Kelengkapan Data Acara Sesuai Kebutuhan Jenis Undangan
    $missing = [];
    $cat_lower = strtolower($kategori_acara);

    if (strpos($cat_lower, 'nikah') !== false) {
        if (empty($nama_acara)) $missing[] = 'Nama Acara';
        if (empty($mempelai_p)) $missing[] = 'Mempelai Pria';
        if (empty($mempelai_w)) $missing[] = 'Mempelai Wanita';
        if (empty($tgl_acara))  $missing[] = 'Tanggal Acara';
        if (empty($lokasi))     $missing[] = 'Lokasi / Gedung Acara';
    } elseif (strpos($cat_lower, 'ulang') !== false) {
        if (empty($nama_acara) && empty($info1)) $missing[] = 'Nama yang Berulang Tahun';
        if (empty($tgl_acara)) $missing[] = 'Tanggal Acara';
        if (empty($lokasi))    $missing[] = 'Lokasi Acara';
    } elseif (strpos($cat_lower, 'khitan') !== false) {
        if (empty($nama_acara) && empty($info1)) $missing[] = 'Nama Anak Khitan';
        if (empty($tgl_acara)) $missing[] = 'Tanggal Acara';
        if (empty($lokasi))    $missing[] = 'Lokasi Acara';
    } elseif (strpos($cat_lower, 'aqiqah') !== false) {
        if (empty($nama_acara) && empty($info1)) $missing[] = 'Nama Bayi';
        if (empty($tgl_acara)) $missing[] = 'Tanggal Acara';
        if (empty($lokasi))    $missing[] = 'Lokasi Acara';
    } else {
        if (empty($nama_acara)) $missing[] = 'Nama Acara';
        if (empty($tgl_acara))  $missing[] = 'Tanggal Acara';
        if (empty($lokasi))     $missing[] = 'Lokasi Acara';
    }

    $is_complete = (count($missing) === 0);

    if (!empty($conn)) {
        try {
            $chk = mysqli_query($conn, "SELECT id FROM order_event_details WHERE order_id = $order_id");
            if ($chk && mysqli_num_rows($chk) > 0) {
                mysqli_query($conn, "UPDATE order_event_details SET 
                    kategori_acara = '$kategori_acara',
                    nama_acara = '$nama_acara',
                    nama_mempelai_pria = '$mempelai_p',
                    nama_mempelai_wanita = '$mempelai_w',
                    nama_orang_tua = '$nama_ortu',
                    tanggal_acara = '$tgl_acara',
                    waktu_acara = '$wkt_acara',
                    waktu_acara_2 = '$wkt_resepsi',
                    lokasi_acara = '$lokasi',
                    alamat_lengkap = '$alamat',
                    link_google_maps = '$maps',
                    info_khusus_1 = '$info1',
                    info_khusus_2 = '$info2',
                    catatan_khusus = '$catatan'
                    WHERE order_id = $order_id");
            } else {
                mysqli_query($conn, "INSERT INTO order_event_details 
                    (order_id, kategori_acara, nama_acara, nama_mempelai_pria, nama_mempelai_wanita, nama_orang_tua, tanggal_acara, waktu_acara, waktu_acara_2, lokasi_acara, alamat_lengkap, link_google_maps, info_khusus_1, info_khusus_2, catatan_khusus)
                    VALUES 
                    ($order_id, '$kategori_acara', '$nama_acara', '$mempelai_p', '$mempelai_w', '$nama_ortu', '$tgl_acara', '$wkt_acara', '$wkt_resepsi', '$lokasi', '$alamat', '$maps', '$info1', '$info2', '$catatan')");
            }

            if ($is_complete) {
                // JIKA SUDAH LENGKAP ALL: LANGSUNG KE STEP 4 (Proses Pengerjaan)!
                mysqli_query($conn, "UPDATE orders SET status = 'diproses', step_index = GREATEST(step_index, 3) WHERE id = $order_id");
            } else {
                // BERHENTI DI STEP 3 JIKA ADA DATA YANG BELUM LENGKAP
                mysqli_query($conn, "UPDATE orders SET status = 'data_terkirim', step_index = 2 WHERE id = $order_id");
            }
        } catch (Throwable $e) {}
    }

    if ($active_order && (int)$active_order['id'] === $order_id) {
        $active_order['kategori_acara'] = $kategori_acara;
        $active_order['nama_acara'] = $nama_acara;
        $active_order['nama_mempelai_pria'] = $mempelai_p;
        $active_order['nama_mempelai_wanita'] = $mempelai_w;
        $active_order['nama_orang_tua'] = $nama_ortu;
        $active_order['tanggal_acara'] = $tgl_acara;
        $active_order['waktu_acara'] = $wkt_acara;
        $active_order['lokasi_acara'] = $lokasi;
        $active_order['alamat_lengkap'] = $alamat;
        $active_order['link_google_maps'] = $maps;
        $active_order['catatan_khusus'] = $catatan;
        $active_order['info_khusus_1'] = $info1;
        $active_order['info_khusus_2'] = $info2;

        if ($is_complete) {
            $active_order['status'] = 'diproses';
            $active_order['step_index'] = max((int)$active_order['step_index'], 3);
            $success_toast = '✓ Rincian data acara telah lengkap 100%! Pesanan langsung otomatis masuk ke STEP 4 (Proses Pengerjaan Desainer LilinNawa). Pembatalan pesanan kini dikunci.';
        } else {
            $active_order['status'] = 'data_terkirim';
            $active_order['step_index'] = 2;
            $error_toast = '⚠️ Data tersimpan di Step 3, namun masih belum lengkap (' . implode(', ', $missing) . ' belum terisi). Pesanan berhenti di Step 3 sampai data lengkap.';
        }
    }
}

// -------------------------------------------------------------------------
// Logika Kelayakan Pembatalan Pesanan Sesuai Kebijakan:
// Mulai Step 4 (proses pengerjaan) TIDAK BISA melakukan pembatalan!
// -------------------------------------------------------------------------
$can_cancel = false;
$is_locked_at_work = false;
if ($active_order) {
    $step_val = (int)($active_order['step_index'] ?? 0);
    $stat_val = $active_order['status'] ?? 'pilih_template';

    if ($step_val >= 3 || in_array($stat_val, ['diproses', 'undangan_jadi', 'dikirim', 'selesai'])) {
        $is_locked_at_work = true;
        $can_cancel = false;
    } elseif ($stat_val !== 'dibatalkan' && $step_val <= 2) {
        $can_cancel = true;
    }
}

// Ambil data refund jika ada
$active_refund = null;
if (!empty($conn) && $active_order && ($active_order['refund_status'] ?? 'none') !== 'none') {
    $r_q = @mysqli_query($conn, "SELECT * FROM refund_requests WHERE order_id = " . (int)$active_order['id'] . " ORDER BY id DESC LIMIT 1");
    if ($r_q && $rf = mysqli_fetch_assoc($r_q)) {
        $active_refund = $rf;
    }
}

$is_cancelled = $active_order
    && (($active_order['status'] ?? '') === 'dibatalkan');

$has_refund = $active_order
    && !in_array(
        ($active_order['refund_status'] ?? 'none'),
        ['', 'none', 'NULL'],
        true
    );

$can_edit_acara = $active_order
    && !$is_cancelled
    && !$has_refund
    && !$is_locked_at_work;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status &amp; Pelacakan Order - LilinNawa</title>
    <link rel="stylesheet" href="../sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="status_trackingorder.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .modal-content {
            background: #FFFFFF;
            width: 100%;
            max-width: 620px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            box-sizing: border-box;
        }
        .btn-action-pill {
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        .toast-banner {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .toast-success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        .toast-warning {
            background: #FFFBEB;
            color: #92400E;
            border: 1px solid #FDE68A;
        }
    </style>
</head>
<body style="margin:0; font-family:'Plus Jakarta Sans',sans-serif; background-color:#F5F2EA; color:#1F2937;">
    <?php include '../sidebar.php'; ?>

    <main class="user-main-content" style="
            padding: 24px;
            width: calc(100% - 290px);
            max-width: none;
            margin-left: 290px;
            margin-right: 0;
            box-sizing: border-box;
            min-width: 0;
        ">
        
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px;">
            <div>
                <h1 style="font-family:'Playfair Display',serif; font-size:26px; color:#111827; margin:0;">
                    Status &amp; Pelacakan Undangan Digital
                </h1>
                <p style="font-size:13px; color:#6B7280; margin:4px 0 0;">
                    Pantau tahapan pengerjaan undangan, kelengkapan data acara, pembayaran, dan penerimaan link website resmi Anda.
                </p>
            </div>
        </div>


        <?php if ($success_toast): ?>
        <div class="toast-banner toast-success">
            <span style="font-size:18px;">✓</span>
            <div><?= htmlspecialchars($success_toast) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($error_toast): ?>
        <div class="toast-banner toast-warning">
            <span style="font-size:18px;">⚠️</span>
            <div><?= htmlspecialchars($error_toast) ?></div>
        </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
        <div style="background:#FFF; padding:48px 24px; text-align:center; border-radius:20px; border:1px solid #E5E7EB;">
            <div style="font-size:48px; margin-bottom:12px;">📦</div>
            <h3 style="font-family:'Playfair Display',serif; font-size:22px; color:#111827;">Belum Ada Pesanan Aktif</h3>
            <p style="color:#6B7280; font-size:13px; margin:8px 0 20px;">Silakan pilih desain di Katalog Template untuk memulai pemesanan undangan digital Anda.</p>
            <a href="Katalog Template.php" class="btn-action-pill" style="background:#701C2F; color:#FFF; padding:12px 24px; font-size:13px;">Pilih Template Desain 🚀</a>
        </div>
        <?php else: ?>

        <!-- Selector Tab Order Jika Customer Punya Beberapa Pesanan -->
        <div class="order-selector-tabs" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
            <?php foreach ($orders as $ord): ?>
            <a href="Status &amp; TrackingOrder.php?order=<?= urlencode($ord['order_number']) ?>" class="order-tab-pill <?= $ord['order_number'] === $active_order['order_number'] ? 'active' : '' ?>" style="padding:10px 18px; border-radius:14px; font-size:12.5px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:6px; border:1px solid <?= $ord['order_number'] === $active_order['order_number'] ? '#701C2F' : '#E5E7EB' ?>; background:<?= $ord['order_number'] === $active_order['order_number'] ? '#701C2F' : '#FFF' ?>; color:<?= $ord['order_number'] === $active_order['order_number'] ? '#FFF' : '#374151' ?>;">
                <span><?= htmlspecialchars($ord['order_number']) ?></span>
                <span style="font-size:11px; opacity:0.85;">(<?= htmlspecialchars($ord['template_name']) ?>)</span>
                <?php if (!empty($ord['result_link'])): ?>
                    <span style="background:#059669; color:#FFF; font-size:9px; padding:1px 6px; border-radius:99px;">Web Jadi ↗</span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php 
        $curr_step = (int)($active_order['step_index'] ?? 0);
        $curr_status = $active_order['status'] ?? 'pilih_template';
        $is_cancelled = ($curr_status === 'dibatalkan');
        ?>

        <!-- KARTU UTAMA TRACKING ORDER -->
        <div class="tracking-card" style="background:#FFFFFF; border-radius:20px; border:1px solid #E5E7EB; padding:24px; box-shadow:0 4px 16px rgba(0,0,0,0.03);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; border-bottom:1px solid #E5E7EB; padding-bottom:18px;">
                <div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:24px; font-weight:800; color:#701C2F; font-family:'Playfair Display',serif;">
                            <?= htmlspecialchars($active_order['order_number']) ?>
                        </span>
                        <span style="background:#FAF3DE; color:#701C2F; font-size:11px; font-weight:800; padding:3px 8px; border-radius:6px; border:1px solid #E5D7B7;">
                            <?= htmlspecialchars($active_order['kategori_acara'] ?? 'Pernikahan') ?>
                        </span>
                    </div>
                    <div style="font-size:13px; color:#4B5563; margin-top:4px;">
                        Paket: <strong><?= htmlspecialchars($active_order['template_name']) ?></strong> &bull; Total: <strong>Rp <?= number_format($active_order['total_price'], 0, ',', '.') ?></strong>
                        &bull; Status Pembayaran: 
                        <?php if($active_order['payment_status'] === 'lunas'): ?>
                            <strong style="color:#059669;">LUNAS ✓</strong>
                        <?php else: ?>
                            <strong style="color:#D97706;">MENUNGGU PEMBAYARAN ⏳</strong>
                        <?php endif; ?>
                    </div>
                

                <!-- TOMBOL-TOMBOL AKSI: PEMBAYARAN, INVOICE, EDIT ACARA, BATALKAN -->
                <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                    <!-- 1. PEMBAYARAN QRIS JIKA BELUM BAYAR & BELUM REFUND/BATAL -->
                    <?php if($active_order['payment_status'] !== 'lunas' && !$is_cancelled && !$has_refund): ?>
                    <button type="button" onclick="openModal('modalPembayaran')" class="btn-action-pill" style="background:#059669; color:#FFFFFF; box-shadow:0 4px 12px rgba(5, 150, 105, 0.25);">
                        💳 Bayar Sekarang / QRIS
                    </button>
                    <?php endif; ?>

                    <!-- 2. INVOICE PEMBELIAN (Desain Sesuai Spesifikasi Foto Pengguna) -->
                    <button type="button" onclick="openModal('modalInvoice')" class="btn-action-pill" style="background:#FAF3DE; color:#701C2F; border:1px solid #E2D4B0; font-weight:700;">
                        🧾 Invoice Pembelian
                    </button>

                    <!-- 3. EDIT DATA ACARA (Jika sudah mengajukan refund, otomatis dikunci) -->
                    <?php if ($can_edit_acara): ?>
                    <button type="button" onclick="openModal('modalEditAcara')" class="btn-action-pill" style="background:#FFFFFF; color:#374151; border:1px solid #D1D5DB;">
                        ✏️ Edit Data Acara
                    </button>
                    <?php elseif ($has_refund): ?>
                    <div style="background:#F3F4F6; color:#9CA3AF; border:1px solid #E5E7EB; padding:8px 12px; border-radius:10px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:5px;" title="Data acara tidak dapat diubah lagi karena pesanan dalam proses pengajuan refund.">
                        🔒 Edit Data Acara Terkunci (Dalam Proses Refund)
                    </div>
                    <?php endif; ?>

                    <!-- 4. PEMBATALAN PESANAN & PENGAJUAN REFUND -->
                    <?php if ($can_cancel): ?>
                    <button type="button" onclick="openModal('modalBatalPesanan')" class="btn-action-pill" style="background:#FFF1F2; color:#BE123C; border:1px solid #FECDD3;">
                        🚫 <?= $active_order['payment_status'] === 'lunas' ? 'Batalkan & Ajukan Refund 💸' : 'Batalkan Pesanan' ?>
                    </button>
                    <?php elseif ($has_refund): ?>
                    <div style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; padding:8px 12px; border-radius:10px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:5px;">
                        ✓ Refund Sedang Berjalan (Tidak Dapat Mengajukan Ulang)
                    </div>
                    <?php elseif ($is_locked_at_work && !$is_cancelled): ?>
                    <div style="background:#F3F4F6; color:#6B7280; border:1px solid #D1D5DB; padding:8px 12px; border-radius:10px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:5px;">
                        🔒 Pengerjaan Dimulai (Mulai Step 4 Tidak Bisa Dibatalkan)
                    </div>
                    <?php endif; ?>
                </div>
            </div>
</div>
            </div>

            <?php if ($is_cancelled): ?>
            <!-- BANNER DIBATALKAN -->
            <div style="background:#FFF1F2; border:1.5px solid #FECDD3; border-radius:16px; padding:18px; margin:20px 0; color:#9F1239;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:20px;">⚠️</span>
                    <strong style="font-size:15px;">Pesanan Ini Telah Dibatalkan</strong>
                </div>
                <p style="font-size:12px; margin:6px 0 0 0; line-height:1.5;">
                    Alasan: <strong><?= htmlspecialchars($active_order['cancel_reason'] ?? 'Dibatalkan oleh pemesan') ?></strong>
                    <?php if (!empty($active_order['cancel_details'])): ?>
                        (<?= htmlspecialchars($active_order['cancel_details']) ?>)
                    <?php endif; ?>
                </p>
                <div style="font-size:11px; color:#BE123C; margin-top:6px;">
                    Waktu Pembatalan: <?= $active_order['cancelled_at'] ?? 'Baru saja' ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- KOTAK STATUS PELACAKAN REFUND PELANGGAN JIKA ADA PENGAJUAN REFUND -->
            <?php if (($active_order['refund_status'] ?? 'none') !== 'none'): 
                $r_stat = $active_order['refund_status'] ?? 'diajukan';
                $r_bank = $active_refund['bank_name'] ?? ($active_order['refund_bank_name'] ?? 'BCA');
                $r_rek  = $active_refund['account_number'] ?? ($active_order['refund_account_no'] ?? '-');
                $r_an   = $active_refund['account_name'] ?? ($active_order['refund_account_name'] ?? $active_order['customer_name']);
                $r_nom  = (float)($active_order['refund_amount'] ?? $active_order['total_price']);
            ?>
            <div style="<?php
    if ($r_stat === 'diajukan') {
        echo 'background:#FFFBEB; border:2px solid #F59E0B; color:#92400E;';
    } elseif ($r_stat === 'disetujui') {
        echo 'background:#EFF6FF; border:2px solid #3B82F6; color:#1E40AF;';
    } elseif ($r_stat === 'diproses_transfer') {
        echo 'background:#F5F3FF; border:2px solid #8B5CF6; color:#5B21B6;';
    } elseif ($r_stat === 'selesai') {
        echo 'background:#ECFDF5; border:2px solid #10B981; color:#064E3B;';
    } elseif ($r_stat === 'ditolak') {
        echo 'background:#FFF1F2; border:2px solid #F43F5E; color:#9F1239;';
    } else {
        echo 'background:#F8FAFC; border:2px solid #CBD5E1; color:#334155;';
    }
?> border-radius:16px; padding:20px; margin:20px 0;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; border-bottom:1.5px solid rgba(0,0,0,0.08); padding-bottom:14px; margin-bottom:16px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span style="font-size:26px;">💸</span>
                        <div>
                            <strong style="font-size:16px; display:block; font-weight:800;">Pelacakan Status Pengajuan Refund Pelanggan</strong>
                            <div style="margin-top:4px;">
                                <?php if ($r_stat === 'diajukan'): ?>
                                    <span style="background:#FEF3C7; color:#92400E; font-size:11px; font-weight:800; padding:4px 10px; border-radius:99px; border:1px solid #FDE68A; display:inline-flex; align-items:center; gap:5px;">
                                        <span style="width:6px; height:6px; background:#F59E0B; border-radius:50%;"></span>
                                        TAHAP 1: FORM DIAJUKAN (MENUNGGU VERIFIKASI ADMIN)
                                    </span>
                                <?php elseif ($r_stat === 'disetujui'): ?>
                                    <span style="background:#DBEAFE; color:#1E40AF; font-size:11px; font-weight:800; padding:4px 10px; border-radius:99px; border:1px solid #BFDBFE; display:inline-flex; align-items:center; gap:5px;">
                                        <span style="width:6px; height:6px; background:#2563EB; border-radius:50%;"></span>
                                        TAHAP 2: DISETUJUI ADMIN (MASUK ANTREAN TRANSFER)
                                    </span>
                                <?php elseif ($r_stat === 'diproses_transfer'): ?>
                                    <span style="background:#EDE9FE; color:#5B21B6; font-size:11px; font-weight:800; padding:4px 10px; border-radius:99px; border:1px solid #DDD6FE; display:inline-flex; align-items:center; gap:5px;">
                                        <span style="width:6px; height:6px; background:#7C3AED; border-radius:50%;"></span>
                                        TAHAP 3: SEDANG DITRANSFER OLEH BAGIAN KEUANGAN
                                    </span>
                                <?php elseif ($r_stat === 'selesai'): ?>
                                    <span style="background:#D1FAE5; color:#065F46; font-size:11px; font-weight:800; padding:4px 10px; border-radius:99px; border:1px solid #A7F3D0; display:inline-flex; align-items:center; gap:5px;">
                                        <span style="width:6px; height:6px; background:#10B981; border-radius:50%;"></span>
                                        TAHAP 4: ✓ SELESAI (DANA BERHASIL DITRANSFER KE REKENING ANDA)
                                    </span>
                                <?php elseif ($r_stat === 'ditolak'): ?>
                                    <span style="background:#FFE4E6; color:#9F1239; font-size:11px; font-weight:800; padding:4px 10px; border-radius:99px; border:1px solid #FECDD3; display:inline-flex; align-items:center; gap:5px;">
                                        <span style="width:6px; height:6px; background:#F43F5E; border-radius:50%;"></span>
                                        PENGAJUAN REFUND DITOLAK OLEH ADMIN
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div style="font-size:18px; font-weight:800; font-family:'Playfair Display',serif; color:#701C2F; background:#FFF; padding:6px 14px; border-radius:10px; border:1px solid #E5E7EB;">
                        Rp <?= number_format($r_nom, 0, ',', '.') ?>
                    </div>
                </div>
                <!-- Stepper Refund (Warna Tiap Status Dibedakan Jelas Sesuai Permintaan User) -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(130px, 1fr)); gap:10px; margin-bottom:14px; text-align:center;">
                    <!-- 1. Diajukan (Amber / Oranye) -->
                    <div style="padding:12px 10px; border-radius:12px; font-size:11px; transition:all 0.2s; <?php
                        if ($r_stat === 'diajukan') {
                            echo 'background:#FEF3C7; border:2px solid #F59E0B; box-shadow:0 0 0 3px rgba(245,158,11,0.2);';
                        } elseif (in_array($r_stat, ['disetujui', 'diproses_transfer', 'selesai'])) {
                            echo 'background:#FDF6E2; border:1.5px solid #FCD34D; opacity:0.95;';
                        } else {
                            echo 'background:#FFF; border:1px solid #E5E7EB;';
                        }
                    ?>">
                        <div style="width:22px; height:22px; border-radius:50%; margin:0 auto 6px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; <?php
                            if ($r_stat === 'diajukan') {
                                echo 'background:#F59E0B; color:#FFF;';
                            } elseif (in_array($r_stat, ['disetujui', 'diproses_transfer', 'selesai'])) {
                                echo 'background:#D97706; color:#FFF;';
                            } else {
                                echo 'background:#E5E7EB; color:#9CA3AF;';
                            }
                        ?>"><?= in_array($r_stat, ['disetujui', 'diproses_transfer', 'selesai']) ? '✓' : '1' ?></div>
                        <span style="font-weight:800; color:#B45309; display:block; font-size:12px;">1. Diajukan</span>
                        <small style="color:#92400E; font-size:10.5px; font-weight:600;">Form Terkirim</small>
                    </div>

                    <!-- 2. Disetujui (Biru Langit / Blue) -->
                    <div style="padding:12px 10px; border-radius:12px; font-size:11px; transition:all 0.2s; <?php
                        if ($r_stat === 'disetujui') {
                            echo 'background:#DBEAFE; border:2px solid #2563EB; box-shadow:0 0 0 3px rgba(37,99,235,0.2);';
                        } elseif (in_array($r_stat, ['diproses_transfer', 'selesai'])) {
                            echo 'background:#EFF6FF; border:1.5px solid #93C5FD; opacity:0.95;';
                        } else {
                            echo 'background:#FFF; border:1px solid #E5E7EB;';
                        }
                    ?>">
                        <div style="width:22px; height:22px; border-radius:50%; margin:0 auto 6px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; <?php
                            if ($r_stat === 'disetujui') {
                                echo 'background:#2563EB; color:#FFF;';
                            } elseif (in_array($r_stat, ['diproses_transfer', 'selesai'])) {
                                echo 'background:#1D4ED8; color:#FFF;';
                            } else {
                                echo 'background:#E5E7EB; color:#9CA3AF;';
                            }
                        ?>"><?= in_array($r_stat, ['diproses_transfer', 'selesai']) ? '✓' : '2' ?></div>
                        <span style="font-weight:800; color:#1E40AF; display:block; font-size:12px;">2. Disetujui</span>
                        <small style="color:#1D4ED8; font-size:10.5px; font-weight:600;">Verifikasi Admin</small>
                    </div>

                    <!-- 3. Proses Transfer (Ungu / Indigo) -->
                    <div style="padding:12px 10px; border-radius:12px; font-size:11px; transition:all 0.2s; <?php
                        if ($r_stat === 'diproses_transfer') {
                            echo 'background:#EDE9FE; border:2px solid #7C3AED; box-shadow:0 0 0 3px rgba(124,58,237,0.2);';
                        } elseif ($r_stat === 'selesai') {
                            echo 'background:#F5F3FF; border:1.5px solid #C4B5FD; opacity:0.95;';
                        } else {
                            echo 'background:#FFF; border:1px solid #E5E7EB;';
                        }
                    ?>">
                        <div style="width:22px; height:22px; border-radius:50%; margin:0 auto 6px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; <?php
                            if ($r_stat === 'diproses_transfer') {
                                echo 'background:#7C3AED; color:#FFF;';
                            } elseif ($r_stat === 'selesai') {
                                echo 'background:#6D28D9; color:#FFF;';
                            } else {
                                echo 'background:#E5E7EB; color:#9CA3AF;';
                            }
                        ?>"><?= $r_stat === 'selesai' ? '✓' : '3' ?></div>
                        <span style="font-weight:800; color:#6D28D9; display:block; font-size:12px;">3. Proses Transfer</span>
                        <small style="color:#5B21B6; font-size:10.5px; font-weight:600;">Bagian Keuangan</small>
                    </div>

                    <!-- 4. Selesai (Hijau Sukses / Emerald) -->
                    <div style="padding:12px 10px; border-radius:12px; font-size:11px; transition:all 0.2s; <?php
                        if ($r_stat === 'selesai') {
                            echo 'background:#D1FAE5; border:2px solid #059669; box-shadow:0 0 0 3px rgba(5,150,105,0.2);';
                        } else {
                            echo 'background:#FFF; border:1px solid #E5E7EB;';
                        }
                    ?>">
                        <div style="width:22px; height:22px; border-radius:50%; margin:0 auto 6px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:800; <?php
                            if ($r_stat === 'selesai') {
                                echo 'background:#059669; color:#FFF;';
                            } else {
                                echo 'background:#E5E7EB; color:#9CA3AF;';
                            }
                        ?>"><?= $r_stat === 'selesai' ? '✓' : '4' ?></div>
                        <span style="font-weight:800; color:#065F46; display:block; font-size:12px;">4. Selesai</span>
                        <small style="color:#047857; font-size:10.5px; font-weight:600;">Dana Masuk Rekening</small>
                    </div>
                </div>
                <!-- Rincian Rekening Tujuan -->
                <div style="background:#FFF; padding:12px 16px; border-radius:12px; border:1px solid #A7F3D0; font-size:12px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <div>Bank: <strong><?= htmlspecialchars($r_bank) ?></strong></div>
                    <div>No Rekening: <strong style="font-family:monospace;"><?= htmlspecialchars($r_rek) ?></strong></div>
                    <div>Atas Nama: <strong><?= htmlspecialchars($r_an) ?></strong></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$is_cancelled): ?>
            <!-- ======================================================== -->
            <!-- ALUR TRACKING 6-STEP RESMI LILINNAWA SESUAI INSTRUKSI    -->
            <!-- STEP 1: Selesai pilih template                           -->
            <!-- STEP 2: Pembayaran / Konsultasi Menunggu pembayaran QRIS -->
            <!-- STEP 3: Data Acara (Berhenti jika data belum lengkap)    -->
            <!-- STEP 4: Proses Pengerjaan (Mulai Step 4 TIDAK BISA batal)-->
            <!-- STEP 5: Review Undangan pada Customer                    -->
            <!-- STEP 6: Selesai & Jadi                                   -->
            <!-- ======================================================== -->
            <div class="steps-container" style="display:flex; justify-content:space-between; margin:24px 0; position:relative; gap:10px; overflow-x:auto;">
                <div class="step-item step-1 <?= $curr_step > 0 ? 'completed' : ($curr_step === 0 ? 'active' : '') ?>">
                    <div class="step-number"><?= $curr_step > 0 ? '✓' : '1' ?></div>
                    <span class="step-title">Pilih Template</span>
                    <span class="step-desc">Template dipilih</span>
                </div>
                <div class="step-item step-2 <?= $curr_step > 1 ? 'completed' : ($curr_step === 1 ? 'active' : '') ?>">
                    <div class="step-number"><?= $curr_step > 1 ? '✓' : '2' ?></div>
                    <span class="step-title">Pembayaran / QRIS</span>
                    <span class="step-desc">Menunggu bayar QRIS</span>
                </div>
                <div class="step-item step-3 <?= $curr_step > 2 ? 'completed' : ($curr_step === 2 ? 'active' : '') ?>">
                    <div class="step-number"><?= $curr_step > 2 ? '✓' : '3' ?></div>
                    <span class="step-title">Data Acara</span>
                    <span class="step-desc">Wajib diisi lengkap</span>
                </div>
                <div class="step-item step-4 <?= $curr_step > 3 ? 'completed' : ($curr_step === 3 ? 'active' : '') ?>">
                    <div class="step-number"><?= $curr_step > 3 ? '✓' : '4' ?></div>
                    <span class="step-title">Pengerjaan</span>
                    <span class="step-desc">Desain &amp; Koding</span>
                </div>
                <div class="step-item step-5 <?= $curr_step > 4 ? 'completed' : ($curr_step === 4 ? 'active' : '') ?>">
                    <div class="step-number"><?= $curr_step > 4 ? '✓' : '5' ?></div>
                    <span class="step-title">Review Undangan</span>
                    <span class="step-desc">Cek preview web</span>
                </div>
                <div class="step-item step-6 <?= $curr_step >= 5 ? 'completed active' : '' ?>">
                    <div class="step-number"><?= $curr_step >= 5 ? '✓' : '6' ?></div>
                    <span class="step-title">Selesai &amp; Jadi</span>
                    <span class="step-desc">Siap disebarkan</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- ======================================================== -->
            <!-- KOTAK TEMPAT PENERIMAAN LINK WEBSITE UNDANGAN YANG DIBELI-->
            <!-- (Permintaan spesifik foto ke-2: Admin menambahkan link    -->
            <!--  website untuk dibagikan kepada customer)               -->
            <!-- ======================================================== -->
            <?php if (!$is_cancelled): ?>
                <?php if (!empty($active_order['result_link'])): ?>
                <!-- KONDISI A: ADMIN SUDAH MENAMBAHKAN LINK WEBSITE HASIL UNDANGAN -->
                <div style="background:#ECFDF5; border:2px solid #059669; border-radius:18px; padding:22px; margin:20px 0; box-shadow:0 6px 20px rgba(5,150,105,0.12);">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:44px; height:44px; background:#059669; color:#FFF; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; box-shadow:0 4px 10px rgba(5,150,105,0.25);">
                                🌐
                            </div>
                            <div>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <h4 style="font-family:'Playfair Display',serif; font-size:17px; font-weight:800; color:#064E3B; margin:0;">
                                        Tempat Penerimaan Link Website Undangan Anda
                                    </h4>
                                    <span style="background:#059669; color:#FFF; font-size:10px; font-weight:800; padding:3px 8px; border-radius:99px;">
                                        ✓ Website Aktif &amp; Siap Disebar
                                    </span>
                                </div>
                                <p style="font-size:12px; color:#047857; margin:4px 0 0; line-height:1.4;">
                                    Tautan website undangan digital yang Anda beli telah disiapkan oleh admin LilinNawa dan siap dibagikan kepada seluruh keluarga, sahabat, dan tamu undangan!
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Display URL Box & Tombol Aksi -->
                    <div style="background:#FFFFFF; border:1px solid #A7F3D0; border-radius:14px; padding:14px 18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                        <div style="display:flex; align-items:center; gap:10px; min-width:240px; flex:1;">
                            <span style="font-size:18px;">🔗</span>
                            <div>
                                <small style="font-size:10px; text-transform:uppercase; font-weight:700; color:#6B7280; display:block;">Link Website Undangan Digital Anda:</small>
                                <a href="<?= htmlspecialchars($active_order['result_link']) ?>" target="_blank" id="resultLinkText" style="font-family:monospace; font-size:13px; font-weight:800; color:#047857; text-decoration:underline;">
                                    <?= htmlspecialchars($active_order['result_link']) ?>
                                </a>
                            </div>
                        </div>

                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a href="<?= htmlspecialchars($active_order['result_link']) ?>" target="_blank" class="btn-action-pill" style="background:#047857; color:#FFF;">
                                Buka Undangan ↗
                            </a>
                            <button type="button" onclick="copyResultLink('<?= htmlspecialchars($active_order['result_link']) ?>')" class="btn-action-pill" style="background:#E6FFFA; color:#047857; border:1px solid #38B2AC;">
                                📋 Salin Link
                            </button>
                            <a href="https://api.whatsapp.com/send?text=<?= urlencode("Halo! Kami mengundang Anda untuk hadir di acara kami. Silakan buka undangan digital resmi di tautan berikut:\n" . $active_order['result_link'] . "\n\nTerima kasih!") ?>" target="_blank" class="btn-action-pill" style="background:#25D366; color:#FFF;">
                                💬 Bagikan ke WA
                            </a>
                            <button type="button" onclick="showQrModal('<?= htmlspecialchars($active_order['result_link']) ?>')" class="btn-action-pill" style="background:#FAF3DE; color:#701C2F; border:1px solid #E2D4B0;">
                                📱 QR Code
                            </button>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- KONDISI B: ADMIN BELUM MENAMBAHKAN LINK WEBSITE (SEDANG DALAM PROSES) -->
                <div style="background:#FFFDF7; border:2px dashed #F59E0B; border-radius:18px; padding:20px; margin:20px 0;">
                    <div style="display:flex; align-items:flex-start; gap:12px;">
                        <div style="width:40px; height:40px; background:#FEF3C7; color:#D97706; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; shrink-0;">
                            ⏳
                        </div>
                        <div style="flex:1;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <h4 style="font-family:'Playfair Display',serif; font-size:16px; font-weight:800; color:#701C2F; margin:0;">
                                    Tempat Penerimaan Link Website Undangan yang Dibelinya
                                </h4>
                                <span style="background:#FEF3C7; color:#92400E; font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px; border:1px solid #FDE68A;">
                                    Sedang Dalam Pengerjaan Desainer
                                </span>
                            </div>
                            <p style="font-size:12px; color:#4B5563; margin:4px 0 10px; line-height:1.5;">
                                Link website resmi pesanan Anda sedang dikerjakan oleh tim desainer LilinNawa. Tautan web Anda akan otomatis muncul di kotak ini setelah admin mengunggah link website (pada Step 5: Review Undangan).
                            </p>
                            <div style="background:#FFF; padding:10px 14px; border-radius:10px; border:1px solid #E5E7EB; font-size:12px; display:inline-flex; align-items:center; gap:8px;">
                                <span style="color:#6B7280;">Estimasi Format Link Anda:</span>
                                <code style="color:#701C2F; font-weight:bold;">https://lilinawa.my.id/u/<?= htmlspecialchars(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $active_order['customer_name'] ?: 'undangan'))) ?></code>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Ringkasan Rincian Acara Terkini Sesuai Kebutuhan Jenis Undangan -->
            <div style="background:#FAF8F2; border-radius:14px; padding:20px; border:1px solid #E5E7EB; margin-top:20px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <div>
                        <h3 style="font-family:'Playfair Display',serif; font-size:16px; color:#111827; margin:0;">
                            📋 Rincian Data Acara (Kategori: <?= htmlspecialchars($active_order['kategori_acara'] ?? 'Pernikahan') ?>)
                        </h3>
                        <small style="color:#6B7280; font-size:11px;">Rincian disesuaikan berdasarkan kebutuhan jenis undangan yang dipilih.</small>
                    </div>
                    <?php if (!$is_cancelled): ?>
                    <button type="button" onclick="openModal('modalEditAcara')" style="background:#701C2F; color:#FFF; border:none; padding:6px 12px; border-radius:8px; font-size:11px; font-weight:700; cursor:pointer;">
                        Ubah / Lengkapi Data ✏️
                    </button>
                    <?php endif; ?>
                </div>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; font-size:13px;">
                    <div>
                        <span style="color:#6B7280; font-size:11px; display:block;">Nama Acara:</span>
                        <strong><?= htmlspecialchars($active_order['nama_acara'] ?? $active_order['customer_name']) ?></strong>
                    </div>

                    <?php if(strpos(strtolower($active_order['kategori_acara'] ?? ''), 'nikah') !== false): ?>
                    <div>
                        <span style="color:#6B7280; font-size:11px; display:block;">Mempelai Pria &amp; Wanita:</span>
                        <strong><?= htmlspecialchars($active_order['nama_mempelai_pria'] ?: '-') ?></strong> &amp; 
                        <strong><?= htmlspecialchars($active_order['nama_mempelai_wanita'] ?: '-') ?></strong>
                    </div>
                    <div>
                        <span style="color:#6B7280; font-size:11px; display:block;">Nama Orang Tua:</span>
                        <span><?= htmlspecialchars($active_order['nama_orang_tua'] ?: '-') ?></span>
                    </div>
                    <?php elseif(strpos(strtolower($active_order['kategori_acara'] ?? ''), 'ulang') !== false): ?>
                    <div>
                        <span style="color:#6B7280; font-size:11px; display:block;">Yang Berulang Tahun:</span>
                        <strong><?= htmlspecialchars($active_order['info_khusus_1'] ?: $active_order['customer_name']) ?></strong>
                    </div>
                    <div>
                        <span style="color:#6B7280; font-size:11px; display:block;">Ulang Tahun Ke-:</span>
                        <span><?= htmlspecialchars($active_order['info_khusus_2'] ?: '-') ?></span>
                    </div>
                    <?php elseif(strpos(strtolower($active_order['kategori_acara'] ?? ''), 'khitan') !== false): ?>
                    <div>
                        <span style="color:#6B7280; font-size:11px; display:block;">Nama Anak Khitan:</span>
                        <strong><?= htmlspecialchars($active_order['info_khusus_1'] ?: $active_order['customer_name']) ?></strong>
                    </div>
                    <?php endif; ?>

                    <div>
                        <span style="color:#6B7280; font-size:11px; display:block;">Tanggal &amp; Waktu Acara:</span>
                        <strong><?= htmlspecialchars($active_order['tanggal_acara'] ?: 'Belum diisi') ?></strong> 
                        <span><?= htmlspecialchars($active_order['waktu_acara'] ?? '') ?></span>
                    </div>
                    <div>
                        <span style="color:#6B7280; font-size:11px; display:block;">Lokasi &amp; Alamat:</span>
                        <strong><?= htmlspecialchars($active_order['lokasi_acara'] ?: 'Belum diisi') ?></strong>
                        <div style="font-size:11px; color:#4B5563;"><?= htmlspecialchars($active_order['alamat_lengkap'] ?? '') ?></div>
                    </div>
                    <div>
                        <span style="color:#6B7280; font-size:11px; display:block;">Catatan Desainer:</span>
                        <span><?= htmlspecialchars($active_order['catatan_khusus'] ?: 'Tidak ada catatan') ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ======================================================== -->
        <!-- MODAL 1: TEMPAT MELAKUKAN PEMBAYARAN (QRIS & TRANSFER)   -->
        <!-- ======================================================== -->
        <?php if ($active_order): ?>
        <div id="modalPembayaran" class="modal-overlay" onclick="if(event.target===this) closeModal('modalPembayaran')">
            <div class="modal-content" style="max-width:560px;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #E5E7EB; padding-bottom:12px; margin-bottom:18px;">
                    <div>
                        <span style="font-size:11px; font-weight:800; color:#059669; text-transform:uppercase;">Tempat Melakukan Pembayaran</span>
                        <h3 style="font-family:'Playfair Display',serif; font-size:20px; font-weight:800; color:#111827; margin:2px 0 0;">
                            💳 Pembayaran QRIS &amp; Transfer Bank
                        </h3>
                    </div>
                    <button type="button" onclick="closeModal('modalPembayaran')" style="background:#F3F4F6; border:none; width:32px; height:32px; border-radius:50%; cursor:pointer;">✕</button>
                </div>

                <div style="background:#ECFDF5; border:1.5px solid #A7F3D0; border-radius:14px; padding:16px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <span style="font-size:11px; color:#065F46; font-weight:700;">TOTAL TAGIHAN:</span>
                        <div style="font-size:24px; font-weight:900; color:#065F46; font-family:'Playfair Display',serif;">
                            Rp <?= number_format($active_order['total_price'], 0, ',', '.') ?>
                        </div>
                        <small style="color:#047857; font-size:11px;">Pesanan: <?= htmlspecialchars($active_order['order_number']) ?> (<?= htmlspecialchars($active_order['template_name']) ?>)</small>
                    </div>
                    <span style="background:#059669; color:#FFF; font-size:11px; font-weight:800; padding:4px 10px; border-radius:99px;">
                        Bebas Admin
                    </span>
                </div>

                <div style="text-align:center; background:#FFFDF7; border:1px solid #E4D7B8; border-radius:14px; padding:18px; margin-bottom:18px;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=00020101021126570014ID.LINKAJA.WWW0118936009110021522003021522003865204581253033605802ID5915LILINNAWA+STUDIO6007BANDUNG6304D1B8" alt="QRIS" style="width:160px; height:160px; margin:0 auto 10px; display:block; border:1px solid #E5E7EB; border-radius:10px; padding:4px; background:#FFF;">
                    <p style="font-size:11px; color:#4B5563; margin:0;">Scan QRIS di atas dengan BCA Mobile, Livin, GoPay, OVO, Dana, atau ShopeePay.</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="konfirmasi_pembayaran">
                    <input type="hidden" name="order_id" value="<?= $active_order['id'] ?>">
                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" onclick="closeModal('modalPembayaran')" style="background:#F3F4F6; border:1px solid #D1D5DB; padding:10px 18px; border-radius:10px; font-weight:700; cursor:pointer;">Tutup</button>
                        <button type="submit" style="background:#059669; color:#FFF; border:none; padding:10px 22px; border-radius:10px; font-weight:800; cursor:pointer;">Saya Sudah Bayar via QRIS ✓</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL 2: INVOICE PEMBELIAN RESMI                         -->
        <!-- ======================================================== -->
        <div id="modalInvoice" class="modal-overlay" onclick="if(event.target===this) closeModal('modalInvoice')">
            <div class="modal-content" style="max-width:760px; padding:28px 32px; border-radius:20px; box-shadow:0 12px 36px rgba(0,0,0,0.18);">
                <!-- Header Faktur Resmi Sesuai Format Eksklusif LilinNawa -->
                <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; border-bottom:2px solid #FAF3DE; padding-bottom:18px; margin-bottom:20px;">
                    <div>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="width:44px; height:44px; border-radius:12px; background:#701C2F; color:#FAF3DE; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:18px; font-family:'Playfair Display',serif; box-shadow:0 4px 10px rgba(112,28,47,0.25);">
                                LN
                            </div>
                            <div>
                                <h2 style="font-family:'Playfair Display',serif; font-size:20px; color:#701C2F; margin:0; line-height:1.2; font-weight:800;">
                                    <?= htmlspecialchars($store['store_name']) ?>
                                </h2>
                                <p style="font-size:11.5px; color:#6B7280; margin:2px 0 0 0;">
                                    Spesialis Undangan Digital Web Eksklusif &amp; Modern
                                </p>
                            </div>
                        </div>
                        <div style="font-size:11px; color:#4B5563; margin-top:10px; line-height:1.6;">
                            📍 <?= htmlspecialchars($store['address_studio']) ?><br>
                            💬 WhatsApp: <strong><?= htmlspecialchars($store['phone_whatsapp']) ?></strong> | ✉️ <?= htmlspecialchars($store['email_official']) ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:22px; font-weight:900; color:#701C2F; font-family:'Playfair Display',serif; letter-spacing:1px;">
                            INVOICE
                        </div>
                        <div style="font-size:12.5px; font-weight:800; color:#1F2937; margin-top:3px;">
                            No: INV-<?= preg_replace('/[^0-9]/', '', $active_order['order_number']) ?>
                        </div>
                        <div style="font-size:11px; color:#6B7280; margin-top:2px;">
                            Tanggal: <?= date('d F Y', strtotime($active_order['created_at'] ?? 'now')) ?>
                        </div>
                        <div style="margin-top:8px;">
                            <?php if ($active_order['payment_status'] === 'lunas'): ?>
                                <span style="display:inline-block; padding:4px 14px; border-radius:99px; background:#D1FAE5; color:#065F46; font-size:11px; font-weight:800; border:1.5px solid #A7F3D0;">
                                    ✓ LUNAS (PAID)
                                </span>
                            <?php else: ?>
                                <span style="display:inline-block; padding:4px 14px; border-radius:99px; background:#FEF3C7; color:#92400E; font-size:11px; font-weight:800; border:1.5px solid #FCD34D;">
                                    ⏳ MENUNGGU PEMBAYARAN
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 2 Kartu Info Klien & Acara (Sesuai Layout Foto Lampiran) -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                    <!-- Kartu Klien -->
                    <div style="background:#FAF3DE; padding:14px 16px; border-radius:14px; border:1px solid #E5D7B7;">
                        <span style="font-size:10.5px; font-weight:800; color:#701C2F; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;">
                            DITAGIHKAN KEPADA (KLIEN):
                        </span>
                        <div style="font-size:13.5px; font-weight:800; color:#1F2937; margin-bottom:4px;">
                            <?= htmlspecialchars($active_order['customer_name'] ?? $user_name) ?>
                        </div>
                        <div style="font-size:11px; color:#4B5563; line-height:1.5;">
                            📱 WhatsApp: <?= htmlspecialchars($active_order['customer_phone'] ?? $user_phone) ?><br>
                            ✉️ Email: <?= htmlspecialchars($active_order['customer_email'] ?? $user_email) ?>
                        </div>
                    </div>

                    <!-- Kartu Detail Acara -->
                    <div style="background:#FAF3DE; padding:14px 16px; border-radius:14px; border:1px solid #E5D7B7;">
                        <span style="font-size:10.5px; font-weight:800; color:#701C2F; text-transform:uppercase; letter-spacing:0.5px; display:block; margin-bottom:6px;">
                            DETAIL ACARA UNDANGAN:
                        </span>
                        <div style="font-size:13.5px; font-weight:800; color:#1F2937; margin-bottom:4px;">
                            <?= htmlspecialchars(!empty($active_order['nama_acara']) ? $active_order['nama_acara'] : ($active_order['template_name'] ?? 'Undangan Digital')) ?>
                        </div>
                        <div style="font-size:11px; color:#4B5563; line-height:1.5;">
                            📅 Tanggal Acara: <?= !empty($active_order['tanggal_acara']) ? date('d M Y', strtotime($active_order['tanggal_acara'])) : 'Sesuai Request' ?><br>
                            📍 Lokasi: <?= htmlspecialchars($active_order['lokasi_acara'] ?? 'Venue Acara') ?>
                        </div>
                    </div>
                </div>

                <!-- Tabel Item Invoice Persis Foto Lampiran -->
                <div style="border-radius:12px; overflow:hidden; border:1px solid #E5E7EB; margin-bottom:16px;">
                    <table style="width:100%; border-collapse:collapse; font-size:12px;">
                        <thead>
                            <tr style="background:#701C2F; color:#FFFFFF; text-align:left;">
                                <th style="padding:10px 14px; font-weight:800;">Deskripsi Layanan / Item</th>
                                <th style="padding:10px 14px; text-align:center; font-weight:800; width:90px;">Jenis</th>
                                <th style="padding:10px 14px; text-align:center; font-weight:800; width:70px;">Qty</th>
                                <th style="padding:10px 14px; text-align:right; font-weight:800; width:110px;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody style="background:#FFF;">
                            <tr style="border-bottom:1px solid #F3F4F6;">
                                <td style="padding:14px;">
                                    <strong style="color:#1F2937; font-size:13px; display:block; margin-bottom:3px;">
                                        <?= htmlspecialchars($active_order['template_name'] ?: 'Undangan Digital Web') ?>
                                    </strong>
                                    <p style="color:#6B7280; font-size:11px; margin:0; line-height:1.4;">
                                        Fitur Lengkap: Responsive Mobile, RSVP WhatsApp, Petunjuk Arah Google Maps, Hitung Mundur, Background Musik.
                                    </p>
                                </td>
                                <td style="padding:14px; text-align:center;">
                                    <span style="background:#FAF3DE; color:#701C2F; font-size:10px; font-weight:800; padding:3px 8px; border-radius:6px; border:1px solid #E5D7B7;">
                                        <?= strtoupper($active_order['type'] ?? 'TEMPLATE') ?>
                                    </span>
                                </td>
                                <td style="padding:14px; text-align:center; font-weight:700; color:#4B5563;">
                                    1 Paket
                                </td>
                                <td style="padding:14px; text-align:right; font-weight:800; color:#1F2937;">
                                    Rp <?= number_format($active_order['total_price'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot style="background:#FAFAFA;">
                            <tr>
                                <td colspan="3" style="text-align:right; padding:8px 14px; font-size:11.5px; color:#6B7280;">
                                    Subtotal:
                                </td>
                                <td style="text-align:right; padding:8px 14px; font-weight:700; color:#1F2937;">
                                    Rp <?= number_format($active_order['total_price'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align:right; padding:8px 14px; font-size:11.5px; color:#6B7280;">
                                    Biaya Pemeliharaan &amp; Hosting Server:
                                </td>
                                <td style="text-align:right; padding:8px 14px; font-weight:800; color:#059669;">
                                    GRATIS
                                </td>
                            </tr>
                            <tr style="border-top:2px solid #701C2F; background:#FFF;">
                                <td colspan="3" style="text-align:right; padding:12px 14px; font-size:13px; font-weight:900; color:#701C2F; text-transform:uppercase;">
                                    TOTAL PEMBAYARAN:
                                </td>
                                <td style="text-align:right; padding:12px 14px; font-size:16px; font-weight:900; color:#701C2F; font-family:'Playfair Display',serif;">
                                    Rp <?= number_format($active_order['total_price'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Footer Tindakan Modal -->
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-top:14px;">
                    <div style="font-size:11px; color:#6B7280;">
                        * Invoice ini merupakan bukti pemesanan sah dari LilinNawa Digital Invitation.
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="button" onclick="window.print()" style="background:#FAF3DE; color:#701C2F; border:1px solid #E2D4B0; padding:9px 18px; border-radius:10px; font-weight:700; font-size:12px; cursor:pointer;">
                            Cetak Invoice 🖨️
                        </button>
                        <button type="button" onclick="closeModal('modalInvoice')" style="background:#701C2F; color:#FFF; border:none; padding:9px 20px; border-radius:10px; font-weight:700; font-size:12px; cursor:pointer;">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- ======================================================== -->
        <!-- MODAL 3: EDIT DATA ACARA DINAMIS SESUAI KEBUTUHAN JENIS  -->
        <!-- ======================================================== -->
        <div id="modalEditAcara" class="modal-overlay" onclick="if(event.target===this) closeModal('modalEditAcara')">
            <div class="modal-content" style="max-width:580px;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #E5E7EB; padding-bottom:12px; margin-bottom:16px;">
                    <div>
                        <h3 style="font-family:'Playfair Display',serif; font-size:18px; color:#701C2F; margin:0;">
                            ✏️ Form Data Acara Sesuai Kebutuhan
                        </h3>
                        <small style="font-size:11px; color:#6B7280;">Lengkapi data di Step 3 untuk langsung maju ke Step 4 (Pengerjaan).</small>
                    </div>
                    <button type="button" onclick="closeModal('modalEditAcara')" style="background:#F3F4F6; border:none; width:30px; height:30px; border-radius:50%; cursor:pointer;">✕</button>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="simpan_acara">
                    <input type="hidden" name="order_id" value="<?= $active_order['id'] ?>">

                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Jenis / Kategori Acara</label>
                        <select name="kategori_acara" id="kategoriAcaraSelect" onchange="toggleFormFields(this.value)" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px;">
                            <option value="Pernikahan" <?= ($active_order['kategori_acara'] ?? '') === 'Pernikahan' ? 'selected' : '' ?>>Pernikahan</option>
                            <option value="Ulang Tahun" <?= ($active_order['kategori_acara'] ?? '') === 'Ulang Tahun' ? 'selected' : '' ?>>Ulang Tahun</option>
                            <option value="Khitanan" <?= ($active_order['kategori_acara'] ?? '') === 'Khitanan' ? 'selected' : '' ?>>Khitanan</option>
                            <option value="Aqiqah" <?= ($active_order['kategori_acara'] ?? '') === 'Aqiqah' ? 'selected' : '' ?>>Aqiqah</option>
                            <option value="Wisuda" <?= ($active_order['kategori_acara'] ?? '') === 'Wisuda' ? 'selected' : '' ?>>Wisuda / Graduation</option>
                            <option value="Acara Lainnya" <?= ($active_order['kategori_acara'] ?? '') === 'Acara Lainnya' ? 'selected' : '' ?>>Acara Lainnya</option>
                        </select>
                    </div>

                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Nama Acara / Judul Undangan *</label>
                        <input type="text" name="nama_acara" required value="<?= htmlspecialchars($active_order['nama_acara'] ?? '') ?>" placeholder="Contoh: The Wedding of Dimas & Anissa" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                    </div>

                    <!-- Fields Khusus Pernikahan -->
                    <div id="fieldsPernikahan" style="margin-bottom:12px;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                            <div>
                                <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Nama Mempelai Pria</label>
                                <input type="text" name="nama_mempelai_pria" value="<?= htmlspecialchars($active_order['nama_mempelai_pria'] ?? '') ?>" placeholder="Dimas Aditya, S.T." style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Nama Mempelai Wanita</label>
                                <input type="text" name="nama_mempelai_wanita" value="<?= htmlspecialchars($active_order['nama_mempelai_wanita'] ?? '') ?>" placeholder="Anissa Rahmawati, S.Ked" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                            </div>
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Nama Orang Tua Kedua Mempelai</label>
                            <input type="text" name="nama_orang_tua" value="<?= htmlspecialchars($active_order['nama_orang_tua'] ?? '') ?>" placeholder="Bpk. Hendra & Ibu Rina | Bpk. Gunawan & Ibu Maya" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                        </div>
                    </div>

                    <!-- Fields Khusus Ulang Tahun / Non-Pernikahan -->
                    <div id="fieldsNonPernikahan" style="display:none; margin-bottom:12px;">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Tokoh Acara (Nama Ultah / Anak Khitan / Bayi)</label>
                                <input type="text" name="info_khusus_1" value="<?= htmlspecialchars($active_order['info_khusus_1'] ?? '') ?>" placeholder="Nama Tokoh Acara" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Keterangan Usia / Gelar / Dresscode</label>
                                <input type="text" name="info_khusus_2" value="<?= htmlspecialchars($active_order['info_khusus_2'] ?? '') ?>" placeholder="Contoh: Ke-17 (Sweet 17) / S.Kom" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                            </div>
                        </div>
                    </div>

                    <!-- Tanggal & Waktu -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px;">
                        <div>
                            <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Tanggal Acara *</label>
                            <input type="date" name="tanggal_acara" required value="<?= htmlspecialchars($active_order['tanggal_acara'] ?? '') ?>" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Waktu Acara</label>
                            <input type="text" name="waktu_acara" value="<?= htmlspecialchars($active_order['waktu_acara'] ?? '') ?>" placeholder="09:00 WIB" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                        </div>
                    </div>

                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Nama Tempat / Gedung *</label>
                        <input type="text" name="lokasi_acara" required value="<?= htmlspecialchars($active_order['lokasi_acara'] ?? '') ?>" placeholder="Gedung Sabuga / Cafe / Kediaman" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                    </div>

                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Alamat Lengkap</label>
                        <input type="text" name="alamat_lengkap" value="<?= htmlspecialchars($active_order['alamat_lengkap'] ?? '') ?>" placeholder="Jl. Tamansari No. 73, Kota Bandung" style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                    </div>

                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Link Google Maps (Opsional)</label>
                        <input type="url" name="link_google_maps" value="<?= htmlspecialchars($active_order['link_google_maps'] ?? '') ?>" placeholder="https://maps.google.com/..." style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Catatan Khusus Desainer</label>
                        <textarea name="catatan_khusus" rows="2" placeholder="Tuliskan lagu latar, font, atau pesan khusus..." style="width:100%; padding:9px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;"><?= htmlspecialchars($active_order['catatan_khusus'] ?? '') ?></textarea>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" onclick="closeModal('modalEditAcara')" style="background:#F3F4F6; border:none; padding:10px 16px; border-radius:8px; font-weight:700; font-size:12px; cursor:pointer;">Batal</button>
                        <button type="submit" style="background:#701C2F; color:#FFF; border:none; padding:10px 22px; border-radius:8px; font-weight:700; font-size:12px; cursor:pointer;">Simpan &amp; Lengkapi Data</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL 4: PEMBATALAN PESANAN & PENGAJUAN REFUND           -->
        <!-- ======================================================== -->
        <div id="modalBatalPesanan" class="modal-overlay" onclick="if(event.target===this) closeModal('modalBatalPesanan')">
            <div class="modal-content" style="max-width:520px;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #E5E7EB; padding-bottom:12px; margin-bottom:16px;">
                    <h3 style="font-family:'Playfair Display',serif; font-size:18px; color:#BE123C; margin:0;">
                        <?= $active_order['payment_status'] === 'lunas' ? '💸 Pembatalan Pesanan & Pengajuan Refund' : '🚫 Batalkan Pesanan ' . htmlspecialchars($active_order['order_number']) ?>
                    </h3>
                    <button type="button" onclick="closeModal('modalBatalPesanan')" style="background:#F3F4F6; border:none; width:30px; height:30px; border-radius:50%; cursor:pointer;">✕</button>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="batal_pesanan">
                    <input type="hidden" name="order_id" value="<?= $active_order['id'] ?>">

                    <p style="font-size:12.5px; color:#4B5563; line-height:1.5; margin-bottom:14px;">
                        Apakah Anda yakin ingin membatalkan pesanan ini? Sesuai ketentuan LilinNawa, pembatalan mandiri hanya berlaku pada <strong>Step 1 s/d Step 3</strong> sebelum masuk Step 4 (Proses Pengerjaan oleh Desainer).
                    </p>

                    <!-- FORM REKENING TUJUAN JIKA SUDAH SELESAI BAYAR (LUNAS) -->
                    <?php if ($active_order['payment_status'] === 'lunas'): ?>
                    <div style="background:#ECFDF5; border:1.5px solid #34D399; border-radius:14px; padding:16px; margin-bottom:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <strong style="color:#065F46; font-size:13px;">Formulir Rekening Tujuan Pengembalian Dana (Refund)</strong>
                            <span style="background:#059669; color:#FFF; font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px;">Lunas Dibayar</span>
                        </div>
                        <div style="font-size:12px; color:#047857; margin-bottom:12px;">
                            Total Nominal Refund: <strong style="font-size:14px;">Rp <?= number_format($active_order['total_price'], 0, ',', '.') ?></strong>
                        </div>
                        <div style="margin-bottom:10px;">
                            <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Bank / E-Wallet Tujuan *</label>
                            <select name="bank_name" required style="width:100%; padding:8px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px;">
                                <option value="BCA">BCA</option>
                                <option value="Mandiri">Bank Mandiri</option>
                                <option value="BRI">BRI</option>
                                <option value="BNI">BNI</option>
                                <option value="BSI">BSI (Bank Syariah Indonesia)</option>
                                <option value="CIMB Niaga">CIMB Niaga</option>
                                <option value="Permata">Bank Permata</option>
                                <option value="GoPay">GoPay</option>
                                <option value="OVO">OVO</option>
                                <option value="DANA">DANA</option>
                                <option value="ShopeePay">ShopeePay</option>
                            </select>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Nomor Rekening / HP *</label>
                                <input type="text" name="account_number" required placeholder="Contoh: 8691522003" style="width:100%; padding:8px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Atas Nama Rekening *</label>
                                <input type="text" name="account_name" required placeholder="Nama Pemilik Rekening" style="width:100%; padding:8px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="margin-bottom:12px;">
                        <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Alasan Pembatalan *</label>
                        <select name="alasan_batal" required style="width:100%; padding:8px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px;">
                            <option value="Ingin memilih template lain di katalog">Ingin memilih template lain di katalog</option>
                            <option value="Perubahan tanggal / waktu acara">Perubahan tanggal / waktu acara</option>
                            <option value="Ingin beralih ke Layanan Custom">Ingin beralih ke Layanan Custom</option>
                            <option value="Salah memasukkan data pemesanan">Salah memasukkan data pemesanan</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="font-size:11px; font-weight:700; color:#374151; display:block; margin-bottom:4px;">Catatan Tambahan (Opsional)</label>
                        <textarea name="catatan_batal" rows="2" placeholder="Catatan singkat..." style="width:100%; padding:8px 12px; border:1px solid #D1D5DB; border-radius:8px; font-size:12px; box-sizing:border-box;"></textarea>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" onclick="closeModal('modalBatalPesanan')" style="background:#F3F4F6; border:1px solid #ccc; padding:9px 16px; border-radius:10px; cursor:pointer; font-weight:700; font-size:12px;">Kembali</button>
                        <button type="submit" style="background:#BE123C; color:#fff; border:none; padding:9px 20px; border-radius:10px; font-weight:800; cursor:pointer; font-size:12px;">
                            <?= $active_order['payment_status'] === 'lunas' ? 'Ya, Batalkan & Ajukan Refund 💸' : 'Ya, Batalkan Pesanan' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal QR Code Undangan -->
        <div id="modalQrCode" class="modal-overlay" onclick="if(event.target===this) closeModal('modalQrCode')">
            <div class="modal-content" style="max-width:380px; text-align:center;">
                <h3 style="font-family:'Playfair Display',serif; font-size:18px; color:#701C2F; margin:0 0 12px;">📱 QR Code Undangan</h3>
                <img id="qrImageElement" src="" alt="QR Code" style="width:200px; height:200px; margin:0 auto 12px; display:block; border:1px solid #E5E7EB; border-radius:12px; padding:6px; background:#FFF;">
                <p style="font-size:11px; color:#4B5563; margin-bottom:16px;">Scan dengan kamera HP untuk langsung membuka website undangan digital Anda.</p>
                <button type="button" onclick="closeModal('modalQrCode')" style="background:#701C2F; color:#FFF; border:none; padding:9px 20px; border-radius:10px; font-weight:700; cursor:pointer;">Tutup</button>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
    function openModal(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'flex';
    }
    function closeModal(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }
    function copyResultLink(url) {
        if (!url) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                showToastNotification('✓ Tautan website undangan berhasil disalin ke clipboard!');
            });
        } else {
            var temp = document.createElement('input');
            temp.value = url;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            showToastNotification('✓ Tautan website undangan berhasil disalin!');
        }
    }
    function showQrModal(url) {
        var img = document.getElementById('qrImageElement');
        if (img) {
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(url);
        }
        openModal('modalQrCode');
    }
    function showToastNotification(msg) {
        var t = document.createElement('div');
        t.innerText = msg;
        t.style.position = 'fixed';
        t.style.bottom = '24px';
        t.style.right = '24px';
        t.style.background = '#059669';
        t.style.color = '#FFF';
        t.style.padding = '12px 20px';
        t.style.borderRadius = '12px';
        t.style.fontWeight = 'bold';
        t.style.fontSize = '12px';
        t.style.boxShadow = '0 10px 25px rgba(0,0,0,0.2)';
        t.style.zIndex = '99999';
        document.body.appendChild(t);
        setTimeout(function() {
            t.style.transition = 'opacity 0.4s';
            t.style.opacity = '0';
            setTimeout(function() { t.remove(); }, 400);
        }, 3000);
    }
    function toggleFormFields(cat) {
        var fp = document.getElementById('fieldsPernikahan');
        var fnp = document.getElementById('fieldsNonPernikahan');
        if (cat === 'Pernikahan') {
            if (fp) fp.style.display = 'block';
            if (fnp) fnp.style.display = 'none';
        } else {
            if (fp) fp.style.display = 'none';
            if (fnp) fnp.style.display = 'block';
        }
    }
    </script>
</body>
</html>
