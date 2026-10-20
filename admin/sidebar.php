<?php 
// admin/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']); 

// Ambil periode aktif untuk indikator navbar jika belum ada
$periode_nav_text = "Belum Ada Periode";
if (isset($pdo)) {
    try {
        $stmt_nav_p = $pdo->query("SELECT nama_periode FROM periode WHERE status_aktif = 1 LIMIT 1");
        $row_p = $stmt_nav_p->fetchColumn();
        if ($row_p) {
            $periode_nav_text = $row_p;
        }
    } catch(Exception $e) {
        // fail-safe
    }
}
?>

<!-- Link Modern CSS jika belum dipanggil di head -->
<link rel="stylesheet" href="assets/css/style.css">

<!-- NAVBAR UTAMA MODERN -->
<nav class="navbar navbar-expand-xl navbar-custom fixed-top">
    <div class="container-fluid px-3 px-lg-4">
        
        <!-- Brand & Logo -->
        <a class="navbar-brand" href="index.php">
            <div class="brand-icon-box">
                <i class="fas fa-vote-yea"></i>
            </div>
            <div>
                <div class="brand-title">E-VOTING SMK</div>
                <div class="brand-subtitle">TARUNA KARYA MANDIRI</div>
            </div>
        </a>
        
        <!-- Badge Periode Aktif di Navbar -->
        <div class="d-none d-md-flex align-items-center ms-3">
            <span class="badge-periode-nav">
                <span class="pulse-dot"></span>
                <span>T.A: <?= htmlspecialchars($periode_nav_text); ?></span>
            </span>
        </div>

        <!-- Tombol Hamburger Mobile -->
        <button class="navbar-toggler border-0 text-white shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars-staggered fs-4 text-white"></i>
        </button>
        
        <!-- Menu Navigasi -->
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav align-items-center my-3 my-xl-0 gap-1">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'periode.php' ? 'active' : ''; ?>" href="periode.php">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Tahun Ajaran</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'siswa.php' ? 'active' : ''; ?>" href="siswa.php">
                        <i class="fas fa-user-graduate"></i>
                        <span>Siswa</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'eskul.php' ? 'active' : ''; ?>" href="eskul.php">
                        <i class="fas fa-sitemap"></i>
                        <span>Eskul</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'anggota_eskul.php' ? 'active' : ''; ?>" href="anggota_eskul.php">
                        <i class="fas fa-users"></i>
                        <span>Anggota</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'kandidat.php' ? 'active' : ''; ?>" href="kandidat.php">
                        <i class="fas fa-id-badge"></i>
                        <span>Kandidat</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'live_count.php' ? 'active' : ''; ?>" href="live_count.php">
                        <i class="fas fa-satellite-dish text-warning"></i>
                        <span>Live Count</span>
                    </a>
                </li>
                
                <li class="nav-item d-none d-xl-block mx-2">
                    <span class="text-white opacity-25">|</span>
                </li>

                <!-- Dropdown Profil & Opsi Cepat -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle py-1 px-3 d-flex align-items-center gap-2" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="background: rgba(255,255,255,0.14); border-radius: 9999px;">
                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 28px; height: 28px; font-size: 0.8rem;">
                            <?= strtoupper(substr($_SESSION['nama_lengkap'] ?? 'A', 0, 1)); ?>
                        </div>
                        <span class="small fw-semibold"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin'); ?></span>
                    </a>
                    
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="navbarDropdown">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-bold text-dark small"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Administrator'); ?></div>
                            <div class="text-muted" style="font-size: 0.72rem;">Hak Akses: Panitia Pemilihan</div>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="cetak_pin.php" target="_blank">
                                <i class="fas fa-print text-primary w-20"></i> Cetak Token / PIN
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 <?= $current_page == 'pengaturan.php' ? 'active' : ''; ?>" href="pengaturan.php">
                                <i class="fas fa-sliders-h text-secondary w-20"></i> Pengaturan
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item py-2 text-danger fw-semibold" href="../logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
                                <i class="fas fa-sign-out-alt w-20"></i> Keluar
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
        
    </div>
</nav>
