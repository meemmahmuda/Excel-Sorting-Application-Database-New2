-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 23, 2025 at 04:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `excel_practice`
--

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_data`
--

CREATE TABLE `uploaded_data` (
  `id` int(11) NOT NULL,
  `holding_or_tl` varchar(255) DEFAULT NULL,
  `txn_id` varchar(255) DEFAULT NULL,
  `date` varchar(255) DEFAULT NULL,
  `amount` varchar(255) DEFAULT NULL,
  `gateway` varchar(255) DEFAULT NULL,
  `payment_type` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `type` enum('uploaded','unmatched') NOT NULL DEFAULT 'uploaded',
  `file_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uploaded_files`
--

CREATE TABLE `uploaded_files` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `type` enum('uploaded','unmatched') NOT NULL DEFAULT 'uploaded',
  `user_id` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `is_approved`, `role`, `created_at`) VALUES
(1, 'Admin', 'admin123@gmail.com', '$2y$10$OeMQy5ZrDJ4PfCC.Gf/m.uVFgZh.x978BtAtXuP/QW/amq63NDn0G', 1, 'admin', '2025-12-23 03:22:38'),
(2, 'Mahmuda', 'meemmahmuda70@gmail.com', '$2y$10$fr.jM7Feqp.N4UV0gs/ELeyomBttq6oQD2iKM2a9rXsjpfr7RHcyG', 1, 'user', '2025-12-23 03:23:35'),
(3, 'Nahrin', 'nahrinkazi@gmail.com', '$2y$10$WPsBd2Dt.qqxPVNtPE.0B.yh44GZFDC.zgC9pQsXpDAmI5K3ZqQ7y', 1, 'user', '2025-12-23 03:30:32'),
(4, 'Mashriqul', '', '$2y$10$u68U12ss1UA1t9ZHk7UlsO1viiVGu7E24GCaPbVfMTZQXx7jyVt2S', 1, 'user', '2025-12-23 03:31:34'),
(5, 'Rizbi', '', '$2y$10$LiUW8owv612kqKgaSTexG.6JSmtHxu6xreohycYekFEEgRxlWL84W', 1, 'user', '2025-12-23 03:33:42'),
(6, 'Talha', '', '$2y$10$L/cF9MRTqqD0daBR9NPVF.9Hdkco6xFuxZjPa9jz9dxUfgS9klCyu', 1, 'user', '2025-12-23 03:34:46'),
(7, 'Sohel', 'sohel@rksoftwarebd.com', '$2y$10$ym4S3OWW8ViKwpj0KkMpWOgCAsUqNhq7SUTI4l.OiieDNhBt9fstq', 1, 'admin', '2025-12-23 03:36:57'),
(8, 'Selmin', 'selmin@rksoftwarebd.com', '$2y$10$Anb1eV4C26xNDTitqjyhF.z2l6wUGuq.3LQUu17KIPpAXK5pZSRXu', 1, 'admin', '2025-12-23 03:40:18'),
(9, 'Bivas', 'bivas@rksoftwarebd.com', '$2y$10$qTju0ho70xPRdwOj4uP/8ujdKbG57WdAavQQBUUNqMDWY1kOOyrzC', 1, 'admin', '2025-12-23 03:41:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `uploaded_data`
--
ALTER TABLE `uploaded_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`);

--
-- Indexes for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `uploaded_data`
--
ALTER TABLE `uploaded_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `uploaded_data`
--
ALTER TABLE `uploaded_data`
  ADD CONSTRAINT `uploaded_data_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `uploaded_files` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `uploaded_files`
--
ALTER TABLE `uploaded_files`
  ADD CONSTRAINT `uploaded_files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
