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
    die("<div style='text-align:center; padding: 50px; font-family:sans-serif;'><h2>Pemilihan Ditutup</h2><p>Belum ada Tahun Ajaran yang diaktifkan oleh Panitia.</p><a href='../logout.php'>Keluar</a></div>");
}
$id_periode = $periode_aktif['id_periode'];

// 3. MENDAPATKAN DATA SISWA DI PERIODE AKTIF
$stmt_siswa = $pdo->prepare("SELECT id_siswa, nama_siswa, status_pilih FROM siswa WHERE nis = ? AND id_periode = ? AND status_aktif = 1");
$stmt_siswa->execute([$nis, $id_periode]);
$siswa = $stmt_siswa->fetch();

if (!$siswa) {
    die("<div style='text-align:center; padding: 50px; font-family:sans-serif;'><h2>Akses Ditolak</h2><p>Data tidak ditemukan pada periode ini.</p><a href='../logout.php'>Keluar</a></div>");
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

// 6. PROSES PENCOBLOSAN (SUBMIT VOTING SECARA OTOMATIS)
if (isset($_POST['submit_vote_hidden'])) {
    $pilihan = $_POST['pilihan_kandidat'] ?? [];
    
    if (count($pilihan) != count($daftar_hak_pilih)) {
        $error_vote = "Terjadi kesalahan. Pastikan semua tahap telah dipilih.";
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
            
            header("Location: ../logout.php");
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
    <title>Area Pemilihan - E-Voting</title>
    <!-- Menambahkan Bootstrap & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fa; padding-bottom: 50px; }
        
        .siswa-navbar { background-color: #ffffff; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 10px 0; z-index: 1050; }

        .logo-sekolah { 
            height: 55px; 
            object-fit: contain; 
            transform: scale(1.4); 
            transform-origin: left center; 
            margin-right: 15px; 
        }

        .welcome-card {
            background: #ffffff; border-radius: 12px; padding: 15px 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            display: flex; justify-content: space-between; align-items: center; 
            margin-top: 20px; margin-bottom: 30px;
            border-top: 4px solid #1a2980;
        }
        
        .wizard-step { animation: fadeIn 0.4s; padding: 25px !important; margin-bottom: 20px !important; }
        @keyframes fadeIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }

        .card-kandidat { 
            border: 2px solid #e9ecef; border-radius: 12px; transition: all 0.2s ease; 
            cursor: pointer; height: 100%; box-shadow: 0 2px 8px rgba(0,0,0,0.04); background-color: white;
            overflow: hidden; 
        }
        .card-kandidat:hover { transform: translateY(-5px); border-color: #0d6efd; box-shadow: 0 8px 20px rgba(13,110,253,0.15); }
        .card-kandidat.selected { border: 3px solid #198754 !important; background-color: #f0fff4 !important; transform: scale(1.02) !important; box-shadow: 0 10px 20px rgba(25,135,84,0.2) !important; }
        
        .foto-kandidat { 
            width: 100%; 
            height: 280px; 
            object-fit: contain; 
            background-color: #f8f9fa; 
            border-bottom: 1px solid #e9ecef; 
        }
        
        .no-urut { position: absolute; top: 12px; right: 12px; background: #dc3545; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 2; }
        .radio-hidden { display: none; }
        
        .card-body { padding: 15px; }
        .card-title { font-size: 1.15rem; margin-bottom: 2px; }
        .btn-pilih-ui { transition: all 0.3s ease; font-size: 0.95rem; padding: 8px; }

        @media (max-width: 768px) {
            .welcome-card { flex-direction: column; text-align: center; gap: 10px; }
            .logo-sekolah { transform: scale(1.2); }
        }
    </style>
</head>
<body>

    <?php
    $logo_file = glob("../uploads/logo_utama.*");
    $logo_url = !empty($logo_file) ? $logo_file[0] : null;
    $logo_version = $logo_url ? filemtime($logo_url) : '1';
    ?>

    <!-- NAVBAR ATAS SISWA -->
    <nav class="siswa-navbar sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <?php if ($logo_url): ?>
                    <img src="<?= $logo_url; ?>?v=<?= $logo_version; ?>" class="logo-sekolah" alt="Logo Sekolah">
                <?php endif; ?>
                <div class="fw-bold fs-5 text-primary d-none d-sm-block" style="line-height: 1.2;">
                    E-Voting SMK <br> Taruna Karya Mandiri
                </div>
                <div class="fw-bold fs-5 text-primary d-block d-sm-none">
                    E-Voting
                </div>
            </div>
            <a href="../logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-4 fw-bold">
                <i class="fas fa-sign-out-alt me-1"></i> Keluar
            </a>
        </div>
    </nav>

    <div class="container">
        
        <!-- KARTU SAMBUTAN -->
        <div class="welcome-card">
            <div>
                <h5 class="mb-1 text-dark">Selamat datang, <span class="fw-bold text-primary"><?= htmlspecialchars($siswa['nama_siswa']); ?></span></h5>
                <small class="text-muted">Gunakan hak suara dengan bijak dan rahasia.</small>
            </div>
            <div>
                <span class="badge bg-light text-dark border border-secondary px-3 py-2">
                    <i class="fas fa-calendar-alt me-1 text-secondary"></i> Periode: <?= htmlspecialchars($periode_aktif['nama_periode']); ?>
                </span>
            </div>
        </div>

        <?php if (isset($error_vote)): ?>
            <div class="alert alert-danger shadow-sm py-2"><i class="fas fa-exclamation-triangle me-2"></i> <?= $error_vote; ?></div>
        <?php endif; ?>

        <?php if (count($daftar_hak_pilih) == 0): ?>
            <div class="bg-white p-5 rounded-4 shadow-sm text-center mt-4">
                <i class="fas fa-calendar-times text-warning" style="font-size: 4rem; margin-bottom: 15px;"></i>
                <h4 class="fw-bold text-dark">Belum Ada Pemilihan</h4>
                <p class="text-muted">Saat ini belum ada jadwal pemilihan yang dibuka oleh panitia.</p>
            </div>
        <?php else: ?>
            
            <?php 
                $total_pemilihan = count($daftar_hak_pilih); 
                $index = 0; 
            ?>

            <form method="POST" action="" id="formVoting">
                
                <?php foreach ($daftar_hak_pilih as $eskul): ?>
                    <div id="step-<?= $index; ?>" class="wizard-step bg-white rounded-4 shadow-sm <?= $index === 0 ? 'd-block' : 'd-none'; ?>">
                        
                        <!-- HEADER KATEGORI & TOMBOL KEMBALI -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($index > 0): ?>
                                    <button type="button" class="btn btn-secondary btn-sm rounded-circle d-flex justify-content-center align-items-center shadow-sm" style="width: 35px; height: 35px;" onclick="prevStep(<?= $index; ?>)" title="Kembali">
                                        <i class="fas fa-arrow-left"></i>
                                    </button>
                                <?php endif; ?>
                                <h5 class="m-0 text-primary fw-bold" style="font-size: 1.25rem;">Pemilihan: <?= htmlspecialchars($eskul['nama_eskul']); ?></h5>
                            </div>
                            <span class="badge bg-primary fs-6">Tahap <?= $index + 1; ?> dari <?= $total_pemilihan; ?></span>
                        </div>
                        <hr class="mb-4 text-primary border-2 opacity-25">
                        
                        <?php 
                        $stmt_kan = $pdo->prepare("SELECT * FROM kandidat WHERE id_eskul = ? AND status_aktif = 1 ORDER BY no_urut ASC");
                        $stmt_kan->execute([$eskul['id_eskul']]);
                        $kandidat = $stmt_kan->fetchAll();
                        ?>

                        <?php if (count($kandidat) == 0): ?>
                            <div class="alert alert-warning py-2">Belum ada kandidat terdaftar. Hubungi panitia.</div>
                        <?php else: ?>
                            <div class="row g-4 justify-content-center">
                                <?php foreach ($kandidat as $kan): ?>
                                    <div class="col-md-4 col-sm-6">
                                        <label class="w-100 h-100 d-block m-0" style="cursor: pointer;">
                                            <input type="radio" name="pilihan_kandidat[<?= $eskul['id_eskul']; ?>]" value="<?= $kan['id_kandidat']; ?>" class="radio-hidden" required>
                                            
                                            <div class="card card-kandidat position-relative">
                                                <div class="no-urut"><?= $kan['no_urut']; ?></div>
                                                <img src="../uploads/<?= htmlspecialchars($kan['foto']); ?>" class="foto-kandidat" alt="Foto Kandidat">
                                                
                                                <div class="card-body text-center">
                                                    <h6 class="card-title fw-bold text-primary"><?= htmlspecialchars($kan['nama_paslon']); ?></h6>
                                                    <p class="text-muted small mb-3"><i class="fas fa-graduation-cap me-1"></i> Kelas: <?= htmlspecialchars($kan['kelas_paslon']); ?></p>
                                                    
                                                    <button type="button" class="btn btn-sm btn-outline-info w-100 mb-2 py-1" data-bs-toggle="modal" data-bs-target="#modalVisi<?= $kan['id_kandidat']; ?>" onclick="event.preventDefault(); event.stopPropagation();">
                                                        Lihat Visi & Misi
                                                    </button>
                                                    
                                                    <div class="btn-pilih-ui btn btn-secondary w-100 fw-bold">PILIH KANDIDAT</div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>

                                    <div class="modal fade" id="modalVisi<?= $kan['id_kandidat']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-info text-white py-2">
                                                    <h6 class="modal-title fw-bold m-0">Visi & Misi Paslon No. <?= $kan['no_urut']; ?></h6>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body" style="white-space: pre-wrap; font-size: 0.9rem;">
<?= htmlspecialchars($kan['visi_misi']); ?>
                                                </div>
                                                <div class="modal-footer py-1">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
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
                            btnUI.innerHTML = 'PILIH KANDIDAT';
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
                        }, 700); 
                    } else {
                        // Memunculkan SweetAlert dengan gaya bahasa siswa
                        setTimeout(() => {
                            Swal.fire({
                                title: 'Sudah Selesai?',
                                text: 'Yakin nih dengan semua pilihan kandidatnya? Suara yang sudah dikirim tidak bisa diubah lagi ya.',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonColor: '#198754',
                                cancelButtonColor: '#dc3545',
                                confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Ya, Kirim Suara!',
                                cancelButtonText: 'Cek Kembali',
                                reverseButtons: true
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    Swal.fire({
                                        title: 'Memproses Suara...',
                                        html: 'Mohon tunggu sebentar.',
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
                                        selectedBtnUI.innerHTML = 'PILIH KANDIDAT';
                                    }
                                    radio.checked = false;
                                }
                            });
                        }, 800); 
                    }
                }
            });
        });

        function nextStep(currentIndex) {
            const currentDiv = document.getElementById('step-' + currentIndex);
            const isChecked = currentDiv.querySelector('input[type="radio"]:checked');
            
            if (!isChecked) {
                alert("Pilih salah satu kandidat terlebih dahulu!");
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