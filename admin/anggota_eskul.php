<?php
// admin/anggota_eskul.php

session_start();
require '../config/koneksi.php';

// 1. PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$pesan_notifikasi = '';

// ==========================================
// FITUR: DOWNLOAD SAMPLE FORMAT CSV 
// ==========================================
if (isset($_POST['download_sample'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=format_import_anggota_eskul.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Nama Siswa', 'Kelas', 'Ekstrakurikuler']);
    fputcsv($output, ['Budi Santoso', 'XI RPL 1', 'Pramuka']);
    fputcsv($output, ['Siti Aminah', 'XI TKJ 2', 'OSIS']);
    fclose($output);
    exit; 
}

// ==========================================
// FITUR: IMPORT DATA CSV
// ==========================================
if (isset($_POST['import_csv'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] == 0) {
        $file_tmp = $_FILES['file_csv']['tmp_name'];
        $handle = fopen($file_tmp, "r");
        
        fgetcsv($handle, 1000, ",");
        
        $sukses = 0;
        $gagal = 0;
        $pesan_error = "";
        $baris = 2;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (empty(trim($data[0])) || empty(trim($data[1])) || empty(trim($data[2]))) {
                $gagal++;
                $pesan_error .= "Baris $baris: Data tidak lengkap.<br>";
                $baris++;
                continue;
            }

            $nama_siswa = trim($data[0]);
            $kelas = trim($data[1]);
            $nama_eskul = trim($data[2]);

            // Cari siswa
            $stmt_s = $pdo->prepare("SELECT id_siswa FROM siswa WHERE LOWER(nama_siswa) LIKE LOWER(?) AND LOWER(kelas) = LOWER(?) AND status_aktif = 1 LIMIT 1");
            $stmt_s->execute(["%$nama_siswa%", $kelas]);
            $id_siswa = $stmt_s->fetchColumn();

            // Cari eskul
            $stmt_e = $pdo->prepare("SELECT id_eskul FROM eskul WHERE LOWER(nama_eskul) LIKE LOWER(?) AND status_aktif = 1 LIMIT 1");
            $stmt_e->execute(["%$nama_eskul%"]);
            $id_eskul = $stmt_e->fetchColumn();

            if ($id_siswa && $id_eskul) {
                $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM anggota_eskul WHERE id_siswa = ? AND id_eskul = ?");
                $stmt_c->execute([$id_siswa, $id_eskul]);
                if ($stmt_c->fetchColumn() == 0) {
                    $stmt_in = $pdo->prepare("INSERT INTO anggota_eskul (id_siswa, id_eskul) VALUES (?, ?)");
                    $stmt_in->execute([$id_siswa, $id_eskul]);
                    $sukses++;
                } else {
                    $gagal++;
                    $pesan_error .= "Baris $baris: Siswa $nama_siswa sudah terdaftar di eskul tersebut.<br>";
                }
            } else {
                $gagal++;
                $pesan_error .= "Baris $baris: Siswa ($nama_siswa - $kelas) atau Eskul ($nama_eskul) tidak ditemukan.<br>";
            }
            $baris++;
        }
        fclose($handle);

        $pesan_notifikasi = "
            <div class='alert alert-info alert-dismissible fade show rounded-3 p-3 shadow-sm' role='alert'>
                <h6 class='alert-heading fw-bold mb-2'><i class='fas fa-info-circle me-2'></i>Laporan Import Anggota</h6>
                <p class='mb-1 small'><b>Berhasil ditambahkan:</b> $sukses data anggota.</p>
                <p class='mb-2 small'><b>Gagal / Dilewati:</b> $gagal data.</p>
                " . (!empty($pesan_error) ? "<div class='bg-light p-2 rounded text-danger small mt-2' style='max-height: 120px; overflow-y: auto;'>$pesan_error</div>" : "") . "
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
        ";
    }
}

// ==========================================
// FITUR: HAPUS ANGGOTA ESKUL
// ==========================================
if (isset($_POST['hapus_anggota'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_anggota = $_POST['id_anggota'];
    $stmt = $pdo->prepare("DELETE FROM anggota_eskul WHERE id_anggota = ?");
    $stmt->execute([$id_anggota]);
    $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Berhasil'; var notification_message='Anggota ekstrakurikuler berhasil dihapus.';</script>";
}

// MENGAMBIL DATA UNTUK DITAMPILKAN DI TABEL
$stmt_data = $pdo->query("
    SELECT a.id_anggota, s.nis, s.nama_siswa, s.kelas, e.nama_eskul 
    FROM anggota_eskul a 
    JOIN siswa s ON a.id_siswa = s.id_siswa 
    JOIN eskul e ON a.id_eskul = e.id_eskul 
    WHERE s.status_aktif = 1 AND e.status_aktif = 1
    ORDER BY e.nama_eskul ASC, s.kelas ASC, s.nama_siswa ASC
");
$data_anggota = $stmt_data->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Anggota Eskul - E-Voting</title>
    <!-- Bootstrap 5 CSS & DataTables -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- NAVBAR ATAS -->
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="top-header">
            <div>
                <h4>Daftar Anggota Ekstrakurikuler</h4>
                <small class="text-muted">Kelola data pemilih khusus (aturan tertutup) menggunakan import CSV atau penetapan manual</small>
            </div>
            <div>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalImport">
                    <i class="fas fa-file-import me-1"></i> Import Anggota Massal
                </button>
            </div>
        </div>

        <?= $pesan_notifikasi; ?>

        <div class="card-modern">
            <div class="table-responsive">
                <table id="tabelAnggota" class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th style="width: 60px;">No</th>
                            <th style="width: 130px;">NIS</th>
                            <th>Nama Siswa</th>
                            <th style="width: 140px;">Kelas</th>
                            <th>Tergabung di Eskul</th>
                            <th class="text-center" style="width: 90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_anggota) > 0): ?>
                            <?php $no = 1; foreach ($data_anggota as $row): ?>
                                <tr>
                                    <td class="text-muted fw-bold"><?= $no++; ?></td>
                                    <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($row['nis']); ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_siswa']); ?></td>
                                    <td><span class="badge badge-soft-secondary font-monospace"><?= htmlspecialchars($row['kelas']); ?></span></td>
                                    <td>
                                        <span class="badge badge-soft-primary">
                                            <i class="fas fa-sitemap me-1"></i> <?= htmlspecialchars($row['nama_eskul']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                            <input type="hidden" name="id_anggota" value="<?= $row['id_anggota']; ?>">
                                            <button type="submit" name="hapus_anggota" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus keanggotaan <?= htmlspecialchars(addslashes($row['nama_siswa'])); ?> dari eskul ini?');" title="Hapus">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL IMPORT CSV -->
    <div class="modal fade" id="modalImport" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-file-csv me-2"></i> Import Anggota Ekstrakurikuler</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border small mb-3">
                        <i class="fas fa-info-circle text-primary me-1"></i>
                        Sistem otomatis mencocokkan siswa berdasarkan <b>Nama</b> dan <b>Kelas</b>.
                    </div>

                    <!-- Tombol Download Sample -->
                    <form method="POST" action="" class="mb-3 text-center">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <button type="submit" name="download_sample" class="btn btn-outline-success btn-sm btn-pill px-4 fw-bold">
                            <i class="fas fa-download me-1"></i> Download Format Template CSV
                        </button>
                    </form>

                    <hr>

                    <!-- Form Upload CSV -->
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih File CSV Anggota</label>
                            <input type="file" name="file_csv" class="form-control" accept=".csv, .txt" required>
                        </div>
                        <button type="submit" name="import_csv" class="btn btn-success w-100 fw-bold">
                            <i class="fas fa-cloud-upload-alt me-2"></i> Mulai Proses Import
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT WAJIB DATATABLES & BOOTSTRAP -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/scripts.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#tabelAnggota').DataTable({
                "language": {
                    "search": "Cari Anggota:",
                    "lengthMenu": "Tampilkan _MENU_",
                    "info": "Total _TOTAL_ anggota",
                    "paginate": { "next": "Lanjut", "previous": "Sebelum" }
                },
                "pageLength": 10,
                "ordering": true
            });
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>
