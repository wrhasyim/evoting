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
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=format_import_anggota_eskul.csv');
    
    $output = fopen('php://output', 'w');
    
    // Baris Header Baru (3 Kolom untuk Akurasi Tinggi)
    fputcsv($output, ['Nama Siswa', 'Kelas', 'Ekstrakurikuler']);
    
    // Memberikan contoh pengisian data dengan kata kunci singkat
    fputcsv($output, ['Budi Santoso', 'XI RPL 1', 'Pramuka']);
    fputcsv($output, ['Siti Aminah', 'XI TKJ 2', 'OSIS']);
    
    fclose($output);
    exit; 
}

// ==========================================
// FITUR: IMPORT DATA CSV (AUTO-DETECT & MATCHING KELAS)
// ==========================================
if (isset($_POST['import_csv'])) {
    if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] == 0) {
        $file_tmp = $_FILES['file_csv']['tmp_name'];
        $handle = fopen($file_tmp, "r");
        
        // Melewati baris pertama (Header)
        fgetcsv($handle, 1000, ",");
        
        $sukses = 0;
        $gagal = 0;
        $pesan_error = "";
        $baris = 2; // Mulai menghitung dari baris ke-2

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Pastikan ketiga kolom terisi
            if (empty(trim($data[0])) || empty(trim($data[1])) || empty(trim($data[2]))) {
                $gagal++;
                $pesan_error .= "Baris $baris: Data tidak lengkap (ada kolom kosong).<br>";
                $baris++;
                continue;
            }

            $nama_siswa = trim($data[0]);
            $kelas_siswa = trim($data[1]);
            $input_eskul = trim($data[2]);

            // 1. Validasi Siswa (Pencocokan Akurat Nama & Kelas)
            $stmt_cek_siswa = $pdo->prepare("SELECT id_siswa FROM siswa WHERE nama_siswa = ? AND kelas = ? AND status_aktif = 1");
            $stmt_cek_siswa->execute([$nama_siswa, $kelas_siswa]);
            $siswa = $stmt_cek_siswa->fetch();

            if (!$siswa) {
                $gagal++;
                $pesan_error .= "Baris $baris: Siswa <b>$nama_siswa</b> (Kelas $kelas_siswa) tidak ditemukan.<br>";
                $baris++;
                continue;
            }
            $id_siswa = $siswa['id_siswa']; 

            // 2. Validasi Eskul (Auto-Detect Kata Kunci)
            // Menggunakan LIKE untuk mencari kata kunci di dalam nama_eskul
            $stmt_cek_eskul = $pdo->prepare("SELECT id_eskul, nama_eskul FROM eskul WHERE nama_eskul LIKE ? AND status_aktif = 1");
            $stmt_cek_eskul->execute(['%' . $input_eskul . '%']);
            $hasil_pencarian_eskul = $stmt_cek_eskul->fetchAll();
            
            if (count($hasil_pencarian_eskul) == 0) {
                $gagal++;
                $pesan_error .= "Baris $baris: Kata kunci <b>$input_eskul</b> tidak cocok dengan ekstrakurikuler mana pun.<br>";
                $baris++;
                continue;
            } elseif (count($hasil_pencarian_eskul) > 1) {
                // Mencegah salah deteksi jika kata kunci terlalu umum (misal: "Klub")
                $gagal++;
                $pesan_error .= "Baris $baris: Kata kunci <b>$input_eskul</b> terlalu umum dan mendeteksi lebih dari 1 ekstrakurikuler. Harap ketik lebih spesifik.<br>";
                $baris++;
                continue;
            }
            
            // Jika berhasil menemukan tepat 1 eskul, ambil ID-nya
            $id_eskul = $hasil_pencarian_eskul[0]['id_eskul'];
            $nama_eskul_ditemukan = $hasil_pencarian_eskul[0]['nama_eskul'];

            // 3. Validasi Duplikat (Cegah Double Data)
            $stmt_cek_duplikat = $pdo->prepare("SELECT id_anggota FROM anggota_eskul WHERE id_siswa = ? AND id_eskul = ?");
            $stmt_cek_duplikat->execute([$id_siswa, $id_eskul]);
            
            if ($stmt_cek_duplikat->fetch()) {
                $gagal++;
                $pesan_error .= "Baris $baris: <b>$nama_siswa</b> sudah terdaftar di <b>$nama_eskul_ditemukan</b> (Dilewati).<br>";
                $baris++;
                continue;
            }

            // 4. Proses Insert Data (Jika semua validasi lolos)
            $stmt_insert = $pdo->prepare("INSERT INTO anggota_eskul (id_siswa, id_eskul) VALUES (?, ?)");
            if ($stmt_insert->execute([$id_siswa, $id_eskul])) {
                $sukses++;
            }
            $baris++;
        }
        fclose($handle);

        // Menampilkan hasil proses import
        $pesan_notifikasi = "
            <div class='alert alert-info alert-dismissible fade show shadow-sm' role='alert'>
                <h5 class='alert-heading'><i class='fas fa-info-circle me-2'></i>Laporan Import Data</h5>
                <hr>
                <p class='mb-1'><b>Berhasil ditambahkan:</b> $sukses data.</p>
                <p class='mb-2'><b>Gagal / Dilewati:</b> $gagal data.</p>
                " . (!empty($pesan_error) ? "<div class='bg-light p-2 rounded text-danger small' style='max-height: 150px; overflow-y: auto;'>$pesan_error</div>" : "") . "
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
        ";
    } else {
        $pesan_notifikasi = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle me-2'></i>Harap pilih file CSV yang valid!</div>";
    }
}

// ==========================================
// MENGAMBIL DATA UNTUK DITAMPILKAN DI TABEL
// ==========================================
$stmt_data = $pdo->query("
    SELECT a.id_anggota, s.nis, s.nama_siswa, s.kelas, e.nama_eskul 
    FROM anggota_eskul a 
    JOIN siswa s ON a.id_siswa = s.id_siswa 
    JOIN eskul e ON a.id_eskul = e.id_eskul 
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
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fa; overflow-x: hidden; }
        .content { margin-left: 260px; padding: 40px; }
        .top-header { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .table-container { background: white; border-radius: 15px; padding: 30px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); }
        .dataTables_wrapper .row { margin-bottom: 15px; }
        .dataTables_filter input { border-radius: 6px; border: 1px solid #dee2e6; padding: 5px 10px; }
    </style>
</head>
<body>

    <!-- MEMANGGIL SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Anggota Ekstrakurikuler</h4>
                <small class="text-muted">Kelola data pemilih khusus (aturan tertutup) menggunakan import CSV.</small>
            </div>
            <div>
                <!-- Tombol Buka Modal Import -->
                <button type="button" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImport">
                    <i class="fas fa-file-import me-2"></i> Import Massal
                </button>
            </div>
        </div>

        <?= $pesan_notifikasi; ?>

        <div class="table-container border-top border-primary border-5">
            <div class="table-responsive">
                <table id="tabelAnggota" class="table table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">No</th>
                            <th width="15%">NIS</th>
                            <th width="30%">Nama Siswa</th>
                            <th width="15%">Kelas</th>
                            <th width="35%">Tergabung di Eskul</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_anggota) > 0): ?>
                            <?php $no = 1; foreach ($data_anggota as $row): ?>
                                <tr>
                                    <td class="text-muted"><?= $no++; ?></td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($row['nis']); ?></td>
                                    <td class="fw-medium"><?= htmlspecialchars($row['nama_siswa']); ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($row['kelas']); ?></span></td>
                                    <td><span class="badge bg-info text-dark px-3 py-2 border"><i class="fas fa-users me-1"></i> <?= htmlspecialchars($row['nama_eskul']); ?></span></td>
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
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-file-csv me-2"></i> Import Anggota Eskul</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    
                    <div class="alert alert-warning small mb-4">
                        <b>PENTING:</b> Sistem akan mencocokkan secara otomatis berdasarkan <b>Nama Siswa</b> dan <b>Kelas</b>. Anda bisa mengetik nama ekstrakurikuler secara singkat (contoh: "Pramuka" atau "OSIS").
                    </div>

                    <!-- Tombol Download Sample -->
                    <form method="POST" action="" class="mb-4 text-center">
                        <button type="submit" name="download_sample" class="btn btn-outline-dark btn-sm rounded-pill px-4 fw-bold">
                            <i class="fas fa-download me-1"></i> Download Template CSV
                        </button>
                    </form>

                    <hr>

                    <!-- Form Upload CSV -->
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="mb-4 mt-3">
                            <label class="form-label fw-bold">Pilih File CSV Anda</label>
                            <input type="file" name="file_csv" class="form-control form-control-lg" accept=".csv" required>
                            <small class="text-muted d-block mt-2">Pastikan file disimpan dalam format <b>CSV (Comma delimited)</b> saat Anda menyimpannya dari Microsoft Excel.</small>
                        </div>
                        <button type="submit" name="import_csv" class="btn btn-success w-100 fw-bold py-2">
                            <i class="fas fa-cloud-upload-alt me-2"></i> Mulai Proses Import
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT WAJIB DATATABLES -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#tabelAnggota').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                },
                "pageLength": 10,
                "ordering": true
            });
        });
    </script>
</body>
</html>