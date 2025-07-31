-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 30, 2025 at 09:06 PM
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
-- Database: `prottoy_foundation`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `admin_id` int(11) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_tokens`
--

CREATE TABLE `auth_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `donations`
--

CREATE TABLE `donations` (
  `id` int(11) NOT NULL,
  `donor_name` varchar(100) DEFAULT NULL,
  `donor_email` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `method` varchar(50) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `receipt` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donations`
--

INSERT INTO `donations` (`id`, `donor_name`, `donor_email`, `amount`, `method`, `date`, `receipt`) VALUES
(48, 'Robin Hossain', 'rh503649@gmail.com', 300.00, 'Cash', '2025-07-05', NULL),
(49, '', '', 0.00, '', '0000-00-00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `donors`
--

CREATE TABLE `donors` (
  `id` int(11) NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `designation` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `donors`
--

INSERT INTO `donors` (`id`, `user_id`, `designation`) VALUES
(2, 2, NULL),
(7, 7, NULL),
(10, 10, NULL),
(11, 12, NULL),
(12, 13, NULL),
(13, 14, NULL),
(14, 15, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `paid_to` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `proof` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `category`, `description`, `amount`, `paid_to`, `date`, `proof`) VALUES
(9, 'Help', 'A poor person helping', 900.00, 'old man', '2025-06-28', NULL),
(10, 'Donor', 'For Poor People', 200.00, 'Rahat Parves Rahi', '2025-07-05', NULL),
(11, 'Donor', 'Rahat', 900.00, 'Rahat Parves Rahi', '2025-07-03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `attempt_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `otp_code`, `expires_at`, `is_used`) VALUES
(4, 2, '392918', '2025-07-05 22:41:14', 0),
(6, 12, '800047', '2025-07-06 02:51:33', 1),
(7, 13, '173289', '2025-07-06 03:01:27', 1),
(8, 14, '974230', '2025-07-06 03:06:56', 0),
(9, 15, '939510', '2025-07-06 03:09:26', 1),
(10, 12, '470543', '2025-07-06 03:12:46', 1),
(11, 12, '274793', '2025-07-06 03:16:21', 1),
(12, 12, '508941', '2025-07-06 03:29:05', 1),
(13, 12, '654302', '2025-07-06 06:02:06', 1),
(14, 12, '230512', '2025-07-06 06:11:26', 1),
(15, 12, '103724', '2025-07-06 06:21:37', 1),
(16, 12, '710754', '2025-07-06 16:38:04', 1),
(17, 12, '164393', '2025-07-19 21:43:51', 1);

-- --------------------------------------------------------

--
-- Table structure for table `reset_password`
--

CREATE TABLE `reset_password` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(75) NOT NULL,
  `home_address` varchar(255) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `profile_picture_url` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` enum('user','admin') DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `designation` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `home_address`, `phone`, `email`, `password`, `created_at`, `profile_picture_url`, `updated_at`, `role`, `status`, `designation`) VALUES
(2, 'Robin', 'Amdala,Kashtoshagra, Shibalaya, Manikganj', '01312427030', 'rh503648@gmail.com', '$2y$10$3AYBIzyySxm/2I5D7UJBe.0EiXYvzNxn2FQ4j4V.LhvQy.1JauYjy', '2025-06-23 15:50:29', 'uploads/profile_pics/profile_685d5682721b2_e6b4d2ad104c8199.jpg', '2025-07-19 15:43:17', 'user', '0', 'President'),
(7, 'Rupom', 'Kashtoshagra, Shibalaya, Manikganj', '01312427044', 'rupom@gmail.com', '$2y$10$qd2UDjk0CX0hACKB0p0pHuy6gPSHlF.46J0d2X/GNiWHRlbWYQEQy', '2025-06-24 22:31:36', NULL, '2025-07-06 09:55:18', NULL, '0', NULL),
(10, 'Blockchain Developer', 'Kashtoshagra, Shibalaya, Manikganj', '013124270389', 'rahat143@gmail.com', '$2y$10$PNpq1Il7ui7sEOWPeMCvCu3KWRa6PU/lFmN9zBm8aR5lTmdqekoQi', '2025-06-24 22:59:12', 'uploads/profile_pics/user_10_411e81624d99d9901ee5.jpg', '2025-07-06 02:03:56', 'user', '1', NULL),
(11, 'Abdul Baki Ruhin', 'Kashtoshagra, Shibalaya, Manikganj', '01312427029', 'ruhin@gmail.com', '$2y$10$S1MJhYA/geFT2zUrckncYetosV.tBH.mKoS/bP8Vdzn1tdoQ6cHOu', '2025-06-25 15:34:32', 'uploads/profile_pics/profile_685bc2a7eccdb.jpg', '2025-07-07 10:37:33', 'user', '0', 'General Secretary'),
(12, 'Robin Hossain', 'Kashtoshagra, Shibalaya, Manikganj', '01312427040', 'rh503649@gmail.com', '$2y$10$Jv5a64C0cmrzXtCqw2CqpumtPjWZWjRjurrqJSXnsmNvxTkchkcQu', '2025-07-06 02:16:08', 'uploads/profile_pics/user_12_9799def38281efd7030b.jpg', '2025-07-19 15:40:21', 'admin', '1', NULL),
(13, 'Rahat Parves Rahi', 'Kashtoshagra, Shibalaya, Manikganj', '01312427050', 'rahat1046@gmail.com', '$2y$10$ghiHpEzt4UEg.Jq1.87uFuCxYNWcDAF6FtQLP2t1Cm8ZfBVT0cHpO', '2025-07-06 02:55:52', NULL, '2025-07-06 00:03:10', 'user', '1', NULL),
(14, 'Rahat Parves Rahi', 'Kashtoshagra, Shibalaya, Manikganj', '01312427060', 'rahat1043@gmail.com', '$2y$10$VERb22QeT/uEJKV1B.Xl4u434XZX7leFOjXhoaX.joh9IKMpF6f6W', '2025-07-06 03:01:44', NULL, '2025-07-06 10:21:27', 'admin', NULL, NULL),
(15, 'Rahat Parves Rahi', 'Kashtoshagra, Shibalaya, Manikganj', '01312427080', 'rahatparves10@gmail.com', '$2y$10$iy6ZiByOZ9aoIypBu7jVbenKZsJT6IkaeXvmwzC.w2LcXs/pMxl/y', '2025-07-06 03:04:14', NULL, '2025-07-06 10:13:42', 'user', '1', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `token_hash_index` (`token_hash`);

--
-- Indexes for table `donations`
--
ALTER TABLE `donations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `donors`
--
ALTER TABLE `donors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`otp_code`),
  ADD UNIQUE KEY `otp_code` (`otp_code`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reset_password`
--
ALTER TABLE `reset_password`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `donations`
--
ALTER TABLE `donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `donors`
--
ALTER TABLE `donors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `reset_password`
--
ALTER TABLE `reset_password`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `auth_tokens`
--
ALTER TABLE `auth_tokens`
  ADD CONSTRAINT `auth_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `donors`
--
ALTER TABLE `donors`
  ADD CONSTRAINT `donors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reset_password`
--
ALTER TABLE `reset_password`
  ADD CONSTRAINT `reset_password_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
