<?php
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Login/Login.php");
    exit;
}

$alert = '';

// Proses Simpan Balasan Admin / Moderasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reply_review') {
        $review_id   = intval($_POST['review_id']);
        $admin_reply = mysqli_real_escape_string($conn, $_POST['admin_reply']);
        mysqli_query($conn, "UPDATE reviews SET admin_reply = '$admin_reply' WHERE id = $review_id");
        $alert = "Balasan untuk ulasan berhasil disimpan!";
    } elseif ($_POST['action'] === 'delete_review') {
        $review_id = intval($_POST['review_id']);
        mysqli_query($conn, "DELETE FROM reviews WHERE id = $review_id");
        $alert = "Ulasan berhasil dihapus dari sistem!";
    }
}

// Filter Rating
$rating_filter = $_GET['rating'] ?? 'all';
$where = "WHERE 1=1";
if ($rating_filter !== 'all') {
    $r_int = intval($rating_filter);
    $where .= " AND rating = $r_int";
}

$reviews = mysqli_query($conn, "SELECT * FROM reviews $where ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Ulasan Pelanggan - Admin LilinNawa</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-wrapper">
    <?php include '../sidebar.php'; ?>

    <main class="admin-main">
      
        <div class="page-title-box">
            <h2>Ulasan &amp; Testimoni Pelanggan</h2>
            <p>Kelola testimoni yang diberikan pembeli, berikan respon balasan resmi LilinNawa yang tampil di portal publik.</p>
        </div>

        <?php if (!empty($alert)): ?>
        <div class="alert-box alert-success alert-dismissible" style="background:#D1FAE5; color:#065F46; padding:12px; border-radius:12px; margin-bottom:20px; font-weight:600;">
            ✓ <?= $alert ?>
        </div>
        <?php endif; ?>

        <!-- Toolbar Filter Rating -->
        <div class="table-card" style="padding:14px 20px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; gap:10px; align-items:center;">
                <span style="font-size:12px; font-weight:bold; color:#701C2F;">Filter Rating:</span>
                <a href="Ulasan.php?rating=all" class="btn-admin <?= $rating_filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:11px; padding:4px 10px;">Semua</a>
                <a href="Ulasan.php?rating=5" class="btn-admin <?= $rating_filter === '5' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:11px; padding:4px 10px;">⭐⭐⭐⭐⭐ (5)</a>
                <a href="Ulasan.php?rating=4" class="btn-admin <?= $rating_filter === '4' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:11px; padding:4px 10px;">⭐⭐⭐⭐ (4)</a>
                <a href="Ulasan.php?rating=3" class="btn-admin <?= $rating_filter === '3' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:11px; padding:4px 10px;">⭐⭐⭐ (3)</a>
            </div>
            <span style="font-size:12px; color:#6B7280; font-weight:600;"><?= mysqli_num_rows($reviews) ?> Ulasan Ditemukan</span>
        </div>

        <!-- Daftar Kartu Ulasan -->
        <div style="display:flex; flex-direction:column; gap:16px;">
            <?php if (mysqli_num_rows($reviews) === 0): ?>
            <div class="table-card" style="text-align:center; padding:36px; color:#9CA3AF;">
                Belum ada ulasan untuk filter yang dipilih.
            </div>
            <?php else: ?>
            <?php while($r = mysqli_fetch_assoc($reviews)): ?>
            <div class="table-card" style="margin-bottom:0;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                    <div>
                        <strong style="font-size:15px; color:#701C2F;"><?= htmlspecialchars($r['customer_name']) ?></strong>
                        <span class="admin-badge admin-badge-green" style="margin-left:8px;">Pembeli Terverifikasi</span>
                        <div style="font-size:12px; color:#6B7280; margin-top:2px;">
                            Pesanan: <strong><?= htmlspecialchars($r['order_number']) ?></strong> • Template: <strong><?= htmlspecialchars($r['template_name']) ?></strong>
                        </div>
                    </div>
                    <span style="color:#F59E0B; font-size:18px; font-weight:bold;">
                        <?= str_repeat('★', (int)$r['rating']) ?>
                    </span>
                </div>

                <div style="background:#F9FAFB; border-left:3px solid #D4AF37; padding:12px 16px; border-radius:4px; font-size:13px; color:#374151; margin-bottom:14px; font-style:italic;">
                    "<?= htmlspecialchars($r['comment']) ?>"
                </div>

                <!-- Respon Admin -->
                <?php if (!empty($r['admin_reply'])): ?>
                <div style="background:#FAF3DE; border:1px solid #E2D3AF; border-radius:10px; padding:12px 14px; margin-bottom:12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <strong style="color:#701C2F; font-size:11px; text-transform:uppercase;">Balasan Resmi Admin:</strong>
                        <button type="button" onclick='openReplyModal(<?= $r["id"] ?>, <?= htmlspecialchars(json_encode($r["admin_reply"]), ENT_QUOTES) ?>)' style="background:none; border:none; color:#701C2F; font-size:11px; font-weight:bold; cursor:pointer;">✏️ Ubah Balasan</button>
                    </div>
                    <p style="font-size:12px; color:#4A101E;"><?= htmlspecialchars($r['admin_reply']) ?></p>
                </div>
                <?php else: ?>
                <div style="display:flex; gap:10px; align-items:center;">
                    <button type="button" class="btn-admin btn-primary" style="font-size:11px;" onclick='openReplyModal(<?= $r["id"] ?>, "")'>
                        💬 Balas Ulasan Ini
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- Modal Balas Ulasan (Jendela Popup) -->
        <div id="modalReplyReview" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>💬 Tanggapan / Balasan Admin</h3>
                    <button type="button" class="btn-close-modal" onclick="closeModal('modalReplyReview')">✕</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reply_review">
                        <input type="hidden" name="review_id" id="reply_review_id">

                        <div class="form-field">
                            <label>Pesan Balasan Anda untuk Pelanggan (Tampil di Website)</label>
                            <textarea name="admin_reply" id="reply_admin_text" rows="5" placeholder="Terima kasih banyak atas kepercayaannya Kak! Semoga pernikahannya lancar dan bahagia selalu..." required></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn-admin btn-secondary" onclick="closeModal('modalReplyReview')">Batal</button>
                        <button type="submit" class="btn-admin btn-primary">Simpan Balasan</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin.js"></script>
    <script>
    function openReplyModal(id, currentReply) {
        document.getElementById('reply_review_id').value = id;
        document.getElementById('reply_admin_text').value = currentReply || '';
        openModal('modalReplyReview');
    }
    </script>
</body>
</html>