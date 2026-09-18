<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/pinjaman.php';
require_once __DIR__ . '/includes/cicilan.php';
require_once __DIR__ . '/includes/metode_pembayaran.php';
require_once __DIR__ . '/includes/kas.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}

// Proses form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add_karyawan'])) {
            $stmt = $pdo->prepare("INSERT INTO karyawan (username, password, nama, jabatan, email, no_hp) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['username'], $_POST['password'], $_POST['nama'], $_POST['jabatan'], $_POST['email'], $_POST['no_hp']]);
            $success = "Karyawan berhasil ditambahkan.";
        } elseif (isset($_POST['edit_karyawan'])) {
            $stmt = $pdo->prepare("UPDATE karyawan SET username = ?, password = ?, nama = ?, jabatan = ?, email = ?, no_hp = ? WHERE id_karyawan = ?");
            $stmt->execute([$_POST['username'], $_POST['password'], $_POST['nama'], $_POST['jabatan'], $_POST['email'], $_POST['no_hp'], $_POST['id_karyawan']]);
            $success = "Karyawan berhasil diperbarui.";
        } elseif (isset($_POST['delete_karyawan'])) {
            $stmt = $pdo->prepare("DELETE FROM karyawan WHERE id_karyawan = ?");
            $stmt->execute([$_POST['id_karyawan']]);
            $success = "Karyawan berhasil dihapus.";
        } elseif (isset($_POST['add_nasabah'])) {
            $email = $_POST['email'];
            $nama = $_POST['nama'];
            $stmt = $pdo->prepare("INSERT INTO nasabah (username, password, nama, alamat, email, no_hp, pekerjaan, gaji) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['username'], $_POST['password'], $nama, $_POST['alamat'], $email, $_POST['no_hp'], $_POST['pekerjaan'], str_replace('.', '', $_POST['gaji'])]);
            $success = "Nasabah berhasil ditambahkan.";
            // Kirim email ke nasabah
            $body = "<h3>Selamat Datang di Koperasi Cilacap!</h3><p>Akun Anda telah berhasil dibuat dengan detail berikut:</p><ul><li>Nama: $nama</li><li>Email: $email</li><li>Username: {$_POST['username']}</li></ul><p>Silakan hubungi admin untuk informasi lebih lanjut.</p>";
            $emailResult = sendEmail($email, "Selamat Datang di Koperasi Cilacap!", $body);
            if ($emailResult !== true) {
                $error = $emailResult;
            }
        } elseif (isset($_POST['edit_nasabah'])) {
            $email = $_POST['email'];
            $nama = $_POST['nama'];
            $id_nasabah = $_POST['id_nasabah'];
            $stmt = $pdo->prepare("UPDATE nasabah SET username = ?, password = ?, nama = ?, alamat = ?, email = ?, no_hp = ?, pekerjaan = ?, gaji = ? WHERE id_nasabah = ?");
            $stmt->execute([$_POST['username'], $_POST['password'], $nama, $_POST['alamat'], $email, $_POST['no_hp'], $_POST['pekerjaan'], str_replace('.', '', $_POST['gaji']), $id_nasabah]);
            $success = "Nasabah berhasil diperbarui.";
            // Kirim email ke nasabah
            $body = "<h3>Pembaruan Data Nasabah</h3><p>Data akun Anda telah diperbarui dengan detail berikut:</p><ul><li>Nama: $nama</li><li>Email: $email</li><li>Username: {$_POST['username']}</li></ul><p>Silakan hubungi admin jika ada pertanyaan.</p>";
            $emailResult = sendEmail($email, "Pembaruan Data Nasabah di Koperasi Cilacap", $body);
            if ($emailResult !== true) {
                $error = $emailResult;
            }
        } elseif (isset($_POST['delete_nasabah'])) {
            $stmt = $pdo->prepare("SELECT nama, email FROM nasabah WHERE id_nasabah = ?");
            $stmt->execute([$_POST['id_nasabah']]);
            $nasabah = $stmt->fetch();
            if ($nasabah) {
                $stmt = $pdo->prepare("DELETE FROM nasabah WHERE id_nasabah = ?");
                $stmt->execute([$_POST['id_nasabah']]);
                $success = "Nasabah berhasil dihapus.";
                // Kirim email ke nasabah
                $body = "<h3>Pemberitahuan Penghapusan Akun</h3><p>Akun Anda dengan nama {$nasabah['nama']} telah dihapus dari sistem Koperasi Cilacap.</p><p>Jika ini adalah kesalahan, silakan hubungi admin.</p>";
                $emailResult = sendEmail($nasabah['email'], "Penghapusan Akun di Koperasi Cilacap", $body);
                if ($emailResult !== true) {
                    $error = $emailResult;
                }
            } else {
                $error = "Nasabah tidak ditemukan.";
            }
        } elseif (isset($_POST['add_pinjaman'])) {
            $result = addPinjaman($pdo, $_POST['id_nasabah'], str_replace('.', '', $_POST['jumlah_pinjaman']), $_POST['tenor'], $_POST['bunga']);
            if (isset($result['error'])) $error = $result['error']; else $success = $result['success'];
        } elseif (isset($_POST['extend_pinjaman'])) {
            $result = extendPinjaman($pdo, $_POST['id_pinjaman'], str_replace('.', '', $_POST['tambahan_jumlah']), $_POST['tambahan_tenor']);
            if (isset($result['error'])) $error = $result['error']; else $success = $result['success'];
        } elseif (isset($_POST['delete_pinjaman'])) {
            $result = deletePinjaman($pdo, $_POST['id_pinjaman']);
            if (isset($result['error'])) $error = $result['error']; else $success = $result['success'];
        } elseif (isset($_POST['verify_cicilan'])) {
            $result = verifyCicilan($pdo, $_POST['id_cicilan'], $_POST['status'], $_SESSION['user_id']);
            if (isset($result['error'])) $error = $result['error']; else $success = $result['success'];
        } elseif (isset($_POST['add_kas'])) {
            $result = addKas($pdo, $_POST['keterangan'], str_replace('.', '', $_POST['debit']), str_replace('.', '', $_POST['kredit']));
            if (isset($result['error'])) $error = $result['error']; else $success = $result['success'];
        } elseif (isset($_POST['set_saldo_awal'])) {
            $result = setSaldoAwal($pdo, str_replace('.', '', $_POST['saldo_awal']));
            if (isset($result['error'])) $error = $result['error']; else $success = $result['success'];
        } elseif (isset($_POST['add_metode'])) {
            $result = addMetode($pdo, $_POST['jenis'], $_POST['nama_bank'], $_POST['nomor_rekening'], $_FILES['qr_code']);
            if (isset($result['error'])) $error = $result['error']; else $success = $result['success'];
        } elseif (isset($_POST['edit_metode'])) {
            $result = editMetode($pdo, $_POST['id_metode'], $_POST['jenis'], $_POST['nama_bank'], $_POST['nomor_rekening'], $_FILES['qr_code']);
            if (isset($result['error'])) $error = $result['error']; else $success = $result['success'];
        } elseif (isset($_POST['delete_metode'])) {
            $result = deleteMetode($pdo, $_POST['id_metode']);
            if (isset($result['error'])) $error = $result['error']; else $success = $result['success'];
        } elseif (isset($_POST['generate_report'])) {
            require_once __DIR__ . '/vendor/fpdf/fpdf.php';
            $periode = $_POST['periode'];
            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 10, 'Laporan Keuangan Koperasi', 0, 1, 'C');
            $pdf->Ln(5);
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, "Periode: $periode", 0, 1);
            $pdf->Ln(5);

            $stmt = $pdo->prepare("SELECT saldo FROM kas_koperasi WHERE tanggal < ? ORDER BY tanggal DESC LIMIT 1");
            $stmt->execute(["$periode-01"]);
            $saldo_awal = $stmt->fetchColumn() ?: 0;

            $stmt = $pdo->prepare("SELECT SUM(total_pinjaman_bunga) as total_pinjaman FROM pinjaman WHERE DATE_FORMAT(tanggal_pinjam, '%Y-%m') = ?");
            $stmt->execute([$periode]);
            $total_pinjaman = $stmt->fetchColumn() ?: 0;

            $stmt = $pdo->prepare("SELECT SUM(c.jumlah_bayar) as total_cicilan FROM cicilan c JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman WHERE c.status = 'disetujui' AND DATE_FORMAT(c.tanggal_bayar, '%Y-%m') = ?");
            $stmt->execute([$periode]);
            $total_cicilan = $stmt->fetchColumn() ?: 0;

            $saldo_akhir = $saldo_awal - $total_pinjaman + $total_cicilan;

            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(50, 10, 'Nama Nasabah', 1);
            $pdf->Cell(50, 10, 'Total Setoran', 1);
            $pdf->Cell(50, 10, 'Jumlah Pinjaman', 1);
            $pdf->Ln();
            $pdf->SetFont('Arial', '', 12);
            $stmt = $pdo->prepare("SELECT n.nama, SUM(c.jumlah_bayar) as total_setoran, SUM(p.total_pinjaman_bunga) as total_pinjaman FROM nasabah n LEFT JOIN pinjaman p ON n.id_nasabah = p.id_nasabah LEFT JOIN cicilan c ON p.id_pinjaman = c.id_pinjaman WHERE c.status = 'disetujui' AND DATE_FORMAT(c.tanggal_bayar, '%Y-%m') = ? GROUP BY n.id_nasabah");
            $stmt->execute([$periode]);
            while ($row = $stmt->fetch()) {
                $pdf->Cell(50, 10, $row['nama'], 1);
                $pdf->Cell(50, 10, 'Rp ' . number_format($row['total_setoran'] ?: 0, 0, ',', '.'), 1);
                $pdf->Cell(50, 10, 'Rp ' . number_format($row['total_pinjaman'] ?: 0, 0, ',', '.'), 1);
                $pdf->Ln();
            }

            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Ringkasan Keuangan', 0, 1);
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 10, 'Saldo Awal: Rp ' . number_format($saldo_awal, 0, ',', '.'), 0, 1);
            $pdf->Cell(0, 10, 'Total Peminjaman: Rp ' . number_format($total_pinjaman, 0, ',', '.'), 0, 1);
            $pdf->Cell(0, 10, 'Total Pembayaran Cicilan: Rp ' . number_format($total_cicilan, 0, ',', '.'), 0, 1);
            $pdf->Cell(0, 10, 'Saldo Akhir: Rp ' . number_format($saldo_akhir, 0, ',', '.'), 0, 1);
            $pdf->Output('D', 'laporan_' . $periode . '.pdf');
            exit;
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "Gagal menambah data: Data sudah ada (kemungkinan username atau email sudah digunakan).";
        } else {
            $error = "Gagal memproses data: " . $e->getMessage();
        }
    }
}

// Data untuk dashboard
try {
    $periode = isset($_GET['periode']) ? $_GET['periode'] : date('Y-m');
    $stmt = $pdo->prepare("SELECT tanggal, saldo FROM kas_koperasi WHERE DATE_FORMAT(tanggal, '%Y-%m') = ? ORDER BY tanggal");
    $stmt->execute([$periode]);
    $kas_data = $stmt->fetchAll();
    $kas_labels = array_column($kas_data, 'tanggal');
    $kas_data_values = array_column($kas_data, 'saldo');
    $stmt = $pdo->prepare("SELECT SUM(total_pinjaman_bunga) as jumlah_pinjaman FROM pinjaman WHERE DATE_FORMAT(tanggal_pinjam, '%Y-%m') = ?");
    $stmt->execute([$periode]);
    $pinjaman_data_values = [$stmt->fetchColumn() ?: 0];
    $stmt = $pdo->query("SELECT COUNT(*) as total_nasabah FROM nasabah");
    $total_nasabah = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) as total_pinjaman_aktif FROM pinjaman WHERE status = 'aktif'");
    $total_pinjaman_aktif = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT saldo FROM kas_koperasi ORDER BY id_kas DESC LIMIT 1");
    $saldo_koperasi = $stmt->fetchColumn() ?: 0;

    // Paginasi
    $limit = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $karyawan_query = $search ? "SELECT * FROM karyawan WHERE nama LIKE :search OR email LIKE :search LIMIT :limit OFFSET :offset" : "SELECT * FROM karyawan LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($karyawan_query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->execute();
    $karyawan = $stmt->fetchAll();
    $total_karyawan = $pdo->query("SELECT COUNT(*) FROM karyawan" . ($search ? " WHERE nama LIKE '%$search%' OR email LIKE '%$search%'" : ""))->fetchColumn();
    $nasabah_query = $search ? "SELECT * FROM nasabah WHERE nama LIKE :search OR email LIKE :search LIMIT :limit OFFSET :offset" : "SELECT * FROM nasabah LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($nasabah_query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->execute();
    $nasabah = $stmt->fetchAll();
    $total_nasabah_all = $pdo->query("SELECT COUNT(*) FROM nasabah" . ($search ? " WHERE nama LIKE '%$search%' OR email LIKE '%$search%'" : ""))->fetchColumn();
    $pinjaman_query = $search ? "SELECT p.*, n.nama FROM pinjaman p JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE n.nama LIKE :search OR p.id_pinjaman LIKE :search LIMIT :limit OFFSET :offset" : "SELECT p.*, n.nama FROM pinjaman p JOIN nasabah n ON p.id_nasabah = n.id_nasabah LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($pinjaman_query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->execute();
    $pinjaman = $stmt->fetchAll();
    $total_pinjaman = $pdo->query("SELECT COUNT(*) FROM pinjaman p JOIN nasabah n ON p.id_nasabah = n.id_nasabah" . ($search ? " WHERE n.nama LIKE '%$search%' OR p.id_pinjaman LIKE '%$search%'" : ""))->fetchColumn();
    $cicilan_query = $search ? "SELECT c.*, n.nama FROM cicilan c JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE c.status = 'menunggu' AND (n.nama LIKE :search OR c.id_cicilan LIKE :search) LIMIT :limit OFFSET :offset" : "SELECT c.*, n.nama FROM cicilan c JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE c.status = 'menunggu' LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($cicilan_query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->execute();
    $cicilan = $stmt->fetchAll();
    $total_cicilan = $pdo->query("SELECT COUNT(*) FROM cicilan c JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE c.status = 'menunggu'" . ($search ? " AND (n.nama LIKE '%$search%' OR c.id_cicilan LIKE '%$search%')" : ""))->fetchColumn();
    $metode_query = $search ? "SELECT * FROM metode_pembayaran WHERE nama_bank LIKE :search OR nomor_rekening LIKE :search LIMIT :limit OFFSET :offset" : "SELECT * FROM metode_pembayaran LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($metode_query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->execute();
    $metode_pembayaran = $stmt->fetchAll();
    $total_metode = $pdo->query("SELECT COUNT(*) FROM metode_pembayaran" . ($search ? " WHERE nama_bank LIKE '%$search%' OR nomor_rekening LIKE '%$search%'" : ""))->fetchColumn();
    $kas_query = $search ? "SELECT * FROM kas_koperasi WHERE keterangan LIKE :search LIMIT :limit OFFSET :offset" : "SELECT * FROM kas_koperasi LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($kas_query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->execute();
    $kas = $stmt->fetchAll();
    $total_kas = $pdo->query("SELECT COUNT(*) FROM kas_koperasi" . ($search ? " WHERE keterangan LIKE '%$search%'" : ""))->fetchColumn();
} catch (PDOException $e) {
    $error = "Gagal mengambil data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/custom.js"></script>
</head>
<body>
    <div class="sidebar">
        <h4 class="text-white text-center">Admin Menu</h4>
        <a href="#dashboard">Dashboard</a>
        <a href="#grafik">Grafik</a>
        <a href="#karyawan">Kelola Karyawan</a>
        <a href="#nasabah">Kelola Nasabah</a>
        <a href="#pinjaman">Kelola Peminjaman</a>
        <a href="#cicilan">Verifikasi Cicilan</a>
        <a href="#kas">Transaksi Kas</a>
        <a href="#metode">Metode Pembayaran</a>
        <a href="#laporan">Laporan</a>
        <a href="riwayat_nasabah.php">Riwayat Nasabah</a>
        <a href="logout.php">Logout</a>
    </div>
    <div class="content">
        <h2>Dashboard Admin</h2>
        <div id="toast-container" class="position-fixed top-0 end-0 p-3"></div>
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div id="dashboard" class="card p-4 mb-4">
            <h3>Ringkasan Koperasi</h3>
            <div class="row">
                <div class="col-md-4"><div class="card text-center"><div class="card-body"><h5>Total Nasabah</h5><p><?php echo $total_nasabah; ?> Orang</p></div></div></div>
                <div class="col-md-4"><div class="card text-center"><div class="card-body"><h5>Pinjaman Aktif</h5><p><?php echo $total_pinjaman_aktif; ?> Pinjaman</p></div></div></div>
                <div class="col-md-4"><div class="card text-center"><div class="card-body"><h5>Saldo Koperasi</h5><p>Rp <?php echo number_format($saldo_koperasi, 0, ',', '.'); ?></p></div></div></div>
            </div>
            <form method="POST" class="needs-validation mt-4" novalidate><div class="row"><div class="col-md-6 mb-3"><input type="text" name="saldo_awal" class="form-control currency" placeholder="Saldo Awal Koperasi (Rp)" required><div class="invalid-feedback">Saldo awal harus lebih dari 0.</div></div><div class="col-md-2"><button type="submit" name="set_saldo_awal" class="btn btn-primary">Atur Saldo</button></div></div></form>
        </div>

        <div id="grafik" class="card p-4 mb-4">
            <h3>Grafik Keuangan</h3>
            <form method="GET" class="mb-3"><div class="row"><div class="col-md-4"><input type="text" name="periode" class="form-control" placeholder="Periode (YYYY-MM)" value="<?php echo $periode; ?>" required></div><div class="col-md-2"><button type="submit" class="btn btn-primary">Filter</button></div></div></form>
            <div class="row"><div class="col-md-6"><canvas id="kasChart"></canvas></div><div class="col-md-6"><canvas id="pinjamanChart"></canvas></div></div>
            <script>
                new Chart(document.getElementById('kasChart').getContext('2d'), {
                    type: 'line',
                    data: { labels: <?php echo json_encode($kas_labels); ?>, datasets: [{ label: 'Saldo Kas', data: <?php echo json_encode($kas_data_values); ?>, borderColor: '#2c3e50', fill: false }] },
                    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { callback: value => 'Rp ' + value.toLocaleString('id-ID') } } } }
                });
                new Chart(document.getElementById('pinjamanChart').getContext('2d'), {
                    type: 'bar',
                    data: { labels: ['<?php echo $periode; ?>'], datasets: [{ label: 'Total Peminjaman', data: <?php echo json_encode($pinjaman_data_values); ?>, backgroundColor: '#28a745' }] },
                    options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { callback: value => 'Rp ' + value.toLocaleString('id-ID') } } } }
                });
            </script>
        </div>

        <div id="karyawan" class="card p-4 mb-4">
            <h3>Kelola Karyawan</h3>
            <form method="GET" class="mb-3"><div class="row"><div class="col-md-6"><input type="text" name="search" class="form-control" placeholder="Cari Nama/Email" value="<?php echo htmlspecialchars($search); ?>"></div><div class="col-md-2"><button type="submit" class="btn btn-primary">Cari</button></div></div></form>
            <form method="POST" class="needs-validation" novalidate><div class="row"><div class="col-md-4 mb-3"><input type="text" name="username" class="form-control" placeholder="Username" required></div><div class="col-md-4 mb-3"><input type="text" name="password" class="form-control" placeholder="Password" required></div><div class="col-md-4 mb-3"><input type="text" name="nama" class="form-control" placeholder="Nama" required></div><div class="col-md-4 mb-3"><input type="text" name="jabatan" class="form-control" placeholder="Jabatan" required></div><div class="col-md-4 mb-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div><div class="col-md-4 mb-3"><input type="text" name="no_hp" class="form-control" placeholder="No HP" required></div><div class="col-12"><button type="submit" name="add_karyawan" class="btn btn-primary">Tambah</button></div></div></form>
            <table class="table mt-4"><thead><tr><th>ID</th><th>Nama</th><th>Jabatan</th><th>Email</th><th>No HP</th><th>Aksi</th></tr></thead><tbody>
                <?php foreach ($karyawan as $row): ?>
                    <tr><td><?php echo $row['id_karyawan']; ?></td><td><?php echo $row['nama']; ?></td><td><?php echo $row['jabatan']; ?></td><td><?php echo $row['email']; ?></td><td><?php echo $row['no_hp']; ?></td><td><button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editKaryawan<?php echo $row['id_karyawan']; ?>">Edit</button><form method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus?');"><input type="hidden" name="id_karyawan" value="<?php echo $row['id_karyawan']; ?>"><button type="submit" name="delete_karyawan" class="btn btn-sm btn-danger">Hapus</button></form></td></tr>
                    <div class="modal fade" id="editKaryawan<?php echo $row['id_karyawan']; ?>"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Karyawan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" class="needs-validation" novalidate><div class="modal-body"><input type="hidden" name="id_karyawan" value="<?php echo $row['id_karyawan']; ?>"><div class="mb-3"><input type="text" name="username" class="form-control" value="<?php echo $row['username']; ?>" required></div><div class="mb-3"><input type="text" name="password" class="form-control" value="<?php echo $row['password']; ?>" required></div><div class="mb-3"><input type="text" name="nama" class="form-control" value="<?php echo $row['nama']; ?>" required></div><div class="mb-3"><input type="text" name="jabatan" class="form-control" value="<?php echo $row['jabatan']; ?>" required></div><div class="mb-3"><input type="email" name="email" class="form-control" value="<?php echo $row['email']; ?>" required></div><div class="mb-3"><input type="text" name="no_hp" class="form-control" value="<?php echo $row['no_hp']; ?>" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="edit_karyawan" class="btn btn-primary">Simpan</button></div></form></div></div></div>
                <?php endforeach; ?>
            </tbody></table>
            <nav><ul class="pagination"><?php for ($i = 1; $i <= ceil($total_karyawan / $limit); $i++): ?><li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav>
        </div>

        <div id="nasabah" class="card p-4 mb-4">
            <h3>Kelola Nasabah</h3>
            <form method="POST" class="needs-validation" novalidate><div class="row"><div class="col-md-4 mb-3"><input type="text" name="username" class="form-control" placeholder="Username" required></div><div class="col-md-4 mb-3"><input type="text" name="password" class="form-control" placeholder="Password" required></div><div class="col-md-4 mb-3"><input type="text" name="nama" class="form-control" placeholder="Nama" required></div><div class="col-md-4 mb-3"><textarea name="alamat" class="form-control" placeholder="Alamat" required></textarea></div><div class="col-md-4 mb-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div><div class="col-md-4 mb-3"><input type="text" name="no_hp" class="form-control" placeholder="No HP" required></div><div class="col-md-4 mb-3"><input type="text" name="pekerjaan" class="form-control" placeholder="Pekerjaan" required></div><div class="col-md-4 mb-3"><input type="text" name="gaji" class="form-control currency" placeholder="Gaji" required></div><div class="col-12"><button type="submit" name="add_nasabah" class="btn btn-primary">Tambah</button></div></div></form>
            <table class="table mt-4"><thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>No HP</th><th>Pekerjaan</th><th>Gaji</th><th>Aksi</th></tr></thead><tbody>
                <?php foreach ($nasabah as $row): ?>
                    <tr><td><?php echo $row['id_nasabah']; ?></td><td><?php echo $row['nama']; ?></td><td><?php echo $row['email']; ?></td><td><?php echo $row['no_hp']; ?></td><td><?php echo $row['pekerjaan']; ?></td><td>Rp <?php echo number_format($row['gaji'], 0, ',', '.'); ?></td><td><a href="riwayat_nasabah.php?nama_nasabah=<?php echo urlencode($row['nama']); ?>" class="btn btn-sm btn-info" title="Lihat Riwayat">Riwayat</a><button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editNasabah<?php echo $row['id_nasabah']; ?>">Edit</button><form method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus?');"><input type="hidden" name="id_nasabah" value="<?php echo $row['id_nasabah']; ?>"><button type="submit" name="delete_nasabah" class="btn btn-sm btn-danger" title="Hapus">Hapus</button></form></td></tr>
                    <div class="modal fade" id="editNasabah<?php echo $row['id_nasabah']; ?>"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Nasabah</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" class="needs-validation" novalidate><div class="modal-body"><input type="hidden" name="id_nasabah" value="<?php echo $row['id_nasabah']; ?>"><div class="mb-3"><input type="text" name="username" class="form-control" value="<?php echo $row['username']; ?>" required></div><div class="mb-3"><input type="text" name="password" class="form-control" value="<?php echo $row['password']; ?>" required></div><div class="mb-3"><input type="text" name="nama" class="form-control" value="<?php echo $row['nama']; ?>" required></div><div class="mb-3"><textarea name="alamat" class="form-control" required><?php echo $row['alamat']; ?></textarea></div><div class="mb-3"><input type="email" name="email" class="form-control" value="<?php echo $row['email']; ?>" required></div><div class="mb-3"><input type="text" name="no_hp" class="form-control" value="<?php echo $row['no_hp']; ?>" required></div><div class="mb-3"><input type="text" name="pekerjaan" class="form-control" value="<?php echo $row['pekerjaan']; ?>" required></div><div class="mb-3"><input type="text" name="gaji" class="form-control currency" value="<?php echo number_format($row['gaji'], 0, ',', '.'); ?>" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="edit_nasabah" class="btn btn-primary">Simpan</button></div></form></div></div></div>
                <?php endforeach; ?>
            </tbody></table>
            <nav><ul class="pagination"><?php for ($i = 1; $i <= ceil($total_nasabah_all / $limit); $i++): ?><li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav>
        </div>

        <div id="pinjaman" class="card p-4 mb-4">
            <h3>Kelola Peminjaman</h3>
            <form method="POST" class="needs-validation" novalidate><div class="row"><div class="col-md-4 mb-3"><select name="id_nasabah" class="form-select" required><option value="">Pilih Nasabah</option><?php $stmt = $pdo->query("SELECT id_nasabah, nama FROM nasabah"); while ($row = $stmt->fetch()) echo "<option value='{$row['id_nasabah']}'>{$row['nama']}</option>"; ?></select></div><div class="col-md-4 mb-3"><input type="text" name="jumlah_pinjaman" class="form-control currency" placeholder="Jumlah Pinjaman" required></div><div class="col-md-4 mb-3"><input type="number" name="tenor" class="form-control" placeholder="Tenor (bulan)" required></div><div class="col-md-4 mb-3"><input type="number" step="0.01" name="bunga" class="form-control" placeholder="Bunga (%)" required></div><div class="col-12"><button type="submit" name="add_pinjaman" class="btn btn-primary">Tambah</button></div></div></form>
            <form method="POST" class="needs-validation mt-3" novalidate><div class="row"><div class="col-md-4 mb-3"><select name="id_pinjaman" class="form-select" required><option value="">Pilih Peminjaman</option><?php $stmt = $pdo->query("SELECT p.id_pinjaman, n.nama, p.total_pinjaman_bunga FROM pinjaman p JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE p.status = 'aktif'"); while ($row = $stmt->fetch()) echo "<option value='{$row['id_pinjaman']}'>Pinjaman #{$row['id_pinjaman']} - {$row['nama']} (Rp " . number_format($row['total_pinjaman_bunga'], 0, ',', '.') . ")</option>"; ?></select></div><div class="col-md-4 mb-3"><input type="text" name="tambahan_jumlah" class="form-control currency" placeholder="Tambahan Jumlah (Rp)" required></div><div class="col-md-4 mb-3"><input type="number" name="tambahan_tenor" class="form-control" placeholder="Tambahan Tenor" required></div><div class="col-12"><button type="submit" name="extend_pinjaman" class="btn btn-primary">Perpanjang</button></div></div></form>
            <table class="table mt-4"><thead><tr><th>ID</th><th>Nasabah</th><th>Jumlah (Bunga)</th><th>Tanggal</th><th>Tenor</th><th>Bunga</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                <?php foreach ($pinjaman as $row): ?>
                    <tr><td><?php echo $row['id_pinjaman']; ?></td><td><?php echo $row['nama']; ?></td><td>Rp <?php echo number_format($row['total_pinjaman_bunga'], 0, ',', '.'); ?></td><td><?php echo $row['tanggal_pinjam']; ?></td><td><?php echo $row['tenor']; ?></td><td><?php echo $row['bunga']; ?>%</td><td><?php echo $row['status']; ?></td><td><form method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus?');"><input type="hidden" name="id_pinjaman" value="<?php echo $row['id_pinjaman']; ?>"><button type="submit" name="delete_pinjaman" class="btn btn-sm btn-danger" title="Hapus">Hapus</button></form></td></tr>
                <?php endforeach; ?>
            </tbody></table>
            <nav><ul class="pagination"><?php for ($i = 1; $i <= ceil($total_pinjaman / $limit); $i++): ?><li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav>
        </div>

        <div id="cicilan" class="card p-4 mb-4">
            <h3>Verifikasi Cicilan</h3>
            <table class="table"><thead><tr><th>ID</th><th>Nasabah</th><th>Jumlah</th><th>Metode</th><th>Bukti</th><th>Aksi</th></tr></thead><tbody>
                <?php foreach ($cicilan as $row): ?>
                    <tr><td><?php echo $row['id_cicilan']; ?></td><td><?php echo $row['nama']; ?></td><td>Rp <?php echo number_format($row['jumlah_bayar'], 0, ',', '.'); ?></td><td><?php echo ucfirst(str_replace('_', ' ', $row['metode_pembayaran'])); ?></td><td><a href="<?php echo $row['bukti_bayar']; ?>" target="_blank" class="btn btn-sm btn-primary" title="Lihat Bukti">Lihat</a></td><td><form method="POST"><input type="hidden" name="id_cicilan" value="<?php echo $row['id_cicilan']; ?>"><select name="status" class="form-select d-inline w-auto"><option value="disetujui">Disetujui</option><option value="ditolak">Ditolak</option></select><button type="submit" name="verify_cicilan" class="btn btn-sm btn-primary" title="Verifikasi">Verifikasi</button></form></td></tr>
                <?php endforeach; ?>
            </tbody></table>
            <nav><ul class="pagination"><?php for ($i = 1; $i <= ceil($total_cicilan / $limit); $i++): ?><li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav>
        </div>

        <div id="kas" class="card p-4 mb-4">
            <h3>Transaksi Kas</h3>
            <form method="POST" class="needs-validation mb-3" novalidate><div class="row"><div class="col-md-6 mb-3"><input type="text" name="keterangan" class="form-control" placeholder="Keterangan" required><div class="invalid-feedback">Keterangan wajib diisi.</div></div><div class="col-md-3 mb-3"><input type="text" name="debit" class="form-control currency" placeholder="Debit (Rp)" required><div class="invalid-feedback">Debit harus lebih dari 0.</div></div><div class="col-md-3 mb-3"><input type="text" name="kredit" class="form-control currency" placeholder="Kredit (Rp)" required><div class="invalid-feedback">Kredit harus lebih dari 0.</div></div><div class="col-12"><button type="submit" name="add_kas" class="btn btn-primary">Tambah Transaksi</button></div></div></form>
            <table class="table"><thead><tr><th>Tanggal</th><th>Keterangan</th><th>Debit</th><th>Kredit</th><th>Saldo</th></tr></thead><tbody>
                <?php foreach ($kas as $row): ?>
                    <tr><td><?php echo $row['tanggal']; ?></td><td><?php echo $row['keterangan']; ?></td><td>Rp <?php echo number_format($row['debit'], 0, ',', '.'); ?></td><td>Rp <?php echo number_format($row['kredit'], 0, ',', '.'); ?></td><td>Rp <?php echo number_format($row['saldo'], 0, ',', '.'); ?></td></tr>
                <?php endforeach; ?>
            </tbody></table>
            <nav><ul class="pagination"><?php for ($i = 1; $i <= ceil($total_kas / $limit); $i++): ?><li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav>
        </div>

        <div id="metode" class="card p-4 mb-4">
            <h3>Metode Pembayaran</h3>
            <form method="POST" class="needs-validation" enctype="multipart/form-data" novalidate><div class="row"><div class="col-md-4 mb-3"><select name="jenis" class="form-select" required><option value="">Pilih Jenis</option><option value="transfer_bank">Transfer Bank</option><option value="qris">QRIS</option></select></div><div class="col-md-4 mb-3"><input type="text" name="nama_bank" class="form-control" placeholder="Nama Bank" required></div><div class="col-md-4 mb-3"><input type="text" name="nomor_rekening" class="form-control" placeholder="Nomor Rekening"></div><div class="col-md-4 mb-3"><input type="file" name="qr_code" class="form-control" accept="image/*"></div><div class="col-12"><button type="submit" name="add_metode" class="btn btn-primary">Tambah</button></div></div></form>
            <table class="table mt-4"><thead><tr><th>Jenis</th><th>Nama Bank</th><th>Nomor Rekening</th><th>QR Code</th><th>Aksi</th></tr></thead><tbody>
                <?php foreach ($metode_pembayaran as $row): ?>
                    <tr><td><?php echo ucfirst(str_replace('_', ' ', $row['jenis'])); ?></td><td><?php echo $row['nama_bank']; ?></td><td><?php echo $row['nomor_rekening'] ?: '-'; ?></td><td><?php echo $row['qr_code_path'] ? '<a href="' . $row['qr_code_path'] . '" target="_blank" class="btn btn-sm btn-primary">Lihat</a>' : '-'; ?></td><td><button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editMetode<?php echo $row['id_metode']; ?>">Edit</button><form method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus?');"><input type="hidden" name="id_metode" value="<?php echo $row['id_metode']; ?>"><button type="submit" name="delete_metode" class="btn btn-sm btn-danger">Hapus</button></form></td></tr>
                    <div class="modal fade" id="editMetode<?php echo $row['id_metode']; ?>"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Metode</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate><div class="modal-body"><input type="hidden" name="id_metode" value="<?php echo $row['id_metode']; ?>"><div class="mb-3"><select name="jenis" class="form-select" required><option value="transfer_bank" <?php echo $row['jenis'] == 'transfer_bank' ? 'selected' : ''; ?>>Transfer Bank</option><option value="qris" <?php echo $row['jenis'] == 'qris' ? 'selected' : ''; ?>>QRIS</option></select></div><div class="mb-3"><input type="text" name="nama_bank" class="form-control" value="<?php echo $row['nama_bank']; ?>" required></div><div class="mb-3"><input type="text" name="nomor_rekening" class="form-control" value="<?php echo $row['nomor_rekening']; ?>"></div><div class="mb-3"><input type="file" name="qr_code" class="form-control" accept="image/*"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="edit_metode" class="btn btn-primary">Simpan</button></div></form></div></div></div>
                <?php endforeach; ?>
            </tbody></table>
            <nav><ul class="pagination"><?php for ($i = 1; $i <= ceil($total_metode / $limit); $i++): ?><li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li><?php endfor; ?></ul></nav>
        </div>

        <div id="laporan" class="card p-4 mb-4">
            <h3>Laporan Keuangan</h3>
            <form method="POST" class="mb-3"><div class="row"><div class="col-md-4"><input type="text" name="periode" class="form-control" placeholder="Periode (YYYY-MM)" required></div><div class="col-md-2"><button type="submit" name="generate_report" class="btn btn-primary">Cetak</button></div></div></form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>