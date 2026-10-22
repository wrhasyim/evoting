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
    die("<h3 style='text-align:center; margin-top:50px;'>Gagal memuat data: Belum ada Tahun Ajaran yang diaktifkan.</h3>");
}
$id_periode_aktif = $periode_aktif['id_periode'];
$nama_periode_aktif = $periode_aktif['nama_periode'];

// 3. MENERIMA FILTER DARI MODAL & UKURAN KERTAS
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$ukuran_kertas = isset($_GET['kertas']) && strtoupper($_GET['kertas']) === 'F4' ? 'F4' : 'A4';
$judul_laporan = "DAFTAR HADIR PEMUNGUTAN SUARA E-VOTING";
$sub_judul = "Kategori: Seluruh Siswa (Umum)";
$teks_ttd_default = "Panitia Pemilihan";
$mode_kategori = "kelas";

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
    $sub_judul = ($nama_kelas === 'GURU') ? "Kategori: Khusus Dewan Guru & Staf" : "Kategori: Khusus Kelas " . strtoupper($nama_kelas);
    $teks_ttd_default = ($nama_kelas === 'GURU') ? "Perwakilan Dewan Guru" : "Wali Kelas " . $nama_kelas;
    
    $stmt = $pdo->prepare("SELECT nis, nama_siswa, kelas FROM siswa WHERE id_periode = ? AND status_aktif = 1 AND kelas = ? ORDER BY nama_siswa ASC");
    $stmt->execute([$id_periode_aktif, $nama_kelas]);
    $data_siswa = $stmt->fetchAll();
    
} elseif (strpos($filter, 'eskul_') === 0) {
    $mode_kategori = "eskul";
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
    $struktur_data['Gabungan'] = $data_siswa;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Daftar Hadir (<?= $ukuran_kertas; ?>) - <?= htmlspecialchars($sub_judul); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Times+New+Roman&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            font-family: 'Times New Roman', Times, serif; 
            background-color: #f1f5f9; 
            color: #000;
            padding: 0 0 40px 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* TOOLBAR ATAS SCREEN */
        .screen-toolbar {
            position: sticky;
            top: 0;
            background: #ffffff;
            border-bottom: 1px solid #cbd5e1;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            padding: 12px 24px;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .paper-sheet {
            max-width: <?= $ukuran_kertas === 'F4' ? '215mm' : '210mm'; ?>;
            min-height: <?= $ukuran_kertas === 'F4' ? '330mm' : '297mm'; ?>;
            margin: 25px auto;
            background: #ffffff;
            padding: 16mm 18mm;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            border-radius: 6px;
            transition: max-width 0.2s ease;
        }

        /* KOP SURAT FORMAL */
        .kop-surat { 
            border-bottom: 3px double #000; 
            padding-bottom: 12px; 
            margin-bottom: 22px; 
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .kop-logo {
            width: 70px;
            height: 70px;
            object-fit: contain;
        }

        .kop-text {
            flex: 1;
            text-align: center;
        }

        .kop-surat h4 { 
            margin: 0; 
            font-weight: bold; 
            font-size: 1.25rem; 
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kop-surat h3 { 
            margin: 2px 0; 
            font-weight: bold; 
            font-size: 1.45rem; 
            text-transform: uppercase;
        }

        .kop-surat p { 
            margin: 4px 0 0 0; 
            font-size: 0.95rem; 
        }
        
        .table-hadir th { 
            text-align: center; 
            vertical-align: middle; 
            background-color: #f1f5f9 !important; 
            font-weight: bold; 
            border: 1px solid #000 !important; 
            padding: 8px 6px;
            font-size: 0.95rem;
        }

        .table-hadir td { 
            vertical-align: middle; 
            height: 40px; 
            border: 1px solid #000 !important;
            padding: 6px 8px;
            font-size: 0.95rem;
        } 
        
        .editable { 
            border: 1px dashed #94a3b8; 
            padding: 2px 6px; 
            border-radius: 4px; 
            transition: 0.2s; 
            display: inline-block;
        }
        .editable:hover, .editable:focus { 
            background-color: #e0f2fe; 
            outline: none; 
            border-color: #0284c7;
        }

        @media print {
            body { 
                padding: 0; 
                background-color: white !important; 
            }
            .no-print { 
                display: none !important; 
            }
            .paper-sheet { 
                box-shadow: none !important; 
                border-radius: 0 !important;
                max-width: 100% !important; 
                width: 100% !important; 
                padding: 0 !important;
                margin: 0 !important;
            }
            .page-break { 
                page-break-after: always !important; 
                break-after: page !important;
            }
            @page { 
                size: <?= $ukuran_kertas === 'F4' ? '215mm 330mm' : 'A4 portrait'; ?>;
                margin: 12mm 15mm; 
            }
            .editable { 
                border: none !important; 
                padding: 0 !important; 
                background: transparent !important; 
            }
        }
    </style>
</head>
<body>

    <!-- TOOLBAR LAYAR (NO-PRINT) -->
    <div class="screen-toolbar no-print">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-file-alt text-primary fs-5"></i>
            <span class="fw-bold text-dark">Pratinjau Lembar Daftar Hadir</span>
            <span class="badge bg-light text-dark border ms-2"><?= htmlspecialchars($sub_judul); ?></span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <form method="GET" action="" style="display: inline-flex; align-items: center; gap: 6px; margin: 0;">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter); ?>">
                <label for="selectKertas" class="small fw-bold text-secondary mb-0">
                    <i class="fas fa-file-alt text-success me-1"></i>Kertas:
                </label>
                <select name="kertas" id="selectKertas" class="form-select form-select-sm" onchange="this.form.submit()" style="font-weight: 700; width: auto;">
                    <option value="A4" <?= $ukuran_kertas === 'A4' ? 'selected' : ''; ?>>A4 (210 &times; 297 mm)</option>
                    <option value="F4" <?= $ukuran_kertas === 'F4' ? 'selected' : ''; ?>>F4 / Folio (215 &times; 330 mm)</option>
                </select>
            </form>
            <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-print me-1"></i> Cetak Dokumen (<?= $ukuran_kertas; ?>)
            </button>
            <a href="siswa.php" class="btn btn-light btn-sm rounded-pill px-3 border">
                Kembali
            </a>
        </div>
    </div>

    <!-- AREA KERTAS DOKUMEN -->
    <div class="paper-sheet">
        <?php if (count($data_siswa) > 0): ?>
            <?php 
            $total_bagian = count($struktur_data);
            $counter = 0;
            
            foreach ($struktur_data as $label_bagian => $daftar_anggota): 
                $counter++;
                $tampil_teks_ttd = ($mode_kategori === "kelas") ? (($label_bagian === 'GURU') ? "Perwakilan Dewan Guru" : "Wali Kelas " . $label_bagian) : $teks_ttd_default;
            ?>
                
                <div class="<?= ($counter < $total_bagian && $mode_kategori === 'kelas') ? 'page-break' : ''; ?>">
                    
                    <!-- KOP SURAT RESMI -->
                    <div class="kop-surat">
                        <?php if (file_exists('../uploads/logo_utama.png')): ?>
                            <img src="../uploads/logo_utama.png" alt="Logo" class="kop-logo">
                        <?php endif; ?>
                        <div class="kop-text">
                            <h4 class="editable" contenteditable="true"><?= $judul_laporan; ?></h4>
                            <h3 class="editable" contenteditable="true">SMK TARUNA KARYA MANDIRI</h3>
                            <p>
                                <span class="editable" contenteditable="true">Tahun Ajaran: <?= htmlspecialchars($nama_periode_aktif); ?></span> &bull; 
                                <span class="editable" contenteditable="true"><?= htmlspecialchars($sub_judul); ?></span>
                            </p>
                        </div>
                    </div>

                    <?php if ($mode_kategori === "kelas"): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold m-0"><?= ($label_bagian === 'GURU' ? 'Kategori:' : 'Kelas:'); ?> <span class="editable" contenteditable="true"><?= htmlspecialchars($label_bagian === 'GURU' ? 'DEWAN GURU & STAF' : $label_bagian); ?></span></h5>
                            <span style="font-size: 0.95rem;"><?= ($label_bagian === 'GURU' ? 'Jumlah Pemilih (Guru):' : 'Jumlah Siswa:'); ?> <b><?= count($daftar_anggota); ?></b></span>
                        </div>
                    <?php endif; ?>
                    
                    <!-- TABEL ABSENSI -->
                    <table class="table table-bordered table-hadir align-middle w-100">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 18%;"><?= ($label_bagian === 'GURU' ? 'ID / Username' : 'NIS'); ?></th>
                                <th><?= ($label_bagian === 'GURU' ? 'Nama Lengkap Guru' : 'Nama Lengkap Siswa'); ?></th>
                                <?php if ($mode_kategori === "eskul"): ?>
                                    <th style="width: 15%;">Kelas</th>
                                    <th colspan="2" style="width: 32%;">Tanda Tangan / Verifikasi</th>
                                <?php else: ?>
                                    <th colspan="2" style="width: 36%;">Tanda Tangan / Verifikasi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($daftar_anggota as $row): ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $no; ?></td>
                                    <td class="text-center font-monospace"><?= htmlspecialchars($row['nis']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_siswa']); ?></td>
                                    
                                    <?php if ($mode_kategori === "eskul"): ?>
                                        <td class="text-center"><?= htmlspecialchars($row['kelas']); ?></td>
                                    <?php endif; ?>
                                    
                                    <!-- Logika Tanda Tangan Zig-Zag -->
                                    <?php if ($no % 2 != 0): ?>
                                        <td class="border-end-0 px-2" style="width: 18%;"><?= $no; ?>. </td>
                                        <td class="border-start-0" style="width: 18%;"></td>
                                    <?php else: ?>
                                        <td class="border-end-0" style="width: 18%;"></td>
                                        <td class="border-start-0 px-2" style="width: 18%;"><?= $no; ?>. </td>
                                    <?php endif; ?>
                                </tr>
                            <?php $no++; endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Area Tanda Tangan Bawah -->
                    <div class="row mt-4 mb-5 pt-2" style="page-break-inside: avoid; break-inside: avoid;">
                        <div class="col-7">
                            <small style="font-size: 0.82rem; color: #555;">
                                Dicetak pada: <?= date("d/m/Y H:i"); ?> WIB &bull; Sistem E-Voting
                            </small>
                        </div>
                        <div class="col-5 text-center">
                            <div class="editable" contenteditable="true" style="margin-bottom: 65px; display: inline-block;">
                                <?= htmlspecialchars($tampil_teks_ttd); ?>,
                            </div>
                            <br>
                            <div class="editable fw-bold" contenteditable="true" style="display: inline-block; min-width: 180px; border-bottom: 1px solid #000;">
                                ( .................................................... )
                            </div>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-warning text-center mt-5">
                <h4><i class="fas fa-exclamation-circle me-2"></i>Tidak ada data siswa</h4>
                <p>Belum ada siswa yang terdaftar untuk kategori yang dipilih.</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
