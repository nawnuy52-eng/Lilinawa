<?php
/**
 * LilinNawa - Script Pembuatan Admin Default
 * File: Config/Create_admin.php
 */
require_once 'Database.php';

$admin_email = 'admin@lilinawa.com';
$admin_pass  = md5('admin123'); // Password default: admin123
$admin_name  = 'Administrator';
$admin_phone = '081252200386';

$check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$admin_email' OR email = 'admin'");
if (mysqli_num_rows($check) == 0) {
    $insert = mysqli_query($conn, "INSERT INTO users (name, email, password, phone, role, address) 
                                   VALUES ('$admin_name', '$admin_email', '$admin_pass', '$admin_phone', 'admin', 'Kantor Pusat LilinNawa')");
    echo "Akun Administrator Berhasil Dibuat! <br>Nama: Administrator<br>Email: admin@lilinawa.com<br>Password: admin123";
} else {
    // Pastikan user Administrator berstatus admin
    mysqli_query($conn, "UPDATE users SET role = 'admin', name = '$admin_name' WHERE email = '$admin_email' OR email = 'admin'");
    echo "Akun Administrator LilinNawa telah aktif dengan hak akses Admin Penuh.";
}
echo "<br><a href='../Login/Login.php'>Menuju Halaman Login</a>";
?>