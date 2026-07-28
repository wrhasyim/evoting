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

// 2. PROSES RESET DATA SUARA (DATABASE)
if (isset($_POST['eksekusi_reset'])) {
    $konfirmasi = trim($_POST['konfirmasi_teks']);
    
    if ($konfirmasi === 'RESET') {
        try {
            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM suara_masuk");
            $pdo->exec("DELETE FROM riwayat_pilih");
            $pdo->exec("UPDATE siswa SET status_pilih = 0");
            $pdo->commit();
            $pesan_notifikasi = "<div class='alert alert-success fw-bold'><i class='fas fa-check-circle me-2'></i> Berhasil! Seluruh data suara telah dibersihkan. Sistem kembali ke Titik Nol.</div>";
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $pesan_notifikasi = "<div class='alert alert-danger'>Terjadi kesalahan sistem: " . $e->getMessage() . "</div>";
        }
    } else {
        $pesan_notifikasi = "<div class='alert alert-danger'>Gagal: Kata konfirmasi kotak suara tidak cocok.</div>";
    }
}

// 3. PROSES PEMBERSIHAN FILE FOTO (STORAGE)
if (isset($_POST['eksekusi_hapus_foto'])) {
    $konfirmasi_foto = trim($_POST['konfirmasi_foto']);
    
    if ($konfirmasi_foto === 'HAPUS FOTO') {
        $folder_uploads = '../uploads/';
        
        // Membaca semua file di dalam folder uploads
        $files = glob($folder_uploads . '*'); 
        $jumlah_dihapus = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $nama_file = basename($file);
                // Proteksi: JANGAN HAPUS file .htaccess
                if ($nama_file !== '.htaccess') {
                    // Perintah menghapus file fisik dari hardisk
                    unlink($file); 
                    $jumlah_dihapus++;
                }
            }
        }
        
        // Mengosongkan referensi foto di database agar tidak muncul icon gambar 'broken' di halaman web
        $pdo->exec("UPDATE kandidat SET foto = ''");
        
        $pesan_notifikasi = "<div class='alert alert-success fw-bold'><i class='fas fa-trash-alt me-2'></i> Berhasil! Membebaskan ruang server dengan menghapus $jumlah_dihapus file foto fisik.</div>";
    } else {
        $pesan_notifikasi = "<div class='alert alert-danger'>Gagal: Kata konfirmasi hapus foto tidak cocok.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset & Maintenance - E-Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fa; overflow-x: hidden; }
        .content { margin-left: 260px; padding: 40px; }
        .top-header { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .card-reset { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); border-top: 5px solid #dc3545; }
        .card-storage { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); border-top: 5px solid #fd7e14; }
    </style>
</head>
<body>

    <!-- Memanggil sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Maintenance & Persiapan</h4>
                <small class="text-muted">Kelola kebersihan database dan storage server Anda.</small>
            </div>
        </div>

        <?= $pesan_notifikasi; ?>

        <div class="row g-4">
            <!-- KARTU 1: RESET SUARA -->
            <div class="col-md-6">
                <div class="card-reset text-center h-100">
                    <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3.5rem; margin-bottom: 15px;"></i>
                    <h4 class="fw-bold text-danger">Reset Kotak Suara</h4>
                    <p class="text-muted mb-4 small">Menghapus seluruh perolehan suara dan mereset status siswa menjadi "Belum Memilih". Ideal setelah sesi simulasi.</p>
                    
                    <div class="alert alert-danger text-start" style="font-size: 0.85rem;">
                        <ul class="mb-0 ps-3">
                            <li>Data Kandidat, Siswa, dan PIN <b>TIDAK</b> dihapus.</li>
                            <li>Hanya perolehan kotak suara yang dikosongkan.</li>
                        </ul>
                    </div>

                    <form method="POST" action="" class="mt-auto">
                        <div class="mb-3 text-start">
                            <label class="form-label fw-bold small">Ketik <span class="text-danger">RESET</span>:</label>
                            <input type="text" name="konfirmasi_teks" class="form-control border-danger text-center fw-bold" autocomplete="off" required>
                        </div>
                        <button type="submit" name="eksekusi_reset" class="btn btn-danger w-100 fw-bold" onclick="return confirm('Yakin ingin mereset suara?');">
                            <i class="fas fa-trash me-2"></i> KOSONGKAN SUARA
                        </button>
                    </form>
                </div>
            </div>

            <!-- KARTU 2: BERSIHKAN PENYIMPANAN FOTO -->
            <div class="col-md-6">
                <div class="card-storage text-center h-100">
                    <i class="fas fa-images text-warning" style="font-size: 3.5rem; margin-bottom: 15px;"></i>
                    <h4 class="fw-bold text-warning" style="color: #d35400 !important;">Pembersihan Storage</h4>
                    <p class="text-muted mb-4 small">Menghapus <b>semua file fisik foto</b> di folder <code>uploads/</code> untuk membebaskan ruang hardisk komputer server.</p>
                    
                    <div class="alert alert-warning text-start text-dark" style="font-size: 0.85rem;">
                        <ul class="mb-0 ps-3">
                            <li>Lakukan ini saat memulai Tahun Ajaran Baru.</li>
                            <li>File sistem <code>.htaccess</code> aman tidak akan terhapus.</li>
                        </ul>
                    </div>

                    <form method="POST" action="" class="mt-auto">
                        <div class="mb-3 text-start">
                            <label class="form-label fw-bold small">Ketik <span class="text-warning" style="color: #d35400 !important;">HAPUS FOTO</span>:</label>
                            <input type="text" name="konfirmasi_foto" class="form-control border-warning text-center fw-bold" autocomplete="off" required>
                        </div>
                        <button type="submit" name="eksekusi_hapus_foto" class="btn btn-warning w-100 fw-bold text-dark" onclick="return confirm('Yakin ingin menghapus semua file foto secara permanen?');">
                            <i class="fas fa-broom me-2"></i> BERSIHKAN FOTO
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>