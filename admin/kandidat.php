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
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $no_urut = (int) $_POST['no_urut'];
    $nama_paslon = trim($_POST['nama_paslon']);
    $kelas_paslon = trim($_POST['kelas_paslon']);
    $visi_misi = trim($_POST['visi_misi']);
    
    $nama_file = $_FILES['foto']['name'];
    $ukuran_file = $_FILES['foto']['size'];
    $tmp_file = $_FILES['foto']['tmp_name'];
    
    $ekstensi_valid = ['jpg', 'jpeg', 'png', 'webp'];
    $ekstensi_gambar = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
    $nama_file_baru = uniqid() . '.' . $ekstensi_gambar;

    if (in_array($ekstensi_gambar, $ekstensi_valid) && $ukuran_file <= 2097152) {
        if (!is_dir('../uploads')) {
            mkdir('../uploads', 0777, true);
        }
        if (move_uploaded_file($tmp_file, '../uploads/' . $nama_file_baru)) {
            $stmt = $pdo->prepare("INSERT INTO kandidat (id_eskul, no_urut, nama_paslon, kelas_paslon, visi_misi, foto) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_eskul_pilih, $no_urut, $nama_paslon, $kelas_paslon, $visi_misi, $nama_file_baru]);
            $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Kandidat Paslon no $no_urut berhasil ditambahkan!';</script>";
        } else {
            $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Gagal mengunggah file foto kandidat.';</script>";
        }
    } else {
        $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Format foto harus JPG, JPEG, PNG, atau WEBP dan ukuran maksimal 2MB.';</script>";
    }
}

// 5. PROSES EDIT KANDIDAT
if (isset($_POST['edit_kandidat'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_kandidat = $_POST['id_kandidat'];
    $no_urut = (int) $_POST['no_urut'];
    $nama_paslon = trim($_POST['nama_paslon']);
    $kelas_paslon = trim($_POST['kelas_paslon']);
    $visi_misi = trim($_POST['visi_misi']);
    $foto_lama = $_POST['foto_lama'];
    
    $nama_file_baru = $foto_lama;

    if (isset($_FILES['foto']) && $_FILES['foto']['name'] != '') {
        $nama_file = $_FILES['foto']['name'];
        $ukuran_file = $_FILES['foto']['size'];
        $tmp_file = $_FILES['foto']['tmp_name'];
        $ekstensi_gambar = strtolower(pathinfo($nama_file, PATHINFO_EXTENSION));
        $ekstensi_valid = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ekstensi_gambar, $ekstensi_valid) && $ukuran_file <= 2097152) {
            $nama_file_baru = uniqid() . '.' . $ekstensi_gambar;
            move_uploaded_file($tmp_file, '../uploads/' . $nama_file_baru);
            
            if (file_exists('../uploads/' . $foto_lama) && $foto_lama != '') {
                unlink('../uploads/' . $foto_lama);
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE kandidat SET no_urut=?, nama_paslon=?, kelas_paslon=?, visi_misi=?, foto=? WHERE id_kandidat=?");
    $stmt->execute([$no_urut, $nama_paslon, $kelas_paslon, $visi_misi, $nama_file_baru, $id_kandidat]);
    $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Data kandidat berhasil diperbarui!';</script>";
}

// 6. PROSES HAPUS KANDIDAT
if (isset($_POST['hapus_kandidat'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_kandidat = $_POST['id_kandidat'];
    $stmt = $pdo->prepare("UPDATE kandidat SET status_aktif = 0 WHERE id_kandidat = ?");
    $stmt->execute([$id_kandidat]);
    $pesan_notifikasi = "<script>var notification_type='warning'; var notification_title='Informasi'; var notification_message='Kandidat telah dinonaktifkan.';</script>";
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
    <title>Manajemen Pasangan Calon (Kandidat) - E-Voting</title>
    <!-- Bootstrap 5 CSS & DataTables -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .foto-kandidat-thumb {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .badge-no-urut {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            color: #ffffff;
            font-weight: 800;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
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
                <h4>Manajemen Pasangan Calon (Kandidat)</h4>
                <small class="text-muted">Kelola data pasangan calon, nomor urut, foto resmi, dan visi-misi pemilihan</small>
            </div>
            <?php if ($id_eskul_pilih): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus"></i> Tambah Kandidat
                </button>
            <?php endif; ?>
        </div>

        <?= $pesan_notifikasi; ?>

        <?php if (count($daftar_eskul) == 0): ?>
            <div class="alert alert-warning py-3 rounded-3 shadow-sm">
                <i class="fas fa-exclamation-triangle me-2"></i> Belum ada data ekstrakurikuler yang terdaftar. Buat ekstrakurikuler terlebih dahulu di menu <b>Eskul</b>.
            </div>
        <?php else: ?>
            <div class="card-modern">
                <!-- FORM FILTER ESKUL -->
                <form method="GET" action="" class="mb-4 p-3 bg-light rounded-3 border">
                    <div class="row align-items-center g-2">
                        <div class="col-md-3">
                            <label class="fw-bold text-dark small m-0"><i class="fas fa-filter text-primary me-1"></i> Pilih Kategori Pemilihan:</label>
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
                    <table class="table table-hover align-middle w-100" id="tabelKandidat">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 70px;">No. Urut</th>
                                <th class="text-center" style="width: 90px;">Foto</th>
                                <th>Nama Pasangan Calon</th>
                                <th style="width: 160px;">Kelas Asal</th>
                                <th>Visi & Misi</th>
                                <th class="text-center" style="width: 120px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($data_kandidat) > 0): ?>
                                <?php foreach ($data_kandidat as $row): ?>
                                    <tr>
                                        <td class="text-center">
                                            <span class="badge-no-urut"><?= $row['no_urut']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($row['foto']) && file_exists('../uploads/' . $row['foto'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($row['foto']); ?>" alt="Foto" class="foto-kandidat-thumb">
                                            <?php else: ?>
                                                <div class="foto-kandidat-thumb d-inline-flex align-items-center justify-content-center bg-light text-muted">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($row['nama_paslon']); ?></div>
                                            <small class="text-muted">Kandidat Paslon No. <?= $row['no_urut']; ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-soft-secondary font-monospace"><?= htmlspecialchars($row['kelas_paslon']); ?></span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalLihatVisi<?= $row['id_kandidat']; ?>">
                                                <i class="fas fa-eye me-1"></i> Lihat Visi & Misi
                                            </button>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_kandidat']; ?>" title="Edit">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $row['id_kandidat']; ?>" title="Hapus">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- MODAL LIHAT VISI MISI -->
                                    <div class="modal fade" id="modalLihatVisi<?= $row['id_kandidat']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title"><i class="fas fa-bullhorn me-2"></i> Visi & Misi Paslon No. <?= $row['no_urut']; ?></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['nama_paslon']); ?></h6>
                                                    <small class="text-muted d-block mb-3">Kelas: <?= htmlspecialchars($row['kelas_paslon']); ?></small>
                                                    <div class="p-3 bg-light rounded-3 border" style="white-space: pre-wrap; line-height: 1.6; font-size: 0.92rem;">
<?= htmlspecialchars($row['visi_misi']); ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- MODAL EDIT KANDIDAT -->
                                    <div class="modal fade" id="modalEdit<?= $row['id_kandidat']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="POST" action="" enctype="multipart/form-data">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                                    <input type="hidden" name="id_kandidat" value="<?= $row['id_kandidat']; ?>">
                                                    <input type="hidden" name="foto_lama" value="<?= $row['foto']; ?>">
                                                    
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold"><i class="fas fa-user-edit text-primary me-2"></i> Edit Data Kandidat</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row g-3 mb-3">
                                                            <div class="col-md-3">
                                                                <label class="form-label">Nomor Urut</label>
                                                                <input type="number" name="no_urut" class="form-control" min="1" value="<?= htmlspecialchars($row['no_urut']); ?>" required>
                                                            </div>
                                                            <div class="col-md-9">
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
                                                            <label class="form-label">Ganti Foto Kandidat (Opsional)</label>
                                                            <input type="file" name="foto" class="form-control" accept=".jpg, .jpeg, .png, .webp">
                                                            <small class="text-muted">Biarkan kosong jika tetap menggunakan foto saat ini.</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="edit_kandidat" class="btn btn-primary">Simpan Perubahan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- MODAL HAPUS KANDIDAT -->
                                    <div class="modal fade" id="modalHapus<?= $row['id_kandidat']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                                    <input type="hidden" name="id_kandidat" value="<?= $row['id_kandidat']; ?>">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i> Konfirmasi Penghapusan</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Nonaktifkan pasangan calon <b>No. <?= $row['no_urut']; ?>: <?= htmlspecialchars($row['nama_paslon']); ?></b>?</p>
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
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title fw-bold"><i class="fas fa-plus-circle me-2"></i> Tambah Kandidat Paslon Baru</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Nomor Urut</label>
                                        <input type="number" name="no_urut" class="form-control" min="1" placeholder="1" required>
                                    </div>
                                    <div class="col-md-9">
                                        <label class="form-label">Nama Pasangan Calon</label>
                                        <input type="text" name="nama_paslon" class="form-control" placeholder="Contoh: Budi Santoso & Siti Aminah" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Kelas Asal</label>
                                    <input type="text" name="kelas_paslon" class="form-control" placeholder="Contoh: XI RPL 1 & XI TKJ 2" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Visi dan Misi</label>
                                    <textarea name="visi_misi" class="form-control" rows="5" placeholder="Tuliskan visi dan misi kandidat di sini..." required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Upload Foto Kandidat Resmi</label>
                                    <input type="file" name="foto" class="form-control" accept=".jpg, .jpeg, .png, .webp" required>
                                    <small class="text-muted">Format: JPG, JPEG, PNG, WEBP. Maksimal 2MB. Disarankan foto berasio 1:1.</small>
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

    <!-- SCRIPT WAJIB DATATABLES & BOOTSTRAP -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/scripts.js"></script>
    <script>
        $(document).ready(function() {
            $('#tabelKandidat').DataTable({
                "language": {
                    "search": "Cari Kandidat:",
                    "lengthMenu": "Tampilkan _MENU_",
                    "info": "Total _TOTAL_ kandidat",
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
