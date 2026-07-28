<?php
// admin/siswa.php

session_start();
require '../config/koneksi.php';

// PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$pesan_notifikasi = '';

// MENCARI PERIODE YANG SEDANG AKTIF
$stmt_periode = $pdo->query("SELECT id_periode, nama_periode FROM periode WHERE status_aktif = 1 LIMIT 1");
$periode_aktif = $stmt_periode->fetch();

if ($periode_aktif) {
    $id_periode_aktif = $periode_aktif['id_periode'];
    $nama_periode_aktif = $periode_aktif['nama_periode'];
} else {
    $id_periode_aktif = null;
    $nama_periode_aktif = "Belum Ada Periode Aktif";
}

// 1. PROSES TAMBAH SISWA MANUAL
if (isset($_POST['tambah_siswa']) && $id_periode_aktif) {
    $nis = trim($_POST['nis']);
    $nama = trim($_POST['nama_siswa']);
    $kelas = trim($_POST['kelas']);
    $pin = strtoupper(substr(md5(time() . $nis), 0, 5));

    $cek_nis = $pdo->prepare("SELECT id_siswa FROM siswa WHERE nis = :nis AND id_periode = :id_periode");
    $cek_nis->execute(['nis' => $nis, 'id_periode' => $id_periode_aktif]);
    
    if ($cek_nis->rowCount() > 0) {
        $pesan_notifikasi = "<div class='alert alert-danger'>Gagal: NIS <b>$nis</b> sudah terdaftar di periode ini!</div>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO siswa (id_periode, nis, nama_siswa, kelas, pin) VALUES (:id_periode, :nis, :nama, :kelas, :pin)");
        $stmt->execute(['id_periode' => $id_periode_aktif, 'nis' => $nis, 'nama' => $nama, 'kelas' => $kelas, 'pin' => $pin]);
        $pesan_notifikasi = "<div class='alert alert-success'>Berhasil menambah siswa. PIN untuk <b>$nama</b> adalah: <b>$pin</b></div>";
    }
}

// 2. PROSES IMPORT DATA MASSAL (CSV)
if (isset($_POST['import_csv']) && $id_periode_aktif) {
    $ekstensi_diizinkan = ['csv', 'txt'];
    $nama_file = $_FILES['file_csv']['name'];
    $pecah_nama = explode('.', $nama_file);
    $ekstensi_file = strtolower(end($pecah_nama));
    $file_tmp = $_FILES['file_csv']['tmp_name'];

    if (in_array($ekstensi_file, $ekstensi_diizinkan) === true) {
        $file_buka = fopen($file_tmp, "r");
        $sukses = 0; $gagal = 0; $baris = 0;

        while (($data = fgetcsv($file_buka, 1000, ",")) !== FALSE) {
            $baris++;
            if ($baris == 1 && (strtolower($data[0]) == 'nis' || strtolower($data[0]) == 'nomor induk')) continue;

            if (count($data) >= 3) {
                $nis = trim($data[0]);
                $nama = trim($data[1]);
                $kelas = trim($data[2]);
                if (empty($nis)) continue; 

                $pin = strtoupper(substr(md5(time() . $nis), 0, 5));

                $cek_nis = $pdo->prepare("SELECT id_siswa FROM siswa WHERE nis = :nis AND id_periode = :id_periode");
                $cek_nis->execute(['nis' => $nis, 'id_periode' => $id_periode_aktif]);
                
                if ($cek_nis->rowCount() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO siswa (id_periode, nis, nama_siswa, kelas, pin) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$id_periode_aktif, $nis, $nama, $kelas, $pin]);
                    $sukses++;
                } else {
                    $gagal++;
                }
            }
        }
        fclose($file_buka);
        $pesan_notifikasi = "<div class='alert alert-info'>Import Selesai. <b>$sukses</b> data ditambahkan. <b>$gagal</b> data dilewati (duplikat di periode ini).</div>";
    }
}

// 3. PROSES EDIT SISWA
if (isset($_POST['edit_siswa'])) {
    $id_siswa = $_POST['id_siswa']; 
    $nama = trim($_POST['nama_siswa']);
    $kelas = trim($_POST['kelas']);
    
    $stmt = $pdo->prepare("UPDATE siswa SET nama_siswa = :nama, kelas = :kelas WHERE id_siswa = :id_siswa");
    $stmt->execute(['nama' => $nama, 'kelas' => $kelas, 'id_siswa' => $id_siswa]);
    $pesan_notifikasi = "<div class='alert alert-success'>Data siswa berhasil diperbarui!</div>";
}

// 4. PROSES HAPUS SISWA (SOFT DELETE)
if (isset($_POST['hapus_siswa'])) {
    $id_siswa = $_POST['id_siswa_hapus'];
    $stmt = $pdo->prepare("UPDATE siswa SET status_aktif = 0 WHERE id_siswa = :id_siswa");
    $stmt->execute(['id_siswa' => $id_siswa]);
    $pesan_notifikasi = "<div class='alert alert-warning'>Data siswa telah dipindahkan ke Tempat Sampah.</div>";
}

// MENGAMBIL DATA SISWA UNTUK TABEL UTAMA
$data_siswa = [];
if ($id_periode_aktif) {
    $stmt_tampil = $pdo->prepare("SELECT * FROM siswa WHERE status_aktif = 1 AND id_periode = :id_periode ORDER BY kelas ASC, nama_siswa ASC");
    $stmt_tampil->execute(['id_periode' => $id_periode_aktif]);
    $data_siswa = $stmt_tampil->fetchAll();
}

// MENGAMBIL DATA ESKUL UNTUK FILTER DAFTAR HADIR
$stmt_eskul_filter = $pdo->query("SELECT id_eskul, nama_eskul FROM eskul WHERE status_aktif = 1 ORDER BY nama_eskul ASC");
$daftar_eskul_filter = $stmt_eskul_filter->fetchAll();

// MENGAMBIL DAFTAR KELAS YANG ADA DI PERIODE INI UNTUK FILTER
$daftar_kelas_filter = [];
if ($id_periode_aktif) {
    $stmt_kelas_filter = $pdo->prepare("SELECT DISTINCT kelas FROM siswa WHERE id_periode = :id_periode AND status_aktif = 1 ORDER BY kelas ASC");
    $stmt_kelas_filter->execute(['id_periode' => $id_periode_aktif]);
    $daftar_kelas_filter = $stmt_kelas_filter->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Siswa - E-Voting</title>
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
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Data Pemilih (Siswa)</h4>
                <small class="text-primary fw-bold"><i class="fas fa-tags me-1"></i> Periode Saat Ini: <?= htmlspecialchars($nama_periode_aktif); ?></small>
            </div>
            <div>
                <?php if ($id_periode_aktif): ?>
                    
                    <!-- PEMBARUAN: Tombol Daftar Hadir sekarang membuka Modal Filter -->
                    <button class="btn btn-outline-success rounded-pill px-3 me-2 fw-bold" data-bs-toggle="modal" data-bs-target="#modalDaftarHadir">
                        <i class="fas fa-file-pdf me-1"></i> Daftar Hadir
                    </button>
                    
                    <a href="cetak_pin.php" target="_blank" class="btn btn-secondary rounded-pill px-3 me-2">
                        <i class="fas fa-print"></i> Cetak PIN
                    </a>
                    <button class="btn btn-success rounded-pill px-4 me-2" data-bs-toggle="modal" data-bs-target="#modalImport">
                        <i class="fas fa-file-excel me-2"></i> Import CSV
                    </button>
                    <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalTambah">
                        <i class="fas fa-plus me-2"></i> Tambah Manual
                    </button>
                <?php else: ?>
                    <span class="badge bg-danger p-2"><i class="fas fa-lock me-1"></i> Aktifkan periode terlebih dahulu</span>
                <?php endif; ?>
            </div>
        </div>

        <?= $pesan_notifikasi; ?>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>NIS</th>
                            <th>Nama Lengkap</th>
                            <th>Kelas</th>
                            <th>PIN Akses</th>
                            <th>Status Memilih</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_siswa) > 0): ?>
                            <?php foreach ($data_siswa as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($row['nis']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_siswa']); ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($row['kelas']); ?></span></td>
                                    <td><code class="fs-6 text-primary"><?= htmlspecialchars($row['pin']); ?></code></td>
                                    <td>
                                        <?php if ($row['status_pilih'] == 1): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i> Sudah</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-times"></i> Belum</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <!-- Tombol Edit -->
                                        <button class="btn btn-sm btn-info text-white" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_siswa']; ?>" title="Edit Data">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <!-- Tombol Hapus -->
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $row['id_siswa']; ?>" title="Hapus Data">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- MODAL EDIT SISWA -->
                                <div class="modal fade" id="modalEdit<?= $row['id_siswa']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Data Siswa</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="id_siswa" value="<?= $row['id_siswa']; ?>">
                                                    <div class="mb-3">
                                                        <label>NIS (Tidak bisa diubah)</label>
                                                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['nis']); ?>" readonly>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Nama Lengkap</label>
                                                        <input type="text" name="nama_siswa" class="form-control" value="<?= htmlspecialchars($row['nama_siswa']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Kelas</label>
                                                        <input type="text" name="kelas" class="form-control" value="<?= htmlspecialchars($row['kelas']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_siswa" class="btn btn-info text-white">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- MODAL HAPUS SISWA -->
                                <div class="modal fade" id="modalHapus<?= $row['id_siswa']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Konfirmasi Penghapusan</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Hapus data siswa <b><?= htmlspecialchars($row['nama_siswa']); ?></b> dari periode ini?</p>
                                                    <input type="hidden" name="id_siswa_hapus" value="<?= $row['id_siswa']; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="hapus_siswa" class="btn btn-danger">Ya, Hapus Data</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada data siswa untuk periode ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH SISWA -->
    <div class="modal fade" id="modalTambah" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Tambah Siswa Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nomor Induk Siswa (NIS)</label>
                            <input type="text" name="nis" class="form-control" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_siswa" class="form-control" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kelas</label>
                            <input type="text" name="kelas" class="form-control" required autocomplete="off">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_siswa" class="btn btn-primary">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL IMPORT CSV -->
    <div class="modal fade" id="modalImport" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-file-upload me-2"></i> Import Data Siswa</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning small">
                            Pastikan file Anda berformat <b>.csv</b>. Kolom berurutan: NIS, Nama Lengkap, Kelas.<br>
                            <a href="data:text/csv;charset=utf-8,NIS,Nama Lengkap,Kelas%0A1001,Budi Santoso,XI RPL 1%0A1002,Siti Aminah,XI TKJ 2" download="format_import_siswa.csv" class="btn btn-sm btn-outline-success mt-3 fw-bold">
                                <i class="fas fa-download me-1"></i> Download Format Sample
                            </a>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pilih File CSV</label>
                            <input type="file" name="file_csv" class="form-control" accept=".csv" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="import_csv" class="btn btn-success">Mulai Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- PEMBARUAN: MODAL CETAK DAFTAR HADIR -->
    <div class="modal fade" id="modalDaftarHadir" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="GET" action="cetak_daftar_hadir.php" target="_blank">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-print me-2"></i> Cetak Daftar Hadir</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Kategori Cetak</label>
                            <select name="filter" class="form-select border-success">
                                <option value="all">Cetak Semua Siswa (Dipisah per Kelas)</option>
                                
                                <optgroup label="Cetak Spesifik per Kelas">
                                    <?php foreach ($daftar_kelas_filter as $kls): ?>
                                        <option value="kelas_<?= htmlspecialchars($kls['kelas']); ?>">Hanya Kelas <?= htmlspecialchars($kls['kelas']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <optgroup label="Cetak Khusus Anggota Ekstrakurikuler">
                                    <?php foreach ($daftar_eskul_filter as $eskul): ?>
                                        <option value="eskul_<?= $eskul['id_eskul']; ?>">Hanya Anggota <?= htmlspecialchars($eskul['nama_eskul']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success fw-bold"><i class="fas fa-file-pdf me-2"></i> Buka Halaman Cetak</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>