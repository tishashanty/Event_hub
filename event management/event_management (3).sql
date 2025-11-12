-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 12, 2025 at 06:27 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `event_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `first_name`, `last_name`, `email`, `password`, `created_at`) VALUES
(5, 'Event', 'Hub', 'admin@gmail.com', '$2y$10$oa5p7CdtLvIQeG25I9g0MOBJNaI8omxaZMPZ0WIJSVKDiWGkKvjVy', '2025-08-19 05:31:17');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `announcement_text` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by_admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `venue` varchar(100) DEFAULT NULL,
  `created_by_user_type` enum('admin','teacher','student') NOT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `duration` int(11) DEFAULT 60
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time`, `venue`, `created_by_user_type`, `created_by_user_id`, `duration`) VALUES
(1, 'Python Workshop', 'python for all', '2025-07-16', '10:30:00', 'Lab 2', 'teacher', 3, 60),
(2, 'Ai', 'Ai leading to the future', '2025-08-22', '10:15:00', 'Seminar hall', 'admin', 3, 60),
(3, 'C code', 'C code in the future', '2025-08-28', '11:30:00', 'Lab 1', 'teacher', 5, 60),
(4, 'CAMPUS TO CORPORATE', 'A transformative session designed to bridge the gap between campus life and corporate expectations ,equipping student with practical insights and industry -ready skills', '2025-08-06', '09:00:00', 'Yoga Hall', 'admin', 3, 60),
(5, 'Explore IT', 'A program to encourage students', '2025-09-01', '09:45:00', 'Seminar hall', 'admin', 3, 60),
(6, 'workshop', 'AI based event', '2025-08-19', '10:00:00', 'Yoga Hall', 'admin', 3, 60),
(7, 'Art', 'Develop ideas', '2025-08-19', '10:00:00', 'Yoga Hall', 'admin', 3, 60),
(8, 'Development', 'A development to the IT', '2025-08-22', '10:15:00', 'Seminar hall', 'teacher', 8, 60),
(9, 'Banking', 'A explain class about banking', '2025-09-02', '11:30:00', 'Yoga Hall', 'teacher', 8, 60),
(10, 'Network', 'Develop our future', '2025-08-22', '10:30:00', 'Yoga Hall', 'teacher', 5, 60),
(11, 'Idea to identify', 'A product showcase by young game developers.', '2025-08-21', '11:30:00', 'Lab 1', 'teacher', 5, 60),
(12, 'AI prompting', 'AI prompting challenge', '2025-08-21', '11:30:00', 'Seminar hall', 'teacher', 5, 60),
(13, 'AI', 'Development', '2025-08-26', '10:00:00', 'Yoga Hall', 'admin', 5, 60),
(14, 'AI For Everyone', 'In this course you will learn the meaning behind common AI terminology', '2025-09-13', '11:35:00', 'Yoga Hall', 'admin', 5, 60),
(15, 'Future AI', 'A seminar about the future AI', '2025-09-26', '10:30:00', 'Seminar hall', 'teacher', 5, 60),
(17, 'We\'re hiring', 'Job opportunities  for freshers. infopark Hiring 2026 Batch Students BCA,BCOM,MCA BSc etc. pooled Recruitment At BVM College,Cherpunkal', '2025-09-22', '10:00:00', 'Seminar hall', 'student', 9, 60),
(19, 'Develop Future', 'agdjbfaklgtn', '2025-08-14', '20:50:00', 'Room 4', '', 8, 60),
(21, 'abfgfng', 'dghhjjj', '2025-09-30', '10:55:00', 'Mini Theatre', 'student', 8, 60),
(22, 'Cyber Security', 'A orientation section about the cyber protect and security', '2025-11-28', '11:00:00', 'Mini Theatre', 'teacher', 5, 60);

-- --------------------------------------------------------

--
-- Table structure for table `event_requests`
--

CREATE TABLE `event_requests` (
  `request_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `program_title` varchar(255) NOT NULL,
  `program_description` text NOT NULL,
  `program_date` date NOT NULL,
  `program_time` time NOT NULL,
  `program_venue` varchar(255) NOT NULL,
  `requirements` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_comment` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `student_viewed` tinyint(1) NOT NULL DEFAULT 0,
  `program_duration` int(11) DEFAULT 60
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_requests`
--

INSERT INTO `event_requests` (`request_id`, `student_id`, `program_title`, `program_description`, `program_date`, `program_time`, `program_venue`, `requirements`, `status`, `created_at`, `admin_comment`, `responded_at`, `student_viewed`, `program_duration`) VALUES
(1, 8, 'Ethical AI', 'A program conducted to take a class and share ideas about ethical AI', '2025-08-29', '11:15:00', 'Seminar hall', 'Speaker,mick,panel', 'approved', '2025-08-19 14:51:04', '', '2025-08-19 20:36:48', 1, 60),
(2, 8, 'AI in future', 'A program conducted to provide a seminar section about the bright use of AI', '2025-08-29', '11:14:00', 'Seminar hall', 'panel,mick', 'rejected', '2025-08-19 15:10:51', 'This request is rejected because at that same time there is already another program conducting at that venue.', '2025-08-19 20:51:34', 1, 60),
(3, 8, 'Network', 'An interaction program conduct for students to establish their ideas.', '2025-08-27', '10:00:00', 'Yoga hall', 'Mick,speaker,panel,chair', 'approved', '2025-08-19 15:40:18', '', '2025-08-19 21:11:14', 1, 60),
(4, 9, 'Develop Future', 'Develop our future bright', '2025-09-10', '10:50:00', 'Yoga hall', 'speaker,panel,mic', 'approved', '2025-09-09 06:11:24', '', '2025-09-12 11:16:46', 1, 60),
(5, 9, 'We\'re hiring', 'Job opportunities  for freshers. infopark Hiring 2026 Batch Students BCA,BCOM,MCA BSc etc. pooled Recruitment At BVM College,Cherpunkal', '2025-09-22', '10:00:00', 'Seminar hall', 'Male/Female\r\nBachelor\'s Degree\r\nMin Age Above 20 Years', 'approved', '2025-09-12 06:01:34', '', '2025-09-21 12:46:59', 1, 60),
(6, 9, 'hjbgygyu', 'b bhjbghjgb', '2024-02-11', '12:59:00', 'kjnhjuhjk', 'kjnkjbn', 'approved', '2025-09-19 04:26:44', '', '2025-09-19 10:03:18', 1, 60),
(7, 8, 'xdgjjz', 'eyssa', '2025-08-06', '20:40:00', 'fgtj', 'EYUJS', 'approved', '2025-09-20 15:10:57', '', '2025-09-21 12:52:00', 1, 60),
(8, 8, 'Develop Future', 'agdjbfaklgtn', '2025-08-14', '20:50:00', 'RFttaus', 'a5rsuy', 'pending', '2025-09-20 15:20:17', NULL, NULL, 0, 60),
(9, 8, 'Develop Future', 'agdjbfaklgtn', '2025-08-14', '20:50:00', 'RFttaus', 'a5rsuy', 'approved', '2025-09-20 15:20:47', '', '2025-09-21 13:40:51', 1, 60),
(10, 8, 'Develop Future', 'agdjbfaklgtn', '2025-08-14', '20:50:00', 'RFttaus', 'a5rsuy', 'rejected', '2025-09-20 15:20:50', 'Failed to approve request: Venue conflict: Another event is already scheduled at the same date, time, and venue.', '2025-09-21 12:53:20', 1, 60),
(11, 8, 'Develop Future', 'agdjbfaklgtn', '2025-08-14', '20:50:00', 'RFttaus', 'a5rsuy', 'approved', '2025-09-20 15:20:57', '', '2025-09-21 12:46:19', 1, 60),
(12, 8, 'Develop Future', 'agdjbfaklgtn', '2025-08-14', '20:50:00', 'RFttaus', 'a5rsuy', 'approved', '2025-09-20 15:21:06', '', '2025-09-21 12:33:41', 1, 60),
(13, 8, 'python workshop', 'program to enhance students in the python for become a  future developer with more knowledge', '2025-09-15', '04:44:00', 'open hall 2', 'mic,speaker,projector,wrieless', 'approved', '2025-09-21 06:02:31', '', '2025-09-21 12:33:39', 1, 60),
(14, 8, 'Network', 'rses', '2025-09-01', '14:50:00', 'trttr', 'rdtrdtrd', 'approved', '2025-09-21 06:17:12', '', '2025-09-21 12:33:34', 1, 60),
(15, 8, 'vhjfmhfhyfhgf', 'htrdthrddtrdhtrdehtr', '2025-09-23', '09:59:00', 'Open Auditorium', 'fgfhtrdshtrsrhtrssre', 'approved', '2025-09-21 08:14:39', '', '2025-09-21 13:45:25', 1, 68),
(16, 8, 'abfgfng', 'dghhjjj', '2025-09-30', '10:55:00', 'Mini Theatre', 'agfhv', 'approved', '2025-09-21 08:28:44', '', '2025-09-21 14:00:01', 1, 60),
(17, 8, 'Python workshop', 'A class conducted for learn python language.', '2025-11-27', '10:45:00', 'Main Theatre', 'Mick, screen, speaker', 'pending', '2025-11-12 14:09:11', NULL, NULL, 0, 60);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_type` enum('student','teacher') NOT NULL,
  `user_id` int(11) NOT NULL,
  `comments` text DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `feedback_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `event_id`, `user_type`, `user_id`, `comments`, `rating`, `feedback_date`) VALUES
(5, 1, 'student', 8, 'It was really interesting and useful section', 4, '2025-08-04 10:41:34'),
(6, 1, 'student', 9, 'super', 5, '2025-08-18 10:43:59'),
(7, 15, 'student', 8, 'very good progream informative one', 3, '2025-10-01 13:37:16');

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `registration_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_type` enum('student','teacher') NOT NULL,
  `user_id` int(11) NOT NULL,
  `registration_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`registration_id`, `event_id`, `user_type`, `user_id`, `registration_date`) VALUES
(13, 1, 'teacher', 4, '2025-07-16 13:23:45'),
(14, 1, 'student', 6, '2025-07-18 13:09:24'),
(15, 1, 'student', 8, '2025-07-31 21:06:52'),
(16, 1, 'student', 9, '2025-08-01 11:00:53'),
(17, 2, 'student', 10, '2025-08-01 11:08:15'),
(18, 2, 'student', 9, '2025-08-05 09:21:16'),
(19, 4, 'student', 12, '2025-08-05 12:20:16'),
(20, 3, 'student', 9, '2025-08-18 10:43:35'),
(21, 3, 'teacher', 5, '2025-08-19 11:10:05'),
(22, 2, 'teacher', 5, '2025-08-26 11:59:42'),
(23, 10, 'teacher', 5, '2025-08-26 20:58:02'),
(24, 11, 'teacher', 5, '2025-08-26 21:02:22'),
(25, 14, 'teacher', 5, '2025-09-10 20:03:05'),
(26, 9, 'teacher', 5, '2025-09-10 20:03:16'),
(27, 15, 'teacher', 5, '2025-09-11 20:06:39'),
(28, 15, 'student', 8, '2025-09-11 20:15:49'),
(29, 14, 'student', 14, '2025-09-12 12:03:16'),
(30, 15, 'student', 14, '2025-09-12 12:03:18');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(250) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `roll_no` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `first_name`, `last_name`, `email`, `password`, `phone_number`, `department`, `year`, `roll_no`) VALUES
(7, 'Annu', 'Thomas', 'annu111@gmail.com', '$2y$10$X5C.et9biJQyKOn55HXcxOKi3Oi19lUmJoTtD4ktskXY7PPMmyuou', '4678937653', 'BCA', 2, '101'),
(8, 'Aleena', 'Roy', 'aleena111@gmail.com', '$2y$10$/XiKPORK1jSLJtzhhX.mBOgSRE17qYl1EoBQwkaYqaI01xWy7e1tW', '6849305432', 'BCA', 1, '44'),
(9, 'Veena', 'Vijayan', 'veena@gmail.com', '$2y$10$5hm2RtvtZJ4XSFf.1WHMieL9s9fnYfv37Y0HhWW3Tx3wcRLDKvKYi', '7012437435', 'BCA', 3, '3245'),
(10, 'Devu s', 'Kumar', 'devu@gmail.com', '$2y$10$AW2Rh7dEYDkzqVp223CATOY6as6A91NUO0/Pqzm78sERmPp6o3bie', '9061186570', 'BCA', 4, '3248'),
(11, 'Midhun', 'Biju', 'midhunbiju604@gmail.com', '$2y$10$8zYTJ.fQcczDQBZSQhidV.FV.g4XkL2zX.Rd1j6agi/C/97KBbibu', '1234567858', 'BCA', 3, '3217'),
(13, 'Thomas', 'George', 'thomas567@gmail.com', '$2y$10$1s3JurjBjgucxf7tC5pTlupACqflP64F8wZBf1uYm6u4mbqh/d/Ra', '3456789098', 'MSW', 1, '111'),
(14, 'Jesvin', 'Robert', 'jesvinrobert4@gmail.com', '$2y$10$N1IKFrr4ENY6wK8WTfGO3uiJbTSiP55NF9hAp3c83eg5E2t7BqRRy', '9999999999', 'BCA', 1, '3204'),
(15, 'James', 'George', 'james111@gmail.com', '$2y$10$HE6xz9U0Jh56/q9R/uuv..7w12nv5j0u3S3sWFUJGiiWPqpim8C/G', '7685468087', 'BCA', 4, '123'),
(16, 'Anu', 'Vijayan', 'ammmu@gmail.com', '$2y$10$qS/2p9upkDsCJmETpzp6DuXfcPELN24x59/zZLBpgoyAGlP1O1sTS', '4563214569', 'BCA', 1, '11213'),
(18, 'Minnu', 'Joseph', 'minnu321@gmail.com', '$2y$10$xFXOr.ktNWej7IUQrJ34JO6A56uu/PpBdnCjEdCDfjfwqlwXqDSTK', '7930274839', 'BBA', 3, '4623');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `first_name`, `last_name`, `email`, `password`, `phone_number`, `department`, `designation`) VALUES
(3, 'mathew', '', 'm@gmail.com', '$2y$10$HNrz.JCiGjhb6wHoaCojG.MCChIn.XY0kQA16fwbN2GglTRO8rFWm', '994632086', 'maths', 'assistant professor'),
(4, 'Amalda', '', 'amalda@gmail.com', '$2y$10$ZDhw3uaQTQc81AT2URYvguYJZ6601DeKwggme7y2opHKhRC9qrGdO', '9876543212', 'Computer', 'SE'),
(5, 'Anu', 'Joseph', 'anu123@gmail.com', '$2y$10$llwIo1wJwlpXZRgJAMbyz.ukLb4NW8wj/P1t.MUsEMl9NZjgBLJBy', '9876543212', 'BCA', 'SE'),
(6, 'Pooja', 'Devi', 'pooja@gmail.com', '$2y$10$npOgHuXZi2ZwxFGrdULWfOESixvx4mugdVP8wovmqQmSqiZjcn9Ni', '9123456678', 'BCA', 'SE'),
(7, 'Rose', 'Jose', 'rose123@gmail.com', '$2y$10$Yh/cN/cFR6ozIWYbr6DbquZNOXYT23SVsYwsV8WFSg84ojVjiNSym', '5678943245', 'BA Animation', 'SE'),
(8, 'Anna', 'Joseph', 'anna789@gmail.com', '$2y$10$A.Rem.1CAMhQwHarzzcEieaav7E6yswat8H9ipvKsIciI/VjMDBfO', '9874563289', 'BBA', 'SE'),
(9, 'James', 'Joseph', 'j@gmail.com', '$2y$10$E02uBko64F3gH7e15Z27r.1r5TQjp2cRN2SzcH0LLa1hWszFpmu7S', '4561239874', 'BCA', 'Associate Professor'),
(10, 'Amala', 'Jose', 'amala678@gmail.com', '$2y$10$3dutKWoonb..zNNaJRZ55eoushhOtj/gKYjGdqXsxpvoqZRGbB0vm', '6387299749', 'BA Animation', 'Assistant Professor'),
(11, 'Roy', 'Thomas', 'roythomas789@gmail.com', '$2y$10$8VozE.NpQuJfKVgwmBr4SeQbDRhOe3GhmbzEX9lxLTH9eN2Im2mCC', '8735627654', 'B.COM', 'Professor');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `created_by_admin_id` (`created_by_admin_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `event_requests`
--
ALTER TABLE `event_requests`
  ADD PRIMARY KEY (`request_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`registration_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `event_requests`
--
ALTER TABLE `event_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `registration_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
