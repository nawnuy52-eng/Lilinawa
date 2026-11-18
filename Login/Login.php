<?php
// Login/Login.php - Autentikasi Pengguna LilinNawa
session_start();
require_once '../Config/Database.php';

$error = '';
$success = '';

// Notifikasi setelah berhasil registrasi
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success = 'Pendaftaran akun berhasil! Silakan masukkan kata sandi untuk masuk ke akun Anda.';
}

$email_input_val = isset($_GET['email']) ? htmlspecialchars(trim($_GET['email'])) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $password_input = $_POST['password'] ?? '';
    $email_input_val = $email;

    if (empty($email) && empty($password_input)) {
        $error = 'Email/nomor WhatsApp dan kata sandi wajib diisi!';
    } elseif (empty($email)) {
        $error = 'Silakan masukkan email atau nomor WhatsApp Anda!';
    } elseif (empty($password_input)) {
        $error = 'Silakan masukkan kata sandi akun Anda!';
    } else {
        $md5_pass = md5($password_input);

        $q = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' OR phone='$email' OR name='$email'");
        if ($q && mysqli_num_rows($q) > 0) {
            $user = mysqli_fetch_assoc($q);
            $authenticated = false;

            if ($user['password'] === $md5_pass) {
                $authenticated = true;
            } elseif (password_verify($password_input, $user['password'])) {
                $authenticated = true;
            } elseif ($user['password'] === $password_input) {
                $authenticated = true;
            }

            if ($authenticated) {
                // Periksa apakah user adalah admin
                $is_admin = ($user['role'] === 'admin' || stripos($user['email'], 'admin') !== false || stripos($user['name'], 'admin') !== false);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['phone']   = $user['phone'];
                $_SESSION['role']    = $is_admin ? 'admin' : 'customer';

                // Jika role admin, arahkan langsung ke Admin Portal
                if ($is_admin) {
                    header("Location: ../Admin/MAIN_A/Home.php");
                } else {
                    header("Location: ../User/Main_U/Home.php");
                }
                exit;
            } else {
                $error = 'Kata sandi yang Anda masukkan salah. Silakan periksa kembali!';
            }
        } else {
            $error = 'Email atau nomor WhatsApp belum terdaftar. Silakan daftar akun baru terlebih dahulu!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk ke Akun - LilinNawa Undangan Digital</title>
    <link rel="stylesheet" href="Login.css?v=<?= time() ?>">
    <style>
        /* Self-Contained Bulletproof Styles for Login.php */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
       body.login-body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    background: #440d1a;
    padding: 16px 12px;
    color: #1F2937;
    position: relative;
    overflow-x: hidden;
}

/* Background Video */
.login-bg-video {
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: -2;
}

/* Overlay agar tampilan tetap elegan */
body.login-body::before {
    content: "";
    position: fixed;
    inset: 0;
    background: rgba(50, 5, 18, 0.45);
    z-index: -1;
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

.login-center-wrapper {
    width: 100%;
    max-width: 440px;
    margin: auto;
    padding: 12px 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
    z-index: 10;
}

.login-box {
    background: #ffffff;
    width: 100%;
    border-radius: 24px;
    padding: 34px 28px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
    border: 1px solid rgba(250, 243, 222, 0.4);
    position: relative;
    overflow: hidden;
}

.login-box::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, #D4AF37, #721B2F, #D4AF37);
}

.login-brand { 
    text-align: center; 
    margin-bottom: 22px; 
}

.login-logo {
    width: 155px;
    height: 95px;
    object-fit: contain;
    display: block;
    margin: 0 auto 12px;
}

.login-brand {
    text-align: center;
    margin-bottom: 22px;
}

.login-brand h1 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 32px;
    color: #721B2F;
    font-weight: 800;
    letter-spacing: -0.5px;
}

.login-brand p {
    font-size: 11px;
    color: #8A4B58;
    margin-top: 3px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    font-weight: 700;
}

.login-brand .badge-login {
    display: inline-block;
    background: #FAF3DE;
    color: #721B2F;
    border: 1px solid #E2D4B0;
    font-size: 11px;
    font-weight: 800;
    padding: 3px 12px;
    border-radius: 999px;
    margin-top: 8px;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 12px;
    margin-bottom: 16px;
    border: 1px solid #fecaca;
    line-height: 1.4;
}

.alert-success {
    background: #ecfdf5;
    color: #065f46;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 12px;
    margin-bottom: 16px;
    border: 1px solid #a7f3d0;
    line-height: 1.4;
}

.form-login { 
    display: flex; 
    flex-direction: column; 
    gap: 14px; 
}

.input-field { 
    display: flex; 
    flex-direction: column; 
    gap: 5px; 
}

.input-field label { 
    font-size: 11.5px; 
    font-weight: 700; 
    color: #374151; 
}

.input-field input {
    padding: 11px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 12px;
    font-size: 13px;
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

.btn-toggle-eye {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-toggle-eye svg {
    width: 23px;
    height: 23px;
    stroke: #333;
    stroke-width: 2;
    fill: none;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
}

.password-wrapper input {
    padding-right: 44px;
}

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

.btn-submit:hover {
    background: #571423;
    transform: translateY(-1px);
}

.form-options-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11.5px;
    color: #4B5563;
    margin-top: -2px;
    margin-bottom: 4px;
}

.form-options-row label {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}

.form-options-row a {
    color: #721B2F;
    text-decoration: none;
    font-weight: 700;
}

.form-options-row a:hover {
    text-decoration: underline;
}

.security-badge-box {
    margin-top: 18px;
    padding: 12px 14px;
    border-radius: 14px;
    background: #FAF3DE;
    border: 1px solid #E2D4B0;
    font-size: 11px;
    color: #721B2F;
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 1.4;
}

.login-footer {
    text-align: center;
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px solid #f3f4f6;
    font-size: 12px;
    color: #6b7280;
}

.login-footer a { 
    color: #721B2F; 
    font-weight: 700; 
    text-decoration: none; 
}

.login-footer a:hover { 
    text-decoration: underline; 
}

.copyright-text {
    color: rgba(254, 243, 222, 0.65);
    font-size: 11px;
    text-align: center;
    margin-top: 12px;
}

/* ABOUT MODAL */
.modal-about-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 5, 8, 0.75);
    backdrop-filter: blur(6px);
    z-index: 99999;

    justify-content: center;
    align-items: center;
    padding: 25px;
}

.modal-about-backdrop.active {
    display: flex;
}

.modal-about-card {
    width: 100%;
    max-width: 1100px;
    height: 88vh;
    max-height: 850px;

    background: #ffffff;
    border-radius: 18px;
    overflow: hidden;

    display: flex;
    flex-direction: column;

    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);

    animation: aboutModalOpen 0.3s ease;
}

.modal-about-header {
    flex-shrink: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;

    padding: 15px 24px;
    background: #FAF3DE;
    border-bottom: 1px solid #E2D4B0;
}

.modal-about-header h3 {
    color: #721B2F;
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 18px;
}

.btn-close-about {
    width: 36px;
    height: 36px;

    border: none;
    border-radius: 50%;

    background: transparent;
    color: #721B2F;

    font-size: 28px;
    cursor: pointer;

    display: flex;
    align-items: center;
    justify-content: center;

    transition: 0.2s;
}

.btn-close-about:hover {
    background: #721B2F;
    color: #ffffff;
}

.about-iframe {
    width: 100%;
    flex: 1;
    border: none;
    background: #ffffff;
}

@keyframes aboutModalOpen {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.97);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Responsive */
@media (max-width: 600px) {
    .modal-about-backdrop {
        padding: 10px;
    }

    .modal-about-card {
        height: 92vh;
        border-radius: 12px;
    }
}
    </style>
</head>
<body class="login-body">
     <video autoplay muted loop playsinline class="login-bg-video">
        <source src="../PV/BG%20Video%20LN.mp4" type="video/mp4">
    </video>    

<!-- Top Navigation Bar: Luas & Elegan di Pojok Layar Luar Box Login -->
    <header class="login-top-navbar">
    <button type="button"
        class="btn-top-nav btn-top-about"id="openAboutBtn">About</button>
</header>

    <!-- Center Box Container -->
    <div class="login-center-wrapper">
        <div class="login-box">
            <div class="login-brand">

                <img src="../PV/Logo%20LN.png"alt="LilinNawa Logo"class="login-logo">

                <h1>LilinNawa</h1>
                <p>Digital Wedding &amp; Event Invitations</p>
                <span class="badge-login">Masuk ke Akun</span>

            </div>

            <?php if(!empty($success)): ?>
                <div class="alert-success">
                    <strong>✓ Berhasil!</strong><br><?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($error)): ?>
                <div class="alert-error">
                    <strong>⚠️ Terjadi Kesalahan:</strong><br><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="form-login" id="loginForm">
                <div class="input-field">
                    <label>Email atau No. WhatsApp</label>
                    <input type="text" name="email" id="emailInput" value="<?= htmlspecialchars($email_input_val) ?>" required placeholder="Masukkan email atau no. WhatsApp terdaftar">
                </div>

                <div class="input-field">
                    <label>Kata Sandi</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="loginPassword" required placeholder="Masukkan kata sandi akun Anda">
                        <button type="button"
                            class="btn-toggle-eye"id="togglePasswordBtn"aria-label="Show password">

                           <svg id="eyeOpen" viewBox="0 0 24 24">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                                <circle cx="12" cy="12" r="3"/>
                           </svg>

                           <svg id="eyeClosed" viewBox="0 0 24 24" style="display:none">
                                <path d="M3 3l18 18"/>
                                <path d="M10.6 5.1A11 11 0 0 1 12 5c6.4 0 10 7 10 7a15 15 0 0 1-4 4.5"/>
                                <path d="M6.6 6.6C3.7 8.5 2 12 2 12s3.6 7 10 7c1.3 0 2.5-.3 3.5-.8"/>
                                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                           </svg>

                        </button>
                    </div>
                </div>

                <div class="form-options-row">
                    <label>
                        <input type="checkbox" name="remember" value="1" checked style="accent-color: #721B2F;">
                        <span>Ingat saya di perangkat ini</span>
                    </label>
                </div>

                <button type="submit" class="btn-submit">Masuk ke Akun</button>
            </form>

            

            <div class="login-footer">
                <p>Belum punya akun pelanggan? <a href="Register.php"><strong>Daftar Akun Baru</strong></a></p>
            </div>
        </div>
    </div>

    <!-- ABOUT MODAL -->
<div id="modalAboutBox" class="modal-about-backdrop">

    <div class="modal-about-card">

        <!-- Modal Header -->
        <div class="modal-about-header">

            <h3>About LilinNawa</h3>

            <button type="button"
                    class="btn-close-about"
                    id="closeAboutBtn"
                    aria-label="Close About">
                &times;
            </button>

        </div>

        <!-- About Content -->
        <iframe
            src="about.php"
            class="about-iframe"
            title="About LilinNawa">
        </iframe>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const passwordInput = document.getElementById('loginPassword');
    const toggleButton = document.getElementById('togglePasswordBtn');
    const eyeOpen = document.getElementById('eyeOpen');
    const eyeClosed = document.getElementById('eyeClosed');

    if (passwordInput && toggleButton) {

        toggleButton.addEventListener('click', function () {

            const isHidden = passwordInput.type === 'password';

            passwordInput.type = isHidden ? 'text' : 'password';

            eyeOpen.style.display = isHidden ? 'none' : 'block';
            eyeClosed.style.display = isHidden ? 'block' : 'none';

            toggleButton.setAttribute(
                'aria-label',
                isHidden ? 'Hide password' : 'Show password'
            );

        });

    }


    // =================================
    // ABOUT MODAL
    // =================================

    const modal = document.getElementById('modalAboutBox');
    const openBtn = document.getElementById('openAboutBtn');
    const closeBtn = document.getElementById('closeAboutBtn');

    function openAbout() {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeAbout() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (modal && openBtn && closeBtn) {

        openBtn.addEventListener('click', openAbout);

        closeBtn.addEventListener('click', closeAbout);

        // Klik area luar modal
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeAbout();
            }
        });

        // Tekan Escape
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('active')) {
                closeAbout();
            }
        });

    }

});
</script>
</body>
</html>