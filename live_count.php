<?php
// live_count.php (Root - Halaman Live Count Publik Tanpa Perlu Login Admin)

require 'config/koneksi.php';

// Mengambil daftar eskul untuk dropdown (HANYA yang memiliki kandidat aktif)
$stmt_eskul = $pdo->query("
    SELECT DISTINCT e.id_eskul, e.nama_eskul, e.status_pemilihan 
    FROM eskul e 
    JOIN kandidat k ON e.id_eskul = k.id_eskul 
    WHERE e.status_aktif = 1 AND k.status_aktif = 1 
    ORDER BY e.id_eskul ASC
");
$daftar_eskul = $stmt_eskul->fetchAll(PDO::FETCH_ASSOC);

// Default: 'all' (Tampilkan semua eskul & kandidat sekaligus)
$id_eskul_pilih = isset($_GET['id_eskul']) ? $_GET['id_eskul'] : 'all';

// Mengambil periode aktif untuk header
$stmt_periode = $pdo->query("SELECT nama_periode FROM periode WHERE status_aktif = 1 LIMIT 1");
$periode_aktif = $stmt_periode->fetchColumn() ?? '2026/2027';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Count Pemilihan Suara Real-Time - SMK Taruna Karya Mandiri</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin/assets/css/style.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .public-navbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .brand-logo {
            width: 46px;
            height: 46px;
            object-fit: contain;
            border-radius: 8px;
        }
        .main-container {
            max-width: 1140px;
            margin: 0 auto;
            padding: 24px 16px 40px;
            flex: 1;
            width: 100%;
        }
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
            background: #ecfdf5;
            color: #065f46;
            padding: 5px 12px;
            border-radius: 9999px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 1px solid #a7f3d0;
        }
        .pulse-dot {
            width: 9px;
            height: 9px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-green 1.6s infinite;
        }
        @keyframes pulse-green {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .card-eskul-live {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            margin-bottom: 28px;
            overflow: hidden;
        }
        .card-eskul-header {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 16px 22px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .candidates-container {
            padding: 20px;
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
        .badge-stage {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 5px 12px;
            border-radius: 9999px;
            border: 1px solid #bfdbfe;
        }
        .footer-public {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 18px 0;
            font-size: 0.85rem;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- NAVBAR RESMI PUBLIK -->
    <header class="public-navbar">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <?php if (file_exists('uploads/logo_utama.png')): ?>
                    <img src="uploads/logo_utama.png" alt="Logo Sekolah" class="brand-logo">
                <?php else: ?>
                    <div class="bg-primary text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width:44px; height:44px;">
                        <i class="fas fa-vote-yea fs-4"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <h5 class="fw-bold text-dark m-0">E-VOTING RESMI</h5>
                    <small class="text-muted fw-semibold">SMK TARUNA KARYA MANDIRI &bull; T.A <?= htmlspecialchars($periode_aktif); ?></small>
                </div>
            </div>
            <div>
                <span class="live-indicator">
                    <span class="pulse-dot"></span>
                    <span>LIVE REAL-TIME</span>
                </span>
            </div>
        </div>
    </header>

    <!-- KONTEN UTAMA -->
    <main class="main-container">
        
        <!-- HEADER LIVE COUNT -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h3 class="fw-bold text-dark m-0">
                    <i class="fas fa-chart-pie text-primary me-2"></i>Hasil Pemilihan Suara (Live Count)
                </h3>
                <small class="text-muted">Pembaruan data otomatis berlangsung setiap 3 detik tanpa perlu memuat ulang halaman.</small>
            </div>
            <div class="badge badge-soft-primary px-3 py-2 fs-6 shadow-sm">
                <i class="fas fa-envelope-open-text me-1"></i> Total Suara Masuk: <span id="angka-total-suara" class="fw-bold font-monospace">0</span>
            </div>
        </div>

        <?php if (count($daftar_eskul) == 0): ?>
            <div class="card-modern text-center py-5 shadow-sm">
                <i class="fas fa-poll fs-1 text-muted mb-3 opacity-50"></i>
                <h5 class="fw-bold text-dark">Belum Ada Pemilihan yang Aktif</h5>
                <p class="text-muted small m-0">Saat ini belum ada pasangan calon yang didaftarkan untuk pemilihan.</p>
            </div>
        <?php else: ?>
            <!-- FILTER PEMILIHAN -->
            <div class="card bg-white border rounded-4 p-3 mb-4 shadow-sm">
                <form method="GET" action="" id="formFilterEskul">
                    <div class="row align-items-center g-2">
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-dark m-0">
                                <i class="fas fa-filter text-primary me-1"></i> Kategori Tampilan:
                            </label>
                        </div>
                        <div class="col-md-9">
                            <select name="id_eskul" id="selectEskul" class="form-select form-select-lg fw-bold" onchange="this.form.submit()">
                                <option value="all" <?= ($id_eskul_pilih === 'all') ? 'selected' : ''; ?>>
                                    🌟 TAMPILKAN SEMUA KANDIDAT & PEMILIHAN SEKALIGUS (REKAP LENGKAP)
                                </option>
                                <optgroup label="Pilih Kategori Khusus:">
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
                <div class="card bg-white border rounded-4 text-center py-5 shadow-sm">
                    <i class="fas fa-spinner fa-spin fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold text-dark">Menghubungkan ke Server Live Count Real-Time...</h5>
                    <p class="text-muted small m-0">Sedang mengambil data perolehan suara terkini</p>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- FOOTER RESMI -->
    <footer class="footer-public">
        <div class="container">
            <span>&copy; <?= date('Y'); ?> Sistem E-Voting Mandiri &bull; <b>SMK Taruna Karya Mandiri</b></span>
        </div>
    </footer>

    <!-- SCRIPT AJAX REAL-TIME (SMOOTH & ZERO FLICKERING) -->
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
            const fotoSrc = paslon.foto ? `uploads/${paslon.foto}` : 'https://via.placeholder.com/150?text=Foto';

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
                                    <span class="fw-bold fs-5 font-monospace text-dark" id="suara-angka-${paslon.id_kandidat}">
                                        ${parseInt(paslon.perolehan).toLocaleString('id-ID')}
                                    </span>
                                    <span class="text-muted small">suara</span>
                                    <span class="badge badge-soft-primary ms-2 font-monospace fs-6" id="suara-persen-${paslon.id_kandidat}">
                                        ${persentase}%
                                    </span>
                                </div>
                            </div>
                            
                            <!-- PROGRESS BAR ANIMATED -->
                            <div class="progress progress-live mt-2">
                                <div class="progress-bar ${warnaPilihan}" 
                                     id="progress-bar-${paslon.id_kandidat}" 
                                     role="progressbar" 
                                     style="width: ${persentase}%;" 
                                     aria-valuenow="${persentase}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <span class="px-2" id="progress-text-${paslon.id_kandidat}">
                                        ${persentase > 5 ? persentase + '%' : ''}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function updateExistingCandidate(paslon, index, totalSuaraEskul, maxSuara) {
            let persentase = 0;
            if (totalSuaraEskul > 0) {
                persentase = ((paslon.perolehan / totalSuaraEskul) * 100).toFixed(1);
            }
            const isLeading = totalSuaraEskul > 0 && parseInt(paslon.perolehan) === maxSuara && maxSuara > 0;

            const card = document.getElementById(`card-candidate-${paslon.id_kandidat}`);
            if (card) {
                if (isLeading) {
                    card.classList.add('leading');
                } else {
                    card.classList.remove('leading');
                }
            }

            const leadingBadge = document.getElementById(`leading-badge-${paslon.id_kandidat}`);
            if (leadingBadge) {
                if (isLeading) {
                    leadingBadge.classList.remove('d-none');
                } else {
                    leadingBadge.classList.add('d-none');
                }
            }

            const suaraAngka = document.getElementById(`suara-angka-${paslon.id_kandidat}`);
            if (suaraAngka) {
                suaraAngka.innerText = parseInt(paslon.perolehan).toLocaleString('id-ID');
            }

            const suaraPersen = document.getElementById(`suara-persen-${paslon.id_kandidat}`);
            if (suaraPersen) {
                suaraPersen.innerText = persentase + '%';
            }

            const progressBar = document.getElementById(`progress-bar-${paslon.id_kandidat}`);
            if (progressBar) {
                progressBar.style.width = persentase + '%';
                progressBar.setAttribute('aria-valuenow', persentase);
            }

            const progressText = document.getElementById(`progress-text-${paslon.id_kandidat}`);
            if (progressText) {
                progressText.innerText = persentase > 5 ? persentase + '%' : '';
            }
        }

        function muatDataRealtime() {
            const urlApi = `api_live_count.php?id_eskul=${encodeURIComponent(idEskulAktif)}&_t=${Date.now()}`;

            fetch(urlApi)
                .then(response => {
                    if (!response.ok) throw new Error("Gagal mengambil respon server");
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.warn("API Error:", data.error);
                        return;
                    }

                    const elTotalAll = document.getElementById('angka-total-suara');
                    if (elTotalAll) {
                        const totalKeseluruhan = data.mode === 'all' ? data.total_suara_keseluruhan : data.total_suara;
                        elTotalAll.innerText = (totalKeseluruhan || 0).toLocaleString('id-ID');
                    }

                    const wadah = document.getElementById('wadah-semua-eskul');
                    if (!wadah) return;

                    const canUpdateInPlace = domInitialized && lastRenderMode === data.mode;

                    if (canUpdateInPlace) {
                        if (data.mode === 'all' && data.data_eskul) {
                            data.data_eskul.forEach(eskul => {
                                const totalValEl = document.getElementById(`eskul-total-val-${eskul.id_eskul}`);
                                const totalEskul = parseInt(eskul.total_suara || 0);
                                if (totalValEl) {
                                    totalValEl.innerText = `${totalEskul.toLocaleString('id-ID')} Suara Masuk`;
                                }

                                const maxSuara = Math.max(...eskul.kandidat.map(k => parseInt(k.perolehan || 0)));
                                eskul.kandidat.forEach((paslon, idx) => {
                                    updateExistingCandidate(paslon, idx, totalEskul, maxSuara);
                                });
                            });
                        } else if (data.mode === 'single' && data.kandidat) {
                            const totalValEl = document.getElementById(`eskul-total-val-${data.id_eskul}`);
                            const totalEskul = parseInt(data.total_suara || 0);
                            if (totalValEl) {
                                totalValEl.innerText = `${totalEskul.toLocaleString('id-ID')} Suara Masuk`;
                            }

                            const maxSuara = Math.max(...data.kandidat.map(k => parseInt(k.perolehan || 0)));
                            data.kandidat.forEach((paslon, idx) => {
                                updateExistingCandidate(paslon, idx, totalEskul, maxSuara);
                            });
                        }
                        return;
                    }

                    // Render Awal (First Load)
                    let outputHtml = '';

                    if (data.mode === 'all') {
                        if (!data.data_eskul || data.data_eskul.length === 0) {
                            outputHtml = `
                                <div class="card bg-white border rounded-4 text-center py-5 shadow-sm">
                                    <i class="fas fa-vote-yea fs-1 text-muted mb-3 opacity-50"></i>
                                    <h5 class="fw-bold text-dark">Belum Ada Kategori Pemilihan Aktif</h5>
                                    <p class="text-muted small m-0">Saat ini panitia belum mengaktifkan pemilihan.</p>
                                </div>
                            `;
                        } else {
                            const eskulDenganKandidat = data.data_eskul.filter(e => e.kandidat && e.kandidat.length > 0);

                            if (eskulDenganKandidat.length === 0) {
                                outputHtml = `
                                    <div class="card bg-white border rounded-4 text-center py-5 shadow-sm">
                                        <i class="fas fa-users-slash fs-1 text-muted mb-3 opacity-50"></i>
                                        <h5 class="fw-bold text-dark">Belum Ada Kandidat yang Didaftarkan</h5>
                                        <p class="text-muted small m-0">Silakan menunggu panitia mengumumkan pasangan calon resmi.</p>
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
</body>
</html>
