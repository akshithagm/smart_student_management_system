-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 29, 2026 at 08:47 PM
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
-- Database: `smart_student_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `admin_id` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `admin_id`, `full_name`, `email`, `phone`, `password`, `created_at`) VALUES
(1, 'ADM002', 'shalith', 'shalith23@gmail.com', '8867168304', '$2y$10$NVUgjtNMUm200iVAlEbpce/h.tVz314n5V300aOCgFVE6f0QfoSa.', '2026-07-26 10:21:14');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `total_classes` int(11) NOT NULL,
  `attended_classes` int(11) NOT NULL,
  `attendance_percentage` decimal(5,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `total_classes`, `attended_classes`, `attendance_percentage`, `created_at`, `updated_at`) VALUES
(1, 'STU004', 100, 86, 86.00, '2026-07-26 09:31:17', '2026-07-26 09:31:17'),
(2, 'STU001', 1, 0, 0.00, '2026-07-26 11:41:08', '2026-07-26 12:05:06'),
(3, 'STU003', 1, 0, 0.00, '2026-07-26 12:28:13', '2026-07-26 12:28:13'),
(4, 'STU006', 1, 1, 100.00, '2026-07-26 16:21:43', '2026-07-26 16:21:43'),
(5, 'STU007', 1, 1, 100.00, '2026-07-26 16:37:01', '2026-07-26 16:37:01');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_records`
--

CREATE TABLE `attendance_records` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_records`
--

INSERT INTO `attendance_records` (`id`, `student_id`, `attendance_date`, `status`, `created_at`) VALUES
(1, 'STU001', '2026-07-26', 'Absent', '2026-07-26 11:41:08'),
(2, 'STU003', '2026-07-26', 'Absent', '2026-07-26 12:28:13'),
(3, 'STU006', '2026-07-26', 'Present', '2026-07-26 16:21:43'),
(4, 'STU007', '2026-07-26', 'Present', '2026-07-26 16:37:01');

-- --------------------------------------------------------

--
-- Table structure for table `marks`
--

CREATE TABLE `marks` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `internal_marks` decimal(5,2) NOT NULL,
  `maximum_marks` decimal(5,2) DEFAULT 100.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marks`
--

INSERT INTO `marks` (`id`, `student_id`, `subject_name`, `internal_marks`, `maximum_marks`, `created_at`, `updated_at`) VALUES
(1, 'STU004', 'Python', 85.00, 100.00, '2026-07-26 09:32:47', '2026-07-26 09:32:47'),
(2, 'STU004', 'Database Management System', 78.00, 100.00, '2026-07-26 09:32:47', '2026-07-26 09:32:47'),
(3, 'STU004', 'Artificial Intelligence', 75.00, 100.00, '2026-07-26 09:32:47', '2026-07-26 13:42:52'),
(4, 'STU002', 'python', 0.29, 0.30, '2026-07-26 13:36:28', '2026-07-26 13:36:28'),
(5, 'STU006', 'Artificial Intelligence', 70.00, 100.00, '2026-07-26 16:22:12', '2026-07-26 16:22:12'),
(6, 'STU007', 'English', 89.00, 100.00, '2026-07-26 16:37:48', '2026-07-26 16:37:48');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `room_name` varchar(100) DEFAULT NULL,
  `room_type` enum('Classroom','Lab','Seminar Hall') DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `room_status` enum('Available','Occupied','Maintenance') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_number`, `room_name`, `room_type`, `capacity`, `floor`, `room_status`, `created_at`) VALUES
(1, 'A101', 'classroom', 'Lab', 45, 'first floor', 'Available', '2026-07-26 06:05:41'),
(2, 'A102', 'computer lab 4', 'Lab', 58, 'second floor', 'Maintenance', '2026-07-26 06:05:41'),
(3, 'A103', 'classroom 3', 'Classroom', 39, 'second floor', 'Available', '2026-07-26 06:05:41'),
(4, 'A104', 'classroom 5', 'Seminar Hall', 70, 'third floor', 'Occupied', '2026-07-26 06:05:41'),
(5, 'B201', NULL, NULL, NULL, NULL, 'Available', '2026-07-26 06:05:41'),
(6, 'B202', NULL, NULL, NULL, NULL, 'Occupied', '2026-07-26 06:05:41'),
(7, 'B203', NULL, NULL, NULL, NULL, 'Available', '2026-07-26 06:05:41'),
(8, 'C301', NULL, NULL, NULL, NULL, 'Available', '2026-07-26 06:05:41'),
(9, 'C302', NULL, NULL, NULL, NULL, 'Maintenance', '2026-07-26 06:05:41'),
(10, 'C303', NULL, NULL, NULL, NULL, 'Available', '2026-07-26 06:05:41'),
(11, 'A106', 'computer lab 4', 'Lab', 55, 'first floor', 'Available', '2026-07-26 14:23:04'),
(12, 'A109', 'classroom 3', 'Classroom', 45, 'first flour', 'Occupied', '2026-07-26 14:25:06'),
(13, 'A100', 'classroom', 'Seminar Hall', 59, 'third floor', 'Occupied', '2026-07-26 15:38:32');

-- --------------------------------------------------------

--
-- Table structure for table `room_allocations`
--

CREATE TABLE `room_allocations` (
  `id` int(11) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `teacher_name` varchar(100) NOT NULL,
  `time_slot` varchar(50) NOT NULL,
  `status` enum('Allocated','Available') DEFAULT 'Allocated',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_allocations`
--

INSERT INTO `room_allocations` (`id`, `room_number`, `class_name`, `teacher_name`, `time_slot`, `status`, `created_at`, `updated_at`) VALUES
(1, 'A101', 'BCA (AI & ML) - 1st Year', 'Dr. Anil Kumar', '09:00 AM - 10:00 AM', 'Allocated', '2026-07-26 06:06:34', '2026-07-26 06:06:34'),
(2, 'A102', 'BCA - 2nd Year', 'Mrs. Priya Sharma', '10:00 AM - 11:00 AM', 'Allocated', '2026-07-26 06:06:34', '2026-07-26 06:06:34'),
(3, 'B201', 'BSc - 1st Year', 'Mr. Rahul Verma', '11:00 AM - 12:00 PM', 'Allocated', '2026-07-26 06:06:34', '2026-07-26 06:06:34'),
(4, 'B203', 'BCom - 3rd Year', 'Mrs. Sneha Reddy', '01:00 PM - 02:00 PM', 'Allocated', '2026-07-26 06:06:34', '2026-07-26 06:06:34'),
(5, 'C301', 'BCA (AI & ML) - 3rd Year', 'Mr. Kiran Patel', '02:00 PM - 03:00 PM', 'Allocated', '2026-07-26 06:06:34', '2026-07-26 06:06:34'),
(6, 'A100', 'BCA(3rd year)', 'sujatha', '9:00 to 10:00', 'Available', '2026-07-26 16:04:32', '2026-07-26 16:12:42');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `department` varchar(100) NOT NULL,
  `year` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `full_name`, `email`, `phone`, `department`, `year`, `password`, `created_at`) VALUES
(1, 'STU001', 'Akshitha Gm', 'akshithagm702@gmail.com', '4567890313', 'BCA (AI & ML)', '1st Year', '1234', '2026-07-26 06:17:30'),
(2, 'STU002', 'spoorty', 'spoo702@gmail.com', '1234567890', 'BCA', '3rd Year', '1234567', '2026-07-26 07:11:31'),
(3, 'STU003', 'amulya', 'amulya23@gmail.com', '4567890313', 'BSc', '2nd Year', '1234567', '2026-07-26 08:51:18'),
(4, 'STU004', 'Akshuu', 'akshuu23@gmail.com', '7483176714', 'BCA', '3rd Year', '748317', '2026-07-26 08:53:11'),
(5, 'STU005', 'komal', 'komal23@gmail.com', '9380308921', 'BSC', '2nd Year', '$2y$10$v7T7sVsq87E82XsTsH/c8u9alv2./.ojNzzI1nq9yo8QKcIw6v4nW', '2026-07-26 10:57:52'),
(6, 'STU006', 'sharadi', 'sharadi23@gmail.com', '4567890313', 'BCA (AI & ML)', '3rd Year', 'sharadi@#123', '2026-07-26 16:17:19'),
(7, 'STU007', 'sudeep', 'sudeep23@gmail.com', '7483176714', 'BCA', '2nd Year', 'sudeep@#1234', '2026-07-26 16:34:59');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `teacher_id` varchar(20) DEFAULT NULL,
  `teacher_name` varchar(100) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `teacher_id`, `teacher_name`, `subject`, `phone`, `email`, `created_at`) VALUES
(1, 'TCH001', 'Dr. Anil Kumar', 'Python Programming', '9876543210', 'anil.kumar@ssms.com', '2026-07-26 06:04:59'),
(2, 'TCH002', 'Mrs. Priya Sharma', 'Database Management', '9876543211', 'priya.sharma@ssms.com', '2026-07-26 06:04:59'),
(3, 'TCH003', 'Mr. Rahul Verma', 'Artificial Intelligence', '9876543212', 'rahul.verma@ssms.com', '2026-07-26 06:04:59'),
(4, 'TCH004', 'Mrs. Sneha Reddy', 'Machine Learning', '9876543213', 'sneha.reddy@ssms.com', '2026-07-26 06:04:59'),
(5, 'TCH005', 'Mr. Kiran Patel', 'Web Technology', '9876543214', 'kiran.patel@ssms.com', '2026-07-26 06:04:59');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `admin_id` (`admin_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `attendance_records`
--
ALTER TABLE `attendance_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `marks`
--
ALTER TABLE `marks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_number` (`room_number`);

--
-- Indexes for table `room_allocations`
--
ALTER TABLE `room_allocations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `teacher_id` (`teacher_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `attendance_records`
--
ALTER TABLE `attendance_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `marks`
--
ALTER TABLE `marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `room_allocations`
--
ALTER TABLE `room_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `marks`
--
ALTER TABLE `marks`
  ADD CONSTRAINT `marks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
