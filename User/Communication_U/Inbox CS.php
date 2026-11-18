<?php
// User/Communication_U/Inbox CS.php - Room Chat Mandiri antara Klien & Admin CS
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../Login/Login.php");
    exit;
}

$user_name  = $_SESSION['name'] ?? 'Pelanggan';
$user_phone = $_SESSION['phone'] ?? '';
$room_id    = 'room-' . $user_phone;

// Handle Kirim Pesan Klien
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $message = trim($_POST['message'] ?? '');
    if (!empty($message)) {
        $msg_safe = mysqli_real_escape_string($conn, $message);
        mysqli_query($conn, "INSERT INTO inbox (room_id, order_number, sender_name, sender_phone, recipient_phone, sender_role, message, is_read) 
                             VALUES ('$room_id', '#LN001', '$user_name', '$user_phone', '081299887766', 'customer', '$msg_safe', 0)");
        header("Location: Inbox CS.php");
        exit;
    }
}

// Tandai pesan dari CS / Admin sebagai sudah dibaca oleh klien
@mysqli_query($conn, "UPDATE inbox SET is_read = 1 WHERE room_id = '$room_id' AND sender_role = 'admin' AND is_read = 0");

// Ambil Pesan Khusus Room Ini
$msgs_q = mysqli_query($conn, "SELECT * FROM inbox WHERE room_id = '$room_id' ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox CS - LN Digital</title>

    <link rel="stylesheet" href="../sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="inbox cs.css?v=<?= time() ?>">
</head>
<body class="user-layout">
    <?php include '../sidebar.php'; ?>

    <main class="user-main-content">
        <div class="inbox-header">
            <h1>💬 Inbox CS &amp; Konsultasi Desain</h1>
            <p>Ruang diskusi langsung antara Anda dan tim desainer LilinNawa untuk revisi materi, musik latar, dan koordinasi preview undangan.</p>
        </div>

        <div class="chat-window">
            <div class="chat-window-head">
                <div class="cs-info">
                    <div class="cs-avatar">CS</div>
                    <div>
                        <div class="cs-name">Customer Support LilinNawa</div>
                        <div class="cs-status">Online • Membantu Klien</div>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <a href="https://wa.me/6281234567890?text=Halo%20Admin%20LilinNawa%2C%20saya%20pelanggan%20ingin%20konsultasi%20pesanan" target="_blank" style="background:#25D366; color:#FFFFFF; padding:6px 14px; border-radius:10px; font-size:12px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                        <span>Chat via WA</span>
                    </a>
                    <div class="room-id-tag">ID: <?= htmlspecialchars($room_id) ?></div>
                </div>
            </div>

            <!-- Pesan Chat -->
            <div class="chat-messages" id="chatBox">
                <?php while ($m = mysqli_fetch_assoc($msgs_q)): ?>
                    <div class="chat-bubble <?= $m['sender_role'] === 'customer' ? 'customer' : 'admin' ?>">
                        <span class="sender-label"><?= htmlspecialchars($m['sender_name']) ?></span>
                        <div><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                        <span class="msg-time"><?= date('H:i', strtotime($m['created_at'] ?? 'now')) ?></span>
                    </div>
                <?php endwhile; ?>
            </div>

            <!-- Formulir Kirim Pesan -->
            <form method="POST" class="chat-input-bar">
                <input type="hidden" name="action" value="send_message">
                <input type="text" name="message" required placeholder="Ketik pesan Anda ke desainer LilinNawa..." autocomplete="off">
                <button type="submit" class="btn-send-chat">Kirim Pesan</button>
            </form>
        </div>
    </main>

    <script>
        // Auto scroll ke pesan paling bawah
        const box = document.getElementById('chatBox');
        if (box) {
            box.scrollTop = box.scrollHeight;
        }
    </script>
</body>
</html>
