<?php
session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Login/Login.php");
    exit;
}

// Kirim Pesan Chat Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_chat') {
    $room_id         = mysqli_real_escape_string($conn, $_POST['room_id']);
    $recipient_name  = mysqli_real_escape_string($conn, $_POST['recipient_name']);
    $recipient_phone = mysqli_real_escape_string($conn, $_POST['recipient_phone']);
    $message         = mysqli_real_escape_string($conn, $_POST['message']);

    // Menggunakan kolom sesuai struktur tabel inbox: sender_role = 'admin', is_read = 1
    @mysqli_query($conn, "INSERT INTO inbox (room_id, sender_name, sender_phone, recipient_phone, sender_role, message, is_read) 
                         VALUES ('$room_id', 'CS LilinNawa', '081299887766', '$recipient_phone', 'admin', '$message', 1)");
    header("Location: Inbox.php?room=" . urlencode($room_id));
    exit;
}

// Ambil Daftar Room Chat dari Pesan Inbox dan Customer secara aman
$rooms_q = mysqli_query($conn, "SELECT room_id, MAX(id) AS max_id FROM inbox WHERE room_id IS NOT NULL AND room_id != '' GROUP BY room_id ORDER BY max_id DESC");
$room_list = [];
if ($rooms_q) {
    while($rm = mysqli_fetch_assoc($rooms_q)) {
        $r_id = $rm['room_id'];
        $m_id = (int)$rm['max_id'];
        $msg_q = mysqli_query($conn, "SELECT sender_name, sender_phone, recipient_phone FROM inbox WHERE id = $m_id LIMIT 1");
        $m_data = mysqli_fetch_assoc($msg_q);
        
        $s_name = $m_data['sender_name'] ?? 'Pelanggan';
        $s_phone = $m_data['sender_phone'] ?? '';
        if (strpos($s_name, 'Admin') !== false || strpos($s_name, 'CS') !== false) {
            $s_phone = str_replace('room-', '', $r_id);
            $u_find = mysqli_query($conn, "SELECT name FROM users WHERE phone = '$s_phone' LIMIT 1");
            if ($u_f = mysqli_fetch_assoc($u_find)) {
                $s_name = $u_f['name'];
            }
        }
        $room_list[$r_id] = [
            'room_id' => $r_id,
            'sender_name' => $s_name,
            'sender_phone' => $s_phone
        ];
    }
}

// Tambahkan kontak customer aktif jika belum ada di room
$cust_q = mysqli_query($conn, "SELECT name, phone FROM users WHERE role = 'customer'");
if ($cust_q) {
    while($c = mysqli_fetch_assoc($cust_q)) {
        $c_room = "room-" . $c['phone'];
        if (!isset($room_list[$c_room])) {
            $room_list[$c_room] = [
                'room_id' => $c_room,
                'sender_name' => $c['name'],
                'sender_phone' => $c['phone']
            ];
        }
    }
}

$first_room = array_key_first($room_list) ?? 'room-081234567890';
$active_room = $_GET['room'] ?? $first_room;
$active_contact = $room_list[$active_room] ?? [
    'sender_name' => 'Pelanggan LilinNawa',
    'sender_phone' => str_replace('room-', '', $active_room)
];

// Tandai pesan customer di room aktif sebagai sudah dibaca oleh Admin
if (!empty($active_room)) {
    @mysqli_query($conn, "UPDATE inbox SET is_read = 1 WHERE room_id = '$active_room' AND sender_role = 'customer'");
}

// Ambil Riwayat Chat Room Terpilih
$messages = mysqli_query($conn, "SELECT * FROM inbox WHERE room_id = '$active_room' ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Inbox &amp; Chat CS - Admin LilinNawa</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="inbox.css">
</head>
<body class="admin-wrapper">
    <?php include '../sidebar.php'; ?>

    <main class="admin-main" style="padding-bottom:20px;">
        
        <div class="page-title-box">
            <h2>Inbox &amp; Customer Support Chat</h2>
            <p>Konsultasi desain custom, konfirmasi data acara, dan tanya jawab langsung dengan customer.</p>
        </div>

        <div class="inbox-container">
            <!-- Sidebar Room Chat -->
            <div class="inbox-rooms">
                <div class="rooms-header">
                    <h4>Percakapan Pelanggan</h4>
                    <small style="color:#6B7280; font-size:11px;"><?= count($room_list) ?> Kontak Aktif</small>
                </div>
                <div class="rooms-list">
                    <?php foreach($room_list as $rid => $c): ?>
                    <a href="Inbox.php?room=<?= urlencode($rid) ?>" class="room-item <?= $active_room === $rid ? 'active' : '' ?>">
                        <div class="room-avatar"><?= strtoupper(substr($c['sender_name'], 0, 1)) ?></div>
                        <div class="room-info">
                            <strong class="room-name"><?= htmlspecialchars($c['sender_name']) ?></strong>
                            <span class="room-phone"><?= htmlspecialchars($c['sender_phone']) ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Area Feed Chat -->
            <div class="inbox-chat-panel">
                <div class="chat-header">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div class="room-avatar"><?= strtoupper(substr($active_contact['sender_name'], 0, 1)) ?></div>
                        <div>
                            <h3 style="font-size:15px; margin:0; color:#701C2F;"><?= htmlspecialchars($active_contact['sender_name']) ?></h3>
                            <span style="font-size:11px; color:#6B7280;"><?= htmlspecialchars($active_contact['sender_phone']) ?></span>
                        </div>
                    </div>

                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $active_contact['sender_phone']) ?>" target="_blank" class="btn-admin btn-gold" style="font-size:11px;">
                        🟢 Buka di WhatsApp
                    </a>
                </div>

                <!-- Chat Feed -->
                <div class="chat-feed">
                    <?php if (mysqli_num_rows($messages) === 0): ?>
                        <div style="text-align:center; padding:40px; color:#9CA3AF; font-size:13px;">
                            💬 Belum ada pesan dalam room ini. Ketikkan sapaan di bawah untuk memulai percakapan.
                        </div>
                    <?php else: ?>
                        <?php while($m = mysqli_fetch_assoc($messages)): 
                            $is_admin_msg = (($m['sender_role'] ?? '') === 'admin' || !empty($m['is_admin']));
                        ?>
                        <div class="chat-bubble <?= $is_admin_msg ? 'bubble-admin' : 'bubble-customer' ?>">
                            <span class="bubble-sender"><?= $is_admin_msg ? '👑 Tim LilinNawa' : htmlspecialchars($m['sender_name']) ?></span>
                            <div class="bubble-text"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                            <span class="bubble-time"><?= date('H:i', strtotime($m['created_at'])) ?></span>
                        </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <!-- Form Kirim Pesan -->
                <form method="POST" class="chat-form">
                    <input type="hidden" name="action" value="send_chat">
                    <input type="hidden" name="room_id" value="<?= htmlspecialchars($active_room) ?>">
                    <input type="hidden" name="recipient_name" value="<?= htmlspecialchars($active_contact['sender_name']) ?>">
                    <input type="hidden" name="recipient_phone" value="<?= htmlspecialchars($active_contact['sender_phone']) ?>">

                    <input type="text" name="message" placeholder="Tuliskan pesan balasan untuk <?= htmlspecialchars($active_contact['sender_name']) ?>..." required autocomplete="off">
                    <button type="submit" class="btn-admin btn-primary">Kirim</button>
                </form>
            </div>
        </div>
    </main>

    <script src="../assets/js/admin.js"></script>
</body>
</html>