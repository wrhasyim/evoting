<?php
// admin/eskul.php

session_start();
require '../config/koneksi.php';

// PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$pesan_notifikasi = '';

// 1. PROSES TAMBAH ESKUL
if (isset($_POST['tambah_eskul'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $nama_eskul = trim($_POST['nama_eskul']);
    $aturan = $_POST['aturan_pemilih'];
    
    $stmt = $pdo->prepare("INSERT INTO eskul (nama_eskul, aturan_pemilih) VALUES (:nama, :aturan)");
    $stmt->execute(['nama' => $nama_eskul, 'aturan' => $aturan]);
    $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Berhasil menambahkan ekstrakurikuler baru.';</script>";
}

// 2. PROSES EDIT ESKUL
if (isset($_POST['edit_eskul'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_eskul = $_POST['id_eskul'];
    $nama_eskul = trim($_POST['nama_eskul']);
    $aturan = $_POST['aturan_pemilih'];
    
    $stmt = $pdo->prepare("UPDATE eskul SET nama_eskul = :nama, aturan_pemilih = :aturan WHERE id_eskul = :id");
    $stmt->execute(['nama' => $nama_eskul, 'aturan' => $aturan, 'id' => $id_eskul]);
    $pesan_notifikasi = "<script>var notification_type='info'; var notification_title='Informasi'; var notification_message='Data ekstrakurikuler berhasil diperbarui.';</script>";
}

// 3. PROSES HAPUS ESKUL (SOFT DELETE)
if (isset($_POST['hapus_eskul'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_eskul = $_POST['id_eskul'];
    $stmt = $pdo->prepare("UPDATE eskul SET status_aktif = 0 WHERE id_eskul = :id");
    $stmt->execute(['id' => $id_eskul]);
    $pesan_notifikasi = "<script>var notification_type='warning'; var notification_title='Informasi'; var notification_message='Ekstrakurikuler telah dipindahkan ke Tempat Sampah.';</script>";
}

// 4. PROSES BUKA/TUTUP PEMILIHAN
if (isset($_POST['toggle_pemilihan'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_eskul = $_POST['id_eskul'];
    $status_baru = (int) $_POST['status_baru'];
    
    $stmt = $pdo->prepare("UPDATE eskul SET status_pemilihan = :status WHERE id_eskul = :id");
    $stmt->execute(['status' => $status_baru, 'id' => $id_eskul]);
    
    if ($status_baru == 1) {
        $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Pemilihan untuk eskul ini resmi <b>DIBUKA</b>. Siswa kini bisa memilihnya.';</script>";
    } else {
        $pesan_notifikasi = "<script>var notification_type='warning'; var notification_title='Informasi'; var notification_message='Pemilihan untuk eskul ini telah <b>DITUTUP</b>.';</script>";
    }
}

// MENGAMBIL SELURUH DATA ESKUL YANG AKTIF
$stmt_tampil = $pdo->query("SELECT * FROM eskul WHERE status_aktif = 1 ORDER BY id_eskul DESC");
$data_eskul = $stmt_tampil->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Ekstrakurikuler - E-Voting</title>
    <!-- Bootstrap 5 & DataTables -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
                <h4>Manajemen Ekstrakurikuler</h4>
                <small class="text-muted">Atur daftar organisasi/eskul, aturan partisipasi pemilih, dan kontrol buka-tutup bilik suara</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="fas fa-plus"></i> Tambah Eskul
            </button>
        </div>

        <?= $pesan_notifikasi; ?>

        <div class="card-modern">
            <div class="table-responsive">
                <table class="table table-hover align-middle w-100" id="tabelEskul">
                    <thead>
                        <tr>
                            <th style="width: 60px;">No</th>
                            <th>Nama Ekstrakurikuler</th>
                            <th style="width: 220px;">Aturan Hak Pemilih</th>
                            <th style="width: 180px;">Status Pemilihan</th>
                            <th class="text-center" style="width: 160px;">Aksi & Kontrol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_eskul) > 0): ?>
                            <?php $no = 1; foreach ($data_eskul as $row): ?>
                                <tr>
                                    <td class="text-muted fw-bold"><?= $no++; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($row['nama_eskul']); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($row['aturan_pemilih'] == 'semua_siswa'): ?>
                                            <span class="badge badge-soft-primary">
                                                <i class="fas fa-globe me-1"></i> Semua Siswa (Terbuka)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-warning">
                                                <i class="fas fa-users-cog me-1"></i> Khusus Anggota (Tertutup)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($row['status_pemilihan']) && $row['status_pemilihan'] == 1): ?>
                                            <span class="badge badge-soft-success">
                                                <i class="fas fa-door-open me-1"></i> Sedang Dibuka
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-secondary">
                                                <i class="fas fa-lock me-1"></i> Ditutup
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <!-- Tombol Buka/Tutup -->
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                                <input type="hidden" name="id_eskul" value="<?= $row['id_eskul']; ?>">
                                                <?php if (isset($row['status_pemilihan']) && $row['status_pemilihan'] == 1): ?>
                                                    <input type="hidden" name="status_baru" value="0">
                                                    <button type="submit" name="toggle_pemilihan" class="btn btn-sm btn-outline-warning" title="Kunci / Tutup Pemilihan Ini">
                                                        <i class="fas fa-lock"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="status_baru" value="1">
                                                    <button type="submit" name="toggle_pemilihan" class="btn btn-sm btn-outline-success" title="Buka Bilik Pemilihan">
                                                        <i class="fas fa-unlock"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <!-- Tombol Edit -->
                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_eskul']; ?>" title="Edit">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <!-- Tombol Hapus -->
                                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $row['id_eskul']; ?>" title="Hapus">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- MODAL EDIT ESKUL -->
                                <div class="modal fade" id="modalEdit<?= $row['id_eskul']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                                <input type="hidden" name="id_eskul" value="<?= $row['id_eskul']; ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold"><i class="fas fa-edit text-primary me-2"></i> Edit Ekstrakurikuler</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Ekstrakurikuler</label>
                                                        <input type="text" name="nama_eskul" class="form-control" value="<?= htmlspecialchars($row['nama_eskul']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Aturan Hak Pilih</label>
                                                        <select name="aturan_pemilih" class="form-select" required>
                                                            <option value="semua_siswa" <?= $row['aturan_pemilih'] == 'semua_siswa' ? 'selected' : ''; ?>>Semua Siswa (Terbuka untuk Umum)</option>
                                                            <option value="hanya_anggota" <?= $row['aturan_pemilih'] == 'hanya_anggota' ? 'selected' : ''; ?>>Hanya Anggota Terdaftar (Khusus Internal)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_eskul" class="btn btn-primary">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- MODAL HAPUS ESKUL -->
                                <div class="modal fade" id="modalHapus<?= $row['id_eskul']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                                <input type="hidden" name="id_eskul" value="<?= $row['id_eskul']; ?>">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Konfirmasi Penghapusan</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Apakah Anda yakin ingin menonaktifkan ekstrakurikuler <b><?= htmlspecialchars($row['nama_eskul']); ?></b>?</p>
                                                    <small class="text-danger"><i class="fas fa-info-circle me-1"></i> Data kandidat dan suara terkait eskul ini akan diarsipkan.</small>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="hapus_eskul" class="btn btn-danger">Hapus Eskul</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada data ekstrakurikuler.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH ESKUL -->
    <div class="modal fade" id="modalTambah" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i> Tambah Ekstrakurikuler Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Ekstrakurikuler / Organisasi</label>
                            <input type="text" name="nama_eskul" class="form-control" placeholder="Contoh: OSIS / MPK / Pramuka / Rohis" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Aturan Hak Pilih Pemungutan Suara</label>
                            <select name="aturan_pemilih" class="form-select" required>
                                <option value="semua_siswa">Semua Siswa (Terbuka untuk Umum Seluruh Sekolah)</option>
                                <option value="hanya_anggota">Hanya Anggota (Tertutup Khusus Anggota Terdaftar)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_eskul" class="btn btn-primary">Simpan Eskul</button>
                    </div>
                </form>
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
            $('#tabelEskul').DataTable({
                "language": {
                    "search": "Cari Eskul:",
                    "lengthMenu": "Tampilkan _MENU_",
                    "info": "Total _TOTAL_ eskul",
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
