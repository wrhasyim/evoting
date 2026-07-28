<?php
// admin/reset_suara.php

session_start();
require '../config/koneksi.php';

// 1. PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$pesan_notifikasi = '';

// 2. PROSES RESET DATA SUARA
if (isset($_POST['eksekusi_reset'])) {
    // Keamanan tambahan: Wajib mengetik kata "RESET"
    $konfirmasi = trim($_POST['konfirmasi_teks']);
    
    if ($konfirmasi === 'RESET') {
        try {
            // Memulai transaksi database
            $pdo->beginTransaction();

            // A. Kosongkan tabel kotak suara
            $pdo->exec("TRUNCATE TABLE suara_masuk");

            // B. Kosongkan catatan riwayat pemilih
            $pdo->exec("TRUNCATE TABLE riwayat_pilih");

            // C. Kembalikan status_pilih semua siswa menjadi 0 (Belum memilih)
            $pdo->exec("UPDATE siswa SET status_pilih = 0");

            $pdo->commit();
            $pesan_notifikasi = "<div class='alert alert-success fw-bold'><i class='fas fa-check-circle me-2'></i> Berhasil! Seluruh data suara telah dibersihkan. Sistem kembali ke Titik Nol.</div>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $pesan_notifikasi = "<div class='alert alert-danger'>Terjadi kesalahan sistem: " . $e->getMessage() . "</div>";
        }
    } else {
        $pesan_notifikasi = "<div class='alert alert-danger'>Gagal: Kata konfirmasi tidak cocok. Pastikan Anda mengetik 'RESET' dengan huruf kapital.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Suara - E-Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fa; overflow-x: hidden; }
        .sidebar { height: 100vh; background: linear-gradient(180deg, #1a2980 0%, #26d0ce 100%); color: white; padding-top: 30px; position: fixed; width: 260px; box-shadow: 4px 0 15px rgba(0,0,0,0.1); z-index: 100; }
        .sidebar-brand { font-weight: 700; font-size: 1.3rem; text-align: center; margin-bottom: 30px; display: flex; flex-direction: column; align-items: center; }
        .sidebar-brand i { font-size: 2rem; margin-bottom: 10px; }
        .sidebar a { color: rgba(255,255,255,0.85); text-decoration: none; padding: 15px 25px; display: block; font-weight: 500; transition: all 0.3s ease; }
        .sidebar a i { margin-right: 12px; width: 20px; text-align: center; }
        .sidebar a:hover, .sidebar .active { background-color: rgba(255,255,255,0.15); color: white; border-left: 5px solid #fff; }
        .content { margin-left: 260px; padding: 40px; }
        .top-header { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .card-reset { background: white; border-radius: 15px; padding: 40px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); max-width: 700px; margin: 0 auto; border-top: 5px solid #dc3545; }
    </style>
</head>
<body>

    <!-- SIDEBAR NAVIGASI -->
    <div class="sidebar">
        <div class="sidebar-brand"><i class="fas fa-vote-yea"></i> E-Voting SMK</div>
        <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="periode.php"><i class="fas fa-calendar-alt"></i> Tahun Ajaran</a>
        <a href="siswa.php"><i class="fas fa-users"></i> Manajemen Siswa</a>
        <a href="eskul.php"><i class="fas fa-school"></i> Manajemen Eskul</a>
        <a href="anggota_eskul.php"><i class="fas fa-users-cog"></i> Anggota Eskul</a>
        <a href="kandidat.php"><i class="fas fa-user-tie"></i> Kandidat</a>
        <a href="live_count.php"><i class="fas fa-chart-pie"></i> Live Count</a>
        <a href="pengaturan.php"><i class="fas fa-cogs"></i> Pengaturan</a>
        <a href="reset_suara.php" class="active text-danger"><i class="fas fa-skull-crossbones"></i> Reset Data</a>
        <a href="../logout.php" class="text-warning mt-4"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Persiapan Akhir</h4>
                <small class="text-muted">Bersihkan data simulasi sebelum hari pemilihan.</small>
            </div>
        </div>

        <?= $pesan_notifikasi; ?>

        <div class="card-reset text-center">
            <i class="fas fa-exclamation-triangle text-danger" style="font-size: 4rem; margin-bottom: 20px;"></i>
            <h3 class="fw-bold text-danger">Zona Berbahaya</h3>
            <p class="text-muted mb-4">Fitur ini digunakan untuk <b>menghapus seluruh perolehan suara</b> dan mereset status siswa menjadi "Belum Memilih". Gunakan HANYA setelah Anda selesai melakukan uji coba/simulasi.</p>
            
            <div class="alert alert-warning text-start small">
                <ul class="mb-0">
                    <li>Data Kandidat, Visi Misi, dan Foto <b>TIDAK</b> akan dihapus.</li>
                    <li>Data Siswa, Kelas, dan PIN <b>TIDAK</b> akan dihapus.</li>
                    <li>Hanya kotak suara yang dikosongkan.</li>
                </ul>
            </div>

            <form method="POST" action="" class="mt-4">
                <div class="mb-3 text-start">
                    <label class="form-label fw-bold">Ketik <span class="text-danger">RESET</span> untuk melanjutkan:</label>
                    <input type="text" name="konfirmasi_teks" class="form-control border-danger text-center fw-bold" placeholder="Ketik RESET di sini" required autocomplete="off">
                </div>
                <button type="submit" name="eksekusi_reset" class="btn btn-danger btn-lg w-100 fw-bold" onclick="return confirm('APAKAH ANDA BENAR-BENAR YAKIN INGIN MENGHAPUS SEMUA SUARA?');">
                    <i class="fas fa-trash me-2"></i> KOSONGKAN KOTAK SUARA
                </button>
            </form>
        </div>
    </div>

</body>
</html>