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

// ==========================================
// FITUR: EXPORT DATABASE (BACKUP)
// ==========================================
if (isset($_POST['export_db'])) {
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sql_dump = "-- E-Voting Database Backup\n";
    $sql_dump .= "-- Waktu Backup: " . date('Y-m-d H:i:s') . "\n\n";
    $sql_dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $sql_dump .= "DROP TABLE IF EXISTS $table;\n";
        $sql_dump .= $row[1] . ";\n\n";

        $stmt = $pdo->query("SELECT * FROM $table");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
            $sql_dump .= "INSERT INTO $table VALUES \n";
            $values = [];
            foreach ($rows as $row_data) {
                $row_values = [];
                foreach ($row_data as $val) {
                    if ($val === null) {
                        $row_values[] = "NULL";
                    } else {
                        $row_values[] = $pdo->quote($val);
                    }
                }
                $values[] = "(" . implode(", ", $row_values) . ")";
            }
            $sql_dump .= implode(",\n", $values) . ";\n\n";
        }
    }
    
    $sql_dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="backup_evoting_' . date('Y_m_d_His') . '.sql"');
    echo $sql_dump;
    exit; 
}

// ==========================================
// FITUR: IMPORT DATABASE (RESTORE)
// ==========================================
if (isset($_POST['import_db'])) {
    if (isset($_FILES['file_sql']) && $_FILES['file_sql']['error'] == 0) {
        $file_tmp = $_FILES['file_sql']['tmp_name'];
        $sql_contents = file_get_contents($file_tmp);
        
        try {
            $pdo->exec($sql_contents);
            $pesan_notifikasi = "<div class='alert alert-success'><i class='fas fa-check-circle me-2'></i>Database berhasil di-restore dari file cadangan!</div>";
        } catch (PDOException $e) {
            $pesan_notifikasi = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>Gagal melakukan restore: " . $e->getMessage() . "</div>";
        }
    } else {
        $pesan_notifikasi = "<div class='alert alert-danger'>Pilih file .sql yang valid terlebih dahulu.</div>";
    }
}

// ==========================================
// FITUR: RESET TOTAL SISTEM
// ==========================================
if (isset($_POST['reset_total'])) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        
        $tabel_direset = [
            'suara_masuk', 
            'riwayat_pilih', 
            'kandidat', 
            'anggota_eskul', 
            'siswa', 
            'eskul', 
            'periode'
        ];

        foreach ($tabel_direset as $tabel) {
            $pdo->exec("DELETE FROM $tabel;");
            $pdo->exec("ALTER TABLE $tabel AUTO_INCREMENT = 1;");
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        
        $pesan_notifikasi = "<div class='alert alert-success fw-bold'><i class='fas fa-check-circle me-2'></i>Sistem berhasil di-reset total! Semua data telah dikosongkan dengan bersih.</div>";
    } catch (PDOException $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $pesan_notifikasi = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>Gagal mereset sistem: " . $e->getMessage() . "</div>";
    }
}

// ==========================================
// FITUR BARU: UNGGAH VISUAL (BANNER & LOGO)
// ==========================================
if (isset($_POST['upload_visual'])) {
    $direktori_simpan = '../uploads/';
    
    // Pastikan folder uploads ada
    if (!file_exists($direktori_simpan)) {
        mkdir($direktori_simpan, 0777, true);
    }
    
    $ekstensi_valid = ['png', 'jpg', 'jpeg'];
    
    // Proses Banner
    if (!empty($_FILES['banner_sekolah']['name'])) {
        $nama_file = $_FILES['banner_sekolah']['name'];
        $tmp_file = $_FILES['banner_sekolah']['tmp_name'];
        $ukuran = $_FILES['banner_sekolah']['size'];
        $ekstensi = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
        
        if (in_array($ekstensi, $ekstensi_valid) && $ukuran <= 2097152) {
            $file_tujuan = $direktori_simpan . 'banner_utama.' . $ekstensi;
            array_map('unlink', glob($direktori_simpan . "banner_utama.*"));
            move_uploaded_file($tmp_file, $file_tujuan);
            $pesan_notifikasi .= "<div class='alert alert-success'>Banner berhasil diperbarui!</div>";
        } else {
            $pesan_notifikasi .= "<div class='alert alert-danger'>Format banner harus JPG/PNG dan maksimal 2MB.</div>";
        }
    }

    // Proses Logo
    if (!empty($_FILES['logo_sekolah']['name'])) {
        $nama_file = $_FILES['logo_sekolah']['name'];
        $tmp_file = $_FILES['logo_sekolah']['tmp_name'];
        $ukuran = $_FILES['logo_sekolah']['size'];
        $ekstensi = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
        
        if (in_array($ekstensi, $ekstensi_valid) && $ukuran <= 1048576) {
            $file_tujuan = $direktori_simpan . 'logo_utama.' . $ekstensi;
            array_map('unlink', glob($direktori_simpan . "logo_utama.*"));
            move_uploaded_file($tmp_file, $file_tujuan);
            $pesan_notifikasi .= "<div class='alert alert-success'>Logo berhasil diperbarui!</div>";
        } else {
            $pesan_notifikasi .= "<div class='alert alert-danger'>Format logo harus JPG/PNG dan maksimal 1MB.</div>";
        }
    }
}

// ==========================================
// PROSES PEMBARUAN PROFIL & PASSWORD
// ==========================================
if (isset($_POST['simpan_pengaturan'])) {
    $nama_baru = trim($_POST['nama_lengkap']);
    $username_baru = trim($_POST['username']);
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    $stmt_pass = $pdo->prepare("SELECT password FROM admin WHERE id_admin = ?");
    $stmt_pass->execute([$id_admin]);
    $hash_lama = $stmt_pass->fetchColumn();

    if (password_verify($password_lama, $hash_lama)) {
        if (!empty($password_baru)) {
            if ($password_baru === $konfirmasi_password) {
                $hash_baru = password_hash($password_baru, PASSWORD_DEFAULT);
                $update = $pdo->prepare("UPDATE admin SET nama_lengkap = ?, username = ?, password = ? WHERE id_admin = ?");
                $update->execute([$nama_baru, $username_baru, $hash_baru, $id_admin]);
                $pesan_notifikasi .= "<div class='alert alert-success'>Profil dan Password berhasil diperbarui!</div>";
                $_SESSION['nama_lengkap'] = $nama_baru;
            } else {
                $pesan_notifikasi .= "<div class='alert alert-danger'>Gagal: Password baru dan konfirmasi tidak cocok.</div>";
            }
        } else {
            $update = $pdo->prepare("UPDATE admin SET nama_lengkap = ?, username = ? WHERE id_admin = ?");
            $update->execute([$nama_baru, $username_baru, $id_admin]);
            $pesan_notifikasi .= "<div class='alert alert-success'>Profil berhasil diperbarui!</div>";
            $_SESSION['nama_lengkap'] = $nama_baru;
        }
    } else {
        $pesan_notifikasi .= "<div class='alert alert-danger'>Gagal: Password saat ini (lama) yang Anda masukkan salah.</div>";
    }
}

// Mengambil Data Admin Saat Ini
$stmt = $pdo->prepare("SELECT username, nama_lengkap FROM admin WHERE id_admin = ?");
$stmt->execute([$id_admin]);
$data_admin = $stmt->fetch();
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
        .form-container { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); }
    </style>
</head>
<body>

    <!-- MEMANGGIL SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Pengaturan Sistem</h4>
                <small class="text-muted">Kelola keamanan profil, manajemen cadangan, kustomisasi visual, dan pembersihan data.</small>
            </div>
        </div>

        <?= $pesan_notifikasi; ?>

        <!-- Membagi layar menjadi 2 kolom agar rapi -->
        <div class="row">
            
            <!-- KOLOM KIRI: Visual & Keamanan -->
            <div class="col-md-6 mb-4">
                
                <!-- Kustomisasi Visual -->
                <div class="form-container border-top border-info border-5 mb-4 bg-white">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <h5 class="fw-bold mb-4 border-bottom pb-2 text-info"><i class="fas fa-paint-brush me-2"></i> Kustomisasi Visual</h5>
                        
                        <div class="mb-4">
                            <label class="form-label fw-medium">Banner Header Pemilihan</label>
                            <input type="file" name="banner_sekolah" class="form-control" accept=".jpg, .jpeg, .png">
                            <small class="text-muted d-block mt-1">Rekomendasi ukuran: 1200x300 pixel (Landscape). Maksimal 2MB.</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-medium">Logo Resmi Sekolah</label>
                            <input type="file" name="logo_sekolah" class="form-control" accept=".png">
                            <small class="text-muted d-block mt-1">Rekomendasi format PNG (latar transparan). Maksimal 1MB.</small>
                        </div>

                        <button type="submit" name="upload_visual" class="btn btn-info text-white w-100 fw-bold py-2 mt-2">
                            <i class="fas fa-upload me-2"></i> Simpan Visual
                        </button>
                    </form>
                </div>

                <!-- Keamanan Akun -->
                <div class="form-container border-top border-primary border-5 bg-white">
                    <form method="POST" action="">
                        <h5 class="fw-bold mb-4 border-bottom pb-2 text-primary"><i class="fas fa-user-shield me-2"></i> Keamanan Akun</h5>
                        
                        <div class="mb-3">
                            <label class="form-label fw-medium">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($data_admin['nama_lengkap']); ?>" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fw-medium">Username Login</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($data_admin['username']); ?>" required>
                        </div>

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
                            <label class="form-label fw-bold text-danger"><i class="fas fa-key me-2"></i>Otorisasi Perubahan</label>
                            <p class="small text-muted mb-2">Masukkan kata sandi Anda saat ini untuk menyimpan pembaruan.</p>
                            <input type="password" name="password_lama" class="form-control border-danger" placeholder="Password saat ini" required>
                        </div>

                        <button type="submit" name="simpan_pengaturan" class="btn btn-primary w-100 fw-bold py-2">
                            <i class="fas fa-save me-2"></i> Simpan Profil
                        </button>
                    </form>
                </div>
            </div>

            <!-- KOLOM KANAN: Export, Import & Reset Database -->
            <div class="col-md-6 mb-4">
                <div class="form-container border-top border-success border-5 mb-4 bg-white">
                    <h5 class="fw-bold mb-4 border-bottom pb-2"><i class="fas fa-database me-2"></i> Manajemen Database</h5>
                    <p class="text-muted small">Cegah kehilangan data dengan melakukan pencadangan (Backup) secara rutin. Anda dapat memulihkan (Restore) sistem menggunakan file .sql hasil unduhan Anda[cite: 3].</p>
                    
                    <!-- Form Export (Download) -->
                    <form method="POST" action="" class="mb-4">
                        <button type="submit" name="export_db" class="btn btn-success w-100 fw-bold py-3 shadow-sm">
                            <i class="fas fa-download me-2 fs-5"></i> Download Backup Data (.sql)
                        </button>
                    </form>

                    <!-- Form Import (Restore) -->
                    <form method="POST" action="" enctype="multipart/form-data" class="bg-light p-3 border rounded">
                        <h6 class="fw-bold text-dark"><i class="fas fa-upload me-2"></i> Restore Database</h6>
                        <div class="mb-3">
                            <label class="form-label small">Pilih File Cadangan (.sql)</label>
                            <input type="file" name="file_sql" class="form-control" accept=".sql" required>
                            <small class="text-danger mt-2 d-block"><b>Peringatan:</b> Melakukan restore akan menimpa seluruh data saat ini[cite: 3].</small>
                        </div>
                        <button type="submit" name="import_db" class="btn btn-outline-dark w-100 fw-bold" onclick="return confirm('Semua data saat ini akan terganti. Anda yakin?');">
                            Mulai Restore
                        </button>
                    </form>
                </div>

                <!-- KOTAK RESET TOTAL -->
                <div class="form-container border-top border-danger border-5 bg-white">
                    <h5 class="fw-bold text-danger mb-3 border-bottom pb-2"><i class="fas fa-skull-crossbones me-2"></i> Reset Total Sistem</h5>
                    <p class="text-muted small">Gunakan fitur ini untuk <b>mengosongkan seluruh isi sistem</b> (Data Tahun Ajaran, Eskul, Siswa, Kandidat, dan Suara Pemilih). Sangat berguna setelah melakukan uji coba menggunakan data <i>dummy</i>[cite: 3].</p>
                    
                    <form method="POST" action="">
                        <button type="submit" name="reset_total" class="btn btn-danger w-100 fw-bold py-2 mt-2" onclick="return confirm('PERINGATAN KERAS!\n\nTindakan ini akan MENGHAPUS SEMUA DATA PERMANEN dari sistem (kecuali akun admin).\n\nApakah Anda benar-benar yakin ingin melakukan RESET TOTAL?');">
                            <i class="fas fa-trash-alt me-2"></i> KOSONGKAN SEMUA DATA
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>