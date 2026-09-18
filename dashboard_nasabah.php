<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/fpdf/fpdf.php';

use PHPMailer\PHPMailer\PHPMailer;

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nasabah') {
    header("Location: index.php");
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bayar_cicilan'])) {
    $id_pinjaman = trim($_POST['id_pinjaman']);
    $jumlah_bayar = str_replace('.', '', trim($_POST['jumlah_bayar']));
    $metode_pembayaran = trim($_POST['metode_pembayaran']);
    
    if (empty($id_pinjaman) || empty($jumlah_bayar) || empty($metode_pembayaran) || empty($_FILES['bukti_bayar']['name'])) {
        $error = "Semua field harus diisi.";
    } elseif ($jumlah_bayar <= 0) {
        $error = "Jumlah pembayaran harus lebih dari 0.";
    } elseif (!in_array($metode_pembayaran, ['transfer_bank', 'qris', 'cash'])) {
        $error = "Metode pembayaran tidak valid.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT jumlah_pinjaman, (SELECT SUM(jumlah_bayar) FROM cicilan WHERE id_pinjaman = ? AND status = 'disetujui') as total_bayar FROM pinjaman WHERE id_pinjaman = ?");
            $stmt->execute([$id_pinjaman, $id_pinjaman]);
            $pinjaman = $stmt->fetch();
            
            $sisa_pinjaman = $pinjaman['jumlah_pinjaman'] - ($pinjaman['total_bayar'] ?: 0) - $jumlah_bayar;
            if ($sisa_pinjaman < 0) {
                $error = "Jumlah pembayaran melebihi sisa pinjaman.";
            } else {
                $target_dir = "uploads/";
                $target_file = $target_dir . time() . '_' . basename($_FILES["bukti_bayar"]["name"]);
                if (move_uploaded_file($_FILES["bukti_bayar"]["tmp_name"], $target_file)) {
                    $stmt = $pdo->prepare("INSERT INTO cicilan (id_pinjaman, jumlah_bayar, sisa_pinjaman, tanggal_bayar, metode_pembayaran, status, bukti_bayar) VALUES (?, ?, ?, CURDATE(), ?, 'menunggu', ?)");
                    $stmt->execute([$id_pinjaman, $jumlah_bayar, $sisa_pinjaman, $metode_pembayaran, $target_file]);
                    $id_cicilan = $pdo->lastInsertId();
                    
                    // Notifikasi email ke nasabah
                    $stmt = $pdo->prepare("SELECT n.email, n.nama FROM nasabah n JOIN pinjaman p ON n.id_nasabah = p.id_nasabah WHERE p.id_pinjaman = ?");
                    $stmt->execute([$id_pinjaman]);
                    $nasabah = $stmt->fetch();
                    
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USER;
                    $mail->Password = SMTP_PASS;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = SMTP_PORT;
                    $mail->setFrom(SMTP_USER, 'Koperasi');
                    $mail->addAddress($nasabah['email']);
                    $mail->Subject = 'Pengajuan Pembayaran Cicilan';
                    $mail->Body = "Halo {$nasabah['nama']},\n\nPembayaran cicilan Anda sebesar Rp " . number_format($jumlah_bayar, 0, ',', '.') . " telah diajukan melalui {$metode_pembayaran}. Sisa pinjaman: Rp " . number_format($sisa_pinjaman, 0, ',', '.'). "\nStatus: Menunggu persetujuan.\n\nTerima kasih.";
                    $mail->send();
                    
                    // Notifikasi ke admin
                    $stmt = $pdo->prepare("INSERT INTO notifikasi (id_admin, pesan, tanggal_notifikasi) SELECT id_admin, ?, NOW() FROM admin");
                    $stmt->execute(["Pembayaran cicilan baru dari {$nasabah['nama']} (ID Cicilan: $id_cicilan, Jumlah: Rp " . number_format($jumlah_bayar, 0, ',', '.') . ") menunggu persetujuan."]);
                    
                    $success = "Pembayaran cicilan berhasil diajukan. Anda akan menerima notifikasi email setelah disetujui.";
                } else {
                    $error = "Gagal mengunggah bukti pembayaran.";
                }
            }
        } catch (Exception $e) {
            $error = "Gagal mengajukan cicilan: " . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("SELECT p.*, (SELECT SUM(jumlah_bayar) FROM cicilan WHERE id_pinjaman = p.id_pinjaman AND status = 'disetujui') as total_bayar FROM pinjaman p WHERE id_nasabah = ?");
$stmt->execute([$_SESSION['user_id']]);
$pinjaman_list = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM metode_pembayaran");
$metode_pembayaran = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Nasabah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
    <script src="assets/js/custom.js"></script>
</head>
<body>
    <div class="sidebar">
        <h4 class="text-white text-center">Nasabah Menu</h4>
        <a href="#pinjaman">Pinjaman Saya</a>
        <a href="#bayar">Bayar Cicilan</a>
        <a href="logout.php">Logout</a>
    </div>
    <div class="content">
        <h2>Dashboard Nasabah</h2>
        <div id="toast-container" class="position-fixed top-0 end-0 p-3"></div>
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div id="pinjaman" class="card p-4 mb-4">
            <h3>Pinjaman Saya</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Jumlah</th>
                        <th>Tanggal</th>
                        <th>Tenor</th>
                        <th>Bunga</th>
                        <th>Sisa</th>
                        <th>Status</th>
                        <th>Riwayat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pinjaman_list as $row): ?>
                        <tr>
                            <td><?php echo $row['id_pinjaman']; ?></td>
                            <td>Rp <?php echo number_format($row['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                            <td><?php echo $row['tanggal_pinjam']; ?></td>
                            <td><?php echo $row['tenor']; ?> bulan</td>
                            <td><?php echo $row['bunga']; ?>%</td>
                            <td>Rp <?php echo number_format($row['jumlah_pinjaman'] - ($row['total_bayar'] ?: 0), 0, ',', '.'); ?></td>
                            <td><?php echo $row['status']; ?></td>
                            <td><a href="riwayat_pembayaran.php?nama_nasabah=<?php echo urlencode($row['id_nasabah']); ?>" class="btn btn-sm btn-info" title="Lihat Riwayat">Riwayat</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($pinjaman_list)): ?>
                        <tr><td colspan="8" class="text-center">Belum ada pinjaman.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="bayar" class="card p-4">
            <h3>Bayar Cicilan</h3>
            <form method="POST" id="form-cicilan" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <select name="id_pinjaman" class="form-select" required>
                            <option value="">Pilih Pinjaman</option>
                            <?php
                            $stmt = $pdo->prepare("SELECT id_pinjaman, jumlah_pinjaman FROM pinjaman WHERE id_nasabah = ? AND status = 'aktif'");
                            $stmt->execute([$_SESSION['user_id']]);
                            while ($row = $stmt->fetch()) {
                                echo "<option value='{$row['id_pinjaman']}'>Pinjaman #{$row['id_pinjaman']} - Rp " . number_format($row['jumlah_pinjaman'], 0, ',', '.') . "</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Pinjaman wajib dipilih.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <input type="text" name="jumlah_bayar" class="form-control currency" placeholder="Jumlah Bayar" required>
                        <div class="invalid-feedback">Jumlah bayar harus lebih dari 0.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <select name="metode_pembayaran" class="form-select" required>
                            <option value="">Pilih Metode Pembayaran</option>
                            <option value="transfer_bank">Transfer Bank</option>
                            <option value="qris">QRIS</option>
                            <option value="cash">Cash</option>
                        </select>
                        <div class="invalid-feedback">Metode pembayaran wajib dipilih.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <input type="file" name="bukti_bayar" class="form-control" accept="image/*,application/pdf" required>
                        <div class="invalid-feedback">Bukti pembayaran wajib diunggah.</div>
                    </div>
                    <div class="col-12 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5>Informasi Pembayaran</h5>
                                <?php foreach ($metode_pembayaran as $metode): ?>
                                    <?php if ($metode['jenis'] == 'transfer_bank'): ?>
                                        <p><strong><?php echo $metode['nama_bank']; ?>:</strong> <?php echo $metode['nomor_rekening']; ?></p>
                                    <?php elseif ($metode['jenis'] == 'qris'): ?>
                                        <p><strong>QRIS:</strong> <a href="<?php echo $metode['qr_code_path']; ?>" target="_blank">Lihat QR Code</a></p>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <p><strong>Cash:</strong> Silakan bayar langsung ke kantor koperasi.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="bayar_cicilan" class="btn btn-primary">Ajukan Pembayaran</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>