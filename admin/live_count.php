<?php
// admin/live_count.php

session_start();
require '../config/koneksi.php';

// 1. PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// 2. AMBIL DAFTAR ESKUL UNTUK FILTER
$stmt_eskul = $pdo->query("SELECT id_eskul, nama_eskul FROM eskul WHERE status_aktif = 1 ORDER BY nama_eskul ASC");
$daftar_eskul = $stmt_eskul->fetchAll();
$id_eskul_pilih = isset($_GET['id_eskul']) ? $_GET['id_eskul'] : (count($daftar_eskul) > 0 ? $daftar_eskul[0]['id_eskul'] : null);

$data_hasil = [];
$total_suara_masuk = 0;

// 3. PROSES KALKULASI SUARA
if ($id_eskul_pilih) {
    // A. Menghitung total seluruh suara yang sudah masuk untuk eskul ini
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM suara_masuk WHERE id_eskul = ?");
    $stmt_total->execute([$id_eskul_pilih]);
    $total_suara_masuk = $stmt_total->fetchColumn();

    // B. Menghitung perolehan suara masing-masing kandidat secara otomatis
    $stmt_hasil = $pdo->prepare("
        SELECT k.no_urut, k.nama_paslon, k.foto, COUNT(s.id_suara) AS perolehan 
        FROM kandidat k 
        LEFT JOIN suara_masuk s ON k.id_kandidat = s.id_kandidat 
        WHERE k.id_eskul = ? AND k.status_aktif = 1 
        GROUP BY k.id_kandidat 
        ORDER BY perolehan DESC, k.no_urut ASC
    ");
    $stmt_hasil->execute([$id_eskul_pilih]);
    $data_hasil = $stmt_hasil->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Meta tag untuk me-refresh halaman otomatis setiap 30 detik -->
    <meta http-equiv="refresh" content="30">
    <title>Live Count - E-Voting</title>
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
        .card-hasil { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); margin-bottom: 20px; }
        .foto-kandidat { width: 80px; height: 80px; object-fit: cover; border-radius: 50%; border: 3px solid #f8f9fa; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .progress { height: 25px; border-radius: 15px; background-color: #e9ecef; }
        .progress-bar { font-weight: bold; font-size: 1rem; line-height: 25px; }
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
        <a href="live_count.php" class="active"><i class="fas fa-chart-pie"></i> Live Count</a>
        <a href="#"><i class="fas fa-cogs"></i> Pengaturan</a>
        <a href="../logout.php" class="text-warning mt-4"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Pemantauan Hasil (Live Count)</h4>
                <small class="text-muted">Data otomatis diperbarui setiap 30 detik.</small>
            </div>
            <div>
                <span class="badge bg-primary p-2 fs-6">
                    <i class="fas fa-envelope-open-text me-1"></i> Total Suara Masuk: <?= $total_suara_masuk; ?>
                </span>
            </div>
        </div>

        <?php if (count($daftar_eskul) == 0): ?>
            <div class="alert alert-warning">Belum ada ekstrakurikuler yang didaftarkan.</div>
        <?php else: ?>
            <!-- FORM FILTER ESKUL -->
            <form method="GET" action="" class="mb-4 bg-light p-3 rounded border">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <label class="fw-bold mb-0">Lihat Hasil Untuk:</label>
                    </div>
                    <div class="col-md-9">
                        <select name="id_eskul" class="form-select border-primary" onchange="this.form.submit()">
                            <?php foreach ($daftar_eskul as $e): ?>
                                <option value="<?= $e['id_eskul']; ?>" <?= ($e['id_eskul'] == $id_eskul_pilih) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($e['nama_eskul']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>

            <div class="row">
                <?php if (count($data_hasil) > 0): ?>
                    <?php 
                    // Menentukan warna progress bar secara dinamis
                    $warna_bar = ['bg-success', 'bg-info', 'bg-warning text-dark', 'bg-danger']; 
                    $index_warna = 0;
                    ?>
                    
                    <div class="col-12">
                        <div class="card-hasil">
                            <h5 class="fw-bold mb-4 border-bottom pb-2">Grafik Perolehan Suara Sementara</h5>
                            
                            <?php foreach ($data_hasil as $row): ?>
                                <?php 
                                // Kalkulasi persentase
                                $persentase = ($total_suara_masuk > 0) ? round(($row['perolehan'] / $total_suara_masuk) * 100, 1) : 0;
                                $kelas_warna = $warna_bar[$index_warna % count($warna_bar)];
                                $index_warna++;
                                ?>
                                
                                <div class="row align-items-center mb-4">
                                    <div class="col-md-1 col-3 text-center">
                                        <img src="../uploads/<?= htmlspecialchars($row['foto']); ?>" alt="Foto" class="foto-kandidat">
                                    </div>
                                    <div class="col-md-11 col-9">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="fw-bold fs-5">Paslon No. <?= $row['no_urut']; ?> - <?= htmlspecialchars($row['nama_paslon']); ?></span>
                                            <span class="fw-bold fs-5 text-primary"><?= $row['perolehan']; ?> Suara (<?= $persentase; ?>%)</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar <?= $kelas_warna; ?> progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $persentase; ?>%;" aria-valuenow="<?= $persentase; ?>" aria-valuemin="0" aria-valuemax="100">
                                                <?= $persentase > 5 ? $persentase . '%' : ''; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center py-5">
                            <i class="fas fa-chart-bar fs-1 mb-3 text-info"></i>
                            <h4>Belum ada kandidat atau belum ada suara yang masuk.</h4>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>