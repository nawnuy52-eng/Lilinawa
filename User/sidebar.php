<?php
// ============================================================
// User/sidebar.php
// Sidebar Portal Klien - LilinNawa
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../Config/Database.php';


// ============================================================
// DATA USER
// ============================================================

$current_page = urldecode(basename($_SERVER['PHP_SELF']));

$user_name = $_SESSION['name'] ?? 'Pelanggan LilinNawa';

$user_phone = $_SESSION['phone'] ?? '';

$user_avatar = strtoupper(
    substr(trim($user_name), 0, 1)
);


// ============================================================
// CHAT BELUM DIBACA
// ============================================================

$unread_user_chat = 0;

try {

    if (isset($conn) && $conn) {

        $safe_phone = mysqli_real_escape_string(
            $conn,
            $user_phone
        );

        $query_unread = "
            SELECT COUNT(*) AS cnt
            FROM inbox
            WHERE room_id = 'room-$safe_phone'
            AND sender_role = 'admin'
            AND is_read = 0
        ";

        $result_unread = mysqli_query(
            $conn,
            $query_unread
        );

        if ($result_unread) {

            $row_unread = mysqli_fetch_assoc(
                $result_unread
            );

            $unread_user_chat =
                (int)($row_unread['cnt'] ?? 0);
        }
    }

} catch (Throwable $e) {

    $unread_user_chat = 0;
}

?>


<!-- ============================================================
     USER SIDEBAR
     ============================================================ -->

<aside class="user-sidebar">


    <!-- ========================================================
         LOGO AREA
         Logo dibuat seperti Sidebar Admin
         ======================================================== -->

    <div class="user-sidebar-brand">

        <a
            href="../Main_U/Home.php"
            class="user-sidebar-logo-link"
        >

            <img
                src="/LILINNAWA/PV/Logo%20LN.png"
                alt="Logo LilinNawa"
                class="user-sidebar-logo"
            >

        </a>

    </div>


    <!-- ========================================================
         PROFILE USER
         ======================================================== -->

    <div class="user-sidebar-profile">

        <div class="user-avatar-circle">

            <?= htmlspecialchars($user_avatar) ?>

        </div>


        <div class="user-profile-details">

            <div class="user-profile-name">

                <?= htmlspecialchars($user_name) ?>

            </div>


            <div class="user-profile-status">

                <?= htmlspecialchars($user_phone) ?>

            </div>

        </div>

    </div>


    <!-- ========================================================
         MENU AREA
         HANYA BAGIAN INI YANG BISA SCROLL
         ======================================================== -->

    <nav class="user-sidebar-nav">


        <!-- ====================================================
             MAIN
             ==================================================== -->

        <div class="user-nav-group">

            <div class="user-nav-heading">

                MAIN

            </div>


            <!-- HOME -->

            <a
                href="../Main_U/Home.php"
                class="user-nav-item
                <?= $current_page === 'Home.php' ? 'active' : '' ?>"
            >

                <span>
                    Home
                </span>

            </a>


            <!-- KATALOG TEMPLATE -->

            <a
                href="../Main_U/Katalog Template.php"
                class="user-nav-item
                <?= $current_page === 'Katalog Template.php' ? 'active' : '' ?>"
            >

                <span>
                    Katalog Template
                </span>

            </a>


            <!-- STATUS TRACKING -->

            <a
                href="../Main_U/Status & TrackingOrder.php"
                class="user-nav-item
                <?= strpos($current_page, 'Status') !== false ? 'active' : '' ?>"
            >

                <span>
                    Status &amp; Tracking Order
                </span>

            </a>


            <!-- ULASAN -->

            <a
                href="../Main_U/Ulasan.php"
                class="user-nav-item
                <?= $current_page === 'Ulasan.php' ? 'active' : '' ?>"
            >

                <span>
                    Ulasan
                </span>

            </a>

        </div>


        <!-- ====================================================
             COMMUNICATION
             ==================================================== -->

        <div class="user-nav-group">

            <div class="user-nav-heading">

                COMMUNICATION

            </div>


            <!-- INBOX CS -->

            <a
                href="../Communication_U/Inbox CS.php"
                class="user-nav-item
                <?= $current_page === 'Inbox CS.php' ? 'active' : '' ?>"
            >

                <span>
                    Inbox CS
                </span>


                <?php if ($unread_user_chat > 0): ?>

                    <span class="user-nav-badge">

                        <?= $unread_user_chat ?>

                    </span>

                <?php endif; ?>

            </a>

        </div>


        <!-- ====================================================
             SETTINGS
             ==================================================== -->

        <div class="user-nav-group">

            <div class="user-nav-heading">

                SETTINGS

            </div>


            <!-- PENGATURAN -->

            <a
                href="../Settings_U/Pengaturan Akun.php"
                class="user-nav-item
                <?= $current_page === 'Pengaturan Akun.php' ? 'active' : '' ?>"
            >

                <span>
                    Pengaturan &amp; Profil
                </span>

            </a>


            <!-- BANTUAN -->

            <a
                href="../Settings_U/Bantuan.php"
                class="user-nav-item
                <?= $current_page === 'Bantuan.php' ? 'active' : '' ?>"
            >

                <span>
                    Bantuan &amp; Panduan
                </span>

            </a>

        </div>


    </nav>


    <!-- ========================================================
         LOGOUT
         TETAP DI BAGIAN PALING BAWAH
         ======================================================== -->

    <div class="user-sidebar-footer">

        <a
            href="../../Login/Logout.php"
            class="btn-sidebar-logout"
            onclick="
                return confirm(
                    'Apakah Anda yakin ingin keluar dari sesi portal klien?'
                );
            "
        >

            <span>
                LOGOUT
            </span>

        </a>

    </div>


</aside>


<!-- ============================================================
     MODAL HELPER
     ============================================================ -->

<script>

window.openModal = function(id) {

    const modal = document.getElementById(id);

    if (modal) {

        modal.style.display = 'flex';

    }

};


window.closeModal = function(id) {

    const modal = document.getElementById(id);

    if (modal) {

        modal.style.display = 'none';

    }

};


document.addEventListener(
    'DOMContentLoaded',
    function() {

        document
            .querySelectorAll('.modal-overlay')
            .forEach(function(overlay) {

                overlay.addEventListener(
                    'click',
                    function(e) {

                        if (e.target === overlay) {

                            overlay.style.display = 'none';

                        }

                    }
                );

            });


        window.addEventListener(
            'keydown',
            function(e) {

                if (e.key === 'Escape') {

                    document
                        .querySelectorAll('.modal-overlay')
                        .forEach(function(modal) {

                            modal.style.display = 'none';

                        });

                }

            }
        );

    }
);

</script>