<?php
// admin/index.php

session_start();
require '../config/koneksi.php';

// PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// LOGIKA ANALITIK
// A. Menghitung Total Pemilih (Siswa & Guru)
$stmt_total = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status_aktif = 1");
$total_pemilih = (int) $stmt_total->fetchColumn();

$stmt_guru = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status_aktif = 1 AND kelas = 'GURU'");
$total_guru = (int) $stmt_guru->fetchColumn();
$total_siswa = $total_pemilih - $total_guru;

// B. Menghitung Pemilih Sudah Memilih
$stmt_sudah = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status_pilih = 1 AND status_aktif = 1");
$siswa_sudah = (int) $stmt_sudah->fetchColumn();

// C. Menghitung Pemilih Belum Memilih
$stmt_belum = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status_pilih = 0 AND status_aktif = 1");
$siswa_belum = (int) $stmt_belum->fetchColumn();

// D. Menghitung Siswa Tanpa Eskul (Siswa Bodong, tidak termasuk Dewan Guru)
$stmt_bodong = $pdo->query("SELECT COUNT(*) FROM siswa WHERE id_siswa NOT IN (SELECT id_siswa FROM anggota_eskul) AND status_aktif = 1 AND kelas != 'GURU'");
$siswa_bodong = (int) $stmt_bodong->fetchColumn();

// FITUR REKAPAN: Detail data siswa tanpa eskul (tidak termasuk Dewan Guru)
$stmt_list_bodong = $pdo->query("
    SELECT nis, nama_siswa, kelas 
    FROM siswa 
    WHERE id_siswa NOT IN (SELECT id_siswa FROM anggota_eskul) 
    AND status_aktif = 1 
    AND kelas != 'GURU'
    ORDER BY kelas ASC, nama_siswa ASC
");
$list_siswa_bodong = $stmt_list_bodong->fetchAll();

// E. Data Rekap Anggota per Eskul
$stmt_eskul = $pdo->query("
    SELECT e.nama_eskul, e.status_pemilihan, COUNT(a.id_eskul) AS total_anggota 
    FROM eskul e 
    LEFT JOIN anggota_eskul a ON e.id_eskul = a.id_eskul 
    WHERE e.status_aktif = 1
    GROUP BY e.id_eskul
");
$rekap_eskul = $stmt_eskul->fetchAll();

// F. LOG VIEWER
$stmt_log = $pdo->query("SELECT a.username, l.aktivitas, l.waktu FROM log_aktivitas l JOIN admin a ON l.id_admin = a.id_admin ORDER BY l.waktu DESC LIMIT 50");
$data_log = $stmt_log->fetchAll();

// G. PROGRESS BAR PERCENTAGE
$persentase_pemilih = ($total_pemilih > 0) ? round(($siswa_sudah / $total_pemilih) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - E-Voting</title>
    
    <!-- CSS Bootstrap 5 & DataTables -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- NAVBAR ATAS -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        
        <!-- HEADER DASHBOARD -->
        <div class="top-header">
            <div>
                <h4>Ringkasan Sistem E-Voting</h4>
                <small class="text-muted">Pantau aktivitas pemilihan, status partisipasi pemilih, dan rekapan suara secara real-time</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="cetak_pin.php" target="_blank" class="btn btn-primary btn-pill">
                    <i class="fas fa-print"></i> Cetak Token Pemilih
                </a>
                <a href="live_count.php" class="btn btn-outline-primary btn-pill">
                    <i class="fas fa-chart-line text-warning"></i> Pantau Live Count
                </a>
            </div>
        </div>
        
        <!-- METRIC KPI CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card-stat bg-grad-primary">
                    <div>
                        <h5>Total Pemilih</h5>
                        <h2><?= number_format($total_pemilih); ?> <span class="fs-6 fw-normal opacity-75">Orang</span></h2>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-white border-opacity-25 small opacity-90">
                        <span><?= number_format($total_siswa); ?> Siswa &bull; <?= number_format($total_guru); ?> Guru</span>
                        <i class="fas fa-arrow-right"></i>
                    </div>
                    <i class="fas fa-users icon-bg"></i>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card-stat bg-grad-success">
                    <div>
                        <h5>Sudah Memilih</h5>
                        <h2><?= number_format($siswa_sudah); ?> <span class="fs-6 fw-normal opacity-75">Pemilih</span></h2>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-white border-opacity-25 small opacity-90">
                        <span>Partisipasi: <?= $persentase_pemilih; ?>%</span>
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <i class="fas fa-vote-yea icon-bg"></i>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card-stat bg-grad-warning">
                    <div>
                        <h5>Belum Memilih</h5>
                        <h2><?= number_format($siswa_belum); ?> <span class="fs-6 fw-normal opacity-75">Pemilih</span></h2>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-white border-opacity-25 small opacity-90">
                        <span>Sisa: <?= ($total_pemilih > 0) ? round(($siswa_belum / $total_pemilih) * 100, 1) : 0; ?>%</span>
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <i class="fas fa-user-clock icon-bg"></i>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card-stat bg-grad-danger">
                    <div>
                        <h5>Tanpa Eskul</h5>
                        <h2><?= number_format($siswa_bodong); ?> <span class="fs-6 fw-normal opacity-75">Siswa</span></h2>
                    </div>
                    <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top border-white border-opacity-25 small opacity-90">
                        <span>Khusus Siswa</span>
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <i class="fas fa-user-slash icon-bg"></i>
                </div>
            </div>
        </div>

        <!-- PROGRESS BAR PARTISIPASI -->
        <div class="card-modern mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold m-0 text-dark"><i class="fas fa-chart-line text-primary me-2"></i> Progress Partisipasi Suara</h5>
                    <small class="text-muted"><?= $siswa_sudah; ?> dari <?= $total_pemilih; ?> pemilih telah menyalurkan hak suara</small>
                </div>
                <div class="text-end">
                    <span class="fs-4 fw-bold text-success"><?= $persentase_pemilih; ?>%</span>
                </div>
            </div>
            <div class="progress rounded-pill shadow-sm" style="height: 22px; background-color: #e2e8f0;">
                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated fw-bold" role="progressbar" style="width: <?= $persentase_pemilih; ?>%;" aria-valuenow="<?= $persentase_pemilih; ?>" aria-valuemin="0" aria-valuemax="100">
                    <?= $persentase_pemilih >= 8 ? $persentase_pemilih . '%' : '' ?>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- REKAP ESKUL -->
            <div class="col-lg-7">
                <div class="card-modern h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h5 class="fw-bold m-0 text-dark">
                            <i class="fas fa-sitemap text-primary me-2"></i> Ekstrakurikuler & Anggota
                        </h5>
                        <a href="eskul.php" class="btn btn-sm btn-outline-primary btn-pill">
                            Kelola Eskul <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Nama Ekstrakurikuler</th>
                                    <th>Status Pemilihan</th>
                                    <th class="text-end">Jumlah Anggota</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($rekap_eskul) > 0): ?>
                                    <?php $no = 1; foreach ($rekap_eskul as $row): ?>
                                        <tr>
                                            <td class="text-muted fw-bold"><?= $no++; ?></td>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_eskul']); ?></td>
                                            <td>
                                                <?php if ($row['status_pemilihan'] == 1): ?>
                                                    <span class="badge badge-soft-success"><i class="fas fa-door-open me-1"></i> Dibuka</span>
                                                <?php else: ?>
                                                    <span class="badge badge-soft-secondary"><i class="fas fa-lock me-1"></i> Ditutup</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge badge-soft-primary px-3 py-1 font-monospace fs-6">
                                                    <?= $row['total_anggota']; ?> Siswa
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="fas fa-folder-open fs-2 d-block mb-2 opacity-50"></i>
                                            Belum ada data ekstrakurikuler.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SISWA TANPA ESKUL WIDGET -->
            <div class="col-lg-5">
                <div class="card-modern h-100 d-flex flex-column justify-content-between" style="border-left: 5px solid #ef4444;">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <h5 class="fw-bold m-0 text-danger">
                                <i class="fas fa-user-slash me-2"></i> Siswa Tanpa Eskul
                            </h5>
                            <span class="badge badge-soft-danger"><?= $siswa_bodong; ?> Data</span>
                        </div>
                        <p class="text-muted small">
                            Siswa aktif yang belum dimasukkan ke dalam daftar anggota ekstrakurikuler mana pun di sistem.
                        </p>
                        <div class="p-3 bg-light rounded-3 text-center my-3">
                            <div class="display-4 fw-bold text-danger"><?= $siswa_bodong; ?></div>
                            <span class="text-muted small fw-medium">Siswa belum memiliki eskul</span>
                        </div>
                    </div>
                    <div>
                        <?php if ($siswa_bodong > 0): ?>
                            <a href="cetak_siswa_bodong.php" target="_blank" class="btn btn-danger w-100 btn-pill mb-2">
                                <i class="fas fa-print me-1"></i> Cetak Dokumen Resmi (A4)
                            </a>
                            <button type="button" class="btn btn-outline-danger w-100 btn-pill" data-bs-toggle="modal" data-bs-target="#modalSiswaBodong">
                                <i class="fas fa-table me-1"></i> Lihat Data & Ekspor
                            </button>
                        <?php else: ?>
                            <div class="text-success small text-center fw-semibold py-2">
                                <i class="fas fa-check-circle me-1"></i> Semua siswa sudah terdaftar pada ekstrakurikuler!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- LOG AKTIVITAS -->
        <div class="card-modern mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <h5 class="fw-bold m-0 text-dark">
                    <i class="fas fa-history text-primary me-2"></i> Log Aktivitas Sistem
                </h5>
                <small class="text-muted">Menampilkan 50 riwayat aktivitas terakhir</small>
            </div>
            <div class="table-responsive">
                <table id="tabelLog" class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th style="width: 60px;">No</th>
                            <th style="width: 180px;">Waktu Kejadian</th>
                            <th style="width: 150px;">Admin / User</th>
                            <th>Deskripsi Aktivitas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($data_log) > 0): ?>
                            <?php $no = 1; foreach ($data_log as $log): ?>
                                <tr>
                                    <td class="text-muted fw-bold"><?= $no++; ?></td>
                                    <td class="font-monospace small text-muted"><?= htmlspecialchars($log['waktu']); ?></td>
                                    <td>
                                        <span class="badge badge-soft-primary">
                                            <i class="fas fa-user-shield me-1"></i> <?= htmlspecialchars($log['username']); ?>
                                        </span>
                                    </td>
                                    <td class="fw-medium text-dark"><?= htmlspecialchars($log['aktivitas']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- MODAL REKAPAN SISWA TANPA ESKUL -->
    <div class="modal fade" id="modalSiswaBodong" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-clipboard-list me-2"></i> Rekapan Siswa Tanpa Ekstrakurikuler
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex justify-content-between align-items-center p-3 mb-3 bg-light rounded-3 border">
                        <div>
                            <div class="fw-bold text-dark">Format Cetak Standar Kesiswaan</div>
                            <small class="text-muted">Gunakan lembar cetak A4 resmi lengkap dengan kop sekolah, nomor, dan kolom penugasan.</small>
                        </div>
                        <a href="cetak_siswa_bodong.php" target="_blank" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold text-nowrap">
                            <i class="fas fa-print me-1"></i> Buka Format Resmi
                        </a>
                    </div>
                    <table id="tabelBodong" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th style="width: 60px;">No</th>
                                <th>NIS</th>
                                <th>Nama Lengkap</th>
                                <th>Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($list_siswa_bodong) > 0): ?>
                                <?php $no = 1; foreach ($list_siswa_bodong as $s): ?>
                                    <tr>
                                        <td class="text-muted"><?= $no++; ?></td>
                                        <td class="fw-bold font-monospace text-primary"><?= htmlspecialchars($s['nis']); ?></td>
                                        <td class="fw-medium"><?= htmlspecialchars($s['nama_siswa']); ?></td>
                                        <td><span class="badge badge-soft-secondary font-monospace"><?= htmlspecialchars($s['kelas']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-pill px-4" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT WAJIB DATATABLES & BOOTSTRAP -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#tabelLog').DataTable({
                "language": {
                    "search": "Cari Log:",
                    "lengthMenu": "Tampilkan _MENU_",
                    "info": "Menampilkan _START_ s/d _END_ dari _TOTAL_ log",
                    "infoEmpty": "Total 0 log",
                    "emptyTable": "Belum ada log aktivitas tercatat.",
                    "zeroRecords": "Tidak ditemukan data yang cocok",
                    "paginate": { "next": "Lanjut", "previous": "Sebelum" }
                },
                "pageLength": 10,
                "ordering": true,
                "order": [[ 1, "desc" ]]
            });

            var table = $('#tabelBodong').DataTable({
                "language": {
                    "search": "Cari:",
                    "lengthMenu": "Tampilkan _MENU_",
                    "info": "Total _TOTAL_ siswa",
                    "paginate": { "next": "Lanjut", "previous": "Sebelum" }
                },
                "pageLength": 10, 
                "ordering": true,
                "dom": "<'row mb-2'<'col-sm-6'l><'col-sm-6 text-end'B>>" +
                       "<'row'<'col-sm-12'tr>>" +
                       "<'row mt-2'<'col-sm-5'i><'col-sm-7'p>>",
                "buttons": [
                    {
                        extend: 'excelHtml5',
                        className: 'btn btn-success btn-sm btn-pill me-1',
                        text: '<i class="fas fa-file-excel me-1"></i> Excel',
                        title: 'REKAPITULASI SISWA TANPA EKSTRAKURIKULER'
                    },
                    {
                        extend: 'print',
                        className: 'btn btn-danger btn-sm btn-pill',
                        text: '<i class="fas fa-print me-1"></i> Cetak Tabel',
                        title: '',
                        customize: function (win) {
                            $(win.document.body).css('font-family', 'Arial, sans-serif').css('padding', '20px');
                            $(win.document.body).prepend(
                                '<div style="text-align:center; margin-bottom:20px; border-bottom: 2px solid #000; padding-bottom: 10px;">' +
                                '<h3 style="margin:0; font-weight:bold; text-transform:uppercase;">SMK TARUNA KARYA MANDIRI</h3>' +
                                '<h4 style="margin:4px 0; font-size:16px;">REKAPITULASI SISWA BELUM MEMILIKI EKSTRAKURIKULER</h4>' +
                                '<p style="margin:0; font-size:12px; color:#555;">Dokumen Internal Penugasan Organisasi Siswa</p>' +
                                '</div>'
                            );
                            $(win.document.body).find('table')
                                .css('width', '100%')
                                .css('border-collapse', 'collapse')
                                .css('margin-top', '15px');
                            $(win.document.body).find('th, td')
                                .css('border', '1px solid #000')
                                .css('padding', '6px 8px');
                            $(win.document.body).find('th')
                                .css('background-color', '#f1f5f9')
                                .css('text-align', 'center');
                        }
                    }
                ]
            });

            $('#modalSiswaBodong').on('shown.bs.modal', function () {
                table.columns.adjust();
            });
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>
