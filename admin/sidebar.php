<?php
// Mendapatkan nama file yang sedang diakses saat ini (misal: index.php atau siswa.php)
$halaman_sekarang = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-vote-yea"></i>
        E-Voting SMK <br> TARUNA KARYA MANDIRI
    </div>
    
    <!-- Menu Dashboard -->
    <a href="index.php" class="<?= ($halaman_sekarang == 'index.php') ? 'active' : ''; ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    
    <!-- Menu Tahun Ajaran (Yang sebelumnya tertinggal) -->
    <a href="periode.php" class="<?= ($halaman_sekarang == 'periode.php') ? 'active' : ''; ?>">
        <i class="fas fa-calendar-alt"></i> Tahun Ajaran
    </a>
    
    <!-- Menu Manajemen Siswa -->
    <a href="siswa.php" class="<?= ($halaman_sekarang == 'siswa.php') ? 'active' : ''; ?>">
        <i class="fas fa-users"></i> Manajemen Siswa
    </a>
    
    <!-- Menu Manajemen Eskul -->
    <a href="eskul.php" class="<?= ($halaman_sekarang == 'eskul.php') ? 'active' : ''; ?>">
        <i class="fas fa-school"></i> Manajemen Eskul
    </a>
    
    <!-- Menu Anggota Eskul (Yang sebelumnya tertinggal) -->
    <a href="anggota_eskul.php" class="<?= ($halaman_sekarang == 'anggota_eskul.php') ? 'active' : ''; ?>">
        <i class="fas fa-users-cog"></i> Anggota Eskul
    </a>
    
    <!-- Menu Kandidat -->
    <a href="kandidat.php" class="<?= ($halaman_sekarang == 'kandidat.php') ? 'active' : ''; ?>">
        <i class="fas fa-user-tie"></i> Kandidat
    </a>
    
    <!-- Menu Live Count -->
    <a href="live_count.php" class="<?= ($halaman_sekarang == 'live_count.php') ? 'active' : ''; ?>">
        <i class="fas fa-chart-pie"></i> Live Count
    </a>
    
    <!-- Menu Pengaturan -->
    <a href="pengaturan.php" class="<?= ($halaman_sekarang == 'pengaturan.php') ? 'active' : ''; ?>">
        <i class="fas fa-cogs"></i> Pengaturan
    </a>
    
    <!-- Menu Keluar (Logout) -->
    <a href="../logout.php" class="text-warning mt-4">
        <i class="fas fa-sign-out-alt"></i> Keluar
    </a>
</div>