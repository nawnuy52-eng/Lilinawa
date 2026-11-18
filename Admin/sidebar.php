<?php
// ========================================================
// LILINNAWA - ADMIN SIDEBAR
// ========================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../Config/Database.php';

$current_page = basename($_SERVER['PHP_SELF']);

$admin_name = $_SESSION['name'] ?? 'Administrator';

$admin_avatar = strtoupper(
    substr($admin_name, 0, 2)
);

$new_orders_count = 0;
$unreplied_reviews_count = 0;
$unread_chats_count = 0;


// ========================================================
// CEK DATABASE
// ========================================================

if (isset($conn) && $conn) {

    // ORDER
    try {

        $query = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS cnt
             FROM orders
             WHERE status IN (
                 'pilih_template',
                 'konsultasi',
                 'data_terkirim'
             )"
        );

        if ($query) {
            $row = mysqli_fetch_assoc($query);
            $new_orders_count = (int)($row['cnt'] ?? 0);
        }

    } catch (Throwable $e) {
        $new_orders_count = 0;
    }


    // REVIEW
    try {

        $query = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS cnt
             FROM reviews
             WHERE admin_reply IS NULL
             OR admin_reply = ''"
        );

        if ($query) {
            $row = mysqli_fetch_assoc($query);
            $unreplied_reviews_count = (int)($row['cnt'] ?? 0);
        }

    } catch (Throwable $e) {
        $unreplied_reviews_count = 0;
    }


    // CHAT
    try {

        $query = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS cnt
             FROM inbox
             WHERE sender_role = 'customer'
             AND is_read = 0"
        );

        if ($query) {
            $row = mysqli_fetch_assoc($query);
            $unread_chats_count = (int)($row['cnt'] ?? 0);
        }

    } catch (Throwable $e) {
        $unread_chats_count = 0;
    }
}

?>


<!-- =====================================================
     CSS
     ===================================================== -->

<link
    rel="stylesheet"
    href="/LILINNAWA/Admin/sidebar.css?v=20261007"
>


<!-- =====================================================
     SIDEBAR
     ===================================================== -->

<aside class="admin-sidebar">


    <!-- =================================================
         BAGIAN ATAS
         LOGO + PROFIL
         TIDAK SCROLL
         ================================================= -->

    <div class="sidebar-top">


        <!-- LOGO -->

        <div class="sidebar-logo">

            <img
                src="/LILINNAWA/PV/Logo%20LN.png"
                alt="Logo LilinNawa"
            >

        </div>


        <!-- PEMISAH -->

        <div class="sidebar-divider"></div>


        <!-- PROFIL ADMIN -->

        <div class="sidebar-profile">

            <div class="profile-avatar">
                <?= htmlspecialchars($admin_avatar) ?>
            </div>


            <div class="profile-information">

                <div class="profile-name">
                    <?= htmlspecialchars($admin_name) ?>
                </div>

                <div class="profile-role">
                    Super Administrator
                </div>

            </div>


            <?php if ($new_orders_count > 0): ?>

                <div class="profile-badge">
                    <?= $new_orders_count ?> Baru
                </div>

            <?php endif; ?>

        </div>


        <!-- PEMISAH -->

        <div class="sidebar-divider profile-divider"></div>

    </div>


    <!-- =================================================
         MENU
         HANYA INI YANG SCROLL
         ================================================= -->

    <div class="sidebar-menu-scroll">

        <nav class="sidebar-nav">


            <!-- =========================================
                 MAIN
                 ========================================= -->

            <section
                class="menu-section"
                id="admin-sec-main"
            >

                <div
                    class="menu-section-title"
                    onclick="toggleAdminMenu(this)"
                >

                    <div class="menu-title-left">

                        <span class="menu-arrow">
                            ▾
                        </span>

                        <span>
                            MAIN
                        </span>

                    </div>

                </div>


                <div class="menu-items">


                    <a
                        href="../MAIN_A/Home.php"
                        class="menu-item <?= $current_page === 'Home.php' ? 'active' : '' ?>"
                    >
                        <span>
                            Home
                        </span>
                    </a>


                    <a
                        href="../MAIN_A/Order.php"
                        class="menu-item <?= $current_page === 'Order.php' ? 'active' : '' ?>"
                    >

                        <span>
                            Manajemen Order
                        </span>

                        <?php if ($new_orders_count > 0): ?>

                            <span class="notification-dot"></span>

                        <?php endif; ?>

                    </a>


                    <a
                        href="../MAIN_A/Template.php"
                        class="menu-item <?= $current_page === 'Template.php' ? 'active' : '' ?>"
                    >
                        <span>
                            Katalog Template
                        </span>
                    </a>


                    <a
                        href="../MAIN_A/Analytics.php"
                        class="menu-item <?= $current_page === 'Analytics.php' ? 'active' : '' ?>"
                    >
                        <span>
                            Analytics &amp; Omset
                        </span>
                    </a>


                    <a
                        href="../MAIN_A/Ulasan.php"
                        class="menu-item <?= $current_page === 'Ulasan.php' ? 'active' : '' ?>"
                    >

                        <span>
                            Ulasan Pelanggan
                        </span>

                        <?php if ($unreplied_reviews_count > 0): ?>

                            <span class="notification-dot"></span>

                        <?php endif; ?>

                    </a>


                </div>

            </section>


            <!-- =========================================
                 COMMUNICATION
                 ========================================= -->

            <section
                class="menu-section"
                id="admin-sec-communication"
            >

                <div
                    class="menu-section-title"
                    onclick="toggleAdminMenu(this)"
                >

                    <div class="menu-title-left">

                        <span class="menu-arrow">
                            ▾
                        </span>

                        <span>
                            COMMUNICATION
                        </span>

                    </div>

                </div>


                <div class="menu-items">


                    <a
                        href="../COMMUNICATION_A/Inbox.php"
                        class="menu-item <?= $current_page === 'Inbox.php' ? 'active' : '' ?>"
                    >

                        <span>
                            Inbox Chat CS
                        </span>

                        <?php if ($unread_chats_count > 0): ?>

                            <span class="notification-dot green"></span>

                        <?php endif; ?>

                    </a>


                    <a
                        href="../COMMUNICATION_A/Notifikasi.php"
                        class="menu-item <?= $current_page === 'Notifikasi.php' ? 'active' : '' ?>"
                    >

                        <span>
                            Notifikasi
                        </span>

                        <?php if ($new_orders_count > 0): ?>

                            <span class="notification-dot"></span>

                        <?php endif; ?>

                    </a>


                </div>

            </section>


            <!-- =========================================
                 MANAGEMENT
                 ========================================= -->

            <section
                class="menu-section"
                id="admin-sec-management"
            >

                <div
                    class="menu-section-title"
                    onclick="toggleAdminMenu(this)"
                >

                    <div class="menu-title-left">

                        <span class="menu-arrow">
                            ▾
                        </span>

                        <span>
                            MANAGEMENT
                        </span>

                    </div>

                </div>


                <div class="menu-items">


                    <a
                        href="../MANAGEMENT_A/Customer.php"
                        class="menu-item <?= $current_page === 'Customer.php' ? 'active' : '' ?>"
                    >

                        <span>
                            Daftar Customer
                        </span>

                    </a>


                </div>

            </section>


            <!-- =========================================
                 SETTINGS
                 ========================================= -->

            <section
                class="menu-section"
                id="admin-sec-settings"
            >

                <div
                    class="menu-section-title"
                    onclick="toggleAdminMenu(this)"
                >

                    <div class="menu-title-left">

                        <span class="menu-arrow">
                            ▾
                        </span>

                        <span>
                            SETTINGS
                        </span>

                    </div>

                </div>


                <div class="menu-items">


                    <a
                        href="../SETTINGS_A/Pengaturan.php"
                        class="menu-item <?= $current_page === 'Pengaturan.php' ? 'active' : '' ?>"
                    >

                        <span>
                            Pengaturan &amp; Profil
                        </span>

                    </a>


                    <a
                        href="../SETTINGS_A/Help.php"
                        class="menu-item <?= $current_page === 'Help.php' ? 'active' : '' ?>"
                    >

                        <span>
                            Bantuan &amp; Panduan
                        </span>

                    </a>


                </div>

            </section>


        </nav>

    </div>


    <!-- =================================================
         LOGOUT
         TIDAK SCROLL
         ================================================= -->

    <div class="sidebar-footer">

        <a
            href="../../Login/Logout.php"
            class="logout-button"
            onclick="return confirm('Apakah Anda yakin ingin keluar dari Admin Workspace LilinNawa?');"
        >
            LOGOUT
        </a>

    </div>


</aside>


<script>

/* ========================================================
   TOGGLE MENU
   ======================================================== */

function toggleAdminMenu(element) {

    const section =
        element.closest('.menu-section');

    if (!section) return;


    const items =
        section.querySelector('.menu-items');

    const arrow =
        section.querySelector('.menu-arrow');

    if (!items) return;


    const closed =
        items.style.display === 'none';


    if (closed) {

        items.style.display = 'block';

        if (arrow) {
            arrow.textContent = '▾';
        }

        localStorage.setItem(
            'admin_menu_' + section.id,
            'open'
        );

    } else {

        items.style.display = 'none';

        if (arrow) {
            arrow.textContent = '▸';
        }

        localStorage.setItem(
            'admin_menu_' + section.id,
            'closed'
        );

    }

}


/* ========================================================
   LOAD MENU
   ======================================================== */

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const sections =
            document.querySelectorAll(
                '.admin-sidebar .menu-section'
            );


        sections.forEach(
            function (section) {

                const items =
                    section.querySelector(
                        '.menu-items'
                    );

                const arrow =
                    section.querySelector(
                        '.menu-arrow'
                    );

                const active =
                    section.querySelector(
                        '.menu-item.active'
                    );

                const saved =
                    localStorage.getItem(
                        'admin_menu_' + section.id
                    );


                if (active) {

                    items.style.display =
                        'block';

                    arrow.textContent =
                        '▾';

                } else if (saved === 'closed') {

                    items.style.display =
                        'none';

                    arrow.textContent =
                        '▸';

                } else {

                    items.style.display =
                        'block';

                    arrow.textContent =
                        '▾';

                }

            }
        );

    }
);

</script>