<?php
// admin/export_hasil.php
session_start();
require '../config/koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

$id_eskul = $_GET['id_eskul'] ?? 'all';

header("Content-Type: application/vnd.ms-excel");
header("Pragma: no-cache");
header("Expires: 0");

if ($id_eskul === 'all') {
    header("Content-Disposition: attachment; filename=Laporan_Hasil_Pemilu_Semua_Eskul.xls");
    $stmt_eskul = $pdo->query("
        SELECT DISTINCT e.id_eskul, e.nama_eskul 
        FROM eskul e 
        JOIN kandidat k ON e.id_eskul = k.id_eskul 
        WHERE e.status_aktif = 1 AND k.status_aktif = 1 
        ORDER BY e.id_eskul ASC
    ");
    $list_eskul = $stmt_eskul->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <table border="1">
        <thead>
            <tr>
                <th colspan="6" style="font-size: 16px; font-weight: bold; text-align: center; background-color: #f1f5f9;">
                    LAPORAN REKAPITULASI HASIL PEMILIHAN E-VOTING (SEMUA KATEGORI)
                </th>
            </tr>
            <tr>
                <th colspan="6" style="text-align: center;">Dicetak pada: <?= date('d/m/Y H:i:s'); ?></th>
            </tr>
        </thead>
    </table>
    <br>
    <?php foreach ($list_eskul as $e): 
        $idE = $e['id_eskul'];
        $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM suara_masuk WHERE id_eskul = ?");
        $stmt_total->execute([$idE]);
        $total_suara = $stmt_total->fetchColumn();

        $stmt_hasil = $pdo->prepare("
            SELECT k.no_urut, k.nama_paslon, k.kelas_paslon, COUNT(s.id_suara) AS perolehan 
            FROM kandidat k 
            LEFT JOIN suara_masuk s ON k.id_kandidat = s.id_kandidat 
            WHERE k.id_eskul = ? AND k.status_aktif = 1 
            GROUP BY k.id_kandidat 
            ORDER BY perolehan DESC, k.no_urut ASC
        ");
        $stmt_hasil->execute([$idE]);
        $data_hasil = $stmt_hasil->fetchAll();
    ?>
        <table border="1">
            <thead>
                <tr style="background-color: #e2e8f0;">
                    <th colspan="6" style="font-size: 14px; font-weight: bold;">
                        Kategori: <?= htmlspecialchars($e['nama_eskul']); ?> (Total Suara Masuk: <?= $total_suara; ?> Suara)
                    </th>
                </tr>
                <tr style="background-color: #f8fafc;">
                    <th style="width: 50px; text-align: center;">Rank</th>
                    <th style="width: 70px; text-align: center;">No. Urut</th>
                    <th style="width: 250px;">Nama Pasangan Calon</th>
                    <th style="width: 150px;">Kelas</th>
                    <th style="width: 120px; text-align: right;">Perolehan Suara</th>
                    <th style="width: 100px; text-align: right;">Persentase</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($data_hasil) > 0): ?>
                    <?php $rank = 1; foreach ($data_hasil as $row): 
                        $pct = ($total_suara > 0) ? round(($row['perolehan'] / $total_suara) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td style="text-align: center;"><?= $rank++; ?></td>
                            <td style="text-align: center;"><?= $row['no_urut']; ?></td>
                            <td><?= htmlspecialchars($row['nama_paslon']); ?></td>
                            <td><?= htmlspecialchars($row['kelas_paslon']); ?></td>
                            <td style="text-align: right;"><?= $row['perolehan']; ?></td>
                            <td style="text-align: right;"><?= $pct; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #64748b;">Belum ada kandidat atau suara pada kategori ini.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <br>
    <?php endforeach; ?>
    <?php
    exit;
}

// Mode Satu Eskul Tertentu
$stmt_nama = $pdo->prepare("SELECT nama_eskul FROM eskul WHERE id_eskul = ?");
$stmt_nama->execute([$id_eskul]);
$nama_eskul = $stmt_nama->fetchColumn() ?: 'Eskul';

header("Content-Disposition: attachment; filename=Laporan_Hasil_Pemilu_" . str_replace(' ', '_', $nama_eskul) . ".xls");

$stmt_total = $pdo->prepare("SELECT COUNT(*) FROM suara_masuk WHERE id_eskul = ?");
$stmt_total->execute([$id_eskul]);
$total_suara = $stmt_total->fetchColumn();

$stmt_hasil = $pdo->prepare("
    SELECT k.no_urut, k.nama_paslon, k.kelas_paslon, COUNT(s.id_suara) AS perolehan 
    FROM kandidat k 
    LEFT JOIN suara_masuk s ON k.id_kandidat = s.id_kandidat 
    WHERE k.id_eskul = ? AND k.status_aktif = 1 
    GROUP BY k.id_kandidat 
    ORDER BY perolehan DESC, k.no_urut ASC
");
$stmt_hasil->execute([$id_eskul]);
$data_hasil = $stmt_hasil->fetchAll();
?>
<table border="1">
    <thead>
        <tr>
            <th colspan="6" style="font-size: 16px; font-weight: bold; text-align: center; background-color: #f1f5f9;">LAPORAN HASIL PEMILIHAN E-VOTING</th>
        </tr>
        <tr>
            <th colspan="6" style="text-align: center;">Kategori: <?= htmlspecialchars($nama_eskul); ?></th>
        </tr>
        <tr>
            <th colspan="6" style="text-align: center;">Total Suara Masuk: <?= $total_suara; ?> Suara</th>
        </tr>
        <tr style="background-color: #f8fafc;">
            <th style="width: 50px; text-align: center;">Rank</th>
            <th style="width: 70px; text-align: center;">No. Urut</th>
            <th style="width: 250px;">Nama Pasangan Calon</th>
            <th style="width: 150px;">Kelas</th>
            <th style="width: 120px; text-align: right;">Perolehan Suara</th>
            <th style="width: 100px; text-align: right;">Persentase</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($data_hasil) > 0): ?>
            <?php $rank = 1; foreach ($data_hasil as $row): 
                $pct = ($total_suara > 0) ? round(($row['perolehan'] / $total_suara) * 100, 1) : 0;
            ?>
                <tr>
                    <td style="text-align: center;"><?= $rank++; ?></td>
                    <td style="text-align: center;"><?= $row['no_urut']; ?></td>
                    <td><?= htmlspecialchars($row['nama_paslon']); ?></td>
                    <td><?= htmlspecialchars($row['kelas_paslon']); ?></td>
                    <td style="text-align: right;"><?= $row['perolehan']; ?></td>
                    <td style="text-align: right;"><?= $pct; ?>%</td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align: center; color: #64748b;">Belum ada data kandidat atau suara.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
