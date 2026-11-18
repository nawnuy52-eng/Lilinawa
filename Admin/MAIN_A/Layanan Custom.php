<?php
session_start();
require_once "../../Config/Database.php";

// Pastikan yang login adalah admin
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../../Login/Login.php");
    exit;
}

$alert = "";
$alert_type = "success";

// 1. Handle Update Paket Custom
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "update_package") {
        $pkg_id    = (int)$_POST["package_id"];
        $pkg_title = mysqli_real_escape_string($conn, trim($_POST["title"] ?? ""));
        $pkg_price = floatval($_POST["price"] ?? 0);
        $pkg_disc  = !empty($_POST["discount_price"]) ? floatval($_POST["discount_price"]) : "NULL";
        $pkg_est   = mysqli_real_escape_string($conn, trim($_POST["est_days"] ?? "2-3 Hari"));
        $pkg_desc  = mysqli_real_escape_string($conn, trim($_POST["description"] ?? ""));
        $pkg_feats = mysqli_real_escape_string($conn, trim($_POST["features"] ?? ""));

        $upd_sql = "UPDATE services SET 
                    title = '$pkg_title', 
                    price = $pkg_price, 
                    discount_price = " . ($pkg_disc === "NULL" ? "NULL" : $pkg_disc) . ", 
                    est_days = '$pkg_est', 
                    description = '$pkg_desc',
                    features = '$pkg_feats'
                    WHERE id = $pkg_id";
        if (mysqli_query($conn, $upd_sql)) {
            $alert = "Data paket custom '$pkg_title' berhasil diperbarui!";
        } else {
            $alert = "Gagal memperbarui paket: " . mysqli_error($conn);
            $alert_type = "danger";
        }
    }
    // 2. Handle Tambah Paket Custom Baru
    elseif ($_POST["action"] === "add_package") {
        $pkg_code  = mysqli_real_escape_string($conn, trim($_POST["service_code"] ?? ("SRV-CUSTOM-" . date("is"))));
        $pkg_title = mysqli_real_escape_string($conn, trim($_POST["title"] ?? ""));
        $pkg_price = floatval($_POST["price"] ?? 0);
        $pkg_disc  = !empty($_POST["discount_price"]) ? floatval($_POST["discount_price"]) : "NULL";
        $pkg_est   = mysqli_real_escape_string($conn, trim($_POST["est_days"] ?? "2 Hari"));
        $pkg_desc  = mysqli_real_escape_string($conn, trim($_POST["description"] ?? ""));
        $pkg_feats = mysqli_real_escape_string($conn, trim($_POST["features"] ?? ""));

        $ins_sql = "INSERT INTO services (service_code, title, type, price, discount_price, description, features, est_days, is_active)
                    VALUES ('$pkg_code', '$pkg_title', 'custom', $pkg_price, " . ($pkg_disc === "NULL" ? "NULL" : $pkg_disc) . ", '$pkg_desc', '$pkg_feats', '$pkg_est', 1)";
        if (mysqli_query($conn, $ins_sql)) {
            $alert = "Paket custom baru '$pkg_title' berhasil ditambahkan!";
        } else {
            $alert = "Gagal menambahkan paket: " . mysqli_error($conn);
            $alert_type = "danger";
        }
    }
    // 3. Handle Toggle Status Operasional
    elseif ($_POST["action"] === "toggle_operational") {
        $curr_op = $_POST["current_status"] ?? "buka";
        $new_op  = ($curr_op === "buka") ? "tutup" : "buka";
        mysqli_query($conn, "UPDATE store_settings SET operational_status = '$new_op' WHERE id = 1");
        $alert = "Status operasional layanan custom berhasil diubah menjadi: " . ($new_op === "buka" ? "Aktif Melayani (Buka)" : "Tutup Sementara (Antrean Penuh)");
    }
    // 4. Handle Update Status Order Custom
    elseif ($_POST["action"] === "update_order_status") {
        $order_id   = (int)$_POST["order_id"];
        $new_status = mysqli_real_escape_string($conn, trim($_POST["new_status"]));
        $pay_status = mysqli_real_escape_string($conn, trim($_POST["payment_status"]));
        $step_idx   = 1;
        if ($new_status === "data_terkirim") $step_idx = 2;
        if (in_array($new_status, ["diproses", "sedang_diproses", "koding"])) $step_idx = 3;
        if (in_array($new_status, ["review", "review_desain"])) $step_idx = 4;
        if (in_array($new_status, ["selesai", "jadi", "undangan_jadi", "dikirim"])) $step_idx = 5;

        mysqli_query($conn, "UPDATE orders SET status = '$new_status', payment_status = '$pay_status', step_index = $step_idx WHERE id = $order_id");
        if ($pay_status === "lunas") {
            @mysqli_query($conn, "UPDATE invoices SET payment_status = 'paid', paid_at = NOW() WHERE order_id = $order_id");
        }
        $alert = "Status pengerjaan pesanan custom berhasil diperbarui!";
    }
}

// Ambil status operasional toko
$store_q = mysqli_query($conn, "SELECT operational_status, phone_whatsapp FROM store_settings WHERE id = 1");
$store   = mysqli_fetch_assoc($store_q);
$op_status = $store["operational_status"] ?? "buka";
$hotline_wa = $store["phone_whatsapp"] ?? "081252200386";

// Ambil daftar master layanan custom dari database
$services_q = mysqli_query($conn, "SELECT * FROM services WHERE type = 'custom' ORDER BY id ASC");
$custom_services = [];
if ($services_q && mysqli_num_rows($services_q) > 0) {
    while ($row = mysqli_fetch_assoc($services_q)) {
        $custom_services[] = $row;
    }
}

// Fallback jika tabel services belum ada data custom
if (empty($custom_services)) {
    $custom_services = [
        [
            "id" => 1,
            "service_code" => "SRV-CUSTOM-01",
            "title" => "Paket Undangan Custom Eksklusif",
            "type" => "custom",
            "price" => 199000,
            "discount_price" => 249000,
            "est_days" => "2 Hari Kerja",
            "description" => "Layanan pembuatan desain undangan digital dari awal sesuai konsep, palet warna, dan referensi moodboard pengantin.",
            "features" => "Desain custom dari nol sesuai konsep klien\n3x revisi minor teks & tata letak\nBackground musik bebas request pemesan\nFitur RSVP WhatsApp & Buku Tamu interaktif\nGoogle Maps terintegrasi & Countdown\nGaleri foto prewedding (hingga 10 foto)",
            "is_active" => 1
        ],
        [
            "id" => 2,
            "service_code" => "SRV-CUSTOM-02",
            "title" => "Paket Undangan Custom Luxury VIP",
            "type" => "custom",
            "price" => 299000,
            "discount_price" => 399000,
            "est_days" => "Kilat 24 Jam",
            "description" => "Tingkat master dengan efek wax seal monogram animasi, amplop digital dinamis multi-rekening, dan prioritas antrean kilat.",
            "features" => "Bebas request konsep tanpa batas\nUnlimited revisi minor hingga hari H\nAnimasi Wax Seal Monogram digital eksklusif\nAmplop Digital Dinamis Multi-Rekening & QRIS\nLive Streaming Embed (YouTube / Zoom)\nPrioritas pengerjaan kilat selesai 24 jam",
            "is_active" => 1
        ]
    ];
}

// Ambil pesanan custom yang masuk
$custom_orders_q = mysqli_query($conn, "SELECT o.*, d.nama_acara, d.tanggal_acara, d.catatan_khusus 
                                        FROM orders o 
                                        LEFT JOIN order_event_details d ON o.id = d.order_id 
                                        WHERE o.type = 'custom' 
                                        ORDER BY o.id DESC");
$custom_orders = [];
$total_custom_orders = 0;
$total_lunas = 0;
$total_pending = 0;

if ($custom_orders_q) {
    while ($co = mysqli_fetch_assoc($custom_orders_q)) {
        $custom_orders[] = $co;
        $total_custom_orders++;
        if (($co["payment_status"] ?? "") === "lunas") {
            $total_lunas++;
        } else {
            $total_pending++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Layanan Custom - Admin LilinNawa</title>
    <link rel="stylesheet" href="../sidebar.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-maroon: #701C2F;
            --primary-maroon-dark: #581423;
            --gold-accent: #D4AF37;
            --cream-bg: #FAF3DE;
            --cream-border: #E5D7B7;
            --text-dark: #1F2937;
            --text-gray: #6B7280;
        }

        body.admin-wrapper {
            margin: 0;
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: #FAF4E4;
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        .admin-main {
            margin-left: 290px;
            flex: 1;
            padding: 28px 36px;
            max-width: 1300px;
            box-sizing: border-box;
        }

        .folder-path {
            font-size: 11px;
            font-family: monospace;
            color: #8A4B58;
            margin-bottom: 12px;
        }

        .page-title-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 22px;
        }
        .page-title-box h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 26px;
            font-weight: 800;
            color: var(--primary-maroon);
            margin: 0 0 4px 0;
        }
        .page-title-box p {
            font-size: 13px;
            color: var(--text-gray);
            margin: 0;
        }

        /* Tombol Admin */
        .btn-admin {
            padding: 9px 16px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-primary {
            background: var(--primary-maroon);
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(112, 28, 47, 0.25);
        }
        .btn-primary:hover {
            background: var(--primary-maroon-dark);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #FAF3DE;
            color: var(--primary-maroon);
            border: 1.5px solid #E5D7B7;
        }
        .btn-secondary:hover {
            background: var(--primary-maroon);
            color: #FFFFFF;
            border-color: var(--primary-maroon);
        }
        .btn-outline {
            background: #FFFFFF;
            color: #374151;
            border: 1.5px solid #D1D5DB;
        }
        .btn-outline:hover {
            background: #F9FAFB;
            border-color: #9CA3AF;
        }

        /* 4 KPI Statistik */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 26px;
        }
        .card-stat {
            background: #FFFFFF;
            border: 1.5px solid #E5D7B7;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 16px rgba(112, 28, 47, 0.03);
            transition: transform 0.2s ease;
        }
        .card-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(112, 28, 47, 0.06);
        }
        .stat-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .stat-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }
        .stat-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary-maroon);
            font-family: 'Playfair Display', serif;
            margin: 2px 0;
        }
        .stat-sub {
            font-size: 11px;
            color: #9CA3AF;
        }

        /* Grid Kartu Master Paket Custom */
        .custom-admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 22px;
            margin-bottom: 30px;
        }
        .package-card {
            background: #FFFFFF;
            border-radius: 20px;
            border: 1.5px solid #E5D7B7;
            padding: 26px;
            box-shadow: 0 8px 24px rgba(112, 28, 47, 0.04);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            transition: all 0.25s ease;
        }
        .package-card:hover {
            border-color: var(--primary-maroon);
            box-shadow: 0 12px 32px rgba(112, 28, 47, 0.08);
        }
        .package-card.highlight {
            border-color: var(--primary-maroon);
            background: linear-gradient(to bottom, #FFFDF9 0%, #FFFFFF 100%);
        }
        .badge-package {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-eksklusif { background: #FAF3DE; color: #701C2F; border: 1px solid #E5D7B7; }
        .badge-vip { background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; }

        /* Tabel Pesanan Custom */
        .table-card {
            background: #FFFFFF;
            border-radius: 20px;
            border: 1.5px solid #E5D7B7;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(112, 28, 47, 0.04);
            margin-bottom: 24px;
        }
        .table-header-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 1.5px solid #F3F4F6;
        }
        .table-header-box h3 {
            font-family: 'Playfair Display', serif;
            font-size: 19px;
            color: var(--primary-maroon);
            margin: 0 0 2px 0;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }
        .admin-table th {
            background: #FAF8F2;
            color: #701C2F;
            font-weight: 800;
            text-align: left;
            padding: 12px 14px;
            border-bottom: 2px solid #E5D7B7;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .admin-table td {
            padding: 14px;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
        }
        .admin-table tr:hover td {
            background: #FFFDF9;
        }

        /* Modal Dialog */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(5px);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 18px;
            box-sizing: border-box;
        }
        .modal-content {
            background: #FFFFFF;
            width: 100%;
            max-width: 580px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 22px;
            padding: 26px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.4);
            border: 1.5px solid #E5D7B7;
            box-sizing: border-box;
        }
        .form-group-custom {
            margin-bottom: 14px;
        }
        .form-group-custom label {
            display: block;
            font-size: 11.5px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 5px;
        }
        .form-group-custom input,
        .form-group-custom select,
        .form-group-custom textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid #D1D5DB;
            border-radius: 10px;
            font-size: 12.5px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group-custom input:focus,
        .form-group-custom select:focus,
        .form-group-custom textarea:focus {
            border-color: var(--primary-maroon);
        }

        @media (max-width: 900px) {
            .admin-main { margin-left: 70px; padding: 18px; }
            .custom-admin-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="admin-wrapper">
    <?php include "../sidebar.php"; ?>

    <main class="admin-main">
    
        <!-- Header Navigasi -->
        <div class="page-title-box">
            <div>
                <h1>✨ Manajemen Layanan Undangan Custom (Bespoke)</h1>
                <p>Kelola master paket desain custom, atur tarif &amp; estimasi pengerjaan, serta pantau seluruh pesanan custom dari klien.</p>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" onclick="openModal('modalTambahPaketCustom')" class="btn-admin btn-primary">
                    ➕ Tambah Paket Custom
                </button>
                <a href="../../User/Main_U/Layanan Custom.php" target="_blank" class="btn-admin btn-secondary" title="Buka Tampilan Halaman Custom yang Dilihat Pelanggan">
                    👁️ Lihat Halaman Klien ↗
                </a>
                <a href="Template.php" class="btn-admin btn-outline">
                    ← Kembali ke Katalog Template
                </a>
            </div>
        </div>

        <?php if (!empty($alert)): ?>
        <div style="padding:14px 18px; border-radius:12px; margin-bottom:20px; font-weight:700; font-size:13px; <?= $alert_type === 'danger' ? 'background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5;' : 'background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0;' ?>">
            <?= htmlspecialchars($alert) ?>
        </div>
        <?php endif; ?>

        <!-- 4 Kartu KPI Ringkasan -->
        <div class="stats-grid">
            <div class="card-stat">
                <div class="stat-icon-wrap" style="background:#FAF3DE; color:#701C2F;">✨</div>
                <div>
                    <span class="stat-label">Total Order Custom</span>
                    <h3 class="stat-value"><?= $total_custom_orders ?> Pesanan</h3>
                    <small class="stat-sub"><?= $total_lunas ?> Lunas &bull; <?= $total_pending ?> Menunggu Bayar</small>
                </div>
            </div>

            <div class="card-stat" style="justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:14px;">
                    <div class="stat-icon-wrap" style="background:<?= $op_status === 'buka' ? '#D1FAE5' : '#FEE2E2' ?>; color:<?= $op_status === 'buka' ? '#065F46' : '#991B1B' ?>;">
                        <?= $op_status === 'buka' ? '🟢' : '🔴' ?>
                    </div>
                    <div>
                        <span class="stat-label">Status Operasional</span>
                        <h3 class="stat-value" style="color:<?= $op_status === 'buka' ? '#059669' : '#DC2626' ?>; font-size:17px;">
                            <?= $op_status === 'buka' ? 'Aktif Melayani' : 'Tutup Sementara' ?>
                        </h3>
                        <small class="stat-sub"><?= $op_status === 'buka' ? 'Desainer standby online' : 'Antrean penuh / istirahat' ?></small>
                    </div>
                </div>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="action" value="toggle_operational">
                    <input type="hidden" name="current_status" value="<?= htmlspecialchars($op_status) ?>">
                    <button type="submit" class="btn-admin btn-secondary" style="padding:6px 10px; font-size:11px;" title="Ubah status operasional penerimaan pesanan custom">
                        ⚙️ Ubah
                    </button>
                </form>
            </div>

            <div class="card-stat">
                <div class="stat-icon-wrap" style="background:#E0F2FE; color:#0284C7;">⏱️</div>
                <div>
                    <span class="stat-label">Rata-rata Waktu</span>
                    <h3 class="stat-value" style="font-size:18px;">1 - 2 Hari Kerja</h3>
                    <small class="stat-sub">Sesuai brief moodboard klien</small>
                </div>
            </div>

            <div class="card-stat">
                <div class="stat-icon-wrap" style="background:#F3E8FF; color:#7C3AED;">💬</div>
                <div>
                    <span class="stat-label">Hotline WhatsApp Admin</span>
                    <h3 class="stat-value" style="color:#7C3AED; font-size:16px;"><?= htmlspecialchars($hotline_wa) ?></h3>
                    <small class="stat-sub">Konsultasi langsung klien</small>
                </div>
            </div>
        </div>

        <!-- Master Paket Desain Custom Resmi -->
        <div style="margin-bottom:30px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <h3 style="font-family:'Playfair Display',serif; font-size:19px; color:#701C2F; margin:0;">
                    📦 Paket Desain Custom Resmi (Bisa Diedit Admin)
                </h3>
                <span style="font-size:12px; color:#6B7280;">
                    Klik <strong>Edit Paket</strong> untuk mengubah harga, diskon, dan benefit paket.
                </span>
            </div>

            <div class="custom-admin-grid">
                <?php foreach ($custom_services as $idx => $pkg): 
                    $is_vip = (stripos($pkg["title"], "vip") !== false || $idx === 1);
                    $features_list = array_filter(array_map("trim", preg_split("/\\r\\n|\\n|\\r|,/", $pkg["features"] ?? "")));
                ?>
                <div class="package-card <?= $is_vip ? 'highlight' : '' ?>" id="card-pkg-<?= $pkg["id"] ?>">
                    <div>
                        <!-- Header Paket -->
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                            <span class="badge-package <?= $is_vip ? 'badge-vip' : 'badge-eksklusif' ?>">
                                <?= $is_vip ? '👑 Paket VIP &bull; Prioritas' : 'Paket ' . ($idx + 1) . ' &bull; Best Seller' ?>
                            </span>
                            <span style="font-size:12px; font-weight:800; color:#059669; background:#ECFDF5; padding:3px 10px; border-radius:99px; border:1px solid #A7F3D0;">
                                ⏱️ <?= htmlspecialchars($pkg["est_days"] ?: "2 Hari Kerja") ?>
                            </span>
                        </div>

                        <!-- Judul & Harga -->
                        <h4 style="font-size:18px; color:#1F2937; margin:0 0 6px 0; font-family:'Playfair Display',serif; font-weight:800;">
                            <?= htmlspecialchars($pkg["title"]) ?>
                        </h4>

                        <div style="font-size:24px; font-weight:900; color:#701C2F; margin-bottom:12px; font-family:'Playfair Display',serif;">
                            Rp <?= number_format($pkg["price"], 0, ',', '.') ?>
                            <?php if (!empty($pkg["discount_price"]) && $pkg["discount_price"] > $pkg["price"]): ?>
                            <span style="font-size:13px; color:#9CA3AF; text-decoration:line-through; font-weight:normal; margin-left:6px; font-family:'Plus Jakarta Sans',sans-serif;">
                                Rp <?= number_format($pkg["discount_price"], 0, ',', '.') ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <!-- Deskripsi -->
                        <p style="font-size:12px; color:#4B5563; line-height:1.6; margin-bottom:14px;">
                            <?= htmlspecialchars($pkg["description"]) ?>
                        </p>

                        <!-- Benefit List -->
                        <div style="font-size:11.5px; color:#374151; line-height:1.8; background:<?= $is_vip ? '#FFFBEB' : '#FAF3DE' ?>; padding:14px 16px; border-radius:14px; border:1px solid <?= $is_vip ? '#FDE68A' : '#E5D7B7' ?>; margin-bottom:18px;">
                            <?php if (!empty($features_list)): ?>
                                <?php foreach ($features_list as $feat): ?>
                                    <div>✓ <?= htmlspecialchars($feat) ?></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div>✓ Desain custom dari nol sesuai konsep</div>
                                <div>✓ Revisi minor teks &amp; tata letak</div>
                                <div>✓ Musik latar &amp; Google Maps</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Footer Kartu: Aksi Admin -->
                    <div style="display:flex; justify-content:space-between; align-items:center; padding-top:14px; border-top:1.5px solid #E5E7EB; margin-top:8px;">
                        <span style="font-size:11px; color:#6B7280; font-family:monospace;">
                            Kode: <code><?= htmlspecialchars($pkg["service_code"] ?? ("SRV-CUSTOM-0" . ($idx + 1))) ?></code>
                        </span>
                        <div style="display:flex; gap:8px;">
                            <button type="button" onclick="editPackage(<?= htmlspecialchars(json_encode($pkg)) ?>)" class="btn-admin btn-secondary" style="font-size:11.5px; padding:7px 14px;">
                                ✏️ Edit Paket &amp; Harga
                            </button>
                            <a href="Order.php?status_filter=all&search=custom" class="btn-admin btn-outline" style="font-size:11px; padding:7px 12px;">
                                📋 Order →
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Tabel Pesanan Undangan Custom Klien -->
        <div class="table-card">
            <div class="table-header-box">
                <div>
                    <h3>📋 Daftar Pesanan Undangan Custom Klien (<?= $total_custom_orders ?>)</h3>
                    <p style="font-size:12px; color:#6B7280; margin:0;">Kelola tahapan pengerjaan koding dan pantau pembayaran pesanan custom klien.</p>
                </div>
                <div style="display:flex; gap:10px; align-items:center;">
                    <!-- Live Search Input -->
                    <input type="text" id="customOrderSearch" oninput="filterCustomOrders(this.value)" placeholder="Cari No Order, Nama, WA, Acara..." style="padding:8px 14px; border:1.5px solid #D1D5DB; border-radius:10px; font-size:12px; outline:none; min-width:240px;">
                    <a href="Order.php?status_filter=all&search=custom" class="btn-admin btn-secondary" style="font-size:11px;">
                        Buka Manajemen Order →
                    </a>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table class="admin-table" id="customOrdersTable">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Pemesan &amp; WhatsApp</th>
                            <th>Paket Custom</th>
                            <th>Rincian Acara &amp; Moodboard</th>
                            <th>Status Pengerjaan</th>
                            <th>Pembayaran</th>
                            <th style="text-align:right;">Aksi Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($custom_orders)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:36px; color:#9CA3AF;">
                                Belum ada pesanan custom yang masuk. Klien dapat membuat pesanan custom melalui halaman <strong>Layanan Custom</strong> di portal klien.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($custom_orders as $co): ?>
                        <tr class="custom-order-row" data-search="<?= strtolower(htmlspecialchars(($co["order_number"] ?? "") . " " . ($co["customer_name"] ?? "") . " " . ($co["customer_phone"] ?? "") . " " . ($co["nama_acara"] ?? "") . " " . ($co["template_name"] ?? ""))) ?>">
                            <td>
                                <strong style="color:#701C2F; font-size:12.5px;"><?= htmlspecialchars($co["order_number"]) ?></strong>
                                <div style="font-size:10.5px; color:#9CA3AF; margin-top:2px;"><?= htmlspecialchars($co["date_created"] ?? $co["created_at"] ?? "") ?></div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($co["customer_name"]) ?></strong><br>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $co["customer_phone"]) ?>" target="_blank" style="color:#059669; font-size:11.5px; text-decoration:none; font-weight:700;">
                                    💬 <?= htmlspecialchars($co["customer_phone"]) ?>
                                </a>
                            </td>
                            <td>
                                <strong style="font-size:12.5px;"><?= htmlspecialchars($co["template_name"] ?: "Custom Bespoke") ?></strong><br>
                                <span style="font-family:'Playfair Display',serif; font-weight:800; color:#701C2F;">Rp <?= number_format($co["total_price"], 0, ',', '.') ?></span>
                            </td>
                            <td>
                                <span style="font-weight:700; color:#1F2937;"><?= htmlspecialchars($co["nama_acara"] ?: "Acara Klien") ?></span><br>
                                <small style="color:#6B7280;"><?= htmlspecialchars($co["tanggal_acara"] ?: "-") ?></small>
                                <?php if (!empty($co["catatan_khusus"])): ?>
                                <div style="font-size:11px; color:#4B5563; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($co["catatan_khusus"]) ?>">
                                    📝 <?= htmlspecialchars($co["catatan_khusus"]) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $st = $co["status"] ?? "konsultasi";
                                $badge_bg = "#FAF3DE"; $badge_color = "#701C2F"; $badge_border = "#E5D7B7";
                                if ($st === "diproses") { $badge_bg = "#E0F2FE"; $badge_color = "#0369A1"; $badge_border = "#BAE6FD"; }
                                elseif ($st === "review") { $badge_bg = "#FEF3C7"; $badge_color = "#92400E"; $badge_border = "#FDE68A"; }
                                elseif (in_array($st, ["selesai", "undangan_jadi", "dikirim"])) { $badge_bg = "#D1FAE5"; $badge_color = "#065F46"; $badge_border = "#A7F3D0"; }
                                elseif ($st === "dibatalkan") { $badge_bg = "#FEE2E2"; $badge_color = "#991B1B"; $badge_border = "#FCA5A5"; }
                                ?>
                                <span style="display:inline-block; padding:3px 9px; border-radius:8px; font-size:11px; font-weight:800; background:<?= $badge_bg ?>; color:<?= $badge_color ?>; border:1px solid <?= $badge_border ?>;">
                                    <?= ucwords(str_replace('_', ' ', $st)) ?>
                                </span>
                            </td>
                            <td>
                                <?php if(($co["payment_status"] ?? "") === "lunas"): ?>
                                    <span style="display:inline-block; padding:3px 9px; border-radius:8px; font-size:11px; font-weight:800; background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0;">
                                        ✓ Lunas
                                    </span>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:3px 9px; border-radius:8px; font-size:11px; font-weight:800; background:#FEF3C7; color:#92400E; border:1px solid #FCD34D;">
                                        ⏳ Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex; gap:6px;">
                                    <a href="../INVOICE_A/Invoice.php?order=<?= urlencode($co["order_number"]) ?>" class="btn-admin btn-secondary" style="padding:6px 10px; font-size:11px;" title="Buka Faktur Invoice">
                                        🧾 Invoice
                                    </a>
                                    <button type="button" onclick="quickUpdateStatus(<?= htmlspecialchars(json_encode($co)) ?>)" class="btn-admin btn-primary" style="padding:6px 12px; font-size:11px;" title="Ubah status pengerjaan pesanan ini">
                                        ⚡ Status
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- ======================================================== -->
    <!-- MODAL 1: EDIT PAKET CUSTOM & HARGA -->
    <!-- ======================================================== -->
    <div id="modalEditPaketCustom" class="modal-overlay" onclick="if(event.target===this) closeModal('modalEditPaketCustom')">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #E5E7EB; padding-bottom:12px; margin-bottom:18px;">
                <h3 style="font-family:'Playfair Display',serif; font-size:19px; color:#701C2F; margin:0;">
                    ✏️ Edit Paket Desain Custom
                </h3>
                <button type="button" onclick="closeModal('modalEditPaketCustom')" style="background:#F3F4F6; border:none; width:30px; height:30px; border-radius:50%; cursor:pointer;">✕</button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_package">
                <input type="hidden" name="package_id" id="edit_pkg_id" value="">

                <div class="form-group-custom">
                    <label>Nama Paket Custom *</label>
                    <input type="text" name="title" id="edit_pkg_title" required placeholder="Contoh: Paket Undangan Custom Eksklusif">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group-custom">
                        <label>Harga Jual (Rp) *</label>
                        <input type="number" name="price" id="edit_pkg_price" required min="0" step="1000" placeholder="199000">
                    </div>
                    <div class="form-group-custom">
                        <label>Harga Coret / Normal (Rp)</label>
                        <input type="number" name="discount_price" id="edit_pkg_discount_price" min="0" step="1000" placeholder="249000">
                    </div>
                </div>

                <div class="form-group-custom">
                    <label>Estimasi Waktu Pengerjaan *</label>
                    <input type="text" name="est_days" id="edit_pkg_est_days" required placeholder="Contoh: 2 Hari Kerja atau Kilat 24 Jam">
                </div>

                <div class="form-group-custom">
                    <label>Deskripsi Singkat Paket</label>
                    <textarea name="description" id="edit_pkg_description" rows="2" placeholder="Jelaskan keistimewaan paket custom ini..."></textarea>
                </div>

                <div class="form-group-custom">
                    <label>Fitur &amp; Keunggulan (Pisahkan tiap poin dengan baris baru / Enter)</label>
                    <textarea name="features" id="edit_pkg_features" rows="5" placeholder="Contoh:
Desain custom dari nol sesuai konsep
3x revisi minor tata letak
Background musik bebas request pemesan
Fitur RSVP WhatsApp & Buku Tamu"></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #E5E7EB; padding-top:14px;">
                    <button type="button" onclick="closeModal('modalEditPaketCustom')" class="btn-admin btn-outline">
                        Batal
                    </button>
                    <button type="submit" class="btn-admin btn-primary">
                        💾 Simpan Perubahan Paket
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 2: TAMBAH PAKET CUSTOM BARU -->
    <!-- ======================================================== -->
    <div id="modalTambahPaketCustom" class="modal-overlay" onclick="if(event.target===this) closeModal('modalTambahPaketCustom')">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #E5E7EB; padding-bottom:12px; margin-bottom:18px;">
                <h3 style="font-family:'Playfair Display',serif; font-size:19px; color:#701C2F; margin:0;">
                    ➕ Tambah Paket Desain Custom Baru
                </h3>
                <button type="button" onclick="closeModal('modalTambahPaketCustom')" style="background:#F3F4F6; border:none; width:30px; height:30px; border-radius:50%; cursor:pointer;">✕</button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="add_package">

                <div class="form-group-custom">
                    <label>Nama Paket Custom *</label>
                    <input type="text" name="title" required placeholder="Contoh: Paket Custom Premiere Deluxe">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group-custom">
                        <label>Kode Layanan</label>
                        <input type="text" name="service_code" value="SRV-CUSTOM-<?= date('is') ?>" required>
                    </div>
                    <div class="form-group-custom">
                        <label>Estimasi Pengerjaan *</label>
                        <input type="text" name="est_days" value="2 Hari Kerja" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div class="form-group-custom">
                        <label>Harga Jual (Rp) *</label>
                        <input type="number" name="price" required min="0" step="1000" placeholder="250000">
                    </div>
                    <div class="form-group-custom">
                        <label>Harga Normal / Coret (Rp)</label>
                        <input type="number" name="discount_price" min="0" step="1000" placeholder="320000">
                    </div>
                </div>

                <div class="form-group-custom">
                    <label>Deskripsi Singkat</label>
                    <textarea name="description" rows="2" placeholder="Ringkasan spesifikasi paket..."></textarea>
                </div>

                <div class="form-group-custom">
                    <label>Fitur Keunggulan (Pisahkan dengan baris baru)</label>
                    <textarea name="features" rows="4" placeholder="Desain custom khusus
Revisi minor
Amplop digital interaktif"></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #E5E7EB; padding-top:14px;">
                    <button type="button" onclick="closeModal('modalTambahPaketCustom')" class="btn-admin btn-outline">
                        Batal
                    </button>
                    <button type="submit" class="btn-admin btn-primary">
                        ➕ Buat Paket Baru
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================================================== -->
    <!-- MODAL 3: UPDATE CEPAT STATUS ORDER CUSTOM -->
    <!-- ======================================================== -->
    <div id="modalUpdateStatusCustom" class="modal-overlay" onclick="if(event.target===this) closeModal('modalUpdateStatusCustom')">
        <div class="modal-content" style="max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #E5E7EB; padding-bottom:12px; margin-bottom:18px;">
                <h3 style="font-family:'Playfair Display',serif; font-size:18px; color:#701C2F; margin:0;">
                    ⚡ Update Status Pesanan Custom
                </h3>
                <button type="button" onclick="closeModal('modalUpdateStatusCustom')" style="background:#F3F4F6; border:none; width:30px; height:30px; border-radius:50%; cursor:pointer;">✕</button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" id="status_order_id" value="">

                <div style="background:#FAF8F2; padding:12px 14px; border-radius:12px; border:1px solid #E5D7B7; margin-bottom:16px;">
                    <div style="font-size:11px; color:#6B7280;">Pesanan:</div>
                    <strong style="color:#701C2F; font-size:14px;" id="status_order_number">#LN000</strong>
                    <div style="font-size:12px; color:#374151; margin-top:2px;" id="status_customer_name">Nama Pemesan</div>
                </div>

                <div class="form-group-custom">
                    <label>Tahapan Pengerjaan Desainer *</label>
                    <select name="new_status" id="status_new_status" required>
                        <option value="konsultasi">Step 2: Konsultasi / Brief Konsep</option>
                        <option value="data_terkirim">Step 3: Data Acara Terkirim</option>
                        <option value="diproses">Step 4: Pengerjaan Koding Desainer</option>
                        <option value="review">Step 5: Review Undangan Siap</option>
                        <option value="selesai">Step 6: Undangan Selesai &amp; Jadi (Aktif)</option>
                        <option value="dibatalkan">Dibatalkan</option>
                    </select>
                </div>

                <div class="form-group-custom">
                    <label>Status Pembayaran QRIS *</label>
                    <select name="payment_status" id="status_payment_status" required>
                        <option value="lunas">Lunas (Sudah Bayar)</option>
                        <option value="pending">Pending (Menunggu Pembayaran)</option>
                    </select>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; border-top:1px solid #E5E7EB; padding-top:14px;">
                    <button type="button" onclick="closeModal('modalUpdateStatusCustom')" class="btn-admin btn-outline">
                        Batal
                    </button>
                    <button type="submit" class="btn-admin btn-primary">
                        💾 Simpan Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openModal(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = "flex";
    }

    function closeModal(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = "none";
    }

    function editPackage(pkg) {
        document.getElementById("edit_pkg_id").value = pkg.id;
        document.getElementById("edit_pkg_title").value = pkg.title;
        document.getElementById("edit_pkg_price").value = pkg.price;
        document.getElementById("edit_pkg_discount_price").value = pkg.discount_price || "";
        document.getElementById("edit_pkg_est_days").value = pkg.est_days || "";
        document.getElementById("edit_pkg_description").value = pkg.description || "";
        document.getElementById("edit_pkg_features").value = pkg.features ? pkg.features.replace(/\\r\\n|\\r|\\n/g, "\n") : "";
        openModal("modalEditPaketCustom");
    }

    function quickUpdateStatus(order) {
        document.getElementById("status_order_id").value = order.id;
        document.getElementById("status_order_number").textContent = order.order_number;
        document.getElementById("status_customer_name").textContent = order.customer_name + " (" + (order.template_name || "Custom") + ")";
        document.getElementById("status_new_status").value = order.status || "konsultasi";
        document.getElementById("status_payment_status").value = order.payment_status || "pending";
        openModal("modalUpdateStatusCustom");
    }

    function filterCustomOrders(q) {
        q = (q || "").toLowerCase().trim();
        var rows = document.querySelectorAll(".custom-order-row");
        rows.forEach(function(row) {
            var searchData = row.getAttribute("data-search") || "";
            if (!q || searchData.indexOf(q) !== -1) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    }
    </script>
</body>
</html>
