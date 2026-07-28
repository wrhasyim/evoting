<?php
// admin/export_hasil.php

session_start();
require '../config/koneksi.php';

// Keamanan halaman
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Ambil ID Eskul dari URL
$id_eskul = isset($_GET['id_eskul']) ? $_GET['id_eskul'] : null;

if (!$id_eskul) {
    die("Pilih ekstrakurikuler terlebih dahulu.");
}

// Ambil Nama Eskul untuk nama file
$stmt_nama = $pdo->prepare("SELECT nama_eskul FROM eskul WHERE id_eskul = ?");
$stmt_nama->execute([$id_eskul]);
$nama_eskul = $stmt_nama->fetchColumn();

// Ambil Total Suara
$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM suara_masuk WHERE id_eskul = ?");
$stmt_total->execute([$id_eskul]);
$total_suara = $stmt_total->fetchColumn();

// Ambil Data Perolehan
$stmt_hasil = $pdo->prepare("
    SELECT k.no_urut, k.nama_paslon, k.kelas_paslon, COUNT(s.id_suara) AS perolehan 
    FROM kandidat k 
    LEFT JOIN suara_masuk s ON k.id_kandidat = s.id_kandidat 
    WHERE k.id_eskul = ? AND k.status_aktif = 1 
    GROUP BY k.id_kandidat 
    ORDER BY perolehan DESC, k.no_urut ASC
");
$stmt_hasil->execute([$id_eskul]);
$data_hasil = $stmt_hasil->fetchAll();

// Memaksa browser untuk mengunduh file sebagai Excel (.xls)
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Hasil_Pemilu_" . str_replace(' ', '_', $nama_eskul) . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
?>

<!-- Format HTML tabel ini akan otomatis dibaca sebagai baris dan kolom oleh Microsoft Excel -->
<table border="1">
    <thead>
        <tr>
            <th colspan="5" style="font-size: 16px; font-weight: bold; text-align: center;">LAPORAN HASIL PEMILIHAN E-VOTING</th>
        </tr>
        <tr>
            <th colspan="5" style="text-align: center;">Kategori: <?= htmlspecialchars($nama_eskul); ?></th>
        </tr>
        <tr>
            <th colspan="5" style="text-align: center;">Total Suara Masuk: <?= $total_suara; ?> Suara</th>
        </tr>
        <tr>
            <th>Peringkat</th>
            <th>No. Urut</th>
            <th>Nama Pasangan Calon</th>
            <th>Kelas</th>
            <th>Perolehan Suara</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($data_hasil) > 0): ?>
            <?php $rank = 1; foreach ($data_hasil as $row): ?>
                <tr>
                    <td style="text-align: center;"><?= $rank++; ?></td>
                    <td style="text-align: center;"><?= $row['no_urut']; ?></td>
                    <td><?= htmlspecialchars($row['nama_paslon']); ?></td>
                    <td><?= htmlspecialchars($row['kelas_paslon']); ?></td>
                    <td style="text-align: right;"><?= $row['perolehan']; ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="text-align: center;">Belum ada data kandidat atau suara.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>