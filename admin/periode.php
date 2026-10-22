<?php
// admin/periode.php

session_start();
require '../config/koneksi.php';

// PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$pesan_notifikasi = '';

// 1. PROSES TAMBAH PERIODE
if (isset($_POST['tambah_periode'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $nama_periode = trim($_POST['nama_periode']);
    
    $stmt = $pdo->prepare("INSERT INTO periode (nama_periode, status_aktif) VALUES (:nama, 0)");
    $stmt->execute(['nama' => $nama_periode]);
    $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Berhasil menambah periode <b>$nama_periode</b>. Silakan klik \'Aktifkan\' untuk menggunakannya.';</script>";
}

// 2. PROSES EDIT PERIODE
if (isset($_POST['edit_periode'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_periode = $_POST['id_periode'];
    $nama_periode = trim($_POST['nama_periode']);
    
    $stmt = $pdo->prepare("UPDATE periode SET nama_periode = :nama WHERE id_periode = :id");
    $stmt->execute(['nama' => $nama_periode, 'id' => $id_periode]);
    $pesan_notifikasi = "<script>var notification_type='info'; var notification_title='Informasi'; var notification_message='Nama periode berhasil diperbarui.';</script>";
}

// 3. PROSES AKTIFKAN PERIODE
if (isset($_POST['aktifkan_periode'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_periode = $_POST['id_periode'];
    
    $pdo->query("UPDATE periode SET status_aktif = 0");
    
    $stmt = $pdo->prepare("UPDATE periode SET status_aktif = 1 WHERE id_periode = :id");
    $stmt->execute(['id' => $id_periode]);
    
    $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Periode berhasil diaktifkan! Seluruh operasional sistem kini berjalan pada periode ini.';</script>";
}

// 4. PROSES HAPUS PERIODE
if (isset($_POST['hapus_periode'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_periode = $_POST['id_periode'];
    
    $cek_aktif = $pdo->prepare("SELECT status_aktif FROM periode WHERE id_periode = ?");
    $cek_aktif->execute([$id_periode]);
    $status = $cek_aktif->fetchColumn();

    if ($status == 1) {
        $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Gagal: Anda tidak boleh menghapus periode yang sedang AKTIF!';</script>";
    } else {
        $stmt = $pdo->prepare("DELETE FROM periode WHERE id_periode = :id");
        $stmt->execute(['id' => $id_periode]);
        $pesan_notifikasi = "<script>var notification_type='warning'; var notification_title='Informasi'; var notification_message='Periode berhasil dihapus.';</script>";
    }
}

// MENGAMBIL SELURUH DATA PERIODE
$stmt_tampil = $pdo->query("SELECT * FROM periode ORDER BY id_periode DESC");
$data_periode = $stmt_tampil->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Tahun Ajaran - E-Voting</title>
    <!-- Bootstrap 5 CSS & DataTables -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .tr-active-periode {
            background-color: #ecfdf5 !important;
            border-left: 4px solid #10b981;
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <div>
                <h4>Tahun Ajaran / Periode Pemilihan</h4>
                <small class="text-muted">Kelola siklus pemilihan tahunan dan aktifkan periode operasional sistem</small>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="fas fa-plus"></i> Tambah Periode Baru
            </button>
        </div>

        <?= $pesan_notifikasi; ?>

        <div class="card-modern">
            <div class="alert alert-info border-0 rounded-3 p-3 mb-4 d-flex align-items-center gap-3">
                <i class="fas fa-info-circle fs-3 text-primary"></i>
                <div class="small">
                    <b>Catatan Operasional:</b> Sistem hanya memperbolehkan <b>1 (satu) periode aktif</b> dalam satu waktu. Mengaktifkan periode baru akan secara otomatis mengarsipkan periode sebelumnya tanpa menghapus rekapan data pemilih dan riwayat suara.
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle w-100" id="tabelPeriode">
                    <thead>
                        <tr>
                            <th style="width: 60px;">No</th>
                            <th>Nama Angkatan / Periode</th>
                            <th style="width: 200px;">Status Operasional</th>
                            <th class="text-center" style="width: 180px;">Aksi / Kontrol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_periode) > 0): ?>
                            <?php $no = 1; foreach ($data_periode as $row): ?>
                                <tr class="<?= $row['status_aktif'] == 1 ? 'tr-active-periode' : ''; ?>">
                                    <td class="text-muted fw-bold"><?= $no++; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($row['nama_periode']); ?></div>
                                        <?php if ($row['status_aktif'] == 1): ?>
                                            <small class="text-success fw-semibold"><i class="fas fa-check-circle me-1"></i> Periode ini sedang digunakan saat ini</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status_aktif'] == 1): ?>
                                            <span class="badge badge-soft-success">
                                                <i class="fas fa-circle text-success me-1"></i> Sedang Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-secondary">
                                                <i class="fas fa-archive me-1"></i> Diarsipkan
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-1">
                                            <?php if ($row['status_aktif'] == 0): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                                    <input type="hidden" name="id_periode" value="<?= $row['id_periode']; ?>">
                                                    <button type="submit" name="aktifkan_periode" class="btn btn-sm btn-outline-success" title="Jadikan Periode Aktif">
                                                        <i class="fas fa-power-off me-1"></i> Aktifkan
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_periode']; ?>" title="Edit Nama">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            
                                            <?php if ($row['status_aktif'] == 0): ?>
                                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $row['id_periode']; ?>" title="Hapus Periode">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- MODAL EDIT PERIODE -->
                                <div class="modal fade" id="modalEdit<?= $row['id_periode']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                                <input type="hidden" name="id_periode" value="<?= $row['id_periode']; ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold"><i class="fas fa-edit text-primary me-2"></i> Edit Nama Periode</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Periode / Tahun Ajaran</label>
                                                        <input type="text" name="nama_periode" class="form-control" value="<?= htmlspecialchars($row['nama_periode']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_periode" class="btn btn-primary">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- MODAL HAPUS PERIODE -->
                                <div class="modal fade" id="modalHapus<?= $row['id_periode']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                                <input type="hidden" name="id_periode" value="<?= $row['id_periode']; ?>">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Konfirmasi Penghapusan</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Hapus periode <b><?= htmlspecialchars($row['nama_periode']); ?></b> secara permanen?</p>
                                                    <small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> Data pemilih di dalam periode ini akan ikut terhapus.</small>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="hapus_periode" class="btn btn-danger">Hapus Permanen</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH PERIODE -->
    <div class="modal fade" id="modalTambah" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i> Tambah Periode Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Periode / Tahun Ajaran</label>
                            <input type="text" name="nama_periode" class="form-control" placeholder="Contoh: 2026/2027 atau Periode 2027" required autocomplete="off">
                            <small class="text-muted">Periode baru berstatus arsip sampai Anda mengklik 'Aktifkan'.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_periode" class="btn btn-primary">Simpan Periode</button>
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
            $('#tabelPeriode').DataTable({
                "language": {
                    "search": "Cari Periode:",
                    "lengthMenu": "Tampilkan _MENU_",
                    "info": "Total _TOTAL_ periode",
                    "infoEmpty": "Total 0 periode",
                    "emptyTable": "Belum ada periode yang terdaftar.",
                    "zeroRecords": "Tidak ditemukan data yang cocok",
                    "paginate": { "next": "Lanjut", "previous": "Sebelum" }
                },
                "pageLength": 10,
                "ordering": false
            });
        });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>
