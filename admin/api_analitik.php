<?php
session_start();
require '../config/koneksi.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['error' => 'Unauthorized access']));
}

try {
    // Cari periode aktif
    $stmt_periode = $pdo->query("SELECT id_periode FROM periode WHERE status_aktif = 1 LIMIT 1");
    $periode_aktif = $stmt_periode->fetchColumn();

    if ($periode_aktif) {
        $stmt = $pdo->prepare("
            SELECT kelas,
                SUM(CASE WHEN status_pilih = 1 THEN 1 ELSE 0 END) as sudah_memilih,
                SUM(CASE WHEN status_pilih = 0 THEN 1 ELSE 0 END) as belum_memilih
            FROM siswa
            WHERE status_aktif = 1 AND id_periode = ?
            GROUP BY kelas
            ORDER BY kelas ASC
        ");
        $stmt->execute([$periode_aktif]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $data = [];
    }

    echo json_encode($data);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
