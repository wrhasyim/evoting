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
    $nama_periode = trim($_POST['nama_periode']);
    
    // Secara default, periode baru tidak langsung aktif (status_aktif = 0)
    $stmt = $pdo->prepare("INSERT INTO periode (nama_periode, status_aktif) VALUES (:nama, 0)");
    $stmt->execute(['nama' => $nama_periode]);
    $pesan_notifikasi = "<div class='alert alert-success'>Berhasil menambah periode <b>$nama_periode</b>. Silakan klik 'Aktifkan' untuk menggunakannya.</div>";
}

// 2. PROSES EDIT PERIODE
if (isset($_POST['edit_periode'])) {
    $id_periode = $_POST['id_periode'];
    $nama_periode = trim($_POST['nama_periode']);
    
    $stmt = $pdo->prepare("UPDATE periode SET nama_periode = :nama WHERE id_periode = :id");
    $stmt->execute(['nama' => $nama_periode, 'id' => $id_periode]);
    $pesan_notifikasi = "<div class='alert alert-info'>Nama periode berhasil diperbarui.</div>";
}

// 3. PROSES AKTIFKAN PERIODE (Logika Krusial)
if (isset($_POST['aktifkan_periode'])) {
    $id_periode = $_POST['id_periode'];
    
    // Langkah A: Matikan SEMUA periode terlebih dahulu
    $pdo->query("UPDATE periode SET status_aktif = 0");
    
    // Langkah B: Aktifkan HANYA periode yang dipilih
    $stmt = $pdo->prepare("UPDATE periode SET status_aktif = 1 WHERE id_periode = :id");
    $stmt->execute(['id' => $id_periode]);
    
    $pesan_notifikasi = "<div class='alert alert-success'>Periode berhasil diaktifkan! Sistem e-voting kini berjalan pada periode ini.</div>";
}

// 4. PROSES HAPUS PERIODE
if (isset($_POST['hapus_periode'])) {
    $id_periode = $_POST['id_periode'];
    
    // Cek apakah yang dihapus adalah periode aktif? Jika ya, tolak penghapusan untuk keamanan.
    $cek_aktif = $pdo->prepare("SELECT status_aktif FROM periode WHERE id_periode = ?");
    $cek_aktif->execute([$id_periode]);
    $status = $cek_aktif->fetchColumn();

    if ($status == 1) {
        $pesan_notifikasi = "<div class='alert alert-danger'>Gagal: Anda tidak boleh menghapus periode yang sedang AKTIF!</div>";
    } else {
        $stmt = $pdo->prepare("DELETE FROM periode WHERE id_periode = :id");
        $stmt->execute(['id' => $id_periode]);
        $pesan_notifikasi = "<div class='alert alert-warning'>Periode berhasil dihapus secara permanen.</div>";
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
    <title>Manajemen Angkatan - E-Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fa; overflow-x: hidden; }
        
        /* Desain Sidebar */
        .sidebar { height: 100vh; background: linear-gradient(180deg, #1a2980 0%, #26d0ce 100%); color: white; padding-top: 30px; position: fixed; width: 260px; box-shadow: 4px 0 15px rgba(0,0,0,0.1); z-index: 100; }
        .sidebar-brand { font-weight: 700; font-size: 1.3rem; text-align: center; margin-bottom: 30px; display: flex; flex-direction: column; align-items: center; }
        .sidebar-brand i { font-size: 2rem; margin-bottom: 10px; }
        .sidebar a { color: rgba(255,255,255,0.85); text-decoration: none; padding: 15px 25px; display: block; font-weight: 500; transition: all 0.3s ease; }
        .sidebar a i { margin-right: 12px; width: 20px; text-align: center; }
        .sidebar a:hover, .sidebar .active { background-color: rgba(255,255,255,0.15); color: white; border-left: 5px solid #fff; }
        
        .content { margin-left: 260px; padding: 40px; }
        .top-header { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .table-container { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); }
        
        .status-aktif { border: 2px solid #28a745; background-color: #eafbf0; }
    </style>
</head>
<body>

    <!-- MEMANGGIL SIDEBAR DARI FILE TERPISAH -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <h4 class="m-0 fw-bold" style="color: #2c3e50;">Manajemen Angkatan / Tahun Ajaran</h4>
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="fas fa-plus me-2"></i> Tambah Periode
            </button>
        </div>

        <?= $pesan_notifikasi; ?>

        <div class="table-container">
            <div class="alert alert-warning small mb-4">
                <strong><i class="fas fa-info-circle me-2"></i>Informasi:</strong> 
                Hanya boleh ada <b>1 (satu)</b> periode yang aktif. Semua data pemilih dan suara akan dihubungkan ke periode yang sedang aktif untuk menjaga riwayat data di tahun-tahun berikutnya.
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Nama Angkatan / Periode</th>
                            <th>Status Saat Ini</th>
                            <th class="text-center">Aksi / Kontrol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_periode) > 0): ?>
                            <?php $no = 1; foreach ($data_periode as $row): ?>
                                <!-- Beri efek highlight khusus jika periode ini aktif -->
                                <tr class="<?= $row['status_aktif'] == 1 ? 'status-aktif' : ''; ?>">
                                    <td class="fw-bold"><?= $no++; ?></td>
                                    <td class="fw-bold fs-5 text-primary"><?= htmlspecialchars($row['nama_periode']); ?></td>
                                    <td>
                                        <?php if ($row['status_aktif'] == 1): ?>
                                            <span class="badge bg-success px-3 py-2 fs-6"><i class="fas fa-check-circle me-1"></i> Sedang Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary px-3 py-2"><i class="fas fa-archive me-1"></i> Tidak Aktif (Arsip)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <!-- Tombol Aktifkan (Hanya muncul jika belum aktif) -->
                                        <?php if ($row['status_aktif'] == 0): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="id_periode" value="<?= $row['id_periode']; ?>">
                                                <button type="submit" name="aktifkan_periode" class="btn btn-sm btn-success me-1" title="Jadikan Periode Aktif">
                                                    <i class="fas fa-power-off"></i> Aktifkan
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Tombol Edit -->
                                        <button class="btn btn-sm btn-info text-white me-1" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_periode']; ?>" title="Edit Nama">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <!-- Tombol Hapus -->
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $row['id_periode']; ?>" title="Hapus Periode">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- MODAL EDIT PERIODE -->
                                <div class="modal fade" id="modalEdit<?= $row['id_periode']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Nama Periode</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="id_periode" value="<?= $row['id_periode']; ?>">
                                                    <div class="mb-3">
                                                        <label>Nama Periode (Cth: Tahun Ajaran 2026/2027)</label>
                                                        <input type="text" name="nama_periode" class="form-control" value="<?= htmlspecialchars($row['nama_periode']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_periode" class="btn btn-info text-white">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- MODAL HAPUS PERIODE -->
                                <div class="modal fade" id="modalHapus<?= $row['id_periode']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Konfirmasi Penghapusan</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Yakin ingin menghapus periode <b><?= htmlspecialchars($row['nama_periode']); ?></b>?</p>
                                                    <p class="text-danger small">Peringatan: Seluruh data suara yang terkait dengan periode ini mungkin akan terpengaruh jika tidak di-backup!</p>
                                                    <input type="hidden" name="id_periode" value="<?= $row['id_periode']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="hapus_periode" class="btn btn-danger">Ya, Hapus Permanen</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Belum ada periode yang dibuat. Silakan tambah periode baru.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH PERIODE -->
    <div class="modal fade" id="modalTambah" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Tambah Periode Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Angkatan / Tahun Ajaran</label>
                            <input type="text" name="nama_periode" class="form-control" placeholder="Contoh: Tahun Ajaran 2026/2027" required autocomplete="off">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>