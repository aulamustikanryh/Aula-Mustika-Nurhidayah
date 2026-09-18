<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'karyawan'])) {
    header("Location: index.php");
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

$nama_nasabah = isset($_GET['nama_nasabah']) ? urldecode(trim($_GET['nama_nasabah'])) : '';
$riwayat = [];
if ($nama_nasabah) {
    $stmt = $pdo->prepare("SELECT n.id_nasabah, n.nama, p.id_pinjaman, p.jumlah_pinjaman, p.tanggal_pinjam, p.tenor, p.bunga, p.status, (SELECT SUM(jumlah_bayar) FROM cicilan WHERE id_pinjaman = p.id_pinjaman AND status = 'disetujui') as total_bayar FROM nasabah n JOIN pinjaman p ON n.id_nasabah = p.id_nasabah WHERE n.nama = ?");
    $stmt->execute([$nama_nasabah]);
    $riwayat = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
    <script src="assets/js/custom.js"></script>
</head>
<body>
    <div class="sidebar">
        <h4 class="text-white text-center"><?php echo $_SESSION['role'] == 'admin' ? 'Admin' : 'Karyawan'; ?> Menu</h4>
        <a href="<?php echo $_SESSION['role'] == 'admin' ? 'dashboard_admin.php' : 'dashboard_karyawan.php'; ?>">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
    <div class="content">
        <h2>Riwayat Pembayaran</h2>
        <div id="toast-container" class="position-fixed top-0 end-0 p-3"></div>
        <form method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" name="nama_nasabah" class="form-control" placeholder="Cari Nama Nasabah" value="<?php echo htmlspecialchars($nama_nasabah); ?>" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Cari</button>
                </div>
            </div>
        </form>
        <?php if ($nama_nasabah && !empty($riwayat)): ?>
            <div class="card p-4">
                <h3>Riwayat Pinjaman: <?php echo htmlspecialchars($nama_nasabah); ?></h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID Pinjaman</th>
                            <th>Jumlah</th>
                            <th>Tanggal</th>
                            <th>Tenor</th>
                            <th>Bunga</th>
                            <th>Sisa</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $row): ?>
                            <tr>
                                <td><?php echo $row['id_pinjaman']; ?></td>
                                <td>Rp <?php echo number_format($row['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                                <td><?php echo $row['tanggal_pinjam']; ?></td>
                                <td><?php echo $row['tenor']; ?> bulan</td>
                                <td><?php echo $row['bunga']; ?>%</td>
                                <td>Rp <?php echo number_format($row['jumlah_pinjaman'] - ($row['total_bayar'] ?: 0), 0, ',', '.'); ?></td>
                                <td><?php echo $row['status']; ?></td>
                                <td>
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $row['id_pinjaman']; ?>">Lihat Detail</button>
                                </td>
                            </tr>
                            <div class="modal fade" id="detailModal<?php echo $row['id_pinjaman']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Detail Pembayaran Pinjaman #<?php echo $row['id_pinjaman']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>ID Cicilan</th>
                                                        <th>Jumlah</th>
                                                        <th>Metode</th>
                                                        <th>Tanggal</th>
                                                        <th>Status</th>
                                                        <th>Bukti</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $stmt = $pdo->prepare("SELECT c.* FROM cicilan c WHERE c.id_pinjaman = ?");
                                                    $stmt->execute([$row['id_pinjaman']]);
                                                    $cicilan = $stmt->fetchAll();
                                                    foreach ($cicilan as $c): ?>
                                                        <tr>
                                                            <td><?php echo $c['id_cicilan']; ?></td>
                                                            <td>Rp <?php echo number_format($c['jumlah_bayar'], 0, ',', '.'); ?></td>
                                                            <td><?php echo ucfirst(str_replace('_', ' ', $c['metode_pembayaran'])); ?></td>
                                                            <td><?php echo $c['tanggal_bayar']; ?></td>
                                                            <td><?php echo $c['status']; ?></td>
                                                            <td><?php echo $c['bukti_bayar'] ? "<a href='{$c['bukti_bayar']}' target='_blank' class='btn btn-sm btn-primary'>Lihat</a>" : '-'; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($cicilan)): ?>
                                                        <tr><td colspan="6" class="text-center">Belum ada pembayaran.</td></tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($nama_nasabah): ?>
            <div class="alert alert-warning">Tidak ada riwayat pinjaman untuk nasabah: <?php echo htmlspecialchars($nama_nasabah); ?>.</div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>