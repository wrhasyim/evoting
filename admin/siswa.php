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

// ==========================================
// FUNGSI: PEMBUAT PIN ACAK YANG UNIK
// ==========================================
function buatPinAcak($panjang = 6) {
    $karakter = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $pin = '';
    $max = strlen($karakter) - 1;
    for ($i = 0; $i < $panjang; $i++) {
        $pin .= $karakter[random_int(0, $max)];
    }
    return $pin;
}

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
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $nis = trim($_POST['nis']);
    $nama = trim($_POST['nama_siswa']);
    $kelas = trim($_POST['kelas']);
    
    $pin = buatPinAcak(6);

    $cek_nis = $pdo->prepare("SELECT id_siswa FROM siswa WHERE nis = :nis AND id_periode = :id_periode");
    $cek_nis->execute(['nis' => $nis, 'id_periode' => $id_periode_aktif]);
    
    if ($cek_nis->rowCount() > 0) {
        $pesan_notifikasi = "<script>var notification_type='error'; var notification_title='Informasi'; var notification_message='Gagal: NIS <b>$nis</b> sudah terdaftar di periode ini!';</script>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO siswa (id_periode, nis, nama_siswa, kelas, pin) VALUES (:id_periode, :nis, :nama, :kelas, :pin)");
        $stmt->execute(['id_periode' => $id_periode_aktif, 'nis' => $nis, 'nama' => $nama, 'kelas' => $kelas, 'pin' => $pin]);
        $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Berhasil menambah siswa. PIN untuk <b>$nama</b> adalah: <b>$pin</b>';</script>";
    }
}

// 2. PROSES IMPORT DATA MASSAL (CSV)
if (isset($_POST['import_csv']) && $id_periode_aktif) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
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

                $pin = buatPinAcak(6);

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
        $pesan_notifikasi = "<script>var notification_type='info'; var notification_title='Informasi'; var notification_message='Import Selesai. <b>$sukses</b> data ditambahkan. <b>$gagal</b> data dilewati (duplikat di periode ini).';</script>";
    }
}

// 3. PROSES EDIT SISWA
if (isset($_POST['edit_siswa'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_siswa = $_POST['id_siswa']; 
    $nama = trim($_POST['nama_siswa']);
    $kelas = trim($_POST['kelas']);
    
    $stmt = $pdo->prepare("UPDATE siswa SET nama_siswa = :nama, kelas = :kelas WHERE id_siswa = :id_siswa");
    $stmt->execute(['nama' => $nama, 'kelas' => $kelas, 'id_siswa' => $id_siswa]);
    $pesan_notifikasi = "<script>var notification_type='success'; var notification_title='Informasi'; var notification_message='Data siswa berhasil diperbarui!';</script>";
}

// 4. PROSES HAPUS SISWA (SOFT DELETE)
if (isset($_POST['hapus_siswa'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id_siswa = $_POST['id_siswa_hapus'];
    $stmt = $pdo->prepare("UPDATE siswa SET status_aktif = 0 WHERE id_siswa = :id_siswa");
    $stmt->execute(['id_siswa' => $id_siswa]);
    $pesan_notifikasi = "<script>var notification_type='warning'; var notification_title='Informasi'; var notification_message='Data siswa telah dipindahkan ke Tempat Sampah.';</script>";
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
    <title>Manajemen Siswa (Pemilih) - E-Voting</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- MEMANGGIL NAVBAR/SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        <div class="top-header">
            <div>
                <h4>Data Pemilih (Siswa)</h4>
                <small class="text-muted">
                    <i class="fas fa-calendar-check text-primary me-1"></i> Periode Aktif: 
                    <span class="badge badge-soft-primary"><?= htmlspecialchars($nama_periode_aktif); ?></span>
                    &bull; Total: <b><?= count($data_siswa); ?></b> Siswa
                </small>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if ($id_periode_aktif): ?>
                    <!-- Tombol Cetak PIN Baru (dengan Opsi Modal) -->
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCetakPin">
                        <i class="fas fa-print"></i> Cetak Token / PIN
                    </button>

                    <!-- Tombol Daftar Hadir -->
                    <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalDaftarHadir">
                        <i class="fas fa-clipboard-check"></i> Daftar Hadir
                    </button>
                    
                    <!-- Tombol Import CSV -->
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalImport">
                        <i class="fas fa-file-csv"></i> Import CSV
                    </button>

                    <!-- Tombol Tambah Siswa Manual -->
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                        <i class="fas fa-user-plus"></i> Tambah Siswa
                    </button>
                <?php else: ?>
                    <span class="badge bg-danger p-2"><i class="fas fa-lock me-1"></i> Aktifkan periode terlebih dahulu</span>
                <?php endif; ?>
            </div>
        </div>

        <?= $pesan_notifikasi; ?>

        <!-- TABEL DATA SISWA -->
        <div class="table-container">
            <div class="table-responsive">
                <table id="tabelSiswa" class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th style="width: 120px;">NIS</th>
                            <th>Nama Lengkap</th>
                            <th style="width: 140px;">Kelas</th>
                            <th style="width: 160px;">Token Akses</th>
                            <th style="width: 130px;">Status Suara</th>
                            <th class="text-center" style="width: 110px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_siswa) > 0): ?>
                            <?php foreach ($data_siswa as $row): ?>
                                <tr>
                                    <td class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['nis']); ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_siswa']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-secondary font-monospace"><?= htmlspecialchars($row['kelas']); ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <code class="token-pin"><?= htmlspecialchars($row['pin']); ?></code>
                                            <button type="button" class="btn btn-sm btn-link text-muted p-0 ms-1" onclick="salinToken('<?= htmlspecialchars($row['pin']); ?>')" title="Salin Token">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row['status_pilih'] == 1): ?>
                                            <span class="badge badge-soft-success">
                                                <i class="fas fa-check-circle me-1"></i> Sudah Memilih
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-soft-warning">
                                                <i class="fas fa-clock me-1"></i> Belum Memilih
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Tombol Edit -->
                                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $row['id_siswa']; ?>" title="Edit Data">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                            <!-- Tombol Hapus -->
                                            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalHapus<?= $row['id_siswa']; ?>" title="Hapus Data">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- MODAL EDIT SISWA -->
                                <div class="modal fade" id="modalEdit<?= $row['id_siswa']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                                <input type="hidden" name="id_siswa" value="<?= $row['id_siswa']; ?>">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><i class="fas fa-user-edit text-primary me-2"></i>Edit Data Siswa</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nomor Induk Siswa (NIS)</label>
                                                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($row['nis']); ?>" readonly>
                                                        <small class="text-muted">NIS tidak dapat diubah setelah terdaftar.</small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Lengkap Siswa</label>
                                                        <input type="text" name="nama_siswa" class="form-control" value="<?= htmlspecialchars($row['nama_siswa']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Kelas</label>
                                                        <input type="text" name="kelas" class="form-control" value="<?= htmlspecialchars($row['kelas']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_siswa" class="btn btn-primary">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- MODAL HAPUS SISWA -->
                                <div class="modal fade" id="modalHapus<?= $row['id_siswa']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                                                <input type="hidden" name="id_siswa_hapus" value="<?= $row['id_siswa']; ?>">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Hapus Siswa</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p class="mb-1">Apakah Anda yakin ingin menghapus data siswa:</p>
                                                    <div class="p-3 bg-light rounded-3 border my-2">
                                                        <div><b>Nama:</b> <?= htmlspecialchars($row['nama_siswa']); ?></div>
                                                        <div><b>NIS:</b> <?= htmlspecialchars($row['nis']); ?></div>
                                                        <div><b>Kelas:</b> <?= htmlspecialchars($row['kelas']); ?></div>
                                                    </div>
                                                    <small class="text-danger"><i class="fas fa-info-circle me-1"></i> Data akan dinonaktifkan dari sistem pemungutan suara.</small>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="hapus_siswa" class="btn btn-danger">Hapus Data</button>
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

    <!-- MODAL PILIH CETAK PIN / TOKEN (FITUR REKOMENDASI) -->
    <div class="modal fade" id="modalCetakPin" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="GET" action="cetak_pin.php" target="_blank">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-print me-2"></i> Cetak Kartu Token / PIN</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            Pilih cakupan pencetakan token suara. Anda dapat mencetak seluruh siswa sekaligus atau memilih per kelas tertentu.
                        </p>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Kategori Kelas</label>
                            <select name="kelas" class="form-select border-primary" id="modalSelectKelas">
                                <option value="all">-- Cetak Semua Siswa (Dipisah per Halaman Kelas) --</option>
                                <?php foreach ($daftar_kelas_filter as $kls): ?>
                                    <option value="<?= htmlspecialchars($kls['kelas']); ?>">
                                        Khusus Kelas: <?= htmlspecialchars($kls['kelas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-lightbulb me-1"></i> <b>Tips:</b> Lembar cetak sudah dilengkapi dengan garis pemotong (gunting), logo sekolah, kelas yang jelas, dan format hemat kertas (A4).
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-external-link-alt me-1"></i> Buka Lembar Cetak
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH SISWA -->
    <div class="modal fade" id="modalTambah" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="fas fa-user-plus text-primary me-2"></i> Tambah Siswa Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nomor Induk Siswa (NIS)</label>
                            <input type="text" name="nis" class="form-control" placeholder="Contoh: 20261001" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_siswa" class="form-control" placeholder="Contoh: Ahmad Fauzan" required autocomplete="off">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kelas</label>
                            <input type="text" name="kelas" class="form-control" placeholder="Contoh: XII RPL 1" required autocomplete="off">
                        </div>
                        <div class="alert alert-light border small text-muted">
                            <i class="fas fa-shield-alt text-success me-1"></i> Token/PIN 6 digit unik akan dibuatkan secara otomatis oleh sistem.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="tambah_siswa" class="btn btn-primary">Simpan Data Siswa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL IMPORT CSV -->
    <div class="modal fade" id="modalImport" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-file-csv me-2"></i> Import Data Siswa (CSV)</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-light border small">
                            <p class="mb-1"><b>Format Dokumen CSV:</b></p>
                            Kolom berurutan: <code>NIS, Nama Lengkap, Kelas</code>.<br>
                            <a href="data:text/csv;charset=utf-8,NIS,Nama Lengkap,Kelas%0A1001,Budi Santoso,XI RPL 1%0A1002,Siti Aminah,XI TKJ 2" download="format_import_siswa.csv" class="btn btn-sm btn-outline-success mt-2 fw-bold">
                                <i class="fas fa-download me-1"></i> Download Contoh Format CSV
                            </a>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih File CSV</label>
                            <input type="file" name="file_csv" class="form-control" accept=".csv, .txt" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="import_csv" class="btn btn-success">Mulai Import Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL CETAK DAFTAR HADIR -->
    <div class="modal fade" id="modalDaftarHadir" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="GET" action="cetak_daftar_hadir.php" target="_blank">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title fw-bold"><i class="fas fa-clipboard-list me-2"></i> Cetak Lembar Daftar Hadir</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Pilih Kategori Cetak</label>
                            <select name="filter" class="form-select border-success">
                                <option value="all">Semua Siswa (Dipisah per Halaman Kelas)</option>
                                
                                <optgroup label="Cetak Khusus per Kelas">
                                    <?php foreach ($daftar_kelas_filter as $kls): ?>
                                        <option value="kelas_<?= htmlspecialchars($kls['kelas']); ?>">Hanya Kelas <?= htmlspecialchars($kls['kelas']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <optgroup label="Cetak Khusus Anggota Eskul">
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

    <!-- SCRIPT WAJIB DATATABLES & SWEETALERT -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/js/scripts.js"></script>
    <script>
        $(document).ready(function() {
            $('#tabelSiswa').DataTable({
                "language": {
                    "search": "Cari Siswa:",
                    "lengthMenu": "Tampilkan _MENU_ data",
                    "info": "Menampilkan _START_ sampai _END_ dari _TOTAL_ siswa",
                    "infoEmpty": "Tidak ada data tersedia",
                    "zeroRecords": "Tidak ditemukan data siswa yang cocok",
                    "paginate": {
                        "first": "Awal",
                        "last": "Akhir",
                        "next": "Lanjut",
                        "previous": "Sebelum"
                    }
                },
                "pageLength": 10,
                "ordering": true
            });
        });

        function salinToken(teks) {
            navigator.clipboard.writeText(teks).then(function() {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Token ' + teks + ' disalin!',
                    showConfirmButton: false,
                    timer: 2000
                });
            });
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
