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
    <title>Live Count - E-Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f7fa; overflow-x: hidden; }
        .content { margin-left: 260px; padding: 40px; }
        .top-header { background: white; padding: 15px 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .card-hasil { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 10px 20px rgba(0,0,0,0.04); margin-bottom: 20px; }
        .foto-kandidat { width: 80px; height: 80px; object-fit: cover; border-radius: 50%; border: 3px solid #f8f9fa; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .progress { height: 25px; border-radius: 15px; background-color: #e9ecef; }
        .progress-bar { font-weight: bold; font-size: 1rem; line-height: 25px; transition: width 0.8s ease-in-out; }
    </style>
</head>
<body>

    <!-- MEMANGGIL SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <div class="top-header">
            <div>
                <h4 class="m-0 fw-bold" style="color: #2c3e50;">Pemantauan Hasil (Live Count)</h4>
                <small class="text-muted text-success"><i class="fas fa-circle text-success me-1"></i> Data real-time beroperasi dengan lancar</small>
            </div>
            <div>
                <?php if ($id_eskul_pilih): ?>
                    <a href="export_hasil.php?id_eskul=<?= $id_eskul_pilih; ?>" class="btn btn-success me-3">
                        <i class="fas fa-file-excel me-1"></i> Ekspor ke Excel
                    </a>
                <?php endif; ?>
                <!-- Tempat kita menaruh angka total suara otomatis -->
                <span class="badge bg-primary p-2 fs-6">
                    <i class="fas fa-envelope-open-text me-1"></i> Total Suara: <span id="angka-total-suara">0</span>
                </span>
            </div>
        </div>

        <?php if (count($daftar_eskul) == 0): ?>
            <div class="alert alert-warning">Belum ada ekstrakurikuler yang didaftarkan.</div>
        <?php else: ?>
            <form method="GET" action="" class="mb-4 bg-light p-3 rounded border">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <label class="fw-bold mb-0">Lihat Hasil Untuk:</label>
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

            <div class="row">
                <div class="col-12">
                    <div class="card-hasil">
                        <h5 class="fw-bold mb-4 border-bottom pb-2">Grafik Perolehan Suara Sementara</h5>
                        
                        <!-- Wadah kosong ini akan diisi oleh JavaScript -->
                        <div id="wadah-grafik">
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-spinner fa-spin fs-2"></i>
                                <p class="mt-2">Memuat data secara real-time...</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- SCRIPT AJAX REAL-TIME -->
    <script>
        // Mengambil ID Eskul dari PHP agar JavaScript tahu eskul mana yang sedang dipantau
        const idEskulAktif = <?= json_encode($id_eskul_pilih); ?>;
        
        // Daftar warna untuk grafik agar bervariasi
        const warnaBar = ['bg-success', 'bg-info', 'bg-warning text-dark', 'bg-danger'];

        // Fungsi utama untuk memuat data dari API
        function muatDataRealtime() {
            if (!idEskulAktif) return; // Hentikan jika tidak ada eskul

            // Menggunakan fetch untuk mengetuk API
            fetch(`api_live_count.php?id_eskul=${idEskulAktif}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error("Kesalahan API:", data.error);
                        return;
                    }

                    // 1. Perbarui Angka Total Suara di Header
                    document.getElementById('angka-total-suara').innerText = data.total_suara;

                    // 2. Perbarui Grafik Kandidat
                    let htmlGrafik = '';
                    
                    if (data.kandidat.length === 0) {
                        htmlGrafik = `
                            <div class="alert alert-info text-center py-5">
                                <i class="fas fa-chart-bar fs-1 mb-3 text-info"></i>
                                <h4>Belum ada kandidat atau belum ada suara yang masuk.</h4>
                            </div>`;
                    } else {
                        data.kandidat.forEach((paslon, index) => {
                            // Hitung persentase aman (cegah error dibagi 0)
                            let persentase = 0;
                            if (data.total_suara > 0) {
                                persentase = ((paslon.perolehan / data.total_suara) * 100).toFixed(1);
                            }

                            // Pilih warna secara bergantian
                            let kelasWarna = warnaBar[index % warnaBar.length];

                            // Susun HTML (Mirip dengan kode PHP lama, tapi ini digambar pakai JS)
                            htmlGrafik += `
                                <div class="row align-items-center mb-4">
                                    <div class="col-md-1 col-3 text-center">
                                        <img src="../uploads/${paslon.foto}" alt="Foto" class="foto-kandidat" onerror="this.src='../uploads/default.png'">
                                    </div>
                                    <div class="col-md-11 col-9">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="fw-bold fs-5">Paslon No. ${paslon.no_urut} - ${paslon.nama_paslon}</span>
                                            <span class="fw-bold fs-5 text-primary">${paslon.perolehan} Suara (${persentase}%)</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar ${kelasWarna} progress-bar-striped progress-bar-animated" role="progressbar" style="width: ${persentase}%;" aria-valuenow="${persentase}" aria-valuemin="0" aria-valuemax="100">
                                                ${persentase > 5 ? persentase + '%' : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                    }

                    // Tampilkan ke layar tanpa berkedip!
                    document.getElementById('wadah-grafik').innerHTML = htmlGrafik;
                })
                .catch(error => {
                    console.error("Gagal mengambil data:", error);
                });
        }

        // Panggil fungsi segera saat halaman pertama kali dibuka
        muatDataRealtime();

        // Atur agar fungsi berjalan otomatis setiap 3000 milidetik (3 detik)
        setInterval(muatDataRealtime, 3000);
    </script>
</body>
</html>