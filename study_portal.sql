-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 21, 2026 at 07:00 AM
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
-- Database: `study_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `labs`
--

CREATE TABLE `labs` (
  `id` int(11) NOT NULL,
  `lab_title` varchar(255) NOT NULL,
  `problem_statement` text DEFAULT NULL,
  `default_code` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `labs`
--

INSERT INTO `labs` (`id`, `lab_title`, `problem_statement`, `default_code`, `created_at`) VALUES
(1, 'Hello World in C', 'Write a program to print \"Hello, World!\"', '#include <stdio.h>\n\nint main() {\n    printf(\"Hello, World!\");\n    return 0;\n}', '2026-07-20 05:05:55');

-- --------------------------------------------------------

--
-- Table structure for table `lab_records`
--

CREATE TABLE `lab_records` (
  `record_id` int(11) NOT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `language` varchar(50) DEFAULT NULL,
  `program_title` varchar(100) DEFAULT NULL,
  `source_code` longtext DEFAULT NULL,
  `output` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_records`
--

INSERT INTO `lab_records` (`record_id`, `student_name`, `language`, `program_title`, `source_code`, `output`, `submitted_at`) VALUES
(1, 'chanvir shivputra jamadar', 'cpp', 'DSA', '#include <iostream>\r\nusing namespace std;\r\n\r\nclass Palindrome {\r\n    int num, original, reverse = 0, rem;\r\n\r\npublic:\r\n    void getNumber() {\r\n        cout << \"Enter a number: \";\r\n        cin >> num;\r\n        original = num;\r\n    }\r\n\r\n    void check() {\r\n    	cout << \"Enter a number: \";\r\n		        cin >> num;\r\n		        original = num;\r\n        while(num != 0) {\r\n            rem = num % 10;\r\n            reverse = reverse * 10 + rem;\r\n            num = num / 10;\r\n        }\r\n\r\n        if(original == reverse)\r\n            cout << \"Number is Palindrome\";\r\n        else\r\n            cout << \"Number is Not Palindrome\";\r\n    }\r\n};\r\n\r\nint main() {\r\n    Palindrome p;\r\n    p.getNumber();\r\n    p.check();\r\n    return 0;\r\n}', 'Enter a number: 4\r\nEnter a number: 4\r\nNumber is Palindrome', '2026-07-08 13:42:42'),
(2, NULL, NULL, NULL, NULL, NULL, '2026-07-19 13:47:03');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset`
--

CREATE TABLE `password_reset` (
  `id` int(11) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `otp` varchar(10) DEFAULT NULL,
  `expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset`
--

INSERT INTO `password_reset` (`id`, `email`, `otp`, `expiry`) VALUES
(1, 'shivputrajamadar057@gmail.com', '123', '2026-07-20 19:17:51');

-- --------------------------------------------------------

--
-- Table structure for table `portal_activity`
--

CREATE TABLE `portal_activity` (
  `id` int(11) NOT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `user_role` varchar(20) DEFAULT NULL,
  `action_type` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `portal_activity`
--

INSERT INTO `portal_activity` (`id`, `user_name`, `user_role`, `action_type`, `message`, `created_at`) VALUES
(1, 'Chanvir shivputra jamadar', 'Staff', 'Upload', 'Uploaded new material: chapter 2 (MATH PART 2)', '2026-07-20 06:07:14'),
(2, 'Chanvir shivputra jamadar', 'Staff', 'Upload', 'Uploaded new material: ddd (MATH PART 2)', '2026-07-20 06:30:48'),
(3, 'Chanvir shivputra jamadar', 'Staff', 'Delete', 'Deleted material: ddd', '2026-07-20 07:41:15'),
(4, 'Chanvir shivputra jamadar', 'Staff', 'Delete', 'Deleted material: Unit 1 Notes', '2026-07-20 07:43:47'),
(5, 'Chanvir shivputra jamadar', 'Staff', 'Upload', 'Uploaded Question Bank: mid term question paper (MATH PART 2 - 2026)', '2026-07-20 07:51:39'),
(6, 'Chanvir shivputmadar', 'Staff', 'Upload', 'Uploaded Question Bank: jhd (ywgd - jehw)', '2026-07-20 08:36:57'),
(7, 'Chanvir  jamadar', 'Staff', 'Delete', 'Deleted material: chapter 2', '2026-07-20 09:37:43'),
(8, 'Chanvir  jamadar', 'Staff', 'Delete', 'Deleted Question Bank item: jhd', '2026-07-20 09:37:53'),
(9, 'Chanvir  jamadar', 'Staff', 'Delete', 'Deleted Question Bank item: mid term question paper', '2026-07-20 09:37:57');

-- --------------------------------------------------------

--
-- Table structure for table `practice_questions`
--

CREATE TABLE `practice_questions` (
  `practice_id` int(11) NOT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `language` varchar(50) DEFAULT NULL,
  `difficulty` varchar(20) DEFAULT NULL,
  `question` text DEFAULT NULL,
  `solution` text DEFAULT NULL,
  `added_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `practice_questions`
--

INSERT INTO `practice_questions` (`practice_id`, `subject`, `language`, `difficulty`, `question`, `solution`, `added_by`, `created_at`) VALUES
(1, 'CPP', 'CPP', 'MORE', 'Q', 'S', 'MAHESH SIR', '2026-07-08 13:49:34');

-- --------------------------------------------------------

--
-- Table structure for table `question_bank`
--

CREATE TABLE `question_bank` (
  `id` int(11) NOT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `year` varchar(50) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `question_bank`
--

INSERT INTO `question_bank` (`id`, `subject`, `year`, `title`, `description`, `file_name`, `upload_date`) VALUES
(1, 'MATH PART 2', '2026', 'unit1 ', 'qb', '1784488651_Unit 3 Question bank.pdf', '2026-07-19 19:17:31');

-- --------------------------------------------------------

--
-- Table structure for table `staff_profile`
--

CREATE TABLE `staff_profile` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `mobile_no` varchar(20) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_profile`
--

INSERT INTO `staff_profile` (`id`, `staff_id`, `name`, `email`, `password`, `phone`, `mobile_no`, `qualification`, `department`, `designation`, `gender`, `dob`, `address`, `photo`, `reset_token`, `token_expiry`) VALUES
(1, 'S001', 'Admin Staff', 'shivputrajamadar@gmail.com', '1234567', '90219243', NULL, NULL, 'IT Department', 'Senior Manager', 'Male', '1999-05-11', 'ganganagr, phurusungi, pune-412308', '1784606758_IMG_20260219_132131706.jpg', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `student_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `prn` varchar(30) DEFAULT NULL,
  `roll_no` varchar(30) DEFAULT NULL,
  `branch` varchar(50) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `division` varchar(10) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`student_id`, `name`, `email`, `password`, `prn`, `roll_no`, `branch`, `semester`, `division`, `phone`, `gender`, `dob`, `address`, `photo`) VALUES
(1, 'Madhu jamadar', 'madhujamdar24@gmail.com\r\n', '12345', '12', '18', 'IT', '3', 'B', '8551082198', 'MALE', '2016-08-22', 'PUNE-4123008', 'NONE');

-- --------------------------------------------------------

--
-- Table structure for table `student_academic`
--

CREATE TABLE `student_academic` (
  `student_id` varchar(50) NOT NULL,
  `attendance` int(11) DEFAULT 0,
  `cgpa` decimal(3,2) DEFAULT NULL,
  `fee_status` varchar(20) DEFAULT NULL,
  `mentor` varchar(100) DEFAULT NULL,
  `total_fees` decimal(10,2) DEFAULT NULL,
  `paid_fees` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_code`
--

CREATE TABLE `student_code` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `saved_code` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_profile`
--

CREATE TABLE `student_profile` (
  `student_id` varchar(50) NOT NULL,
  `roll` varchar(20) DEFAULT NULL,
  `prn` varchar(20) DEFAULT NULL,
  `abc_id` varchar(30) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `semester` varchar(10) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `aadhaar_no` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `aadhaar_file` varchar(255) DEFAULT NULL,
  `pan_file` varchar(255) DEFAULT NULL,
  `ssc_file` varchar(255) DEFAULT NULL,
  `hsc_file` varchar(255) DEFAULT NULL,
  `caste_file` varchar(255) DEFAULT NULL,
  `income_file` varchar(255) DEFAULT NULL,
  `domicile_file` varchar(255) DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_queries`
--

CREATE TABLE `student_queries` (
  `query_id` int(11) NOT NULL,
  `student_id` varchar(50) DEFAULT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `staff_reply` text DEFAULT NULL,
  `status` enum('Pending','Resolved') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `study_materials`
--

CREATE TABLE `study_materials` (
  `id` int(11) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `study_materials`
--

INSERT INTO `study_materials` (`id`, `subject`, `title`, `description`, `file_name`, `upload_date`) VALUES
(4, 'MATH PART 2', 'unit 3', 'notes ', '1784520962_unit 3 notes.pdf', '2026-07-20 04:16:02');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `subject_code` varchar(20) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `branch` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_name`, `subject_code`, `semester`, `branch`) VALUES
(1, 'Programming in C', 'CS101', 'Semester 1', 'IT'),
(2, 'Java Programming', 'CS102', 'Semester 2', 'IT'),
(3, 'Python Programming', 'CS103', 'Semester 3', 'IT'),
(4, 'PHP Programming', 'CS104', 'Semester 4', 'IT');

-- --------------------------------------------------------

--
-- Table structure for table `syllabus`
--

CREATE TABLE `syllabus` (
  `syllabus_id` int(11) NOT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `unit_no` int(11) DEFAULT NULL,
  `topic_name` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` varchar(100) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `labs`
--
ALTER TABLE `labs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lab_records`
--
ALTER TABLE `lab_records`
  ADD PRIMARY KEY (`record_id`);

--
-- Indexes for table `password_reset`
--
ALTER TABLE `password_reset`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `portal_activity`
--
ALTER TABLE `portal_activity`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `practice_questions`
--
ALTER TABLE `practice_questions`
  ADD PRIMARY KEY (`practice_id`);

--
-- Indexes for table `question_bank`
--
ALTER TABLE `question_bank`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_profile`
--
ALTER TABLE `staff_profile`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_id` (`staff_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `student_academic`
--
ALTER TABLE `student_academic`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `student_code`
--
ALTER TABLE `student_code`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_profile`
--
ALTER TABLE `student_profile`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `student_queries`
--
ALTER TABLE `student_queries`
  ADD PRIMARY KEY (`query_id`);

--
-- Indexes for table `study_materials`
--
ALTER TABLE `study_materials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`);

--
-- Indexes for table `syllabus`
--
ALTER TABLE `syllabus`
  ADD PRIMARY KEY (`syllabus_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `labs`
--
ALTER TABLE `labs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `lab_records`
--
ALTER TABLE `lab_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `password_reset`
--
ALTER TABLE `password_reset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `portal_activity`
--
ALTER TABLE `portal_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `practice_questions`
--
ALTER TABLE `practice_questions`
  MODIFY `practice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `question_bank`
--
ALTER TABLE `question_bank`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `staff_profile`
--
ALTER TABLE `staff_profile`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_code`
--
ALTER TABLE `student_code`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_queries`
--
ALTER TABLE `student_queries`
  MODIFY `query_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `study_materials`
--
ALTER TABLE `study_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `syllabus`
--
ALTER TABLE `syllabus`
  MODIFY `syllabus_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `student_academic`
--
ALTER TABLE `student_academic`
  ADD CONSTRAINT `student_academic_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
