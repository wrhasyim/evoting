<?php
// config/koneksi.php

// 1. Deklarasi variabel pengaturan database (Ini yang sebelumnya terlewat di salinan Anda)
$host     = "localhost";
$dbname   = "evoting_smk";
$username = "root"; 
$password = "";     

try {
    // 2. Membuat koneksi PDO baru
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    
    // 3. Mengatur mode error PDO agar menampilkan peringatan (Exception) jika ada masalah query
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 4. Mengatur default pengembalian data dalam bentuk Array Associative
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Jika koneksi gagal, hentikan program dan tampilkan pesan error
    die("Koneksi Database Gagal: " . $e->getMessage());
}
?>