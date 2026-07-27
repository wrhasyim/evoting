<?php
// admin/pengaturan.php

session_start();
require '../config/koneksi.php';

// 1. PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$pesan_notifikasi = '';
$id_admin = $_SESSION['id_admin'];

// 2. MENGAMBIL DATA ADMIN SAAT INI
$stmt = $pdo->prepare("SELECT username, nama_lengkap FROM admin WHERE id_admin = ?");
$stmt->execute([$id_admin]);
$data_admin = $stmt->fetch();

// 3. PROSES PEMBARUAN PROFIL & PASSWORD
if (isset($_POST['simpan_pengaturan'])) {
    $nama_baru = trim($_POST['nama_lengkap']);
    $username_baru = trim($_POST['username']);
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    // Ambil password hash lama dari database untuk verifikasi
    $stmt_pass = $pdo->prepare("SELECT password FROM admin WHERE id_admin = ?");
    $stmt_pass->execute([$id_admin]);
    $hash_lama = $stmt_pass->fetchColumn();

    // Verifikasi password lama
    if (password_verify($password_lama, $hash_lama)) {
        
        // Skenario A: Admin juga ingin mengganti password
        if (!empty($password_baru)) {
            if ($password_baru === $konfirmasi_password) {
                // Enkripsi password baru
                $hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);
                
                $update = $pdo->prepare("UPDATE admin SET nama_lengkap = ?, username = ?, password = ? WHERE id_admin = ?");
                $update->execute([$nama_baru, $username_baru, $hash_baru, $id_admin]);
                
                $pesan_notifikasi = "<div class='alert alert-success'>Profil dan Password berhasil diperbarui!</div>";
                
                // Perbarui sesi
                $_SESSION['nama_lengkap'] = $nama_baru;
            } else {
                $pesan_notifikasi = "<div class='alert alert-danger'>Gagal: Password baru dan konfirmasi tidak cocok.</div>";
            }
        } 
        // Skenario B: Admin HANYA ingin mengganti Nama/Username (Password baru dikosongkan)
        else {
            $update = $pdo->prepare("UPDATE admin SET nama_lengkap = ?, username = ? WHERE id_admin = ?");
            $update->execute([$nama_baru, $username_baru, $id_admin]);
            
            $pesan_notifikasi = "<div class='alert alert-success'>Profil berhasil diperbarui!</div>";
            
            // Perbarui sesi
            $_SESSION['nama_lengkap'] = $nama_baru;
        }
        
    } else {
        $pesan_notifikasi = "<div class='alert alert-danger'>Gagal: Password saat ini (lama) yang Anda masukkan salah.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Sistem - E-Voting</title>
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
        .form-container { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); max-width: 600px; }
    </style>
</head>
<body>

    <!-- MEMANGGIL SIDEBAR DARI FILE TERPISAH -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Pengaturan Keamanan</h4>
                <small class="text-muted">Kelola profil dan kata sandi administrator Anda.</small>
            </div>
        </div>

        <?= $pesan_notifikasi; ?>

        <div class="form-container border-top border-primary border-5">
            <form method="POST" action="">
                <h5 class="fw-bold mb-4 border-bottom pb-2">Data Profil</h5>
                
                <div class="mb-3">
                    <label class="form-label fw-medium">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($data_admin['nama_lengkap']); ?>" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-medium">Username Login</label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($data_admin['username']); ?>" required>
                </div>

                <h5 class="fw-bold mb-4 border-bottom pb-2">Ubah Kata Sandi</h5>
                <div class="alert alert-warning small">
                    Kosongkan kolom <b>Password Baru</b> jika Anda hanya ingin mengubah Nama/Username.
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium">Password Baru</label>
                    <input type="password" name="password_baru" class="form-control" placeholder="Masukkan kata sandi baru (Opsional)">
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-medium">Konfirmasi Password Baru</label>
                    <input type="password" name="konfirmasi_password" class="form-control" placeholder="Ketik ulang kata sandi baru">
                </div>

                <div class="mb-4 p-3 bg-light rounded border border-danger">
                    <label class="form-label fw-bold text-danger"><i class="fas fa-shield-alt me-2"></i>Verifikasi Keamanan</label>
                    <p class="small text-muted mb-2">Untuk menyimpan perubahan di atas, Anda wajib memasukkan kata sandi Anda yang sedang aktif saat ini.</p>
                    <input type="password" name="password_lama" class="form-control border-danger" placeholder="Password saat ini" required>
                </div>

                <button type="submit" name="simpan_pengaturan" class="btn btn-primary w-100 fw-bold py-2">
                    <i class="fas fa-save me-2"></i> Simpan Peraturan
                </button>
            </form>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>