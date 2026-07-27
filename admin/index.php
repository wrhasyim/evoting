<?php
// admin/index.php

// 1. Memulai sesi dan memanggil koneksi
session_start();
require '../config/koneksi.php';

// 2. PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// 3. LOGIKA ANALITIK (Mengambil data dari database)

// A. Menghitung Total Siswa
$stmt_total = $pdo->query("SELECT COUNT(*) FROM siswa");
$total_siswa = $stmt_total->fetchColumn();

// B. Menghitung Siswa Sudah Memilih
$stmt_sudah = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status_pilih = 1");
$siswa_sudah = $stmt_sudah->fetchColumn();

// C. Menghitung Siswa Belum Memilih
$stmt_belum = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status_pilih = 0");
$siswa_belum = $stmt_belum->fetchColumn();

// D. Menghitung Siswa Tanpa Eskul (Siswa Bodong)
$stmt_bodong = $pdo->query("SELECT COUNT(*) FROM siswa WHERE nis NOT IN (SELECT nis FROM anggota_eskul)");
$siswa_bodong = $stmt_bodong->fetchColumn();

// E. Mengambil Data Rekap Anggota per Eskul
// Menggunakan id_eskul agar tidak terjadi error "Unknown column"
$stmt_eskul = $pdo->query("
    SELECT e.nama_eskul, COUNT(a.id_eskul) AS total_anggota 
    FROM eskul e 
    LEFT JOIN anggota_eskul a ON e.id_eskul = a.id_eskul 
    GROUP BY e.id_eskul
");
$rekap_eskul = $stmt_eskul->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - E-Voting</title>
    
    <!-- CSS Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Pengaturan Font Utama */
        body { 
            font-family: 'Poppins', sans-serif; 
            background-color: #f4f7fa; 
            overflow-x: hidden;
        }

        /* Desain Sidebar Modern dengan Gradien */
        .sidebar { 
            height: 100vh; 
            background: linear-gradient(180deg, #1a2980 0%, #26d0ce 100%); 
            color: white; 
            padding-top: 30px; 
            position: fixed; 
            width: 260px; 
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            z-index: 100;
        }
        .sidebar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            text-align: center;
            margin-bottom: 30px;
            letter-spacing: 1px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .sidebar-brand i { font-size: 2rem; margin-bottom: 10px; }
        .sidebar a { 
            color: rgba(255,255,255,0.85); 
            text-decoration: none; 
            padding: 15px 25px; 
            display: block; 
            font-weight: 500; 
            transition: all 0.3s ease;
        }
        .sidebar a i { margin-right: 12px; width: 20px; text-align: center; }
        .sidebar a:hover, .sidebar .active { 
            background-color: rgba(255,255,255,0.15); 
            color: white; 
            border-left: 5px solid #fff;
        }

        /* Area Konten Utama */
        .content { margin-left: 260px; padding: 40px; }
        
        /* Header Sederhana di atas Konten */
        .top-header {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Desain Kartu Statistik 3D */
        .card-stat { 
            border-radius: 15px; 
            color: white; 
            padding: 25px; 
            box-shadow: 0 10px 20px rgba(0,0,0,0.08); 
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
        }
        .card-stat:hover { 
            transform: translateY(-7px); 
            box-shadow: 0 15px 25px rgba(0,0,0,0.15);
        }
        .card-stat h5 { font-weight: 500; font-size: 1.1rem; opacity: 0.9; z-index: 2; position: relative; }
        .card-stat h2 { font-weight: 700; font-size: 2.2rem; margin-top: 10px; z-index: 2; position: relative; }
        .card-stat .icon-bg {
            position: absolute;
            right: -10px;
            bottom: -20px;
            font-size: 7rem;
            opacity: 0.2;
            z-index: 1;
        }

        /* Warna Latar Gradien untuk Kartu */
        .bg-grad-primary { background: linear-gradient(135deg, #4e54c8, #8f94fb); }
        .bg-grad-success { background: linear-gradient(135deg, #11998e, #38ef7d); }
        .bg-grad-warning { background: linear-gradient(135deg, #f2994a, #f2c94c); color: #333 !important; }
        .bg-grad-warning h5, .bg-grad-warning h2, .bg-grad-warning .icon-bg { color: #333; opacity: 0.7; }

        /* Wadah Tabel (Table Container) */
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.04);
            border: none;
        }
        .table thead th { border-bottom: 2px solid #e9ecef; color: #6c757d; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        .table tbody tr { transition: background-color 0.2s; }
        .table tbody tr:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>

    <!-- MEMANGGIL SIDEBAR DARI FILE TERPISAH -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        
        <!-- Header Putih -->
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Ringkasan Sistem</h4>
                <small class="text-muted">Pantau aktivitas pemilihan secara real-time</small>
            </div>
            <div>
                <span class="badge bg-primary p-2 fs-6 rounded-pill">
                    <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin'); ?>
                </span>
            </div>
        </div>
        
        <!-- Baris Kartu Statistik Berwarna -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card-stat bg-grad-primary">
                    <h5>Total Pemilih</h5>
                    <h2><?= $total_siswa; ?> <span class="fs-5 fw-normal">Siswa</span></h2>
                    <i class="fas fa-users icon-bg"></i>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card-stat bg-grad-success">
                    <h5>Sudah Memilih</h5>
                    <h2><?= $siswa_sudah; ?> <span class="fs-5 fw-normal">Siswa</span></h2>
                    <i class="fas fa-check-circle icon-bg"></i>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card-stat bg-grad-warning">
                    <h5>Belum Memilih</h5>
                    <h2><?= $siswa_belum; ?> <span class="fs-5 fw-normal">Siswa</span></h2>
                    <i class="fas fa-hourglass-half icon-bg"></i>
                </div>
            </div>
        </div>

        <!-- Baris Analitik Data Eskul & Peringatan -->
        <div class="row">
            <!-- Tabel Rincian Eskul -->
            <div class="col-md-8 mb-4">
                <div class="table-container">
                    <h5 class="fw-bold mb-4" style="color: #2c3e50;"><i class="fas fa-list-alt me-2 text-primary"></i> Data Anggota Ekstrakurikuler</h5>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Ekstrakurikuler</th>
                                    <th class="text-end">Jumlah Anggota</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($rekap_eskul) > 0): ?>
                                    <?php $no = 1; foreach ($rekap_eskul as $row): ?>
                                        <tr class="border-bottom">
                                            <td><span class="text-muted fw-bold"><?= $no++; ?></span></td>
                                            <td class="fw-medium"><?= htmlspecialchars($row['nama_eskul']); ?></td>
                                            <td class="text-end">
                                                <span class="badge bg-light text-dark border px-3 py-2">
                                                    <?= $row['total_anggota']; ?> Siswa
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            <i class="fas fa-folder-open fs-1 d-block mb-2 opacity-50"></i>
                                            Belum ada data ekstrakurikuler.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Kartu Peringatan Siswa Bodong -->
            <div class="col-md-4 mb-4">
                <div class="table-container border-start border-danger border-5" style="background-color: #fffafb;">
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3.5rem;"></i>
                        </div>
                        <h5 class="fw-bold text-danger">Siswa Tanpa Eskul</h5>
                        <h1 class="display-3 fw-bold text-danger my-3"><?= $siswa_bodong; ?></h1>
                        <p class="text-muted small px-3">
                            Jumlah siswa yang belum ditugaskan atau tidak terdaftar di ekstrakurikuler mana pun di dalam sistem.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>

</body>
</html>