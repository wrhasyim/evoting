<?php
// admin/cetak_rekap_pkl.php
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
$nama_periode_aktif = $periode_aktif['nama_periode'] ?? '2026/2027';

// 2. Filter Kelas
$filter_kelas = isset($_GET['kelas']) ? trim($_GET['kelas']) : 'all';

// 3. Ambil Daftar Kelas Khusus Siswa PKL
$stmt_kelas_pkl = $pdo->query("SELECT DISTINCT kelas FROM siswa WHERE status_aktif = 1 AND status_pkl = 1 ORDER BY kelas ASC");
$daftar_kelas_pkl = $stmt_kelas_pkl->fetchAll(PDO::FETCH_COLUMN);

// 4. Query Data Siswa PKL
$sql = "SELECT id_siswa, nis, nama_siswa, kelas, pin, tempat_pkl, periode_pkl, no_hp, status_pilih 
        FROM siswa 
        WHERE status_aktif = 1 AND status_pkl = 1";
$params = [];

if ($filter_kelas !== 'all' && $filter_kelas !== '') {
    $sql .= " AND kelas = ?";
    $params[] = $filter_kelas;
}
$sql .= " ORDER BY kelas ASC, nama_siswa ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data_pkl = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total keseluruhan
$total_pkl = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status_aktif = 1 AND status_pkl = 1")->fetchColumn();
$total_xi = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status_aktif = 1 AND status_pkl = 1 AND kelas LIKE 'XI %'")->fetchColumn();
$total_xii = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status_aktif = 1 AND status_pkl = 1 AND kelas LIKE 'XII %'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Token Siswa PKL - E-Voting SMK TKM</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
        }

        /* TOOLBAR ATAS (Hanya tampil di layar) */
        .toolbar-top {
            background: #ffffff;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 24px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .badge-pill-custom {
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .paper-container {
            max-width: 1200px;
            margin: 24px auto;
            background: #ffffff;
            padding: 32px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }

        /* KOP SURAT RESMI */
        .kop-surat {
            display: flex;
            align-items: center;
            gap: 18px;
            border-bottom: 3px double #0f172a;
            padding-bottom: 14px;
            margin-bottom: 20px;
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

        .kop-text h4 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: #475569;
            text-transform: uppercase;
        }

        .kop-text h2 {
            margin: 2px 0;
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
        }

        .kop-text h3 {
            margin: 2px 0;
            font-size: 1.15rem;
            font-weight: 800;
            color: #2563eb;
            text-transform: uppercase;
        }

        .kop-text p {
            margin: 2px 0 0;
            font-size: 0.85rem;
            color: #64748b;
        }

        .token-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.05rem;
            font-weight: 800;
            color: #dc2626;
            background: #fef2f2;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #fecaca;
            display: inline-block;
            letter-spacing: 1.5px;
        }

        .nis-badge {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            font-size: 0.88rem;
            color: #1d4ed8;
            background: #eff6ff;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #bfdbfe;
        }

        .table-pkl {
            font-size: 0.86rem;
            vertical-align: middle;
        }

        .table-pkl thead th {
            background-color: #1e293b !important;
            color: #ffffff !important;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.5px;
            padding: 9px 8px;
            border: 1px solid #0f172a;
        }

        .table-pkl tbody td {
            padding: 8px 8px;
            border: 1px solid #cbd5e1;
        }

        .btn-copy-wa {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 6px;
        }

        /* CETAK PRINT STYLES */
        @media print {
            .toolbar-top, .btn-copy-wa, .screen-only {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .paper-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }
            .token-badge {
                color: #b91c1c !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .table-pkl thead th {
                background-color: #1e293b !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            @page {
                size: A4 portrait;
                margin: 10mm 10mm 10mm 10mm;
            }
        }
    </style>
</head>
<body>

    <!-- TOOLBAR ATAS (SCREEN ONLY) -->
    <div class="toolbar-top screen-only">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h5 class="m-0 fw-bold text-dark">
                <i class="fas fa-briefcase text-warning me-2"></i>Token Siswa Sedang PKL
            </h5>
            <span class="badge-pill-custom bg-primary text-white">
                <i class="fas fa-users me-1"></i> Total: <?= $total_pkl; ?> Siswa
            </span>
            <span class="badge-pill-custom bg-info text-dark">
                Kelas XI: <?= $total_xi; ?> &bull; Kelas XII: <?= $total_xii; ?>
            </span>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Filter Kelas -->
            <form method="GET" action="" class="d-flex align-items-center gap-1 m-0">
                <select name="kelas" class="form-select form-select-sm fw-bold border-primary" onchange="this.form.submit()" style="min-width: 180px;">
                    <option value="all" <?= $filter_kelas === 'all' ? 'selected' : ''; ?>>-- Tampilkan Semua Kelas (<?= $total_pkl; ?>) --</option>
                    <?php foreach ($daftar_kelas_pkl as $kls): ?>
                        <option value="<?= htmlspecialchars($kls); ?>" <?= $filter_kelas === $kls ? 'selected' : ''; ?>>
                            Kelas: <?= htmlspecialchars($kls); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Tombol Cetak / Simpan PDF -->
            <button class="btn btn-sm btn-primary fw-bold" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Cetak Tabel
            </button>

            <!-- Tombol Cetak Kartu Slip PIN -->
            <a href="cetak_pin.php?kelas=pkl" target="_blank" class="btn btn-sm btn-warning text-dark fw-bold">
                <i class="fas fa-id-card me-1"></i> Cetak Kartu Slip (F4/A4)
            </a>

            <!-- Tombol Download PDF Resmi -->
            <a href="../DAFTAR_TOKEN_SISWA_PKL.pdf" target="_blank" class="btn btn-sm btn-outline-danger fw-bold">
                <i class="fas fa-file-pdf me-1"></i> Unduh PDF Resmi
            </a>

            <!-- Tombol Kembali -->
            <a href="siswa.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <!-- WADAH DOKUMEN CETAK -->
    <div class="paper-container">
        
        <!-- KOP RESMI -->
        <div class="kop-surat">
            <?php if (file_exists('../uploads/logo_utama.png')): ?>
                <img src="../uploads/logo_utama.png" alt="Logo" class="kop-logo">
            <?php else: ?>
                <div class="bg-primary text-white rounded-3 p-3 d-flex align-items-center justify-content-center" style="width:70px; height:70px;">
                    <i class="fas fa-vote-yea fs-2"></i>
                </div>
            <?php endif; ?>
            <div class="kop-text">
                <h4>PANITIA PEMILIHAN KETUA OSIS & EKSTRAKURIKULER</h4>
                <h2>SMK TARUNA KARYA MANDIRI</h2>
                <h3>DAFTAR AKUN & TOKEN PEMILIH SISWA SEDANG PROSES PKL</h3>
                <p>
                    Tahun Ajaran <?= htmlspecialchars($nama_periode_aktif); ?> &bull; 
                    Kategori: <b>Siswa Berada di Luar Sekolah (Praktik Kerja Lapangan)</b> &bull; Total: <b><?= count($data_pkl); ?> Siswa</b>
                </p>
            </div>
        </div>

        <!-- INFO NOTIS UNTUK PANITIA -->
        <div class="alert alert-warning py-2 px-3 small border-0 rounded-3 mb-3 screen-only d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-info-circle me-1"></i>
                <b>Panduan Panitia:</b> Siswa di bawah ini sedang melaksanakan PKL di perusahaan/instansi luar sekolah. Panitia atau Wali Kelas dapat mengirimkan <b>NIS</b> dan <b>Token</b> melalui pesan WhatsApp menggunakan tombol <b>Salin Pesan WA</b> di kolom paling kanan.
            </div>
            <a href="cetak_pin.php?kelas=pkl" target="_blank" class="btn btn-sm btn-dark text-nowrap ms-2">
                <i class="fas fa-ticket-alt me-1"></i> Cetak Kartu Slip
            </a>
        </div>

        <!-- TABEL DATA SISWA PKL -->
        <div class="table-responsive">
            <table class="table table-bordered table-pkl align-middle w-100">
                <thead>
                    <tr>
                        <th style="width: 4%;" class="text-center">No</th>
                        <th style="width: 11%;" class="text-center">NIS</th>
                        <th style="width: 22%;">Nama Lengkap Siswa</th>
                        <th style="width: 10%;" class="text-center">Kelas</th>
                        <th style="width: 21%;">Perusahaan / Instansi PKL</th>
                        <th style="width: 12%;" class="text-center">Token / PIN</th>
                        <th style="width: 10%;" class="text-center">No. HP / WA</th>
                        <th style="width: 10%;" class="text-center screen-only">Kirim WA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($data_pkl) === 0): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Tidak ada data siswa PKL untuk filter ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($data_pkl as $row): ?>
                            <tr>
                                <td class="text-center fw-bold"><?= $no; ?></td>
                                <td class="text-center"><span class="nis-badge"><?= htmlspecialchars($row['nis']); ?></span></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['nama_siswa']); ?></div>
                                    <?php if (!empty($row['periode_pkl']) && $row['periode_pkl'] !== '-'): ?>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">Periode: <?= htmlspecialchars($row['periode_pkl']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['kelas']); ?></span>
                                </td>
                                <td>
                                    <i class="fas fa-building text-secondary me-1"></i>
                                    <b><?= htmlspecialchars($row['tempat_pkl'] ?: '-'); ?></b>
                                </td>
                                <td class="text-center">
                                    <span class="token-badge"><?= htmlspecialchars($row['pin']); ?></span>
                                </td>
                                <td class="text-center font-monospace" style="font-size: 0.78rem;">
                                    <?= htmlspecialchars($row['no_hp'] ?: '-'); ?>
                                </td>
                                <td class="text-center screen-only">
                                    <?php 
                                        $pesan_wa = "Halo {$row['nama_siswa']} ({$row['kelas']}), ini Token Hak Suara resmi kamu untuk Pemilihan E-Voting SMK Taruna Karya Mandiri:\n\n"
                                                  . "🔑 NIS: {$row['nis']}\n"
                                                  . "🎟️ Token: {$row['pin']}\n\n"
                                                  . "Silakan coblos melalui bilik suara online sekolah. Hak suara bersifat LUBER JURDIL.";
                                        $encoded_wa = rawurlencode($pesan_wa);
                                        $hp_clean = preg_replace('/[^0-9]/', '', $row['no_hp'] ?? '');
                                        if (substr($hp_clean, 0, 1) === '0') {
                                            $hp_clean = '62' . substr($hp_clean, 1);
                                        }
                                    ?>
                                    <button type="button" class="btn btn-sm btn-outline-success btn-copy-wa" 
                                            onclick="salinPesanWA(`<?= addslashes($pesan_wa); ?>`, this)" 
                                            title="Salin teks pesan WhatsApp">
                                        <i class="fas fa-copy me-1"></i> Salin
                                    </button>
                                    <?php if (!empty($hp_clean)): ?>
                                        <a href="https://wa.me/<?= $hp_clean; ?>?text=<?= $encoded_wa; ?>" target="_blank" class="btn btn-sm btn-success btn-copy-wa" title="Buka WhatsApp Langsung">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php $no++; endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- TANDA TANGAN PENGESAHAN (HANYA MUNCUL DI PRINT) -->
        <div class="row mt-5 pt-3" style="page-break-inside: avoid;">
            <div class="col-6 text-center">
                <small class="text-muted d-block mb-1">Mengetahui,</small>
                <b>Koordinator Pokja PKL / Hubin</b>
                <div style="height: 60px;"></div>
                <div class="fw-bold" style="border-bottom: 1px solid #000; display: inline-block; min-width: 180px;">
                    ( .................................................... )
                </div>
            </div>
            <div class="col-6 text-center">
                <small class="text-muted d-block mb-1">Purwakarta, <?= date('d F Y'); ?></small>
                <b>Ketua Panitia Pemilihan E-Voting</b>
                <div style="height: 60px;"></div>
                <div class="fw-bold" style="border-bottom: 1px solid #000; display: inline-block; min-width: 180px;">
                    ( .................................................... )
                </div>
            </div>
        </div>

    </div>

    <script>
        function salinPesanWA(pesan, btn) {
            navigator.clipboard.writeText(pesan).then(() => {
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check me-1"></i> Tersalin!';
                btn.classList.remove('btn-outline-success');
                btn.classList.add('btn-success');
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-success');
                }, 2000);
            }).catch(err => {
                alert("Gagal menyalin teks: " + err);
            });
        }
    </script>
</body>
</html>
