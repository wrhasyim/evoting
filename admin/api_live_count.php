<?php
// admin/api_live_count.php

session_start();
require '../config/koneksi.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

$id_eskul = $_GET['id_eskul'] ?? 'all';

if ($id_eskul === 'all' || empty($id_eskul)) {
    // Mode Semua Eskul Sekaligus
    $stmt_eskul = $pdo->query("SELECT id_eskul, nama_eskul, status_pemilihan FROM eskul WHERE status_aktif = 1 ORDER BY id_eskul ASC");
    $daftar_eskul = $stmt_eskul->fetchAll(PDO::FETCH_ASSOC);

    $stmt_total_all = $pdo->query("SELECT COUNT(*) FROM suara_masuk");
    $total_suara_keseluruhan = (int) $stmt_total_all->fetchColumn();

    $stmt_total_per_eskul = $pdo->query("SELECT id_eskul, COUNT(*) as total FROM suara_masuk GROUP BY id_eskul");
    $total_map = [];
    while ($r = $stmt_total_per_eskul->fetch(PDO::FETCH_ASSOC)) {
        $total_map[$r['id_eskul']] = (int) $r['total'];
    }

    $stmt_kandidat = $pdo->query("
        SELECT k.id_kandidat, k.id_eskul, k.no_urut, k.nama_paslon, k.kelas_paslon, k.foto, COUNT(s.id_suara) AS perolehan 
        FROM kandidat k 
        LEFT JOIN suara_masuk s ON k.id_kandidat = s.id_kandidat 
        WHERE k.status_aktif = 1 
        GROUP BY k.id_kandidat 
        ORDER BY k.id_eskul ASC, perolehan DESC, k.no_urut ASC
    ");
    $kandidat_rows = $stmt_kandidat->fetchAll(PDO::FETCH_ASSOC);

    $kandidat_by_eskul = [];
    foreach ($kandidat_rows as $kr) {
        $kandidat_by_eskul[$kr['id_eskul']][] = $kr;
    }

    $data_eskul = [];
    foreach ($daftar_eskul as $e) {
        $idE = $e['id_eskul'];
        $kandidats = $kandidat_by_eskul[$idE] ?? [];
        $total_suara = $total_map[$idE] ?? 0;
        
        $data_eskul[] = [
            'id_eskul' => $idE,
            'nama_eskul' => $e['nama_eskul'],
            'status_pemilihan' => (int) $e['status_pemilihan'],
            'total_suara' => $total_suara,
            'kandidat' => $kandidats
        ];
    }

    echo json_encode([
        'mode' => 'all',
        'total_suara_keseluruhan' => $total_suara_keseluruhan,
        'data_eskul' => $data_eskul
    ]);
    exit;
}

// Mode Satu Eskul Tertentu (Backward Compatible)
$stmt_eskul = $pdo->prepare("SELECT nama_eskul, status_pemilihan FROM eskul WHERE id_eskul = ?");
$stmt_eskul->execute([$id_eskul]);
$info_eskul = $stmt_eskul->fetch(PDO::FETCH_ASSOC);
$nama_eskul = $info_eskul ? $info_eskul['nama_eskul'] : '';

$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM suara_masuk WHERE id_eskul = ?");
$stmt_total->execute([$id_eskul]);
$total_suara = (int) $stmt_total->fetchColumn();

$stmt_hasil = $pdo->prepare("
    SELECT k.id_kandidat, k.no_urut, k.nama_paslon, k.kelas_paslon, k.foto, COUNT(s.id_suara) AS perolehan 
    FROM kandidat k 
    LEFT JOIN suara_masuk s ON k.id_kandidat = s.id_kandidat 
    WHERE k.id_eskul = ? AND k.status_aktif = 1 
    GROUP BY k.id_kandidat 
    ORDER BY perolehan DESC, k.no_urut ASC
");
$stmt_hasil->execute([$id_eskul]);
$data_kandidat = $stmt_hasil->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'mode' => 'single',
    'id_eskul' => $id_eskul,
    'nama_eskul' => $nama_eskul,
    'total_suara' => $total_suara,
    'kandidat' => $data_kandidat
]);
