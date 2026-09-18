<?php
require_once 'config.php';

function verifyCicilan($pdo, $id_cicilan, $status, $id_karyawan) {
    try {
        $stmt = $pdo->prepare("SELECT c.id_pinjaman, c.jumlah_bayar, c.tanggal_bayar, c.metode_pembayaran, n.nama, n.email FROM cicilan c JOIN pinjaman p ON c.id_pinjaman = p.id_pinjaman JOIN nasabah n ON p.id_nasabah = n.id_nasabah WHERE c.id_cicilan = ? AND c.status = 'menunggu'");
        $stmt->execute([$id_cicilan]);
        $cicilan = $stmt->fetch();
        if (!$cicilan) {
            return ['error' => 'Cicilan tidak ditemukan atau sudah diverifikasi.'];
        }
        $stmt = $pdo->prepare("UPDATE cicilan SET status = ?, id_karyawan = ?, tanggal_verifikasi = CURDATE() WHERE id_cicilan = ?");
        $stmt->execute([$status, $id_karyawan, $id_cicilan]);
        if ($status == 'disetujui') {
            $stmt = $pdo->prepare("INSERT INTO kas_koperasi (tanggal, keterangan, debit, kredit, saldo) VALUES (CURDATE(), ?, ?, 0, (SELECT saldo FROM kas_koperasi ORDER BY id_kas DESC LIMIT 1) + ?)");
            $stmt->execute(["Pembayaran cicilan ID $id_cicilan", $cicilan['jumlah_bayar'], $cicilan['jumlah_bayar']]);
            // Kirim email ke nasabah
            $body = "<h3>Pembayaran Cicilan Disetujui</h3><p>Pembayaran cicilan Anda telah disetujui dengan detail berikut:</p><ul><li>ID Cicilan: $id_cicilan</li><li>Jumlah: Rp " . number_format($cicilan['jumlah_bayar'], 0, ',', '.') . "</li><li>Metode Pembayaran: " . ucfirst(str_replace('_', ' ', $cicilan['metode_pembayaran'])) . "</li><li>Tanggal: {$cicilan['tanggal_bayar']}</li></ul><p>Terima kasih atas pembayaran Anda.</p>";
            $emailResult = sendEmail($cicilan['email'], "Pembayaran Cicilan Disetujui di Koperasi Cilacap", $body);
            if ($emailResult !== true) {
                return ['error' => $emailResult];
            }
        } else {
            // Kirim email ke nasabah untuk status ditolak
            $body = "<h3>Pembayaran Cicilan Ditolak</h3><p>Pembayaran cicilan Anda dengan ID $id_cicilan telah ditolak.</p><p>Detail:</p><ul><li>Jumlah: Rp " . number_format($cicilan['jumlah_bayar'], 0, ',', '.') . "</li><li>Metode Pembayaran: " . ucfirst(str_replace('_', ' ', $cicilan['metode_pembayaran'])) . "</li><li>Tanggal: {$cicilan['tanggal_bayar']}</li></ul><p>Silakan hubungi admin untuk informasi lebih lanjut.</p>";
            $emailResult = sendEmail($cicilan['email'], "Pembayaran Cicilan Ditolak di Koperasi Cilacap", $body);
            if ($emailResult !== true) {
                return ['error' => $emailResult];
            }
        }
        return ['success' => 'Cicilan berhasil diverifikasi.'];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['error' => 'Gagal memverifikasi cicilan: Data terkait mungkin sudah ada atau tidak valid.'];
        }
        return ['error' => 'Gagal memverifikasi cicilan: ' . $e->getMessage()];
    }
}
?>