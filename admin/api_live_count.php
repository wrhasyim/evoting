<?php
// admin/api_live_count.php

session_start();
require '../config/koneksi.php';

// Format output sebagai JSON
header('Content-Type: application/json');

// Proteksi keamanan
if (!isset($_SESSION['admin_logged_in']) || !isset($_GET['id_eskul'])) {
    echo json_encode(['error' => 'Akses ditolak atau ID Eskul tidak valid']);
    exit;
}

$id_eskul = $_GET['id_eskul'];

// 1. Hitung total seluruh suara
$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM suara_masuk WHERE id_eskul = ?");
$stmt_total->execute([$id_eskul]);
$total_suara = (int) $stmt_total->fetchColumn();

// 2. Hitung perolehan masing-masing kandidat
$stmt_hasil = $pdo->prepare("
    SELECT k.no_urut, k.nama_paslon, k.foto, COUNT(s.id_suara) AS perolehan 
    FROM kandidat k 
    LEFT JOIN suara_masuk s ON k.id_kandidat = s.id_kandidat 
    WHERE k.id_eskul = ? AND k.status_aktif = 1 
    GROUP BY k.id_kandidat 
    ORDER BY perolehan DESC, k.no_urut ASC
");
$stmt_hasil->execute([$id_eskul]);
$data_kandidat = $stmt_hasil->fetchAll(PDO::FETCH_ASSOC);

// Kirim data dalam format JSON
echo json_encode([
    'total_suara' => $total_suara,
    'kandidat' => $data_kandidat
]);
?>