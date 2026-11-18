<?php
// Login/Register.php - Registrasi Pelanggan Baru LilinNawa
session_start();
require_once '../Config/Database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = mysqli_real_escape_string($conn, trim($_POST['name'] ?? ''));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $phone    = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $address  = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $password_raw = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($phone) || empty($password_raw)) {
        $error = "Semua kolom bertanda bintang (*) wajib diisi secara lengkap!";
    } elseif (strlen($password_raw) < 5) {
        $error = "Kata sandi minimal 5 karakter demi keamanan akun Anda!";
    } else {
        $password = md5($password_raw);

        $check = mysqli_query($conn, "SELECT id FROM users WHERE email='$email' OR phone='$phone'");
        if ($check && mysqli_num_rows($check) > 0) {
            $error = "Email atau nomor WhatsApp sudah terdaftar! Silakan langsung masuk.";
        } else {
            $role = (stripos($email, 'admin') !== false || stripos($name, 'admin') !== false) ? 'admin' : 'customer';
            $sql = "INSERT INTO users (name, email, password, phone, role, address) 
                    VALUES ('$name', '$email', '$password', '$phone', '$role', '$address')";
            if (mysqli_query($conn, $sql)) {
                // Buat room chat otomatis jika pelanggan
                if ($role === 'customer') {
                    $room_id = 'room-' . preg_replace('/[^0-9]/', '', $phone);
                    $welcome_msg = "Halo Kak $name! Selamat datang di LilinNawa. Silakan tanyakan hal seputar katalog undangan atau konsultasi desain.";
                    @mysqli_query($conn, "INSERT INTO inbox (room_id, sender_name, sender_phone, recipient_phone, sender_role, message, is_read) 
                                         VALUES ('$room_id', 'Admin LilinNawa', '081252200386', '$phone', 'admin', '$welcome_msg', 1)");
                }

                // Setelah daftar langsung otomatis diarahkan kembali ke halaman login dengan pesan sukses
                header("Location: Login.php?registered=1&email=" . urlencode($email));
                exit;
            } else {
                $error = "Pendaftaran gagal: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Baru - LilinNawa</title>
    <!-- CSS File Sendiri Khusus Register -->
    <link rel="stylesheet" href="register.css?v=<?= time() ?>">
    <style>
        /* Bulletproof Inlined Styles for Register.php */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        body.register-body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            background: radial-gradient(circle at top right, #8b233a, #701C2F 60%, #440d1a);
            padding: 16px 12px;
            color: #1F2937;
            position: relative;
        }
        .login-top-navbar {
            width: 100%;
            max-width: 1140px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            z-index: 20;
        }
        .btn-top-nav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            backdrop-filter: blur(8px);
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-top-back {
            background: rgba(0, 0, 0, 0.25);
            color: #FAF3DE;
            border-color: rgba(255, 255, 255, 0.2);
        }
        .btn-top-back:hover {
            background: rgba(0, 0, 0, 0.45);
            color: #FFFFFF;
        }
        .btn-top-about {
            background: rgba(0, 0, 0, 0.35);
            color: #FDE68A;
            border-color: rgba(212, 175, 55, 0.6);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        }
        .btn-top-about:hover {
            background: rgba(0, 0, 0, 0.6);
            color: #FFFFFF;
            border-color: #FDE68A;
            transform: translateY(-1px);
        }
        .register-center-wrapper {
            width: 100%;
            max-width: 480px;
            margin: auto;
            padding: 12px 0;
            display: flex;
            flex-direction: column;
            gap: 16px;
            z-index: 10;
        }
        .register-box {
            background: #ffffff;
            width: 100%;
            border-radius: 26px;
            padding: 34px 28px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(250, 243, 222, 0.4);
            position: relative;
            overflow: hidden;
        }
        .register-box::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #D4AF37, #721B2F, #D4AF37);
        }
        .register-brand { text-align: center; margin-bottom: 20px; }
        .register-brand h1 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 32px;
            color: #721B2F;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .register-brand p {
            font-size: 11px;
            color: #8A4B58;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 700;
        }
        .form-register { display: flex; flex-direction: column; gap: 13px; }
        .input-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media(max-width: 480px) { .input-grid { grid-template-columns: 1fr; } }
        .input-field { display: flex; flex-direction: column; gap: 5px; }
        .input-field label { font-size: 11px; font-weight: 700; color: #374151; }
        .input-field input {
            padding: 10px 14px;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            font-size: 12.5px;
            color: #1f2937;
            background: #fafafa;
            transition: all 0.2s;
            outline: none;
            width: 100%;
        }
        .input-field input:focus {
            border-color: #721B2F;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(114, 27, 47, 0.12);
        }
        .password-wrapper { position: relative; display: flex; align-items: center; width: 100%; }
        .password-wrapper input { padding-right: 44px; }
        .btn-toggle-eye {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px;
            font-size: 15px;
            color: #6B7280;
        }
        .btn-submit {
            background: #721B2F;
            color: #ffffff;
            padding: 12px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 4px;
            box-shadow: 0 4px 14px rgba(114, 27, 47, 0.25);
            transition: all 0.2s;
            width: 100%;
        }
        .btn-submit:hover { background: #571423; transform: translateY(-1px); }
        .register-footer {
            text-align: center;
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid #f3f4f6;
            font-size: 12px;
            color: #6b7280;
        }
        .register-footer a { color: #721B2F; font-weight: 700; text-decoration: none; }
        .register-footer a:hover { text-decoration: underline; }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 12px;
            margin-bottom: 14px;
            border: 1px solid #fecaca;
            line-height: 1.4;
        }
        .copyright-text {
            color: rgba(254, 243, 222, 0.65);
            font-size: 11px;
            text-align: center;
            margin-top: 12px;
        }
        /* Modal About */
        .modal-about-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.65);
            backdrop-filter: blur(4px);
            z-index: 100;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-about-backdrop.show { display: flex; }
        .modal-about-card {
            background: #FFFFFF;
            border-radius: 20px;
            max-width: 480px;
            width: 100%;
            padding: 26px;
            border: 1.5px solid #E2D4B0;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            position: relative;
        }
        .modal-about-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #F3F4F6;
        }
        .modal-about-header h3 {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 20px;
            color: #721B2F;
            font-weight: 800;
        }
        .btn-close-about {
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: #9CA3AF;
        }
    </style>
</head>
<body class="register-body">
    <!-- Top Navigation Bar: Luas & Elegan di Pojok Layar -->
    <header class="login-top-navbar">
        <button type="button" class="btn-top-nav btn-top-about" id="top-right-about-link-reg" onclick="openAboutModal()">
            <span>About</span>
        </button>
    </header>

    <!-- Center Box Container -->
    <div class="register-center-wrapper">
        <div class="register-box">
            <div class="register-brand">
                <h1>LilinNawa</h1>
                <p>Pendaftaran Akun Pelanggan</p>
            </div>

            <?php if(!empty($error)): ?>
                <div class="alert-error">
                    <strong>⚠️ Registrasi Gagal:</strong><br><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="form-register">
                <div class="input-field">
                    <label>Nama Lengkap *</label>
                    <input type="text" name="name" required placeholder="Contoh: Dimas Aditya" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                </div>

                <div class="input-grid">
                    <div class="input-field">
                        <label>No. WhatsApp *</label>
                        <input type="text" name="phone" required placeholder="081234567890" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                    </div>
                    <div class="input-field">
                        <label>Email *</label>
                        <input type="email" name="email" required placeholder="nama@email.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                    </div>
                </div>

                <div class="input-field">
                    <label>Kata Sandi *</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="regPassword" required placeholder="Minimal 6 karakter">
                        <button type="button" class="btn-toggle-eye" onclick="toggleEye('regPassword', this)" title="Lihat / Sembunyikan Sandi" aria-label="Toggle password visibility">
                            <span class="eye-icon">👁️</span>
                        </button>
                    </div>
                </div>

                <div class="input-field">
                    <label>Kota / Alamat Singkat</label>
                    <input type="text" name="address" placeholder="Contoh: Bandung, Jawa Barat" value="<?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?>">
                </div>

                <button type="submit" class="btn-submit">Daftar Akun Sekarang</button>
            </form>

            <div class="register-footer">
                <p>Sudah memiliki akun? <a href="Login.php"><strong>Masuk ke Akun</strong></a></p>
            </div>
        </div>
    </div>

    <div class="copyright-text">
        &copy; 2025 LilinNawa Invitations &bull; Sesi Aman &amp; Terenkripsi
    </div>

    <!-- Modal About Popup -->
    <div id="modalAboutBoxReg" class="modal-about-backdrop">
        <div class="modal-about-card">
            <div class="modal-about-header">
                <h3>ℹ️ Tentang LilinNawa</h3>
                <button type="button" class="btn-close-about" onclick="closeAboutModal()">&times;</button>
            </div>
            <div style="font-size: 13px; color: #4B5563; line-height: 1.8;">
                <p style="margin-bottom: 12px;">
                    <strong>LilinNawa</strong> adalah platform pemesanan dan manajemen undangan pernikahan serta acara digital yang memadukan estetika elegan dengan kemudahan teknologi masa kini.
                </p>
                <div style="background:#FAF3DE; border:1px solid #E2D4B0; border-radius:12px; padding:12px 14px; margin-bottom:14px; font-size:12px;">
                    <strong style="color:#721B2F;">Fitur Utama:</strong>
                    <ul style="padding-left:18px; margin-top:4px;">
                        <li>Portal Dashboard Pelanggan &amp; Admin terpadu.</li>
                        <li>Slot personalisasi <strong>SPACE LOGO USAHA</strong>.</li>
                        <li>Integrasi RSVP WhatsApp &amp; Navigasi Google Maps.</li>
                        <li>Pelacakan progres pembuatan undangan 5 tahap.</li>
                    </ul>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:11px; color:#6B7280;">Versi 2.5.0 Luxury Edition</span>
                    <a href="about.php" style="color:#721B2F; font-weight:bold; font-size:12px; text-decoration:none;">Baca Profil Lengkap &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleEye(inputId, btn) {
        var input = document.getElementById(inputId);
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '<span class="eye-icon">🙈</span>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<span class="eye-icon">👁️</span>';
        }
    }

    function openAboutModal() {
        document.getElementById('modalAboutBoxReg').classList.add('show');
    }

    function closeAboutModal() {
        document.getElementById('modalAboutBoxReg').classList.remove('show');
    }

    window.onclick = function(e) {
        var modal = document.getElementById('modalAboutBoxReg');
        if (e.target === modal) {
            closeAboutModal();
        }
    };
    </script>
</body>
</html>