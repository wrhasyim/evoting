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
    header("Location: beranda_siswa.php"); // Halaman siswa yang akan kita buat nanti
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
                
                header("Location: beranda_siswa.php"); // Halaman yang akan kita buat selanjutnya
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
    <title>Login - E-Voting SMK Taruna Karya Mandiri</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f4f7f6; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
        }
        .login-box { 
            background: #fff; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 8px 20px rgba(0,0,0,0.1); 
            width: 100%; 
            max-width: 380px; 
        }
        .text-center { text-align: center; }
        h2 { color: #2c3e50; margin-bottom: 5px; }
        p.subtitle { color: #7f8c8d; font-size: 14px; margin-bottom: 25px; }
        
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 8px; color: #34495e; font-weight: 600; font-size: 14px;}
        input[type="text"], input[type="password"] { 
            width: 100%; 
            padding: 12px; 
            box-sizing: border-box; 
            border: 1px solid #ccc; 
            border-radius: 6px; 
            font-size: 15px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            border-color: #007bff;
            outline: none;
        }
        
        .btn { 
            width: 100%; 
            padding: 12px; 
            background-color: #007bff; 
            color: white; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 16px; 
            font-weight: bold;
            transition: background-color 0.3s;
        }
        .btn:hover { background-color: #0056b3; }
        
        .error { 
            background-color: #f8d7da; 
            color: #721c24; 
            padding: 10px; 
            border-radius: 6px; 
            text-align: center; 
            margin-bottom: 20px; 
            font-size: 14px; 
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="text-center">
            <h2>E-Voting Terpadu</h2>
            <p class="subtitle">SMK Taruna Karya Mandiri</p>
        </div>
        
        <?php if ($error_pesan): ?>
            <div class="error"><?= $error_pesan; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="userid">Username / NIS</label>
                <input type="text" id="userid" name="userid" placeholder="Masukkan ID Anda" required autocomplete="off">
            </div>
            <div class="form-group">
                <label for="credential">Kata Sandi / PIN</label>
                <input type="password" id="credential" name="credential" placeholder="Masukkan Kata Sandi atau PIN" required>
            </div>
            <button type="submit" name="login" class="btn">Masuk Sistem</button>
        </form>
    </div>
</body>
</html>