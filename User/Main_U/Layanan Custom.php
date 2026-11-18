<?php
// User/Main_U/Layanan Custom.php - Layanan Pemesanan Undangan Digital Custom LilinNawa
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2;
    $_SESSION['name'] = 'Dimas Aditya';
    $_SESSION['email'] = 'dimas.aditya@gmail.com';
    $_SESSION['phone'] = '081234567890';
    $_SESSION['role'] = 'customer';
}

$user_id    = (int)$_SESSION['user_id'];
$user_name  = $_SESSION['name'] ?? 'Dimas Aditya';
$user_phone = $_SESSION['phone'] ?? '081234567890';
$user_email = $_SESSION['email'] ?? 'dimas.aditya@gmail.com';

$success_msg = '';
$error_msg   = '';

// Handle Pemesanan Undangan Custom
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'order_custom') {
    $cust_name    = mysqli_real_escape_string($conn, trim($_POST['customer_name'] ?? $user_name));
    $cust_phone   = mysqli_real_escape_string($conn, trim($_POST['customer_phone'] ?? $user_phone));
    $cust_email   = mysqli_real_escape_string($conn, trim($_POST['customer_email'] ?? $user_email));
    $package_name = mysqli_real_escape_string($conn, trim($_POST['package_name'] ?? 'Paket Undangan Custom Eksklusif'));
    $package_price= floatval($_POST['package_price'] ?? 199000);
    $theme_style  = mysqli_real_escape_string($conn, trim($_POST['theme_style'] ?? 'Modern Minimalist'));
    $features_req = isset($_POST['features']) ? implode(', ', array_map(function($f) use ($conn) { return mysqli_real_escape_string($conn, $f); }, $_POST['features'])) : 'Musik, RSVP, Maps, Countdown';
    $moodboard    = mysqli_real_escape_string($conn, trim($_POST['moodboard_url'] ?? ''));
    $notes        = mysqli_real_escape_string($conn, trim($_POST['custom_notes'] ?? ''));

    if (empty($cust_name) || empty($cust_phone)) {
        $error_msg = 'Nama lengkap dan nomor WhatsApp wajib diisi!';
    } else {
        $order_number = '#LN' . rand(100, 999);
        $full_template_name = "$package_name ($theme_style)";

        $sql_order = "INSERT INTO orders (order_number, user_id, customer_name, customer_email, customer_phone, type, template_name, status, step_index, total_price, payment_status) 
                      VALUES ('$order_number', $user_id, '$cust_name', '$cust_email', '$cust_phone', 'custom', '$full_template_name', 'pilih_template', 0, $package_price, 'pending')";
        
        if (mysqli_query($conn, $sql_order)) {
            $new_order_id = mysqli_insert_id($conn);

            // Simpan rincian data acara awal
            $combined_notes = "Konsep: $theme_style. Fitur Request: $features_req. Moodboard: $moodboard. Catatan: $notes";
            mysqli_query($conn, "INSERT INTO order_event_details (order_id, nama_acara, tanggal_acara, waktu_acara, lokasi_acara, alamat_lengkap, catatan_khusus) 
                                 VALUES ($new_order_id, 'Acara Custom $cust_name', CURDATE(), '09:00 - Selesai WIB', 'Menunggu konfirmasi', 'Menunggu konfirmasi', '$combined_notes')");

            // Kirim pesan ke inbox CS
            $clean_phone = preg_replace('/[^0-9]/', '', $cust_phone);
            $room_id = 'room-' . $clean_phone;
            $chat_msg = "Halo Admin LilinNawa, saya baru saja mengajukan pesanan Undangan Custom ($order_number) dengan paket $package_name dan konsep $theme_style. Mohon arahan tahap konsultasinya ya!";
            mysqli_query($conn, "INSERT INTO inbox (room_id, order_number, sender_name, sender_phone, recipient_phone, sender_role, message) 
                                 VALUES ('$room_id', '$order_number', '$cust_name', '$cust_phone', '081252200386', 'customer', '$chat_msg')");

            header("Location: Status & TrackingOrder.php?order=" . urlencode($order_number) . "&status=custom_created");
            exit;
        } else {
            $error_msg = 'Gagal menyimpan pesanan custom: ' . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layanan Undangan Custom - LilinNawa</title>
    <link rel="stylesheet" href="../sidebar.css">
    <style>
        .custom-page-wrapper {
            max-width: 1000px;
            margin: 0 auto;
        }
        .custom-hero {
            background: linear-gradient(135deg, #701C2F 0%, #4A0E1C 100%);
            color: #FFFFFF;
            border-radius: 24px;
            padding: 32px 36px;
            margin-bottom: 28px;
            box-shadow: 0 10px 30px rgba(112, 28, 47, 0.25);
            position: relative;
            overflow: hidden;
        }
        .custom-hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            margin: 0 0 8px 0;
            color: #FDE68A;
        }
        .custom-hero p {
            font-size: 14px;
            color: #F3F4F6;
            margin: 0;
            max-width: 650px;
            line-height: 1.6;
        }
        .packages-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        @media (max-width: 768px) {
            .packages-grid { grid-template-columns: 1fr; }
        }
        .package-card {
            background: #FFFFFF;
            border-radius: 20px;
            border: 2px solid #E5E7EB;
            padding: 26px;
            position: relative;
            transition: all 0.2s ease;
            box-shadow: 0 4px 16px rgba(0,0,0,0.03);
        }
        .package-card.recommended {
            border-color: #D4AF37;
            background: linear-gradient(180deg, #FFFDF8 0%, #FFFFFF 100%);
        }
        .badge-recommended {
            position: absolute;
            top: 14px;
            right: 16px;
            background: #D4AF37;
            color: #111827;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 9999px;
            text-transform: uppercase;
        }
        .package-title {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            color: #701C2F;
            margin: 0 0 6px 0;
        }
        .package-price {
            font-size: 26px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 14px;
        }
        .package-features {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
            font-size: 13px;
            color: #4B5563;
            line-height: 1.8;
        }
        .package-features li {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .package-features li span {
            color: #059669;
            font-weight: bold;
        }
        .form-order-custom {
            background: #FFFFFF;
            border-radius: 24px;
            padding: 32px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 640px) {
            .form-grid-2 { grid-template-columns: 1fr; }
        }
        .form-group-custom {
            margin-bottom: 16px;
        }
        .form-group-custom label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 6px;
        }
        .input-custom {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #D1D5DB;
            border-radius: 10px;
            font-size: 13px;
            box-sizing: border-box;
            background: #F9FAFB;
        }
        .input-custom:focus {
            border-color: #701C2F;
            background: #FFFFFF;
            outline: none;
        }
        .btn-submit-custom {
            width: 100%;
            background: #701C2F;
            color: #FFFFFF;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.2s;
            box-shadow: 0 4px 14px rgba(112, 28, 47, 0.3);
        }
        .btn-submit-custom:hover {
            background: #541423;
        }
    </style>
</head>
<body class="user-layout">
    <?php include '../sidebar.php'; ?>

    <main class="user-main-content">
       
        <div class="custom-page-wrapper">
            <!-- Hero Banner Layanan Custom -->
            <div class="custom-hero">
                <h1>✨ Layanan Undangan Digital Custom</h1>
                <p>
                    Wujudkan konsep pernikahan atau acara impian Anda tanpa batas. Tim desainer profesional LilinNawa siap merancang undangan interaktif dari nol sesuai tema, ornamen adat nusantara, palet warna, dan referensi moodboard Anda.
                </p>
            </div>

            <?php if (!empty($error_msg)): ?>
            <div style="background:#FEE2E2; color:#991B1B; padding:14px 18px; border-radius:12px; font-weight:700; font-size:13px; margin-bottom:20px;">
                ✕ <?= htmlspecialchars($error_msg) ?>
            </div>
            <?php endif; ?>

            <!-- Pilihan Paket Custom -->
            <div class="packages-grid">
                <!-- Paket 1 -->
                <div class="package-card" id="card-pkg-1" onclick="selectPackage('Paket Undangan Custom Eksklusif', 199000, this)" style="cursor:pointer;">
                    <div style="font-size:11px; font-weight:700; color:#701C2F; text-transform:uppercase; margin-bottom:4px;">Paling Populer</div>
                    <h3 class="package-title">Custom Eksklusif</h3>
                    <div class="package-price">Rp 199.000 <span style="font-size:13px; font-weight:normal; color:#6B7280;"><del>Rp 249.000</del></span></div>
                    <ul class="package-features">
                        <li><span>✓</span> Desain Custom Dari Nol Sesuai Konsep</li>
                        <li><span>✓</span> 3x Sesi Revisi Minor</li>
                        <li><span>✓</span> RSVP WhatsApp &amp; Buku Tamu Online</li>
                        <li><span>✓</span> Navigasi Google Maps &amp; Countdown Timer</li>
                        <li><span>✓</span> Galeri 10 Foto Prewedding</li>
                        <li><span>✓</span> Estimasi Pengerjaan 2 Hari</li>
                    </ul>
                    <button type="button" class="btn-select-pkg" style="width:100%; padding:10px; background:#FAF3DE; color:#701C2F; border:1.5px solid #D4AF37; border-radius:10px; font-weight:700; cursor:pointer;">
                        Pilih Paket Eksklusif
                    </button>
                </div>

                <!-- Paket 2 -->
                <div class="package-card recommended" id="card-pkg-2" onclick="selectPackage('Paket Undangan Custom Luxury VIP', 299000, this)" style="cursor:pointer;">
                    <span class="badge-recommended">VIP BESPOKE</span>
                    <div style="font-size:11px; font-weight:700; color:#D4AF37; text-transform:uppercase; margin-bottom:4px;">Full Eksklusif</div>
                    <h3 class="package-title">Custom Luxury VIP</h3>
                    <div class="package-price">Rp 299.000 <span style="font-size:13px; font-weight:normal; color:#6B7280;"><del>Rp 399.000</del></span></div>
                    <ul class="package-features">
                        <li><span>✓</span> Bebas Konsep Sepuasnya (Full Bespoke)</li>
                        <li><span>✓</span> Unlimited Revisi Minor</li>
                        <li><span>✓</span> Animasi Wax Seal Monogram Khusus</li>
                        <li><span>✓</span> Amplop Digital QRIS Dinamis Multi-Rekening</li>
                        <li><span>✓</span> Live Streaming Embed &amp; Background Video</li>
                        <li><span>✓</span> Prioritas Pengerjaan Kilat 24 Jam</li>
                    </ul>
                    <button type="button" class="btn-select-pkg" style="width:100%; padding:10px; background:#701C2F; color:#FFFFFF; border:none; border-radius:10px; font-weight:700; cursor:pointer;">
                        Pilih Paket Luxury VIP
                    </button>
                </div>
            </div>

            <!-- Formulir Pemesanan & Brief Konsultasi Custom -->
            <div class="form-order-custom">
                <div style="border-bottom: 1px solid #E5E7EB; padding-bottom: 12px; margin-bottom: 20px;">
                    <h3 style="font-family:'Playfair Display',serif; font-size:22px; color:#701C2F; margin:0;">
                        📝 Formulir Pemesanan &amp; Brief Undangan Custom
                    </h3>
                    <p style="font-size:13px; color:#6B7280; margin-top:4px;">
                        Lengkapi informasi di bawah ini. Tim desainer LilinNawa akan segera menghubungi WhatsApp Anda untuk koordinasi konsep visual.
                    </p>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="order_custom">
                    <input type="hidden" name="package_name" id="selected_package_name" value="Paket Undangan Custom Eksklusif">
                    <input type="hidden" name="package_price" id="selected_package_price" value="199000">

                    <div style="background:#FAF3DE; border:1px solid #E2D3AF; border-radius:12px; padding:14px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <span style="font-size:11px; color:#6B7280; display:block;">Paket Terpilih:</span>
                            <strong style="color:#701C2F; font-size:15px;" id="display_pkg_name">Paket Undangan Custom Eksklusif</strong>
                        </div>
                        <strong style="color:#059669; font-size:16px;" id="display_pkg_price">Rp 199.000</strong>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group-custom">
                            <label>Nama Lengkap Pemesan *</label>
                            <input type="text" name="customer_name" required value="<?= htmlspecialchars($user_name) ?>" class="input-custom">
                        </div>

                        <div class="form-group-custom">
                            <label>Nomor WhatsApp Aktif *</label>
                            <input type="text" name="customer_phone" required value="<?= htmlspecialchars($user_phone) ?>" class="input-custom">
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group-custom">
                            <label>Alamat Email</label>
                            <input type="email" name="customer_email" required value="<?= htmlspecialchars($user_email) ?>" class="input-custom">
                        </div>

                        <div class="form-group-custom">
                            <label>Pilihan Tema / Konsep Undangan *</label>
                            <select name="theme_style" required class="input-custom" style="cursor:pointer; font-weight:600;">
                                <option value="Modern Minimalist & Clean">Modern Minimalist &amp; Clean</option>
                                <option value="Tradisional Adat Jawa / Sunda / Minang">Tradisional Nusantara (Adat Jawa / Sunda / Minang)</option>
                                <option value="Luxury Royal Gold & Emerald">Luxury Royal Gold &amp; Emerald</option>
                                <option value="Floral Botanical Watercolor">Floral Botanical Watercolor</option>
                                <option value="Rustic Vintage Nature">Rustic Vintage Nature</option>
                                <option value="Modern Monogram Wax Seal">Modern Monogram Wax Seal</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label>Fitur Khusus yang Diinginkan</label>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:10px; margin-top:8px;">
                            <label style="font-size:12px; color:#374151; display:flex; align-items:center; gap:6px; cursor:pointer;">
                                <input type="checkbox" name="features[]" value="RSVP WhatsApp" checked> RSVP WhatsApp Langsung
                            </label>
                            <label style="font-size:12px; color:#374151; display:flex; align-items:center; gap:6px; cursor:pointer;">
                                <input type="checkbox" name="features[]" value="Google Maps & Petunjuk Arah" checked> Google Maps &amp; Waze
                            </label>
                            <label style="font-size:12px; color:#374151; display:flex; align-items:center; gap:6px; cursor:pointer;">
                                <input type="checkbox" name="features[]" value="Musik Latar Autoplay" checked> Musik Latar Autoplay
                            </label>
                            <label style="font-size:12px; color:#374151; display:flex; align-items:center; gap:6px; cursor:pointer;">
                                <input type="checkbox" name="features[]" value="Countdown Timer" checked> Countdown Timer Acara
                            </label>
                            <label style="font-size:12px; color:#374151; display:flex; align-items:center; gap:6px; cursor:pointer;">
                                <input type="checkbox" name="features[]" value="Amplop Digital QRIS" checked> Amplop Digital / QRIS
                            </label>
                            <label style="font-size:12px; color:#374151; display:flex; align-items:center; gap:6px; cursor:pointer;">
                                <input type="checkbox" name="features[]" value="Love Story & Rundown"> Cerita Cinta &amp; Rundown
                            </label>
                        </div>
                    </div>

                    <div class="form-group-custom">
                        <label>Link Moodboard / Referensi Desain (Pinterest / Google Drive / Instagram)</label>
                        <input type="url" name="moodboard_url" placeholder="https://pinterest.com/... atau link referensi gambar" class="input-custom">
                    </div>

                    <div class="form-group-custom">
                        <label>Catatan Khusus atau Request Desain Lainnya</label>
                        <textarea name="custom_notes" rows="3" placeholder="Contoh: Kami menginginkan nuansa sage green dengan font serif klasik, ada ornamen batik megamendung halus..." class="input-custom"></textarea>
                    </div>

                    <button type="submit" class="btn-submit-custom">
                        ✨ Buat Pesanan Undangan Custom Sekarang
                    </button>
                    <p style="text-align:center; font-size:11px; color:#6B7280; margin-top:8px;">
                        Setelah submit, Anda akan otomatis diarahkan ke menu Tracking Order dan room chat desainer LilinNawa.
                    </p>
                </form>
            </div>
        </div>
    </main>

    <script>
    function selectPackage(name, price, element) {
        document.getElementById('selected_package_name').value = name;
        document.getElementById('selected_package_price').value = price;
        document.getElementById('display_pkg_name').innerText = name;
        document.getElementById('display_pkg_price').innerText = 'Rp ' + Number(price).toLocaleString('id-ID');

        document.querySelectorAll('.package-card').forEach(function(c) {
            c.style.borderColor = '#E5E7EB';
        });
        element.style.borderColor = '#701C2F';
    }
    </script>
</body>
</html>
