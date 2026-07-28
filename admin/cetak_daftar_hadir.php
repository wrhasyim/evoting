<?php
// admin/cetak_daftar_hadir.php

session_start();
require '../config/koneksi.php';

// 1. PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// 2. MENDAPATKAN PERIODE AKTIF
$stmt_periode = $pdo->query("SELECT id_periode, nama_periode FROM periode WHERE status_aktif = 1 LIMIT 1");
$periode_aktif = $stmt_periode->fetch();

if (!$periode_aktif) {
    die("<h3>Gagal memuat data: Belum ada Tahun Ajaran yang diaktifkan.</h3>");
}
$id_periode_aktif = $periode_aktif['id_periode'];
$nama_periode_aktif = $periode_aktif['nama_periode'];

// 3. MENERIMA FILTER DARI MODAL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$judul_laporan = "DAFTAR HADIR PEMILIHAN E-VOTING";
$sub_judul = "Kategori: Semua Siswa (Publik)";
$teks_ttd_default = "Panitia Pemilihan";
$mode_kategori = "kelas"; // Menentukan apakah dikelompokkan per kelas atau digabung (eskul)

$data_siswa = [];

// 4. LOGIKA PEMISAHAN KATEGORI
if ($filter === 'all') {
    $mode_kategori = "kelas";
    $stmt = $pdo->prepare("SELECT nis, nama_siswa, kelas FROM siswa WHERE id_periode = ? AND status_aktif = 1 ORDER BY kelas ASC, nama_siswa ASC");
    $stmt->execute([$id_periode_aktif]);
    $data_siswa = $stmt->fetchAll();
    
} elseif (strpos($filter, 'kelas_') === 0) {
    $mode_kategori = "kelas";
    $nama_kelas = substr($filter, 6); 
    $sub_judul = "Kategori: Khusus Kelas " . strtoupper($nama_kelas);
    $teks_ttd_default = "Wali Kelas " . $nama_kelas;
    
    $stmt = $pdo->prepare("SELECT nis, nama_siswa, kelas FROM siswa WHERE id_periode = ? AND status_aktif = 1 AND kelas = ? ORDER BY nama_siswa ASC");
    $stmt->execute([$id_periode_aktif, $nama_kelas]);
    $data_siswa = $stmt->fetchAll();
    
} elseif (strpos($filter, 'eskul_') === 0) {
    $mode_kategori = "eskul"; // Eskul digabung dalam satu kesatuan
    $id_eskul = substr($filter, 6); 
    
    $stmt_eskul = $pdo->prepare("SELECT nama_eskul FROM eskul WHERE id_eskul = ?");
    $stmt_eskul->execute([$id_eskul]);
    $nama_eskul = $stmt_eskul->fetchColumn();
    
    $sub_judul = "Kategori: Anggota " . ucwords($nama_eskul);
    $teks_ttd_default = "Pembina " . ucwords($nama_eskul); 
    
    $stmt = $pdo->prepare("
        SELECT s.nis, s.nama_siswa, s.kelas 
        FROM siswa s
        JOIN anggota_eskul ae ON s.id_siswa = ae.id_siswa
        WHERE s.id_periode = ? AND s.status_aktif = 1 AND ae.id_eskul = ?
        ORDER BY s.kelas ASC, s.nama_siswa ASC
    ");
    $stmt->execute([$id_periode_aktif, $id_eskul]);
    $data_siswa = $stmt->fetchAll();
}

// 5. PENGELOMPOKAN DATA BERDASARKAN MODE
if ($mode_kategori === "kelas") {
    $struktur_data = [];
    foreach ($data_siswa as $row) {
        $struktur_data[$row['kelas']][] = $row;
    }
} else {
    // Jika eskul, cukup simpan dalam satu wadah tunggal tanpa pecah kelas
    $struktur_data['Gabungan'] = $data_siswa;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Daftar Hadir - E-Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Times New Roman', Times, serif; background-color: #f4f7fa; padding: 20px; }
        .print-container { background: white; padding: 0; }
        .kop-surat { text-align: center; border-bottom: 3px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .kop-surat h3 { margin: 0; font-weight: bold; text-transform: uppercase; font-size: 1.5rem; }
        .kop-surat p { margin: 5px 0 0 0; font-size: 1.1rem; }
        
        .table-hadir th { text-align: center; vertical-align: middle; background-color: #f8f9fa !important; font-weight: bold; border-bottom: 2px solid #000; }
        .table-hadir td { vertical-align: middle; height: 45px; } 
        
        .editable { 
            border: 1px dashed #adb5bd; 
            padding: 2px 5px; 
            border-radius: 4px; 
            transition: 0.2s; 
            display: inline-block;
            min-width: 50px;
        }
        .editable:hover, .editable:focus { 
            background-color: #e9ecef; 
            outline: none; 
            border-color: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13,110,253,.25);
        }

        @media print {
            body { padding: 0; background-color: white; }
            .no-print { display: none !important; }
            .page-break { page-break-after: always; }
            @page { margin: 15mm; }
            
            .editable { 
                border: none !important; 
                padding: 0 !important; 
                background: transparent !important; 
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="mb-4 no-print text-center">
        <div class="alert alert-info d-inline-block text-start mb-3 shadow-sm border-info">
            <i class="fas fa-lightbulb me-2 fs-5"></i> 
            <b>Tips Cerdas:</b> Teks bergaris putus-putus bisa diklik dan diedit langsung sebelum mencetak.
        </div>
        <br>
        <button onclick="window.print()" class="btn btn-primary btn-lg shadow-sm rounded-pill px-4 fw-bold">
            <i class="fas fa-print me-2"></i> Cetak ke PDF / Printer
        </button>
    </div>

    <div class="print-container container-fluid px-0">
        <?php if (count($data_siswa) > 0): ?>
            <?php 
            $total_bagian = count($struktur_data);
            $counter = 0;
            
            foreach ($struktur_data as $label_bagian => $daftar_anggota): 
                $counter++;
                $tampil_teks_ttd = ($mode_kategori === "kelas") ? "Wali Kelas " . $label_bagian : $teks_ttd_default;
            ?>
                
                <div class="<?= ($counter < $total_bagian && $mode_kategori === 'kelas') ? 'page-break' : ''; ?>">
                    
                    <div class="kop-surat">
                        <h3 class="editable" contenteditable="true"><?= $judul_laporan; ?></h3><br>
                        <b class="editable fs-5" contenteditable="true">SMK TARUNA KARYA MANDIRI</b>
                        <p style="font-size: 0.95rem;">
                            <span class="editable" contenteditable="true">Tahun Ajaran: <?= htmlspecialchars($nama_periode_aktif); ?></span> | 
                            <span class="editable" contenteditable="true"><?= htmlspecialchars($sub_judul); ?></span>
                        </p>
                    </div>

                    <?php if ($mode_kategori === "kelas"): ?>
                        <h5 class="fw-bold mb-3 px-2">Kelas: <?= htmlspecialchars($label_bagian); ?></h5>
                    <?php endif; ?>
                    
                    <!-- Tabel Absensi -->
                    <table class="table table-bordered table-hadir align-middle border-dark">
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th width="15%">NIS</th>
                                <th width="35%">Nama Lengkap Siswa</th>
                                <?php if ($mode_kategori === "eskul"): ?>
                                    <th width="15%">Kelas</th>
                                    <th colspan="2" width="30%">Tanda Tangan</th>
                                <?php else: ?>
                                    <th colspan="2" width="45%">Tanda Tangan</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($daftar_anggota as $row): ?>
                                <tr>
                                    <td class="text-center"><?= $no; ?></td>
                                    <td class="text-center fw-bold"><?= htmlspecialchars($row['nis']); ?></td>
                                    <td class="px-3"><?= htmlspecialchars($row['nama_siswa']); ?></td>
                                    
                                    <?php if ($mode_kategori === "eskul"): ?>
                                        <td class="text-center"><span class="badge bg-secondary"><?= htmlspecialchars($row['kelas']); ?></span></td>
                                    <?php endif; ?>
                                    
                                    <!-- Logika Tanda Tangan Zig-Zag -->
                                    <?php if ($no % 2 != 0): ?>
                                        <td class="border-end-0 px-3" width="15%"><?= $no; ?>. </td>
                                        <td class="border-start-0" width="15%"></td>
                                    <?php else: ?>
                                        <td class="border-end-0" width="15%"></td>
                                        <td class="border-start-0 px-3" width="15%"><?= $no; ?>. </td>
                                    <?php endif; ?>
                                </tr>
                            <?php $no++; endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Area Tanda Tangan Bawah -->
                    <div class="row mt-4 mb-5 pb-4">
                        <div class="col-7"></div>
                        <div class="col-5 text-center">
                            <div class="editable" contenteditable="true" style="display: block; margin-bottom: 70px;">
                                <?= htmlspecialchars($tampil_teks_ttd); ?>,
                            </div>
                            <div class="editable fw-bold text-decoration-underline" contenteditable="true" style="display: block; width: 100%;">
                                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            </div>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-warning text-center mt-5 border border-warning">
                <h4><i class="fas fa-exclamation-circle me-2"></i>Tidak ada data siswa</h4>
                <p>Belum ada siswa yang terdaftar untuk kategori ini.</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>