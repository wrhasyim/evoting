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
    die("<div style='text-align:center; padding: 50px; font-family:sans-serif;'><h2>Akses Ditolak</h2><p>Data Anda tidak ditemukan pada periode ini.</p><a href='../logout.php'>Keluar</a></div>");
}

$id_siswa = $siswa['id_siswa'];

// 4. CEK APAKAH SUDAH MEMILIH SEMUA (KUNCI GLOBAL)
if ($siswa['status_pilih'] == 1) {
    die("<div style='text-align:center; padding: 50px; font-family:sans-serif;'><h2>Terima Kasih!</h2><p>Anda sudah memberikan hak suara Anda. Pilihan Anda telah dirahasiakan dan diamankan oleh sistem.</p><a href='../logout.php' style='display:inline-block; margin-top:20px; padding:10px 20px; background:#007bff; color:#fff; text-decoration:none; border-radius:5px;'>Keluar Sistem</a></div>");
}

// 5. MENGAMBIL DAFTAR ESKUL YANG BERHAK DIPILIH (Sedang Dibuka & Belum Dicoblos Siswa Ini)
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
if (isset($_POST['submit_vote'])) {
    $pilihan = $_POST['pilihan_kandidat'] ?? [];
    
    if (count($pilihan) != count($daftar_hak_pilih)) {
        $error_vote = "Anda harus memilih kandidat untuk semua kategori yang tersedia!";
    } else {
        try {
            $pdo->beginTransaction();

            foreach ($pilihan as $id_eskul => $id_kandidat) {
                // A. Catat Suara (Rahasia)
                $stmt_suara = $pdo->prepare("INSERT INTO suara_masuk (id_eskul, id_kandidat) VALUES (?, ?)");
                $stmt_suara->execute([$id_eskul, $id_kandidat]);

                // B. Catat Riwayat (Identitas)
                $stmt_riwayat = $pdo->prepare("INSERT INTO riwayat_pilih (id_siswa, id_eskul) VALUES (?, ?)");
                $stmt_riwayat->execute([$id_siswa, $id_eskul]);
            }

            // C. Kunci Status Siswa (Pernah Memilih)
            $stmt_lock = $pdo->prepare("UPDATE siswa SET status_pilih = 1 WHERE id_siswa = ?");
            $stmt_lock->execute([$id_siswa]);

            $pdo->commit();
            
            // Arahkan ke beranda untuk melihat pesan Terima Kasih
            header("Location: beranda_siswa.php");
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fa; padding-bottom: 50px; }
        .header-area { background: linear-gradient(135deg, #1a2980, #26d0ce); color: white; padding: 30px 0; border-bottom-left-radius: 30px; border-bottom-right-radius: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 40px; }
        
        .card-kandidat { 
            border: 2px solid #e9ecef; 
            border-radius: 15px; 
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); 
            cursor: pointer; 
            height: 100%; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); 
            background-color: white;
        }
        .card-kandidat:hover { 
            transform: translateY(-5px); 
            border-color: #0d6efd;
            box-shadow: 0 10px 25px rgba(13,110,253,0.15); 
        }
        
        /* Kelas ketika kartu berhasil di-klik (Dipilih) */
        .card-kandidat.selected { 
            border: 3px solid #198754 !important; 
            background-color: #f0fff4 !important; 
            transform: scale(1.03) !important; 
            box-shadow: 0 15px 30px rgba(25,135,84,0.3) !important; 
        }
        
        .foto-kandidat { width: 100%; height: 250px; object-fit: cover; border-top-left-radius: 13px; border-top-right-radius: 13px; }
        .no-urut { position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; box-shadow: 0 2px 5px rgba(0,0,0,0.2); z-index: 2; }
        .radio-hidden { display: none; }
        .section-title { font-weight: 700; color: #2c3e50; border-bottom: 3px solid #007bff; display: inline-block; padding-bottom: 5px; margin-bottom: 25px; }
        
        .wizard-step { animation: fadeIn 0.4s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
        
        .btn-pilih-ui { transition: all 0.3s ease; }
    </style>
</head>
<body>

    <div class="header-area text-center">
        <div class="container">
            <h2 class="fw-bold"><i class="fas fa-vote-yea me-2"></i> E-Voting SMK TARUNA KARYA MANDIRI</h2>
            <p class="mb-0 fs-5">Selamat datang, <b><?= htmlspecialchars($siswa['nama_siswa']); ?></b></p>
            <span class="badge bg-light text-dark mt-2 px-3 py-2">Periode: <?= htmlspecialchars($periode_aktif['nama_periode']); ?></span>
            
            <div class="mt-3">
                <a href="../logout.php" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="fas fa-sign-out-alt"></i> Keluar
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($error_vote)): ?>
            <div class="alert alert-danger shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i> <?= $error_vote; ?></div>
        <?php endif; ?>

        <?php if (count($daftar_hak_pilih) == 0): ?>
            <div class="bg-white p-5 rounded-4 shadow-sm text-center">
                <i class="fas fa-check-circle text-success" style="font-size: 5rem; margin-bottom: 20px;"></i>
                <h3 class="fw-bold text-dark">Terima Kasih!</h3>
                <p class="text-muted fs-5">Anda sudah menyelesaikan semua pemilihan yang tersedia saat ini, atau belum ada jadwal pemilihan yang dibuka oleh panitia.</p>
            </div>
        <?php else: ?>
            
            <?php 
                $total_pemilihan = count($daftar_hak_pilih); 
                $index = 0; 
            ?>

            <form method="POST" action="" id="formVoting">
                
                <?php foreach ($daftar_hak_pilih as $eskul): ?>
                    <!-- WIZARD STEP -->
                    <div id="step-<?= $index; ?>" class="wizard-step bg-white p-4 rounded-4 shadow-sm mb-5 <?= $index === 0 ? 'd-block' : 'd-none'; ?>">
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="section-title m-0">Pemilihan: <?= htmlspecialchars($eskul['nama_eskul']); ?></h4>
                            <span class="badge bg-primary fs-6">Tahap <?= $index + 1; ?> dari <?= $total_pemilihan; ?></span>
                        </div>
                        
                        <?php 
                        $stmt_kan = $pdo->prepare("SELECT * FROM kandidat WHERE id_eskul = ? AND status_aktif = 1 ORDER BY no_urut ASC");
                        $stmt_kan->execute([$eskul['id_eskul']]);
                        $kandidat = $stmt_kan->fetchAll();
                        ?>

                        <?php if (count($kandidat) == 0): ?>
                            <div class="alert alert-warning">Belum ada kandidat yang didaftarkan untuk pemilihan ini. Hubungi panitia.</div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($kandidat as $kan): ?>
                                    <div class="col-md-4 col-sm-6">
                                        <label class="w-100 h-100 d-block m-0" style="cursor: pointer;">
                                            <input type="radio" name="pilihan_kandidat[<?= $eskul['id_eskul']; ?>]" value="<?= $kan['id_kandidat']; ?>" class="radio-hidden" required>
                                            
                                            <div class="card card-kandidat position-relative">
                                                <div class="no-urut"><?= $kan['no_urut']; ?></div>
                                                <img src="../uploads/<?= htmlspecialchars($kan['foto']); ?>" class="foto-kandidat" alt="Foto Kandidat">
                                                <div class="card-body text-center">
                                                    <h5 class="card-title fw-bold text-primary mb-1"><?= htmlspecialchars($kan['nama_paslon']); ?></h5>
                                                    <p class="text-muted small mb-2"><i class="fas fa-graduation-cap me-1"></i> Kelas: <?= htmlspecialchars($kan['kelas_paslon']); ?></p>
                                                    
                                                    <button type="button" class="btn btn-sm btn-outline-info w-100 mb-3" data-bs-toggle="modal" data-bs-target="#modalVisi<?= $kan['id_kandidat']; ?>" onclick="event.preventDefault(); event.stopPropagation();">
                                                        Lihat Visi & Misi
                                                    </button>
                                                    
                                                    <div class="btn-pilih-ui btn btn-secondary w-100 fw-bold">PILIH KANDIDAT INI</div>
                                                </div>
                                            </div>
                                        </label>
                                    </div>

                                    <!-- MODAL VISI MISI -->
                                    <div class="modal fade" id="modalVisi<?= $kan['id_kandidat']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-info text-white">
                                                    <h5 class="modal-title fw-bold">Visi & Misi Paslon No. <?= $kan['no_urut']; ?></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body" style="white-space: pre-wrap; font-size: 0.95rem;">
<?= htmlspecialchars($kan['visi_misi']); ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- AREA TOMBOL NAVIGASI SLIDER -->
                        <div class="d-flex justify-content-between align-items-center mt-5 border-top pt-4">
                            <div>
                                <?php if ($index > 0): ?>
                                    <button type="button" class="btn btn-outline-secondary px-4 fw-bold rounded-pill" onclick="prevStep(<?= $index; ?>)">
                                        <i class="fas fa-arrow-left me-2"></i> Sebelumnya
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if ($index < $total_pemilihan - 1): ?>
                                    <button type="button" class="btn btn-outline-primary px-4 fw-bold rounded-pill" onclick="nextStep(<?= $index; ?>)">
                                        Selanjutnya <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="submit_vote" class="btn btn-success px-4 fw-bold rounded-pill" id="btnSubmitVote" onclick="return validateFinalStep(<?= $index; ?>)">
                                        <i class="fas fa-paper-plane me-2"></i> KIRIM SUARA SAYA
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                    <?php $index++; ?>
                <?php endforeach; ?>

            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Efek visual saat kartu dipilih & Logika Auto-Next
        document.querySelectorAll('.radio-hidden').forEach(radio => {
            radio.addEventListener('change', function() {
                const name = this.getAttribute('name');
                
                // 1. Menghapus styling dari kandidat lain di kategori yang sama
                document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                    // PERBAIKAN: Mencari .card-kandidat melalui elemen parent (label)
                    const card = r.closest('label').querySelector('.card-kandidat');
                    if(card) {
                        card.classList.remove('selected');
                        const btnUI = card.querySelector('.btn-pilih-ui');
                        if(btnUI) {
                            btnUI.classList.replace('btn-success', 'btn-secondary');
                            btnUI.innerHTML = 'PILIH KANDIDAT INI';
                        }
                    }
                });
                
                if(this.checked) {
                    // 2. Menerapkan styling hijau dan pop-out ke kandidat yang dipilih
                    const selectedCard = this.closest('label').querySelector('.card-kandidat');
                    if(selectedCard) {
                        selectedCard.classList.add('selected');
                        const selectedBtnUI = selectedCard.querySelector('.btn-pilih-ui');
                        if(selectedBtnUI) {
                            selectedBtnUI.classList.replace('btn-secondary', 'btn-success');
                            selectedBtnUI.innerHTML = '<i class="fas fa-check-circle me-1"></i> DIPILIH';
                        }
                    }

                    // 3. LOGIKA AUTO-NEXT (Otomatis bergeser ke step berikutnya)
                    const currentStepDiv = this.closest('.wizard-step');
                    const currentIndex = parseInt(currentStepDiv.id.split('-')[1]);
                    const totalSteps = document.querySelectorAll('.wizard-step').length;

                    if (currentIndex < totalSteps - 1) {
                        setTimeout(() => {
                            nextStep(currentIndex);
                        }, 800); 
                    }
                }
            });
        });

        // FUNGSI NAVIGASI WIZARD
        function nextStep(currentIndex) {
            const currentDiv = document.getElementById('step-' + currentIndex);
            const isChecked = currentDiv.querySelector('input[type="radio"]:checked');
            
            if (!isChecked) {
                alert("Peringatan: Anda belum menentukan pilihan. Silakan klik salah satu kartu kandidat terlebih dahulu!");
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

        function validateFinalStep(currentIndex) {
            const currentDiv = document.getElementById('step-' + currentIndex);
            const isChecked = currentDiv.querySelector('input[type="radio"]:checked');
            
            if (!isChecked) {
                alert("Peringatan: Anda belum menentukan pilihan terakhir. Silakan klik kartu kandidat!");
                return false; 
            }

            const konfirmasi = confirm("Apakah Anda yakin dengan semua pilihan Anda? Suara yang masuk tidak dapat dibatalkan.");
            if(konfirmasi) {
                const btn = document.getElementById('btnSubmitVote');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Memproses...';
                btn.classList.add('disabled');
                return true;
            }
            return false;
        }
    </script>
</body>
</html>