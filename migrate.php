<?php
require 'config/koneksi.php';

try {
    $pdo->exec("ALTER TABLE eskul ADD COLUMN waktu_mulai DATETIME DEFAULT NULL AFTER status_pemilihan");
    $pdo->exec("ALTER TABLE eskul ADD COLUMN waktu_selesai DATETIME DEFAULT NULL AFTER waktu_mulai");
    echo "Migration completed successfully.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
