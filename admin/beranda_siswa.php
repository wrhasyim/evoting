<?php
// admin/beranda_siswa.php

session_start();
require '../config/koneksi.php';

// 1. PENGAMANAN HALAMAN
if (!isset($_SESSION['siswa_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$nis = $_SESSION['nis'];

// 2. MENDAPATKAN PERIODE AKTIF
$stmt_periode = $pdo->query("SELECT id_periode, nama_periode FROM periode WHERE status_aktif = 1 LIMIT 1");
$periode_aktif = $stmt_periode->fetch();

if (!$periode_aktif) {
    die("<div style='text-align:center; padding: 60px 20px; font-family:sans-serif;'><h2>Pemilihan Ditutup</h2><p>Belum ada Tahun Ajaran yang diaktifkan oleh Panitia.</p><a href='../logout.php' style='color:#2563eb; text-decoration:none; font-weight:bold;'>Keluar ke Halaman Utama</a></div>");
}
$id_periode = $periode_aktif['id_periode'];

// 3. MENDAPATKAN DATA SISWA DI PERIODE AKTIF
$stmt_siswa = $pdo->prepare("SELECT id_siswa, nama_siswa, kelas, status_pilih FROM siswa WHERE nis = ? AND id_periode = ? AND status_aktif = 1");
$stmt_siswa->execute([$nis, $id_periode]);
$siswa = $stmt_siswa->fetch();

if (!$siswa) {
    die("<div style='text-align:center; padding: 60px 20px; font-family:sans-serif;'><h2>Akses Ditolak</h2><p>Data pemilih tidak ditemukan pada periode ini.</p><a href='../logout.php' style='color:#2563eb; text-decoration:none; font-weight:bold;'>Keluar</a></div>");
}

$id_siswa = $siswa['id_siswa'];

// 4. CEK APAKAH SUDAH MEMILIH SEMUA
if ($siswa['status_pilih'] == 1) {
    header("Location: ../logout.php");
    exit;
}

// 5. MENGAMBIL DAFTAR ESKUL YANG BERHAK DIPILIH
$stmt_hak_pilih = $pdo->prepare("
    SELECT e.id_eskul, e.nama_eskul 
    FROM eskul e 
    WHERE e.status_aktif = 1 
    AND e.status_pemilihan = 1 
    AND e.id_eskul NOT IN (SELECT id_eskul FROM riwayat_pilih WHERE id_siswa = ?)
    AND (
        e.aturan_pemilih = 'semua_siswa' 
        OR e.id_eskul IN (SELECT id_eskul FROM anggota_eskul WHERE id_siswa = ?)
    )
");
$stmt_hak_pilih->execute([$id_siswa, $id_siswa]);
$daftar_hak_pilih = $stmt_hak_pilih->fetchAll();

// 6. PROSES PENCOBLOSAN (SUBMIT VOTING)
if (isset($_POST['submit_vote_hidden'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $pilihan = $_POST['pilihan_kandidat'] ?? [];
    
    if (count($pilihan) != count($daftar_hak_pilih)) {
        $error_vote = "Terjadi kesalahan. Pastikan semua tahap pemilihan telah Anda tentukan.";
    } else {
        try {
            $pdo->beginTransaction();

            foreach ($pilihan as $id_eskul => $id_kandidat) {
                $stmt_suara = $pdo->prepare("INSERT INTO suara_masuk (id_eskul, id_kandidat) VALUES (?, ?)");
                $stmt_suara->execute([$id_eskul, $id_kandidat]);

                $stmt_riwayat = $pdo->prepare("INSERT INTO riwayat_pilih (id_siswa, id_eskul) VALUES (?, ?)");
                $stmt_riwayat->execute([$id_siswa, $id_eskul]);
            }

            $stmt_lock = $pdo->prepare("UPDATE siswa SET status_pilih = 1 WHERE id_siswa = ?");
            $stmt_lock->execute([$id_siswa]);

            $pdo->commit();
            
            header("Location: ../logout.php?voted=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_vote = "Terjadi kesalahan sistem saat merekam suara: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilik Suara Elektronik - E-Voting</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            padding-bottom: 60px; 
            color: #1e293b;
        }
        
        .siswa-navbar { 
            background-color: #ffffff; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            padding: 12px 0; 
            z-index: 1050; 
            border-bottom: 1px solid #e2e8f0;
        }

        .logo-sekolah { 
            height: 48px; 
            object-fit: contain; 
            margin-right: 12px; 
        }

        .welcome-card {
            background: #ffffff; 
            border-radius: 18px; 
            padding: 20px 26px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-top: 24px; 
            margin-bottom: 28px;
            border: 1px solid #e2e8f0;
            border-left: 6px solid #2563eb;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .wizard-step { 
            animation: fadeIn 0.35s ease; 
            padding: 28px !important; 
            margin-bottom: 24px !important; 
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
            border: 1px solid #e2e8f0;
        }

        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(10px); } 
            to { opacity: 1; transform: translateY(0); } 
        }

        .card-kandidat { 
            border: 2px solid #e2e8f0; 
            border-radius: 18px; 
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); 
            cursor: pointer; 
            height: 100%; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.04); 
            background-color: #ffffff;
            overflow: hidden; 
            position: relative;
        }

        .card-kandidat:hover { 
            transform: translateY(-6px); 
            border-color: #3b82f6; 
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.15); 
        }

        .card-kandidat.selected { 
            border: 3px solid #10b981 !important; 
            background-color: #f0fdf4 !important; 
            transform: scale(1.02) !important; 
            box-shadow: 0 16px 35px rgba(16, 185, 129, 0.25) !important; 
        }
        
        .foto-kandidat { 
            width: 100%; 
            height: 270px; 
            object-fit: contain; 
            background-color: #f8fafc; 
            border-bottom: 1px solid #e2e8f0; 
            padding: 10px;
        }
        
        .no-urut { 
            position: absolute; 
            top: 14px; 
            right: 14px; 
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); 
            color: #ffffff; 
            width: 44px; 
            height: 44px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 800; 
            font-size: 1.25rem; 
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.35); 
            z-index: 2; 
        }

        .radio-hidden { 
            display: none; 
        }
        
        .btn-pilih-ui { 
            transition: all 0.25s ease; 
            font-size: 0.95rem; 
            font-weight: 700;
            padding: 10px; 
            border-radius: 10px;
        }

        .badge-stage {
            background: linear-gradient(135deg, #2563eb, #06b6d4);
            color: #ffffff;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 0.88rem;
        }
    </style>
</head>
<body>

    <!-- NAVBAR ATAS SISWA -->
    <nav class="siswa-navbar sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <?php if (file_exists('../uploads/logo_utama.png')): ?>
                    <img src="../uploads/logo_utama.png?v=<?= time(); ?>" class="logo-sekolah" alt="Logo">
                <?php else: ?>
                    <div class="rounded-3 bg-primary text-white d-flex align-items-center justify-content-center me-3" style="width: 42px; height: 42px;">
                        <i class="fas fa-vote-yea fs-5"></i>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="fw-bold fs-6 text-dark" style="line-height: 1.2;">Bilik Suara Elektronik</div>
                    <div class="small text-muted">SMK Taruna Karya Mandiri</div>
                </div>
            </div>
            <div>
                <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold" onclick="return confirm('Apakah Anda yakin ingin keluar dari bilik suara?');">
                    <i class="fas fa-sign-out-alt me-1"></i> Keluar
                </a>
            </div>
        </div>
    </nav>

    <div class="container" style="max-width: 1060px;">
        
        <!-- KARTU PROFIL PEMILIH -->
        <div class="welcome-card">
            <div>
                <div class="small text-muted text-uppercase fw-bold letter-spacing-1">Identitas Pemilih Sah</div>
                <h4 class="mb-1 fw-bold text-dark">
                    <?= htmlspecialchars($siswa['nama_siswa']); ?>
                </h4>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span class="badge bg-primary px-3 py-1 font-monospace">NIS: <?= htmlspecialchars($siswa['nis']); ?></span>
                    <span class="badge bg-dark px-3 py-1 font-monospace">Kelas: <?= htmlspecialchars($siswa['kelas']); ?></span>
                </div>
            </div>
            <div>
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill font-monospace">
                    <i class="fas fa-calendar-alt text-primary me-1"></i> Periode: <?= htmlspecialchars($periode_aktif['nama_periode']); ?>
                </span>
            </div>
        </div>

        <?php if (isset($error_vote)): ?>
            <div class="alert alert-danger shadow-sm py-3 rounded-3 mb-4"><i class="fas fa-exclamation-triangle me-2"></i> <?= $error_vote; ?></div>
        <?php endif; ?>

        <?php if (count($daftar_hak_pilih) == 0): ?>
            <div class="bg-white p-5 rounded-4 shadow-sm text-center mt-4 border">
                <i class="fas fa-vote-yea text-success" style="font-size: 4rem; margin-bottom: 15px;"></i>
                <h4 class="fw-bold text-dark">Tidak Ada Pemilihan yang Terbuka</h4>
                <p class="text-muted">Saat ini belum ada bilik pemilihan yang dibuka oleh panitia untuk Anda ikuti.</p>
                <a href="../logout.php" class="btn btn-primary rounded-pill px-4 mt-2">Keluar Sistem</a>
            </div>
        <?php else: ?>
            
            <?php 
                $total_pemilihan = count($daftar_hak_pilih); 
                $index = 0; 
            ?>

            <form method="POST" action="" id="formVoting">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token(); ?>">
                
                <?php foreach ($daftar_hak_pilih as $eskul): ?>
                    <div id="step-<?= $index; ?>" class="wizard-step <?= $index === 0 ? 'd-block' : 'd-none'; ?>">
                        
                        <!-- HEADER KATEGORI & STEP INDIKATOR -->
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($index > 0): ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-circle d-flex justify-content-center align-items-center" style="width: 38px; height: 38px;" onclick="prevStep(<?= $index; ?>)" title="Kembali ke tahap sebelumnya">
                                        <i class="fas fa-arrow-left"></i>
                                    </button>
                                <?php endif; ?>
                                <div>
                                    <h4 class="m-0 text-dark fw-bold"><?= htmlspecialchars($eskul['nama_eskul']); ?></h4>
                                    <small class="text-muted">Silakan tentukan pasangan calon pilihan Anda di bawah ini</small>
                                </div>
                            </div>
                            <span class="badge-stage">
                                <i class="fas fa-layer-group me-1"></i> Tahap <?= $index + 1; ?> dari <?= $total_pemilihan; ?>
                            </span>
                        </div>
                        <hr class="mb-4">
                        
                        <?php 
                        $stmt_kan = $pdo->prepare("SELECT * FROM kandidat WHERE id_eskul = ? AND status_aktif = 1 ORDER BY no_urut ASC");
                        $stmt_kan->execute([$eskul['id_eskul']]);
                        $kandidat = $stmt_kan->fetchAll();
                        ?>

                        <?php if (count($kandidat) == 0): ?>
                            <div class="alert alert-warning py-3 rounded-3 text-center">
                                <i class="fas fa-exclamation-circle me-1"></i> Belum ada kandidat terdaftar untuk kategori ini. Silakan hubungi panitia.
                            </div>
                        <?php else: ?>
                            <div class="row g-4 justify-content-center">
                                <?php foreach ($kandidat as $kan): ?>
                                    <div class="col-md-4 col-sm-6">
                                        <label class="w-100 h-100 d-block m-0" style="cursor: pointer;">
                                            <input type="radio" name="pilihan_kandidat[<?= $eskul['id_eskul']; ?>]" value="<?= $kan['id_kandidat']; ?>" class="radio-hidden" required>
                                            
                                            <div class="card card-kandidat position-relative">
                                                <div class="no-urut"><?= $kan['no_urut']; ?></div>
                                                <img src="../uploads/<?= htmlspecialchars($kan['foto']); ?>" class="foto-kandidat" alt="Foto Kandidat" onerror="this.onerror=null; this.src='https://via.placeholder.com/300?text=Foto+Kandidat';">
                                                
                                                <div class="card-body p-3 text-center">
                                                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($kan['nama_paslon']); ?></h5>
                                                    <div class="badge bg-light text-secondary border font-monospace mb-3">
                                                        <i class="fas fa-graduation-cap me-1"></i> <?= htmlspecialchars($kan['kelas_paslon']); ?>
                                                    </div>
                                                    
                                                    <button type="button" class="btn btn-sm btn-outline-info w-100 mb-2 py-2 fw-semibold rounded-pill" data-bs-toggle="modal" data-bs-target="#modalVisi<?= $kan['id_kandidat']; ?>" onclick="event.preventDefault(); event.stopPropagation();">
                                                        <i class="fas fa-bullhorn me-1"></i> Visi & Misi Paslon
                                                    </button>
                                                    
                                                    <div class="btn-pilih-ui btn btn-secondary w-100">
                                                        <i class="far fa-circle me-1"></i> PILIH KANDIDAT
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- MODAL DETAIL VISI MISI -->
                                    <div class="modal fade" id="modalVisi<?= $kan['id_kandidat']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title fw-bold m-0">Visi & Misi Paslon No. <?= $kan['no_urut']; ?></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4" style="white-space: pre-wrap; line-height: 1.6; font-size: 0.95rem;">
                                                    <h6 class="fw-bold text-dark"><?= htmlspecialchars($kan['nama_paslon']); ?> (<?= htmlspecialchars($kan['kelas_paslon']); ?>)</h6>
                                                    <hr class="my-2">
<?= htmlspecialchars($kan['visi_misi']); ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                    <?php $index++; ?>
                <?php endforeach; ?>

            </form>
        <?php endif; ?>
    </div>

    <!-- Scripts Bootstrap & SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        document.querySelectorAll('.radio-hidden').forEach(radio => {
            radio.addEventListener('change', function() {
                const name = this.getAttribute('name');
                
                document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                    const card = r.closest('label').querySelector('.card-kandidat');
                    if(card) {
                        card.classList.remove('selected');
                        const btnUI = card.querySelector('.btn-pilih-ui');
                        if(btnUI) {
                            btnUI.classList.replace('btn-success', 'btn-secondary');
                            btnUI.innerHTML = '<i class="far fa-circle me-1"></i> PILIH KANDIDAT';
                        }
                    }
                });
                
                if(this.checked) {
                    const selectedCard = this.closest('label').querySelector('.card-kandidat');
                    let selectedBtnUI;
                    if(selectedCard) {
                        selectedCard.classList.add('selected');
                        selectedBtnUI = selectedCard.querySelector('.btn-pilih-ui');
                        if(selectedBtnUI) {
                            selectedBtnUI.classList.replace('btn-secondary', 'btn-success');
                            selectedBtnUI.innerHTML = '<i class="fas fa-check-circle me-1"></i> DIPILIH';
                        }
                    }

                    const currentStepDiv = this.closest('.wizard-step');
                    const currentIndex = parseInt(currentStepDiv.id.split('-')[1]);
                    const totalSteps = document.querySelectorAll('.wizard-step').length;

                    if (currentIndex < totalSteps - 1) {
                        setTimeout(() => {
                            nextStep(currentIndex);
                        }, 500); 
                    } else {
                        // Konfirmasi Akhir Pencoblosan
                        setTimeout(() => {
                            Swal.fire({
                                title: 'Konfirmasi Pilihan Suara',
                                text: 'Apakah Anda sudah yakin dengan semua pilihan kandidat Anda? Suara yang telah dikirimkan tidak dapat diubah kembali.',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonColor: '#10b981',
                                cancelButtonColor: '#64748b',
                                confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Ya, Kirim Suara Sekarang!',
                                cancelButtonText: 'Cek Pilihan Lagi',
                                reverseButtons: true
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    Swal.fire({
                                        title: 'Merekam Suara Anda...',
                                        text: 'Mohon tunggu, sistem sedang memverifikasi dan mengunci pilihan Anda secara aman.',
                                        allowOutsideClick: false,
                                        didOpen: () => {
                                            Swal.showLoading();
                                        }
                                    });
                                    
                                    const form = document.getElementById('formVoting');
                                    const hiddenInput = document.createElement('input');
                                    hiddenInput.type = 'hidden';
                                    hiddenInput.name = 'submit_vote_hidden';
                                    hiddenInput.value = '1';
                                    form.appendChild(hiddenInput);
                                    
                                    form.submit();
                                } else {
                                    if(selectedCard) {
                                        selectedCard.classList.remove('selected');
                                        selectedBtnUI.classList.replace('btn-success', 'btn-secondary');
                                        selectedBtnUI.innerHTML = '<i class="far fa-circle me-1"></i> PILIH KANDIDAT';
                                    }
                                    radio.checked = false;
                                }
                            });
                        }, 600); 
                    }
                }
            });
        });

        function nextStep(currentIndex) {
            const currentDiv = document.getElementById('step-' + currentIndex);
            const isChecked = currentDiv.querySelector('input[type="radio"]:checked');
            
            if (!isChecked) {
                Swal.fire('Perhatian', 'Pilih salah satu kandidat terlebih dahulu sebelum melanjutkan.', 'info');
                return;
            }

            currentDiv.classList.replace('d-block', 'd-none');
            const nextDiv = document.getElementById('step-' + (currentIndex + 1));
            nextDiv.classList.replace('d-none', 'd-block');
            window.scrollTo({ top: 0, behavior: 'smooth' }); 
        }

        function prevStep(currentIndex) {
            const currentDiv = document.getElementById('step-' + currentIndex);
            currentDiv.classList.replace('d-block', 'd-none');
            const prevDiv = document.getElementById('step-' + (currentIndex - 1));
            prevDiv.classList.replace('d-none', 'd-block');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
</body>
</html>
