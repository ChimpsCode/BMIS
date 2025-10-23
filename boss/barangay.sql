-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 22, 2025 at 09:36 AM
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
-- Database: `boss`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_complaints`
--

CREATE TABLE `tbl_complaints` (
  `complaint_id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `details` text NOT NULL,
  `status` varchar(50) NOT NULL,
  `handled_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_documents`
--

CREATE TABLE `tbl_documents` (
  `doc_id` int(11) NOT NULL,
  `doc_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `fee` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_documents`
--

INSERT INTO `tbl_documents` (`doc_id`, `doc_name`, `description`, `fee`) VALUES
(1, 'Brgy Clearance', 'Brgy Clearance', 50.00),
(2, 'Certificate of Residency', 'Certificate of Residency', 30.00),
(3, 'Certificate of Indigency', 'Certificate of Indigency', 0.00),
(4, 'Business Permit', 'Business Permit', 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_households`
--

CREATE TABLE `tbl_households` (
  `household_id` int(11) NOT NULL,
  `house_no` varchar(50) NOT NULL,
  `purok` varchar(50) NOT NULL,
  `head_resident_id` int(11) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `street` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_inquiries`
--

CREATE TABLE `tbl_inquiries` (
  `inquiry_id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `date_sent` datetime DEFAULT current_timestamp(),
  `status` enum('Pending','Replied','Closed') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_logs`
--

CREATE TABLE `tbl_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity` varchar(255) NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `action_type` enum('INSERT','UPDATE','DELETE','LOGIN','LOGOUT','VIEW') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_logs`
--

INSERT INTO `tbl_logs` (`log_id`, `user_id`, `activity`, `timestamp`, `ip_address`, `action_type`) VALUES
(1, NULL, 'New document request by resident_id 1', '2025-10-16 18:40:58', NULL, NULL),
(2, NULL, 'New document request by resident_id 1', '2025-10-16 18:58:08', NULL, NULL),
(3, NULL, 'New document request by resident_id 1', '2025-10-16 19:03:03', NULL, NULL),
(4, NULL, 'New document request by resident_id 1', '2025-10-16 19:11:17', NULL, NULL),
(5, NULL, 'New document request by resident_id 1', '2025-10-16 19:15:08', NULL, NULL),
(6, NULL, 'New document request by resident_id 1', '2025-10-16 19:15:16', NULL, NULL),
(7, NULL, 'New document request by resident_id 1', '2025-10-16 19:15:27', NULL, NULL),
(8, NULL, 'New document request by resident_id 1', '2025-10-16 19:15:35', NULL, NULL),
(9, NULL, 'New document request by resident_id 1', '2025-10-16 19:27:43', NULL, NULL),
(10, NULL, 'New document request by resident_id 1', '2025-10-16 19:27:51', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_messages`
--

CREATE TABLE `tbl_messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `date_sent` datetime DEFAULT current_timestamp(),
  `status` enum('read','unread') DEFAULT 'unread'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_officials`
--

CREATE TABLE `tbl_officials` (
  `official_id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `term_start` date NOT NULL,
  `term_end` date DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_requests`
--

CREATE TABLE `tbl_requests` (
  `request_id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `doc_id` int(11) DEFAULT NULL,
  `status` varchar(50) NOT NULL,
  `date_requested` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `payment_method` enum('GCash','Cash') DEFAULT 'GCash',
  `payment_status` enum('Pending','Paid') DEFAULT 'Pending',
  `delivery_option` enum('Soft Copy','Pickup') DEFAULT 'Pickup',
  `release_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_requests`
--

INSERT INTO `tbl_requests` (`request_id`, `resident_id`, `doc_id`, `status`, `date_requested`, `notes`, `payment_method`, `payment_status`, `delivery_option`, `release_date`) VALUES
(1, 1, 1, 'Pending', '2025-10-16 18:04:16', 'hello, I need it po sana ngayung araw salamat.', 'GCash', 'Pending', 'Pickup', NULL),
(2, 1, 1, 'Pending', '2025-10-16 18:40:58', 'thanks po', 'GCash', 'Pending', 'Pickup', NULL),
(3, 1, 1, 'Pending', '2025-10-16 18:58:07', 'adawewtgaijwgdwagbwa', 'GCash', 'Pending', 'Pickup', NULL),
(4, 1, 2, 'Pending', '2025-10-16 19:03:03', 'adada lkjhwaiod h okayed', 'GCash', 'Pending', 'Pickup', NULL),
(5, 1, 1, 'Pending', '2025-10-16 19:11:17', 'asdsad', 'GCash', 'Pending', 'Pickup', NULL),
(6, 1, 1, 'Pending', '2025-10-16 19:15:08', 'asdsad', 'GCash', 'Pending', 'Pickup', NULL),
(7, 1, 1, 'Pending', '2025-10-16 19:15:16', 'sadas', 'GCash', 'Pending', 'Pickup', NULL),
(8, 1, 4, 'Pending', '2025-10-16 19:15:27', 'asdsad', 'GCash', 'Pending', 'Pickup', NULL),
(9, 1, 1, 'Pending', '2025-10-16 19:15:35', 'asdsad', 'GCash', 'Pending', 'Pickup', NULL),
(10, 1, 1, 'Pending', '2025-10-16 19:27:43', 'adad', 'GCash', 'Pending', 'Pickup', NULL),
(11, 1, 1, 'Pending', '2025-10-16 19:27:51', 'adad', 'GCash', 'Pending', 'Pickup', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_residents`
--

CREATE TABLE `tbl_residents` (
  `resident_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `birthdate` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `address` varchar(255) NOT NULL,
  `household_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_residents`
--

INSERT INTO `tbl_residents` (`resident_id`, `first_name`, `last_name`, `birthdate`, `gender`, `address`, `household_id`) VALUES
(1, 'asdassd', 'asdasd', '2027-02-02', 'Male', 'dad', NULL),
(2, 'Juan', 'Dela Cruz', '1980-01-01', 'Male', '123 Barangay St, City', NULL),
(3, 'Juan', 'Dela Cruz', '1980-01-01', 'Male', '123 Barangay St, City', NULL),
(4, 'Juan', 'Dela Cruz', '1980-01-01', 'Male', '123 Barangay St, City', NULL),
(5, 'Juan', 'Dela Cruz', '1980-01-01', 'Male', '123 Barangay St, City', NULL),
(6, 'Juan', 'Dela Cruz', '1980-01-01', 'Male', '123 Barangay St, City', NULL),
(7, 'Juan', 'Dela Cruz', '1980-01-01', 'Male', '123 Barangay St, City', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff','resident','barangay_captain') NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `account_status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`user_id`, `username`, `password_hash`, `role`, `resident_id`, `last_login`, `account_status`) VALUES
(1, 'admin', '$2y$10$H3C0k6JvQq1L8F9z5n6N.ur2Kj1ZsQH3lHqzKXnK9B0pV9ZzQxj12', 'admin', NULL, NULL, 'Active'),
(3, 'admin123', '$2y$10$4BwbYg/8lQXZvPZ9IbtSRuSgWvTmmQEq6.ixAX9DsK2uPOozwRZxS', 'admin', NULL, NULL, 'Active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_complaints`
--
ALTER TABLE `tbl_complaints`
  ADD PRIMARY KEY (`complaint_id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `handled_by` (`handled_by`);

--
-- Indexes for table `tbl_documents`
--
ALTER TABLE `tbl_documents`
  ADD PRIMARY KEY (`doc_id`);

--
-- Indexes for table `tbl_households`
--
ALTER TABLE `tbl_households`
  ADD PRIMARY KEY (`household_id`),
  ADD KEY `fk_head_resident` (`head_resident_id`);

--
-- Indexes for table `tbl_inquiries`
--
ALTER TABLE `tbl_inquiries`
  ADD PRIMARY KEY (`inquiry_id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `tbl_logs`
--
ALTER TABLE `tbl_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tbl_messages`
--
ALTER TABLE `tbl_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `tbl_officials`
--
ALTER TABLE `tbl_officials`
  ADD PRIMARY KEY (`official_id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `resident_id` (`resident_id`),
  ADD KEY `doc_id` (`doc_id`);

--
-- Indexes for table `tbl_residents`
--
ALTER TABLE `tbl_residents`
  ADD PRIMARY KEY (`resident_id`),
  ADD KEY `household_id` (`household_id`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `resident_id` (`resident_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_complaints`
--
ALTER TABLE `tbl_complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_documents`
--
ALTER TABLE `tbl_documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_households`
--
ALTER TABLE `tbl_households`
  MODIFY `household_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_inquiries`
--
ALTER TABLE `tbl_inquiries`
  MODIFY `inquiry_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_logs`
--
ALTER TABLE `tbl_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tbl_messages`
--
ALTER TABLE `tbl_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_officials`
--
ALTER TABLE `tbl_officials`
  MODIFY `official_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `tbl_residents`
--
ALTER TABLE `tbl_residents`
  MODIFY `resident_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_users`
--
ALTER TABLE `tbl_users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_complaints`
--
ALTER TABLE `tbl_complaints`
  ADD CONSTRAINT `tbl_complaints_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `tbl_residents` (`resident_id`),
  ADD CONSTRAINT `tbl_complaints_ibfk_2` FOREIGN KEY (`handled_by`) REFERENCES `tbl_users` (`user_id`);

--
-- Constraints for table `tbl_households`
--
ALTER TABLE `tbl_households`
  ADD CONSTRAINT `fk_head_resident` FOREIGN KEY (`head_resident_id`) REFERENCES `tbl_residents` (`resident_id`);

--
-- Constraints for table `tbl_inquiries`
--
ALTER TABLE `tbl_inquiries`
  ADD CONSTRAINT `tbl_inquiries_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `tbl_residents` (`resident_id`);

--
-- Constraints for table `tbl_logs`
--
ALTER TABLE `tbl_logs`
  ADD CONSTRAINT `tbl_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`user_id`);

--
-- Constraints for table `tbl_messages`
--
ALTER TABLE `tbl_messages`
  ADD CONSTRAINT `tbl_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `tbl_users` (`user_id`),
  ADD CONSTRAINT `tbl_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `tbl_users` (`user_id`);

--
-- Constraints for table `tbl_officials`
--
ALTER TABLE `tbl_officials`
  ADD CONSTRAINT `tbl_officials_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `tbl_residents` (`resident_id`),
  ADD CONSTRAINT `tbl_officials_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `tbl_users` (`user_id`);

--
-- Constraints for table `tbl_requests`
--
ALTER TABLE `tbl_requests`
  ADD CONSTRAINT `tbl_requests_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `tbl_residents` (`resident_id`),
  ADD CONSTRAINT `tbl_requests_ibfk_2` FOREIGN KEY (`doc_id`) REFERENCES `tbl_documents` (`doc_id`);

--
-- Constraints for table `tbl_residents`
--
ALTER TABLE `tbl_residents`
  ADD CONSTRAINT `tbl_residents_ibfk_1` FOREIGN KEY (`household_id`) REFERENCES `tbl_households` (`household_id`);

--
-- Constraints for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD CONSTRAINT `tbl_users_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `tbl_residents` (`resident_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
