<?php

session_start();
require_once '../../Config/Database.php';


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {

    header("Location: ../../Login/Login.php");
    exit;

}

$alert = '';
$alertType = 'success';

// PROSES TAMBAH, EDIT, STATUS, DAN HAPUS TEMPLATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // TAMBAH / EDIT TEMPLATE
    if ($_POST['action'] === 'save_template') {

        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $price = max(0, (float)($_POST['price'] ?? 0));
        $discount_price = max(0, (float)($_POST['discount_price'] ?? 0));
        $preview_url = trim($_POST['preview_url'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $allowedCategories = [
            'Pernikahan',
            'Khitanan',
            'Ulang Tahun',
            'Wisuda',
            'Syukuran & Acara',
            'Aqiqah'

        ];



        if ($title === '' || !in_array($category, $allowedCategories, true)) {

            $alert = 'Judul dan kategori template wajib diisi dengan benar.';
            $alertType = 'error';

        } elseif ($price <= 0) {

            $alert = 'Harga asli harus lebih besar dari nol.';
            $alertType = 'error';

        } elseif ($preview_url !== '' && (!filter_var($preview_url, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($preview_url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            $alert = 'Link Live Demo harus berupa URL yang valid (http:// atau https://).';
            $alertType = 'error';
        } elseif (strlen($preview_url) > 2048) {
            $alert = 'Link Live Demo maksimal 2048 karakter.';
            $alertType = 'error';
        } else {
            $image_url = '';
            $upload_error = '';

            // Ambil thumbnail lama saat mengedit.
            if ($id > 0) {
                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT image_url FROM templates WHERE id = ? LIMIT 1"
                );


                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'i', $id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $oldRow = mysqli_fetch_assoc($result);
                    mysqli_stmt_close($stmt);

                    if ($oldRow) {
                        $image_url = (string)$oldRow['image_url'];

                    } else {
                        $alert = 'Template yang ingin diedit tidak ditemukan.';
                        $alertType = 'error';
                    }

                } else {
                    $alert = 'Gagal memeriksa data template.';
                    $alertType = 'error';
                }
            }

            // Lanjutkan hanya jika tidak ada error sebelumnya.
            if ($alert === '') {
                // Proses upload gambar jika file dipilih.
                if (

                    isset($_FILES['thumbnail']) &&
                    ($_FILES['thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE)
                        !== UPLOAD_ERR_NO_FILE

                ) {

                    $file = $_FILES['thumbnail'];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $upload_error = 'Upload gambar gagal. Silakan coba kembali.';

                    } elseif ($file['size'] > 5 * 1024 * 1024) {
                        $upload_error = 'Ukuran gambar maksimal 5 MB.';

                    } else {
                        $imageInfo = @getimagesize($file['tmp_name']);
                        $mime = $imageInfo['mime'] ?? '';
                        $allowedTypes = [
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            'image/webp' => 'webp',
                            'image/gif'  => 'gif'
                        ];

                        if (!$imageInfo || !isset($allowedTypes[$mime])) {
                            $upload_error =
                                'Format gambar harus JPG, PNG, WEBP, atau GIF.';

                        } else {
                            $uploadDir = __DIR__ . '/../../uploads/templates/';

                            if (
                                !is_dir($uploadDir) &&
                                !mkdir($uploadDir, 0755, true) &&
                                !is_dir($uploadDir)

                            ) {

                                $upload_error =
                                    'Folder penyimpanan gambar tidak dapat dibuat.';

                            } else {

                                try {
                                    $fileName = 'template_'
                                        . bin2hex(random_bytes(12))
                                        . '.'
                                        . $allowedTypes[$mime];


                                    if (
                                        move_uploaded_file(
                                            $file['tmp_name'],
                                            $uploadDir . $fileName
                                        )
                                    ) {

                                        // Lokasi relatif dari root website.
                                        $image_url =
                                            'uploads/templates/' . $fileName;

                                    } else {
                                        $upload_error =
                                            'Gambar tidak berhasil disimpan ke server.';
                                    }

                                } catch (Throwable $e) {
                                    $upload_error =
                                        'Gagal membuat nama file gambar.';
                                }
                            }
                        }
                    }
                }

                // Validasi akhir gambar.
                if ($upload_error !== '') {
                    $alert = $upload_error;
                    $alertType = 'error';

                } elseif ($image_url === '') {
                    $alert =
                        'Silakan pilih gambar thumbnail untuk template baru.';
                    $alertType = 'error';

                } else {
                    // Simpan data menggunakan prepared statement.
                    if ($id > 0) {
                        $sql = "UPDATE templates
                                SET title = ?,
                                    category = ?,
                                    price = ?,
                                    discount_price = ?,
                                    image_url = ?,
                                    preview_url = ?,
                                    description = ?,
                                   is_active = ?
                                WHERE id = ?";
                        $stmt = mysqli_prepare($conn, $sql);

                        if ($stmt) {
                            mysqli_stmt_bind_param(
                                $stmt,
                                'ssddsssii',
                                $title,
                                $category,
                                $price,
                                $discount_price,
                                $image_url,
                                $preview_url,
                                $description,
                                $is_active,
                                $id
                            );

                            $saved = mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);

                            if ($saved) {
                                $alert = 'Template berhasil diperbarui!';
                                $alertType = 'success';

                            } else {
                                $alert = 'Template gagal diperbarui.';
                                $alertType = 'error';
                            }

                        } else {
                            $alert = 'Gagal menyiapkan pembaruan template.';
                            $alertType = 'error';
                        }

                    } else {
                        $sql = "INSERT INTO templates
                                (title, category, price, discount_price,
                                 image_url, preview_url, description, is_active)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $sql);

                        if ($stmt) {
                            mysqli_stmt_bind_param(
                                $stmt,
                                'ssddsssi',
                                $title,
                                $category,
                                $price,
                                $discount_price,
                                $image_url,
                                $preview_url,
                                $description,
                                $is_active
                            );

                            $saved = mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);

                            if ($saved) {
                                $alert = 'Template baru berhasil ditambahkan!';
                                $alertType = 'success';

                            } else {
                                $alert = 'Template gagal ditambahkan.';
                                $alertType = 'error';
                            }

                        } else {
                            $alert = 'Gagal menyiapkan penyimpanan template.';
                            $alertType = 'error';
                        }
                    }
                }
            }
        }

    // AKTIFKAN / NONAKTIFKAN
    } elseif ($_POST['action'] === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        $curr = intval($_POST['curr'] ?? 0);

        if ($id > 0) {

            $newStatus = $curr ? 0 : 1;
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE templates SET is_active = ? WHERE id = ?"
            );

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $newStatus, $id);
                $saved = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $alert = $saved
                    ? 'Status template berhasil diubah!'
                    : 'Status template gagal diubah.';
                $alertType = $saved ? 'success' : 'error';
            }
        }

       // HAPUS TEMPLATE
    } elseif ($_POST['action'] === 'delete_template') {
        $id = intval($_POST['id'] ?? 0);

        if ($id > 0) {
            // Pertahankan perilaku relasi order seperti kode sebelumnya.
            mysqli_query(
                $conn,
                "UPDATE orders SET template_id = NULL WHERE template_id = $id"
            );

            $stmt = mysqli_prepare(
                $conn,
                "DELETE FROM templates WHERE id = ?"
            );

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                $deleted = mysqli_stmt_execute($stmt);
                $affected = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($deleted && $affected > 0) {
                    $alert = 'Template berhasil dihapus dari katalog.';
                    $alertType = 'success';

                } else {
                    // Jika gagal dihapus, coba nonaktifkan.
                    $fallback = mysqli_prepare(
                        $conn,
                        "UPDATE templates SET is_active = 0 WHERE id = ?"
                    );

                    if ($fallback) {
                        mysqli_stmt_bind_param($fallback, 'i', $id);
                        mysqli_stmt_execute($fallback);
                        mysqli_stmt_close($fallback);

                    }

                    $alert = 'Template tidak dapat dihapus dan dicoba dinonaktifkan.';
                    $alertType = 'error';

                }
            }
        }
    }
}


// Ambil data template.
$templates = mysqli_query(
    $conn,
    "SELECT * FROM templates ORDER BY id DESC"
);

if (!$templates) {
    die('Gagal mengambil data katalog template.');
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Katalog Template - Admin LilinNawa</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

</head>



<body class="admin-wrapper">



<?php include '../sidebar.php'; ?>



<main class="admin-main">
    <!-- HEADER -->

    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:20px;flex-wrap:wrap;">

        <div>

            <h2 style="font-size:24px;font-weight:800;color:#701C2F;margin:0 0 6px;">

                Katalog &amp; Desain Template

            </h2>

            <p style="color:#6B7280;font-size:13px;margin:0;">

                Atur daftar tema undangan digital yang tersedia untuk dipilih oleh calon pengantin.

            </p>

        </div>



        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-left:auto;">

            <a href="Layanan Custom.php"

               class="btn-admin btn-secondary"

               style="padding:12px 18px;font-weight:700;border-radius:12px;border:1.5px solid #E5D7B7;background:#FAF3DE;color:#701C2F;text-decoration:none;display:flex;align-items:center;gap:8px;">

                ✨ Buka Halaman Layanan Custom

            </a>



            <button type="button"

                    class="btn-admin btn-primary"

                    onclick="openAddTemplateModal()"

                    style="padding:12px 22px;font-weight:700;border-radius:12px;background:#701C2F;color:#FFF;border:none;cursor:pointer;">

                ➕ Tambah Template Baru

            </button>

        </div>

    </div>



    <!-- NOTIFIKASI -->

    <?php if ($alert !== ''): ?>

        <div class="alert-box"

             role="status"

             style="background:<?= $alertType === 'success' ? '#D1FAE5' : '#FEE2E2' ?>;

                    color:<?= $alertType === 'success' ? '#065F46' : '#991B1B' ?>;

                    padding:12px;border-radius:12px;margin-bottom:20px;font-weight:600;">

            <?= htmlspecialchars($alert, ENT_QUOTES, 'UTF-8') ?>

        </div>

    <?php endif; ?>



    <!-- PENCARIAN DAN FILTER -->

    <div style="background:#FFFFFF;border:1px solid #E5E7EB;border-radius:14px;padding:14px 18px;margin-bottom:24px;display:flex;gap:14px;align-items:center;flex-wrap:wrap;">



        <div style="position:relative;flex:1;min-width:240px;">

            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9CA3AF;">🔍</span>



            <input type="text"

                   id="tplLiveSearch"

                   placeholder="Cari template berdasarkan judul atau kategori..."

                   oninput="filterTemplatesLive()"

                   style="width:100%;padding:10px 14px 10px 38px;border:1px solid #D1D5DB;border-radius:10px;box-sizing:border-box;">

        </div>



        <select id="tplCategoryFilter"

                onchange="filterTemplatesLive()"

                style="padding:10px 14px;border:1px solid #D1D5DB;border-radius:10px;background:#FAF3DE;color:#701C2F;font-weight:700;">

            <option value="">Semua Kategori</option>

            <option value="Pernikahan">Pernikahan</option>

            <option value="Khitanan">Khitanan</option>

            <option value="Ulang Tahun">Ulang Tahun</option>

            <option value="Wisuda">Wisuda</option>

            <option value="Syukuran & Acara">Syukuran & Acara</option>

            <option value="Aqiqah">Aqiqah</option>

        </select>

    </div>



    <!-- GRID KATALOG -->

    <div id="templatesGridContainer"

         style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">



        <?php while ($tpl = mysqli_fetch_assoc($templates)): ?>



            <?php

            $templateJson = json_encode(

                $tpl,

                JSON_HEX_APOS |

                JSON_HEX_QUOT |

                JSON_HEX_TAG |

                JSON_HEX_AMP |

                JSON_INVALID_UTF8_SUBSTITUTE

            );



            $imagePath = (string)($tpl['image_url'] ?? '');



            if (preg_match('~^https?://~i', $imagePath)) {

                $thumbnailSrc = $imagePath;

            } else {

                $thumbnailSrc = '../../' . ltrim($imagePath, '/');

            }

            ?>



            <div class="template-card-item"

                 data-title="<?= htmlspecialchars($tpl['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"

                 data-category="<?= htmlspecialchars($tpl['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>"

                 data-desc="<?= htmlspecialchars($tpl['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"

                 style="background:#FFFFFF;border:1px solid #E5E7EB;border-radius:18px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,0.03);display:flex;flex-direction:column;">



                <div style="position:relative;height:180px;background:#FAF3DE;">

                    <img

                        src="<?= htmlspecialchars($thumbnailSrc, ENT_QUOTES, 'UTF-8') ?>"

                        alt="<?= htmlspecialchars($tpl['title'] ?? 'Thumbnail template', ENT_QUOTES, 'UTF-8') ?>"

                        style="width:100%;height:100%;object-fit:cover;"

                        onerror="this.onerror=null;this.src='http&#x73;*:*//images.unsplash.com/photo-1519225421980-715cb0215aed?w=600&auto=format&fit=crop&q=80';">



                    <span class="admin-badge <?= $tpl['is_active'] ? 'admin-badge-success' : 'admin-badge-danger' ?>"

                          style="position:absolute;top:12px;right:12px;">

                        <?= $tpl['is_active'] ? '🟢 Aktif' : '🔴 Nonaktif' ?>

                    </span>



                    <span style="position:absolute;bottom:12px;left:12px;background:rgba(0,0,0,0.7);color:#FFF;font-size:10px;font-weight:700;padding:4px 8px;border-radius:6px;text-transform:uppercase;">

                        <?= htmlspecialchars($tpl['category'] ?? '', ENT_QUOTES, 'UTF-8') ?>

                    </span>

                </div>



                <div style="padding:16px;flex:1;display:flex;flex-direction:column;justify-content:space-between;">



                    <div>

                        <h3 style="font-family:'Playfair Display',serif;font-size:17px;color:#701C2F;">

                            <?= htmlspecialchars($tpl['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>

                        </h3>



                        <div style="margin:6px 0;">

                            <span style="font-size:16px;font-weight:800;color:#701C2F;">

                                Rp <?= number_format(

                                    (float)(($tpl['discount_price'] > 0)

                                        ? $tpl['discount_price']

                                        : $tpl['price']),

                                    0,

                                    ',',

                                    '.'

                                ) ?>

                            </span>



                            <?php if (

                                $tpl['discount_price'] > 0 &&

                                $tpl['discount_price'] < $tpl['price']

                            ): ?>

                                <span style="font-size:12px;color:#9CA3AF;text-decoration:line-through;margin-left:6px;">

                                    Rp <?= number_format((float)$tpl['price'], 0, ',', '.') ?>

                                </span>

                            <?php endif; ?>

                        </div>



                        <p style="font-size:12px;color:#6B7280;line-height:1.5;margin-bottom:14px;">

                            <?= htmlspecialchars(

                                $tpl['description'] ?: 'Template undangan digital premium LilinNawa.',

                                ENT_QUOTES,

                                'UTF-8'

                            ) ?>

                        </p>

                    </div>



                    <!-- AKSI TEMPLATE -->

                    <div style="display:flex;gap:6px;border-top:1px solid #F3F4F6;padding-top:12px;">



                        <button

                            type="button"

                            class="btn-admin btn-secondary"

                            style="flex:1;justify-content:center;font-size:11px;padding:6px 8px;"

                            data-template="<?= htmlspecialchars($templateJson ?: '{}', ENT_QUOTES, 'UTF-8') ?>"

                            onclick="handleEditTemplate(this)">

                            ✏️ Edit

                        </button>



                        <form method="POST" style="flex:1;">

                            <input type="hidden" name="action" value="toggle_status">

                            <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">

                            <input type="hidden" name="curr" value="<?= (int)$tpl['is_active'] ?>">



                            <button type="submit"

                                    class="btn-admin <?= $tpl['is_active'] ? 'btn-secondary' : 'btn-primary' ?>"

                                    style="width:100%;justify-content:center;font-size:11px;padding:6px 8px;">

                                <?= $tpl['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>

                            </button>

                        </form>



                        <form method="POST"

                              onsubmit="return confirm('Apakah Anda yakin ingin menghapus template ini?');"

                              style="flex:0.8;">



                            <input type="hidden" name="action" value="delete_template">

                            <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">



                            <button type="submit"

                                    class="btn-admin btn-danger"

                                    style="width:100%;justify-content:center;font-size:11px;padding:6px 8px;background:#DC2626;color:#FFF;border:none;border-radius:8px;cursor:pointer;"

                                    title="Hapus Template">

                                🗑️ Hapus

                            </button>

                        </form>

                    </div>

                </div>

            </div>



        <?php endwhile; ?>



    </div>



    <div id="tplEmptyNotice"

         style="display:none;text-align:center;padding:40px;background:#FFF;border-radius:14px;color:#9CA3AF;margin-top:16px;">

        Tidak ada template yang cocok dengan pencarian atau filter.

    </div>



    <!-- MODAL TAMBAH / EDIT -->

    <div id="modalTemplate" class="modal-overlay">



        <div class="modal-content">



            <div class="modal-header">

                <h3 id="tplModalTitle">Tambah Template Baru</h3>



                <button type="button"

                        class="btn-close-modal"

                        onclick="closeTemplateModal()">

                    ✕

                </button>

            </div>



            <form id="templateForm"

                  method="POST"

                  enctype="multipart/form-data">



                <div class="modal-body">



                    <input type="hidden" name="action" value="save_template">

                    <input type="hidden" name="id" id="tpl_id" value="0">



                    <div style="display:flex;flex-direction:column;gap:12px;">



                        <div>

                            <label for="tpl_title" style="font-size:12px;font-weight:bold;display:block;margin-bottom:4px;">

                                Nama / Judul Template

                            </label>



                            <input type="text"

                                   name="title"

                                   id="tpl_title"

                                   required

                                   style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box;">

                        </div>



                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">



                            <div>

                                <label for="tpl_category" style="font-size:12px;font-weight:bold;display:block;margin-bottom:4px;">

                                    Kategori

                                </label>



                                <select name="category"

                                        id="tpl_category"

                                        required

                                        style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box;">

                                    <option value="Pernikahan">Pernikahan</option>

                                    <option value="Khitanan">Khitanan</option>

                                    <option value="Ulang Tahun">Ulang Tahun</option>

                                    <option value="Wisuda">Wisuda</option>

                                    <option value="Syukuran & Acara">Syukuran & Acara</option>

                                    <option value="Aqiqah">Aqiqah</option>

                                </select>

                            </div>



                            <div>

                                <label style="font-size:12px;font-weight:bold;display:block;margin-bottom:4px;">

                                    Status Aktif

                                </label>



                                <label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-size:13px;">

                                    <input type="checkbox"

                                           name="is_active"

                                           id="tpl_is_active"

                                           value="1"

                                           checked>

                                    Tampilkan di Website

                                </label>

                            </div>

                        </div>



                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">



                            <div>

                                <label for="tpl_price" style="font-size:12px;font-weight:bold;display:block;margin-bottom:4px;">

                                    Harga Asli (Rp)

                                </label>



                                <input type="number"

                                       name="price"

                                       id="tpl_price"

                                       min="1"

                                       step="1"

                                       required

                                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box;">

                            </div>



                            <div>

                                <label for="tpl_discount_price" style="font-size:12px;font-weight:bold;display:block;margin-bottom:4px;">

                                    Harga Promo / Diskon (Rp)

                                </label>



                                <input type="number"

                                       name="discount_price"

                                       id="tpl_discount_price"

                                       min="0"

                                       step="1"

                                       value="0"

                                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box;">

                            </div>

                        </div>



                        <!-- UPLOAD THUMBNAIL -->

                        <div>

                            <label for="tpl_thumbnail"

                                   style="font-size:12px;font-weight:bold;display:block;margin-bottom:4px;">

                                Upload Gambar Thumbnail

                            </label>



                            <input type="file"

                                   name="thumbnail"

                                   id="tpl_thumbnail"

                                   accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"

                                   required

                                   style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;background:#fff;box-sizing:border-box;">



                            <small id="tpl_thumbnail_help"

                                   style="display:block;color:#6B7280;margin-top:5px;">

                                Pilih gambar dari perangkat. Format JPG, PNG, WEBP, atau GIF. Maksimal 5 MB.

                            </small>



                            <img id="tpl_thumbnail_preview"

                                 src=""

                                 alt="Preview thumbnail"

                                 style="display:none;max-width:180px;max-height:130px;object-fit:cover;margin-top:10px;border-radius:8px;border:1px solid #E5D7B7;">



                            <button type="button"

                                    id="tpl_remove_preview"

                                    onclick="clearNewThumbnail()"

                                    style="display:none;margin-top:6px;padding:6px 10px;border:1px solid #D1D5DB;border-radius:6px;background:#fff;cursor:pointer;">

                                Batalkan gambar baru

                            </button>

                        </div>



                        <div>

                            <label for="tpl_preview_url"
                                   style="font-size:12px;font-weight:bold;display:block;margin-bottom:4px;">
                                Link Live Demo
                            </label>

                            <input type="url"
                                   name="preview_url"
                                   id="tpl_preview_url"
                                   maxlength="2048"
                                   placeholder="https://domain.com/demo/template"
                                   style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box;">

                            <small style="display:block;color:#6B7280;margin-top:5px;">
                                Masukkan URL halaman demo template. Kosongkan jika belum tersedia.
                            </small>
                        </div>

                        <div>

                            <label for="tpl_description"

                                   style="font-size:12px;font-weight:bold;display:block;margin-bottom:4px;">

                                Deskripsi Singkat

                            </label>



                            <textarea name="description"

                                      id="tpl_description"

                                      rows="3"

                                      style="width:100%;padding:10px;border:1px solid #ccc;border-radius:8px;box-sizing:border-box;"></textarea>

                        </div>



                    </div>

                </div>



                <div class="modal-footer">

                    <button type="button"

                            class="btn-admin btn-secondary"

                            onclick="closeTemplateModal()">

                        Batal

                    </button>



                    <button type="submit" class="btn-admin btn-primary">

                        Simpan Template

                    </button>

                </div>



            </form>

        </div>

    </div>



</main>



<!-- PENTING: tag script harus ditutup dengan </script> yang normal -->

<script src="../assets/js/admin.js"></script>



<script>

(function () {

    'use strict';



    var currentObjectUrl = null;



    function getElement(id) {

        return document.getElementById(id);

    }



    // =================================================

    // MEMBUKA DAN MENUTUP MODAL

    // =================================================

    function showTemplateModal() {

        var modal = getElement('modalTemplate');



        if (!modal) {

            alert('Modal template tidak ditemukan.');

            return;

        }



        // Mendukung modal CSS yang menggunakan class "show".

        modal.style.display = 'flex';

        modal.classList.add('show');

        document.body.classList.add('modal-open');

    }



    function hideTemplateModal() {

        var modal = getElement('modalTemplate');



        if (!modal) return;



        modal.classList.remove('show');

        modal.style.display = 'none';

        document.body.classList.remove('modal-open');

    }



    window.openAddTemplateModal = function () {

        var form = getElement('templateForm');



        if (form) form.reset();



        getElement('tplModalTitle').textContent = 'Tambah Template Baru';

        getElement('tpl_id').value = '0';

        getElement('tpl_title').value = '';

        getElement('tpl_category').value = 'Pernikahan';

        getElement('tpl_price').value = '149000';

        getElement('tpl_discount_price').value = '99000';

        getElement('tpl_preview_url').value = '';
        getElement('tpl_description').value = '';

        getElement('tpl_is_active').checked = true;



        getElement('tpl_thumbnail').value = '';

        getElement('tpl_thumbnail').required = true;



        getElement('tpl_thumbnail_help').textContent =

            'Pilih gambar dari perangkat. Format JPG, PNG, WEBP, atau GIF. Maksimal 5 MB.';



        getElement('tpl_thumbnail_preview').removeAttribute('src');

        getElement('tpl_thumbnail_preview').style.display = 'none';

        getElement('tpl_remove_preview').style.display = 'none';



        revokePreviewUrl();

        showTemplateModal();

    };



    // =================================================

    // TOMBOL EDIT

    // =================================================

    window.handleEditTemplate = function (button) {

        try {

            var json = button.getAttribute('data-template');



            if (!json) {

                throw new Error('Data template kosong.');

            }



            var template = JSON.parse(json);

            window.openEditTemplateModal(template);



        } catch (error) {

            console.error('Gagal membuka data template:', error);

            alert('Data template gagal dibaca. Silakan periksa Console browser.');

        }

    };



    window.openEditTemplateModal = function (tpl) {

        if (!tpl || !tpl.id) {

            alert('Data template tidak valid.');

            return;

        }



        getElement('tplModalTitle').textContent =

            'Edit Template: ' + (tpl.title || '');



        getElement('tpl_id').value = tpl.id;

        getElement('tpl_title').value = tpl.title || '';

        getElement('tpl_category').value = tpl.category || 'Pernikahan';

        getElement('tpl_price').value = tpl.price || 0;

        getElement('tpl_discount_price').value = tpl.discount_price || 0;

        getElement('tpl_preview_url').value = tpl.preview_url || '';
        getElement('tpl_description').value = tpl.description || '';

        getElement('tpl_is_active').checked = Number(tpl.is_active) === 1;



        // Saat edit, file baru tidak wajib dipilih.

        getElement('tpl_thumbnail').value = '';

        getElement('tpl_thumbnail').required = false;



        getElement('tpl_thumbnail_help').textContent =

            'Thumbnail saat ini ditampilkan di bawah. Pilih file baru hanya jika ingin menggantinya. Kosongkan jika ingin mempertahankan gambar lama.';



        revokePreviewUrl();



        var preview = getElement('tpl_thumbnail_preview');

        var imagePath = tpl.image_url || '';



        if (imagePath) {

            preview.src = resolveTemplateImage(imagePath);

            preview.style.display = 'block';

        } else {

            preview.removeAttribute('src');

            preview.style.display = 'none';

        }



        getElement('tpl_remove_preview').style.display = 'none';



        showTemplateModal();

    };



    // =================================================

    // RESOLUSI LOKASI GAMBAR

    // =================================================

    function resolveTemplateImage(path) {

        path = String(path || '').trim();



        if (/^(https?:\/\/|data:|blob:)/i.test(path)) {

            return path;

        }



        return '../../' + path.replace(/^\/+/, '');

    }



    // =================================================

    // PREVIEW UPLOAD GAMBAR

    // =================================================

    function revokePreviewUrl() {

        if (currentObjectUrl) {

            URL.revokeObjectURL(currentObjectUrl);

            currentObjectUrl = null;

        }

    }



    window.clearNewThumbnail = function () {

        var input = getElement('tpl_thumbnail');



        input.value = '';



        revokePreviewUrl();



        var id = Number(getElement('tpl_id').value);

        var preview = getElement('tpl_thumbnail_preview');



        if (id > 0) {

            // Kembali menampilkan thumbnail lama saat edit.

            var button = document.querySelector(

                '.template-card-item [data-template="' +

                CSS.escape('') + '"]'

            );



            // Gunakan data template dari kartu berdasarkan ID.

            var cards = document.querySelectorAll(

                '.template-card-item button[data-template]'

            );



            var found = false;



            cards.forEach(function (card) {

                if (found) return;



                try {

                    var tpl = JSON.parse(card.dataset.template);



                    if (Number(tpl.id) === id) {

                        preview.src = resolveTemplateImage(tpl.image_url || '');

                        preview.style.display = tpl.image_url ? 'block' : 'none';

                        found = true;

                    }

                } catch (e) {

                    console.error(e);

                }

            });

        } else {

            preview.removeAttribute('src');

            preview.style.display = 'none';

        }



        getElement('tpl_remove_preview').style.display = 'none';

    };



    document.addEventListener('DOMContentLoaded', function () {

        var input = getElement('tpl_thumbnail');

        var preview = getElement('tpl_thumbnail_preview');



        if (input) {

            input.addEventListener('change', function () {

                var file = input.files && input.files[0];



                if (!file) return;



                var allowed = [

                    'image/jpeg',

                    'image/png',

                    'image/webp',

                    'image/gif'

                ];



                if (file.size > 5 * 1024 * 1024) {

                    alert('Ukuran gambar maksimal 5 MB.');

                    input.value = '';

                    return;

                }



                if (!allowed.includes(file.type)) {

                    alert('Format gambar harus JPG, PNG, WEBP, atau GIF.');

                    input.value = '';

                    return;

                }



                revokePreviewUrl();



                currentObjectUrl = URL.createObjectURL(file);

                preview.src = currentObjectUrl;

                preview.style.display = 'block';



                getElement('tpl_remove_preview').style.display = 'inline-block';

            });

        }



        // Tutup modal jika area luar modal diklik.

        var modal = getElement('modalTemplate');



        if (modal) {

            modal.addEventListener('click', function (event) {

                if (event.target === modal) {

                    hideTemplateModal();

                }

            });

        }



        // Tutup modal dengan tombol Escape.

        document.addEventListener('keydown', function (event) {

            if (event.key === 'Escape' && modal &&

                modal.classList.contains('show')) {

                hideTemplateModal();

            }

        });

    });



    window.closeTemplateModal = hideTemplateModal;



    // Kompatibilitas dengan pemanggilan lama dari admin.js.

    window.openModal = window.openModal || function (id) {

        if (id === 'modalTemplate') showTemplateModal();

    };



    window.closeModal = window.closeModal || function (id) {

        if (id === 'modalTemplate') hideTemplateModal();

    };



    // =================================================

    // PENCARIAN DAN FILTER

    // =================================================

    window.filterTemplatesLive = function () {

        var searchElement = getElement('tplLiveSearch');

        var categoryElement = getElement('tplCategoryFilter');



        var query = (searchElement.value || '').toLowerCase().trim();

        var category = (categoryElement.value || '').toLowerCase().trim();



        var cards = document.querySelectorAll('.template-card-item');

        var visibleCount = 0;



        cards.forEach(function (card) {

            var title = (card.getAttribute('data-title') || '').toLowerCase();

            var cat = (card.getAttribute('data-category') || '').toLowerCase();

            var desc = (card.getAttribute('data-desc') || '').toLowerCase();



            var matchesSearch =

                !query ||

                title.includes(query) ||

                cat.includes(query) ||

                desc.includes(query);



            var matchesCategory = !category || cat === category;



            if (matchesSearch && matchesCategory) {

                card.style.display = 'flex';

                visibleCount++;

            } else {

                card.style.display = 'none';

            }

        });



        var emptyNotice = getElement('tplEmptyNotice');



        if (emptyNotice) {

            emptyNotice.style.display =

                visibleCount === 0 ? 'block' : 'none';

        }

    };



})();

</script>



</body>

</html>
