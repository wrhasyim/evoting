<?php
// admin/cetak_pin.php

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

// 2. Ambil Semua Daftar Kelas untuk Dropdown Filter
$daftar_semua_kelas = [];
if ($id_periode_aktif) {
    $stmt_k = $pdo->prepare("SELECT DISTINCT kelas FROM siswa WHERE id_periode = ? AND status_aktif = 1 ORDER BY kelas ASC");
    $stmt_k->execute([$id_periode_aktif]);
    $daftar_semua_kelas = $stmt_k->fetchAll(PDO::FETCH_COLUMN);
}

// 3. Tangani Filter Kelas, Ukuran Kertas, Kapasitas, & Mode Lembar
$filter_kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : 'all';
$ukuran_kertas = isset($_GET['kertas']) && strtoupper($_GET['kertas']) === 'F4' ? 'F4' : 'A4';
$mode_lembar = isset($_GET['mode']) && $_GET['mode'] === 'sambung' ? 'sambung' : 'kelas';

// Pilihan Kapasitas Kartu (8, 10, atau 12 Kartu per Lembar)
// Default utama: 8 kartu (2 kolom x 4 baris) - Terbukti paling proper, besar, dan aman 100% saat menggunakan Margin Default di dialog printer tanpa terpotong
$kapasitas_pilihan = isset($_GET['kapasitas']) ? (int)$_GET['kapasitas'] : 8;
if (!in_array($kapasitas_pilihan, [8, 10, 12])) {
    $kapasitas_pilihan = 8;
}
$cards_per_page = $kapasitas_pilihan;

// Perhitungan Geometri Fisik Kertas & Kartu (Maksimal Mengisi Sampai Bawah, WYSIWYG 1:1)
if ($cards_per_page === 8) {
    // 2 Kolom x 4 Baris (Format Paling Proper & Aman Margin Default)
    $grid_cols = 2;
    $grid_rows = 4;
    $grid_gap_x_mm = 5;
    $grid_gap_y_mm = 4;
    if ($ukuran_kertas === 'F4') {
        // F4 (215 x 330 mm): 4 baris x 68mm + 3 x 4mm = 284mm (Sangat aman dari batas margin default F4)
        $card_width_mm = 96;
        $card_height_mm = 68;
    } else {
        // A4 (210 x 297 mm): 4 baris x 61mm + 3 x 3.5mm = 254.5mm (Sangat aman dari batas margin default A4)
        $card_width_mm = 93;
        $card_height_mm = 61;
    }
} elseif ($cards_per_page === 10) {
    // 2 Kolom x 5 Baris
    $grid_cols = 2;
    $grid_rows = 5;
    $grid_gap_x_mm = 5;
    $grid_gap_y_mm = 3;
    if ($ukuran_kertas === 'F4') {
        $card_width_mm = 96;
        $card_height_mm = 55;
    } else {
        $card_width_mm = 93;
        $card_height_mm = 50;
    }
} elseif ($cards_per_page === 12) {
    // 3 Kolom x 4 Baris
    $grid_cols = 3;
    $grid_rows = 4;
    $grid_gap_x_mm = 4;
    $grid_gap_y_mm = 4;
    if ($ukuran_kertas === 'F4') {
        $card_width_mm = 63.5;
        $card_height_mm = 68;
    } else {
        $card_width_mm = 62;
        $card_height_mm = 61;
    }
}

// 4. Query Data Siswa
$params = [];
$query = "SELECT nis, nama_siswa, kelas, pin FROM siswa WHERE status_aktif = 1";

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
$data_siswa = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pengelompokan Data per Kelas
$data_per_kelas = [];
foreach ($data_siswa as $siswa) {
    $k = $siswa['kelas'];
    if (!isset($data_per_kelas[$k])) {
        $data_per_kelas[$k] = [];
    }
    $data_per_kelas[$k][] = $siswa;
}

// Struktur Halaman Cetak yang 100% Seragam (Presisi Mesin Potong Kertas)
$halaman_cetak = [];

if ($mode_lembar === 'kelas') {
    // Mode Pisah per Kelas: Setiap kelas mulai di lembar baru
    foreach ($data_per_kelas as $nama_kelas => $daftar_siswa_kelas) {
        $chunks = array_chunk($daftar_siswa_kelas, $cards_per_page);
        $total_lembar_kelas = count($chunks);
        foreach ($chunks as $idx_chunk => $siswa_halaman) {
            $halaman_cetak[] = [
                'kelas' => $nama_kelas,
                'lembar_ke' => $idx_chunk + 1,
                'total_lembar' => $total_lembar_kelas,
                'siswa' => $siswa_halaman
            ];
        }
    }
} else {
    // Mode Sambung Terus (Super Hemat Kertas, tanpa sisa slot per kelas)
    $chunks = array_chunk($data_siswa, $cards_per_page);
    $total_lembar = count($chunks);
    foreach ($chunks as $idx_chunk => $siswa_halaman) {
        $halaman_cetak[] = [
            'kelas' => 'Gabungan Seluruh Kelas',
            'lembar_ke' => $idx_chunk + 1,
            'total_lembar' => $total_lembar,
            'siswa' => $siswa_halaman
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Kartu Token Pemilih (<?= $ukuran_kertas; ?> &bull; <?= $cards_per_page; ?> pcs/lembar) - <?= htmlspecialchars($filter_kelas == 'all' ? 'Semua Kelas' : 'Kelas ' . $filter_kelas); ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* RESET & BASE */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f1f5f9; 
            color: #1e293b; 
            padding: 0 0 60px 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* TOOLBAR ATAS (HANYA LAYAR BROWSER) */
        .toolbar-top {
            position: sticky;
            top: 0;
            background: #ffffff;
            border-bottom: 1px solid #cbd5e1;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08);
            padding: 12px 24px;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .toolbar-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toolbar-title i {
            color: #2563eb;
        }

        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .form-select-custom {
            padding: 6px 10px;
            border-radius: 8px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.83rem;
            font-weight: 600;
            color: #334155;
            background-color: #ffffff;
            outline: none;
            cursor: pointer;
        }
        .form-select-custom:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 16px;
            border-radius: 9999px;
            font-size: 0.86rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-print {
            background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
            color: #ffffff;
        }

        .btn-back {
            background: #e2e8f0;
            color: #475569;
        }
        .btn-back:hover {
            background: #cbd5e1;
            color: #0f172a;
        }

        .badge-count {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        /* WADAH KERTAS / DOKUMEN */
        .paper-container {
            max-width: <?= $ukuran_kertas === 'F4' ? '215mm' : '210mm'; ?>;
            margin: 20px auto;
            padding: 0;
        }

        /* BANNER PEMBERITAHUAN FORMAT POTONG MESIN DI SCREEN */
        .screen-notice {
            background: #f0fdf4;
            border: 1.5px solid #86efac;
            color: #166534;
            border-radius: 10px;
            padding: 10px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.84rem;
            box-shadow: 0 2px 8px rgba(22, 101, 52, 0.05);
        }

        /* SHEET WRAPPER (MENJAMIN TAMPILAN SCREEN 100% SAMA PERSIS DENGAN KERTAS FISIK) */
        .sheet-wrapper {
            margin: 30px auto;
            width: <?= $ukuran_kertas === 'F4' ? '215mm' : '210mm'; ?>;
        }

        /* HEADER IDENTIFIKASI LEMBAR DI SCREEN (DISEMBUNYIKAN TOTAL SAAT PRINT) */
        .sheet-header-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            padding: 8px 16px;
            font-size: 0.84rem;
            color: #334155;
            box-shadow: 0 4px 10px rgba(0,0,0,0.02);
        }

        /* LEMBAR CETAK (PRINT SHEET) DENGAN UKURAN 1:1 FISIK SEBENARNYA */
        .print-sheet {
            background: #ffffff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.1);
            display: grid;
            grid-template-columns: repeat(<?= $grid_cols; ?>, 1fr);
            column-gap: <?= $grid_gap_x_mm; ?>mm;
            row-gap: <?= $grid_gap_y_mm; ?>mm;
            box-sizing: border-box;
            padding: <?= $ukuran_kertas === 'F4' ? '8mm 8mm 15mm 8mm' : '7mm 7mm 14mm 7mm'; ?>;
            width: <?= $ukuran_kertas === 'F4' ? '215mm' : '210mm'; ?>;
            height: <?= $ukuran_kertas === 'F4' ? '330mm' : '297mm'; ?>;
            min-height: <?= $ukuran_kertas === 'F4' ? '330mm' : '297mm'; ?>;
            max-height: <?= $ukuran_kertas === 'F4' ? '330mm' : '297mm'; ?>;
            border: 1.5px solid #cbd5e1;
            border-radius: 0 0 8px 8px;
            position: relative;
            overflow: hidden;
        }

        /* DESAIN KARTU TOKEN - BESAR, PROPORSIONAL & MENGISI LEMBAR SAMPAI BAWAH */
        .card-token {
            box-sizing: border-box;
            width: 100%;
            height: <?= $card_height_mm; ?>mm;
            max-height: <?= $card_height_mm; ?>mm;
            min-height: <?= $card_height_mm; ?>mm;
            border: 1.2px dashed #64748b;
            border-radius: 6px;
            padding: 6px 10px;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* SLOT KOSONG UNTUK MENJAGA KOORDINAT POTONG 100% IDENTIK */
        .card-empty {
            border: 1.2px dashed #cbd5e1;
            background: #fbfcfe;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-notice {
            font-size: 0.68rem;
            color: #94a3b8;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
            line-height: 1.3;
        }

        /* HEADER KARTU */
        .card-header-slip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 2px;
        }

        .header-left-brand {
            display: flex;
            align-items: center;
            gap: 7px;
            overflow: hidden;
        }

        .logo-slip {
            width: 24px;
            height: 24px;
            object-fit: contain;
            border-radius: 3px;
            flex-shrink: 0;
        }

        .logo-placeholder {
            width: 24px;
            height: 24px;
            border-radius: 3px;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            flex-shrink: 0;
        }

        .header-text {
            line-height: 1.15;
            overflow: hidden;
        }

        .header-text .app-name {
            font-size: 0.64rem;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .header-text .school-name {
            font-size: 0.55rem;
            font-weight: 700;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .header-cut-icon {
            font-size: 9px;
            color: #94a3b8;
            opacity: 0.7;
            flex-shrink: 0;
        }

        /* BODY KARTU: NAMA, NIS, KELAS */
        .card-body-slip {
            line-height: 1.25;
            margin-top: 1px;
        }

        .info-value-name {
            font-weight: 800;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: <?= $grid_cols === 2 ? '0.92rem' : '0.80rem'; ?>;
            display: block;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .info-row-combined {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .info-col-nis {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .info-label {
            color: #64748b;
            font-weight: 600;
            font-size: 0.72rem;
        }

        .info-value-nis {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: #334155;
            font-size: 0.76rem;
        }

        /* BADGE KELAS (KONTRAS TINGGI, BESAR & JELAS) */
        .badge-kelas-slip {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 800;
            font-size: 0.74rem;
            padding: 2px 7px;
            border-radius: 4px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* TOKEN PIN BOX (BESAR, JELAS & SANGAT KONTRAS) */
        .token-box {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 5px;
            padding: 3px 6px;
            text-align: center;
            margin: 2px 0;
        }

        .token-header-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1px;
        }

        .token-label {
            font-size: 0.52rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .security-text-tiny {
            font-size: 0.48rem;
            color: #64748b;
            font-style: italic;
        }

        .token-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: <?= $grid_cols === 2 ? '1.45rem' : '1.25rem'; ?>;
            font-weight: 900;
            letter-spacing: <?= $grid_cols === 2 ? '5px' : '3px'; ?>;
            color: #dc2626;
            line-height: 1.1;
            display: inline-block;
        }

        /* FOOTER KARTU */
        .card-footer-slip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.52rem;
            color: #64748b;
            border-top: 1px dotted #cbd5e1;
            padding-top: 3px;
            margin-top: 1px;
        }

        .footer-note {
            font-size: 0.50rem;
            color: #475569;
            font-weight: 600;
        }

        .barcode-sim {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            letter-spacing: -1px;
            color: #0f172a;
            font-weight: 900;
            opacity: 0.7;
        }

        /* ========================================================
           PENGATURAN CETAK KHUSUS (PRINTER EPSON/CANON/HP & MESIN POTONG)
           DIJAMIN 100% WYSIWYG, MENGISI LEMBAR SAMPAI BAWAH
           ======================================================== */
        @media print {
            body { 
                background: #ffffff !important; 
                padding: 0 !important; 
                margin: 0 !important;
            }
            .toolbar-top, 
            .screen-only, 
            .sheet-header-info, 
            .screen-notice { 
                display: none !important; 
            }
            .paper-container { 
                box-shadow: none !important; 
                border-radius: 0 !important;
                max-width: 100% !important; 
                width: 100% !important; 
                padding: 0 !important;
                margin: 0 !important;
                background: transparent !important;
            }
            .sheet-wrapper {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            .print-sheet {
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                display: grid !important;
                grid-template-columns: repeat(<?= $grid_cols; ?>, 1fr) !important;
                column-gap: <?= $grid_gap_x_mm; ?>mm !important;
                row-gap: <?= $grid_gap_y_mm; ?>mm !important;
                width: 100% !important;
                height: auto !important;
                max-height: none !important;
                box-sizing: border-box !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
            }
            .card-token {
                height: <?= $card_height_mm; ?>mm !important;
                max-height: <?= $card_height_mm; ?>mm !important;
                min-height: <?= $card_height_mm; ?>mm !important;
                border: 1px dashed #475569 !important;
                background-color: #ffffff !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .card-empty {
                border: 1px dashed #cbd5e1 !important;
                background: transparent !important;
                visibility: visible !important;
            }
            .card-empty .empty-notice {
                color: #94a3b8 !important;
                opacity: 0.5 !important;
            }
            .badge-kelas-slip {
                background-color: #000000 !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
            }
            .token-box {
                background: #f8fafc !important;
                border: 1px solid #cbd5e1 !important;
                -webkit-print-color-adjust: exact !important;
            }
            .token-value {
                color: #dc2626 !important;
                -webkit-print-color-adjust: exact !important;
            }
            @page {
                size: <?= $ukuran_kertas === 'F4' ? '215mm 330mm' : 'A4 portrait'; ?>;
                margin: <?= $ukuran_kertas === 'F4' ? '8mm 8mm 15mm 8mm' : '7mm 7mm 14mm 7mm'; ?>;
            }
        }
    </style>
</head>
<body>

    <!-- TOOLBAR ATAS (Hanya tampil di layar browser) -->
    <div class="toolbar-top">
        <div class="toolbar-left">
            <div class="toolbar-title">
                <i class="fas fa-ticket-alt"></i>
                <span>Cetak Token Suara</span>
            </div>
            <span class="badge-count">
                <i class="fas fa-users me-1"></i> Total: <?= count($data_siswa); ?> Siswa
            </span>
            <span class="badge-count" style="background:#f8fafc; color:#475569; border-color:#e2e8f0;">
                <i class="fas fa-layer-group me-1"></i> Total <?= count($halaman_cetak); ?> Lembar
            </span>
            <span class="badge-count" style="background:#f8fafc; color:#475569; border-color:#e2e8f0;">
                <i class="fas fa-calendar-alt me-1"></i> <?= htmlspecialchars($nama_periode_aktif); ?>
            </span>
        </div>

        <div class="toolbar-right">
            <!-- Filter Kertas, Kapasitas, Mode Lembar, & Kelas -->
            <form method="GET" action="" style="display: flex; align-items: center; gap: 7px; margin: 0; flex-wrap: wrap;">
                <!-- Pilihan Kertas -->
                <div style="display: flex; align-items: center; gap: 4px;">
                    <label for="selectKertas" style="font-size: 0.78rem; font-weight: 700; color: #475569;">
                        <i class="fas fa-file-alt text-primary"></i> Kertas:
                    </label>
                    <select name="kertas" id="selectKertas" class="form-select-custom" onchange="this.form.submit()">
                        <option value="F4" <?= $ukuran_kertas === 'F4' ? 'selected' : ''; ?>>F4 / Folio (215 &times; 330 mm)</option>
                        <option value="A4" <?= $ukuran_kertas === 'A4' ? 'selected' : ''; ?>>A4 (210 &times; 297 mm)</option>
                    </select>
                </div>

                <!-- Pilihan Kapasitas Kartu per Lembar -->
                <div style="display: flex; align-items: center; gap: 4px;">
                    <label for="selectKapasitas" style="font-size: 0.78rem; font-weight: 700; color: #475569;">
                        <i class="fas fa-th text-primary"></i> Kapasitas:
                    </label>
                    <select name="kapasitas" id="selectKapasitas" class="form-select-custom" onchange="this.form.submit()">
                        <option value="8" <?= $cards_per_page === 8 ? 'selected' : ''; ?>>8 Kartu (2&times;4) - Sangat Proper & Aman Margin Default</option>
                        <option value="10" <?= $cards_per_page === 10 ? 'selected' : ''; ?>>10 Kartu (2&times;5) - Padat 5 Baris</option>
                        <option value="12" <?= $cards_per_page === 12 ? 'selected' : ''; ?>>12 Kartu (3&times;4) - Standar 3 Kolom</option>
                    </select>
                </div>

                <!-- Pilihan Mode Susunan Lembar -->
                <div style="display: flex; align-items: center; gap: 4px;">
                    <label for="selectMode" style="font-size: 0.78rem; font-weight: 700; color: #475569;">
                        <i class="fas fa-layer-group text-primary"></i> Mode:
                    </label>
                    <select name="mode" id="selectMode" class="form-select-custom" onchange="this.form.submit()">
                        <option value="kelas" <?= $mode_lembar === 'kelas' ? 'selected' : ''; ?>>Pisah per Kelas</option>
                        <option value="sambung" <?= $mode_lembar === 'sambung' ? 'selected' : ''; ?>>Sambung Terus (Hemat Kertas)</option>
                    </select>
                </div>

                <!-- Pilihan Filter Kelas -->
                <div style="display: flex; align-items: center; gap: 4px;">
                    <label for="selectKelas" style="font-size: 0.78rem; font-weight: 700; color: #475569;">
                        <i class="fas fa-filter text-primary"></i> Kelas:
                    </label>
                    <select name="kelas" id="selectKelas" class="form-select-custom" onchange="this.form.submit()">
                        <option value="all" <?= $filter_kelas == 'all' ? 'selected' : ''; ?>>-- Semua Kelas --</option>
                        <?php foreach ($daftar_semua_kelas as $k): ?>
                            <option value="<?= htmlspecialchars($k); ?>" <?= $filter_kelas == $k ? 'selected' : ''; ?>>
                                Kelas: <?= htmlspecialchars($k); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <!-- Tombol Cetak -->
            <button class="btn-action btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak (<?= $ukuran_kertas; ?> &bull; <?= $cards_per_page; ?> pcs)
            </button>

            <!-- Tombol Kembali -->
            <a href="siswa.php" class="btn-action btn-back">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- AREA DOKUMEN CETAK -->
    <div class="paper-container">

        <!-- NOTIFIKASI SCREEN: FORMAT WYSIWYG & PAS PENUH -->
        <div class="screen-only screen-notice">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-check-circle" style="font-size: 1.3rem; color: #16a34a;"></i>
                <div>
                    <b>Format Presisi Pas Penuh (WYSIWYG 1:1 Aktif):</b>
                    <span>
                        Setiap lembar memuat <b><?= $cards_per_page; ?> kartu (<?= $grid_cols; ?> kolom &times; <?= $grid_rows; ?> baris)</b>. 
                        Kartu berukuran <b>besar, proporsional, dan mengisi kertas sampai batas bawah</b> tanpa sisa ruang kosong melompong. 
                        Tampilan di layar Anda sekarang 100% sama dengan hasil cetak printer.
                    </span>
                </div>
            </div>
            <button class="btn-action btn-print" style="padding: 5px 14px; font-size: 0.8rem;" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Cetak Sekarang
            </button>
        </div>

        <?php if (count($data_siswa) === 0): ?>
            <div style="text-align: center; padding: 60px 20px; color: #64748b; background: #ffffff; border-radius: 12px; border: 1px solid #cbd5e1;">
                <i class="fas fa-id-card" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px; display: block;"></i>
                <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 6px;">Tidak Ada Data Siswa</h3>
                <p style="font-size: 0.95rem;">Tidak ditemukan data siswa aktif untuk kelas atau periode yang dipilih.</p>
                <a href="siswa.php" class="btn-action btn-print" style="margin-top: 15px; display: inline-flex;">Kembali ke Data Siswa</a>
            </div>
        <?php else: ?>

            <?php 
            $nomor_lembar = 0;
            foreach ($halaman_cetak as $halaman): 
                $nomor_lembar++;
                $daftar_kartu = $halaman['siswa'];
                $jumlah_kartu = count($daftar_kartu);
                $slot_kosong = $cards_per_page - $jumlah_kartu;
            ?>

                <!-- WRAPPER 1:1 FISIK UNTUK SETIAP LEMBAR KERTAS -->
                <div class="sheet-wrapper">
                    
                    <!-- SCREEN ONLY: INFORMASI LEMBAR (DISEMBUNYIKAN OTOMATIS SAAT DICETAK) -->
                    <div class="screen-only sheet-header-info">
                        <div>
                            <i class="fas fa-file-alt text-primary me-1"></i>
                            <b>Lembar Ke-<?= $nomor_lembar; ?></b> dari <?= count($halaman_cetak); ?> Lembar &bull; 
                            Kelas: <b><?= htmlspecialchars($halaman['kelas']); ?></b> 
                            (<?= $jumlah_kartu; ?> Siswa<?= $slot_kosong > 0 ? " + $slot_kosong Slot Kosong" : ""; ?>)
                        </div>
                        <span style="font-size: 0.76rem; font-weight: 700; color: #64748b;">
                            Format <?= $ukuran_kertas; ?> (<?= $cards_per_page; ?> Kartu &bull; <?= $grid_cols; ?>&times;<?= $grid_rows; ?>)
                        </span>
                    </div>

                    <!-- LEMBAR CETAK FISIK (100% IDENTIK DAN SERAGAM DI SETIAP HALAMAN) -->
                    <div class="print-sheet">
                        
                        <!-- KARTU TOKEN SISWA -->
                        <?php foreach ($daftar_kartu as $s): ?>
                            <div class="card-token">
                                <!-- Header Kartu -->
                                <div class="card-header-slip">
                                    <div class="header-left-brand">
                                        <?php if (file_exists('../uploads/logo_utama.png')): ?>
                                            <img src="../uploads/logo_utama.png" alt="Logo" class="logo-slip">
                                        <?php else: ?>
                                            <div class="logo-placeholder">
                                                <i class="fas fa-vote-yea"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="header-text">
                                            <div class="app-name">E-VOTING RESMI PEMILIHAN</div>
                                            <div class="school-name">SMK TARUNA KARYA MANDIRI</div>
                                        </div>
                                    </div>
                                    <div class="header-cut-icon">
                                        <i class="fas fa-scissors"></i>
                                    </div>
                                </div>

                                <!-- Body Kartu: Nama, NIS, dan KELAS -->
                                <div class="card-body-slip">
                                    <span class="info-value-name" title="<?= htmlspecialchars($s['nama_siswa']); ?>">
                                        <?= htmlspecialchars($s['nama_siswa']); ?>
                                    </span>
                                    <div class="info-row-combined">
                                        <div class="info-col-nis">
                                            <span class="info-label">NIS:</span>
                                            <span class="info-value-nis"><?= htmlspecialchars($s['nis']); ?></span>
                                        </div>
                                        <div class="info-col-kelas">
                                            <span class="badge-kelas-slip"><?= htmlspecialchars($s['kelas']); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Box PIN / Token (Besar, Tebal, Kontras) -->
                                <div class="token-box">
                                    <div class="token-header-meta">
                                        <span class="token-label"><i class="fas fa-key me-1"></i> TOKEN / PIN PEMILIH:</span>
                                        <span class="security-text-tiny"><i class="fas fa-shield-alt"></i> Rahasia &bull; Sekali Pakai</span>
                                    </div>
                                    <div class="token-value"><?= htmlspecialchars($s['pin']); ?></div>
                                </div>

                                <!-- Footer Kartu -->
                                <div class="card-footer-slip">
                                    <span class="footer-note">&bull; Masukkan Token di Bilik Suara &bull;</span>
                                    <span class="barcode-sim">
                                        |||| | | ||| | || | |||
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- SLOT KOSONG UNTUK MEMASTIKAN KOORDINAT POTONG TETAP IDENTIK -->
                        <?php for ($i = 0; $i < $slot_kosong; $i++): ?>
                            <div class="card-token card-empty">
                                <div class="empty-notice">
                                    <i class="fas fa-cut" style="font-size: 1.1rem; margin-bottom: 3px; display: block;"></i>
                                    Area Slot Kosong<br>
                                    <span style="font-size: 0.55rem; font-weight: normal; opacity: 0.7;">Panduan Pemotong Mesin</span>
                                </div>
                            </div>
                        <?php endfor; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</body>
</html>
