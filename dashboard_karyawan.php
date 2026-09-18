<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/fpdf/fpdf.php';

use PHPMailer\PHPMailer\PHPMailer;

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'karyawan') {
    header("Location: index.php");
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Validasi dan tambah peminjaman
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_pinjaman'])) {
    $id_nasabah = trim($_POST['id_nasabah']);
    $jumlah_pinjaman = str_replace('.', '', trim($_POST['jumlah_pinjaman']));
    $tenor = trim($_POST['tenor']);
    $bunga = trim($_POST['bunga']);

    if (empty($id_nasabah) || empty($jumlah_pinjaman) || empty($tenor) || empty($bunga)) {
        $error = "Semua field harus diisi.";
    } elseif ($jumlah_pinjaman <= 0) {
        $error = "Jumlah pinjaman harus lebih dari 0.";
    } elseif ($tenor <= 0) {
        $error = "Tenor harus lebih dari 0.";
    } elseif ($bunga < 0) {
        $error = "Bunga tidak boleh negatif.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO pinjaman (id_nasabah, jumlah_pinjaman, tanggal_pinjam, tenor, bunga, status) VALUES (?, ?, CURDATE(), ?, ?, 'aktif')");
            $stmt->execute([$id_nasabah, $jumlah_pinjaman, $tenor, $bunga]);
            $pinjaman_id = $pdo->lastInsertId();

            // Kirim email notifikasi
            $stmt = $pdo->prepare("SELECT email, nama FROM nasabah WHERE id_nasabah = ?");
            $stmt->execute([$id_nasabah]);
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
            $mail->Subject = 'Peminjaman Baru';
            $mail->Body = "Halo {$nasabah['nama']},\n\nPeminjaman baru telah ditambahkan:\nID Peminjaman: $pinjaman_id\nJumlah: Rp " . number_format($jumlah_pinjaman, 0, ',', '.') . "\nTenor: $tenor bulan\nBunga: $bunga%\nStatus: Aktif\n\nTerima kasih.";
            $mail->send();
            
            $success = "Peminjaman berhasil ditambahkan dan notifikasi email telah dikirim.";
        } catch (Exception $e) {
            $error = "Gagal menambah peminjaman: " . $e->getMessage();
        }
    }
}

// Verifikasi cicilan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_cicilan'])) {
    try {
        $status = $_POST['status'];
        $id_cicilan = $_POST['id_cicilan'];
        
        if (!in_array($status, ['disetujui', 'ditolak'])) {
            $error = "Status tidak valid.";
        } else {
            $stmt = $pdo->prepare("UPDATE cicilan SET status = ?, acc_by = ? WHERE id_cicilan = ?");
            $stmt->execute([$status, $_SESSION['user_id'], $id_cicilan]);

            if ($status == 'disetujui') {
                $stmt = $pdo->prepare("SELECT c.jumlah_bayar, c.sisa_pinjaman, p.id_nasabah, p.id_pinjaman FROM cicilan c JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman WHERE c.id_cicilan = ?");
                $stmt->execute([$id_cicilan]);
                $cicilan = $stmt->fetch();
                
                // Ambil saldo terakhir dari kas_koperasi
                $stmt = $pdo->query("SELECT saldo FROM kas_koperasi ORDER BY id_kas DESC LIMIT 1");
                $saldo_sebelumnya = $stmt->fetchColumn() ?: 0;
                $saldo_baru = $saldo_sebelumnya + $cicilan['jumlah_bayar'];
                
                // Insert ke kas_koperasi
                $stmt = $pdo->prepare("INSERT INTO kas_koperasi (tanggal, keterangan, debit, saldo) VALUES (CURDATE(), ?, ?, ?)");
                $stmt->execute(["Pembayaran cicilan #$id_cicilan", $cicilan['jumlah_bayar'], $saldo_baru]);
                
                // Cek apakah pinjaman lunas
                $stmt = $pdo->prepare("SELECT SUM(jumlah_bayar) as total_bayar, p.jumlah_pinjaman FROM cicilan c JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman WHERE c.id_pinjaman = ? AND c.status = 'disetujui'");
                $stmt->execute([$cicilan['id_pinjaman']]);
                $data = $stmt->fetch();
                if ($data['total_bayar'] >= $data['jumlah_pinjaman']) {
                    $stmt = $pdo->prepare("UPDATE pinjaman SET status = 'lunas' WHERE id_pinjaman = ?");
                    $stmt->execute([$cicilan['id_pinjaman']]);
                    $is_lunas = true;
                } else {
                    $is_lunas = false;
                }
                
                // Kirim email notifikasi ke nasabah
                $stmt = $pdo->prepare("SELECT n.email, n.nama FROM nasabah n WHERE n.id_nasabah = ?");
                $stmt->execute([$cicilan['id_nasabah']]);
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
                $mail->Subject = 'Status Pembayaran Cicilan';
                $mail->Body = "Halo {$nasabah['nama']},\n\nPembayaran cicilan Anda sebesar Rp " . number_format($cicilan['jumlah_bayar'], 0, ',', '.') . " telah disetujui. Sisa pinjaman: Rp " . number_format($cicilan['sisa_pinjaman'], 0, ',', '.') . ".";
                if ($is_lunas) {
                    $mail->Body .= "\n\nSelamat! Pinjaman Anda telah lunas.";
                }
                $mail->send();
                
                // Notifikasi ke admin
                $stmt = $pdo->prepare("INSERT INTO notifikasi (id_admin, pesan, tanggal_notifikasi) SELECT id_admin, ?, NOW() FROM admin");
                $msg = "Pembayaran cicilan #$id_cicilan dari {$nasabah['nama']} disetujui oleh karyawan.";
                if ($is_lunas) {
                    $msg .= " Pinjaman telah lunas.";
                }
                $stmt->execute([$msg]);
            } else {
                // Kirim email notifikasi penolakan
                $stmt = $pdo->prepare("SELECT n.email, n.nama, c.jumlah_bayar FROM cicilan c JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE c.id_cicilan = ?");
                $stmt->execute([$id_cicilan]);
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
                $mail->Subject = 'Status Pembayaran Cicilan';
                $mail->Body = "Halo {$nasabah['nama']},\n\nPembayaran cicilan Anda sebesar Rp " . number_format($nasabah['jumlah_bayar'], 0, ',', '.') . " telah ditolak. Silakan hubungi koperasi untuk informasi lebih lanjut.";
                $mail->send();

                // Notifikasi ke admin
                $stmt = $pdo->prepare("INSERT INTO notifikasi (id_admin, pesan, tanggal_notifikasi) SELECT id_admin, ?, NOW() FROM admin");
                $stmt->execute(["Pembayaran cicilan #$id_cicilan dari {$nasabah['nama']} ditolak oleh karyawan."]);
            }
            $success = "Cicilan berhasil diverifikasi.";
        }
    } catch (Exception $e) {
        $error = "Gagal memverifikasi cicilan: " . $e->getMessage();
    }
}

// Kirim pengingat jatuh tempo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reminder'])) {
    try {
        $id_pinjaman = $_POST['id_pinjaman'];
        $stmt = $pdo->prepare("SELECT n.email, n.nama, p.jumlah_pinjaman, (SELECT SUM(jumlah_bayar) FROM cicilan WHERE id_pinjaman = p.id_pinjaman AND status = 'disetujui') as total_bayar FROM pinjaman p JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE p.id_pinjaman = ?");
        $stmt->execute([$id_pinjaman]);
        $pinjaman = $stmt->fetch();
        
        if ($pinjaman) {
            $sisa_pinjaman = $pinjaman['jumlah_pinjaman'] - ($pinjaman['total_bayar'] ?: 0);
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->setFrom(SMTP_USER, 'Koperasi');
            $mail->addAddress($pinjaman['email']);
            $mail->Subject = 'Pengingat Jatuh Tempo Pinjaman';
            $mail->Body = "Halo {$pinjaman['nama']},\n\nIni adalah pengingat untuk pembayaran cicilan pinjaman Anda (ID: $id_pinjaman). Sisa pinjaman: Rp " . number_format($sisa_pinjaman, 0, ',', '.') . ". Silakan lakukan pembayaran segera.\n\nTerima kasih.";
            $mail->send();
            
            $success = "Pengingat jatuh tempo telah dikirim ke {$pinjaman['nama']}.";
        } else {
            $error = "Pinjaman tidak ditemukan.";
        }
    } catch (Exception $e) {
        $error = "Gagal mengirim pengingat: " . $e->getMessage();
    }
}

// Data peminjaman jatuh tempo
$stmt = $pdo->prepare("SELECT p.id_pinjaman, n.nama, p.jumlah_pinjaman, p.tanggal_pinjam, p.tenor, (SELECT SUM(jumlah_bayar) FROM cicilan WHERE id_pinjaman = p.id_pinjaman AND status = 'disetujui') as total_bayar FROM pinjaman p JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE p.status = 'aktif' AND DATE_ADD(p.tanggal_pinjam, INTERVAL p.tenor MONTH) <= CURDATE()");
$stmt->execute();
$jatuh_tempo = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Karyawan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
    <script src="assets/js/custom.js"></script>
</head>
<body>
    <div class="sidebar">
        <h4 class="text-white text-center">Karyawan Menu</h4>
        <a href="#pinjaman">Kelola Peminjaman</a>
        <a href="#cicilan">Verifikasi Cicilan</a>
        <a href="#jatuh-tempo">Jatuh Tempo</a>
        <a href="riwayat_pembayaran.php">Riwayat Pembayaran</a>
        <a href="logout.php">Logout</a>
    </div>
    <div class="content">
        <h2>Dashboard Karyawan</h2>
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
            <h3>Kelola Peminjaman</h3>
            <form method="POST" id="form-pinjaman" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <select name="id_nasabah" class="form-select" required>
                            <option value="">Pilih Nasabah</option>
                            <?php
                            $stmt = $pdo->query("SELECT id_nasabah, nama FROM nasabah");
                            while ($row = $stmt->fetch()) {
                                echo "<option value='{$row['id_nasabah']}'>{$row['nama']}</option>";
                            }
                            ?>
                        </select>
                        <div class="invalid-feedback">Nasabah wajib dipilih.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <input type="text" name="jumlah_pinjaman" class="form-control currency" placeholder="Jumlah Pinjaman" required>
                        <div class="invalid-feedback">Jumlah pinjaman harus lebih dari 0.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <input type="number" name="tenor" class="form-control" placeholder="Tenor (bulan)" required>
                        <div class="invalid-feedback">Tenor harus lebih dari 0.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <input type="number" step="0.01" name="bunga" class="form-control" placeholder="Bunga (%)" required>
                        <div class="invalid-feedback">Bunga tidak boleh negatif.</div>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_pinjaman" class="btn btn-primary">Tambah Peminjaman</button>
                    </div>
                </div>
            </form>
            <table class="table mt-4">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nasabah</th>
                        <th>Jumlah</th>
                        <th>Tanggal</th>
                        <th>Tenor</th>
                        <th>Bunga</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT p.*, n.nama FROM pinjaman p JOIN nasabah n ON p.id_nasabah = n.id_nasabah");
                    while ($row = $stmt->fetch()) {
                        echo "<tr>
                            <td>{$row['id_pinjaman']}</td>
                            <td>{$row['nama']}</td>
                            <td>Rp " . number_format($row['jumlah_pinjaman'], 0, ',', '.') . "</td>
                            <td>{$row['tanggal_pinjam']}</td>
                            <td>{$row['tenor']}</td>
                            <td>{$row['bunga']}%</td>
                            <td>{$row['status']}</td>
                            <td>
                                <a href='riwayat_pembayaran.php?nama_nasabah=" . urlencode($row['nama']) . "' class='btn btn-sm btn-info' title='Lihat Riwayat Pembayaran'>Riwayat</a>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div id="cicilan" class="card p-4 mb-4">
            <h3>Verifikasi Cicilan</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID Cicilan</th>
                        <th>Nasabah</th>
                        <th>Jumlah</th>
                        <th>Metode</th>
                        <th>Bukti</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $pdo->query("SELECT c.*, n.nama FROM cicilan c JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE c.status = 'menunggu'");
                    while ($row = $stmt->fetch()) {
                        echo "<tr>
                            <td>{$row['id_cicilan']}</td>
                            <td>{$row['nama']}</td>
                            <td>Rp " . number_format($row['jumlah_bayar'], 0, ',', '.') . "</td>
                            <td>" . ucfirst(str_replace('_', ' ', $row['metode_pembayaran'])) . "</td>
                            <td><a href='{$row['bukti_bayar']}' target='_blank' class='btn btn-sm btn-primary' title='Lihat Bukti'>Lihat</a></td>
                            <td>
                                <form method='POST'>
                                    <input type='hidden' name='id_cicilan' value='{$row['id_cicilan']}'>
                                    <select name='status'>
                                        <option value='disetujui'>Disetujui</option>
                                        <option value='ditolak'>Ditolak</option>
                                    </select>
                                    <button type='submit' name='verify_cicilan' class='btn btn-sm btn-primary'>Verifikasi</button>
                                </form>
                            </td>
                        </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div id="jatuh-tempo" class="card p-4">
            <h3>Peminjaman Jatuh Tempo</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nasabah</th>
                        <th>Jumlah</th>
                        <th>Sisa</th>
                        <th>Tanggal Pinjam</th>
                        <th>Tenor</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jatuh_tempo as $row): ?>
                        <tr>
                            <td><?php echo $row['id_pinjaman']; ?></td>
                            <td><?php echo $row['nama']; ?></td>
                            <td>Rp <?php echo number_format($row['jumlah_pinjaman'], 0, ',', '.'); ?></td>
                            <td>Rp <?php echo number_format($row['jumlah_pinjaman'] - ($row['total_bayar'] ?: 0), 0, ',', '.'); ?></td>
                            <td><?php echo $row['tanggal_pinjam']; ?></td>
                            <td><?php echo $row['tenor']; ?> bulan</td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="id_pinjaman" value="<?php echo $row['id_pinjaman']; ?>">
                                    <button type="submit" name="send_reminder" class="btn btn-sm btn-warning">Kirim Pengingat</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($jatuh_tempo)): ?>
                        <tr><td colspan="7" class="text-center">Tidak ada pinjaman jatuh tempo.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>