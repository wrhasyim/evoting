<?php
// admin/cetak_pin.php

session_start();
require '../config/koneksi.php';

// 1. PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// 2. MENDAPATKAN PERIODE AKTIF
$stmt_periode = $pdo->query("SELECT id_periode, nama_periode FROM periode WHERE status_aktif = 1 LIMIT 1");
$periode_aktif = $stmt_periode->fetch();

if (!$periode_aktif) {
    die("<h3>Gagal memuat data: Belum ada Tahun Ajaran yang diaktifkan.</h3>");
}

$id_periode_aktif = $periode_aktif['id_periode'];
$nama_periode_aktif = $periode_aktif['nama_periode'];

// 3. MENGAMBIL DATA SISWA UNTUK PERIODE AKTIF
$stmt_siswa = $pdo->prepare("SELECT nis, nama_siswa, kelas, pin FROM siswa WHERE id_periode = ? AND status_aktif = 1 ORDER BY kelas ASC, nama_siswa ASC");
$stmt_siswa->execute([$id_periode_aktif]);
$data_siswa = $stmt_siswa->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak PIN Pemilih - E-Voting</title>
    <!-- Kita tetap menggunakan Bootstrap agar tabel rapi, tetapi menambahkan CSS khusus print -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; background-color: #fff; padding: 20px; }
        .kop-surat { text-align: center; border-bottom: 3px solid #000; padding-bottom: 15px; margin-bottom: 20px; }
        .kop-surat h2 { margin: 0; font-weight: bold; text-transform: uppercase; }
        .kop-surat p { margin: 5px 0 0 0; font-size: 16px; }
        
        .table th { background-color: #f8f9fa !important; font-weight: bold; }
        
        /* Mengatur agar tombol print tidak ikut tercetak di kertas */
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

    <!-- Tombol Navigasi (Akan hilang saat dicetak) -->
    <div class="mb-4 no-print text-center">
        <a href="siswa.php" class="btn btn-secondary me-2">Kembali ke Manajemen Siswa</a>
        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Cetak / Simpan PDF</button>
        <p class="text-muted mt-2 small">Tips: Anda juga bisa menyorot (blok) tabel di bawah ini dan mem-paste-nya ke Excel.</p>
    </div>

    <!-- Area yang akan dicetak -->
    <div class="print-area">
        <div class="kop-surat">
            <h2>Daftar PIN Akses E-Voting</h2>
            <p>SMK Taruna Karya Mandiri</p>
            <p>Periode: <b><?= htmlspecialchars($nama_periode_aktif); ?></b></p>
        </div>

        <table class="table table-bordered table-sm align-middle">
            <thead>
                <tr class="text-center">
                    <th width="5%">No</th>
                    <th width="15%">NIS</th>
                    <th width="40%">Nama Lengkap</th>
                    <th width="20%">Kelas</th>
                    <th width="20%">PIN Akses</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($data_siswa) > 0): ?>
                    <?php $no = 1; foreach ($data_siswa as $row): ?>
                        <tr>
                            <td class="text-center"><?= $no++; ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['nis']); ?></td>
                            <td><?= htmlspecialchars($row['nama_siswa']); ?></td>
                            <td class="text-center"><?= htmlspecialchars($row['kelas']); ?></td>
                            <td class="text-center fw-bold" style="font-family: monospace; font-size: 1.1rem; letter-spacing: 2px;">
                                <?= htmlspecialchars($row['pin']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">Belum ada data siswa untuk periode ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="mt-4 text-end" style="font-size: 14px;">
            <p>Dicetak pada: <?= date('d-m-Y H:i'); ?></p>
            <p>Panitia Pemilihan</p>
            <br><br><br>
            <p>_______________________</p>
        </div>
    </div>

</body>
</html>