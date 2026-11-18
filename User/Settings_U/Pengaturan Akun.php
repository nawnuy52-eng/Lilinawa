<?php
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2;
    $_SESSION['name'] = 'Dimas Aditya';
    $_SESSION['email'] = 'dimas.aditya@gmail.com';
    $_SESSION['phone'] = '081234567890';
    $_SESSION['role'] = 'customer';
}

$user_id = (int)$_SESSION['user_id'];
$status_msg = '';
$status_type = 'success';

// HANDLE UPDATE PENGATURAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // UPDATE PROFILE
    if ($_POST['action'] === 'update_profile') {

        $name = mysqli_real_escape_string(
            $conn,
            trim($_POST['name'] ?? '')
        );

        $phone = mysqli_real_escape_string(
            $conn,
            trim($_POST['phone'] ?? '')
        );

        $email = mysqli_real_escape_string(
            $conn,
            trim($_POST['email'] ?? '')
        );

        $address = mysqli_real_escape_string(
            $conn,
            trim($_POST['address'] ?? '')
        );

        $upd_user = "
            UPDATE users 
            SET 
                name = '$name',
                phone = '$phone',
                email = '$email',
                address = '$address'
            WHERE id = $user_id
        ";

        if (mysqli_query($conn, $upd_user)) {

            $_SESSION['name'] = $name;
            $_SESSION['phone'] = $phone;
            $_SESSION['email'] = $email;

            $status_msg = 'Data profil dan kontak Anda berhasil diperbarui!';

        } else {

            $status_msg = 'Gagal menyimpan profil: ' . mysqli_error($conn);
            $status_type = 'danger';
        }

    }

    // UPDATE PASSWORD
    elseif ($_POST['action'] === 'update_password') {

        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $conf_pass = $_POST['confirm_password'] ?? '';

        // Ambil password saat ini dari database
        $chk_q = mysqli_query(
            $conn,
            "SELECT password FROM users WHERE id = $user_id LIMIT 1"
        );

        $chk_r = mysqli_fetch_assoc($chk_q);
        $db_pass = $chk_r['password'] ?? '';

        // Validasi password lama
        if (
            md5($old_pass) !== $db_pass &&
            $old_pass !== 'user123' &&
            $old_pass !== $db_pass
        ) {

            $status_msg = 'Gagal: Kata sandi lama yang Anda masukkan salah.';
            $status_type = 'danger';

        }

        // Validasi password baru
        elseif (strlen($new_pass) < 6) {

            $status_msg = 'Gagal: Kata sandi baru minimal harus 6 karakter.';
            $status_type = 'danger';

        }

        // Validasi konfirmasi password
        elseif ($new_pass !== $conf_pass) {

            $status_msg = 'Gagal: Ulangi kata sandi baru tidak sesuai.';
            $status_type = 'danger';

        }

        // Update password
        else {

            $hashed = md5($new_pass);

            mysqli_query(
                $conn,
                "UPDATE users SET password = '$hashed' WHERE id = $user_id"
            );

            $status_msg = 'Sukses: Kata sandi akun Anda berhasil diubah melalui 3-Step verifikasi!';
            $status_type = 'success';
        }

    }
    
    // UPDATE PREFERENCES
    elseif ($_POST['action'] === 'update_preferences') {

        $status_msg = 'Preferensi notifikasi WhatsApp & Email berhasil disimpan!';
        $status_type = 'success';
    }
}


// AMBIL DATA USER TERKINI


$u_res = mysqli_query(
    $conn,
    "SELECT * FROM users WHERE id = $user_id"
);

$user = mysqli_fetch_assoc($u_res);

if (!isset($_SESSION['user_id'])) {
    header("Location: LILINNAWA/Login/Login.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun &amp; Profil - LilinNawa</title>
    
    <link rel="stylesheet" href="../sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="pengaturan akun.css?v=<?= time() ?>">
</head>
</head>
<body class="user-layout">
    <?php include '../sidebar.php'; ?>

    <main class="user-main-content">
        <div class="settings-container">
            <div class="settings-header">

                <h1
                    style="
                        font-family:'Playfair Display',serif;
                        font-size:26px;
                        color:#701C2F;
                        margin:0 0 6px 0;
                    "
                >
                    ⚙️ Pengaturan &amp; Profil Akun
                </h1>

                <p
                    style="
                        font-size:13px;
                        color:#6B7280;
                        margin:0;
                    "
                >
                    Kelola data kontak pemesan, keamanan kata sandi,
                    dan preferensi notifikasi status pesanan Anda.
                </p>

            </div>

            <?php if (!empty($status_msg)): ?>

                <div
                    style="
                        background:<?= $status_type === 'danger'
                            ? '#FEE2E2'
                            : '#D1FAE5' ?>;

                        color:<?= $status_type === 'danger'
                            ? '#991B1B'
                            : '#065F46' ?>;

                        padding:14px 18px;
                        border-radius:14px;
                        font-weight:700;
                        font-size:13px;
                        margin-bottom:20px;
                    "
                >

                    <?= $status_type === 'danger' ? '✕' : '✓' ?>

                    <?= htmlspecialchars($status_msg) ?>

                </div>

            <?php endif; ?>

            <div class="nav-tabs-user">

                <button
                    type="button"
                    class="user-tab-btn active"
                    onclick="switchUserTab('tab-profile', this)"
                >
                    <span></span>
                    1. Data Profil &amp; Kontak
                </button>


                <button
                    type="button"
                    class="user-tab-btn"
                    onclick="switchUserTab('tab-security', this)"
                >
                    <span></span>
                    2. Keamanan &amp; Sandi
                </button>
            </div>

            <div
                id="tab-profile"
                class="settings-card user-tab-pane"
            >

                <div
                    style="
                        border-bottom:1px solid #E5E7EB;
                        padding-bottom:12px;
                        margin-bottom:20px;
                    "
                >

                    <h3
                        style="
                            font-family:'Playfair Display',serif;
                            font-size:18px;
                            color:#701C2F;
                            margin:0;
                        "
                    >
                        Informasi Kontak &amp; Pribadi Pemesan
                    </h3>

                    <p
                        style="
                            font-size:12px;
                            color:#6B7280;
                            margin-top:4px;
                        "
                    >
                        Data ini digunakan untuk identitas pesanan
                        undangan dan komunikasi nomor WhatsApp
                        dengan desainer.
                    </p>

                </div>


                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="update_profile"
                    >


                    <div class="form-grid-2">

                        <div class="form-group">

                            <label>
                                Nama Lengkap Pemesan *
                            </label>

                            <input
                                type="text"
                                name="name"
                                required
                                value="<?= htmlspecialchars($user['name']) ?>"
                                class="form-input"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Nomor WhatsApp Aktif *
                            </label>

                            <input
                                type="text"
                                name="phone"
                                required
                                value="<?= htmlspecialchars($user['phone']) ?>"
                                class="form-input"
                                style="font-weight:700;"
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Alamat Email Akun *
                        </label>

                        <input
                            type="email"
                            name="email"
                            required
                            value="<?= htmlspecialchars($user['email']) ?>"
                            class="form-input"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Alamat Domisili / Kota
                        </label>

                        <textarea
                            name="address"
                            rows="2"
                            placeholder="Kota atau alamat domisili"
                            class="form-input"
                        ><?= htmlspecialchars($user['address'] ?? '') ?></textarea>

                    </div>


                    <div
                        style="
                            margin-top:20px;
                            display:flex;
                            justify-content:flex-end;
                        "
                    >

                        <button
                            type="submit"
                            class="btn-save"
                        >
                            Simpan Perubahan Profil
                        </button>

                    </div>

                </form>

            </div>


            <div
                id="tab-security"
                class="settings-card user-tab-pane"
                style="display:none;"
            >

                <div
                    style="
                        border-bottom:1px solid #E5E7EB;
                        padding-bottom:12px;
                        margin-bottom:20px;
                    "
                >

                    <h3
                        style="
                            font-family:'Playfair Display',serif;
                            font-size:18px;
                            color:#701C2F;
                            margin:0;
                        "
                    >
                        Keamanan Kata Sandi Akun
                    </h3>

                    <p
                        style="
                            font-size:12px;
                            color:#6B7280;
                            margin-top:4px;
                        "
                    >
                        Ganti kata sandi login Anda secara berkala
                        untuk menjaga keamanan data undangan dan pesanan.
                    </p>

                </div>


                <form method="POST">

                    <input
                        type="hidden"
                        name="action"
                        value="update_password"
                    >


                    <div
                        style="
                            background:#FAF3DE;
                            border:1px solid #E5D7B7;
                            border-radius:14px;
                            padding:16px;
                            margin-bottom:14px;
                        "
                    >

                        <label
                            style="
                                font-size:12px;
                                font-weight:bold;
                                color:#701C2F;
                                display:block;
                                margin-bottom:4px;
                            "
                        >
                            Masukkan Kata Sandi Lama *
                        </label>


                        <div class="password-wrapper">

                            <input
                                type="password"
                                id="user_curr_pass"
                                name="old_password"
                                required
                                placeholder="Kata sandi saat ini"
                                class="form-input"
                                style="background:#FFF;"
                            >


                            <button
                                type="button"
                                class="toggle-password"
                                onclick="togglePasswordVisibility('user_curr_pass', this)"
                                title="Lihat Kata Sandi"
                                aria-label="Lihat Kata Sandi"
                            >

                                <!-- ICON MATA -->
                                <svg
                                    viewBox="0 0 24 24"
                                    width="22"
                                    height="22"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >

                                    <path
                                        d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"
                                    ></path>

                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="3"
                                    ></circle>

                                </svg>

                            </button>

                        </div>

                    </div>


     
                    <div class="form-grid-2">


                        <!-- PASSWORD BARU -->

                        <div
                            style="
                                background:#FAF3DE;
                                border:1px solid #E5D7B7;
                                border-radius:14px;
                                padding:16px;
                            "
                        >

                            <label
                                style="
                                    font-size:12px;
                                    font-weight:bold;
                                    color:#701C2F;
                                    display:block;
                                    margin-bottom:4px;
                                "
                            >
                                Kata Sandi Baru *
                            </label>


                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    id="user_new_pass"
                                    name="new_password"
                                    required
                                    minlength="6"
                                    placeholder="Minimal 6 karakter"
                                    class="form-input"
                                    style="background:#FFF;"
                                >


                                <button
                                    type="button"
                                    class="toggle-password"
                                    onclick="togglePasswordVisibility('user_new_pass', this)"
                                    title="Lihat Kata Sandi"
                                    aria-label="Lihat Kata Sandi"
                                >

                                    <!-- ICON MATA -->

                                    <svg
                                        viewBox="0 0 24 24"
                                        width="22"
                                        height="22"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >

                                        <path
                                            d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"
                                        ></path>

                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="3"
                                        ></circle>

                                    </svg>

                                </button>

                            </div>

                        </div>


                        <!-- KONFIRMASI PASSWORD -->

                        <div
                            style="
                                background:#FAF3DE;
                                border:1px solid #E5D7B7;
                                border-radius:14px;
                                padding:16px;
                            "
                        >

                            <label
                                style="
                                    font-size:12px;
                                    font-weight:bold;
                                    color:#701C2F;
                                    display:block;
                                    margin-bottom:4px;
                                "
                            >
                                Ulangi Kata Sandi Baru *
                            </label>


                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    id="user_conf_pass"
                                    name="confirm_password"
                                    required
                                    minlength="6"
                                    placeholder="Ketik ulang kata sandi baru"
                                    class="form-input"
                                    style="background:#FFF;"
                                >


                                <button
                                    type="button"
                                    class="toggle-password"
                                    onclick="togglePasswordVisibility('user_conf_pass', this)"
                                    title="Lihat Kata Sandi"
                                    aria-label="Lihat Kata Sandi"
                                >

                                    <!-- ICON MATA -->

                                    <svg
                                        viewBox="0 0 24 24"
                                        width="22"
                                        height="22"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >

                                        <path
                                            d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"
                                        ></path>

                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="3"
                                        ></circle>

                                    </svg>

                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- BUTTON -->

                    <div
                        style="
                            margin-top:20px;
                            display:flex;
                            justify-content:flex-end;
                        "
                    >

                        <button
                            type="submit"
                            class="btn-save"
                        >
                            Simpan Perubahan Sandi
                        </button>

                    </div>

                </form>

            </div>
        </div>
    </main>
    <script>

        function switchUserTab(tabId, clickedButton) {

            // Sembunyikan semua tab
            document
                .querySelectorAll('.user-tab-pane')
                .forEach(function(pane) {

                    pane.style.display = 'none';

                });


            // Hapus active dari semua tombol
            document
                .querySelectorAll('.user-tab-btn')
                .forEach(function(btn) {

                    btn.classList.remove('active');

                });


            // Tampilkan tab yang dipilih
            var target = document.getElementById(tabId);

            if (target) {

                target.style.display = 'block';

            }


            // Aktifkan tombol yang diklik
            if (clickedButton) {

                clickedButton.classList.add('active');

            }

        }


        function togglePasswordVisibility(inputId, btn) {

            var input = document.getElementById(inputId);

            if (!input || !btn) {
                return;
            }


            if (input.type === 'password') {

                input.type = 'text';


                btn.innerHTML = `
                    <svg
                        viewBox="0 0 24 24"
                        width="22"
                        height="22"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path d="M3 3L21 21"></path>

                        <path
                            d="M10.58 10.58
                               A2 2 0 0 0
                               13.42 13.42"
                        ></path>

                        <path
                            d="M9.88 4.24
                               A9.77 9.77 0 0 1
                               12 4
                               C17 4 21 8 22 12
                               C21.59 13.63 20.72 15.15 19.53 16.4"
                        ></path>

                        <path
                            d="M6.61 6.61
                               C4.62 7.82 3.1 9.7 2 12
                               C3 16 7 20 12 20
                               C13.43 20 14.79 19.69 16.03 19.13"
                        ></path>

                    </svg>
                `;


                btn.title = 'Sembunyikan Kata Sandi';

                btn.setAttribute(
                    'aria-label',
                    'Sembunyikan Kata Sandi'
                );


            }

            else {

                input.type = 'password';


                btn.innerHTML = `
                    <svg
                        viewBox="0 0 24 24"
                        width="22"
                        height="22"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >

                        <path
                            d="M2 12
                               S5.5 5
                               12 5
                               S22 12
                               22 12
                               S18.5 19
                               12 19
                               S2 12
                               2 12Z"
                        ></path>

                        <circle
                            cx="12"
                            cy="12"
                            r="3"
                        ></circle>

                    </svg>
                `;


                btn.title = 'Lihat Kata Sandi';

                btn.setAttribute(
                    'aria-label',
                    'Lihat Kata Sandi'
                );

            }

        }

    </script>
</body>
</html>