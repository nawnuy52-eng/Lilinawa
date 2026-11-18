<?php
// User/Main_U/Ulasan.php - Ulasan & Testimoni Pelanggan
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../Login/Login.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$user_name  = $_SESSION['name'] ?? 'Pelanggan';
$user_phone = $_SESSION['phone'] ?? '';
$user_email = $_SESSION['email'] ?? '';

$msg_status = '';
// Tangani Submit Ulasan Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kirim_ulasan') {
    $ord_no  = mysqli_real_escape_string($conn, $_POST['order_number']);
    $rating  = (int)($_POST['rating'] ?? 5);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    // Ambil nama template aktual dari pesanan
    $tpl_q = mysqli_query($conn, "SELECT template_name FROM orders WHERE order_number = '$ord_no' LIMIT 1");
    $tpl_row = mysqli_fetch_assoc($tpl_q);
    $tpl_nm = $tpl_row['template_name'] ?? ($_POST['template_name'] ?? 'Undangan Digital');
    $tpl_nm = mysqli_real_escape_string($conn, $tpl_nm);

    $ins_q = "INSERT INTO reviews (customer_name, order_number, template_name, rating, comment, is_published) 
              VALUES ('$user_name', '$ord_no', '$tpl_nm', $rating, '$comment', 1)";
    if (mysqli_query($conn, $ins_q)) {
        $msg_status = 'Terima kasih! Ulasan & kepuasan Anda berhasil dikirimkan.';
    }
}

// Ambil Ulasan yang dipublikasikan
$reviews_res = mysqli_query($conn, "SELECT * FROM reviews WHERE is_published = 1 ORDER BY id DESC");

// Ambil order milik user ini untuk pilihan dropdown
$my_orders_q = mysqli_query($conn, "SELECT order_number, template_name FROM orders WHERE user_id = $user_id OR customer_phone = '$user_phone' OR customer_name = '$user_name'");
$my_orders = [];
while ($mo = mysqli_fetch_assoc($my_orders_q)) {
    $my_orders[] = $mo;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ulasan &amp; Testimoni - LilinNawa</title>

    <!-- CSS SIDEBAR -->
    <link rel="stylesheet" href="../sidebar.css?v=<?= time() ?>">

    <!-- CSS HALAMAN ULASAN -->
    <link rel="stylesheet" href="ulasan.css?v=<?= time() ?>">
</head>
<body class="user-layout">
    <?php include '../sidebar.php'; ?>

    <main class="user-main-content">
        <div class="review-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
            <div>
                <h1>⭐ Ulasan &amp; Pengalaman Klien</h1>
                <p>Bagikan kesan dan ulasan Anda tentang kualitas undangan pernikahan digital serta pelayanan tim LN Digital.</p>
            </div>
            <button type="button" onclick="openModal('modalTulisUlasan')" style="background: #721B2F; color: #FFFFFF; border: none; padding: 12px 24px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                ✍️ Tulis Ulasan Pesanan
            </button>
        </div>

        <?php if ($msg_status): ?>
        <div style="background: #10B981; color: #FFFFFF; padding: 14px 18px; border-radius: 14px; font-weight: 700; font-size: 13px; margin-bottom: 20px;">
            ✓ <?= htmlspecialchars($msg_status) ?>
        </div>
        <?php endif; ?>

        <!-- Daftar Ulasan Publik -->
        <div class="reviews-grid">
            <?php if (mysqli_num_rows($reviews_res) === 0): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 48px; background: #FFFFFF; border-radius: 16px; color: #9CA3AF;">
                Belum ada ulasan yang dipublikasikan. Jadilah yang pertama memberikan testimoni!
            </div>
            <?php else: ?>
            <?php while ($rev = mysqli_fetch_assoc($reviews_res)): ?>
            <div class="review-item-card">
                <div class="review-card-head">
                    <span class="reviewer-name"><?= htmlspecialchars($rev['customer_name']) ?></span>
                    <span class="reviewer-template"><?= htmlspecialchars($rev['template_name']) ?></span>
                </div>
                <div class="review-stars">
                    <?= str_repeat('★', (int)$rev['rating']) ?><?= str_repeat('☆', 5 - (int)$rev['rating']) ?>
                </div>
                <p class="review-comment">"<?= htmlspecialchars($rev['comment']) ?>"</p>
                <?php if (!empty($rev['admin_reply'])): ?>
                <div class="admin-reply-box">
                    <strong>Balasan Tim LilinNawa:</strong> <?= htmlspecialchars($rev['admin_reply']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- Jendela Modal Tulis Ulasan (Pop-up Window) -->
        <div id="modalTulisUlasan" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>⭐ Tulis Ulasan &amp; Pengalaman</h3>
                    <button type="button" class="btn-close-modal" onclick="closeModal('modalTulisUlasan')">✕</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="kirim_ulasan">

                        <div style="display: flex; flex-direction: column; gap: 14px;">
                            <div>
                                <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 6px;">Pilih Pesanan Anda</label>
                                <select name="order_number" required style="width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 10px; font-size: 13px; box-sizing: border-box;">
                                    <?php if (count($my_orders) > 0): ?>
                                        <?php foreach ($my_orders as $ord): ?>
                                        <option value="<?= htmlspecialchars($ord['order_number']) ?>">
                                            <?= htmlspecialchars($ord['order_number']) ?> - <?= htmlspecialchars($ord['template_name'] ?? 'Undangan') ?>
                                        </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="#LN-BARU">Pesanan Undangan Digital LilinNawa</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div>
                                <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 6px;">Beri Rating Bintang</label>
                                <select name="rating" style="width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 10px; font-size: 13px; box-sizing: border-box;">
                                    <option value="5">⭐⭐⭐⭐⭐ (5/5) Sangat Puas &amp; Elegan</option>
                                    <option value="4">⭐⭐⭐⭐ (4/5) Bagus Sekali &amp; Cepat</option>
                                    <option value="3">⭐⭐⭐ (3/5) Cukup Baik</option>
                                    <option value="2">⭐⭐ (2/5) Perlu Peningkatan</option>
                                    <option value="1">⭐ (1/5) Kurang Puas</option>
                                </select>
                            </div>

                            <div>
                                <label style="font-size: 12px; font-weight: 700; color: #374151; display: block; margin-bottom: 6px;">Ulasan &amp; Testimoni Anda *</label>
                                <textarea name="comment" required rows="4" placeholder="Ceritakan pengalaman Anda mengenai tampilan desain, kecepatan pengerjaan, dan kemudahan membagikan undangan..." style="width: 100%; padding: 12px; border: 1px solid #D1D5DB; border-radius: 12px; font-size: 13px; box-sizing: border-box;"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-admin btn-secondary" onclick="closeModal('modalTulisUlasan')">Batal</button>
                        <button type="submit" class="btn-save-event">Kirim Ulasan</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
