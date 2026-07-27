<?php
// admin/kandidat.php

session_start();
require '../config/koneksi.php';

// 1. PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$pesan_notifikasi = '';

// 2. CEK PERIODE AKTIF
$stmt_periode = $pdo->query("SELECT id_periode, nama_periode FROM periode WHERE status_aktif = 1 LIMIT 1");
$periode_aktif = $stmt_periode->fetch();
$id_periode_aktif = $periode_aktif ? $periode_aktif['id_periode'] : null;

// 3. AMBIL DAFTAR ESKUL UNTUK FILTER
$stmt_eskul = $pdo->query("SELECT id_eskul, nama_eskul FROM eskul WHERE status_aktif = 1 ORDER BY nama_eskul ASC");
$daftar_eskul = $stmt_eskul->fetchAll();
$id_eskul_pilih = isset($_GET['id_eskul']) ? $_GET['id_eskul'] : (count($daftar_eskul) > 0 ? $daftar_eskul[0]['id_eskul'] : null);

// 4. PROSES TAMBAH KANDIDAT
if (isset($_POST['tambah_kandidat']) && $id_eskul_pilih) {
    $no_urut = $_POST['no_urut'];
    $nama_paslon = trim($_POST['nama_paslon']);
    $kelas_paslon = trim($_POST['kelas_paslon']);
    $visi_misi = trim($_POST['visi_misi']);
    
    $nama_file = $_FILES['foto']['name'];
    $ukuran_file = $_FILES['foto']['size'];
    $tmp_file = $_FILES['foto']['tmp_name'];
    
    $ekstensi_valid = ['jpg', 'jpeg', 'png'];
    $ekstensi_gambar = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
    $nama_file_baru = uniqid() . '.' . $ekstensi_gambar;

    if (in_array($ekstensi_gambar, $ekstensi_valid) && $ukuran_file <= 2097152) {
        if (move_uploaded_file($tmp_file, '../uploads/' . $nama_file_baru)) {
            $stmt = $pdo->prepare("INSERT INTO kandidat (id_eskul, no_urut, nama_paslon, kelas_paslon, visi_misi, foto) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_eskul_pilih, $no_urut, $nama_paslon, $kelas_paslon, $visi_misi, $nama_file_baru]);
            $pesan_notifikasi = "<div class='alert alert-success'>Kandidat berhasil ditambahkan!</div>";
        } else {
            $pesan_notifikasi = "<div class='alert alert-danger'>Gagal mengunggah foto.</div>";
        }
    } else {
        $pesan_notifikasi = "<div class='alert alert-danger'>Format foto tidak valid atau ukuran lebih dari 2MB.</div>";
    }
}

// 5. PROSES EDIT KANDIDAT (FITUR BARU)
if (isset($_POST['edit_kandidat'])) {
    $id_kandidat = $_POST['id_kandidat'];
    $no_urut = $_POST['no_urut'];
    $nama_paslon = trim($_POST['nama_paslon']);
    $kelas_paslon = trim($_POST['kelas_paslon']);
    $visi_misi = trim($_POST['visi_misi']);
    $foto_lama = $_POST['foto_lama'];
    
    $nama_file_baru = $foto_lama; // Secara default, gunakan foto yang sudah ada

    // Jika admin mengunggah foto baru
    if (isset($_FILES['foto']) && $_FILES['foto']['name'] != '') {
        $nama_file = $_FILES['foto']['name'];
        $ukuran_file = $_FILES['foto']['size'];
        $tmp_file = $_FILES['foto']['tmp_name'];
        $ekstensi_gambar = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
        $ekstensi_valid = ['jpg', 'jpeg', 'png'];

        if (in_array($ekstensi_gambar, $ekstensi_valid) && $ukuran_file <= 2097152) {
            $nama_file_baru = uniqid() . '.' . $ekstensi_gambar;
            move_uploaded_file($tmp_file, '../uploads/' . $nama_file_baru);
            
            // Hapus file foto lama di server agar tidak menumpuk
            if (file_exists('../uploads/' . $foto_lama) && $foto_lama != '') {
                unlink('../uploads/' . $foto_lama);
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE kandidat SET no_urut=?, nama_paslon=?, kelas_paslon=?, visi_misi=?, foto=? WHERE id_kandidat=?");
    $stmt->execute([$no_urut, $nama_paslon, $kelas_paslon, $visi_misi, $nama_file_baru, $id_kandidat]);
    $pesan_notifikasi = "<div class='alert alert-success'>Data kandidat berhasil diperbarui!</div>";
}

// 6. PROSES HAPUS KANDIDAT
if (isset($_POST['hapus_kandidat'])) {
    $id_kandidat = $_POST['id_kandidat'];
    $stmt = $pdo->prepare("UPDATE kandidat SET status_aktif = 0 WHERE id_kandidat = ?");
    $stmt->execute([$id_kandidat]);
    $pesan_notifikasi = "<div class='alert alert-warning'>Kandidat telah dihapus.</div>";
}

// AMBIL DATA KANDIDAT
$data_kandidat = [];
if ($id_eskul_pilih) {
    $stmt_kandidat = $pdo->prepare("SELECT * FROM kandidat WHERE id_eskul = ? AND status_aktif = 1 ORDER BY no_urut ASC");
    $stmt_kandidat->execute([$id_eskul_pilih]);
    $data_kandidat = $stmt_kandidat->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kandidat - E-Voting</title>
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
        .foto-kandidat { width: 60px; height: 60px; object-fit: cover; border-radius: 50%; border: 2px solid #dee2e6; }
    </style>
</head>
<body>

    <!-- MEMANGGIL SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Manajemen Kandidat</h4>
                <small class="text-muted">Kelola data pasangan calon dan visi-misinya.</small>
            </div>
            <?php if ($id_eskul_pilih): ?>
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus me-2"></i> Tambah Kandidat
                </button>
            <?php endif; ?>
        </div>

        <?= $pesan_notifikasi; ?>

        <?php if (count($daftar_eskul) == 0): ?>
            <div class="alert alert-warning">Belum ada ekstrakurikuler yang terdaftar. Buat ekstrakurikuler terlebih dahulu.</div>
        <?php else: ?>
            <div class="table-container">
                <!-- FORM FILTER ESKUL -->
                <form method="GET" action="" class="mb-4 bg-light p-3 rounded border">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <label class="fw-bold mb-0">Lihat Kandidat Untuk:</label>
                        </div>
                        <div class="col-md-9">
                            <select name="id_eskul" class="form-select border-primary" onchange="this.form.submit()">
                                <?php foreach ($daftar_eskul as $e): ?>
                                    <option value="<?= $e['id_eskul']; ?>" <?= ($e['id_eskul'] == $id_eskul_pilih) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($e['nama_eskul']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>

                <!-- TABEL DATA KANDIDAT -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center" width="5%">No. Urut</th>
                                <th class="text-center" width="10%">Foto</th>
                                <th width="30%">Nama Pasangan Calon</th>
                                <th width="15%">Kelas</th>
                                <th width="30%">Visi & Misi Singkat</th>
                                <th class="text-center" width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($data_kandidat) > 0): ?>
                                <?php foreach ($data_kandidat as $row): ?>
                                    <tr>
                                        <td class="text-center fw-bold fs-5 text-primary"><?= $row['no_urut']; ?></td>
                                        <td class="text-center">
                                            <img src="../uploads/<?= htmlspecialchars($row['foto']); ?>" alt="Foto" class="foto-kandidat">
                                        </td>
                                        <td class="fw-bold"><?= htmlspecialchars($row['nama_paslon']); ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($row['kelas_paslon']); ?></span></td>
                                        <td>
                                            <span class="d-inline-block text-truncate" style="max-width: 250px;">
                                                <?= htmlspecialchars($row['visi_misi']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <!-- Tombol Edit -->
                                            <button class="btn btn-sm btn-info text-white me-1" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_kandidat']; ?>" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <!-- Tombol Hapus -->
                                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $row['id_kandidat']; ?>" title="Hapus">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- MODAL EDIT KANDIDAT -->
                                    <div class="modal fade" id="modalEdit<?= $row['id_kandidat']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="" enctype="multipart/form-data">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Data Kandidat</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <input type="hidden" name="id_kandidat" value="<?= $row['id_kandidat']; ?>">
                                                        <input type="hidden" name="foto_lama" value="<?= $row['foto']; ?>">
                                                        
                                                        <div class="row">
                                                            <div class="col-md-3 mb-3">
                                                                <label class="form-label">Nomor Urut</label>
                                                                <input type="number" name="no_urut" class="form-control" min="1" value="<?= htmlspecialchars($row['no_urut']); ?>" required>
                                                            </div>
                                                            <div class="col-md-9 mb-3">
                                                                <label class="form-label">Nama Pasangan Calon</label>
                                                                <input type="text" name="nama_paslon" class="form-control" value="<?= htmlspecialchars($row['nama_paslon']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Kelas Asal</label>
                                                            <input type="text" name="kelas_paslon" class="form-control" value="<?= htmlspecialchars($row['kelas_paslon']); ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Visi dan Misi</label>
                                                            <textarea name="visi_misi" class="form-control" rows="5" required><?= htmlspecialchars($row['visi_misi']); ?></textarea>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Ganti Foto (Opsional)</label>
                                                            <input type="file" name="foto" class="form-control" accept=".jpg, .jpeg, .png">
                                                            <small class="text-muted">Biarkan kosong jika tidak ingin mengubah foto saat ini.</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="edit_kandidat" class="btn btn-info text-white">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- MODAL HAPUS KANDIDAT -->
                                    <div class="modal fade" id="modalHapus<?= $row['id_kandidat']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">Konfirmasi Penghapusan</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-start">
                                                        <p>Hapus kandidat <b><?= htmlspecialchars($row['nama_paslon']); ?></b>?</p>
                                                        <input type="hidden" name="id_kandidat" value="<?= $row['id_kandidat']; ?>">
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="hapus_kandidat" class="btn btn-danger">Ya, Hapus</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Belum ada kandidat untuk ekstrakurikuler ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MODAL TAMBAH KANDIDAT -->
            <div class="modal fade" id="modalTambah" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold">Tambah Kandidat Baru</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Nomor Urut</label>
                                        <input type="number" name="no_urut" class="form-control" min="1" required>
                                    </div>
                                    <div class="col-md-9 mb-3">
                                        <label class="form-label">Nama Pasangan Calon</label>
                                        <input type="text" name="nama_paslon" class="form-control" placeholder="Contoh: Budi & Siti" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Kelas Asal</label>
                                    <input type="text" name="kelas_paslon" class="form-control" placeholder="Contoh: XI AKL 1 & XI BDP 2" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Visi dan Misi</label>
                                    <textarea name="visi_misi" class="form-control" rows="5" placeholder="Tuliskan visi dan misi kandidat di sini..." required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Upload Foto Kandidat</label>
                                    <input type="file" name="foto" class="form-control" accept=".jpg, .jpeg, .png" required>
                                    <small class="text-muted">Format: JPG, JPEG, PNG. Ukuran maksimal: 2MB. Sebaiknya rasio 1:1 (Kotak).</small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="tambah_kandidat" class="btn btn-primary">Simpan Kandidat</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>