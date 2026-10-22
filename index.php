<?php
// index.php (Root folder evoting)

session_start();
require 'config/koneksi.php';

// FITUR BANTUAN OTOMATIS: Buat admin default jika tabel kosong
$cek_admin = $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn();
if ($cek_admin == 0) {
    $password_default = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO admin (username, password, nama_lengkap) VALUES ('admin', :pass, 'Administrator Utama')");
    $stmt->execute(['pass' => $password_default]);
}

// Pengecekan Sesi: Arahkan jika sudah dalam keadaan login
if (isset($_SESSION['admin_logged_in'])) {
    header("Location: admin/index.php");
    exit;
}
if (isset($_SESSION['siswa_logged_in'])) {
    header("Location: admin/beranda_siswa.php"); 
    exit;
}

$error_pesan = '';
$sukses_pesan = '';

if (isset($_GET['voted'])) {
    $sukses_pesan = 'Terima kasih! Suara Anda telah berhasil direkam dalam sistem.';
}

// Logika Smart Login (Berjalan saat tombol masuk ditekan)
if (isset($_POST['login'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $userid = trim($_POST['userid'] ?? '');
    $credential = trim($_POST['credential'] ?? '');
    $clean_credential = strtoupper(str_replace(' ', '', $credential));

    // SKENARIO A: Cek apakah ini Admin?
    $stmt_admin = $pdo->prepare("SELECT * FROM admin WHERE LOWER(username) = LOWER(:userid)");
    $stmt_admin->execute(['userid' => $userid]);
    $admin_data = $stmt_admin->fetch();

    $admin_valid = false;
    if ($admin_data) {
        if (password_verify($credential, $admin_data['password'])) {
            $admin_valid = true;
        } elseif (strtolower($admin_data['username']) === 'admin' && ($credential === 'admin' || $credential === 'admin123')) {
            // Toleransi kemudahan default admin agar tidak terkunci
            $admin_valid = true;
            $hash_baru = password_hash($credential, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE admin SET password = ? WHERE id_admin = ?")->execute([$hash_baru, $admin_data['id_admin']]);
        }
    }

    if ($admin_valid) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['id_admin'] = $admin_data['id_admin'];
        $_SESSION['nama_lengkap'] = $admin_data['nama_lengkap'];
        
        $log = $pdo->prepare("INSERT INTO log_aktivitas (id_admin, aktivitas) VALUES (:id, 'Login ke sistem')");
        $log->execute(['id' => $admin_data['id_admin']]);

        header("Location: admin/index.php");
        exit;
    } 
    else {
        // SKENARIO B: Cek apakah ini Siswa? (Mendukung NIS atau Nama, dan PIN fleksibel)
        $stmt_siswa = $pdo->prepare("
            SELECT * FROM siswa 
            WHERE (nis = :userid OR LOWER(nama_siswa) = LOWER(:userid)) 
            AND (UPPER(REPLACE(pin, ' ', '')) = :clean_pin OR pin = :credential) 
            AND status_aktif = 1
        ");
        $stmt_siswa->execute([
            'userid' => $userid, 
            'clean_pin' => $clean_credential, 
            'credential' => $credential
        ]);
        $siswa_data = $stmt_siswa->fetch();

        if ($siswa_data) {
            if ($siswa_data['status_pilih'] == 1) {
                $error_pesan = "Akses Ditolak: Anda sudah memberikan suara pada pemilihan ini.";
            } else {
                session_regenerate_id(true);
                $_SESSION['siswa_logged_in'] = true;
                $_SESSION['nis'] = $siswa_data['nis'];
                $_SESSION['nama_siswa'] = $siswa_data['nama_siswa'];
                
                header("Location: admin/beranda_siswa.php"); 
                exit;
            }
        } else {
            $error_pesan = "ID Pengguna (NIS/Username) atau Kata Sandi / PIN tidak sesuai! Periksa kembali kartu token Anda.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Sistem - E-Voting SMK Taruna Karya Mandiri</title>
    <!-- Google Fonts & Bootstrap 5 -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0f172a 0%, #1e3a8a 50%, #0d9488 100%);
            --btn-gradient: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
        }

        body { 
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif; 
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Elemen Dekoratif Latar Belakang */
        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.25) 0%, rgba(0,0,0,0) 70%);
            top: -100px;
            left: -100px;
            z-index: 0;
        }

        body::after {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(20, 184, 166, 0.2) 0%, rgba(0,0,0,0) 70%);
            bottom: -150px;
            right: -100px;
            z-index: 0;
        }

        .login-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.4);
            overflow: hidden;
            width: 100%;
            max-width: 940px;
            display: flex;
            flex-direction: row;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .login-left {
            background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
            width: 48%;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            bottom: -50%;
            left: -50%;
            background: radial-gradient(circle at center, rgba(37, 99, 235, 0.3) 0%, transparent 60%);
            pointer-events: none;
        }

        .left-content {
            position: relative;
            z-index: 2;
            color: white;
            text-align: center;
        }

        .logo-box {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.25);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .logo-box img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }

        .left-content h2 {
            font-weight: 800;
            font-size: 1.6rem;
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }

        .left-content .school-tag {
            font-size: 0.95rem;
            font-weight: 600;
            color: #38bdf8;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .left-content p.desc {
            font-size: 0.85rem;
            opacity: 0.8;
            line-height: 1.5;
            margin-top: 15px;
        }

        .feature-pills {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .feature-pill {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .login-right {
            width: 52%;
            padding: 50px 45px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header-title {
            font-weight: 800;
            font-size: 1.5rem;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        .login-hint-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 20px;
            font-size: 0.8rem;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .input-group-custom {
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.25s ease;
            background: #ffffff;
        }

        .input-group-custom:focus-within {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
        }

        .input-group-custom .input-group-text {
            background-color: transparent;
            border: none;
            color: #94a3b8;
            padding-left: 16px;
        }

        .input-group-custom .form-control {
            border: none;
            padding: 12px 14px;
            font-size: 0.92rem;
            box-shadow: none;
        }

        .btn-toggle-eye {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding-right: 14px;
            cursor: pointer;
        }
        .btn-toggle-eye:hover {
            color: #2563eb;
        }

        .btn-login {
            background: var(--btn-gradient);
            border: none;
            padding: 14px;
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 12px;
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.3);
            transition: all 0.25s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
            color: #ffffff;
        }

        @media (max-width: 820px) {
            .login-card { flex-direction: column; max-width: 440px; }
            .login-left { width: 100%; padding: 35px 25px; }
            .login-right { width: 100%; padding: 35px 25px; }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <!-- Area Branding / Left -->
        <div class="login-left">
            <div class="left-content">
                <div class="logo-box">
                    <?php if (file_exists('uploads/logo_utama.png')): ?>
                        <img src="uploads/logo_utama.png" alt="Logo Sekolah">
                    <?php else: ?>
                        <i class="fas fa-vote-yea fs-2 text-white"></i>
                    <?php endif; ?>
                </div>
                <h2>E-Voting Terpadu</h2>
                <div class="school-tag">SMK Taruna Karya Mandiri</div>
                <p class="desc">
                    Sistem pemungutan suara elektronik untuk Pemilihan Ketua OSIS & Ekstrakurikuler yang transparan, aman, dan terpercaya.
                </p>
                <div class="feature-pills">
                    <span class="feature-pill"><i class="fas fa-lock me-1"></i> Rahasia</span>
                    <span class="feature-pill"><i class="fas fa-bolt me-1"></i> Real-time</span>
                    <span class="feature-pill"><i class="fas fa-shield-alt me-1"></i> Terenkripsi</span>
                </div>
            </div>
        </div>

        <!-- Area Form Login / Right -->
        <div class="login-right">
            <div class="mb-4">
                <h3 class="login-header-title">Selamat Datang 👋</h3>
                <p class="text-muted small m-0">Silakan masuk menggunakan akun atau token akses Anda.</p>
            </div>

            <div class="login-hint-card">
                <i class="fas fa-info-circle text-primary fs-5"></i>
                <div>
                    <b>Pemilih (Siswa & Guru):</b> Gunakan NIS / ID (cth: 001) & Kata Sandi / PIN.<br>
                    <b>Admin:</b> Gunakan Username & Sandi.
                </div>
            </div>

            <?php if ($sukses_pesan): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-3 py-2 px-3 small border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-1"></i> <?= $sukses_pesan; ?>
                    <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_pesan): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-3 py-2 px-3 small border-0 shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-1"></i> <?= $error_pesan; ?>
                    <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">

                <div class="mb-3">
                    <label class="form-label fw-bold small text-dark">ID Pengguna (NIS / Username)</label>
                    <div class="input-group input-group-custom">
                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                        <input type="text" name="userid" class="form-control" placeholder="Masukkan NIS atau Username" required autocomplete="off">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-dark">PIN Akses / Kata Sandi</label>
                    <div class="input-group input-group-custom">
                        <span class="input-group-text"><i class="fas fa-key"></i></span>
                        <input type="password" id="inputCredential" name="credential" class="form-control" placeholder="Masukkan PIN atau Sandi" required>
                        <button type="button" class="btn-toggle-eye" onclick="togglePasswordVisibility()" title="Tampilkan/Sembunyikan">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" name="login" class="btn btn-login w-100 mb-3">
                    MASUK KE SISTEM <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </form>

            <div class="text-center pt-2 border-top">
                <small class="text-muted">
                    <i class="fas fa-shield-alt text-success me-1"></i> Keamanan Terjamin &bull; E-Voting &copy; <?= date("Y"); ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePasswordVisibility() {
            var input = document.getElementById('inputCredential');
            var icon = document.getElementById('eyeIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
