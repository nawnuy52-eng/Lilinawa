<?php

session_start();
require_once '../../Config/Database.php';

// Pastikan yang login adalah customer
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../Login/Login.php");
    exit;
}

$user_id    = $_SESSION['user_id'] ?? 0;
$user_name  = $_SESSION['name'] ?? 'Pelanggan LilinNawa';
$user_email = $_SESSION['email'] ?? '';
$user_phone = $_SESSION['phone'] ?? '';


function getTemplateImageUrl($imagePath)
{
    $imagePath = trim((string) $imagePath);

    if ($imagePath === '') {
        return '';
    }

    // URL gambar eksternal
    if (preg_match('~^https?://~i', $imagePath)) {
        return $imagePath;
    }

    // Normalisasi separator Windows
    $imagePath = str_replace('\\', '/', $imagePath);

    // Pertahankan path root-relative
    if (strpos($imagePath, '/') === 0) {
        return $imagePath;
    }

    // Path yang sudah memiliki awalan relatif
    if (strpos($imagePath, '../') === 0) {
        return $imagePath;
    }

    // Path relatif yang disimpan dari folder utama website
    return '../../' . ltrim($imagePath, '/');
}

// Filter kategori
$selected_category = $_GET['cat'] ?? 'all';
$where_cat = '';

if ($selected_category !== 'all' && $selected_category !== '') {
    $cat_safe = mysqli_real_escape_string($conn, $selected_category);
    $where_cat = "AND category = '$cat_safe'";
}

// Ambil katalog template aktif
$templates_query = mysqli_query(
    $conn,
    "SELECT * FROM templates
     WHERE is_active = 1 $where_cat
     ORDER BY id ASC"
);

$templates_list = [];

if ($templates_query && mysqli_num_rows($templates_query) > 0) {
    while ($row = mysqli_fetch_assoc($templates_query)) {
        // Simpan jalur gambar yang sudah disesuaikan
        $row['image_url_resolved'] = getTemplateImageUrl(
            $row['image_url'] ?? ''
        );

        $templates_list[] = $row;
    }
}

// =====================================================
// PROSES PEMESANAN TEMPLATE
// =====================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'pilih_template_data_awal'
) {
    $tpl_id = (int) ($_POST['template_id'] ?? 0);

    $tpl_nm = '';
    $price = 0.0;
    $tplFound = false;

    // Ambil harga dan judul dari database
    $tplStmt = mysqli_prepare(
        $conn,
        'SELECT title, price, discount_price
         FROM templates
         WHERE id = ? AND is_active = 1
         LIMIT 1'
    );

    if ($tplStmt) {
        mysqli_stmt_bind_param($tplStmt, 'i', $tpl_id);

        if (mysqli_stmt_execute($tplStmt)) {
            mysqli_stmt_bind_result(
                $tplStmt,
                $dbTplTitle,
                $dbTplPrice,
                $dbTplDiscount
            );

            if (mysqli_stmt_fetch($tplStmt)) {
                $tplFound = true;
                $tpl_nm = $dbTplTitle;

                $price = (
                    !empty($dbTplDiscount)
                    && (float) $dbTplDiscount < (float) $dbTplPrice
                )
                    ? (float) $dbTplDiscount
                    : (float) $dbTplPrice;
            }
        }

        mysqli_stmt_close($tplStmt);
    }

    if (!$tplFound) {
        header('Location: Katalog Template.php?error=template_unavailable');
        exit;
    }

    $ord_no = '#LN' . random_int(100, 999);

    $nama_acara = mysqli_real_escape_string(
        $conn,
        trim($_POST['nama_acara'] ?? '')
    );

    $tanggal_acara = mysqli_real_escape_string(
        $conn,
        $_POST['tanggal_acara'] ?? ''
    );

    $lokasi_acara = mysqli_real_escape_string(
        $conn,
        $_POST['lokasi_acara'] ?? ''
    );

    $no_wa = mysqli_real_escape_string(
        $conn,
        $_POST['customer_phone'] ?? $user_phone
    );

    $catatan = mysqli_real_escape_string(
        $conn,
        $_POST['catatan_khusus'] ?? ''
    );

    $user_name_db = mysqli_real_escape_string($conn, $user_name);
    $user_email_db = mysqli_real_escape_string($conn, $user_email);

    // Simpan pesanan
    $sql_order = "
        INSERT INTO orders (
            order_number,
            user_id,
            customer_name,
            customer_email,
            customer_phone,
            type,
            template_id,
            template_name,
            status,
            step_index,
            total_price,
            payment_status
        ) VALUES (
            '$ord_no',
            $user_id,
            '$user_name_db',
            '$user_email_db',
            '$no_wa',
            'template',
            $tpl_id,
            '" . mysqli_real_escape_string($conn, $tpl_nm) . "',
            'pilih_template',
            0,
            $price,
            'pending'
        )
    ";

    if (mysqli_query($conn, $sql_order)) {
        $order_insert_id = mysqli_insert_id($conn);

        // Simpan rincian acara
        $sql_event = "
            INSERT INTO order_event_details (
                order_id,
                nama_acara,
                tanggal_acara,
                lokasi_acara,
                catatan_khusus
            ) VALUES (
                $order_insert_id,
                '$nama_acara',
                '$tanggal_acara',
                '$lokasi_acara',
                '$catatan'
            )
        ";

        mysqli_query($conn, $sql_event);

        // Buat invoice
        $inv_no = 'INV-' . preg_replace('/[^0-9]/', '', $ord_no);

        $sql_inv = "
            INSERT INTO invoices (
                invoice_number,
                order_id,
                user_id,
                customer_name,
                customer_email,
                customer_phone,
                item_description,
                amount,
                payment_status
            ) VALUES (
                '$inv_no',
                $order_insert_id,
                $user_id,
                '$user_name_db',
                '$user_email_db',
                '$no_wa',
                '" . mysqli_real_escape_string($conn, $tpl_nm) . "',
                $price,
                'unpaid'
            )
        ";

        mysqli_query($conn, $sql_inv);
    } else {
        error_log('Gagal membuat pesanan template: ' . mysqli_error($conn));
        header('Location: Katalog Template.php?error=order_failed');
        exit;
    }

    header(
        'Location: Status & TrackingOrder.php?order='
        . urlencode($ord_no)
        . '&status=created'
    );
    exit;
}

// PROSES PEMESANAN CUSTOM
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'order_custom'
) {
    $pkg_type = $_POST['package_type'] ?? 'eksklusif';

    $custom_title = ($pkg_type === 'vip')
        ? 'Paket Undangan Custom Luxury VIP'
        : 'Paket Undangan Custom Eksklusif';

    $price = ($pkg_type === 'vip') ? 299000 : 199000;
    $ord_no = '#LN' . random_int(100, 999);

    $nama_acara = mysqli_real_escape_string(
        $conn,
        trim($_POST['nama_acara'] ?? '')
    );

    $tanggal_acara = mysqli_real_escape_string(
        $conn,
        $_POST['tanggal_acara'] ?? ''
    );

    $no_wa = mysqli_real_escape_string(
        $conn,
        $_POST['whatsapp_kontak'] ?? $user_phone
    );

    $catatan = mysqli_real_escape_string(
        $conn,
        $_POST['catatan_konsep'] ?? ''
    );

    $user_name_db = mysqli_real_escape_string($conn, $user_name);
    $user_email_db = mysqli_real_escape_string($conn, $user_email);

    $sql_order = "
        INSERT INTO orders (
            order_number,
            user_id,
            customer_name,
            customer_email,
            customer_phone,
            type,
            template_id,
            template_name,
            status,
            step_index,
            total_price,
            payment_status
        ) VALUES (
            '$ord_no',
            $user_id,
            '$user_name_db',
            '$user_email_db',
            '$no_wa',
            'custom',
            NULL,
            '$custom_title',
            'konsultasi',
            1,
            $price,
            'pending'
        )
    ";

    if (mysqli_query($conn, $sql_order)) {
        $order_insert_id = mysqli_insert_id($conn);

        $sql_event = "
            INSERT INTO order_event_details (
                order_id,
                nama_acara,
                tanggal_acara,
                catatan_khusus
            ) VALUES (
                $order_insert_id,
                '$nama_acara',
                '$tanggal_acara',
                '$catatan'
            )
        ";

        mysqli_query($conn, $sql_event);

        $inv_no = 'INV-' . preg_replace('/[^0-9]/', '', $ord_no);

        $sql_inv = "
            INSERT INTO invoices (
                invoice_number,
                order_id,
                user_id,
                customer_name,
                customer_email,
                customer_phone,
                item_description,
                amount,
                payment_status
            ) VALUES (
                '$inv_no',
                $order_insert_id,
                $user_id,
                '$user_email_db',
                '$user_email_db',
                '$no_wa',
                '$custom_title',
                $price,
                'unpaid'
            )
        ";

        mysqli_query($conn, $sql_inv);
    } else {
        error_log('Gagal membuat pesanan custom: ' . mysqli_error($conn));
        header('Location: Katalog Template.php?error=order_failed');
        exit;
    }

    header(
        'Location: Status & TrackingOrder.php?order='
        . urlencode($ord_no)
        . '&status=custom_created'
    );
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Template - LilinNawa</title>

    <link rel="stylesheet" href="../sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="katalog template.css?v=<?= time() ?>">

    <style>
        .modal-data-awal-overlay {
            display: none;
            position: fixed;
            z-index: 99999;
            inset: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
        }

        .modal-data-awal-card {
            background: #FFFFFF;
            width: 100%;
            max-width: 580px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 20px;
            border: 1px solid #E5D7B7;
            box-shadow: 0 20px 40px rgba(112, 28, 47, 0.2);
            padding: 26px;
            position: relative;
        }

        .template-thumb img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .template-image-placeholder {
            width: 100%;
            height: 100%;
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #FAF3DE;
            color: #701C2F;
            text-align: center;
            padding: 16px;
            box-sizing: border-box;
        }
    </style>
</head>

<body class="user-layout">

<?php include '../sidebar.php'; ?>

<main class="user-main-content">

    <?php if (($_GET['error'] ?? '') === 'template_unavailable'): ?>
        <div role="alert" style="background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;padding:12px 16px;border-radius:12px;margin-bottom:16px;">
            Template tidak tersedia atau sudah dinonaktifkan. Silakan pilih template lain.
        </div>
    <?php elseif (($_GET['error'] ?? '') === 'order_failed'): ?>
        <div role="alert" style="background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;padding:12px 16px;border-radius:12px;margin-bottom:16px;">
            Pesanan belum berhasil dibuat. Silakan coba kembali.
        </div>
    <?php endif; ?>

    <div class="catalog-header">
        <h1>🎨 Katalog Template Undangan Digital</h1>
        <p>Pilih desain favorit Anda, isi formulir data awal acara lewat jendela pop-up, dan pantau proses pengerjaan langsung di Tracking Order LilinNawa.</p>
    </div>

    <div style="background:linear-gradient(135deg,#701C2F 0%,#4A0E1C 100%);color:#FFF;border-radius:18px;padding:18px 24px;margin-bottom:22px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;box-shadow:0 4px 16px rgba(112,28,47,0.2);">
        <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:22px;">✨</div>
            <div>
                <h3 style="margin:0;font-size:16px;font-weight:700;color:#FDE68A;">Punya Konsep Sendiri? Buat Undangan Custom Bebas Konsep</h3>
                <p style="margin:3px 0 0;color:#F3F4F6;font-size:12px;">Buka halaman pemesanan kustom dari awal (Bespoke Design) sesuai referensi moodboard &amp; palet warna impian Anda.</p>
            </div>
        </div>
        <a href="Layanan Custom.php" style="background:#FDE68A;color:#701C2F;font-weight:800;font-size:12px;padding:10px 20px;border-radius:10px;text-decoration:none;white-space:nowrap;box-shadow:0 2px 8px rgba(0,0,0,0.15);">
            Buka Layanan Custom →
        </a>
    </div>

    <div style="background:#FFFFFF;border:1px solid #E5E7EB;border-radius:14px;padding:12px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;box-shadow:0 2px 6px rgba(0,0,0,0.03);">
        <input type="text" id="userTplLiveSearch" placeholder="Cari template berdasarkan judul, fitur, atau kategori..." oninput="filterUserTemplatesLive()" style="width:100%;border:none;outline:none;font-size:13px;color:#1F2937;">
    </div>

    <div class="filter-tabs">
        <a href="Katalog Template.php?cat=all" class="filter-btn <?= ($selected_category === 'all' || $selected_category === '') ? 'active' : '' ?>">
            <span>Semua Desain</span>
        </a>
        <a href="Katalog Template.php?cat=Pernikahan" class="filter-btn <?= $selected_category === 'Pernikahan' ? 'active' : '' ?>">
            <span>💍 Pernikahan</span>
        </a>
        <a href="Katalog Template.php?cat=Ulang%20Tahun" class="filter-btn <?= $selected_category === 'Ulang Tahun' ? 'active' : '' ?>">
            <span>🎂 Ulang Tahun</span>
        </a>
        <a href="Katalog Template.php?cat=Khitanan" class="filter-btn <?= $selected_category === 'Khitanan' ? 'active' : '' ?>">
            <span>🌙 Khitanan</span>
        </a>
        <a href="Katalog Template.php?cat=Aqiqah" class="filter-btn <?= $selected_category === 'Aqiqah' ? 'active' : '' ?>">
            <span>👶 Aqiqah</span>
        </a>
        <a href="Katalog Template.php?cat=Wisuda" class="filter-btn <?= $selected_category === 'Wisuda' ? 'active' : '' ?>">
            <span>🎓 Wisuda</span>
        </a>
        <a href="Katalog Template.php?cat=<?= urlencode('Syukuran & Acara') ?>" 
   class="filter-btn <?= $selected_category === 'Syukuran & Acara' ? 'active' : '' ?>">
            <span>🏢 Syukuran &amp; Acara</span>
        </a>
    </div>

    <div class="templates-grid">

        <?php if (empty($templates_list)): ?>
            <p style="grid-column:1/-1;text-align:center;padding:30px;color:#6B7280;">
                Belum ada template aktif pada kategori ini.
            </p>
        <?php endif; ?>

        <?php foreach ($templates_list as $row): ?>
            <?php
                $imageUrl = $row['image_url_resolved'] ?? '';
                $templateJson = json_encode(
                    $row,
                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                );
                $basePrice = (float) ($row['price'] ?? 0);
                $discountPrice = (float) ($row['discount_price'] ?? 0);
                $effectivePrice = (
                    $discountPrice > 0 && $discountPrice < $basePrice
                ) ? $discountPrice : $basePrice;
            ?>

            <div class="template-card">
                <div class="template-thumb">

                    <?php if ($imageUrl !== ''): ?>
                        <img
                            src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($row['title'] ?? 'Template', ENT_QUOTES, 'UTF-8') ?>"
                            loading="lazy"
                            onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';"
                        >
                        <div class="template-image-placeholder" style="display:none;">
                            Gambar tidak dapat dimuat.
                        </div>
                    <?php else: ?>
                        <div class="template-image-placeholder">
                            Gambar belum tersedia.
                        </div>
                    <?php endif; ?>

                    <span class="category-tag">
                        <?= htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="template-body">
                    <div>
                        <h3><?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="template-features">
                            <?= htmlspecialchars(
                                $row['features'] ?? 'Hitung Mundur • Google Maps • Galeri Foto • Background Musik • RSVP WhatsApp',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>
                    </div>

                    <div>
                        <div class="template-price-row">
                            <span class="price-main">
                                Rp <?= number_format($effectivePrice, 0, ',', '.') ?>
                            </span>

                            <?php if ($discountPrice > 0 && $discountPrice < $basePrice): ?>
                                <span class="price-discount">
                                    Rp <?= number_format($basePrice, 0, ',', '.') ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="template-actions">
                            <?php $demoUrl = trim((string) ($row['preview_url'] ?? '')); ?>

                            <button
                                type="button"
                                class="btn-demo js-live-demo"
                                data-preview-url="<?= htmlspecialchars($demoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                data-preview-title="<?= htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            >
                                Live Demo
                            </button>

                            <button
                                type="button"
                                class="btn-order-tpl"
                                onclick='openModalDataAwal(<?= $templateJson ?>)'
                            >
                                Pesan Desain
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>
</main>

<!-- MODAL LIVE DEMO -->
<div id="liveDemoModal" class="live-demo-overlay" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="liveDemoTitle">
    <section class="live-demo-dialog">
        <header class="live-demo-header">
            <div class="live-demo-heading">
                <h2 id="liveDemoTitle">Live Demo Template</h2>
                <p id="liveDemoSubtitle">Pratinjau website undangan</p>
            </div>

            <div class="live-demo-header-actions">
                <a id="liveDemoExternalLink" class="live-demo-external" href="#" target="_blank" rel="noopener noreferrer" hidden>
                    Buka tab baru ↗
                </a>
                <button type="button" class="live-demo-close" onclick="closeLiveDemo()" aria-label="Tutup Live Demo">✕</button>
            </div>
        </header>

        <div class="live-demo-status" id="liveDemoStatus" role="status" aria-live="polite">
            Memuat pratinjau...
        </div>

        <div class="live-demo-frame-wrap">
            <iframe id="liveDemoFrame" title="Pratinjau website undangan" src="about:blank" loading="eager" referrerpolicy="strict-origin-when-cross-origin" allow="fullscreen; autoplay; clipboard-read; clipboard-write"></iframe>
        </div>

        <footer class="live-demo-footer">
            <span>Jika pratinjau tidak muncul, situs tujuan mungkin membatasi tampilan di iframe.</span>
            <button type="button" class="live-demo-fallback" id="liveDemoFallbackButton" onclick="openLiveDemoExternal()" hidden>
                Buka website di tab baru
            </button>
        </footer>
    </section>
</div>

<!-- MODAL DATA AWAL ACARA -->
<div id="modalDataAwalAcara" class="modal-data-awal-overlay">
    <div class="modal-data-awal-card">

        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;border-bottom:1.5px solid #FAF3DE;padding-bottom:12px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background:#FAF3DE;color:#701C2F;display:flex;align-items:center;justify-content:center;font-size:18px;">📝</div>
                <div>
                    <h3 style="font-family:'Playfair Display',serif;font-size:17px;color:#701C2F;margin:0;">Formulir Data Awal Pemesanan</h3>
                    <p style="font-size:11px;color:#6B7280;margin:2px 0 0;">Lengkapi data dasar acara Anda untuk langsung diarahkan ke menu Tracking Order.</p>
                </div>
            </div>
            <button type="button" onclick="closeModalDataAwal()" style="background:#F3F4F6;border:none;width:30px;height:30px;border-radius:50%;font-size:14px;font-weight:bold;color:#4B5563;cursor:pointer;">✕</button>
        </div>

        <div style="display:flex;align-items:center;gap:14px;background:#FAF3DE;border:1px solid #E5D7B7;border-radius:14px;padding:12px;margin-bottom:18px;">
            <img id="modalTplThumb" src="" alt="Thumbnail" style="width:60px;height:60px;border-radius:10px;object-fit:cover;border:1px solid #E5D7B7;">
            <div style="flex:1;">
                <span id="modalTplCategory" style="font-size:10px;font-weight:bold;background:#701C2F;color:#FFF;padding:2px 6px;border-radius:4px;">Kategori</span>
                <h4 id="modalTplTitle" style="font-size:14px;color:#1F2937;margin:4px 0 2px;">Judul Template</h4>
                <div id="modalTplPrice" style="font-size:13px;font-weight:800;color:#701C2F;">Rp 0</div>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="pilih_template_data_awal">
            <input type="hidden" name="template_id" id="modalInputTplId">
            <input type="hidden" name="template_name" id="modalInputTplName">
            <input type="hidden" name="price" id="modalInputTplPrice">

            <div style="margin-bottom:12px;">
                <label style="font-size:11px;font-weight:bold;color:#374151;display:block;margin-bottom:4px;">Nama Acara / Pasangan Mempelai *</label>
                <input type="text" name="nama_acara" required placeholder="Contoh: Dimas Aditya & Adinda Putri (Wedding)" style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:12px;box-sizing:border-box;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                <div>
                    <label style="font-size:11px;font-weight:bold;color:#374151;display:block;margin-bottom:4px;">Tanggal Acara</label>
                    <input type="date" name="tanggal_acara" style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:12px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:bold;color:#374151;display:block;margin-bottom:4px;">Nomor WhatsApp Klien *</label>
                    <input type="text" name="customer_phone" value="<?= htmlspecialchars($user_phone, ENT_QUOTES, 'UTF-8') ?>" required style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:12px;box-sizing:border-box;">
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label style="font-size:11px;font-weight:bold;color:#374151;display:block;margin-bottom:4px;">Kota / Lokasi Acara</label>
                <input type="text" name="lokasi_acara" placeholder="Contoh: Grand Ballroom Hotel Hilton, Bandung" style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:12px;box-sizing:border-box;">
            </div>

            <div style="margin-bottom:18px;">
                <label style="font-size:11px;font-weight:bold;color:#374151;display:block;margin-bottom:4px;">Catatan Tambahan untuk Desainer (Opsional)</label>
                <textarea name="catatan_khusus" rows="2" placeholder="Tuliskan jika ada request khusus warna, musik, atau penulisan..." style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:12px;box-sizing:border-box;"></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:10px;border-top:1.5px solid #FAF3DE;padding-top:14px;">
                <button type="button" onclick="closeModalDataAwal()" style="padding:9px 16px;border:1px solid #D1D5DB;background:#FFF;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">
                    Batal
                </button>
                <button type="submit" style="padding:9px 20px;background:#701C2F;color:#FFF;border:none;border-radius:8px;font-size:12px;font-weight:bold;cursor:pointer;box-shadow:0 4px 12px rgba(112,28,47,0.2);">
                    Lanjut &amp; Masuk ke Tracking Order →
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// =====================================================
// LIVE DEMO
// =====================================================
(function setupLiveDemo() {
    var modal = document.getElementById('liveDemoModal');
    var frame = document.getElementById('liveDemoFrame');
    var status = document.getElementById('liveDemoStatus');
    var title = document.getElementById('liveDemoTitle');
    var subtitle = document.getElementById('liveDemoSubtitle');
    var externalLink = document.getElementById('liveDemoExternalLink');
    var fallbackButton = document.getElementById('liveDemoFallbackButton');
    var loadingTimer = null;
    var currentDemoUrl = '';

    function validHttpUrl(value) {
        try {
            var parsed = new URL(value);
            return (
                (parsed.protocol === 'http:' || parsed.protocol === 'https:')
                && !!parsed.hostname
            );
        } catch (error) {
            return false;
        }
    }

    window.openLiveDemo = function(button) {
        var rawUrl = (
            button && button.getAttribute('data-preview-url') || ''
        ).trim();

        var templateTitle = (
            button && button.getAttribute('data-preview-title') || 'Template'
        ).trim();

        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('live-demo-open');

        title.textContent = 'Live Demo — ' + templateTitle;
        subtitle.textContent = 'Pratinjau website undangan';

        currentDemoUrl = '';
        externalLink.hidden = true;
        fallbackButton.hidden = true;

        status.className = 'live-demo-status is-error';
        status.textContent = 'URL demo belum tersedia atau tidak valid. Admin perlu mengisi URL website undangan pada kolom URL Live Demo.';

        frame.src = 'about:blank';

        if (loadingTimer) {
            window.clearTimeout(loadingTimer);
        }

        if (!validHttpUrl(rawUrl)) {
            return;
        }

        currentDemoUrl = rawUrl;
        externalLink.href = rawUrl;
        externalLink.hidden = false;
        fallbackButton.hidden = false;

        status.className = 'live-demo-status is-loading';
        status.textContent = 'Memuat website undangan…';

        frame.onload = function() {
            if (!currentDemoUrl) return;

            if (loadingTimer) {
                window.clearTimeout(loadingTimer);
            }

            status.className = 'live-demo-status';
            status.textContent = 'Jika isi website tidak terlihat, situs tujuan mungkin memblokir iframe. Gunakan tombol buka tab baru.';
        };

        frame.src = rawUrl;

        loadingTimer = window.setTimeout(function() {
            status.className = 'live-demo-status is-error';
            status.textContent = 'Pratinjau belum dapat dipastikan tampil. Situs mungkin lambat atau melarang iframe; coba buka website di tab baru.';
        }, 10000);
    };

    window.openLiveDemoExternal = function() {
        if (currentDemoUrl && validHttpUrl(currentDemoUrl)) {
            window.open(currentDemoUrl, '_blank', 'noopener,noreferrer');
        }
    };

    window.closeLiveDemo = function() {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('live-demo-open');

        currentDemoUrl = '';
        frame.onload = null;
        frame.src = 'about:blank';

        if (loadingTimer) {
            window.clearTimeout(loadingTimer);
        }
    };

    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            window.closeLiveDemo();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.style.display === 'flex') {
            window.closeLiveDemo();
        }
    });

    document.querySelectorAll('.js-live-demo').forEach(function(button) {
        button.addEventListener('click', function() {
            window.openLiveDemo(button);
        });
    });
})();

// =====================================================
// MODAL PEMESANAN
// =====================================================
function openModalDataAwal(tpl) {
    var thumb = document.getElementById('modalTplThumb');
    var imagePath = tpl.image_url_resolved || '';

    // Gunakan jalur yang sudah diproses oleh PHP.
    // Fallback ini membantu jika data yang diterima masih berupa path lama.
    if (!imagePath && tpl.image_url) {
        imagePath = resolveTemplateImage(tpl.image_url);
    }

    thumb.onerror = function() {
        this.onerror = null;
        this.style.visibility = 'hidden';
    };

    thumb.style.visibility = 'visible';
    thumb.src = imagePath;

    document.getElementById('modalTplTitle').textContent =
        tpl.title || 'Template';

    document.getElementById('modalTplCategory').textContent =
        tpl.category || 'Undangan';

    var basePrice = Number(tpl.price) || 0;
    var discountPrice = Number(tpl.discount_price) || 0;

    var effectivePrice = (
        discountPrice > 0 && discountPrice < basePrice
    ) ? discountPrice : basePrice;

    document.getElementById('modalTplPrice').textContent =
        'Rp ' + effectivePrice.toLocaleString('id-ID');

    document.getElementById('modalInputTplId').value = tpl.id || '';
    document.getElementById('modalInputTplName').value = tpl.title || '';
    document.getElementById('modalInputTplPrice').value = effectivePrice;

    var modal = document.getElementById('modalDataAwalAcara');

    if (modal) {
        modal.style.display = 'flex';
    }
}

// Membentuk URL gambar untuk data yang belum memiliki image_url_resolved.
function resolveTemplateImage(path) {
    path = String(path || '').trim();

    if (!path) return '';

    if (/^https?:\/\//i.test(path)) {
        return path;
    }

    path = path.replace(/\\/g, '/');

    if (path.charAt(0) === '/' || path.indexOf('../') === 0) {
        return path;
    }

    return '../../' + path.replace(/^\/+/, '');
}

function closeModalDataAwal() {
    var modal = document.getElementById('modalDataAwalAcara');

    if (modal) {
        modal.style.display = 'none';
    }
}

window.addEventListener('click', function(event) {
    var modal = document.getElementById('modalDataAwalAcara');

    if (event.target === modal) {
        modal.style.display = 'none';
    }
});

// =====================================================
// PENCARIAN TEMPLATE
// =====================================================
function filterUserTemplatesLive() {
    var input = document.getElementById('userTplLiveSearch');
    var query = input.value.toLowerCase();
    var cards = document.querySelectorAll('.template-card');

    cards.forEach(function(card) {
        var text = card.textContent.toLowerCase();
        card.style.display = text.indexOf(query) > -1 ? 'flex' : 'none';
    });
}
</script>

</body>
</html>
