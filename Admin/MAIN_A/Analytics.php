<?php

session_start();
require_once '../../Config/Database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../Login/Login.php');
    exit;
}

mysqli_set_charset($conn, 'utf8mb4');

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string {
    return 'Rp ' . number_format((float)$value, 0, ',', '.');
}

function runQuery(mysqli $conn, string $sql) {
    $result = mysqli_query($conn, $sql);
    if ($result === false) {
        error_log('Analytics query error: ' . mysqli_error($conn));
        return false;
    }
    return $result;
}

function fetchOne(mysqli $conn, string $sql): array {
    $result = runQuery($conn, $sql);
    if (!$result) {
        return [];
    }
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    return $row ?: [];
}

/* Filter tanggal: format YYYY-MM-DD divalidasi sebelum dipakai ke query. */
$today = date('Y-m-d');
$defaultStart = date('Y-m-01');
$startDate = $_GET['start_date'] ?? $defaultStart;
$endDate = $_GET['end_date'] ?? $today;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !strtotime($startDate)) {
    $startDate = $defaultStart;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || !strtotime($endDate)) {
    $endDate = $today;
}
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$startEsc = mysqli_real_escape_string($conn, $startDate);
$endEsc = mysqli_real_escape_string($conn, $endDate);
$orderDateFilter = "DATE(o.created_at) BETWEEN '{$startEsc}' AND '{$endEsc}'";
$refundDateFilter = "DATE(COALESCE(r.completed_at, r.created_at)) BETWEEN '{$startEsc}' AND '{$endEsc}'";

/* Pendapatan kotor: hanya order yang pembayarannya benar-benar berstatus lunas. */
$grossData = fetchOne(
    $conn,
    "SELECT COUNT(*) AS paid_orders, COALESCE(SUM(o.total_price), 0) AS gross_revenue
     FROM orders o
     WHERE o.payment_status = 'lunas'
       AND {$orderDateFilter}"
);
$paidOrderCount = (int)($grossData['paid_orders'] ?? 0);
$grossRevenue = (float)($grossData['gross_revenue'] ?? 0);

/* Refund hanya mengurangi pendapatan saat status refund_requests sudah 'selesai'. */
$refundData = fetchOne(
    $conn,
    "SELECT COUNT(*) AS completed_refund_count,
            COALESCE(SUM(r.refund_amount), 0) AS completed_refund_total
     FROM refund_requests r
     WHERE r.status = 'selesai'
       AND {$refundDateFilter}"
);
$completedRefundCount = (int)($refundData['completed_refund_count'] ?? 0);
$completedRefundTotal = (float)($refundData['completed_refund_total'] ?? 0);
$netRevenue = $grossRevenue - $completedRefundTotal;
$averageOrderValue = $paidOrderCount > 0 ? $grossRevenue / $paidOrderCount : 0;

/* Statistik status operasional pesanan. */
$statusCounts = [];
$statusResult = runQuery(
    $conn,
    "SELECT status, COUNT(*) AS total
     FROM orders
     WHERE DATE(created_at) BETWEEN '{$startEsc}' AND '{$endEsc}'
     GROUP BY status"
);
if ($statusResult) {
    while ($row = mysqli_fetch_assoc($statusResult)) {
        $statusCounts[$row['status']] = (int)$row['total'];
    }
    mysqli_free_result($statusResult);
}

/* Statistik status pengajuan refund, agar refund yang belum selesai tidak dianggap biaya refund. */
$refundStatusCounts = [];
$refundStatusResult = runQuery(
    $conn,
    "SELECT status, COUNT(*) AS total
     FROM refund_requests
     WHERE DATE(created_at) BETWEEN '{$startEsc}' AND '{$endEsc}'
     GROUP BY status"
);
if ($refundStatusResult) {
    while ($row = mysqli_fetch_assoc($refundStatusResult)) {
        $refundStatusCounts[$row['status']] = (int)$row['total'];
    }
    mysqli_free_result($refundStatusResult);
}

/* Data template terlaris berdasarkan order lunas; refund selesai ditampilkan terpisah
   dan tidak mengubah hitungan jumlah order terjual. */
$templateResult = runQuery(
    $conn,
    "SELECT o.template_name,
            COUNT(*) AS total_sold,
            COALESCE(SUM(o.total_price), 0) AS gross_total
     FROM orders o
     WHERE o.payment_status = 'lunas'
       AND o.template_name IS NOT NULL
       AND o.template_name <> ''
       AND DATE(o.created_at) BETWEEN '{$startEsc}' AND '{$endEsc}'
     GROUP BY o.template_name
     ORDER BY total_sold DESC, gross_total DESC
     LIMIT 5"
);

/* Laporan harian: pendapatan kotor order lunas dikurangi refund yang selesai pada hari tersebut. */
$daily = [];
$dailyOrders = runQuery(
    $conn,
    "SELECT DATE(o.created_at) AS report_date,
            COUNT(*) AS paid_orders,
            COALESCE(SUM(o.total_price), 0) AS gross
     FROM orders o
     WHERE o.payment_status = 'lunas'
       AND DATE(o.created_at) BETWEEN '{$startEsc}' AND '{$endEsc}'
     GROUP BY DATE(o.created_at)"
);
if ($dailyOrders) {
    while ($row = mysqli_fetch_assoc($dailyOrders)) {
        $key = $row['report_date'];
        $daily[$key] = [
            'paid_orders' => (int)$row['paid_orders'],
            'gross' => (float)$row['gross'],
            'refund' => 0.0,
            'refund_count' => 0
        ];
    }
    mysqli_free_result($dailyOrders);
}

$dailyRefunds = runQuery(
    $conn,
    "SELECT DATE(COALESCE(r.completed_at, r.created_at)) AS report_date,
            COUNT(*) AS refund_count,
            COALESCE(SUM(r.refund_amount), 0) AS refund_total
     FROM refund_requests r
     WHERE r.status = 'selesai'
       AND DATE(COALESCE(r.completed_at, r.created_at)) BETWEEN '{$startEsc}' AND '{$endEsc}'
     GROUP BY DATE(COALESCE(r.completed_at, r.created_at))"
);
if ($dailyRefunds) {
    while ($row = mysqli_fetch_assoc($dailyRefunds)) {
        $key = $row['report_date'];
        if (!isset($daily[$key])) {
            $daily[$key] = [
                'paid_orders' => 0,
                'gross' => 0.0,
                'refund' => 0.0,
                'refund_count' => 0
            ];
        }
        $daily[$key]['refund'] = (float)$row['refund_total'];
        $daily[$key]['refund_count'] = (int)$row['refund_count'];
    }
    mysqli_free_result($dailyRefunds);
}
krsort($daily);

/* Ringkasan pesanan dan refund. */
$totalOrdersInPeriod = (int)(fetchOne(
    $conn,
    "SELECT COUNT(*) AS total FROM orders
     WHERE DATE(created_at) BETWEEN '{$startEsc}' AND '{$endEsc}'"
)['total'] ?? 0);

$pendingRefundCount = (int)($refundStatusCounts['diajukan'] ?? 0)
    + (int)($refundStatusCounts['disetujui'] ?? 0)
    + (int)($refundStatusCounts['diproses_transfer'] ?? 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analytics &amp; Omset - Admin LilinNawa</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="home.css">
    <style>
        .analytics-page{padding:24px 30px;max-width:1500px;margin:0 auto;box-sizing:border-box}
        .analytics-filters{display:flex;gap:12px;align-items:end;flex-wrap:wrap;background:#fff;padding:16px;border:1px solid #e5e7eb;border-radius:14px;margin:20px 0}
        .analytics-filters label{display:flex;flex-direction:column;gap:6px;font-size:12px;font-weight:700;color:#4b5563}
        .analytics-filters input{padding:9px 10px;border:1px solid #d1d5db;border-radius:8px;font:inherit}
        .analytics-filters button,.analytics-filters a{padding:10px 15px;border:0;border-radius:8px;background:#701c2f;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}
        .analytics-filters a{background:#f3f4f6;color:#374151}
        .analytics-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:22px}
        .analytics-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;box-shadow:0 4px 14px rgba(0,0,0,.025)}
        .analytics-card .label{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.4px;color:#6b7280}
        .analytics-card .value{font-size:clamp(21px,2.2vw,28px);font-weight:800;color:#701c2f;margin:8px 0}
        .analytics-card small{color:#6b7280;font-size:12px}
        .analytics-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:20px;align-items:start}
        .analytics-panel{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;margin-bottom:20px}
        .analytics-panel h3{font-size:16px;margin:0;padding:17px 20px;border-bottom:1px solid #e5e7eb;color:#111827}
        .analytics-panel .panel-body{padding:18px 20px}
        .analytics-table-wrap{overflow-x:auto}
        .analytics-table{border-collapse:collapse;width:100%;font-size:13px}
        .analytics-table th,.analytics-table td{text-align:left;padding:12px 14px;border-bottom:1px solid #f0f1f3;white-space:nowrap}
        .analytics-table th{background:#f9fafb;color:#6b7280;font-size:11px;text-transform:uppercase}
        .money-positive{color:#047857;font-weight:800}.money-negative{color:#b91c1c;font-weight:800}
        .status-row{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6;font-size:13px}
        .status-row:last-child{border-bottom:0}.status-count{font-weight:800;color:#701c2f}
        .formula-note{font-size:12px;color:#6b7280;line-height:1.65;background:#f9fafb;border-radius:10px;padding:12px 14px;margin-top:14px}
        .empty-row{text-align:center;color:#9ca3af;padding:22px!important}
        @media(max-width:900px){.analytics-page{padding:18px 14px}.analytics-grid{grid-template-columns:1fr}}
    </style>
</head>
<body class="admin-wrapper">
<?php include '../sidebar.php'; ?>
<main class="admin-main analytics-page">
    <div class="page-title-box">
        <h2>Laporan Omset &amp; Analitik Penjualan</h2>
        <p>Ringkasan pendapatan kotor, refund yang telah selesai, pendapatan bersih, dan performa pesanan LilinNawa.</p>
    </div>

    <form class="analytics-filters" method="get">
        <label>Dari tanggal
            <input type="date" name="start_date" value="<?= e($startDate) ?>" required>
        </label>
        <label>Sampai tanggal
            <input type="date" name="end_date" value="<?= e($endDate) ?>" required>
        </label>
        <button type="submit">Tampilkan Laporan</button>
        <a href="Analytics.php">Bulan Ini</a>
    </form>

    <section class="analytics-stats">
        <div class="analytics-card">
            <div class="label">Pendapatan Kotor</div>
            <div class="value"><?= rupiah($grossRevenue) ?></div>
            <small><?= $paidOrderCount ?> pesanan dengan pembayaran lunas pada periode terpilih</small>
        </div>
        <div class="analytics-card">
            <div class="label">Refund Selesai</div>
            <div class="value" style="color:#b91c1c"><?= rupiah($completedRefundTotal) ?></div>
            <small><?= $completedRefundCount ?> pengajuan refund berstatus selesai pada periode terpilih</small>
        </div>
        <div class="analytics-card">
            <div class="label">Pendapatan Bersih</div>
            <div class="value" style="color:<?= $netRevenue < 0 ? '#b91c1c' : '#047857' ?>"><?= rupiah($netRevenue) ?></div>
            <small>Pendapatan kotor dikurangi refund selesai</small>
        </div>
        <div class="analytics-card">
            <div class="label">Rata-rata Nilai Order (AOV)</div>
            <div class="value"><?= rupiah($averageOrderValue) ?></div>
            <small>Pendapatan kotor dibagi jumlah order lunas</small>
        </div>
    </section>

    <section class="analytics-grid">
        <div>
            <div class="analytics-panel">
                <h3>📅 Ringkasan Harian</h3>
                <div class="analytics-table-wrap">
                    <table class="analytics-table">
                        <thead><tr><th>Tanggal</th><th>Order Lunas</th><th>Kotor</th><th>Refund Selesai</th><th>Bersih Harian</th></tr></thead>
                        <tbody>
                        <?php if (empty($daily)): ?>
                            <tr><td colspan="5" class="empty-row">Belum ada transaksi/refund pada periode ini.</td></tr>
                        <?php else: foreach ($daily as $date => $d): $dailyNet = $d['gross'] - $d['refund']; ?>
                            <tr>
                                <td><?= e(date('d-m-Y', strtotime($date))) ?></td>
                                <td><?= (int)$d['paid_orders'] ?></td>
                                <td><?= rupiah($d['gross']) ?></td>
                                <td class="money-negative"><?= rupiah($d['refund']) ?></td>
                                <td class="<?= $dailyNet < 0 ? 'money-negative' : 'money-positive' ?>"><?= rupiah($dailyNet) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="formula-note">
                    <strong>Rumus:</strong> Pendapatan kotor = jumlah total_price dari order berstatus lunas.
                    Refund selesai = jumlah refund_requests berstatus <code>selesai</code>.
                    Pendapatan bersih = pendapatan kotor − refund selesai. Pengajuan, persetujuan, dan transfer yang belum selesai tidak mengurangi pendapatan bersih.
                </div>
            </div>

            <div class="analytics-panel">
                <h3>🏆 Template Terlaris pada Periode Terpilih</h3>
                <div class="analytics-table-wrap">
                    <table class="analytics-table">
                        <thead><tr><th>Template</th><th>Order Lunas</th><th>Pendapatan Kotor</th></tr></thead>
                        <tbody>
                        <?php if (!$templateResult || mysqli_num_rows($templateResult) === 0): ?>
                            <tr><td colspan="3" class="empty-row">Belum ada order lunas untuk periode ini.</td></tr>
                        <?php else: while ($tpl = mysqli_fetch_assoc($templateResult)): ?>
                            <tr>
                                <td><strong><?= e($tpl['template_name']) ?></strong></td>
                                <td><?= (int)$tpl['total_sold'] ?> pesanan</td>
                                <td class="money-positive"><?= rupiah($tpl['gross_total']) ?></td>
                            </tr>
                        <?php endwhile; mysqli_free_result($templateResult); endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <div class="analytics-panel">
                <h3>📦 Status Pesanan</h3>
                <div class="panel-body">
                    <div class="status-row"><span>Total semua pesanan dibuat</span><span class="status-count"><?= $totalOrdersInPeriod ?></span></div>
                    <?php
                    $statusLabels = [
                        'pilih_template' => 'Pilih template',
                        'konsultasi' => 'Konsultasi / menunggu pembayaran',
                        'data_terkirim' => 'Data acara terkirim',
                        'diproses' => 'Sedang diproses',
                        'undangan_jadi' => 'Undangan jadi / review',
                        'dikirim' => 'Dikirim',
                        'selesai' => 'Selesai',
                        'dibatalkan' => 'Dibatalkan'
                    ];
                    foreach ($statusLabels as $key => $label):
                    ?>
                        <div class="status-row"><span><?= e($label) ?></span><span class="status-count"><?= (int)($statusCounts[$key] ?? 0) ?></span></div>
                    <?php endforeach; ?>
                    <div class="formula-note">Status operasional menunjukkan tahap pesanan, bukan bukti bahwa pembayaran atau refund sudah terjadi.</div>
                </div>
            </div>

            <div class="analytics-panel">
                <h3>💸 Status Pengajuan Refund</h3>
                <div class="panel-body">
                    <?php
                    $refundLabels = [
                        'diajukan' => 'Diajukan / menunggu review',
                        'disetujui' => 'Disetujui',
                        'diproses_transfer' => 'Sedang diproses transfer',
                        'selesai' => 'Selesai / dana ditransfer',
                        'ditolak' => 'Ditolak'
                    ];
                    foreach ($refundLabels as $key => $label):
                    ?>
                        <div class="status-row"><span><?= e($label) ?></span><span class="status-count"><?= (int)($refundStatusCounts[$key] ?? 0) ?></span></div>
                    <?php endforeach; ?>
                    <div class="status-row"><strong>Refund belum selesai</strong><strong class="status-count"><?= $pendingRefundCount ?></strong></div>
                    <div class="formula-note">Refund belum selesai tidak masuk ke total refund yang mengurangi pendapatan. Pesanan dibatalkan tanpa refund selesai juga tidak dihitung sebagai refund.</div>
                </div>
            </div>
        </div>
    </section>
</main>
<script src="../assets/js/admin.js"></script>
</body>
</html>
