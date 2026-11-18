CREATE DATABASE IF NOT EXISTS `lilinawa_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lilinawa_db`;

-- 1. Tabel Pengguna (Admin & Customer)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(25) NOT NULL,
  `role` ENUM('admin', 'customer') DEFAULT 'customer',
  `address` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabel Pengaturan Toko & Profil (Store Settings)
CREATE TABLE IF NOT EXISTS `store_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store_name` VARCHAR(150) NOT NULL DEFAULT 'LilinNawa Digital Invitation',
  `tagline` VARCHAR(255) NOT NULL DEFAULT 'Kreasi Undangan Digital Mewah, Praktis & Elegan',
  `phone_whatsapp` VARCHAR(30) NOT NULL DEFAULT '081252200386',
  `email_official` VARCHAR(100) NOT NULL DEFAULT 'admin@lilinawa.com',
  `address_studio` TEXT NOT NULL,
  `instagram` VARCHAR(100) NOT NULL DEFAULT '@lilinawa.invitation',
  `tiktok` VARCHAR(100) NOT NULL DEFAULT '@lilinawa_digital',
  `bank_name` VARCHAR(50) NOT NULL DEFAULT 'BCA',
  `bank_account_no` VARCHAR(50) NOT NULL DEFAULT '8691522003',
  `bank_account_holder` VARCHAR(100) NOT NULL DEFAULT 'LilinNawa Studio',
  `bank_mandiri_no` VARCHAR(50) NOT NULL DEFAULT '130005220038',
  `bank_bri_no` VARCHAR(50) NOT NULL DEFAULT '001201005220038',
  `qris_image_url` VARCHAR(255) NOT NULL DEFAULT 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=00020101021126570014ID.LINKAJA.WWW0118936009110021522003021522003865204581253033605802ID5915LILINNAWA+STUDIO6007BANDUNG6304D1B8',
  `qris_active` TINYINT(1) DEFAULT 1,
  `operational_status` ENUM('buka', 'tutup') DEFAULT 'buka',
  `operational_hours_weekday` VARCHAR(100) DEFAULT 'Senin - Sabtu: 08:00 - 21:00 WIB',
  `operational_hours_weekend` VARCHAR(100) DEFAULT 'Minggu: 09:00 - 17:00 WIB',
  `announcement_message` TEXT NULL,
  `est_process_time` VARCHAR(100) DEFAULT '1.4 Hari (24 - 48 Jam)',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel Master Layanan (Termasuk Undangan Custom)
CREATE TABLE IF NOT EXISTS `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `service_code` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(150) NOT NULL,
  `type` ENUM('template', 'custom') NOT NULL DEFAULT 'custom',
  `price` DECIMAL(10,2) NOT NULL,
  `discount_price` DECIMAL(10,2) NULL,
  `description` TEXT NOT NULL,
  `features` TEXT NOT NULL,
  `est_days` VARCHAR(50) NOT NULL DEFAULT '2-3 Hari',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabel Katalog Template Undangan
CREATE TABLE IF NOT EXISTS `templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `discount_price` DECIMAL(10,2) NULL,
  `image_url` VARCHAR(255) NOT NULL,
  `preview_url` VARCHAR(2048) NULL DEFAULT NULL,
  `description` TEXT,
  `features` TEXT,
  `is_active` TINYINT(1) DEFAULT 1,
  `sales_count` INT DEFAULT 0,
  `rating` DECIMAL(2,1) DEFAULT 5.0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabel Pesanan (Orders) - Tanpa Data Transaksi Dulu Sesuai Permintaan
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(30) NOT NULL UNIQUE,
  `user_id` INT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_email` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(25) NOT NULL,
  `type` ENUM('template', 'custom') NOT NULL DEFAULT 'template',
  `template_id` INT NULL,
  `template_name` VARCHAR(150) NULL,
  `status` ENUM('pilih_template', 'konsultasi', 'data_terkirim', 'diproses', 'undangan_jadi', 'dikirim', 'dibatalkan') DEFAULT 'pilih_template',
  `step_index` INT DEFAULT 0,
  `total_price` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('pending', 'lunas') DEFAULT 'pending',
  `result_link` VARCHAR(255) NULL,
  `refund_status` ENUM('none', 'diajukan', 'disetujui', 'diproses_transfer', 'selesai', 'ditolak') DEFAULT 'none',
  `refund_amount` DECIMAL(10,2) NULL,
  `cancel_reason` VARCHAR(255) NULL,
  `cancel_details` TEXT NULL,
  `cancelled_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`template_id`) REFERENCES `templates`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabel Detail Rincian Acara Undangan
CREATE TABLE IF NOT EXISTS `order_event_details` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `kategori_acara` VARCHAR(50) DEFAULT 'Pernikahan',
  `nama_acara` VARCHAR(150) NOT NULL,
  `nama_mempelai_pria` VARCHAR(100) NULL,
  `nama_mempelai_wanita` VARCHAR(100) NULL,
  `nama_orang_tua` TEXT NULL,
  `tanggal_acara` DATE NULL,
  `waktu_acara` VARCHAR(100) NULL,
  `waktu_acara_2` VARCHAR(100) NULL,
  `lokasi_acara` VARCHAR(200) NULL,
  `alamat_lengkap` TEXT NULL,
  `link_google_maps` VARCHAR(255) NULL,
  `info_khusus_1` VARCHAR(150) NULL,
  `info_khusus_2` VARCHAR(150) NULL,
  `catatan_khusus` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabel Invoice Pembelian Resmi
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `order_id` INT NOT NULL,
  `user_id` INT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_email` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(25) NOT NULL,
  `item_description` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'QRIS / Transfer Bank',
  `payment_status` ENUM('unpaid', 'paid', 'cancelled') DEFAULT 'unpaid',
  `paid_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Tabel Ulasan & Testimoni Pelanggan
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(30) NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `template_name` VARCHAR(150) NOT NULL,
  `rating` INT DEFAULT 5,
  `comment` TEXT NOT NULL,
  `is_published` TINYINT(1) DEFAULT 1,
  `admin_reply` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Tabel Room Chat Inbox CS
CREATE TABLE IF NOT EXISTS `inbox` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `room_id` VARCHAR(50) NOT NULL,
  `order_number` VARCHAR(30) NULL,
  `sender_name` VARCHAR(100) NOT NULL,
  `sender_phone` VARCHAR(25) NOT NULL,
  `recipient_phone` VARCHAR(25) NULL,
  `sender_role` ENUM('customer', 'admin') DEFAULT 'customer',
  `is_admin` TINYINT(1) DEFAULT 0,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`room_id`),
  INDEX (`sender_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Tabel Pengajuan Refund Pelanggan
CREATE TABLE IF NOT EXISTS `refund_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `order_number` VARCHAR(30) NOT NULL,
  `user_id` INT NULL,
  `customer_name` VARCHAR(100) NOT NULL,
  `customer_phone` VARCHAR(25) NOT NULL,
  `customer_email` VARCHAR(100) NULL,
  `refund_amount` DECIMAL(10,2) NOT NULL,
  `bank_name` VARCHAR(50) NOT NULL,
  `account_number` VARCHAR(50) NOT NULL,
  `account_name` VARCHAR(100) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `notes` TEXT NULL,
  `status` ENUM('diajukan', 'disetujui', 'diproses_transfer', 'selesai', 'ditolak') DEFAULT 'diajukan',
  `admin_notes` TEXT NULL,
  `transfer_proof` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `processed_at` TIMESTAMP NULL,
  `completed_at` TIMESTAMP NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- DATA SEEDING AWAL (MASTER DATA & AKUN, TANPA DATA TRANSAKSI)
-- ========================================================

-- Akun Default:
-- Admin: admin@lilinawa.com / admin123 (No WA: 081252200386)
-- Customer: dimas.aditya@gmail.com / user123 (No WA: 081234567890)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `phone`, `role`, `address`) VALUES
(1, 'Administrator LilinNawa', 'admin@lilinawa.com', MD5('admin123'), '081252200386', 'admin', 'Jl. Terusan Kiara Condong No. 45, Bandung, Jawa Barat'),
(2, 'Dimas Aditya', 'dimas.aditya@gmail.com', MD5('user123'), '081234567890', 'customer', 'Jl. Sukajadi No. 45, Bandung, Jawa Barat')
ON DUPLICATE KEY UPDATE `phone`=VALUES(`phone`);

-- Pengaturan Toko & Profil Resmi LilinNawa
INSERT INTO `store_settings` (
  `id`, `store_name`, `tagline`, `phone_whatsapp`, `email_official`, `address_studio`, 
  `instagram`, `tiktok`, `bank_name`, `bank_account_no`, `bank_account_holder`, 
  `bank_mandiri_no`, `bank_bri_no`, `operational_status`, `operational_hours_weekday`, 
  `operational_hours_weekend`, `announcement_message`, `est_process_time`
) VALUES (
  1, 
  'LilinNawa Digital Invitation', 
  'Kreasi Undangan Digital Mewah, Praktis & Elegan', 
  '081252200386', 
  'admin@lilinawa.com', 
  'Jl. Terusan Kiara Condong No. 45, Bandung, Jawa Barat', 
  '@lilinawa.invitation', 
  '@lilinawa_digital', 
  'BCA', 
  '8691522003', 
  'LilinNawa Studio', 
  '130005220038', 
  '001201005220038', 
  'buka', 
  'Senin - Sabtu: 08:00 - 21:00 WIB', 
  'Minggu: 09:00 - 17:00 WIB', 
  'Selamat datang di LilinNawa! Studio kami aktif melayani pembuatan undangan digital & custom.', 
  '1.4 Hari (24 - 48 Jam)'
)
ON DUPLICATE KEY UPDATE `phone_whatsapp`='081252200386';

-- Master Layanan Undangan Custom
INSERT INTO `services` (`id`, `service_code`, `title`, `type`, `price`, `discount_price`, `description`, `features`, `est_days`, `is_active`) VALUES
(1, 'SRV-CUSTOM-01', 'Paket Undangan Custom Eksklusif', 'custom', 199000, 249000, 'Layanan pembuatan desain undangan digital custom dari awal sesuai konsep, palet warna, dan referensi moodboard pengantin.', 'Desain Custom Dari Nol, 3x Revisi Minor, Background Musik Bebas Request, RSVP WhatsApp Interaktif, Google Maps & Petunjuk Arah, Countdown Timer, Galeri 10 Foto', '2 Hari', 1),
(2, 'SRV-CUSTOM-02', 'Paket Undangan Custom Luxury VIP', 'custom', 299000, 399000, 'Layanan undangan digital tingkat master dengan efek wax seal animasi, video background, QR Code amplop digital dinamis, dan prioritas antrean.', 'Bebas Konsep Sepuasnya, Unlimited Revisi Minor, Animasi Wax Seal Monogram Khusus, Amplop Digital Dinamis Multi-Rekening, Live Streaming Embed, Prioritas Pengerjaan Kilat 24 Jam', '1 Hari', 1)
ON DUPLICATE KEY UPDATE `price`=VALUES(`price`);

-- Master Katalog Template
INSERT INTO `templates` (`id`, `title`, `category`, `price`, `discount_price`, `image_url`, `preview_url`, `description`, `features`, `is_active`, `sales_count`, `rating`) VALUES
(1, 'Elegant Maroon Royal', 'Pernikahan', 150000, 99000, 'https://images.unsplash.com/photo-1519741497674-611481863552', 'https://demo-lilinawa.my.id/preview/elegant-maroon', 'Desain mewah maroon floral gold dengan RSVP WhatsApp dan Google Maps.', 'RSVP WhatsApp, Google Maps, Countdown Timer, Background Music', 1, 142, 5.0),
(2, 'Minimalist Sage Garden', 'Pernikahan', 140000, 89000, 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc', 'https://demo-lilinawa.my.id/preview/sage-garden', 'Nuansa botanical sage green tenang dan estetik.', 'RSVP WhatsApp, Gallery 10 Foto, Love Story Timeline, Amplop Digital', 1, 98, 4.9),
(3, 'Classic Monogram Luxe', 'Pernikahan', 175000, 119000, 'https://images.unsplash.com/photo-1469371670807-013ccf25f16a', 'https://demo-lilinawa.my.id/preview/classic-monogram', 'Wax Seal Digital monogram premium berkelas.', 'Wax Seal, Musik Syahdu, Buku Tamu Digital', 1, 87, 5.0),
(4, 'Golden Sweet Seventeen', 'Ulang Tahun', 120000, 75000, 'https://images.unsplash.com/photo-1513151233558-d860c5398176', 'https://demo-lilinawa.my.id/preview/sweet-seventeen', 'Undangan ulang tahun ke-17 ceria dengan efek glitter dan musik pesta.', 'Music Autoplay, Dress Code, RSVP, Ucapan Doa Online', 1, 64, 4.8),
(5, 'Neon Glow Birthday Bash', 'Ulang Tahun', 125000, 79000, 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30', 'https://demo-lilinawa.my.id/preview/neon-glow', 'Tema pesta modern seru dan interaktif dengan hitung mundur pesta.', 'Countdown Acara, Rundown Pesta, Link Waze & Maps', 1, 45, 4.7),
(6, 'Bintang Nusantara Khitanan', 'Khitanan', 100000, 69000, 'https://images.unsplash.com/photo-1576092768241-dec231879fc3', 'https://demo-lilinawa.my.id/preview/khitanan-nusantara', 'Tasyakuran khitanan buah hati hangat dan nuansa islami nusantara.', 'Doa Khitan, Peta Lokasi, Amplop Berkah Digital', 1, 52, 5.0),
(7, 'Sweet Blessing Aqiqah', 'Aqiqah', 99000, 65000, 'https://images.unsplash.com/photo-1515488042361-ee00e0ddd4e4', 'https://demo-lilinawa.my.id/preview/aqiqah-blessing', 'Undangan syukuran aqiqah bayi dengan palet pastel lembut.', 'Biodata Bayi, Doa Keluarga, Galeri Foto Bayi Lucu', 1, 38, 4.9),
(8, 'Golden Honor Graduation', 'Wisuda', 110000, 79000, 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1', 'https://demo-lilinawa.my.id/preview/golden-graduation', 'Undangan syukuran kelulusan & wisuda berkelas.', 'Galeri Wisudawan, Rundown Selebrasi, Ucapan Selamat', 1, 40, 4.9),
(9, 'Corporate Gala & Exhibition', 'Syukuran', 199000, 149000, 'https://images.unsplash.com/photo-1511578314322-379afb476865', 'https://demo-lilinawa.my.id/preview/corporate-gala', 'Undangan profesional seminar dan pameran resmi korporat.', 'QR Code Tiket, Rundown Acara, Profil Pembicara', 1, 29, 4.8)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);


-- ========================================================

-- ========================================================
-- DATA AWAL SIMULASI PESANAN & PENGAJUAN REFUND LILINNAWA
-- ========================================================

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `customer_name`, `customer_email`, `customer_phone`, `type`, `template_id`, `template_name`, `status`, `step_index`, `total_price`, `payment_status`, `result_link`, `refund_status`, `refund_amount`, `cancel_reason`) VALUES (1, '#LN001', 2, 'Dimas Aditya', 'dimas.aditya@gmail.com', '081234567890', 'template', 1, 'Royal Java Gold', 'undangan_jadi', 4, 149000, 'lunas', 'https://lilinawa.my.id/u/dimas-anissa', 'none', 0, NULL) ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);
INSERT INTO `order_event_details` (`id`, `order_id`, `kategori_acara`, `nama_acara`, `nama_mempelai_pria`, `nama_mempelai_wanita`, `nama_orang_tua`, `tanggal_acara`, `waktu_acara`, `waktu_acara_2`, `lokasi_acara`, `alamat_lengkap`, `link_google_maps`, `catatan_khusus`) VALUES (1, 1, 'Pernikahan', 'The Wedding of Dimas & Anissa', 'Dimas Aditya Pratama, S.T.', 'Anissa Rahmawati, S.Ked', 'Bpk. Hendra & Ibu Rina | Bpk. Gunawan & Ibu Maya', '2026-11-20', '08:00 - 10:00 WIB', '11:00 - 14:00 WIB', 'Gedung Sasana Budaya Ganesha (Sabuga)', 'Jl. Tamansari No. 73, Kota Bandung, Jawa Barat', 'https://maps.google.com/?q=Sabuga+Bandung', 'Lagu latar Payung Teduh - Akad dan tema warna rose gold mewah.') ON DUPLICATE KEY UPDATE `nama_acara`=VALUES(`nama_acara`);
INSERT INTO `orders` (`id`, `order_number`, `user_id`, `customer_name`, `customer_email`, `customer_phone`, `type`, `template_id`, `template_name`, `status`, `step_index`, `total_price`, `payment_status`, `result_link`, `refund_status`, `refund_amount`, `cancel_reason`) VALUES (2, '#LN002', 2, 'Dimas Aditya', 'dimas.aditya@gmail.com', '081234567890', 'template', 2, 'Minimalist Sage Garden', 'konsultasi', 1, 99000, 'pending', '', 'none', 0, NULL) ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);
INSERT INTO `order_event_details` (`id`, `order_id`, `kategori_acara`, `nama_acara`, `nama_mempelai_pria`, `nama_mempelai_wanita`, `nama_orang_tua`, `tanggal_acara`, `waktu_acara`, `lokasi_acara`, `alamat_lengkap`) VALUES (2, 2, 'Pernikahan', 'Pernikahan Dimas & Anissa', 'Dimas Aditya', 'Anissa Rahmawati', '', '2026-12-15', '09:00 WIB', 'Bandung Creative Hub', 'Jl. Laswi No. 7, Kota Bandung') ON DUPLICATE KEY UPDATE `nama_acara`=VALUES(`nama_acara`);
INSERT INTO `orders` (`id`, `order_number`, `user_id`, `customer_name`, `customer_email`, `customer_phone`, `type`, `template_id`, `template_name`, `status`, `step_index`, `total_price`, `payment_status`, `result_link`, `refund_status`, `refund_amount`, `cancel_reason`) VALUES (3, '#LN003', 2, 'Dimas Aditya', 'dimas.aditya@gmail.com', '081234567890', 'template', 5, 'Pastel Sweet Birthday', 'data_terkirim', 2, 89000, 'lunas', '', 'diajukan', 89000, 'Perubahan tanggal / waktu acara keluarga') ON DUPLICATE KEY UPDATE `refund_status`=VALUES(`refund_status`);
INSERT INTO `order_event_details` (`id`, `order_id`, `kategori_acara`, `nama_acara`, `info_khusus_1`, `info_khusus_2`, `tanggal_acara`, `waktu_acara`, `lokasi_acara`, `alamat_lengkap`) VALUES (3, 3, 'Ulang Tahun', 'Sweet 17th Birthday Bash Anissa', 'Anissa Rahmawati', 'Ke-17 (Sweet Seventeen)', '2026-10-30', '18:30 WIB', 'The Parlor Cafe Bandung', 'Jl. Rancakendal Luhur No. 9, Dago, Bandung') ON DUPLICATE KEY UPDATE `nama_acara`=VALUES(`nama_acara`);
INSERT INTO `refund_requests` (`id`, `order_id`, `order_number`, `user_id`, `customer_name`, `customer_phone`, `customer_email`, `refund_amount`, `bank_name`, `account_number`, `account_name`, `reason`, `notes`, `status`, `admin_notes`, `transfer_proof`) VALUES (1, 3, '#LN003', 2, 'Dimas Aditya', '081234567890', 'dimas.aditya@gmail.com', 89000, 'BCA', '8691522003', 'Dimas Aditya', 'Perubahan tanggal / waktu acara keluarga', 'Mohon diproses refund ke rekening BCA di atas, terima kasih banyak admin.', 'diajukan', '', '') ON DUPLICATE KEY UPDATE `status`=VALUES(`status`);
