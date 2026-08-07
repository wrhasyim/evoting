<style>
    /* ========================================== */
    /* CSS KHUSUS NAVBAR ATAS & PERBAIKAN LAYOUT  */
    /* ========================================== */
    
    /* 1. Mencegah konten tertutup navbar yang melayang */
    body {
        padding-top: 80px !important; 
        background-color: #f4f7fa;
    }
    
    /* 2. Styling Navbar Utama */
    .navbar-custom { 
        background: linear-gradient(90deg, #1a2980 0%, #26d0ce 100%); 
        padding: 12px 0; 
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        z-index: 1050; /* Memastikan navbar di posisi atas agar ikon bisa diklik */
    }
    
    .navbar-custom .navbar-brand { 
        color: white; 
        font-weight: 700; 
        letter-spacing: 0.5px; 
    }

    /* KELAS BARU: Mengatur ukuran teks sekolah agar lebih kecil dan rapi */
    .brand-text {
        font-size: 0.9rem; /* Ukuran diperkecil agar lebih rapi */
        line-height: 1.3;
        display: inline-block;
        vertical-align: middle;
    }
    
    /* 3. Merapikan Ikon dan Teks agar sejajar (Flexbox) */
    .navbar-custom .nav-link { 
        color: rgba(255,255,255,0.85); 
        font-weight: 500; 
        padding: 8px 15px !important;
        margin: 0 2px; 
        border-radius: 8px; 
        transition: all 0.3s ease;
        display: flex; 
        align-items: center; 
        gap: 8px;
    }
    
    .navbar-custom .nav-link i {
        font-size: 1.1rem;
    }
    
    .navbar-custom .nav-link:hover, .navbar-custom .nav-link.active { 
        color: white; 
        background-color: rgba(255,255,255,0.2); 
    }

    /* 4. Styling Dropdown Profil di Kanan */
    .navbar-custom .dropdown-menu {
        border-radius: 12px;
        border: none;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        margin-top: 10px;
        min-width: 200px;
    }

    .navbar-custom .dropdown-item {
        padding: 10px 20px;
        font-weight: 500;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.2s;
    }

    .navbar-custom .dropdown-item:hover {
        background-color: #f8f9fa;
        color: #1a2980;
    }
    
    /* ========================================== */
    /* CSS OVERRIDE (MEMPERBAIKI SEMUA HALAMAN)   */
    /* ========================================== */
    .content {
        margin-left: 0 !important; 
        padding: 20px !important;
        max-width: 1300px;
        margin: 0 auto !important; 
    }
</style>

<?php 
$current_page = basename($_SERVER['PHP_SELF']); 
?>

<!-- NAVBAR BOOTSTRAP -->
<nav class="navbar navbar-expand-xl navbar-custom fixed-top">
    <div class="container-fluid px-4">
        
        <!-- Logo & Nama Aplikasi yang Diperbarui -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <i class="fas fa-vote-yea fs-3"></i> 
            <span class="d-none d-sm-inline brand-text">
                E-Voting SMK <br> TARUNA KARYA MANDIRI
            </span>
            <span class="d-inline d-sm-none brand-text">E-Voting</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: rgba(255,255,255,0.5);">
            <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
        </button>
        
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav align-items-center">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'index.php' ? 'active' : ''; ?>" href="index.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'periode.php' ? 'active' : ''; ?>" href="periode.php">
                        <i class="fas fa-calendar-alt"></i> Tahun Ajaran
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'siswa.php' ? 'active' : ''; ?>" href="siswa.php">
                        <i class="fas fa-users"></i> Siswa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'eskul.php' ? 'active' : ''; ?>" href="eskul.php">
                        <i class="fas fa-school"></i> Eskul
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'anggota_eskul.php' ? 'active' : ''; ?>" href="anggota_eskul.php">
                        <i class="fas fa-users-cog"></i> Anggota
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'kandidat.php' ? 'active' : ''; ?>" href="kandidat.php">
                        <i class="fas fa-user-tie"></i> Kandidat
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'live_count.php' ? 'active' : ''; ?>" href="live_count.php">
                        <i class="fas fa-chart-pie"></i> Live Count
                    </a>
                </li>
                
                <li class="nav-item d-none d-xl-block mx-2">
                    <span class="text-white opacity-50">|</span>
                </li>

                <!-- Penambahan class me-4 agar profil tidak mepet pojok kanan -->
                <li class="nav-item dropdown me-4">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle fs-3"></i>
                        <span class="d-xl-none">Menu Akun</span>
                    </a>
                    
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="navbarDropdown">
                        <li>
                            <a class="dropdown-item <?= $current_page == 'pengaturan.php' ? 'active text-white bg-primary' : ''; ?>" href="pengaturan.php">
                                <i class="fas fa-cogs text-secondary w-25"></i> Pengaturan
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger fw-bold" href="../logout.php">
                                <i class="fas fa-sign-out-alt w-25"></i> Keluar
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
        
    </div>
</nav>