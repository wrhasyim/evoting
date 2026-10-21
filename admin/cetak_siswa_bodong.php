<?php
// admin/cetak_siswa_bodong.php

session_start();
require '../config/koneksi.php';

// PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// 1. Ambil Periode Aktif
$stmt_periode = $pdo->query("SELECT id_periode, nama_periode FROM periode WHERE status_aktif = 1 LIMIT 1");
$periode_aktif = $stmt_periode->fetch();
$id_periode_aktif = $periode_aktif['id_periode'] ?? null;
$nama_periode_aktif = $periode_aktif['nama_periode'] ?? 'T.A Tidak Diketahui';

// 2. Ambil Daftar Kelas untuk Filter
$stmt_kelas = $pdo->prepare("
    SELECT DISTINCT kelas 
    FROM siswa 
    WHERE id_siswa NOT IN (SELECT id_siswa FROM anggota_eskul) 
    AND status_aktif = 1 
    " . ($id_periode_aktif ? "AND id_periode = $id_periode_aktif" : "") . "
    ORDER BY kelas ASC
");
$stmt_kelas->execute();
$daftar_kelas = $stmt_kelas->fetchAll(PDO::FETCH_COLUMN);

// 3. Tangani Filter Kelas & Ukuran Kertas
$filter_kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : 'all';
$ukuran_kertas = isset($_GET['kertas']) && strtoupper($_GET['kertas']) === 'F4' ? 'F4' : 'A4';

// 4. Query Data Siswa Tanpa Eskul
$params = [];
$query = "
    SELECT id_siswa, nis, nama_siswa, kelas 
    FROM siswa 
    WHERE id_siswa NOT IN (SELECT id_siswa FROM anggota_eskul) 
    AND status_aktif = 1
";

if ($id_periode_aktif) {
    $query .= " AND id_periode = ?";
    $params[] = $id_periode_aktif;
}

if ($filter_kelas !== '' && $filter_kelas !== 'all') {
    $query .= " AND kelas = ?";
    $params[] = $filter_kelas;
}

$query .= " ORDER BY kelas ASC, nama_siswa ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data_siswa_bodong = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pengelompokan Data per Kelas
$data_per_kelas = [];
foreach ($data_siswa_bodong as $siswa) {
    $k = $siswa['kelas'];
    if (!isset($data_per_kelas[$k])) {
        $data_per_kelas[$k] = [];
    }
    $data_per_kelas[$k][] = $siswa;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Rekapitulasi Siswa Tanpa Eskul (<?= $ukuran_kertas; ?>) - E-Voting</title>
    <!-- Google Fonts & Bootstrap 5 -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Times+New+Roman&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            font-family: 'Times New Roman', Times, serif; 
            background-color: #f1f5f9; 
            color: #000;
            padding: 0 0 50px 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* TOOLBAR LAYAR (TERSEMBUNYI SAAT CETAK) */
        .screen-toolbar {
            position: sticky;
            top: 0;
            background: #ffffff;
            border-bottom: 1px solid #cbd5e1;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 12px 24px;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: 'Plus Jakarta Sans', sans-serif;
            flex-wrap: wrap;
            gap: 12px;
        }

        .paper-sheet {
            max-width: <?= $ukuran_kertas === 'F4' ? '215mm' : '210mm'; ?>;
            min-height: <?= $ukuran_kertas === 'F4' ? '330mm' : '297mm'; ?>;
            margin: 25px auto;
            background: #ffffff;
            padding: 16mm 18mm;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 8px;
            transition: max-width 0.2s ease;
        }

        /* KOP SURAT FORMAL */
        .kop-surat { 
            border-bottom: 3px double #000; 
            padding-bottom: 12px; 
            margin-bottom: 22px; 
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .kop-logo {
            width: 75px;
            height: 75px;
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

        .table-laporan {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 0.92rem;
        }

        .table-laporan th {
            border: 1px solid #000 !important;
            background-color: #f1f5f9 !important;
            padding: 8px 6px;
            text-align: center;
            font-weight: bold;
            vertical-align: middle;
        }

        .table-laporan td {
            border: 1px solid #000 !important;
            padding: 6px 8px;
            vertical-align: middle;
        }

        .class-heading {
            background-color: #e2e8f0;
            padding: 6px 12px;
            font-weight: bold;
            font-size: 1rem;
            border: 1px solid #000;
            border-bottom: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .badge-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.76rem;
            font-weight: bold;
            border: 1px solid #991b1b;
            color: #991b1b;
            background-color: #fef2f2;
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
            .screen-toolbar { 
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
                margin: 10mm 12mm; 
            }
            .editable { 
                border: none !important; 
                padding: 0 !important; 
                background: transparent !important; 
            }
            .table-laporan th {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <!-- SCREEN TOOLBAR -->
    <div class="screen-toolbar no-print">
        <div class="d-flex align-items-center gap-3">
            <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                <i class="fas fa-print text-danger"></i>
                <span>Cetak Rekap Siswa Tanpa Ekstrakurikuler</span>
            </div>
            <span class="badge bg-danger text-white rounded-pill px-3 py-2">
                Total: <?= count($data_siswa_bodong); ?> Siswa
            </span>
            <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
                Periode: <?= htmlspecialchars($nama_periode_aktif); ?>
            </span>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Filter Kertas & Kelas -->
            <form method="GET" action="" style="display: flex; align-items: center; gap: 8px; margin: 0; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 4px;">
                    <label for="selectKertas" style="font-size: 0.82rem; font-weight: 700; color: #475569;">
                        <i class="fas fa-file-alt text-danger"></i> Kertas:
                    </label>
                    <select name="kertas" id="selectKertas" class="form-select form-select-sm" onchange="this.form.submit()" style="font-weight: 600; width: auto;">
                        <option value="A4" <?= $ukuran_kertas === 'A4' ? 'selected' : ''; ?>>A4 (210 &times; 297 mm)</option>
                        <option value="F4" <?= $ukuran_kertas === 'F4' ? 'selected' : ''; ?>>F4 / Folio (215 &times; 330 mm)</option>
                    </select>
                </div>

                <div style="display: flex; align-items: center; gap: 4px;">
                    <label for="filterKelas" style="font-size: 0.82rem; font-weight: 700; color: #475569;">
                        <i class="fas fa-filter text-danger"></i> Kelas:
                    </label>
                    <select name="kelas" id="filterKelas" class="form-select form-select-sm" onchange="this.form.submit()" style="font-weight: 600;">
                        <option value="all" <?= $filter_kelas == 'all' ? 'selected' : ''; ?>>-- Cetak Semua Kelas --</option>
                        <?php foreach ($daftar_kelas as $k): ?>
                            <option value="<?= htmlspecialchars($k); ?>" <?= $filter_kelas == $k ? 'selected' : ''; ?>>
                                Kelas: <?= htmlspecialchars($k); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <button onclick="window.print()" class="btn btn-danger btn-sm rounded-pill px-4 fw-bold shadow-sm">
                <i class="fas fa-print me-1"></i> Cetak Dokumen (<?= $ukuran_kertas; ?>)
            </button>
            <a href="index.php" class="btn btn-light btn-sm rounded-pill px-3 border">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <!-- AREA DOKUMEN CETAK A4 -->
    <div class="paper-sheet">
        <!-- KOP SURAT FORMAL -->
        <div class="kop-surat">
            <?php if (file_exists('../uploads/logo_utama.png')): ?>
                <img src="../uploads/logo_utama.png" alt="Logo" class="kop-logo">
            <?php endif; ?>
            <div class="kop-text">
                <h4 class="editable" contenteditable="true">LAPORAN REKAPITULASI DATA SISWA</h4>
                <h3 class="editable" contenteditable="true">SMK TARUNA KARYA MANDIRI</h3>
                <p>
                    <span class="editable" contenteditable="true"><b>Kategori:</b> Siswa Belum Terdaftar Pada Ekstrakurikuler Mana Pun</span> &bull; 
                    <span class="editable" contenteditable="true">T.A: <?= htmlspecialchars($nama_periode_aktif); ?></span>
                </p>
            </div>
        </div>

        <?php if (count($data_siswa_bodong) === 0): ?>
            <div class="alert alert-success text-center py-5 my-4">
                <i class="fas fa-check-circle fs-1 text-success mb-3 d-block"></i>
                <h5 class="fw-bold text-dark">Luar Biasa! Tidak Ada Siswa Tanpa Eskul</h5>
                <p class="text-muted m-0">Seluruh siswa aktif telah berhasil ditugaskan ke dalam ekstrakurikuler di sistem.</p>
            </div>
        <?php else: ?>

            <?php 
            $no_global = 1;
            foreach ($data_per_kelas as $nama_kelas => $daftar_siswa_kelas): 
            ?>
                <!-- HEADER KELAS -->
                <div class="class-heading">
                    <span>KELAS: <?= htmlspecialchars($nama_kelas); ?></span>
                    <span style="font-size: 0.88rem; font-weight: normal;">Jumlah: <b><?= count($daftar_siswa_kelas); ?> Siswa</b></span>
                </div>

                <table class="table-laporan">
                    <thead>
                        <tr>
                            <th style="width: 5%;">No</th>
                            <th style="width: 18%;">NIS</th>
                            <th style="width: 35%;">Nama Lengkap Siswa</th>
                            <th style="width: 17%;">Status</th>
                            <th style="width: 25%;">Rekomendasi / Penugasan Eskul</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no_kelas = 1;
                        foreach ($daftar_siswa_kelas as $row): 
                        ?>
                            <tr>
                                <td style="text-align: center;"><?= $no_kelas++; ?></td>
                                <td style="text-align: center; font-family: monospace; font-weight: bold;"><?= htmlspecialchars($row['nis']); ?></td>
                                <td><?= htmlspecialchars($row['nama_siswa']); ?></td>
                                <td style="text-align: center;">
                                    <span class="badge-status">Belum Ada Eskul</span>
                                </td>
                                <td style="border-bottom: 1px dotted #000; text-align: center; color: #666; font-size: 0.8rem;">
                                    ..................................................
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endforeach; ?>

            <!-- AREA PENGESAHAN DOKUMEN -->
            <div class="row mt-4 pt-2" style="page-break-inside: avoid; break-inside: avoid;">
                <div class="col-7">
                    <small style="font-size: 0.82rem; color: #555;">
                        <i>Dokumen ini dicetak otomatis dari Sistem E-Voting pada tanggal <?= date("d/m/Y H:i"); ?> WIB.</i><br>
                        <i>Harap diserahkan ke Bidang Kesiswaan atau Wali Kelas terkait untuk penetapan eskul.</i>
                    </small>
                </div>
                <div class="col-5 text-center">
                    <div class="editable" contenteditable="true" style="margin-bottom: 65px; display: inline-block;">
                        Mengetahui,<br>
                        <b>Waka Kesiswaan / Panitia</b>
                    </div>
                    <br>
                    <div class="editable fw-bold" contenteditable="true" style="display: inline-block; min-width: 180px; border-bottom: 1px solid #000;">
                        ( .................................................... )
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

</body>
</html>
