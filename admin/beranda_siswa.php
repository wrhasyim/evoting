<?php
// beranda_siswa.php

session_start();
require 'config/koneksi.php';

// 1. PENGAMANAN HALAMAN
if (!isset($_SESSION['siswa_logged_in'])) {
    header("Location: index.php");
    exit;
}

$nis = $_SESSION['nis'];

// 2. MENDAPATKAN PERIODE AKTIF
$stmt_periode = $pdo->query("SELECT id_periode, nama_periode FROM periode WHERE status_aktif = 1 LIMIT 1");
$periode_aktif = $stmt_periode->fetch();

if (!$periode_aktif) {
    die("<div style='text-align:center; padding: 50px; font-family:sans-serif;'><h2>Pemilihan Ditutup</h2><p>Belum ada Tahun Ajaran yang diaktifkan oleh Panitia.</p><a href='logout.php'>Keluar</a></div>");
}
$id_periode = $periode_aktif['id_periode'];

// 3. MENDAPATKAN DATA SISWA DI PERIODE AKTIF
$stmt_siswa = $pdo->prepare("SELECT id_siswa, nama_siswa, status_pilih FROM siswa WHERE nis = ? AND id_periode = ? AND status_aktif = 1");
$stmt_siswa->execute([$nis, $id_periode]);
$siswa = $stmt_siswa->fetch();

if (!$siswa) {
    die("<div style='text-align:center; padding: 50px; font-family:sans-serif;'><h2>Akses Ditolak</h2><p>Data Anda tidak ditemukan pada periode ini.</p><a href='logout.php'>Keluar</a></div>");
}

$id_siswa = $siswa['id_siswa'];

// 4. CEK APAKAH SUDAH MEMILIH
if ($siswa['status_pilih'] == 1) {
    die("<div style='text-align:center; padding: 50px; font-family:sans-serif;'><h2>Terima Kasih!</h2><p>Anda sudah memberikan hak suara Anda. Pilihan Anda telah dirahasiakan dan diamankan oleh sistem.</p><a href='logout.php'>Keluar Sistem</a></div>");
}

// 5. MENGAMBIL DAFTAR ESKUL YANG BERHAK DIPILIH SISWA INI
$stmt_hak_pilih = $pdo->prepare("
    SELECT e.id_eskul, e.nama_eskul 
    FROM eskul e 
    WHERE e.status_aktif = 1 AND (
        e.aturan_pemilih = 'semua_siswa' 
        OR e.id_eskul IN (SELECT id_eskul FROM anggota_eskul WHERE id_siswa = ?)
    )
");
$stmt_hak_pilih->execute([$id_siswa]);
$daftar_hak_pilih = $stmt_hak_pilih->fetchAll();

// 6. PROSES PENCOBLOSAN (SUBMIT VOTING)
if (isset($_POST['submit_vote'])) {
    $pilihan = $_POST['pilihan_kandidat'] ?? []; // Array [id_eskul => id_kandidat]
    
    // Pastikan siswa memilih untuk semua eskul yang diwajibkan kepadanya
    if (count($pilihan) != count($daftar_hak_pilih)) {
        $error_vote = "Anda harus memilih satu kandidat untuk setiap kategori pemilihan!";
    } else {
        try {
            // Memulai transaksi database agar LUBER JURDIL aman (jika satu gagal, semua batal)
            $pdo->beginTransaction();

            foreach ($pilihan as $id_eskul => $id_kandidat) {
                // A. Catat Suara (Rahasia)
                $stmt_suara = $pdo->prepare("INSERT INTO suara_masuk (id_eskul, id_kandidat) VALUES (?, ?)");
                $stmt_suara->execute([$id_eskul, $id_kandidat]);

                // B. Catat Riwayat (Identitas)
                $stmt_riwayat = $pdo->prepare("INSERT INTO riwayat_pilih (id_siswa, id_eskul) VALUES (?, ?)");
                $stmt_riwayat->execute([$id_siswa, $id_eskul]);
            }

            // C. Kunci Status Siswa
            $stmt_lock = $pdo->prepare("UPDATE siswa SET status_pilih = 1 WHERE id_siswa = ?");
            $stmt_lock->execute([$id_siswa]);

            $pdo->commit();
            
            // Arahkan ke halaman sukses / logout
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
        .card-kandidat { border: 2px solid transparent; border-radius: 15px; transition: all 0.3s; cursor: pointer; height: 100%; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .card-kandidat:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .card-kandidat.selected { border-color: #28a745; background-color: #f0fff4; box-shadow: 0 0 15px rgba(40,167,69,0.3); }
        .foto-kandidat { width: 100%; height: 250px; object-fit: cover; border-top-left-radius: 13px; border-top-right-radius: 13px; }
        .no-urut { position: absolute; top: 10px; right: 10px; background: #dc3545; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .radio-hidden { display: none; }
        .section-title { font-weight: 700; color: #2c3e50; border-bottom: 3px solid #007bff; display: inline-block; padding-bottom: 5px; margin-bottom: 25px; }
    </style>
</head>
<body>

    <div class="header-area text-center">
        <div class="container">
            <h2 class="fw-bold"><i class="fas fa-vote-yea me-2"></i> E-Voting Sekolah</h2>
            <p class="mb-0 fs-5">Selamat datang, <b><?= htmlspecialchars($siswa['nama_siswa']); ?></b></p>
            <span class="badge bg-light text-dark mt-2 px-3 py-2">Periode: <?= htmlspecialchars($periode_aktif['nama_periode']); ?></span>
        </div>
    </div>

    <div class="container">
        <?php if (isset($error_vote)): ?>
            <div class="alert alert-danger shadow-sm"><i class="fas fa-exclamation-triangle me-2"></i> <?= $error_vote; ?></div>
        <?php endif; ?>

        <?php if (count($daftar_hak_pilih) == 0): ?>
            <div class="alert alert-info text-center py-5">
                <i class="fas fa-info-circle fs-1 mb-3 text-info"></i>
                <h4>Tidak ada pemilihan yang tersedia untuk Anda saat ini.</h4>
                <a href="logout.php" class="btn btn-primary mt-3">Keluar Sistem</a>
            </div>
        <?php else: ?>
            <form method="POST" action="" id="formVoting">
                
                <?php foreach ($daftar_hak_pilih as $eskul): ?>
                    <div class="bg-white p-4 rounded-4 shadow-sm mb-5">
                        <h4 class="section-title">Pemilihan: <?= htmlspecialchars($eskul['nama_eskul']); ?></h4>
                        
                        <?php 
                        // Ambil kandidat untuk eskul ini
                        $stmt_kan = $pdo->prepare("SELECT * FROM kandidat WHERE id_eskul = ? AND status_aktif = 1 ORDER BY no_urut ASC");
                        $stmt_kan->execute([$eskul['id_eskul']]);
                        $kandidat = $stmt_kan->fetchAll();
                        ?>

                        <?php if (count($kandidat) == 0): ?>
                            <p class="text-muted">Belum ada kandidat yang didaftarkan untuk pemilihan ini.</p>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($kandidat as $kan): ?>
                                    <div class="col-md-4 col-sm-6">
                                        <label class="w-100 h-100">
                                            <!-- Input Radio disembunyikan, menggunakan CSS untuk tampilan kartu -->
                                            <input type="radio" name="pilihan_kandidat[<?= $eskul['id_eskul']; ?>]" value="<?= $kan['id_kandidat']; ?>" class="radio-hidden" required>
                                            
                                            <div class="card card-kandidat position-relative">
                                                <div class="no-urut"><?= $kan['no_urut']; ?></div>
                                                <img src="uploads/<?= htmlspecialchars($kan['foto']); ?>" class="foto-kandidat" alt="Foto Kandidat">
                                                <div class="card-body text-center">
                                                    <h5 class="card-title fw-bold text-primary mb-1"><?= htmlspecialchars($kan['nama_paslon']); ?></h5>
                                                    <p class="text-muted small mb-2"><i class="fas fa-graduation-cap me-1"></i> Kelas: <?= htmlspecialchars($kan['kelas_paslon']); ?></p>
                                                    <button type="button" class="btn btn-sm btn-outline-info w-100 mb-3" data-bs-toggle="modal" data-bs-target="#modalVisi<?= $kan['id_kandidat']; ?>">
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
                    </div>
                <?php endforeach; ?>

                <div class="text-center sticky-bottom bg-white p-3 shadow-lg rounded-top-4 border-top">
                    <p class="text-danger fw-bold mb-2"><i class="fas fa-lock"></i> Pastikan pilihan Anda sudah benar. Suara yang sudah masuk tidak dapat diubah.</p>
                    <button type="submit" name="submit_vote" class="btn btn-success btn-lg px-5 rounded-pill fw-bold" id="btnSubmitVote">
                        <i class="fas fa-paper-plane me-2"></i> MASUKKAN SUARA SAYA
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Efek visual saat kartu dipilih
        document.querySelectorAll('.radio-hidden').forEach(radio => {
            radio.addEventListener('change', function() {
                // Hapus kelas 'selected' dari semua kartu dalam kategori eskul yang sama
                const name = this.getAttribute('name');
                document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                    r.closest('.card-kandidat').classList.remove('selected');
                    r.closest('.card-kandidat').querySelector('.btn-pilih-ui').classList.replace('btn-success', 'btn-secondary');
                    r.closest('.card-kandidat').querySelector('.btn-pilih-ui').innerHTML = 'PILIH KANDIDAT INI';
                });
                
                // Tambahkan kelas 'selected' ke kartu yang diklik
                if(this.checked) {
                    this.closest('.card-kandidat').classList.add('selected');
                    this.closest('.card-kandidat').querySelector('.btn-pilih-ui').classList.replace('btn-secondary', 'btn-success');
                    this.closest('.card-kandidat').querySelector('.btn-pilih-ui').innerHTML = '<i class="fas fa-check-circle me-1"></i> DIPILIH';
                }
            });
        });

        // Pencegahan klik ganda pada tombol submit
        document.getElementById('formVoting').addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmitVote');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Memproses...';
            btn.classList.add('disabled');
        });
    </script>
</body>
</html>