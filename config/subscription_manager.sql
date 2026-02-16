

-- ========================================
--      Database: subscription_manager
-- ========================================

-- login credentials:

// Admin ===> username: admin , password: password

// Agent ===> username: agent1 , password: password
// Agent ===> username: yonetwrok , password: 123456









-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 27, 2026 at 12:51 AM
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
-- Database: `subscription_manager`
--

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `client_id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`client_id`, `agent_id`, `full_name`, `email`, `phone`, `address`, `city`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 'Ahmed Mansouri', 'ahmed@example.com', '+212 6 11 22 33 44', NULL, 'Casablanca', NULL, 'active', '2026-01-20 13:18:40', '2026-01-20 13:18:40'),
(2, 2, 'Fatima Zahra', 'fatima@example.com', '+212 6 22 33 44 55', NULL, 'Rabat', NULL, 'active', '2026-01-20 13:18:40', '2026-01-20 13:18:40'),
(3, 2, 'Youssef Benali', 'youssef@example.com', '+212 6 33 44 55 66', NULL, 'Marrakech', NULL, 'active', '2026-01-20 13:18:40', '2026-01-20 13:18:40'),
(4, 2, 'oussama', 'oussama@gmail.com', '0612345678', '', 'TINEJDAD', '', 'active', '2026-01-21 15:44:21', '2026-01-22 19:44:07'),
(5, 2, 'khalid', 'khalid@gmail.com', '+212 6 12 34 56 78', '', 'TINEJDAD', 'test client', 'inactive', '2026-01-23 10:53:02', '2026-01-23 10:58:09'),
(6, 5, 'MED AMIN', 'amin@gmail.com', '+212 6 12 34 56 78', 'tinejdad', 'Errachidia', '', 'active', '2026-01-26 23:39:04', '2026-01-26 23:39:04'),
(7, 5, 'Ahmed Mansouri', 'ahmed@example.com', '+212611223344', NULL, 'Casablanca', 'Monthly client', 'active', '2026-01-26 23:44:31', '2026-01-26 23:44:31'),
(8, 5, 'Fatima Zahra', 'fatima@example.com', '+212622334455', NULL, 'Rabat', 'Yearly subscription', 'active', '2026-01-26 23:44:31', '2026-01-26 23:44:31'),
(9, 5, 'Youssef Benali', 'youssef@example.com', '+212633445566', NULL, 'Marrakech', NULL, 'active', '2026-01-26 23:44:31', '2026-01-26 23:44:31');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `subscription_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `subscription_name` varchar(100) NOT NULL,
  `subscription_type` enum('Monthly','Quarterly','Yearly') NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','cancelled') DEFAULT 'active',
  `auto_renew` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`subscription_id`, `client_id`, `subscription_name`, `subscription_type`, `price`, `start_date`, `end_date`, `status`, `auto_renew`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'Netflix Premium', 'Monthly', 99.00, '2026-01-01', '2026-02-01', 'active', 0, NULL, '2026-01-20 13:18:40', '2026-01-20 13:18:40'),
(2, 1, 'Internet Fiber', 'Monthly', 299.00, '2026-01-01', '2026-02-01', 'active', 0, NULL, '2026-01-20 13:18:40', '2026-01-20 13:18:40'),
(3, 2, 'Spotify Family', 'Yearly', 990.00, '2026-01-01', '2027-01-01', 'active', 0, NULL, '2026-01-20 13:18:40', '2026-01-20 13:18:40'),
(4, 3, 'Office 365', 'Monthly', 149.00, '2025-12-01', '2026-01-01', 'active', 0, NULL, '2026-01-20 13:18:40', '2026-01-20 13:18:40'),
(5, 4, 'NETFLIX', 'Monthly', 99.00, '2026-02-01', '2027-02-28', 'active', 0, NULL, '2026-01-21 15:45:29', '2026-01-22 19:44:21'),
(6, 1, 'NETFLIX', 'Monthly', 99.00, '2026-01-23', '2027-01-31', 'active', 0, NULL, '2026-01-23 09:53:15', '2026-01-23 09:53:15'),
(7, 4, 'office 365', '', 1000.00, '2026-01-23', '2027-01-23', 'active', 0, NULL, '2026-01-23 10:39:13', '2026-01-23 10:39:13'),
(9, 5, 'office 365', 'Monthly', 99.00, '2026-01-23', '2026-02-23', '', 0, NULL, '2026-01-23 10:57:26', '2026-01-23 11:00:03'),
(10, 4, 'amazon prime', 'Monthly', 99.00, '2026-01-23', '2026-02-23', 'active', 0, NULL, '2026-01-23 11:04:52', '2026-01-23 11:04:52'),
(11, 6, 'office 365', 'Monthly', 199.00, '2026-01-27', '2026-02-27', 'active', 0, NULL, '2026-01-26 23:39:27', '2026-01-26 23:39:27'),
(12, 6, 'Internet Fiber', 'Monthly', 250.00, '2026-01-27', '2026-02-27', 'active', 0, NULL, '2026-01-26 23:48:23', '2026-01-26 23:48:23'),
(13, 7, 'Spotify Family', 'Yearly', 999.00, '2026-01-27', '2027-02-27', 'active', 0, NULL, '2026-01-26 23:49:29', '2026-01-26 23:49:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','agent') NOT NULL DEFAULT 'agent',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `full_name`, `phone`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', NULL, 'admin', 'active', '2026-01-20 13:18:39', '2026-01-20 13:18:39'),
(2, 'agent1', 'agent1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Khalid Agent', '+212 6 12 34 56 78', 'agent', 'active', '2026-01-20 13:18:40', '2026-01-20 14:10:19'),
(5, 'yonetwok', 'yonetwok@gmail.com', '$2y$10$FgSYFQVcmwNLMC7P6BdtIewuwtmH194AcuRf.n4aN4rH1tkZpeY7K', 'yonetwok', '0612345678', 'agent', 'active', '2026-01-24 18:27:16', '2026-01-24 18:27:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`client_id`),
  ADD KEY `idx_agent` (`agent_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`subscription_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `subscription_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE;
COMMIT;



