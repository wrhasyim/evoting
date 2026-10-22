<?php
// admin/live_count.php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Mengambil daftar eskul untuk dropdown (HANYA yang memiliki kandidat aktif)
$stmt_eskul = $pdo->query("
    SELECT DISTINCT e.id_eskul, e.nama_eskul, e.status_pemilihan 
    FROM eskul e 
    JOIN kandidat k ON e.id_eskul = k.id_eskul 
    WHERE e.status_aktif = 1 AND k.status_aktif = 1 
    ORDER BY e.id_eskul ASC
");
$daftar_eskul = $stmt_eskul->fetchAll();

// Default: 'all' (Tampilkan semua eskul & kandidat sekaligus)
$id_eskul_pilih = isset($_GET['id_eskul']) ? $_GET['id_eskul'] : 'all';
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
            width: 76px;
            height: 76px;
            object-fit: cover;
            border-radius: 16px;
            border: 2px solid #e2e8f0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            background: #fff;
        }
        .progress-live {
            height: 28px;
            border-radius: 9999px;
            background-color: #f1f5f9;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        .progress-live .progress-bar {
            font-size: 0.88rem;
            font-weight: 800;
            line-height: 28px;
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1) !important;
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
        .card-eskul-live {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-eskul-live:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.07);
        }
        .card-eskul-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 12px;
        }
        .candidate-card-item {
            padding: 16px;
            border-radius: 14px;
            border: 1px solid #edf2f7;
            margin-bottom: 14px;
            background: #ffffff;
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .candidate-card-item.leading {
            border-color: #3b82f6;
            background: #f8faff;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08);
        }
    </style>
</head>
<body>

    <!-- NAVBAR & SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="top-header">
            <div>
                <h4>Pemantauan Hasil Pemilihan (Live Count)</h4>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="live-indicator">
                        <span class="pulse-dot"></span>
                        <span>SERVER LIVE COUNT AKTIF</span>
                    </span>
                    <small class="text-muted">&bull; Sinkronisasi otomatis setiap 3 detik (Smooth 60FPS)</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="export_hasil.php?id_eskul=<?= htmlspecialchars($id_eskul_pilih); ?>" class="btn btn-success">
                    <i class="fas fa-file-excel me-1"></i> Ekspor ke Excel
                </a>
                <div class="badge badge-soft-primary px-3 py-2 fs-6">
                    <i class="fas fa-envelope-open-text me-1"></i> Total Suara Masuk: <span id="angka-total-suara" class="fw-bold font-monospace">0</span>
                </div>
            </div>
        </div>

        <?php if (count($daftar_eskul) == 0): ?>
            <div class="alert alert-warning py-3 rounded-3 shadow-sm">Belum ada ekstrakurikuler yang didaftarkan.</div>
        <?php else: ?>
            <!-- FILTER PEMILIHAN -->
            <div class="card-modern mb-4">
                <form method="GET" action="" class="p-3 bg-light rounded-3 border">
                    <div class="row align-items-center g-2">
                        <div class="col-md-3">
                            <label class="fw-bold text-dark small m-0">
                                <i class="fas fa-filter text-primary me-1"></i> Tampilan Live Count:
                            </label>
                        </div>
                        <div class="col-md-9">
                            <select name="id_eskul" id="pilih-eskul" class="form-select border-primary fw-bold" onchange="this.form.submit()">
                                <option value="all" <?= ($id_eskul_pilih === 'all') ? 'selected' : ''; ?>>
                                    🌟 TAMPILKAN SEMUA KANDIDAT & ESKUL SEKALIGUS (REKAP LIVE)
                                </option>
                                <optgroup label="Pilih Kategori Spesifik:">
                                    <?php foreach ($daftar_eskul as $e): ?>
                                        <option value="<?= $e['id_eskul']; ?>" <?= ((string)$e['id_eskul'] === (string)$id_eskul_pilih) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($e['nama_eskul']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <!-- WADAH GRAFIK REAL-TIME -->
            <div id="wadah-semua-eskul">
                <div class="card-modern text-center py-5">
                    <i class="fas fa-spinner fa-spin fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark">Menghubungkan ke Saluran Live Count Real-Time...</h5>
                    <p class="text-muted small m-0">Sedang memuat data perolehan suara dari semua kandidat dan eskul</p>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- SCRIPT AJAX REAL-TIME (SMOOTH & FLICKER-FREE) -->
    <script>
        const idEskulAktif = <?= json_encode($id_eskul_pilih); ?>;
        const warnaGradients = [
            'bg-grad-primary',
            'bg-grad-success',
            'bg-grad-warning',
            'bg-grad-info',
            'bg-grad-danger'
        ];

        let domInitialized = false;
        let lastRenderMode = null;

        function renderCandidateItem(paslon, index, totalSuaraEskul, maxSuara) {
            let persentase = 0;
            if (totalSuaraEskul > 0) {
                persentase = ((paslon.perolehan / totalSuaraEskul) * 100).toFixed(1);
            }

            const isLeading = totalSuaraEskul > 0 && parseInt(paslon.perolehan) === maxSuara && maxSuara > 0;
            const warnaPilihan = warnaGradients[index % warnaGradients.length];
            const fotoSrc = paslon.foto ? `../uploads/${paslon.foto}` : 'https://via.placeholder.com/150?text=Foto';

            return `
                <div class="candidate-card-item ${isLeading ? 'leading' : ''}" id="card-candidate-${paslon.id_kandidat}">
                    <div class="row align-items-center g-3">
                        <div class="col-auto text-center position-relative">
                            <img src="${fotoSrc}" alt="Foto" class="foto-kandidat-live" onerror="this.onerror=null; this.src='https://via.placeholder.com/150?text=Foto';">
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark" style="font-size: 0.75rem;">
                                No. ${paslon.no_urut}
                            </span>
                        </div>
                        <div class="col">
                            <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                                <div>
                                    <span class="fw-bold fs-6 text-dark me-2">${paslon.nama_paslon}</span>
                                    <span class="badge badge-soft-secondary small font-monospace">${paslon.kelas_paslon || ''}</span>
                                    <span class="badge badge-soft-success ms-2 ${isLeading ? '' : 'd-none'}" id="leading-badge-${paslon.id_kandidat}">
                                        <i class="fas fa-crown me-1 text-warning"></i> Unggul Sementara
                                    </span>
                                </div>
                                <div class="text-end">
                                    <span class="fs-5 fw-bold font-monospace text-primary" id="suara-val-${paslon.id_kandidat}">
                                        ${parseInt(paslon.perolehan || 0).toLocaleString('id-ID')} Suara
                                    </span>
                                    <span class="badge badge-soft-secondary ms-2" id="pct-val-${paslon.id_kandidat}">${persentase}%</span>
                                </div>
                            </div>
                            <div class="progress progress-live mt-2">
                                <div class="progress-bar ${warnaPilihan} progress-bar-striped progress-bar-animated text-white" 
                                     id="bar-val-${paslon.id_kandidat}"
                                     role="progressbar" 
                                     style="width: ${persentase}%;" 
                                     aria-valuenow="${persentase}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    ${persentase >= 8 ? persentase + '%' : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // UPDATE ELEMENT SECARA IN-PLACE (MENCEGAH KREJET-KREJET / FLICKERING)
        function updateCandidateInPlace(paslon, totalSuaraEskul, maxSuara) {
            let persentase = 0;
            if (totalSuaraEskul > 0) {
                persentase = ((paslon.perolehan / totalSuaraEskul) * 100).toFixed(1);
            }
            const isLeading = totalSuaraEskul > 0 && parseInt(paslon.perolehan) === maxSuara && maxSuara > 0;
            const suaraFormatted = parseInt(paslon.perolehan || 0).toLocaleString('id-ID') + ' Suara';

            const card = document.getElementById(`card-candidate-${paslon.id_kandidat}`);
            const elSuara = document.getElementById(`suara-val-${paslon.id_kandidat}`);
            const elPct = document.getElementById(`pct-val-${paslon.id_kandidat}`);
            const elBar = document.getElementById(`bar-val-${paslon.id_kandidat}`);
            const elLeading = document.getElementById(`leading-badge-${paslon.id_kandidat}`);

            if (elSuara && elSuara.innerText !== suaraFormatted) {
                elSuara.innerText = suaraFormatted;
            }
            if (elPct && elPct.innerText !== (persentase + '%')) {
                elPct.innerText = persentase + '%';
            }
            if (elBar) {
                elBar.style.width = persentase + '%';
                elBar.setAttribute('aria-valuenow', persentase);
                elBar.innerText = persentase >= 8 ? (persentase + '%') : '';
            }
            if (card) {
                if (isLeading) {
                    card.classList.add('leading');
                } else {
                    card.classList.remove('leading');
                }
            }
            if (elLeading) {
                if (isLeading) {
                    elLeading.classList.remove('d-none');
                } else {
                    elLeading.classList.add('d-none');
                }
            }
        }

        function muatDataRealtime() {
            fetch(`api_live_count.php?id_eskul=${idEskulAktif}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error("Kesalahan API:", data.error);
                        return;
                    }

                    const elTotal = document.getElementById('angka-total-suara');
                    const wadah = document.getElementById('wadah-semua-eskul');
                    if (!wadah) return;

                    // Perbarui Angka Total Suara Keseluruhan
                    const totalSemua = parseInt(data.total_suara_keseluruhan !== undefined ? data.total_suara_keseluruhan : (data.total_suara || 0));
                    if (elTotal && elTotal.innerText !== totalSemua.toLocaleString('id-ID')) {
                        elTotal.innerText = totalSemua.toLocaleString('id-ID');
                    }

                    // JIKA DOM SUDAH ADA, LAKUKAN IN-PLACE UPDATE (SMOOTH & ZERO FLICKER)
                    if (domInitialized && lastRenderMode === data.mode) {
                        if (data.mode === 'all') {
                            const eskulDenganKandidat = data.data_eskul.filter(e => e.kandidat && e.kandidat.length > 0);
                            eskulDenganKandidat.forEach(eskul => {
                                const totalEskul = parseInt(eskul.total_suara || 0);
                                const maxSuara = Math.max(...eskul.kandidat.map(k => parseInt(k.perolehan || 0)));
                                const elTotalEskul = document.getElementById(`eskul-total-val-${eskul.id_eskul}`);
                                if (elTotalEskul) {
                                    elTotalEskul.innerText = totalEskul.toLocaleString('id-ID') + ' Suara Masuk';
                                }
                                eskul.kandidat.forEach(paslon => {
                                    updateCandidateInPlace(paslon, totalEskul, maxSuara);
                                });
                            });
                        } else {
                            const totalEskul = parseInt(data.total_suara || 0);
                            const maxSuara = data.kandidat && data.kandidat.length > 0 ? Math.max(...data.kandidat.map(k => parseInt(k.perolehan || 0))) : 0;
                            const elTotalEskul = document.getElementById(`eskul-total-val-${data.id_eskul}`);
                            if (elTotalEskul) {
                                elTotalEskul.innerText = totalEskul.toLocaleString('id-ID') + ' Suara Masuk';
                            }
                            if (data.kandidat) {
                                data.kandidat.forEach(paslon => {
                                    updateCandidateInPlace(paslon, totalEskul, maxSuara);
                                });
                            }
                        }
                        return;
                    }

                    // INITIAL RENDER (PERTAMA KALI ATAU JIKA MODE BERUBAH)
                    let outputHtml = '';

                    if (data.mode === 'all') {
                        if (!data.data_eskul || data.data_eskul.length === 0) {
                            outputHtml = `
                                <div class="card-modern text-center py-5">
                                    <i class="fas fa-folder-open fs-1 text-muted mb-3"></i>
                                    <h5 class="fw-bold text-dark">Belum Ada Ekstrakurikuler</h5>
                                </div>
                            `;
                        } else {
                            const eskulDenganKandidat = data.data_eskul.filter(e => e.kandidat && e.kandidat.length > 0);
                            const eskulTanpaKandidat = data.data_eskul.filter(e => !e.kandidat || e.kandidat.length === 0);

                            if (eskulDenganKandidat.length === 0) {
                                outputHtml = `
                                    <div class="card-modern text-center py-5">
                                        <i class="fas fa-users-slash fs-1 text-muted mb-3 opacity-50"></i>
                                        <h5 class="fw-bold text-dark">Belum Ada Kandidat yang Didaftarkan</h5>
                                        <p class="text-muted small m-0">Silakan tambahkan data pasangan calon di menu <b>Kandidat Paslon</b>.</p>
                                    </div>
                                `;
                            } else {
                                eskulDenganKandidat.forEach(eskul => {
                                    const totalEskul = parseInt(eskul.total_suara || 0);
                                    const maxSuara = Math.max(...eskul.kandidat.map(k => parseInt(k.perolehan || 0)));
                                    const statusBadge = eskul.status_pemilihan == 1 
                                        ? '<span class="badge badge-soft-success"><i class="fas fa-door-open me-1"></i> Bilik Suara DIBUKA</span>'
                                        : '<span class="badge badge-soft-secondary"><i class="fas fa-lock me-1"></i> Bilik Suara DITUTUP</span>';

                                    let candidatesHtml = '';
                                    eskul.kandidat.forEach((paslon, idx) => {
                                        candidatesHtml += renderCandidateItem(paslon, idx, totalEskul, maxSuara);
                                    });

                                    outputHtml += `
                                        <div class="card-eskul-live" id="eskul-box-${eskul.id_eskul}">
                                            <div class="card-eskul-header">
                                                <div>
                                                    <h5 class="fw-bold text-dark m-0">
                                                        <i class="fas fa-award text-warning me-2"></i> ${eskul.nama_eskul}
                                                    </h5>
                                                    <div class="mt-1">${statusBadge}</div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge badge-soft-primary px-3 py-2 fs-6">
                                                        <i class="fas fa-vote-yea me-1"></i> <span id="eskul-total-val-${eskul.id_eskul}">${totalEskul.toLocaleString('id-ID')} Suara Masuk</span>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="candidates-container">
                                                ${candidatesHtml}
                                            </div>
                                        </div>
                                    `;
                                });
                            }
                        }
                    } else {
                        let candidatesHtml = '';
                        if (!data.kandidat || data.kandidat.length === 0) {
                            candidatesHtml = `
                                <div class="alert alert-light border text-center py-5 rounded-4">
                                    <i class="fas fa-poll-h fs-1 mb-3 text-secondary opacity-50"></i>
                                    <h5 class="fw-bold text-dark">Belum Ada Kandidat atau Suara</h5>
                                    <p class="text-muted small m-0">Kandidat belum ditambahkan untuk kategori ini.</p>
                                </div>
                            `;
                        } else {
                            const totalEskul = parseInt(data.total_suara || 0);
                            const maxSuara = Math.max(...data.kandidat.map(k => parseInt(k.perolehan || 0)));
                            data.kandidat.forEach((paslon, idx) => {
                                candidatesHtml += renderCandidateItem(paslon, idx, totalEskul, maxSuara);
                            });
                        }

                        outputHtml = `
                            <div class="card-eskul-live" id="eskul-box-${data.id_eskul}">
                                <div class="card-eskul-header">
                                    <div>
                                        <h5 class="fw-bold text-dark m-0">
                                            <i class="fas fa-award text-warning me-2"></i> ${data.nama_eskul || 'Kategori Pemilihan'}
                                        </h5>
                                        <small class="text-muted">Pemantauan Khusus Real-Time</small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge badge-soft-primary px-3 py-2 fs-6">
                                            <i class="fas fa-vote-yea me-1"></i> <span id="eskul-total-val-${data.id_eskul}">${(data.total_suara || 0).toLocaleString('id-ID')} Suara Masuk</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="candidates-container">
                                    ${candidatesHtml}
                                </div>
                            </div>
                        `;
                    }

                    wadah.innerHTML = outputHtml;
                    domInitialized = true;
                    lastRenderMode = data.mode;
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
