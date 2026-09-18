<?php
require_once 'config.php';

function addPinjaman($pdo, $id_nasabah, $jumlah_pinjaman, $tenor, $bunga) {
    try {
        $jumlah_pinjaman = (int)$jumlah_pinjaman;
        $tenor = (int)$tenor;
        $bunga = (float)$bunga;
        if ($jumlah_pinjaman <= 0 || $tenor <= 0 || $bunga < 0) {
            return ['error' => 'Input tidak valid: Jumlah pinjaman, tenor, atau bunga tidak boleh negatif.'];
        }
        $total_pinjaman_bunga = $jumlah_pinjaman * (1 + ($bunga / 100));
        $stmt = $pdo->prepare("INSERT INTO pinjaman (id_nasabah, jumlah_pinjaman, total_pinjaman_bunga, tanggal_pinjam, tenor, bunga, status) VALUES (?, ?, ?, CURDATE(), ?, ?, 'aktif')");
        $stmt->execute([$id_nasabah, $jumlah_pinjaman, $total_pinjaman_bunga, $tenor, $bunga]);
        $id_pinjaman = $pdo->lastInsertId();
        // Ambil data nasabah untuk email
        $stmt = $pdo->prepare("SELECT nama, email FROM nasabah WHERE id_nasabah = ?");
        $stmt->execute([$id_nasabah]);
        $nasabah = $stmt->fetch();
        if ($nasabah) {
            $body = "<h3>Pengajuan Pinjaman Berhasil</h3><p>Pengajuan pinjaman Anda telah disetujui dengan detail berikut:</p><ul><li>ID Pinjaman: $id_pinjaman</li><li>Jumlah Pinjaman: Rp " . number_format($jumlah_pinjaman, 0, ',', '.') . "</li><li>Total (dengan bunga): Rp " . number_format($total_pinjaman_bunga, 0, ',', '.') . "</li><li>Tenor: $tenor bulan</li><li>Bunga: $bunga%</li><li>Tanggal: " . date('Y-m-d') . "</li></ul><p>Silakan hubungi admin untuk informasi lebih lanjut.</p>";
            $emailResult = sendEmail($nasabah['email'], "Pengajuan Pinjaman Berhasil di Koperasi Cilacap", $body);
            if ($emailResult !== true) {
                return ['error' => $emailResult];
            }
        }
        return ['success' => 'Pinjaman berhasil ditambahkan.'];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['error' => 'Gagal menambah pinjaman: Data terkait nasabah mungkin sudah ada atau tidak valid.'];
        }
        return ['error' => 'Gagal menambah pinjaman: ' . $e->getMessage()];
    }
}

function extendPinjaman($pdo, $id_pinjaman, $tambahan_jumlah, $tambahan_tenor) {
    try {
        $tambahan_jumlah = (int)$tambahan_jumlah;
        $tambahan_tenor = (int)$tambahan_tenor;
        if ($tambahan_jumlah <= 0 || $tambahan_tenor <= 0) {
            return ['error' => 'Input tidak valid: Tambahan jumlah atau tenor tidak boleh negatif.'];
        }
        $stmt = $pdo->prepare("SELECT p.jumlah_pinjaman, p.total_pinjaman_bunga, p.tenor, p.bunga, n.nama, n.email FROM pinjaman p JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE p.id_pinjaman = ? AND p.status = 'aktif'");
        $stmt->execute([$id_pinjaman]);
        $pinjaman = $stmt->fetch();
        if (!$pinjaman) {
            return ['error' => 'Pinjaman tidak ditemukan atau tidak aktif.'];
        }
        $new_jumlah = $pinjaman['jumlah_pinjaman'] + $tambahan_jumlah;
        $new_total = $new_jumlah * (1 + ($pinjaman['bunga'] / 100));
        $new_tenor = $pinjaman['tenor'] + $tambahan_tenor;
        $stmt = $pdo->prepare("UPDATE pinjaman SET jumlah_pinjaman = ?, total_pinjaman_bunga = ?, tenor = ? WHERE id_pinjaman = ?");
        $stmt->execute([$new_jumlah, $new_total, $new_tenor, $id_pinjaman]);
        // Kirim email ke nasabah
        $body = "<h3>Perpanjangan Pinjaman Berhasil</h3><p>Pinjaman Anda telah diperpanjang dengan detail berikut:</p><ul><li>ID Pinjaman: $id_pinjaman</li><li>Tambahan Jumlah: Rp " . number_format($tambahan_jumlah, 0, ',', '.') . "</li><li>Jumlah Baru: Rp " . number_format($new_jumlah, 0, ',', '.') . "</li><li>Total (dengan bunga): Rp " . number_format($new_total, 0, ',', '.') . "</li><li>Tambahan Tenor: $tambahan_tenor bulan</li><li>Tenor Baru: $new_tenor bulan</li></ul><p>Silakan hubungi admin untuk informasi lebih lanjut.</p>";
        $emailResult = sendEmail($pinjaman['email'], "Perpanjangan Pinjaman di Koperasi Cilacap", $body);
        if ($emailResult !== true) {
            return ['error' => $emailResult];
        }
        return ['success' => 'Pinjaman berhasil diperpanjang.'];
    } catch (PDOException $e) {
        return ['error' => 'Gagal memperpanjang pinjaman: ' . $e->getMessage()];
    }
}

function deletePinjaman($pdo, $id_pinjaman) {
    try {
        $stmt = $pdo->prepare("SELECT p.id_pinjaman, n.nama, n.email FROM pinjaman p JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE p.id_pinjaman = ?");
        $stmt->execute([$id_pinjaman]);
        $pinjaman = $stmt->fetch();
        if (!$pinjaman) {
            return ['error' => 'Pinjaman tidak ditemukan.'];
        }
        $stmt = $pdo->prepare("DELETE FROM pinjaman WHERE id_pinjaman = ?");
        $stmt->execute([$id_pinjaman]);
        if ($stmt->rowCount() == 0) {
            return ['error' => 'Pinjaman tidak ditemukan.'];
        }
        // Kirim email ke nasabah
        $body = "<h3>Pemberitahuan Penghapusan Pinjaman</h3><p>Pinjaman Anda dengan ID {$pinjaman['id_pinjaman']} telah dihapus dari sistem Koperasi Cilacap.</p><p>Jika ini adalah kesalahan, silakan hubungi admin.</p>";
        $emailResult = sendEmail($pinjaman['email'], "Penghapusan Pinjaman di Koperasi Cilacap", $body);
        if ($emailResult !== true) {
            return ['error' => $emailResult];
        }
        return ['success' => 'Pinjaman berhasil dihapus.'];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['error' => 'Gagal menghapus pinjaman: Pinjaman ini masih memiliki cicilan terkait.'];
        }
        return ['error' => 'Gagal menghapus pinjaman: ' . $e->getMessage()];
    }
}
?>