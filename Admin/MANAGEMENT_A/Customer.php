<?php
// Admin/MANAGEMENT_A/Customer.php - Data Pelanggan LilinNawa
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Login/Login.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$where = "WHERE role = 'customer'";
if (!empty($search)) {
    $safe = mysqli_real_escape_string($conn, $search);
    $where .= " AND (name LIKE '%$safe%' OR phone LIKE '%$safe%' OR email LIKE '%$safe%')";
}

$customers = mysqli_query($conn, "SELECT u.*, 
                                  (SELECT COUNT(*) FROM orders WHERE customer_phone = u.phone OR customer_name = u.name) as order_count,
                                  (SELECT SUM(total_price) FROM orders WHERE (customer_phone = u.phone OR customer_name = u.name) AND payment_status = 'lunas') as total_spend
                                  FROM users u 
                                  $where 
                                  ORDER BY u.id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Customer - Admin LilinNawa</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-wrapper">
    <?php include '../sidebar.php'; ?>

    <main class="admin-main">
   
        <div class="page-title-box">
            <h2>Data &amp; Direktori Pelanggan</h2>
            <p>Daftar seluruh calon pengantin yang terdaftar, riwayat transaksi pesanan, dan kontak WhatsApp.</p>
        </div>

        <!-- Filter Pencarian Customer (Live Search) -->
        <div class="table-card" style="padding:14px 20px; margin-bottom:20px;">
            <form method="GET" onsubmit="return false;" style="display:flex; gap:10px;">
                <input type="text" id="customerLiveSearch" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama, WhatsApp, email, atau alamat..." style="flex:1; padding:10px 14px; border:1px solid #ccc; border-radius:10px; font-size:13px;" oninput="filterCustomersLive()">
                <?php if (!empty($search)): ?>
                    <a href="Customer.php" class="btn-admin btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Nama Customer</th>
                            <th>Kontak WhatsApp</th>
                            <th>Email</th>
                            <th>Alamat Domisili</th>
                            <th>Total Order</th>
                            <th>Total Transaksi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($customers) === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:32px; color:#9CA3AF;">Belum ada customer yang terdaftar.</td>
                        </tr>
                        <?php else: ?>
                        <?php while($c = mysqli_fetch_assoc($customers)): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($c['name']) ?></strong><br>
                                <small style="color:#6B7280; font-size:11px;">ID: #USR-<?= $c['id'] ?></small>
                            </td>
                            <td>
                                <strong style="color:#701C2F;"><?= htmlspecialchars($c['phone']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($c['email']) ?></td>
                            <td>
                                <span style="font-size:11.5px; color:#4B5563;"><?= htmlspecialchars($c['address'] ?? 'Kota Bandung, Jawa Barat') ?></span>
                            </td>
                            <td>
                                <span class="admin-badge admin-badge-maroon"><?= $c['order_count'] ?> Pesanan</span>
                            </td>
                            <td>
                                <strong>Rp <?= number_format($c['total_spend'] ?? 0, 0, ',', '.') ?></strong>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $c['phone']) ?>" target="_blank" class="btn-admin btn-gold" style="font-size:11px; padding:4px 10px;">
                                        💬 WhatsApp
                                    </a>
                                    <a href="../MAIN_A/Order.php?search=<?= urlencode($c['phone']) ?>" class="btn-admin btn-secondary" style="font-size:11px; padding:4px 10px;">
                                        📦 Lihat Order
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin.js"></script>
    <script>
    function filterCustomersLive() {
        var val = (document.getElementById('customerLiveSearch').value || '').toLowerCase().trim();
        var rows = document.querySelectorAll('.admin-table tbody tr');
        rows.forEach(function(row) {
            var text = (row.innerText || row.textContent).toLowerCase();
            row.style.display = text.indexOf(val) > -1 ? '' : 'none';
        });
    }
    </script>
</body>
</html>