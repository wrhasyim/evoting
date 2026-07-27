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
    $nama_eskul = trim($_POST['nama_eskul']);
    $aturan = $_POST['aturan_pemilih'];
    
    $stmt = $pdo->prepare("INSERT INTO eskul (nama_eskul, aturan_pemilih) VALUES (:nama, :aturan)");
    $stmt->execute(['nama' => $nama_eskul, 'aturan' => $aturan]);
    $pesan_notifikasi = "<div class='alert alert-success'>Berhasil menambahkan ekstrakurikuler baru.</div>";
}

// 2. PROSES EDIT ESKUL
if (isset($_POST['edit_eskul'])) {
    $id_eskul = $_POST['id_eskul'];
    $nama_eskul = trim($_POST['nama_eskul']);
    $aturan = $_POST['aturan_pemilih'];
    
    $stmt = $pdo->prepare("UPDATE eskul SET nama_eskul = :nama, aturan_pemilih = :aturan WHERE id_eskul = :id");
    $stmt->execute(['nama' => $nama_eskul, 'aturan' => $aturan, 'id' => $id_eskul]);
    $pesan_notifikasi = "<div class='alert alert-info'>Data ekstrakurikuler berhasil diperbarui.</div>";
}

// 3. PROSES HAPUS ESKUL (SOFT DELETE)
if (isset($_POST['hapus_eskul'])) {
    $id_eskul = $_POST['id_eskul'];
    
    $stmt = $pdo->prepare("UPDATE eskul SET status_aktif = 0 WHERE id_eskul = :id");
    $stmt->execute(['id' => $id_eskul]);
    $pesan_notifikasi = "<div class='alert alert-warning'>Ekstrakurikuler telah dipindahkan ke Tempat Sampah.</div>";
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
    <title>Manajemen Eskul - E-Voting</title>
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
        .table-container { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); }
    </style>
</head>
<body>

    <!-- SIDEBAR NAVIGASI -->
    <div class="sidebar">
        <div class="sidebar-brand"><i class="fas fa-vote-yea"></i> E-Voting SMK</div>
        <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="periode.php"><i class="fas fa-calendar-alt"></i> Tahun Ajaran</a>
        <a href="siswa.php"><i class="fas fa-users"></i> Manajemen Siswa</a>
        <a href="eskul.php" class="active"><i class="fas fa-school"></i> Manajemen Eskul</a>
        <a href="#"><i class="fas fa-user-tie"></i> Kandidat</a>
        <a href="#"><i class="fas fa-chart-pie"></i> Live Count</a>
        <a href="#"><i class="fas fa-cogs"></i> Pengaturan</a>
        <a href="../logout.php" class="text-warning mt-4"><i class="fas fa-sign-out-alt"></i> Keluar</a>
    </div>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <h4 class="m-0 fw-bold" style="color: #2c3e50;">Manajemen Ekstrakurikuler</h4>
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalTambah">
                <i class="fas fa-plus me-2"></i> Tambah Eskul
            </button>
        </div>

        <?= $pesan_notifikasi; ?>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Nama Ekstrakurikuler / Organisasi</th>
                            <th>Aturan Hak Pilih</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_eskul) > 0): ?>
                            <?php $no = 1; foreach ($data_eskul as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?= $no++; ?></td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($row['nama_eskul']); ?></td>
                                    <td>
                                        <?php if ($row['aturan_pemilih'] == 'semua_siswa'): ?>
                                            <span class="badge bg-success"><i class="fas fa-globe"></i> Terbuka (Semua Siswa)</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-lock"></i> Tertutup (Hanya Anggota)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <!-- Tombol Edit -->
                                        <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_eskul']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Tombol Hapus -->
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $row['id_eskul']; ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- MODAL EDIT ESKUL -->
                                <div class="modal fade" id="modalEdit<?= $row['id_eskul']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Data Ekstrakurikuler</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="id_eskul" value="<?= $row['id_eskul']; ?>">
                                                    <div class="mb-3">
                                                        <label>Nama Ekstrakurikuler</label>
                                                        <input type="text" name="nama_eskul" class="form-control" value="<?= htmlspecialchars($row['nama_eskul']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Aturan Hak Pilih</label>
                                                        <select name="aturan_pemilih" class="form-select" required>
                                                            <option value="semua_siswa" <?= $row['aturan_pemilih'] == 'semua_siswa' ? 'selected' : ''; ?>>Semua Siswa (Terbuka)</option>
                                                            <option value="hanya_anggota" <?= $row['aturan_pemilih'] == 'hanya_anggota' ? 'selected' : ''; ?>>Hanya Anggota (Tertutup)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_eskul" class="btn btn-info text-white">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- MODAL HAPUS ESKUL -->
                                <div class="modal fade" id="modalHapus<?= $row['id_eskul']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Konfirmasi Penghapusan</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Hapus ekstrakurikuler <b><?= htmlspecialchars($row['nama_eskul']); ?></b>?</p>
                                                    <input type="hidden" name="id_eskul" value="<?= $row['id_eskul']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="hapus_eskul" class="btn btn-danger">Ya, Hapus</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Belum ada data ekstrakurikuler.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH ESKUL -->
    <div class="modal fade" id="modalTambah" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Tambah Ekstrakurikuler</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Ekstrakurikuler / Organisasi</label>
                            <input type="text" name="nama_eskul" class="form-control" placeholder="Contoh: OSIS" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Aturan Hak Pilih</label>
                            <select name="aturan_pemilih" class="form-select" required>
                                <option value="semua_siswa">Semua Siswa (Terbuka)</option>
                                <option value="hanya_anggota">Hanya Anggota (Tertutup)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_eskul" class="btn btn-primary">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>