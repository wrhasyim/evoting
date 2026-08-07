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
// FITUR MANAJEMEN DATABASE
// ==========================================

// EXPORT DATABASE
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

// IMPORT DATABASE
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

// RESET TOTAL SISTEM
if (isset($_POST['reset_total'])) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tabel_direset = ['suara_masuk', 'riwayat_pilih', 'kandidat', 'anggota_eskul', 'siswa', 'eskul', 'periode'];
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

// UNGGAH VISUAL (BANNER & LOGO)
if (isset($_POST['upload_visual'])) {
    $direktori_simpan = '../uploads/';
    if (!file_exists($direktori_simpan)) { mkdir($direktori_simpan, 0777, true); }
    $ekstensi_valid = ['png', 'jpg', 'jpeg'];
    
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

// PEMBARUAN PROFIL & PASSWORD
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

// ==========================================
// FITUR TAMBAHAN MAINTENANCE
// ==========================================

// RESET DATA SUARA SAJA
if (isset($_POST['eksekusi_reset_suara'])) {
    $konfirmasi = trim($_POST['konfirmasi_teks_suara']);
    if ($konfirmasi === 'RESET') {
        try {
            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM suara_masuk");
            $pdo->exec("DELETE FROM riwayat_pilih");
            $pdo->exec("UPDATE siswa SET status_pilih = 0");
            $pdo->commit();
            $pesan_notifikasi .= "<div class='alert alert-success fw-bold'><i class='fas fa-check-circle me-2'></i> Berhasil! Seluruh data suara telah dibersihkan. Sistem kembali ke Titik Nol.</div>";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $pesan_notifikasi .= "<div class='alert alert-danger'>Terjadi kesalahan sistem: " . $e->getMessage() . "</div>";
        }
    } else {
        $pesan_notifikasi .= "<div class='alert alert-danger'>Gagal: Kata konfirmasi kotak suara tidak cocok.</div>";
    }
}

// HAPUS FOTO KANDIDAT
if (isset($_POST['eksekusi_hapus_foto'])) {
    $konfirmasi_foto = trim($_POST['konfirmasi_foto']);
    if ($konfirmasi_foto === 'HAPUS FOTO') {
        $folder_uploads = '../uploads/';
        $files = glob($folder_uploads . '*'); 
        $jumlah_dihapus = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                $nama_file = basename($file);
                if ($nama_file !== '.htaccess' && $nama_file !== 'logo_utama.png' && $nama_file !== 'banner_utama.png' && $nama_file !== 'banner_utama.jpg') {
                    unlink($file); 
                    $jumlah_dihapus++;
                }
            }
        }
        $pdo->exec("UPDATE kandidat SET foto = ''");
        $pesan_notifikasi .= "<div class='alert alert-success fw-bold'><i class='fas fa-trash-alt me-2'></i> Berhasil! Menghapus $jumlah_dihapus file foto fisik.</div>";
    } else {
        $pesan_notifikasi .= "<div class='alert alert-danger'>Gagal: Kata konfirmasi hapus foto tidak cocok.</div>";
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
    <title>Pengaturan Terpadu - E-Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fa; overflow-x: hidden; }
        
        /* SOLUSI NAVBAR MELAR: Mencegah teks navbar turun ke baris baru */
        .nav-link { white-space: nowrap !important; }

        .top-header { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .form-container { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); height: 100%; }
    </style>
</head>
<body>

    <!-- MEMANGGIL NAVBAR DARI FILE SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA DIBUNGKUS CLASS .content -->
    <div class="content">
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Pengaturan Terpadu</h4>
                <small class="text-muted">Pusat kendali akun, visual, dan pemeliharaan data.</small>
            </div>
        </div>

        <?= $pesan_notifikasi; ?>

        <!-- BARIS 1: Profil & Manajemen Database -->
        <div class="row g-4 mb-4">
            
            <!-- Keamanan Akun -->
            <div class="col-lg-6">
                <div class="form-container border-top border-primary border-5 bg-white">
                    <form method="POST" action="">
                        <h5 class="fw-bold mb-4 border-bottom pb-2 text-primary"><i class="fas fa-user-shield me-2"></i> Keamanan Akun</h5>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($data_admin['nama_lengkap'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-medium">Username Login</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($data_admin['username'] ?? ''); ?>" required>
                        </div>
                        <div class="alert alert-warning small">Kosongkan kolom <b>Password Baru</b> jika hanya mengubah teks di atas.</div>
                        <div class="mb-3">
                            <label class="form-label fw-medium">Password Baru</label>
                            <input type="password" name="password_baru" class="form-control" placeholder="Opsional">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-medium">Konfirmasi Password Baru</label>
                            <input type="password" name="konfirmasi_password" class="form-control" placeholder="Opsional">
                        </div>
                        <div class="mb-4 p-3 bg-light rounded border border-danger">
                            <label class="form-label fw-bold text-danger"><i class="fas fa-key me-2"></i>Otorisasi Perubahan</label>
                            <input type="password" name="password_lama" class="form-control border-danger" placeholder="Masukkan Password saat ini" required>
                        </div>
                        <button type="submit" name="simpan_pengaturan" class="btn btn-primary w-100 fw-bold py-2"><i class="fas fa-save me-2"></i> Simpan Profil</button>
                    </form>
                </div>
            </div>

            <!-- Database & Visual -->
            <div class="col-lg-6">
                <!-- Visual -->
                <div class="form-container border-top border-info border-5 mb-4 bg-white" style="height: auto;">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <h5 class="fw-bold mb-3 border-bottom pb-2 text-info"><i class="fas fa-paint-brush me-2"></i> Kustomisasi Visual</h5>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Banner Sekolah (JPG/PNG, Max 2MB)</label>
                            <input type="file" name="banner_sekolah" class="form-control form-control-sm" accept=".jpg, .jpeg, .png">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium">Logo Sekolah (PNG, Max 1MB)</label>
                            <input type="file" name="logo_sekolah" class="form-control form-control-sm" accept=".png">
                        </div>
                        <button type="submit" name="upload_visual" class="btn btn-info text-white w-100 fw-bold"><i class="fas fa-upload me-2"></i> Simpan Visual</button>
                    </form>
                </div>

                <!-- Backup & Restore -->
                <div class="form-container border-top border-success border-5 bg-white" style="height: auto;">
                    <h5 class="fw-bold mb-3 border-bottom pb-2 text-success"><i class="fas fa-database me-2"></i> Manajemen Database</h5>
                    <form method="POST" action="" class="mb-3">
                        <button type="submit" name="export_db" class="btn btn-success w-100 fw-bold"><i class="fas fa-download me-2"></i> Download Backup (.sql)</button>
                    </form>
                    <form method="POST" action="" enctype="multipart/form-data" class="bg-light p-2 border rounded">
                        <input type="file" name="file_sql" class="form-control form-control-sm mb-2" accept=".sql" required>
                        <button type="submit" name="import_db" class="btn btn-outline-dark btn-sm w-100 fw-bold" onclick="return confirm('Semua data saat ini akan terganti. Anda yakin?');">Mulai Restore</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- BARIS 2: Maintenance Area -->
        <h5 class="fw-bold text-danger mt-4 mb-3 border-bottom border-danger pb-2"><i class="fas fa-tools me-2"></i> Area Berbahaya (Maintenance)</h5>
        <div class="row g-4 mb-5">
            
            <!-- Reset Kotak Suara -->
            <div class="col-md-4">
                <div class="form-container border-top border-warning border-5 text-center">
                    <i class="fas fa-box-open text-warning fs-1 mb-2"></i>
                    <h6 class="fw-bold text-warning">Reset Kotak Suara</h6>
                    <p class="small text-muted mb-3">Menghapus perolehan suara saja. Ideal setelah simulasi.</p>
                    <form method="POST" action="">
                        <input type="text" name="konfirmasi_teks_suara" class="form-control form-control-sm text-center fw-bold border-warning mb-2" placeholder="Ketik RESET" required>
                        <button type="submit" name="eksekusi_reset_suara" class="btn btn-warning btn-sm w-100 fw-bold text-dark"><i class="fas fa-eraser me-1"></i> Kosongkan Suara</button>
                    </form>
                </div>
            </div>

            <!-- Bersihkan Foto -->
            <div class="col-md-4">
                <div class="form-container border-top border-secondary border-5 text-center">
                    <i class="fas fa-images text-secondary fs-1 mb-2"></i>
                    <h6 class="fw-bold text-secondary">Bersihkan Foto</h6>
                    <p class="small text-muted mb-3">Menghapus file fisik foto kandidat lama (Logo/Banner tetap aman).</p>
                    <form method="POST" action="">
                        <input type="text" name="konfirmasi_foto" class="form-control form-control-sm text-center fw-bold border-secondary mb-2" placeholder="Ketik HAPUS FOTO" required>
                        <button type="submit" name="eksekusi_hapus_foto" class="btn btn-secondary btn-sm w-100 fw-bold"><i class="fas fa-broom me-1"></i> Bersihkan Storage</button>
                    </form>
                </div>
            </div>

            <!-- Reset Total -->
            <div class="col-md-4">
                <div class="form-container border-top border-danger border-5 text-center bg-light">
                    <i class="fas fa-skull-crossbones text-danger fs-1 mb-2"></i>
                    <h6 class="fw-bold text-danger">Reset Total Sistem</h6>
                    <p class="small text-muted mb-3">Mengosongkan SEMUA data siswa, kandidat, dan suara.</p>
                    <form method="POST" action="">
                        <button type="submit" name="reset_total" class="btn btn-danger btn-sm w-100 fw-bold mt-4" onclick="return confirm('PERINGATAN KERAS! Semua data akan hilang permanen. Yakin?');"><i class="fas fa-trash-alt me-1"></i> RESET TOTAL</button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <!-- Script Bootstrap Wajib untuk fungsionalitas Dropdown menu Profil -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>