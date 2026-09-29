<?php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    die("Unauthorized");
}

$id_eskul = $_GET['id_eskul'] ?? null;
if (!$id_eskul) {
    die("ID Eskul dibutuhkan.");
}

$stmt_eskul = $pdo->prepare("SELECT * FROM eskul WHERE id_eskul = ?");
$stmt_eskul->execute([$id_eskul]);
$eskul = $stmt_eskul->fetch();

if (!$eskul) {
    die("Eskul tidak ditemukan.");
}

$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM suara_masuk WHERE id_eskul = ?");
$stmt_total->execute([$id_eskul]);
$total_suara = $stmt_total->fetchColumn();

$stmt_hasil = $pdo->prepare("
    SELECT k.no_urut, k.nama_paslon, k.kelas_paslon, COUNT(s.id_suara) AS perolehan
    FROM kandidat k
    LEFT JOIN suara_masuk s ON k.id_kandidat = s.id_kandidat
    WHERE k.id_eskul = ? AND k.status_aktif = 1
    GROUP BY k.id_kandidat
    ORDER BY perolehan DESC, k.no_urut ASC
");
$stmt_hasil->execute([$id_eskul]);
$hasil = $stmt_hasil->fetchAll();

$tanggal = date('d F Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara - <?= htmlspecialchars($eskul['nama_eskul']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Times New Roman', Times, serif; background: #fff; color: #000; }
        .kop-surat { border-bottom: 3px solid #000; margin-bottom: 30px; padding-bottom: 10px; }
        .print-btn { position: fixed; bottom: 30px; right: 30px; }
        @media print { .print-btn { display: none; } }
        .ttd-box { height: 100px; }
    </style>
</head>
<body class="p-5">
    <div class="print-btn">
        <button class="btn btn-primary shadow" onclick="window.print()"><i class="fas fa-print"></i> Cetak Dokumen</button>
    </div>

    <div class="kop-surat text-center">
        <h3 class="mb-1 text-uppercase fw-bold">PANITIA PEMILIHAN EKSTRAKURIKULER</h3>
        <h4 class="mb-1">SMK TARUNA KARYA MANDIRI</h4>
        <p class="mb-0">Jl. Contoh Alamat Sekolah No. 123, Kota Pendidikan</p>
    </div>

    <div class="text-center mb-5">
        <h5 class="fw-bold text-decoration-underline">BERITA ACARA HASIL PEMILIHAN</h5>
        <p>Nomor: 01/BA-EVOTING/<?= date('Y'); ?></p>
    </div>

    <p class="mb-4 text-justify" style="text-indent: 40px; line-height: 1.8;">
        Pada hari ini, tanggal <b><?= $tanggal; ?></b>, telah dilaksanakan proses perhitungan suara elektronik (E-Voting) untuk pemilihan ketua ekstrakurikuler <b><?= htmlspecialchars($eskul['nama_eskul']); ?></b>. Berdasarkan data yang tersimpan di dalam sistem secara transparan dan akuntabel, diperoleh hasil sebagai berikut:
    </p>

    <div class="mb-4 px-5">
        <p class="mb-1">Total Suara Sah Masuk: <b><?= $total_suara; ?> Suara</b></p>
    </div>

    <table class="table table-bordered border-dark text-center align-middle mb-5">
        <thead class="table-light">
            <tr>
                <th>Peringkat</th>
                <th>No. Urut</th>
                <th>Nama Pasangan Calon</th>
                <th>Kelas</th>
                <th>Perolehan Suara</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($hasil) > 0): ?>
                <?php $rank = 1; foreach ($hasil as $row): ?>
                    <tr>
                        <td><?= $rank++; ?></td>
                        <td><?= htmlspecialchars($row['no_urut']); ?></td>
                        <td class="text-start fw-bold"><?= htmlspecialchars($row['nama_paslon']); ?></td>
                        <td><?= htmlspecialchars($row['kelas_paslon']); ?></td>
                        <td class="fw-bold"><?= $row['perolehan']; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="5">Belum ada kandidat atau suara.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="mb-5 text-justify" style="text-indent: 40px; line-height: 1.8;">
        Demikian berita acara ini dibuat dengan sebenar-benarnya berdasarkan hasil perolehan suara sah di dalam sistem tanpa adanya rekayasa dari pihak manapun, agar dapat dipergunakan sebagaimana mestinya.
    </p>

    <div class="row text-center mt-5">
        <div class="col-6">
            <p>Saksi Pemilihan,</p>
            <div class="ttd-box"></div>
            <p class="fw-bold text-decoration-underline mb-0">( ........................................ )</p>
        </div>
        <div class="col-6">
            <p>Ketua Panitia,</p>
            <div class="ttd-box"></div>
            <p class="fw-bold text-decoration-underline mb-0">( ........................................ )</p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>
