<?php
// index.php (Berada di root folder evoting)

// 1. Memulai sesi
session_start();

// 2. Memanggil file koneksi (karena ini di root, langsung panggil config/)
require 'config/koneksi.php';

// --- FITUR BANTUAN OTOMATIS: Buat admin default jika tabel kosong ---
$cek_admin = $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
if ($cek_admin == 0) {
    $password_default = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admin (username, password, nama_lengkap) VALUES ('admin', :pass, 'Administrator Utama')");
    $stmt->execute(['pass' => $password_default]);
}
// ------------------------------------------------------------------

// 3. Pengecekan Sesi: Arahkan jika sudah dalam keadaan login
if (isset($_SESSION['admin_logged_in'])) {
    header("Location: admin/index.php");
    exit;
}
if (isset($_SESSION['siswa_logged_in'])) {
    // PERBAIKAN: Arahkan siswa ke folder admin tempat file tersebut berada
    header("Location: admin/beranda_siswa.php"); 
    exit;
}

$error_pesan = '';

// 4. Logika Smart Login (Berjalan saat tombol masuk ditekan)
if (isset($_POST['login'])) {
    $userid = trim($_POST['userid']);
    $credential = $_POST['credential'];

    // SKENARIO A: Cek apakah ini Admin?
    $stmt_admin = $pdo->prepare("SELECT * FROM admin WHERE username = :userid");
    $stmt_admin->execute(['userid' => $userid]);
    $admin_data = $stmt_admin->fetch();

    if ($admin_data && password_verify($credential, $admin_data['password'])) {
        // Ini adalah Admin, set sesi admin
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['id_admin'] = $admin_data['id_admin'];
        $_SESSION['nama_lengkap'] = $admin_data['nama_lengkap'];
        
        // Catat log
        $log = $pdo->prepare("INSERT INTO log_aktivitas (id_admin, aktivitas) VALUES (:id, 'Login ke sistem')");
        $log->execute(['id' => $admin_data['id_admin']]);

        header("Location: admin/index.php");
        exit;
    } 
    else {
        // SKENARIO B: Jika bukan Admin, cek apakah ini Siswa?
        // Catatan: PIN siswa diasumsikan disimpan sebagai teks biasa (plain text) dari hasil generate
        $stmt_siswa = $pdo->prepare("SELECT * FROM siswa WHERE nis = :userid AND pin = :credential AND status_aktif = 1");
        $stmt_siswa->execute(['userid' => $userid, 'credential' => $credential]);
        $siswa_data = $stmt_siswa->fetch();

        if ($siswa_data) {
            // Cek apakah siswa sudah pernah memilih
            if ($siswa_data['status_pilih'] == 1) {
                $error_pesan = "Akses Ditolak: Anda sudah memberikan suara.";
            } else {
                // Ini adalah Siswa yang sah, set sesi siswa
                $_SESSION['siswa_logged_in'] = true;
                $_SESSION['nis'] = $siswa_data['nis'];
                $_SESSION['nama_siswa'] = $siswa_data['nama_siswa'];
                
                // PERBAIKAN: Arahkan siswa ke folder admin tempat file tersebut berada
                header("Location: admin/beranda_siswa.php"); 
                exit;
            }
        } else {
            // SKENARIO C: Tidak terdaftar di admin maupun siswa
            $error_pesan = "ID Pengguna atau Kata Sandi / PIN salah!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Voting SMK</title>
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #1a2980 0%, #26d0ce 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .login-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            display: flex;
            flex-direction: row;
        }
        .login-left {
            /* Placeholder gambar sekolah atau elemen abstrak */
            background: url('https://images.unsplash.com/photo-1546422904-90eab23c3d7e?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80') center/cover no-repeat;
            width: 50%;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-left::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(26,41,128,0.85) 0%, rgba(38,208,206,0.85) 100%);
        }
        .left-content {
            position: relative;
            z-index: 1;
            color: white;
            text-align: center;
            padding: 40px;
        }
        .left-content i {
            font-size: 4rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .login-right {
            width: 50%;
            padding: 50px 40px;
            background: white;
        }
        .input-group-text {
            background-color: transparent;
            border-right: none;
            color: #6c757d;
        }
        .form-control {
            border-left: none;
            padding-left: 0;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #dee2e6;
        }
        .input-group:focus-within {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            border-radius: 0.375rem;
        }
        .input-group:focus-within .input-group-text,
        .input-group:focus-within .form-control {
            border-color: #86b7fe;
        }
        .btn-login {
            background: linear-gradient(135deg, #1a2980 0%, #26d0ce 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            transition: transform 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(38,208,206,0.4);
        }
        /* Mode Mobile (responsif) */
        @media (max-width: 768px) {
            .login-card { flex-direction: column; max-width: 450px; }
            .login-left { width: 100%; padding: 40px 0; }
            .login-right { width: 100%; padding: 40px 25px; }
        }
    </style>
</head>
<body>

    <div class="container d-flex justify-content-center">
        <div class="login-card">
            <!-- Sisi Kiri (Area Branding) -->
            <div class="login-left d-none d-md-flex">
                <div class="left-content">
                    <i class="fas fa-vote-yea"></i>
                    <h2 class="fw-bold mb-3">E-Voting Terpadu</h2>
                    <p class="mb-0 fs-5">SMK Taruna Karya Mandiri</p>
                    <p class="small opacity-75 mt-3">Sistem Pemilihan Ketua OSIS & Ekstrakurikuler yang Jujur, Adil, dan Transparan.</p>
                </div>
            </div>

            <!-- Sisi Kanan (Area Form Login) -->
            <div class="login-right">
                <!-- Header khusus tampilan mobile -->
                <div class="text-center d-md-none mb-4">
                    <i class="fas fa-vote-yea fs-1 text-primary mb-2"></i>
                    <h3 class="fw-bold text-dark">E-Voting SMK</h3>
                </div>

                <h4 class="fw-bold text-dark mb-1">Selamat Datang 👋</h4>
                <p class="text-muted small mb-4">Silakan masuk menggunakan akun Anda.</p>

                <?php if ($error_pesan): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?= $error_pesan; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="form-label fw-semibold small text-dark">Username / NIS</label>
                        <div class="input-group mb-3 shadow-sm">
                            <span class="input-group-text bg-light"><i class="fas fa-user"></i></span>
                            <input type="text" name="userid" class="form-control bg-light" placeholder="Masukkan ID Anda" required autocomplete="off">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold small text-dark">Kata Sandi / PIN</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-light"><i class="fas fa-lock"></i></span>
                            <input type="password" name="credential" class="form-control bg-light" placeholder="Masukkan Sandi atau PIN" required>
                        </div>
                    </div>

                    <button type="submit" name="login" class="btn btn-primary btn-login w-100 rounded-pill shadow mt-2">
                        MASUK SISTEM <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <span class="badge bg-light text-secondary border px-3 py-2">
                        <i class="fas fa-shield-alt text-primary me-1"></i> Smart Login System
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>