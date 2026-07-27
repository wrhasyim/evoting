<?php
// logout.php (Berada di root folder evoting)

// 1. Mulai sesi untuk mendeteksi sesi mana yang sedang aktif
session_start();

// 2. Kosongkan semua variabel sesi yang ada
session_unset();

// 3. Hancurkan sesi secara total dari server
session_destroy();

// 4. Arahkan pengguna kembali ke halaman Smart Login (index.php)
header("Location: index.php");
exit;
?>