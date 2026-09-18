<?php
require_once 'config.php';

function addKas($pdo, $keterangan, $debit, $kredit) {
    try {
        $debit = (int)$debit;
        $kredit = (int)$kredit;
        if ($debit < 0 || $kredit < 0) {
            return ['error' => 'Debit atau kredit tidak valid: Nilai tidak boleh negatif.'];
        }
        $stmt = $pdo->query("SELECT saldo FROM kas_koperasi ORDER BY id_kas DESC LIMIT 1");
        $saldo_sebelumnya = $stmt->fetchColumn() ?: 0;
        $saldo_baru = $saldo_sebelumnya + $debit - $kredit;
        $stmt = $pdo->prepare("INSERT INTO kas_koperasi (tanggal, keterangan, debit, kredit, saldo) VALUES (CURDATE(), ?, ?, ?, ?)");
        $stmt->execute([$keterangan, $debit, $kredit, $saldo_baru]);
        return ['success' => 'Transaksi kas berhasil ditambahkan.'];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['error' => 'Gagal menambah transaksi kas: Data terkait sudah ada atau tidak valid.'];
        }
        return ['error' => 'Gagal menambah transaksi kas: ' . $e->getMessage()];
    }
}

function setSaldoAwal($pdo, $saldo_awal) {
    try {
        $saldo_awal = (int)$saldo_awal;
        if ($saldo_awal < 0) {
            return ['error' => 'Saldo awal tidak valid: Nilai tidak boleh negatif.'];
        }
        $stmt = $pdo->query("SELECT COUNT(*) FROM kas_koperasi");
        if ($stmt->fetchColumn() > 0) {
            return ['error' => 'Saldo awal hanya bisa diatur sekali saat tabel kas masih kosong.'];
        }
        $stmt = $pdo->prepare("INSERT INTO kas_koperasi (tanggal, keterangan, debit, kredit, saldo) VALUES (CURDATE(), 'Saldo Awal', ?, 0, ?)");
        $stmt->execute([$saldo_awal, $saldo_awal]);
        return ['success' => 'Saldo awal berhasil diatur.'];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            return ['error' => 'Gagal mengatur saldo awal: Data terkait sudah ada atau tidak valid.'];
        }
        return ['error' => 'Gagal mengatur saldo awal: ' . $e->getMessage()];
    }
}
?>