<?php
/**
 * LilinNawa Digital Invitation - Database Connection
 * File: Config/Database.php
 * Target Database Tunggal: `lilinawa_db`
 */
$host     = "localhost";
$username = "root";
$password = "";
$database = "lilinawa_db";

// Koneksi utama ke database tunggal lilinawa_db
$conn = @mysqli_connect($host, $username, $password, $database);

// Jika database lilinawa_db belum ada di XAMPP, buat otomatis
if (!$conn) {
    $raw_conn = @mysqli_connect($host, $username, $password);
    if ($raw_conn) {
        @mysqli_query($raw_conn, "CREATE DATABASE IF NOT EXISTS `lilinawa_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $conn = @mysqli_connect($host, $username, $password, "lilinawa_db");
    }
}

$db_connected = false;
$db_error = "";

if ($conn) {
    $db_connected = true;
    @mysqli_set_charset($conn, "utf8mb4");
} else {
    $db_error = mysqli_connect_error();
}
?>
