<?php
// admin/index.php

// 1. Memulai sesi dan memanggil koneksi
session_start();
require '../config/koneksi.php';

// 2. PENGAMANAN HALAMAN
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// 3. LOGIKA ANALITIK (Mengambil data dari database)

// A. Menghitung Total Siswa
$stmt_total = $pdo->query("SELECT COUNT(*) FROM siswa");
$total_siswa = $stmt_total->fetchColumn();

// B. Menghitung Siswa Sudah Memilih
$stmt_sudah = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status_pilih = 1");
$siswa_sudah = $stmt_sudah->fetchColumn();

// C. Menghitung Siswa Belum Memilih
$stmt_belum = $pdo->query("SELECT COUNT(*) FROM siswa WHERE status_pilih = 0");
$siswa_belum = $stmt_belum->fetchColumn();

// D. Menghitung Siswa Tanpa Eskul (Siswa Bodong)
$stmt_bodong = $pdo->query("SELECT COUNT(*) FROM siswa WHERE id_siswa NOT IN (SELECT id_siswa FROM anggota_eskul)");
$siswa_bodong = $stmt_bodong->fetchColumn();

// FITUR REKAPAN: Mengambil detail data siswa tanpa eskul
$stmt_list_bodong = $pdo->query("
    SELECT nis, nama_siswa, kelas 
    FROM siswa 
    WHERE id_siswa NOT IN (SELECT id_siswa FROM anggota_eskul) 
    AND status_aktif = 1 
    ORDER BY kelas ASC, nama_siswa ASC
");
$list_siswa_bodong = $stmt_list_bodong->fetchAll();

// E. Mengambil Data Rekap Anggota per Eskul
$stmt_eskul = $pdo->query("
    SELECT e.nama_eskul, COUNT(a.id_eskul) AS total_anggota 
    FROM eskul e 
    LEFT JOIN anggota_eskul a ON e.id_eskul = a.id_eskul 
    GROUP BY e.id_eskul
");
$rekap_eskul = $stmt_eskul->fetchAll();

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
    <!-- CSS untuk Tombol Export DataTables -->
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Google Fonts & FontAwesome -->
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
        
        .card-stat { border-radius: 15px; color: white; padding: 25px; box-shadow: 0 10px 20px rgba(0,0,0,0.08); position: relative; overflow: hidden; transition: transform 0.3s ease, box-shadow 0.3s ease; border: none; }
        .card-stat:hover { transform: translateY(-7px); box-shadow: 0 15px 25px rgba(0,0,0,0.15); }
        .card-stat h5 { font-weight: 500; font-size: 1.1rem; opacity: 0.9; z-index: 2; position: relative; }
        .card-stat h2 { font-weight: 700; font-size: 2.2rem; margin-top: 10px; z-index: 2; position: relative; }
        .card-stat .icon-bg { position: absolute; right: -10px; bottom: -20px; font-size: 7rem; opacity: 0.2; z-index: 1; }

        .bg-grad-primary { background: linear-gradient(135deg, #4e54c8, #8f94fb); }
        .bg-grad-success { background: linear-gradient(135deg, #11998e, #38ef7d); }
        .bg-grad-warning { background: linear-gradient(135deg, #f2994a, #f2c94c); color: #333 !important; }
        .bg-grad-warning h5, .bg-grad-warning h2, .bg-grad-warning .icon-bg { color: #333; opacity: 0.7; }

        .table-container { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); border: none; }
        
        .dataTables_wrapper .row { margin-bottom: 10px; }
        .dataTables_filter input { border-radius: 6px; border: 1px solid #dee2e6; padding: 4px 10px; }
        .dt-buttons .btn { margin-right: 5px; margin-bottom: 10px; } 
    </style>
</head>
<body>

    <!-- MEMANGGIL SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- KONTEN UTAMA -->
    <div class="content">
        
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Ringkasan Sistem</h4>
                <small class="text-muted">Pantau aktivitas pemilihan secara real-time</small>
            </div>
            <div>
                <span class="badge bg-primary p-2 fs-6 rounded-pill">
                    <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin'); ?>
                </span>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card-stat bg-grad-primary">
                    <h5>Total Pemilih</h5>
                    <h2><?= $total_siswa; ?> <span class="fs-5 fw-normal">Siswa</span></h2>
                    <i class="fas fa-users icon-bg"></i>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card-stat bg-grad-success">
                    <h5>Sudah Memilih</h5>
                    <h2><?= $siswa_sudah; ?> <span class="fs-5 fw-normal">Siswa</span></h2>
                    <i class="fas fa-check-circle icon-bg"></i>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card-stat bg-grad-warning">
                    <h5>Belum Memilih</h5>
                    <h2><?= $siswa_belum; ?> <span class="fs-5 fw-normal">Siswa</span></h2>
                    <i class="fas fa-hourglass-half icon-bg"></i>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="table-container h-100">
                    <h5 class="fw-bold mb-4" style="color: #2c3e50;"><i class="fas fa-list-alt me-2 text-primary"></i> Data Anggota Ekstrakurikuler</h5>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="border-bottom">
                                <tr>
                                    <th>No</th>
                                    <th>Nama Ekstrakurikuler</th>
                                    <th class="text-end">Jumlah Anggota</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($rekap_eskul) > 0): ?>
                                    <?php $no = 1; foreach ($rekap_eskul as $row): ?>
                                        <tr class="border-bottom">
                                            <td><span class="text-muted fw-bold"><?= $no++; ?></span></td>
                                            <td class="fw-medium"><?= htmlspecialchars($row['nama_eskul']); ?></td>
                                            <td class="text-end">
                                                <span class="badge bg-light text-dark border px-3 py-2">
                                                    <?= $row['total_anggota']; ?> Siswa
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">
                                            <i class="fas fa-folder-open fs-1 d-block mb-2 opacity-50"></i>
                                            Belum ada data ekstrakurikuler.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="table-container border-start border-danger border-5 h-100 d-flex flex-column justify-content-center" style="background-color: #fffafb;">
                    <div class="text-center py-4">
                        <div class="mb-3">
                            <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3.5rem;"></i>
                        </div>
                        <h5 class="fw-bold text-danger">Siswa Tanpa Eskul</h5>
                        <h1 class="display-3 fw-bold text-danger my-3"><?= $siswa_bodong; ?></h1>
                        <p class="text-muted small px-3 mb-4">
                            Jumlah siswa yang belum ditugaskan ke ekstrakurikuler mana pun di dalam sistem.
                        </p>
                        <?php if ($siswa_bodong > 0): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalSiswaBodong">
                                <i class="fas fa-search me-1"></i> Lihat Data Rekapan
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- MODAL REKAPAN SISWA TANPA ESKUL -->
    <div class="modal fade" id="modalSiswaBodong" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-clipboard-list me-2"></i> Rekapan Siswa Tanpa Eskul
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <table id="tabelBodong" class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th width="10%">No</th>
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
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($s['nis']); ?></td>
                                        <td class="fw-medium"><?= htmlspecialchars($s['nama_siswa']); ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($s['kelas']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup Jendela</button>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT WAJIB UNTUK DATATABLES & BOOTSTRAP -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- SCRIPT TAMBAHAN UNTUK EXPORT (EXCEL & PDF) -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    
    <script>
        $(document).ready(function() {
            var table = $('#tabelBodong').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                },
                "pageLength": 10, 
                "ordering": true,
                "dom": "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                       "<'row'<'col-sm-12 mt-2 mb-3 text-center'B>>" +
                       "<'row'<'col-sm-12'tr>>" +
                       "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                "buttons": [
                    {
                        extend: 'excelHtml5',
                        className: 'btn btn-success btn-sm fw-bold px-3',
                        text: '<i class="fas fa-file-excel me-1"></i> Export Excel',
                        title: 'REKAPITULASI SISWA TANPA EKSTRAKURIKULER',
                        messageTop: 'Data otomatis digenerate dari Sistem E-Voting',
                        exportOptions: { columns: [0, 1, 2, 3] }
                    },
                    {
                        extend: 'pdfHtml5',
                        className: 'btn btn-danger btn-sm fw-bold px-3',
                        text: '<i class="fas fa-file-pdf me-1"></i> Export PDF',
                        title: 'REKAPITULASI SISWA TANPA EKSTRAKURIKULER',
                        pageSize: 'A4',
                        exportOptions: { columns: [0, 1, 2, 3] },
                        // Modifikasi khusus tampilan PDF agar elegan
                        customize: function (doc) {
                            // Mengatur margin dan alignment teks
                            doc.content[0].alignment = 'center';
                            doc.content[0].margin = [0, 0, 0, 15];
                            
                            // Mengatur proporsi lebar tabel
                            doc.content[1].table.widths = ['10%', '25%', '40%', '25%'];
                            
                            // Mewarnai judul kolom (Header)
                            doc.styles.tableHeader.fillColor = '#1a2980';
                            doc.styles.tableHeader.color = 'white';
                            doc.styles.tableHeader.alignment = 'center';
                            
                            // Merapikan isi teks agar rata kiri
                            doc.defaultStyle.fontSize = 11;
                            doc.styles.tableBodyEven.alignment = 'left';
                            doc.styles.tableBodyOdd.alignment = 'left';
                        }
                    },
                    {
                        extend: 'print',
                        className: 'btn btn-secondary btn-sm fw-bold px-3',
                        text: '<i class="fas fa-print me-1"></i> Cetak Langsung',
                        title: '', // Dikosongkan karena kita akan membuat judul buatan (custom)
                        exportOptions: { columns: [0, 1, 2, 3] },
                        // Modifikasi khusus untuk jendela Print agar mengikuti format kertas yang rapi
                        customize: function (win) {
                            $(win.document.body).css('font-family', 'Arial, sans-serif');
                            
                            // Menyuntikkan judul yang lebih rapi
                            $(win.document.body).prepend(
                                '<div style="text-align:center; margin-bottom:20px;">' +
                                '<h2 style="margin:0; font-weight:bold;">REKAPITULASI DATA SISWA</h2>' +
                                '<p style="margin:5px 0 0 0; font-size:14px; color:#555;">Siswa Tanpa Ekstrakurikuler - Sistem E-Voting</p>' +
                                '<hr style="border-top:2px solid #333;">' +
                                '</div>'
                            );
                            
                            // Menyuntikkan kode CSS untuk merapikan border dan background tabel
                            $(win.document.body).find('table')
                                .css('width', '100%')
                                .css('border-collapse', 'collapse')
                                .css('margin-top', '15px');
                            $(win.document.body).find('th, td')
                                .css('border', '1px solid #000')
                                .css('padding', '10px');
                            $(win.document.body).find('th')
                                .css('background-color', '#f4f7fa')
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
</body>
</html>