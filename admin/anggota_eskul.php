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

// 2. CEK PERIODE AKTIF
$stmt_periode = $pdo->query("SELECT id_periode, nama_periode FROM periode WHERE status_aktif = 1 LIMIT 1");
$periode_aktif = $stmt_periode->fetch();
$id_periode_aktif = $periode_aktif ? $periode_aktif['id_periode'] : null;

// 3. AMBIL DAFTAR ESKUL UNTUK DROPDOWN FILTER
$stmt_eskul = $pdo->query("SELECT id_eskul, nama_eskul FROM eskul WHERE status_aktif = 1 ORDER BY nama_eskul ASC");
$daftar_eskul = $stmt_eskul->fetchAll();

// Menentukan eskul mana yang sedang dilihat datanya
$id_eskul_pilih = isset($_GET['id_eskul']) ? $_GET['id_eskul'] : (count($daftar_eskul) > 0 ? $daftar_eskul[0]['id_eskul'] : null);

// 4. PROSES TAMBAH ANGGOTA
if (isset($_POST['tambah_anggota']) && $id_periode_aktif && $id_eskul_pilih) {
    $id_siswa = $_POST['id_siswa'];

    // Cek keamanan ganda: Pastikan belum jadi anggota
    $cek = $pdo->prepare("SELECT id_anggota FROM anggota_eskul WHERE id_siswa = ? AND id_eskul = ?");
    $cek->execute([$id_siswa, $id_eskul_pilih]);
    
    if ($cek->rowCount() > 0) {
        $pesan_notifikasi = "<div class='alert alert-danger'>Gagal: Siswa tersebut sudah terdaftar di eskul ini.</div>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO anggota_eskul (id_siswa, id_eskul) VALUES (?, ?)");
        $stmt->execute([$id_siswa, $id_eskul_pilih]);
        $pesan_notifikasi = "<div class='alert alert-success'>Siswa berhasil ditambahkan ke dalam ekstrakurikuler!</div>";
    }
}

// 5. PROSES HAPUS ANGGOTA (Mencabut hak pilih dari eskul ini)
if (isset($_POST['hapus_anggota'])) {
    $id_anggota = $_POST['id_anggota'];
    $stmt = $pdo->prepare("DELETE FROM anggota_eskul WHERE id_anggota = ?");
    $stmt->execute([$id_anggota]);
    $pesan_notifikasi = "<div class='alert alert-warning'>Siswa telah dikeluarkan dari daftar pemilih ekstrakurikuler ini.</div>";
}

// 6. AMBIL DATA ANGGOTA UNTUK TABEL
$data_anggota = [];
if ($id_eskul_pilih && $id_periode_aktif) {
    $stmt_anggota = $pdo->prepare("
        SELECT a.id_anggota, s.nis, s.nama_siswa, s.kelas 
        FROM anggota_eskul a
        JOIN siswa s ON a.id_siswa = s.id_siswa
        WHERE a.id_eskul = ? AND s.id_periode = ? AND s.status_aktif = 1
        ORDER BY s.kelas ASC, s.nama_siswa ASC
    ");
    $stmt_anggota->execute([$id_eskul_pilih, $id_periode_aktif]);
    $data_anggota = $stmt_anggota->fetchAll();
}

// 7. AMBIL DATA SISWA YANG BUKAN ANGGOTA (Untuk Dropdown Tambah Manual)
$siswa_tersedia = [];
if ($id_eskul_pilih && $id_periode_aktif) {
    $stmt_tersedia = $pdo->prepare("
        SELECT id_siswa, nis, nama_siswa, kelas 
        FROM siswa 
        WHERE id_periode = ? AND status_aktif = 1 
        AND id_siswa NOT IN (SELECT id_siswa FROM anggota_eskul WHERE id_eskul = ?)
        ORDER BY kelas ASC, nama_siswa ASC
    ");
    $stmt_tersedia->execute([$id_periode_aktif, $id_eskul_pilih]);
    $siswa_tersedia = $stmt_tersedia->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anggota Eskul - E-Voting</title>
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

    <!-- MEMANGGIL SIDEBAR DARI FILE TERPISAH -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Pemilih Ekstrakurikuler</h4>
                <small class="text-muted">Kelola siapa saja yang berhak memilih di setiap organisasi.</small>
            </div>
            <?php if ($id_periode_aktif && $id_eskul_pilih): ?>
                <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus me-2"></i> Tambah Anggota
                </button>
            <?php endif; ?>
        </div>

        <?= $pesan_notifikasi; ?>

        <?php if (!$id_periode_aktif): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> Sistem dikunci: Silakan aktifkan Tahun Ajaran terlebih dahulu.</div>
        <?php elseif (count($daftar_eskul) == 0): ?>
            <div class="alert alert-warning"><i class="fas fa-info-circle me-2"></i> Belum ada ekstrakurikuler yang terdaftar.</div>
        <?php else: ?>
            <div class="table-container">
                <!-- FORM FILTER ESKUL -->
                <form method="GET" action="" class="mb-4 bg-light p-3 rounded border">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <label class="fw-bold mb-0">Pilih Ekstrakurikuler:</label>
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

                <!-- TABEL DATA ANGGOTA -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th width="5%">No</th>
                                <th width="15%">NIS</th>
                                <th width="45%">Nama Lengkap</th>
                                <th width="20%">Kelas</th>
                                <th width="15%" class="text-center">Cabut Hak Pilih</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($data_anggota) > 0): ?>
                                <?php $no = 1; foreach ($data_anggota as $row): ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($row['nis']); ?></td>
                                        <td><?= htmlspecialchars($row['nama_siswa']); ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($row['kelas']); ?></span></td>
                                        <td class="text-center">
                                            <form method="POST" action="" onsubmit="return confirm('Keluarkan siswa ini dari eskul?');">
                                                <input type="hidden" name="id_anggota" value="<?= $row['id_anggota']; ?>">
                                                <button type="submit" name="hapus_anggota" class="btn btn-sm btn-outline-danger" title="Hapus dari eskul">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada siswa yang dimasukkan ke ekstrakurikuler ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MODAL TAMBAH ANGGOTA -->
            <div class="modal fade" id="modalTambah" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="">
                            <div class="modal-header">
                                <h5 class="modal-title fw-bold">Tambah Anggota Ekstrakurikuler</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info small">
                                    Hanya menampilkan siswa yang <b>belum</b> bergabung di ekstrakurikuler ini.
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Pilih Siswa</label>
                                    <select name="id_siswa" class="form-select" required>
                                        <option value="" disabled selected>-- Cari dan Pilih Siswa --</option>
                                        <?php foreach ($siswa_tersedia as $s): ?>
                                            <option value="<?= $s['id_siswa']; ?>">
                                                <?= htmlspecialchars($s['nis'] . ' - ' . $s['nama_siswa'] . ' (' . $s['kelas'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="tambah_anggota" class="btn btn-primary">Simpan Anggota</button>
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