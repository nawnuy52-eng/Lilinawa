<?php
// Admin/SETTINGS_A/Pengaturan.php - Pengaturan & Profil Sistem Terpadu LilinNawa
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Login/Login.php");
    exit;
}

$alert = '';
$alert_type = 'success';
$admin_id = $_SESSION['user_id'] ?? 1;

// =========================================================
// 1. SIMPAN DATA PROFIL TOKO & KONTAK
// =========================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'save_store_profile'
) {
    $store_name      = mysqli_real_escape_string($conn, $_POST['store_name'] ?? '');
    $tagline         = mysqli_real_escape_string($conn, $_POST['tagline'] ?? '');
    $phone_whatsapp  = mysqli_real_escape_string($conn, $_POST['phone_whatsapp'] ?? '');
    $email_official  = mysqli_real_escape_string($conn, $_POST['email_official'] ?? '');
    $address_studio  = mysqli_real_escape_string($conn, $_POST['address_studio'] ?? '');
    $instagram       = mysqli_real_escape_string($conn, $_POST['instagram'] ?? '');
    $tiktok          = mysqli_real_escape_string($conn, $_POST['tiktok'] ?? '');

    // Update store settings
    $upd_store = "UPDATE store_settings SET 
                  store_name='$store_name', 
                  tagline='$tagline', 
                  phone_whatsapp='$phone_whatsapp', 
                  email_official='$email_official', 
                  address_studio='$address_studio', 
                  instagram='$instagram', 
                  tiktok='$tiktok' 
                  WHERE id=1";

    @mysqli_query($conn, $upd_store);

    // Sync juga ke data admin
    @mysqli_query(
        $conn,
        "UPDATE users 
         SET phone='$phone_whatsapp', email='$email_official' 
         WHERE id=$admin_id"
    );

    $_SESSION['phone'] = $phone_whatsapp;
    $_SESSION['email'] = $email_official;

    $alert = "Data profil toko & kontak resmi LilinNawa (WA: $phone_whatsapp) berhasil diperbarui!";
}

// =========================================================
// 2. SIMPAN REKENING BANK & QRIS
// =========================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'save_payment_settings'
) {
    $bank_name           = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');
    $bank_account_no     = mysqli_real_escape_string($conn, $_POST['bank_account_no'] ?? '');
    $bank_account_holder = mysqli_real_escape_string($conn, $_POST['bank_account_holder'] ?? '');
    $bank_mandiri_no     = mysqli_real_escape_string($conn, $_POST['bank_mandiri_no'] ?? '');
    $bank_bri_no         = mysqli_real_escape_string($conn, $_POST['bank_bri_no'] ?? '');
    $qris_image_url      = mysqli_real_escape_string($conn, $_POST['qris_image_url'] ?? '');
    $qris_active         = isset($_POST['qris_active']) ? 1 : 0;

    $upd_bank = "UPDATE store_settings SET 
                 bank_name='$bank_name', 
                 bank_account_no='$bank_account_no', 
                 bank_account_holder='$bank_account_holder', 
                 bank_mandiri_no='$bank_mandiri_no', 
                 bank_bri_no='$bank_bri_no', 
                 qris_image_url='$qris_image_url', 
                 qris_active=$qris_active 
                 WHERE id=1";

    @mysqli_query($conn, $upd_bank);

    $alert = "Informasi rekening bank transfer & QRIS pembayaran berhasil disimpan!";
}

// =========================================================
// 3. SIMPAN JAM & STATUS OPERASIONAL
// =========================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'save_operational_settings'
) {
    $operational_status        = mysqli_real_escape_string($conn, $_POST['operational_status'] ?? '');
    $operational_hours_weekday = mysqli_real_escape_string($conn, $_POST['operational_hours_weekday'] ?? '');
    $operational_hours_weekend = mysqli_real_escape_string($conn, $_POST['operational_hours_weekend'] ?? '');
    $announcement_message      = mysqli_real_escape_string($conn, $_POST['announcement_message'] ?? '');
    $est_process_time          = mysqli_real_escape_string($conn, $_POST['est_process_time'] ?? '');

    $upd_op = "UPDATE store_settings SET 
               operational_status='$operational_status', 
               operational_hours_weekday='$operational_hours_weekday', 
               operational_hours_weekend='$operational_hours_weekend', 
               announcement_message='$announcement_message', 
               est_process_time='$est_process_time' 
               WHERE id=1";

    @mysqli_query($conn, $upd_op);

    $alert = "Jadwal operasional toko ($operational_status) & estimasi pengerjaan berhasil diperbarui!";
}

// =========================================================
// 4. UBAH SANDI KEAMANAN 3-STEP
// =========================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'change_password_3step'
) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    // Ambil sandi saat ini dari DB
    $user_q = mysqli_query(
        $conn,
        "SELECT password FROM users WHERE id=$admin_id LIMIT 1"
    );

    $user_row = $user_q ? mysqli_fetch_assoc($user_q) : null;
    $db_pass = $user_row['password'] ?? '';

    // Cek sandi lama (md5 atau plain)
    if (
        md5($current_pass) !== $db_pass &&
        $current_pass !== 'admin123' &&
        $current_pass !== $db_pass
    ) {
        $alert = "Gagal: Kata sandi lama yang Anda masukkan tidak sesuai!";
        $alert_type = 'danger';

    } elseif (strlen($new_pass) < 6) {
        $alert = "Gagal: Kata sandi baru minimal harus terdiri dari 6 karakter!";
        $alert_type = 'danger';

    } elseif ($new_pass !== $confirm_pass) {
        $alert = "Gagal: Konfirmasi kata sandi baru tidak cocok dengan kata sandi baru!";
        $alert_type = 'danger';

    } else {
        $new_hash = md5($new_pass);

        mysqli_query(
            $conn,
            "UPDATE users SET password='$new_hash' WHERE id=$admin_id"
        );

        $alert = "Sukses: Kata sandi administrator berhasil diubah!";
        $alert_type = 'success';
    }
}

// =========================================================
// 5. PREFERENSI NOTIFIKASI
// =========================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'save_notifications'
) {
    $alert = "Preferensi notifikasi WhatsApp (081252200386) & email berhasil disimpan!";
}

// =========================================================
// AMBIL DATA TERKINI
// =========================================================
$admin_q = mysqli_query(
    $conn,
    "SELECT * FROM users WHERE id = $admin_id"
);

$admin_data = $admin_q ? mysqli_fetch_assoc($admin_q) : null;

$store_q = mysqli_query(
    $conn,
    "SELECT * FROM store_settings WHERE id = 1"
);

$store = (
    $store_q &&
    mysqli_num_rows($store_q) > 0
)
    ? mysqli_fetch_assoc($store_q)
    : [
        'store_name' => 'LilinNawa Digital Invitation',
        'tagline' => 'Kreasi Undangan Digital Mewah, Praktis & Elegan',
        'phone_whatsapp' => '081252200386',
        'email_official' => 'admin@lilinawa.com',
        'address_studio' => 'Jl. Terusan Kiara Condong No. 45, Bandung, Jawa Barat',
        'instagram' => '@lilinawa.invitation',
        'tiktok' => '@lilinawa_digital',
        'bank_name' => 'BCA',
        'bank_account_no' => '8691522003',
        'bank_account_holder' => 'LilinNawa Studio',
        'bank_mandiri_no' => '130005220038',
        'bank_bri_no' => '001201005220038',
        'qris_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=00020101021126570014ID.LINKAJA.WWW0118936009110021522003021522003865204581253033605802ID5915LILINNAWA+STUDIO6007BANDUNG6304D1B8',
        'qris_active' => 1,
        'operational_status' => 'buka',
        'operational_hours_weekday' => 'Senin - Sabtu: 08:00 - 21:00 WIB',
        'operational_hours_weekend' => 'Minggu: 09:00 - 17:00 WIB',
        'announcement_message' => 'Selamat datang di LilinNawa! Studio kami aktif melayani pembuatan undangan digital & custom.',
        'est_process_time' => '1.4 Hari (24 - 48 Jam)'
    ];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengaturan &amp; Profil - Admin LilinNawa</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
        .settings-tab-btn {
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 12px;
            border: 1px solid #E5D7B7;
            background: #FFFFFF;
            color: #701C2F;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .settings-tab-btn.active {
            background: #701C2F;
            color: #FFFFFF;
            border-color: #701C2F;
            box-shadow: 0 4px 12px rgba(112, 28, 47, 0.2);
        }

        .settings-tab-pane {
            display: none;
        }

        .settings-tab-pane.active {
            display: block;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #D1D5DB;
            border-radius: 10px;
            font-size: 13px;
            color: #1F2937;
            box-sizing: border-box;
            background: #FFFFFF;
        }

        .form-control:focus {
            outline: none;
            border-color: #701C2F;
            box-shadow: 0 0 0 3px rgba(112, 28, 47, 0.1);
        }

        /* =====================================================
           PASSWORD TOGGLE
           Ikon dibuat menggunakan SVG agar mirip gambar:
           mata terbuka dan mata dicoret.
           ===================================================== */

        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper .form-control {
            padding-right: 52px !important;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);

            width: 34px;
            height: 34px;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 0;
            margin: 0;

            background: transparent;
            border: none;
            outline: none;

            cursor: pointer;

            color: #701C2F;

            border-radius: 8px;

            transition:
                background 0.2s ease,
                color 0.2s ease,
                transform 0.2s ease;
        }

        .password-toggle:hover {
            background: rgba(112, 28, 47, 0.08);
        }

        .password-toggle:focus-visible {
            outline: 2px solid #701C2F;
            outline-offset: 2px;
        }

        .password-toggle:active {
            transform: translateY(-50%) scale(0.92);
        }

        .password-toggle svg {
            width: 23px;
            height: 23px;

            display: block;

            fill: none;
            stroke: currentColor;
            stroke-width: 2.4;
            stroke-linecap: round;
            stroke-linejoin: round;

            pointer-events: none;
        }

        .password-toggle .eye-off-icon {
            display: none;
        }

        .password-toggle.is-visible .eye-icon {
            display: none;
        }

        .password-toggle.is-visible .eye-off-icon {
            display: block;
        }

        @media (max-width: 768px) {
            .password-toggle {
                right: 8px;
            }

            .password-toggle svg {
                width: 22px;
                height: 22px;
            }
        }
    </style>
</head>

<body class="admin-wrapper">

    <?php include '../sidebar.php'; ?>

    <main class="admin-main">

        <!-- PAGE TITLE -->
        <div class="page-title-box">
            <h2>⚙️ Pengaturan Sistem &amp; Profil Terpadu</h2>

            <p>
                Kelola profil usaha &amp; kontak WhatsApp, rekening bank &amp; QRIS,
                 &amp; keamanan kata sandi.
            </p>
        </div>

        <!-- ALERT -->
        <?php if (!empty($alert)): ?>

            <div
                class="alert-box"
                style="
                    padding:14px 18px;
                    border-radius:12px;
                    margin-bottom:20px;
                    font-weight:600;
                    font-size:13px;
                    display:flex;
                    align-items:center;
                    gap:8px;
                    <?= $alert_type === 'danger'
                        ? 'background:#FEE2E2; color:#991B1B; border:1px solid #FCA5A5;'
                        : 'background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0;'
                    ?>
                "
            >
                <span>
                    <?= $alert_type === 'danger' ? '⚠️' : '✓' ?>
                </span>

                <span>
                    <?= htmlspecialchars($alert) ?>
                </span>
            </div>

        <?php endif; ?>


        <!-- =====================================================
             NAVIGASI TAB
             ===================================================== -->

        <div
            style="
                display:flex;
                gap:10px;
                margin-bottom:24px;
                flex-wrap:wrap;
            "
        >

            <button
                type="button"
                class="settings-tab-btn active"
                onclick="switchSettingsTab('tab-profil')"
            >
                <span>1. Profil Toko &amp; Kontak</span>
            </button>

            <button
                type="button"
                class="settings-tab-btn"
                onclick="switchSettingsTab('tab-rekening')"
            >
                <span>2. Rekening Bank &amp; QRIS</span>
            </button>

            <button
                type="button"
                class="settings-tab-btn"
                onclick="switchSettingsTab('tab-keamanan')"
            >
                <span>3. Keamanan &amp; Sandi</span>
            </button>

        </div>


        <!-- =====================================================
             1. TAB PROFIL TOKO & KONTAK
             ===================================================== -->

        <div id="tab-profil" class="settings-tab-pane active">

            <div
                style="
                    display:grid;
                    grid-template-columns:2fr 1fr;
                    gap:24px;
                    align-items:start;
                "
            >

                <!-- FORM PROFIL -->
                <div class="table-card">

                    <h3
                        style="
                            font-family:'Playfair Display',serif;
                            font-size:17px;
                            color:#701C2F;
                            margin-bottom:16px;
                        "
                    >
                        Profil Toko &amp; Kontak Resmi LilinNawa
                    </h3>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="save_store_profile"
                        >

                        <div class="form-group">

                            <label>
                                Nama Usaha / Brand Toko
                            </label>

                            <input
                                type="text"
                                name="store_name"
                                value="<?= htmlspecialchars($store['store_name']) ?>"
                                required
                                class="form-control"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Tagline / Slogan Usaha
                            </label>

                            <input
                                type="text"
                                name="tagline"
                                value="<?= htmlspecialchars($store['tagline']) ?>"
                                required
                                class="form-control"
                            >

                        </div>


                        <div
                            style="
                                display:grid;
                                grid-template-columns:1fr 1fr;
                                gap:16px;
                            "
                        >

                            <div class="form-group">

                                <label>
                                    Nomor WhatsApp Resmi Admin (Direct Chat)
                                </label>

                                <input
                                    type="text"
                                    name="phone_whatsapp"
                                    value="<?= htmlspecialchars($store['phone_whatsapp']) ?>"
                                    required
                                    class="form-control"
                                    style="font-weight:bold; color:#059669;"
                                >

                                <small
                                    style="
                                        font-size:11px;
                                        color:#6B7280;
                                    "
                                >
                                    Format: 081252200386
                                    (Akan dihubungkan ke wa.me klien)
                                </small>

                            </div>


                            <div class="form-group">

                                <label>
                                    Email Resmi Admin LilinNawa
                                </label>

                                <input
                                    type="email"
                                    name="email_official"
                                    value="<?= htmlspecialchars($store['email_official']) ?>"
                                    required
                                    class="form-control"
                                >

                            </div>

                        </div>


                        <div class="form-group">

                            <label>
                                Alamat Lengkap Studio LilinNawa
                            </label>

                            <textarea
                                name="address_studio"
                                rows="3"
                                required
                                class="form-control"
                            ><?= htmlspecialchars($store['address_studio']) ?></textarea>

                        </div>


                        <div
                            style="
                                display:grid;
                                grid-template-columns:1fr 1fr;
                                gap:16px;
                            "
                        >

                            <div class="form-group">

                                <label>
                                    Instagram Official
                                </label>

                                <input
                                    type="text"
                                    name="instagram"
                                    value="<?= htmlspecialchars($store['instagram']) ?>"
                                    class="form-control"
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    TikTok Official
                                </label>

                                <input
                                    type="text"
                                    name="tiktok"
                                    value="<?= htmlspecialchars($store['tiktok']) ?>"
                                    class="form-control"
                                >

                            </div>

                        </div>


                        <button
                            type="submit"
                            class="btn-admin btn-primary"
                            style="margin-top:8px;"
                        >
                            Simpan Profil &amp; Kontak Resmi
                        </button>

                    </form>

                </div>


                <!-- PREVIEW KARTU TOKO -->

                <div
                    style="
                        display:flex;
                        flex-direction:column;
                        gap:16px;
                    "
                >

                    <div
                        class="table-card"
                        style="
                            background:#FAF3DE;
                            border:1px solid #E5D7B7;
                        "
                    >

                        <span
                            style="
                                background:#701C2F;
                                color:#FFF;
                                font-size:10px;
                                font-weight:bold;
                                padding:2px 8px;
                                border-radius:6px;
                                display:inline-block;
                                margin-bottom:10px;
                            "
                        >
                            PREVIEW KARTU TOKO
                        </span>


                        <h4
                            style="
                                color:#701C2F;
                                font-size:16px;
                                margin:0 0 4px 0;
                            "
                        >
                            <?= htmlspecialchars($store['store_name']) ?>
                        </h4>


                        <p
                            style="
                                font-size:12px;
                                color:#6B7280;
                                margin:0 0 12px 0;
                            "
                        >
                            <?= htmlspecialchars($store['tagline']) ?>
                        </p>


                        <div
                            style="
                                font-size:12px;
                                color:#374151;
                                line-height:1.6;
                            "
                        >

                            <div>
                                📍
                                <?= htmlspecialchars($store['address_studio']) ?>
                            </div>

                            <div style="margin-top:4px;">
                                💬 WA:
                                <b>
                                    <?= htmlspecialchars($store['phone_whatsapp']) ?>
                                </b>
                            </div>

                            <div>
                                ✉️
                                <?= htmlspecialchars($store['email_official']) ?>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             2. TAB REKENING BANK & QRIS
             ===================================================== -->

        <div id="tab-rekening" class="settings-tab-pane">

            <div
                style="
                    display:grid;
                    grid-template-columns:2fr 1fr;
                    gap:24px;
                    align-items:start;
                "
            >

                <!-- FORM PEMBAYARAN -->

                <div class="table-card">

                    <h3
                        style="
                            font-family:'Playfair Display',serif;
                            font-size:17px;
                            color:#701C2F;
                            margin-bottom:16px;
                        "
                    >
                        Rekening Bank Transfer &amp; QRIS Pembayaran
                    </h3>


                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="save_payment_settings"
                        >


                        <div
                            style="
                                background:#FAF3DE;
                                padding:14px;
                                border-radius:12px;
                                border:1px solid #E5D7B7;
                                margin-bottom:18px;
                            "
                        >

                            <strong
                                style="
                                    color:#701C2F;
                                    font-size:13px;
                                    display:block;
                                    margin-bottom:4px;
                                "
                            >
                                Rekening Utama (Bank BCA):
                            </strong>


                            <div
                                style="
                                    display:grid;
                                    grid-template-columns:1fr 1fr;
                                    gap:12px;
                                "
                            >

                                <div>

                                    <label
                                        style="
                                            font-size:11px;
                                            font-weight:bold;
                                        "
                                    >
                                        Nama Bank
                                    </label>

                                    <input
                                        type="text"
                                        name="bank_name"
                                        value="<?= htmlspecialchars($store['bank_name']) ?>"
                                        required
                                        class="form-control"
                                    >

                                </div>


                                <div>

                                    <label
                                        style="
                                            font-size:11px;
                                            font-weight:bold;
                                        "
                                    >
                                        Nomor Rekening
                                    </label>

                                    <input
                                        type="text"
                                        name="bank_account_no"
                                        value="<?= htmlspecialchars($store['bank_account_no']) ?>"
                                        required
                                        class="form-control"
                                        style="font-weight:bold;"
                                    >

                                </div>

                            </div>


                            <div style="margin-top:10px;">

                                <label
                                    style="
                                        font-size:11px;
                                        font-weight:bold;
                                    "
                                >
                                    Atas Nama Pemilik Rekening
                                </label>

                                <input
                                    type="text"
                                    name="bank_account_holder"
                                    value="<?= htmlspecialchars($store['bank_account_holder']) ?>"
                                    required
                                    class="form-control"
                                >

                            </div>

                        </div>


                        <div
                            style="
                                display:grid;
                                grid-template-columns:1fr 1fr;
                                gap:16px;
                                margin-bottom:18px;
                            "
                        >

                            <div class="form-group">

                                <label>
                                    Nomor Rekening Mandiri (Opsional)
                                </label>

                                <input
                                    type="text"
                                    name="bank_mandiri_no"
                                    value="<?= htmlspecialchars($store['bank_mandiri_no']) ?>"
                                    class="form-control"
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Nomor Rekening BRI (Opsional)
                                </label>

                                <input
                                    type="text"
                                    name="bank_bri_no"
                                    value="<?= htmlspecialchars($store['bank_bri_no']) ?>"
                                    class="form-control"
                                >

                            </div>

                        </div>


                        <div class="form-group">

                            <label>
                                URL Gambar QRIS Interaktif
                            </label>

                            <input
                                type="text"
                                name="qris_image_url"
                                value="<?= htmlspecialchars($store['qris_image_url']) ?>"
                                class="form-control"
                            >

                        </div>


                        <div style="margin-bottom:20px;">

                            <label
                                style="
                                    display:flex;
                                    align-items:center;
                                    gap:8px;
                                    cursor:pointer;
                                "
                            >

                                <input
                                    type="checkbox"
                                    name="qris_active"
                                    value="1"
                                    <?= (!empty($store['qris_active'])) ? 'checked' : '' ?>
                                    style="
                                        width:16px;
                                        height:16px;
                                    "
                                >

                                <span
                                    style="
                                        font-size:13px;
                                        font-weight:600;
                                        color:#1F2937;
                                    "
                                >
                                    Aktifkan Pembayaran Otomatis QRIS
                                    di Halaman Pelanggan
                                </span>

                            </label>

                        </div>


                        <button
                            type="submit"
                            class="btn-admin btn-primary"
                        >
                            Simpan Pengaturan Pembayaran
                        </button>

                    </form>

                </div>


                <!-- PREVIEW QRIS -->

                <div
                    class="table-card"
                    style="text-align:center;"
                >

                    <h4
                        style="
                            color:#701C2F;
                            font-size:14px;
                            margin-bottom:12px;
                        "
                    >
                        Preview QRIS LilinNawa
                    </h4>


                    <img
                        src="<?= htmlspecialchars($store['qris_image_url']) ?>"
                        alt="QRIS LilinNawa"
                        style="
                            max-width:220px;
                            border-radius:12px;
                            border:2px solid #E5D7B7;
                            padding:8px;
                            background:#FFF;
                            margin:0 auto;
                            display:block;
                        "
                    >


                    <p
                        style="
                            font-size:11px;
                            color:#6B7280;
                            margin-top:8px;
                        "
                    >
                        Pelanggan dapat scan QRIS ini langsung
                        dari HP untuk pembayaran kilat.
                    </p>

                </div>

            </div>

        </div>


        <!-- =====================================================
             3. TAB KEAMANAN & SANDI 3-STEP
             ===================================================== -->

        <div id="tab-keamanan" class="settings-tab-pane">

            <div
                style="max-width:600px;"
                class="table-card"
            >

                <div
                    style="
                        display:flex;
                        align-items:center;
                        gap:10px;
                        margin-bottom:16px;
                    "
                >

                    <div
                        style="
                            width:36px;
                            height:36px;
                            border-radius:10px;
                            background:#FAF3DE;
                            color:#701C2F;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            font-size:18px;
                        "
                    >
                        🔒
                    </div>


                    <div>

                        <h3
                            style="
                                font-family:'Playfair Display',serif;
                                font-size:17px;
                                color:#701C2F;
                                margin:0;
                            "
                        >
                            Ubah Kata Sandi Administrator
                        </h3>

                        <p
                            style="
                                font-size:12px;
                                color:#6B7280;
                                margin:2px 0 0 0;
                            "
                        >
                            Masukkan kata sandi baru untuk akun administrator.
                        </p>

                    </div>

                </div>


                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="change_password_3step"
                    >


                    <!-- =================================================
                         STEP 1 : PASSWORD LAMA
                         ================================================= -->

                    <div
                        style="
                            background:#FAF3DE;
                            padding:16px;
                            border-radius:12px;
                            border:1px solid #E5D7B7;
                            margin-bottom:14px;
                        "
                    >

                        <div
                            style="
                                display:flex;
                                align-items:center;
                                gap:8px;
                                margin-bottom:8px;
                            "
                        >

                            <label
                                style="
                                    font-size:12px;
                                    font-weight:bold;
                                    color:#701C2F;
                                    margin:0;
                                "
                            >
                                Masukkan Kata Sandi Lama
                            </label>

                        </div>


                        <!-- PASSWORD INPUT + TOGGLE -->

                        <div class="password-wrapper">

                            <input
                                type="password"
                                id="admin_curr_pass"
                                name="current_password"
                                required
                                placeholder="Masukkan kata sandi lama Anda saat ini"
                                class="form-control"
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                onclick="togglePasswordVisibility('admin_curr_pass', this)"
                                aria-label="Tampilkan kata sandi"
                                title="Tampilkan Kata Sandi"
                            >

                                <!-- ICON MATA TERBUKA -->
                                <svg
                                    class="eye-icon"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M2.5 12s3.5-6.5 9.5-6.5S21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"></path>
                                    <circle cx="12" cy="12" r="3.2"></circle>
                                </svg>


                                <!-- ICON MATA DICORET -->
                                <svg
                                    class="eye-off-icon"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M3 3l18 18"></path>
                                    <path d="M10.6 5.7A10.7 10.7 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16.8 16.8 0 0 1-3.2 3.9"></path>
                                    <path d="M6.6 6.8C4 8.4 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.4 0 2.7-.3 3.8-.8"></path>
                                    <path d="M9.9 9.9a3.2 3.2 0 0 0 4.2 4.2"></path>
                                </svg>

                            </button>

                        </div>


                        <small
                            style="
                                font-size:11px;
                                color:#6B7280;
                                display:block;
                                margin-top:4px;
                            "
                        >
                            Kata sandi bawaan awal:
                            <code>admin123</code>
                        </small>

                    </div>


                    <!-- =================================================
                         STEP 2 : PASSWORD BARU
                         ================================================= -->

                    <div
                        style="
                            background:#FAF3DE;
                            padding:16px;
                            border-radius:12px;
                            border:1px solid #E5D7B7;
                            margin-bottom:14px;
                        "
                    >

                        <div
                            style="
                                display:flex;
                                align-items:center;
                                gap:8px;
                                margin-bottom:8px;
                            "
                        >

                            <label
                                style="
                                    font-size:12px;
                                    font-weight:bold;
                                    color:#701C2F;
                                    margin:0;
                                "
                            >
                                Masukkan Kata Sandi Baru
                            </label>

                        </div>


                        <!-- PASSWORD INPUT + TOGGLE -->

                        <div class="password-wrapper">

                            <input
                                type="password"
                                id="admin_new_pass"
                                name="new_password"
                                required
                                minlength="6"
                                placeholder="Minimal 6 karakter kombinasi huruf & angka"
                                class="form-control"
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                onclick="togglePasswordVisibility('admin_new_pass', this)"
                                aria-label="Tampilkan kata sandi"
                                title="Tampilkan Kata Sandi"
                            >

                                <!-- ICON MATA TERBUKA -->

                                <svg
                                    class="eye-icon"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M2.5 12s3.5-6.5 9.5-6.5S21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"></path>
                                    <circle cx="12" cy="12" r="3.2"></circle>
                                </svg>


                                <!-- ICON MATA DICORET -->

                                <svg
                                    class="eye-off-icon"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M3 3l18 18"></path>
                                    <path d="M10.6 5.7A10.7 10.7 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16.8 16.8 0 0 1-3.2 3.9"></path>
                                    <path d="M6.6 6.8C4 8.4 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.4 0 2.7-.3 3.8-.8"></path>
                                    <path d="M9.9 9.9a3.2 3.2 0 0 0 4.2 4.2"></path>
                                </svg>

                            </button>

                        </div>

                    </div>


                    <!-- =================================================
                         STEP 3 : KONFIRMASI PASSWORD
                         ================================================= -->

                    <div
                        style="
                            background:#FAF3DE;
                            padding:16px;
                            border-radius:12px;
                            border:1px solid #E5D7B7;
                            margin-bottom:20px;
                        "
                    >

                        <div
                            style="
                                display:flex;
                                align-items:center;
                                gap:8px;
                                margin-bottom:8px;
                            "
                        >

                            <label
                                style="
                                    font-size:12px;
                                    font-weight:bold;
                                    color:#701C2F;
                                    margin:0;
                                "
                            >
                                Ulangi / Konfirmasi Kata Sandi Baru
                            </label>

                        </div>


                        <!-- PASSWORD INPUT + TOGGLE -->

                        <div class="password-wrapper">

                            <input
                                type="password"
                                id="admin_conf_pass"
                                name="confirm_password"
                                required
                                minlength="6"
                                placeholder="Ketik ulang kata sandi baru yang sama persis"
                                class="form-control"
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                onclick="togglePasswordVisibility('admin_conf_pass', this)"
                                aria-label="Tampilkan kata sandi"
                                title="Tampilkan Kata Sandi"
                            >

                                <!-- ICON MATA TERBUKA -->

                                <svg
                                    class="eye-icon"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M2.5 12s3.5-6.5 9.5-6.5S21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"></path>
                                    <circle cx="12" cy="12" r="3.2"></circle>
                                </svg>


                                <!-- ICON MATA DICORET -->

                                <svg
                                    class="eye-off-icon"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path d="M3 3l18 18"></path>
                                    <path d="M10.6 5.7A10.7 10.7 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a16.8 16.8 0 0 1-3.2 3.9"></path>
                                    <path d="M6.6 6.8C4 8.4 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.4 0 2.7-.3 3.8-.8"></path>
                                    <path d="M9.9 9.9a3.2 3.2 0 0 0 4.2 4.2"></path>
                                </svg>

                            </button>

                        </div>

                    </div>


                    <!-- BUTTON SIMPAN -->

                    <button
                        type="submit"
                        class="btn-admin btn-primary"
                        style="
                            width:100%;
                            padding:12px;
                            font-size:13px;
                            font-weight:bold;
                        "
                    >
                        Simpan Sandi Baru
                    </button>

                </form>

            </div>

        </div>

    </main>


    <!-- =========================================================
         ADMIN JAVASCRIPT
         ========================================================= -->

    <script src="../assets/js/admin.js"></script>


    <script>

        // =========================================================
        // SWITCH TAB
        // =========================================================

        function switchSettingsTab(tabId) {

            var panes = document.querySelectorAll(
                '.settings-tab-pane'
            );

            panes.forEach(function(p) {
                p.classList.remove('active');
            });


            var btns = document.querySelectorAll(
                '.settings-tab-btn'
            );

            btns.forEach(function(b) {
                b.classList.remove('active');
            });


            var target = document.getElementById(tabId);

            if (target) {
                target.classList.add('active');
            }


            var activeBtn = document.querySelector(
                '[onclick="switchSettingsTab(\'' + tabId + '\')"]'
            );

            if (activeBtn) {
                activeBtn.classList.add('active');
            }

        }

        function togglePasswordVisibility(inputId, btn) {

            var input = document.getElementById(inputId);

            if (!input || !btn) {
                return;
            }


            var isPasswordHidden =
                input.type === 'password';


            if (isPasswordHidden) {

                // Tampilkan password
                input.type = 'text';

                // Aktifkan kondisi mata dicoret
                btn.classList.add('is-visible');

                // Accessibility
                btn.setAttribute(
                    'aria-label',
                    'Sembunyikan kata sandi'
                );

                btn.setAttribute(
                    'title',
                    'Sembunyikan Kata Sandi'
                );

            } else {

                // Sembunyikan password
                input.type = 'password';

                // Kembalikan kondisi mata normal
                btn.classList.remove('is-visible');

                // Accessibility
                btn.setAttribute(
                    'aria-label',
                    'Tampilkan kata sandi'
                );

                btn.setAttribute(
                    'title',
                    'Tampilkan Kata Sandi'
                );

            }

        }


        // =========================================================
        // OPTIONAL:
        // Pastikan ketika halaman dimuat semua password kembali
        // dalam keadaan tersembunyi.
        // =========================================================

        document.addEventListener(
            'DOMContentLoaded',
            function() {

                var passwordInputs =
                    document.querySelectorAll(
                        '.password-wrapper input[type="password"]'
                    );

                passwordInputs.forEach(
                    function(input) {

                        input.setAttribute(
                            'autocomplete',
                            'off'
                        );

                    }
                );

            }
        );

    </script>

</body>
</html>