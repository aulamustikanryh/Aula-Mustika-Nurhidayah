<?php
require_once 'config.php';

function addMetode($pdo, $jenis, $nama_bank, $nomor_rekening, $qr_code) {
    try {
        if (!in_array($jenis, ['transfer_bank', 'qris'])) {
            return ['error' => 'Jenis metode pembayaran tidak valid.'];
        }
        $qr_code_path = '';
        if ($qr_code['name']) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $qr_code_path = $upload_dir . uniqid() . '_' . basename($qr_code['name']);
            if (!move_uploaded_file($qr_code['tmp_name'], $qr_code_path)) {
                return ['error' => 'Gagal mengunggah file QR code.'];
            }
        }
        $stmt = $pdo->prepare("INSERT INTO metode_pembayaran (jenis, nama_bank, nomor_rekening, qr_code_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([$jenis, $nama_bank, $nomor_rekening, $qr_code_path]);
        return ['success' => 'Metode pembayaran berhasil ditambahkan.'];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['error' => 'Gagal menambah metode pembayaran: Nomor rekening atau data terkait sudah ada.'];
        }
        return ['error' => 'Gagal menambah metode pembayaran: ' . $e->getMessage()];
    }
}

function editMetode($pdo, $id_metode, $jenis, $nama_bank, $nomor_rekening, $qr_code) {
    try {
        if (!in_array($jenis, ['transfer_bank', 'qris'])) {
            return ['error' => 'Jenis metode pembayaran tidak valid.'];
        }
        $stmt = $pdo->prepare("SELECT qr_code_path FROM metode_pembayaran WHERE id_metode = ?");
        $stmt->execute([$id_metode]);
        $current = $stmt->fetch();
        if (!$current) {
            return ['error' => 'Metode pembayaran tidak ditemukan.'];
        }
        $qr_code_path = $current['qr_code_path'];
        if ($qr_code['name']) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $qr_code_path = $upload_dir . uniqid() . '_' . basename($qr_code['name']);
            if (!move_uploaded_file($qr_code['tmp_name'], $qr_code_path)) {
                return ['error' => 'Gagal mengunggah file QR code.'];
            }
            if ($current['qr_code_path'] && file_exists($current['qr_code_path'])) {
                unlink($current['qr_code_path']);
            }
        }
        $stmt = $pdo->prepare("UPDATE metode_pembayaran SET jenis = ?, nama_bank = ?, nomor_rekening = ?, qr_code_path = ? WHERE id_metode = ?");
        $stmt->execute([$jenis, $nama_bank, $nomor_rekening, $qr_code_path, $id_metode]);
        return ['success' => 'Metode pembayaran berhasil diperbarui.'];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['error' => 'Gagal memperbarui metode pembayaran: Nomor rekening atau data terkait sudah ada.'];
        }
        return ['error' => 'Gagal memperbarui metode pembayaran: ' . $e->getMessage()];
    }
}

function deleteMetode($pdo, $id_metode) {
    try {
        $stmt = $pdo->prepare("SELECT qr_code_path FROM metode_pembayaran WHERE id_metode = ?");
        $stmt->execute([$id_metode]);
        $metode = $stmt->fetch();
        if (!$metode) {
            return ['error' => 'Metode pembayaran tidak ditemukan.'];
        }
        if ($metode['qr_code_path'] && file_exists($metode['qr_code_path'])) {
            unlink($metode['qr_code_path']);
        }
        $stmt = $pdo->prepare("DELETE FROM metode_pembayaran WHERE id_metode = ?");
        $stmt->execute([$id_metode]);
        return ['success' => 'Metode pembayaran berhasil dihapus.'];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['error' => 'Gagal menghapus metode pembayaran: Metode ini masih digunakan oleh cicilan.'];
        }
        return ['error' => 'Gagal menghapus metode pembayaran: ' . $e->getMessage()];
    }
}
?>