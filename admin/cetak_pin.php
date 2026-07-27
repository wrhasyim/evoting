<?php
// admin/cetak_pin.php

session_start();
require '../config/koneksi.php';

// PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Mengambil seluruh data siswa yang aktif, diurutkan berdasarkan kelas lalu nama
$stmt = $pdo->query("SELECT nis, nama_siswa, kelas, pin FROM siswa WHERE status_aktif = 1 ORDER BY kelas ASC, nama_siswa ASC");
$data_siswa = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($data_siswa) == 0) {
    die("<h3 style='text-align:center; font-family:sans-serif; margin-top:50px;'>Belum ada data siswa untuk dicetak.</h3>");
}

// LOGIKA PENGELOMPOKAN: Memecah data tunggal menjadi kelompok per kelas
$data_per_kelas = [];
foreach ($data_siswa as $siswa) {
    $kelas = $siswa['kelas'];
    // Jika array untuk kelas ini belum ada, buatkan
    if (!isset($data_per_kelas[$kelas])) {
        $data_per_kelas[$kelas] = [];
    }
    // Masukkan data siswa ke dalam array kelasnya
    $data_per_kelas[$kelas][] = $siswa;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak PIN Pemilih - E-Voting</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* RESET DASAR */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: #f0f2f5; color: #333; padding: 20px; }
        
        /* TOMBOL CETAK MENGAMBANG */
        .btn-print {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: #0d6efd;
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(13,110,253,0.4);
            z-index: 1000;
            transition: all 0.3s;
        }
        .btn-print:hover { background-color: #0b5ed7; transform: translateY(-3px); }

        /* WADAH KERTAS */
        .container { max-width: 210mm; margin: 0 auto; background: white; padding: 20mm; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        
        /* JUDUL KELAS */
        .class-header { text-align: center; font-size: 24px; font-weight: 700; margin-bottom: 20px; color: #2c3e50; border-bottom: 2px dashed #ccc; padding-bottom: 10px; }
        
        /* GRID KARTU PIN */
        .cards-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 40px; }
        
        /* DESAIN KARTU PIN (SEPERTI TIKET) */
        .card-pin {
            border: 2px dashed #6c757d; /* Garis putus-putus untuk digunting */
            border-radius: 8px;
            padding: 15px;
            background-color: #fff;
            position: relative;
        }
        .card-header { text-align: center; font-size: 12px; font-weight: 700; color: #0d6efd; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .card-body { font-size: 13px; line-height: 1.5; }
        .card-body span { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pin-box {
            margin-top: 10px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            text-align: center;
            padding: 8px;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 2px;
            color: #dc3545; /* Warna merah agar mencolok */
            border-radius: 5px;
        }

        /* ========================================= */
        /* PENGATURAN KHUSUS SAAT DICETAK KE PRINTER */
        /* ========================================= */
        @media print {
            body { background-color: white; padding: 0; }
            .container { box-shadow: none; max-width: 100%; width: 100%; padding: 0; }
            .btn-print { display: none !important; } /* Sembunyikan tombol cetak */
            .page-break { page-break-after: always; } /* Paksa ganti halaman setiap ganti kelas */
            .card-pin { break-inside: avoid; } /* Mencegah 1 kartu terpotong di antara 2 halaman */
        }
    </style>
</head>
<body>

    <!-- Tombol untuk memicu dialog print browser -->
    <button class="btn-print no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Cetak Dokumen
    </button>

    <div class="container">
        <?php 
        $jumlah_kelas = count($data_per_kelas);
        $iterasi = 0;

        // Membaca array yang sudah dikelompokkan
        foreach ($data_per_kelas as $nama_kelas => $siswa_kelas) { 
            $iterasi++;
        ?>
            <!-- Judul Kelas -->
            <div class="class-header">
                KUMPULAN PIN KELAS: <?= htmlspecialchars($nama_kelas); ?>
            </div>

            <!-- Wadah Kartu -->
            <div class="cards-grid">
                <?php foreach ($siswa_kelas as $s): ?>
                    <div class="card-pin">
                        <div class="card-header">E-VOTING SMK TARUNA KARYA MANDIRI</div>
                        <div class="card-body">
                            <span><b>NIS:</b> <?= htmlspecialchars($s['nis']); ?></span>
                            <span><b>Nama:</b> <?= htmlspecialchars($s['nama_siswa']); ?></span>
                            <div class="pin-box">
                                <?= htmlspecialchars($s['pin']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 
                Pemaksa Halaman Baru (Page Break).
                Hanya diterapkan jika ini bukan kelas terakhir.
                Agar tidak ada halaman kosong di akhir cetakan.
            -->
            <?php if ($iterasi < $jumlah_kelas): ?>
                <div class="page-break"></div>
            <?php endif; ?>

        <?php } ?>
    </div>

</body>
</html>