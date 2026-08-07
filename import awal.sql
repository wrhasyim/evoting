-- E-Voting Database Backup
-- Waktu Backup: 2026-07-27 06:59:13

SET FOREIGN_KEY_CHECKS = 0;

-- ==========================================
-- FASE 1: MENGHAPUS TABEL (Anak -> Induk)
-- ==========================================
DROP TABLE IF EXISTS log_aktivitas;
DROP TABLE IF EXISTS suara_masuk;
DROP TABLE IF EXISTS riwayat_pilih;
DROP TABLE IF EXISTS anggota_eskul;
DROP TABLE IF EXISTS kandidat;
DROP TABLE IF EXISTS siswa;
DROP TABLE IF EXISTS eskul;
DROP TABLE IF EXISTS periode;
DROP TABLE IF EXISTS admin;

-- ==========================================
-- FASE 2: MEMBUAT TABEL (Induk -> Anak)
-- ==========================================

-- 1. Tabel Induk: Admin
CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  PRIMARY KEY (`id_admin`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO admin VALUES 
('1', 'admin', '$2y$10$KaLjyVTkCmXSRLuxDQ8gQeEeHVzkcRnzxWQzggKIi0vC9wgao7jgm', 'Administrator Utama');

-- 2. Tabel Induk: Periode
CREATE TABLE `periode` (
  `id_periode` int(11) NOT NULL AUTO_INCREMENT,
  `nama_periode` varchar(50) NOT NULL,
  `status_aktif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_periode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Tabel Induk: Eskul
CREATE TABLE `eskul` (
  `id_eskul` int(11) NOT NULL AUTO_INCREMENT,
  `nama_eskul` varchar(100) NOT NULL,
  `aturan_pemilih` enum('semua_siswa','hanya_anggota') DEFAULT 'semua_siswa',
  `status_pemilihan` tinyint(1) DEFAULT 0,
  `status_aktif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_eskul`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Tabel Anak: Siswa (Bergantung pada periode)
CREATE TABLE `siswa` (
  `id_siswa` int(11) NOT NULL AUTO_INCREMENT,
  `id_periode` int(11) NOT NULL,
  `nis` varchar(20) NOT NULL,
  `nama_siswa` varchar(100) NOT NULL,
  `kelas` varchar(50) NOT NULL,
  `pin` varchar(10) NOT NULL,
  `status_pilih` tinyint(1) DEFAULT 0,
  `status_aktif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_siswa`),
  KEY `id_periode` (`id_periode`),
  CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`id_periode`) REFERENCES `periode` (`id_periode`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Tabel Anak: Kandidat (Bergantung pada eskul)
CREATE TABLE `kandidat` (
  `id_kandidat` int(11) NOT NULL AUTO_INCREMENT,
  `id_eskul` int(11) DEFAULT NULL,
  `no_urut` int(11) NOT NULL,
  `nama_paslon` varchar(150) NOT NULL,
  `kelas_paslon` varchar(100) NOT NULL,
  `visi_misi` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `status_aktif` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id_kandidat`),
  KEY `id_eskul` (`id_eskul`),
  CONSTRAINT `kandidat_ibfk_1` FOREIGN KEY (`id_eskul`) REFERENCES `eskul` (`id_eskul`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Tabel Anak: Log Aktivitas (Bergantung pada admin)
CREATE TABLE `log_aktivitas` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_admin` int(11) DEFAULT NULL,
  `aktivitas` varchar(255) NOT NULL,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `id_admin` (`id_admin`),
  CONSTRAINT `log_aktivitas_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO log_aktivitas VALUES 
('13', '1', 'Login ke sistem', '2026-07-27 11:59:08');

-- 7. Tabel Anak (Junction): Anggota Eskul (Bergantung pada siswa & eskul)
CREATE TABLE `anggota_eskul` (
  `id_anggota` int(11) NOT NULL AUTO_INCREMENT,
  `id_siswa` int(11) DEFAULT NULL,
  `id_eskul` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_anggota`),
  KEY `id_siswa` (`id_siswa`),
  KEY `id_eskul` (`id_eskul`),
  CONSTRAINT `anggota_eskul_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE,
  CONSTRAINT `anggota_eskul_ibfk_2` FOREIGN KEY (`id_eskul`) REFERENCES `eskul` (`id_eskul`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Tabel Anak: Riwayat Pilih (Bergantung pada siswa & eskul)
CREATE TABLE `riwayat_pilih` (
  `id_riwayat` int(11) NOT NULL AUTO_INCREMENT,
  `id_siswa` int(11) DEFAULT NULL,
  `id_eskul` int(11) DEFAULT NULL,
  `waktu_memilih` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_riwayat`),
  KEY `id_siswa` (`id_siswa`),
  KEY `id_eskul` (`id_eskul`),
  CONSTRAINT `riwayat_pilih_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE,
  CONSTRAINT `riwayat_pilih_ibfk_2` FOREIGN KEY (`id_eskul`) REFERENCES `eskul` (`id_eskul`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. Tabel Anak: Suara Masuk (Bergantung pada eskul & kandidat)
CREATE TABLE `suara_masuk` (
  `id_suara` int(11) NOT NULL AUTO_INCREMENT,
  `id_eskul` int(11) DEFAULT NULL,
  `id_kandidat` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_suara`),
  KEY `id_eskul` (`id_eskul`),
  KEY `id_kandidat` (`id_kandidat`),
  CONSTRAINT `suara_masuk_ibfk_1` FOREIGN KEY (`id_eskul`) REFERENCES `eskul` (`id_eskul`) ON DELETE CASCADE,
  CONSTRAINT `suara_masuk_ibfk_2` FOREIGN KEY (`id_kandidat`) REFERENCES `kandidat` (`id_kandidat`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;