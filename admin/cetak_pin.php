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

// 3. Tangani Filter Kelas
$filter_kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : 'all';

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Kartu Token Pemilih - <?= htmlspecialchars($filter_kelas == 'all' ? 'Semua Kelas' : 'Kelas ' . $filter_kelas); ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
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

        /* TOOLBAR ATAS (HANYA DITAMPILKAN DI LAYAR, DISEMBUNYIKAN DI PRINTER) */
        .toolbar-top {
            position: sticky;
            top: 0;
            background: #ffffff;
            border-bottom: 1px solid #cbd5e1;
            box-shadow: 0 4px 15px rgba(15, 23, 42, 0.08);
            padding: 14px 24px;
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
            gap: 12px;
        }

        .toolbar-title {
            font-size: 1.1rem;
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
            gap: 10px;
            flex-wrap: wrap;
        }

        .form-select-kelas {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1.5px solid #cbd5e1;
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
            background-color: #ffffff;
            outline: none;
            cursor: pointer;
        }
        .form-select-kelas:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.15);
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 9999px;
            font-size: 0.9rem;
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
            padding: 6px 12px;
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 700;
        }

        /* WADAH KERTAS / DOKUMEN */
        .paper-container {
            max-width: 210mm;
            margin: 25px auto;
            background: #ffffff;
            padding: 15mm 15mm;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            border-radius: 12px;
        }

        /* JUDUL KELOMPOK KELAS */
        .class-banner {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-left: 6px solid #2563eb;
            border-radius: 8px;
            padding: 12px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .class-banner-title {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .class-banner-meta {
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
        }

        /* GRID KARTU PIN (3 KOLOM PRESISI PADA A4) */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 35px;
        }

        /* DESAIN KARTU TOKEN / TIKET RESMI */
        .card-token {
            border: 1.5px dashed #94a3b8;
            border-radius: 10px;
            padding: 12px 14px;
            background-color: #ffffff;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            page-break-inside: avoid;
            break-inside: avoid;
            transition: transform 0.2s;
        }

        .card-token:hover {
            border-color: #2563eb;
        }

        /* Gunting Indikator Garis */
        .cut-indicator {
            position: absolute;
            top: -9px;
            right: 12px;
            background: #ffffff;
            padding: 0 6px;
            font-size: 10px;
            color: #64748b;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 3px;
        }

        /* HEADER KARTU */
        .card-header-slip {
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .logo-slip {
            width: 30px;
            height: 30px;
            object-fit: contain;
            border-radius: 6px;
        }

        .logo-placeholder {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .header-text {
            flex: 1;
            line-height: 1.15;
        }

        .header-text .app-name {
            font-size: 0.65rem;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .header-text .school-name {
            font-size: 0.58rem;
            font-weight: 600;
            color: #64748b;
        }

        /* BODY KARTU: NIS, NAMA, KELAS */
        .card-body-slip {
            font-size: 0.76rem;
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }

        .info-label {
            color: #64748b;
            font-weight: 600;
            font-size: 0.72rem;
        }

        .info-value-name {
            font-weight: 800;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 170px;
            font-size: 0.82rem;
            display: block;
            margin-bottom: 3px;
        }

        .info-value-nis {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: #334155;
            font-size: 0.76rem;
        }

        /* KELAS BADGE (SESUAI PERMINTAAN USER: DITAMBAHI KELAS DENGAN CANTIK) */
        .badge-kelas-slip {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 800;
            font-size: 0.74rem;
            padding: 2px 8px;
            border-radius: 4px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        /* TOKEN PIN BOX (BESAR & SANGAT JELAS) */
        .token-box {
            background: linear-gradient(145deg, #f8fafc 0%, #e2e8f0 100%);
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            padding: 6px 4px;
            text-align: center;
            margin-top: 4px;
            margin-bottom: 6px;
        }

        .token-label {
            font-size: 0.58rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: block;
            margin-bottom: 2px;
        }

        .token-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: 4px;
            color: #dc2626; /* Merah menyala agar mudah dibaca */
            line-height: 1.2;
            display: inline-block;
        }

        /* FOOTER KARTU */
        .card-footer-slip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.58rem;
            color: #64748b;
            border-top: 1px dotted #cbd5e1;
            padding-top: 5px;
            margin-top: 2px;
        }

        .security-text {
            font-style: italic;
            font-size: 0.55rem;
            color: #475569;
            line-height: 1.1;
        }

        .barcode-sim {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.65rem;
            letter-spacing: -1px;
            color: #0f172a;
            font-weight: 900;
            opacity: 0.7;
        }

        /* PENGATURAN CETAK KHUSUS (PRINTER & PDF) */
        @media print {
            body { 
                background: #ffffff !important; 
                padding: 0 !important; 
            }
            .toolbar-top { 
                display: none !important; 
            }
            .paper-container { 
                box-shadow: none !important; 
                border-radius: 0 !important;
                max-width: 100% !important; 
                width: 100% !important; 
                padding: 0 !important;
                margin: 0 !important;
            }
            .class-banner {
                background: #f1f5f9 !important;
                border: 1px solid #cbd5e1 !important;
                border-left: 5px solid #000000 !important;
                padding: 8px 12px !important;
                margin-bottom: 12px !important;
            }
            .cards-grid {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 10px !important;
                margin-bottom: 20px !important;
            }
            .card-token {
                border: 1px dashed #475569 !important;
                background-color: #ffffff !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
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
                color: #b91c1c !important;
            }
            .page-break { 
                page-break-after: always !important; 
                break-after: page !important;
            }
            @page {
                size: A4 portrait;
                margin: 10mm;
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
                <span>Cetak Token Suara Pemilih</span>
            </div>
            <span class="badge-count">
                <i class="fas fa-users me-1"></i> Total: <?= count($data_siswa); ?> Siswa
            </span>
            <span class="badge-count" style="background:#f8fafc; color:#475569; border-color:#e2e8f0;">
                <i class="fas fa-calendar-alt me-1"></i> <?= htmlspecialchars($nama_periode_aktif); ?>
            </span>
        </div>

        <div class="toolbar-right">
            <!-- Filter Kelas -->
            <form method="GET" action="" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                <label for="selectKelas" style="font-size: 0.82rem; font-weight: 700; color: #475569;">Pilih Kelas:</label>
                <select name="kelas" id="selectKelas" class="form-select-kelas" onchange="this.form.submit()">
                    <option value="all" <?= $filter_kelas == 'all' ? 'selected' : ''; ?>>-- Cetak Semua Kelas --</option>
                    <?php foreach ($daftar_semua_kelas as $k): ?>
                        <option value="<?= htmlspecialchars($k); ?>" <?= $filter_kelas == $k ? 'selected' : ''; ?>>
                            Kelas: <?= htmlspecialchars($k); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Tombol Cetak -->
            <button class="btn-action btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Dokumen (A4)
            </button>

            <!-- Tombol Kembali -->
            <a href="siswa.php" class="btn-action btn-back">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <!-- AREA DOKUMEN CETAK -->
    <div class="paper-container">

        <?php if (count($data_siswa) === 0): ?>
            <div style="text-align: center; padding: 60px 20px; color: #64748b;">
                <i class="fas fa-id-card" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px; display: block;"></i>
                <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 6px;">Tidak Ada Data Siswa</h3>
                <p style="font-size: 0.95rem;">Tidak ditemukan data siswa aktif untuk kelas atau periode yang dipilih.</p>
                <a href="siswa.php" class="btn-action btn-print" style="margin-top: 15px; display: inline-flex;">Kembali ke Data Siswa</a>
            </div>
        <?php else: ?>

            <?php 
            $total_kelas = count($data_per_kelas);
            $idx_kelas = 0;

            foreach ($data_per_kelas as $nama_kelas => $daftar_siswa_kelas): 
                $idx_kelas++;
            ?>
                <!-- BANNER HEADER KELAS -->
                <div class="class-banner">
                    <div class="class-banner-title">
                        <i class="fas fa-graduation-cap text-primary"></i>
                        <span>DAFTAR TOKEN KELAS: <?= htmlspecialchars($nama_kelas); ?></span>
                    </div>
                    <div class="class-banner-meta">
                        Jumlah: <b><?= count($daftar_siswa_kelas); ?> Siswa</b> &bull; <?= htmlspecialchars($nama_periode_aktif); ?>
                    </div>
                </div>

                <!-- GRID KARTU TOKEN KELAS INI -->
                <div class="cards-grid">
                    <?php foreach ($daftar_siswa_kelas as $s): ?>
                        <div class="card-token">
                            <!-- Garis potong -->
                            <div class="cut-indicator">
                                <i class="fas fa-cut"></i> Gunting
                            </div>

                            <!-- Header Kartu -->
                            <div class="card-header-slip">
                                <?php if (file_exists('../uploads/logo_utama.png')): ?>
                                    <img src="../uploads/logo_utama.png" alt="Logo" class="logo-slip">
                                <?php else: ?>
                                    <div class="logo-placeholder">
                                        <i class="fas fa-vote-yea"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="header-text">
                                    <div class="app-name">E-VOTING RESMI</div>
                                    <div class="school-name">SMK TARUNA KARYA MANDIRI</div>
                                </div>
                            </div>

                            <!-- Body Kartu: Nama, NIS, dan KELAS -->
                            <div class="card-body-slip">
                                <span class="info-value-name" title="<?= htmlspecialchars($s['nama_siswa']); ?>">
                                    <?= htmlspecialchars($s['nama_siswa']); ?>
                                </span>
                                <div class="info-row">
                                    <span class="info-label">NIS:</span>
                                    <span class="info-value-nis"><?= htmlspecialchars($s['nis']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Kelas:</span>
                                    <!-- PENAMBAHAN KELAS SECARA JELAS DAN CANTIK -->
                                    <span class="badge-kelas-slip"><?= htmlspecialchars($s['kelas']); ?></span>
                                </div>
                            </div>

                            <!-- Box PIN / Token -->
                            <div class="token-box">
                                <span class="token-label"><i class="fas fa-key me-1"></i> TOKEN / PIN AKSES</span>
                                <span class="token-value"><?= htmlspecialchars($s['pin']); ?></span>
                            </div>

                            <!-- Footer Kartu -->
                            <div class="card-footer-slip">
                                <span class="security-text">
                                    <i class="fas fa-shield-alt"></i> Rahasia & Sekali Pakai
                                </span>
                                <span class="barcode-sim">
                                    |||| | | ||| | ||
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pemisah Halaman Antar Kelas Saat Print -->
                <?php if ($idx_kelas < $total_kelas): ?>
                    <div class="page-break"></div>
                <?php endif; ?>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</body>
</html>
