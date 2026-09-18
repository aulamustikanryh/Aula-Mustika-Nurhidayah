<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// Ambil nama nasabah dari parameter GET
$nama_nasabah = isset($_GET['nama_nasabah']) ? trim($_GET['nama_nasabah']) : '';
if (empty($nama_nasabah)) {
    die("Nama nasabah tidak ditemukan.");
}

// Paginasi
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

try {
    // Query untuk data nasabah
    $stmt = $pdo->prepare("SELECT id_nasabah, nama FROM nasabah WHERE nama = ?");
    $stmt->execute([$nama_nasabah]);
    $nasabah = $stmt->fetch();
    if (!$nasabah) {
        die("Nasabah tidak ditemukan.");
    }

    // Query untuk riwayat pinjaman
    $pinjaman_query = "SELECT p.*, n.nama FROM pinjaman p JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE n.nama = :nama_nasabah LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($pinjaman_query);
    $stmt->bindValue(':nama_nasabah', $nama_nasabah);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pinjaman = $stmt->fetchAll();
    $total_pinjaman = $pdo->prepare("SELECT COUNT(*) FROM pinjaman p JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE n.nama = ?");
    $total_pinjaman->execute([$nama_nasabah]);
    $total_pinjaman_count = $total_pinjaman->fetchColumn();

    // Query untuk riwayat cicilan
    $cicilan_query = "SELECT c.*, n.nama FROM cicilan c JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE n.nama = :nama_nasabah LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($cicilan_query);
    $stmt->bindValue(':nama_nasabah', $nama_nasabah);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $cicilan = $stmt->fetchAll();
    $total_cicilan = $pdo->prepare("SELECT COUNT(*) FROM cicilan c JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE n.nama = ?");
    $total_cicilan->execute([$nama_nasabah]);
    $total_cicilan_count = $total_cicilan->fetchColumn();
} catch (PDOException $e) {
    $error = "Gagal mengambil data riwayat: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Nasabah - <?php echo htmlspecialchars($nama_nasabah); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <div class="sidebar">
        <h4 class="text-white text-center">Admin Menu</h4>
        <a href="dashboard_admin.php">Dashboard</a>
        <a href="dashboard_admin.php#grafik">Grafik</a>
        <a href="dashboard_admin.php#karyawan">Kelola Karyawan</a>
        <a href="dashboard_admin.php#nasabah">Kelola Nasabah</a>
        <a href="dashboard_admin.php#pinjaman">Kelola Peminjaman</a>
        <a href="dashboard_admin.php#cicilan">Verifikasi Cicilan</a>
        <a href="dashboard_admin.php#kas">Transaksi Kas</a>
        <a href="dashboard_admin.php#metode">Metode Pembayaran</a>
        <a href="dashboard_admin.php#laporan">Laporan</a>
        <a href="riwayat_nasabah.php">Riwayat Nasabah</a>
        <a href="logout.php">Logout</a>
    </div>
    <div class="content">
        <h2>Riwayat Nasabah: <?php echo htmlspecialchars($nama_nasabah); ?></h2>
        <div id="toast-container" class="position-fixed top-0 end-0 p-3"></div>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="card p-4 mb-4">
            <h3>Riwayat Peminjaman</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID Pinjaman</th>
                        <th>Jumlah (Bunga)</th>
                        <th>Tanggal</th>
                        <th>Tenor</th>
                        <th>Bunga</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pinjaman as $row): ?>
                        <tr>
                            <td><?php echo $row['id_pinjaman']; ?></td>
                            <td>Rp <?php echo number_format($row['total_pinjaman_bunga'], 0, ',', '.'); ?></td>
                            <td><?php echo $row['tanggal_pinjam']; ?></td>
                            <td><?php echo $row['tenor']; ?> bulan</td>
                            <td><?php echo $row['bunga']; ?>%</td>
                            <td><?php echo $row['status']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <nav>
                <ul class="pagination">
                    <?php for ($i = 1; $i <= ceil($total_pinjaman_count / $limit); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?nama_nasabah=<?php echo urlencode($nama_nasabah); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>

        <div class="card p-4 mb-4">
            <h3>Riwayat Cicilan</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID Cicilan</th>
                        <th>ID Pinjaman</th>
                        <th>Jumlah</th>
                        <th>Metode</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Bukti</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cicilan as $row): ?>
                        <tr>
                            <td><?php echo $row['id_cicilan']; ?></td>
                            <td><?php echo $row['id_pinjaman']; ?></td>
                            <td>Rp <?php echo number_format($row['jumlah_bayar'], 0, ',', '.'); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $row['metode_pembayaran'])); ?></td>
                            <td><?php echo $row['tanggal_bayar']; ?></td>
                            <td><?php echo $row['status']; ?></td>
                            <td><?php echo $row['bukti_bayar'] ? '<a href="' . $row['bukti_bayar'] . '" target="_blank" class="btn btn-sm btn-primary">Lihat</a>' : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <nav>
                <ul class="pagination">
                    <?php for ($i = 1; $i <= ceil($total_cicilan_count / $limit); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?nama_nasabah=<?php echo urlencode($nama_nasabah); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>

        <a href="dashboard_admin.php#nasabah" class="btn btn-primary">Kembali ke Dashboard</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>