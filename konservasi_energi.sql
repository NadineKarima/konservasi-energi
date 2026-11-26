-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 12, 2026 at 02:55 PM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `konservasi_energi`
--

-- --------------------------------------------------------

--
-- Table structure for table `checklist`
--

CREATE TABLE `checklist` (
  `id_checklist` int(11) NOT NULL,
  `nis` int(10) DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `jam` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checklist`
--

INSERT INTO `checklist` (`id_checklist`, `nis`, `id_user`, `tanggal`, `jam`) VALUES
(1, 12345, NULL, '2026-10-21', '13:27:00'),
(2, 12347, NULL, '2026-10-21', '19:05:00'),
(4, 12348, NULL, '2026-10-21', '19:07:00'),
(5, 12348, NULL, '2026-10-22', '09:35:00'),
(6, 12348, NULL, '2026-10-22', '09:38:00'),
(7, 12348, NULL, '2026-10-22', '09:38:00'),
(8, 12346, NULL, '2026-10-25', '21:40:00'),
(9, 12345, NULL, '2026-11-11', '07:15:00'),
(10, 12345, NULL, '2026-11-12', '07:17:00'),
(11, 12345, NULL, '2026-11-12', '20:32:00');

-- --------------------------------------------------------

--
-- Table structure for table `detail_checklist`
--

CREATE TABLE `detail_checklist` (
  `id_detail` int(11) NOT NULL,
  `id_checklist` int(11) NOT NULL,
  `id_perangkat` int(11) NOT NULL,
  `tahapan` enum('SEBELUM','SELAMA','SESUDAH','5R') NOT NULL,
  `kondisi` varchar(100) NOT NULL,
  `status` enum('Ya','Tidak') NOT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_checklist`
--

INSERT INTO `detail_checklist` (`id_detail`, `id_checklist`, `id_perangkat`, `tahapan`, `kondisi`, `status`, `keterangan`) VALUES
(1, 1, 25, 'SEBELUM', 'Mati', 'Ya', ''),
(2, 2, 11, 'SEBELUM', 'Mati', 'Ya', ''),
(4, 4, 1, '5R', 'Mati', 'Ya', ''),
(5, 5, 25, 'SEBELUM', 'mati', 'Ya', ''),
(6, 6, 25, 'SELAMA', 'menyala', 'Ya', 'timer nyala'),
(7, 7, 25, 'SESUDAH', 'mati', 'Ya', ''),
(8, 8, 11, 'SEBELUM', 'mati', 'Ya', ''),
(9, 9, 25, 'SEBELUM', 'mati', 'Ya', ''),
(10, 9, 25, 'SELAMA', 'menyala', 'Ya', ''),
(11, 9, 25, 'SESUDAH', 'dimatikan', 'Ya', ''),
(12, 9, 25, '5R', 'standby', 'Ya', ''),
(13, 10, 25, 'SEBELUM', 'mati', 'Ya', ''),
(14, 10, 25, 'SELAMA', 'menyalal', 'Ya', ''),
(15, 10, 25, 'SESUDAH', 'dimatikan', 'Ya', ''),
(16, 10, 25, '5R', 'syandby', 'Ya', ''),
(17, 11, 25, 'SEBELUM', 'mati', 'Ya', ''),
(18, 11, 25, 'SELAMA', 'menyala', 'Tidak', 'mati sendiri'),
(19, 11, 25, 'SESUDAH', 'dimatikan', 'Ya', ''),
(20, 11, 25, '5R', 'standby', 'Ya', '');

-- --------------------------------------------------------

--
-- Table structure for table `perangkat`
--

CREATE TABLE `perangkat` (
  `id_perangkat` int(11) NOT NULL,
  `nama_perangkat` varchar(100) NOT NULL,
  `jenis` varchar(50) NOT NULL,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `perangkat`
--

INSERT INTO `perangkat` (`id_perangkat`, `nama_perangkat`, `jenis`, `keterangan`) VALUES
(1, 'Lab 1 - Komputer 01', 'Komputer', 'Lab Komputer 1'),
(2, 'Lab 1 - Komputer 02', 'Komputer', 'Lab Komputer 1'),
(3, 'Lab 1 - Komputer 03', 'Komputer', 'Lab Komputer 1'),
(4, 'Lab 1 - Komputer 04', 'Komputer', 'Lab Komputer 1'),
(5, 'Lab 1 - Komputer 05', 'Komputer', 'Lab Komputer 1'),
(6, 'Lab 1 - Komputer 06', 'Komputer', 'Lab Komputer 1'),
(7, 'Lab 1 - Komputer 07', 'Komputer', 'Lab Komputer 1'),
(8, 'Lab 1 - Komputer 08', 'Komputer', 'Lab Komputer 1'),
(9, 'Lab 1 - Komputer 09', 'Komputer', 'Lab Komputer 1'),
(10, 'Lab 1 - Komputer 10', 'Komputer', 'Lab Komputer 1'),
(11, 'Lab 1 - Komputer 11', 'Komputer', 'Lab Komputer 1'),
(12, 'Lab 1 - Komputer 12', 'Komputer', 'Lab Komputer 1'),
(13, 'Lab 1 - Komputer 13', 'Komputer', 'Lab Komputer 1'),
(14, 'Lab 1 - Komputer 14', 'Komputer', 'Lab Komputer 1'),
(15, 'Lab 1 - Komputer 15', 'Komputer', 'Lab Komputer 1'),
(16, 'Lab 1 - Komputer 16', 'Komputer', 'Lab Komputer 1'),
(17, 'Lab 1 - Komputer 17', 'Komputer', 'Lab Komputer 1'),
(18, 'Lab 1 - Komputer 18', 'Komputer', 'Lab Komputer 1'),
(19, 'Lab 1 - Komputer 19', 'Komputer', 'Lab Komputer 1'),
(20, 'Lab 1 - Komputer 20', 'Komputer', 'Lab Komputer 1'),
(21, 'Lab 1 - Lampu 1', 'Lampu', 'Lab Komputer 1'),
(22, 'Lab 1 - Lampu 2', 'Lampu', 'Lab Komputer 1'),
(23, 'Lab 1 - Lampu 3', 'Lampu', 'Lab Komputer 1'),
(24, 'Lab 1 - Lampu 4', 'Lampu', 'Lab Komputer 1'),
(25, 'Lab 1 - AC 1', 'Pendingin', 'Lab Komputer 1'),
(26, 'Lab 1 - Proyektor', 'Proyektor', 'Lab Komputer 1');

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `nis` int(10) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kelas` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `siswa`
--

INSERT INTO `siswa` (`nis`, `nama`, `kelas`) VALUES
(12345, 'Rennatha', 'XI RPL2'),
(12346, 'Shafira', 'XI RPL2'),
(12347, 'Erika', 'XI RPL1'),
(12348, 'Nadine', 'XI RPL2'),
(12349, 'Noviana', 'XI RPL2'),
(12350, 'Shilvanes', 'XI RPL2');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `level` enum('Admin','Guru','Siswa') NOT NULL,
  `nis` int(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `username`, `password`, `nama`, `level`, `nis`) VALUES
(1, 'admin', '$2y$10$MQ/ngSo2GzmYuUK/4/irGOzoPR5oTE4uHi7txJaJ5qzIVer5.cZ5e', 'Administrator', 'Admin', NULL),
(2, 'guru', '$2y$10$Vu1epxK4MKvkqW5n1ckN/.3bxwbOQ5cZN4VDKdeY7emLz55fyz3BK', 'Guru', 'Guru', NULL),
(3, 'siswa', '$2y$10$RKDXj/.ipNYrBO3LHWdk8eNRJwrKQSH2q7UyPUNjvdPScLAQMYhue', 'Siswa(main)', 'Siswa', 12348),
(4, '12345', '$2y$10$Yc9YmV7oN2pvwN5.jB2ZzeGL1nZJeyrmcPnSnOZukKwKOrbQVwrPW', 'Rennatha', 'Siswa', 12345),
(5, '12348', '$2y$10$77VGXzOt4AQeDDNSFXsqUuJx9DBT8HSynhBrUpByUWRT/tScz4fVu', 'Nadine', 'Siswa', 12348),
(6, '12346', '$2y$10$O8qtuEHlOqg.QfyTaL0dxuVgmes5P3IIezyTC.gTW78dsXEu9AzXW', 'Shafira', 'Siswa', 12346),
(7, '12349', '$2y$10$BgPSR.FOTiwCRDH71EL3ieMn8Pe40Pv8LiO63Lo/RCEVoRvPolEPm', 'Noviana', 'Siswa', 12349),
(8, '12347', '$2y$10$9RAtmCTmiFK7c1T.mXk/KOAk89qQqtwm438B8F0fNxMoXiF0aCq/u', 'Erika', 'Siswa', 12347),
(9, '12350', '$2y$10$NM8k/rTO.RlXL8Rdk/Sh2OYtZJMYvVK8s5xoykp19JMtbLSlUx6iW', 'Shilvanes', 'Siswa', 12350);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `checklist`
--
ALTER TABLE `checklist`
  ADD PRIMARY KEY (`id_checklist`),
  ADD KEY `idx_nis` (`nis`),
  ADD KEY `idx_checklist_user` (`id_user`);

--
-- Indexes for table `detail_checklist`
--
ALTER TABLE `detail_checklist`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `idx_checklist` (`id_checklist`),
  ADD KEY `idx_perangkat` (`id_perangkat`);

--
-- Indexes for table `perangkat`
--
ALTER TABLE `perangkat`
  ADD PRIMARY KEY (`id_perangkat`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`nis`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `uk_username` (`username`),
  ADD KEY `idx_users_nis` (`nis`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `checklist`
--
ALTER TABLE `checklist`
  MODIFY `id_checklist` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `detail_checklist`
--
ALTER TABLE `detail_checklist`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `perangkat`
--
ALTER TABLE `perangkat`
  MODIFY `id_perangkat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `checklist`
--
ALTER TABLE `checklist`
  ADD CONSTRAINT `fk_checklist_siswa` FOREIGN KEY (`nis`) REFERENCES `siswa` (`nis`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_checklist_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `detail_checklist`
--
ALTER TABLE `detail_checklist`
  ADD CONSTRAINT `fk_detail_checklist` FOREIGN KEY (`id_checklist`) REFERENCES `checklist` (`id_checklist`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detail_perangkat` FOREIGN KEY (`id_perangkat`) REFERENCES `perangkat` (`id_perangkat`) ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_siswa` FOREIGN KEY (`nis`) REFERENCES `siswa` (`nis`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
