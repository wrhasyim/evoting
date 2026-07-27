<style>
    /* ========================================== */
    /* CSS KHUSUS SIDEBAR (ALL-IN-ONE)            */
    /* ========================================== */
    .sidebar { 
        height: 100vh; 
        background: linear-gradient(180deg, #1a2980 0%, #26d0ce 100%); 
        color: white; 
        padding-top: 30px; 
        position: fixed; 
        width: 260px; 
        box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        z-index: 100;
        overflow-y: auto; /* Mencegah menu terpotong jika layar kecil */
    }
    
    /* Pembaruan agar teks nama sekolah lega dan tidak mepet */
    .sidebar-brand { 
        font-weight: 700; 
        font-size: 1.15rem; 
        text-align: center; 
        margin-bottom: 30px; 
        padding: 0 10px; /* Jarak aman kiri dan kanan */
        line-height: 1.6; /* Merenggangkan jarak antar baris */
        display: flex; 
        flex-direction: column; 
        align-items: center; 
    }
    
    .sidebar-brand i { 
        font-size: 2.2rem; 
        margin-bottom: 12px; 
    }
    
    .sidebar a { 
        color: rgba(255,255,255,0.85); 
        text-decoration: none; 
        padding: 15px 25px; 
        display: block; 
        font-weight: 500; 
        transition: all 0.3s ease;
    }
    
    .sidebar a i { 
        margin-right: 12px; 
        width: 20px; 
        text-align: center; 
    }
    
    .sidebar a:hover, .sidebar .active { 
        background-color: rgba(255,255,255,0.15); 
        color: white; 
        border-left: 5px solid #fff;
    }
</style>

<div class="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-vote-yea"></i>
        <!-- Menambahkan tag <br> untuk memaksa nama turun ke baris baru agar rapi -->
        E-Voting SMK <br> TARUNA KARYA MANDIRI
    </div>
    
    <?php 
    // Fitur Cerdas: Mendeteksi nama file yang sedang dibuka
    $current_page = basename($_SERVER['PHP_SELF']); 
    ?>

    <!-- Menu akan otomatis diberi kelas 'active' jika sedang berada di halamannya -->
    <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    
    <a href="periode.php" class="<?= $current_page == 'periode.php' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-alt"></i> Tahun Ajaran
    </a>
    
    <a href="siswa.php" class="<?= $current_page == 'siswa.php' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i> Manajemen Siswa
    </a>
    
    <a href="eskul.php" class="<?= $current_page == 'eskul.php' ? 'active' : ''; ?>">
        <i class="fas fa-school"></i> Manajemen Eskul
    </a>
    
    <a href="anggota_eskul.php" class="<?= $current_page == 'anggota_eskul.php' ? 'active' : ''; ?>">
        <i class="fas fa-users-cog"></i> Anggota Eskul
    </a>
    
    <a href="kandidat.php" class="<?= $current_page == 'kandidat.php' ? 'active' : ''; ?>">
        <i class="fas fa-user-tie"></i> Kandidat
    </a>
    
    <a href="live_count.php" class="<?= $current_page == 'live_count.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-pie"></i> Live Count
    </a>
    
    <a href="pengaturan.php" class="<?= $current_page == 'pengaturan.php' ? 'active' : ''; ?>">
        <i class="fas fa-cogs"></i> Pengaturan
    </a>
    
    <a href="../logout.php" class="text-warning mt-4">
        <i class="fas fa-sign-out-alt"></i> Keluar
    </a>
</div>