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
    verify_csrf_token($_POST['csrf_token'] ?? '');
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
    verify_csrf_token($_POST['csrf_token'] ?? '');
    if (isset($_FILES['file_sql']) && $_FILES['file_sql']['error'] == 0) {
        $file_tmp = $_FILES['file_sql']['tmp_name'];
        $sql_contents = file_get_contents($file_tmp);
        
        $nama_file = $_FILES['file_sql']['name'];
        $ekstensi = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));

        $blacklist = ['drop database', 'delete from admin', 'outfile', 'load_file', 'grant all', 'shutdown'];
        $aman = true;

        foreach ($blacklist as $kata_terlarang) {
            if (stripos($sql_contents, $kata_terlarang) !== false) {
                $aman = false;
                break;
            }
        }

        if ($ekstensi === 'sql' && $aman) {
            try {
                $pdo->exec($sql_contents);
                $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Database berhasil di-restore dari file cadangan!';</script>";
            } catch (PDOException $e) {
                $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Gagal melakukan restore: " . addslashes($e->getMessage()) . "';</script>";
            }
        } else {
            $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Akses Ditolak'; var notification_message='File terindikasi mengandung query berbahaya atau bukan format SQL murni.';</script>";
        }
    } else {
        $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Pilih file .sql yang valid terlebih dahulu.';</script>";
    }
}

// RESET TOTAL SISTEM
if (isset($_POST['reset_total'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tabel_direset = ['suara_masuk', 'riwayat_pilih', 'kandidat', 'anggota_eskul', 'siswa', 'eskul', 'periode'];
        foreach ($tabel_direset as $tabel) {
            $pdo->exec("DELETE FROM $tabel;");
            $pdo->exec("ALTER TABLE $tabel AUTO_INCREMENT = 1;");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Sistem berhasil di-reset total! Semua data telah dikosongkan dengan bersih.';</script>";
    } catch (PDOException $e) {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Gagal mereset sistem: " . $e->getMessage() . "';</script>";
    }
}

// UNGGAH VISUAL (LOGO SAJA)
if (isset($_POST['upload_visual'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $direktori_simpan = '../uploads/';
    if (!file_exists($direktori_simpan)) { mkdir($direktori_simpan, 0777, true); }
    $ekstensi_valid = ['png', 'jpg', 'jpeg'];
    
    if (!empty($_FILES['logo_sekolah']['name'])) {
        $nama_file = $_FILES['logo_sekolah']['name'];
        $tmp_file = $_FILES['logo_sekolah']['tmp_name'];
        $ukuran = $_FILES['logo_sekolah']['size'];
        $ekstensi = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
        if (in_array($ekstensi, $ekstensi_valid) && $ukuran <= 2097152) {
            $file_tujuan = $direktori_simpan . 'logo_utama.' . $ekstensi;
            array_map('unlink', glob($direktori_simpan . "logo_utama.*"));
            move_uploaded_file($tmp_file, $file_tujuan);
            $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Logo sekolah resmi berhasil diperbarui!';</script>";
        } else {
            $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Format logo harus JPG/PNG dan maksimal 2MB.';</script>";
        }
    }
}

// PEMBARUAN PROFIL & PASSWORD
if (isset($_POST['simpan_pengaturan'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
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
                $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Profil dan Password admin berhasil diperbarui!';</script>";
                $_SESSION['nama_lengkap'] = $nama_baru;
            } else {
                $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Gagal: Password baru dan konfirmasi tidak cocok.';</script>";
            }
        } else {
            $update = $pdo->prepare("UPDATE admin SET nama_lengkap = ?, username = ? WHERE id_admin = ?");
            $update->execute([$nama_baru, $username_baru, $id_admin]);
            $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Profil admin berhasil diperbarui!';</script>";
            $_SESSION['nama_lengkap'] = $nama_baru;
        }
    } else {
        $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Gagal: Password saat ini (lama) yang Anda masukkan salah.';</script>";
    }
}

// RESET DATA SUARA SAJA
if (isset($_POST['eksekusi_reset_suara'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $konfirmasi = trim($_POST['konfirmasi_teks_suara']);
    if ($konfirmasi === 'RESET') {
        try {
            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM suara_masuk");
            $pdo->exec("DELETE FROM riwayat_pilih");
            $pdo->exec("UPDATE siswa SET status_pilih = 0");
            $pdo->commit();
            $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Berhasil! Seluruh data suara telah dibersihkan. Sistem kembali ke Titik Nol.';</script>";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Terjadi kesalahan sistem: " . addslashes($e->getMessage()) . "';</script>";
        }
    } else {
        $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Gagal: Kata konfirmasi teks tidak cocok.';</script>";
    }
}

// HAPUS FOTO KANDIDAT
if (isset($_POST['eksekusi_hapus_foto'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $konfirmasi_foto = trim($_POST['konfirmasi_foto']);
    if ($konfirmasi_foto === 'HAPUS FOTO') {
        $folder_uploads = '../uploads/';
        $files = glob($folder_uploads . '*'); 
        $jumlah_dihapus = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                $nama_file = basename($file);
                if ($nama_file !== '.htaccess' && $nama_file !== 'logo_utama.png' && $nama_file !== 'logo_utama.jpg' && $nama_file !== 'logo_utama.jpeg' && $nama_file !== 'banner_utama.png' && $nama_file !== 'banner_utama.jpg') {
                    unlink($file); 
                    $jumlah_dihapus++;
                }
            }
        }
        $pdo->exec("UPDATE kandidat SET foto = ''");
        $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Berhasil menghapus $jumlah_dihapus file foto fisik kandidat.';</script>";
    } else {
        $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Gagal: Kata konfirmasi hapus foto tidak cocok.';</script>";
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
    <title>Pengaturan Terpadu Sistem - E-Voting</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- NAVBAR ATAS -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <div>
                <h4>Pengaturan Terpadu Sistem</h4>
                <small class="text-muted">Pusat kendali akun administrator, backup database, kustomisasi visual, dan pemeliharaan</small>
            </div>
        </div>

        <?= $pesan_notifikasi; ?>

        <!-- BARIS 1: Profil & Backup Database -->
        <div class="row g-4 mb-4">
            
            <!-- Keamanan Akun Administrator -->
            <div class="col-lg-6">
                <div class="card-modern h-100" style="border-top: 5px solid #2563eb;">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <h5 class="fw-bold mb-3 pb-2 border-bottom text-primary">
                            <i class="fas fa-user-shield me-2"></i> Keamanan Akun Administrator
                        </h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap Administrator</label>
                            <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($data_admin['nama_lengkap'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username Login</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($data_admin['username'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="alert alert-light border small text-muted my-3">
                            <i class="fas fa-info-circle text-primary me-1"></i> Kosongkan kolom <b>Password Baru</b> jika Anda hanya ingin memperbarui nama/username.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password Baru (Opsional)</label>
                            <input type="password" name="password_baru" class="form-control" placeholder="Masukkan password baru jika ingin diganti">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" name="konfirmasi_password" class="form-control" placeholder="Ulangi password baru">
                        </div>
                        
                        <div class="mb-4 p-3 bg-light rounded-3 border border-danger">
                            <label class="form-label fw-bold text-danger"><i class="fas fa-key me-1"></i> Otorisasi Password Saat Ini</label>
                            <input type="password" name="password_lama" class="form-control border-danger" placeholder="Ketikkan kata sandi Anda saat ini" required>
                            <small class="text-muted">Dibutuhkan untuk memvalidasi setiap perubahan pada akun.</small>
                        </div>

                        <button type="submit" name="simpan_pengaturan" class="btn btn-primary w-100">
                            <i class="fas fa-save me-1"></i> Simpan Profil & Keamanan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Database & Visual -->
            <div class="col-lg-6">
                <!-- Visual / Logo Sekolah -->
                <div class="card-modern mb-4" style="border-top: 5px solid #06b6d4;">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <h5 class="fw-bold mb-3 pb-2 border-bottom text-info">
                            <i class="fas fa-image me-2"></i> Logo Resmi Sekolah
                        </h5>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="p-2 border rounded-3 bg-light text-center" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                                <?php if (file_exists('../uploads/logo_utama.png')): ?>
                                    <img src="../uploads/logo_utama.png?v=<?= time(); ?>" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                <?php else: ?>
                                    <i class="fas fa-school fs-2 text-secondary"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <label class="form-label small fw-bold">Upload File Logo Baru (PNG/JPG, Maks. 2MB)</label>
                                <input type="file" name="logo_sekolah" class="form-control form-control-sm" accept=".png, .jpg, .jpeg" required>
                                <small class="text-muted">Logo akan otomatis muncul di kop cetak token, kartu voting, dan navbar.</small>
                            </div>
                        </div>
                        <button type="submit" name="upload_visual" class="btn btn-outline-info w-100 fw-bold">
                            <i class="fas fa-upload me-1"></i> Perbarui Logo Resmi
                        </button>
                    </form>
                </div>

                <!-- Backup & Restore -->
                <div class="card-modern" style="border-top: 5px solid #10b981;">
                    <h5 class="fw-bold mb-3 pb-2 border-bottom text-success">
                        <i class="fas fa-database me-2"></i> Cadangan Database (Backup & Restore)
                    </h5>
                    <p class="text-muted small">Amankan database secara berkala sebelum dan sesudah pemilihan suara.</p>
                    
                    <form method="POST" action="" class="mb-3">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <button type="submit" name="export_db" class="btn btn-success w-100">
                            <i class="fas fa-download me-1"></i> Download Backup Database (.SQL)
                        </button>
                    </form>

                    <form method="POST" action="" enctype="multipart/form-data" class="p-3 bg-light rounded-3 border">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <label class="form-label small fw-bold text-dark">Restore dari File Backup .SQL</label>
                        <input type="file" name="file_sql" class="form-control form-control-sm mb-2" accept=".sql" required>
                        <button type="submit" name="import_db" class="btn btn-outline-dark btn-sm w-100 fw-bold" onclick="return confirm('PENTING: Seluruh database saat ini akan ditimpa dengan isi file backup. Apakah Anda yakin?');">
                            <i class="fas fa-upload me-1"></i> Eksekusi Restore Database
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- BARIS 2: Maintenance Area -->
        <h5 class="fw-bold text-danger mt-4 mb-3 border-bottom border-danger pb-2">
            <i class="fas fa-tools me-2"></i> Pemeliharaan & Reset Sistem
        </h5>
        <div class="row g-4 mb-4">
            
            <!-- Reset Kotak Suara -->
            <div class="col-md-4">
                <div class="card-modern text-center h-100" style="border-top: 5px solid #f59e0b;">
                    <i class="fas fa-box-open text-warning fs-1 mb-2"></i>
                    <h6 class="fw-bold text-dark">Kosongkan Kotak Suara</h6>
                    <p class="small text-muted mb-3">Menghapus perolehan suara saja & mengembalikan status pemilih ke belum memilih. Ideal setelah simulasi.</p>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <input type="text" name="konfirmasi_teks_suara" class="form-control form-control-sm text-center fw-bold border-warning mb-2" placeholder="Ketik kata: RESET" required autocomplete="off">
                        <button type="submit" name="eksekusi_reset_suara" class="btn btn-warning btn-sm w-100 fw-bold text-dark">
                            <i class="fas fa-eraser me-1"></i> Kosongkan Kotak Suara
                        </button>
                    </form>
                </div>
            </div>

            <!-- Bersihkan Foto Fisik -->
            <div class="col-md-4">
                <div class="card-modern text-center h-100" style="border-top: 5px solid #64748b;">
                    <i class="fas fa-images text-secondary fs-1 mb-2"></i>
                    <h6 class="fw-bold text-dark">Bersihkan Foto Fisik</h6>
                    <p class="small text-muted mb-3">Menghapus file foto kandidat lama di penyimpanan server (file logo utama tetap aman).</p>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <input type="text" name="konfirmasi_foto" class="form-control form-control-sm text-center fw-bold border-secondary mb-2" placeholder="Ketik kata: HAPUS FOTO" required autocomplete="off">
                        <button type="submit" name="eksekusi_hapus_foto" class="btn btn-secondary btn-sm w-100 fw-bold">
                            <i class="fas fa-broom me-1"></i> Bersihkan File Fisik
                        </button>
                    </form>
                </div>
            </div>

            <!-- Reset Total Sistem -->
            <div class="col-md-4">
                <div class="card-modern text-center h-100" style="border-top: 5px solid #ef4444; background: #fffafb;">
                    <i class="fas fa-exclamation-triangle text-danger fs-1 mb-2"></i>
                    <h6 class="fw-bold text-danger">Reset Total Seluruh Sistem</h6>
                    <p class="small text-muted mb-3">Mengosongkan SELURUH data siswa, eskul, kandidat, dan suara secara permanen.</p>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <button type="submit" name="reset_total" class="btn btn-danger btn-sm w-100 fw-bold mt-4" onclick="return confirm('PERINGATAN KERAS! Seluruh data akan terhapus permanen dan tidak bisa dikembalikan. Lanjutkan?');">
                            <i class="fas fa-trash-alt me-1"></i> RESET TOTAL SISTEM
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <!-- Script Bootstrap & SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/scripts.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>
