<?php
// admin/live_count.php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Mengambil daftar eskul untuk dropdown (pilihan filter)
$stmt_eskul = $pdo->query("SELECT id_eskul, nama_eskul FROM eskul WHERE status_aktif = 1 ORDER BY nama_eskul ASC");
$daftar_eskul = $stmt_eskul->fetchAll();
$id_eskul_pilih = isset($_GET['id_eskul']) ? $_GET['id_eskul'] : (count($daftar_eskul) > 0 ? $daftar_eskul[0]['id_eskul'] : null);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Count Suara Real-Time - E-Voting</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .foto-kandidat-live {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }
        .progress-live {
            height: 26px;
            border-radius: 9999px;
            background-color: #f1f5f9;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }
        .progress-live .progress-bar {
            font-size: 0.88rem;
            font-weight: 800;
            line-height: 26px;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 700;
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS -->
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="top-header">
            <div>
                <h4>Pemantauan Hasil Pemilihan (Live Count)</h4>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="live-indicator">
                        <span class="pulse-dot"></span>
                        <span>SERVER LIVE STREAMING AKTIF</span>
                    </span>
                    <small class="text-muted">&bull; Sinkronisasi otomatis setiap 3 detik</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if ($id_eskul_pilih): ?>
                    <a href="export_hasil.php?id_eskul=<?= $id_eskul_pilih; ?>" class="btn btn-success">
                        <i class="fas fa-file-excel me-1"></i> Ekspor ke Excel
                    </a>
                <?php endif; ?>
                <div class="badge badge-soft-primary px-3 py-2 fs-6">
                    <i class="fas fa-envelope-open-text me-1"></i> Total Suara: <span id="angka-total-suara" class="fw-bold font-monospace">0</span>
                </div>
            </div>
        </div>

        <?php if (count($daftar_eskul) == 0): ?>
            <div class="alert alert-warning py-3 rounded-3 shadow-sm">Belum ada ekstrakurikuler yang didaftarkan.</div>
        <?php else: ?>
            <div class="card-modern mb-4">
                <form method="GET" action="" class="p-3 bg-light rounded-3 border">
                    <div class="row align-items-center g-2">
                        <div class="col-md-3">
                            <label class="fw-bold text-dark small m-0"><i class="fas fa-filter text-primary me-1"></i> Pantau Pemilihan Untuk:</label>
                        </div>
                        <div class="col-md-9">
                            <select name="id_eskul" id="pilih-eskul" class="form-select border-primary" onchange="this.form.submit()">
                                <?php foreach ($daftar_eskul as $e): ?>
                                    <option value="<?= $e['id_eskul']; ?>" <?= ($e['id_eskul'] == $id_eskul_pilih) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($e['nama_eskul']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-modern">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h5 class="fw-bold m-0 text-dark">
                        <i class="fas fa-chart-bar text-primary me-2"></i> Grafik Perolehan Suara Kandidat
                    </h5>
                    <small class="text-muted">Data diperbarui secara real-time dari database</small>
                </div>
                
                <!-- Wadah grafik real-time -->
                <div id="wadah-grafik">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-spinner fa-spin fs-1 text-primary mb-3"></i>
                        <h6 class="fw-bold">Menghubungkan ke Saluran Live Count...</h6>
                        <small>Memuat perolehan suara terkini</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- SCRIPT AJAX REAL-TIME -->
    <script>
        const idEskulAktif = <?= json_encode($id_eskul_pilih); ?>;
        const warnaGradients = [
            'bg-grad-primary',
            'bg-grad-success',
            'bg-grad-warning',
            'bg-grad-info',
            'bg-grad-danger'
        ];

        function muatDataRealtime() {
            if (!idEskulAktif) return;

            fetch(`api_live_count.php?id_eskul=${idEskulAktif}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error("Kesalahan API:", data.error);
                        return;
                    }

                    // Perbarui Angka Total Suara
                    const elTotal = document.getElementById('angka-total-suara');
                    if (elTotal) {
                        elTotal.innerText = data.total_suara.toLocaleString('id-ID');
                    }

                    // Perbarui Grafik
                    let htmlGrafik = '';
                    
                    if (!data.kandidat || data.kandidat.length === 0) {
                        htmlGrafik = `
                            <div class="alert alert-light border text-center py-5 rounded-4">
                                <i class="fas fa-poll-h fs-1 mb-3 text-secondary opacity-50"></i>
                                <h5 class="fw-bold text-dark">Belum Ada Kandidat atau Suara</h5>
                                <p class="text-muted small m-0">Kandidat belum ditambahkan atau belum ada suara yang masuk untuk kategori ini.</p>
                            </div>`;
                    } else {
                        // Tentukan suara tertinggi untuk memberi lencana pemimpin sementara
                        const maxSuara = Math.max(...data.kandidat.map(k => parseInt(k.perolehan || 0)));

                        data.kandidat.forEach((paslon, index) => {
                            let persentase = 0;
                            if (data.total_suara > 0) {
                                persentase = ((paslon.perolehan / data.total_suara) * 100).toFixed(1);
                            }

                            const isLeading = data.total_suara > 0 && parseInt(paslon.perolehan) === maxSuara && maxSuara > 0;
                            const warnaPilihan = warnaGradients[index % warnaGradients.length];

                            htmlGrafik += `
                                <div class="p-3 mb-3 rounded-3 border ${isLeading ? 'border-primary bg-light' : 'bg-white'} shadow-sm">
                                    <div class="row align-items-center g-3">
                                        <div class="col-auto text-center position-relative">
                                            <img src="../uploads/${paslon.foto}" alt="Foto" class="foto-kandidat-live" onerror="this.onerror=null; this.src='https://via.placeholder.com/150?text=Kandidat';">
                                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark" style="font-size: 0.75rem;">
                                                No. ${paslon.no_urut}
                                            </span>
                                        </div>
                                        <div class="col">
                                            <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                                                <div>
                                                    <span class="fw-bold fs-6 text-dark me-2">${paslon.nama_paslon}</span>
                                                    ${isLeading ? '<span class="badge badge-soft-success"><i class="fas fa-crown me-1 text-warning"></i> Unggul Sementara</span>' : ''}
                                                </div>
                                                <div class="text-end">
                                                    <span class="fs-5 fw-bold font-monospace text-primary">${paslon.perolehan.toLocaleString('id-ID')} Suara</span>
                                                    <span class="badge badge-soft-secondary ms-2">${persentase}%</span>
                                                </div>
                                            </div>
                                            <div class="progress progress-live mt-2">
                                                <div class="progress-bar ${warnaPilihan} progress-bar-striped progress-bar-animated text-white" role="progressbar" style="width: ${persentase}%;" aria-valuenow="${persentase}" aria-valuemin="0" aria-valuemax="100">
                                                    ${persentase >= 8 ? persentase + '%' : ''}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                    }

                    const wadah = document.getElementById('wadah-grafik');
                    if (wadah) {
                        wadah.innerHTML = htmlGrafik;
                    }
                })
                .catch(error => {
                    console.error("Gagal mengambil data real-time:", error);
                });
        }

        muatDataRealtime();
        setInterval(muatDataRealtime, 3000);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>
