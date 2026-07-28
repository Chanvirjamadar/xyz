<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "study_portal";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Auto-initialize required tables if missing
$tables = [
    "CREATE TABLE IF NOT EXISTS `library_subjects` (
        `subject_id` INT AUTO_INCREMENT PRIMARY KEY,
        `subject_name` VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `library_resources` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `subject_id` INT DEFAULT NULL,
        `uploader_id` VARCHAR(50) DEFAULT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `original_filename` VARCHAR(255) NOT NULL,
        `resource_type` VARCHAR(50) DEFAULT 'pdf',
        `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
        `downloads_count` INT DEFAULT 0,
        `views_count` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `library_favorites` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `resource_id` INT NOT NULL,
        `rater_id` VARCHAR(50) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `library_ratings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `resource_id` INT NOT NULL,
        `rater_id` VARCHAR(50) NOT NULL,
        `rating` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `coding_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT DEFAULT 1,
        `language` VARCHAR(50) DEFAULT NULL,
        `code` LONGTEXT DEFAULT NULL,
        `program_input` TEXT DEFAULT NULL,
        `program_output` TEXT DEFAULT NULL,
        `output` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `coding_drafts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT DEFAULT 1,
        `language` VARCHAR(50) NOT NULL,
        `code` LONGTEXT DEFAULT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_draft` (`student_id`, `language`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `announcements` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `announcement_reads` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `announcement_id` INT NOT NULL,
        `student_id` INT NOT NULL,
        `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_read` (`announcement_id`, `student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `queries` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `category` VARCHAR(50) DEFAULT 'other',
        `subject` VARCHAR(255) DEFAULT NULL,
        `message` TEXT NOT NULL,
        `status` ENUM('pending', 'answered') DEFAULT 'pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `query_replies` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `query_id` INT NOT NULL,
        `staff_id` VARCHAR(50) DEFAULT NULL,
        `reply` TEXT NOT NULL,
        `replied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `portal_activity` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_name` VARCHAR(100) DEFAULT NULL,
        `user_role` VARCHAR(50) DEFAULT NULL,
        `action_type` VARCHAR(50) DEFAULT NULL,
        `message` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `admin_users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) UNIQUE NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $sql) {
    @mysqli_query($conn, $sql);
}

// Seed default admin if missing
$adminCheck = @mysqli_query($conn, "SELECT COUNT(*) as c FROM `admin_users`");
if ($adminCheck && ($row = $adminCheck->fetch_assoc()) && (int)$row['c'] === 0) {
    @mysqli_query($conn, "INSERT INTO `admin_users` (`username`, `password`, `name`, `email`) VALUES ('admin', 'admin123', 'System Administrator', 'admin@zealhub.edu')");
}

// Auto-migrate missing columns on existing tables
$columnMigrations = [
    "ALTER TABLE `library_resources` ADD COLUMN `resource_type` VARCHAR(50) DEFAULT 'pdf'",
    "ALTER TABLE `question_bank` ADD COLUMN `uploaded_by` VARCHAR(100) DEFAULT 'Faculty'",
    "ALTER TABLE `student` ADD COLUMN `prn` VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE `student` ADD COLUMN `roll_no` VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE `student` ADD COLUMN `branch` VARCHAR(100) DEFAULT NULL",
    "ALTER TABLE `student` ADD COLUMN `semester` VARCHAR(50) DEFAULT NULL",
    "ALTER TABLE `student` ADD COLUMN `division` VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE `student` ADD COLUMN `phone` VARCHAR(30) DEFAULT NULL",
    "ALTER TABLE `student` ADD COLUMN `gender` VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE `student` ADD COLUMN `dob` DATE DEFAULT NULL",
    "ALTER TABLE `student` ADD COLUMN `address` TEXT DEFAULT NULL",
    "ALTER TABLE `student` ADD COLUMN `photo` VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE `announcements` ADD COLUMN `target_audience` VARCHAR(20) DEFAULT 'both'",
    "ALTER TABLE `announcements` ADD COLUMN `posted_by` VARCHAR(100) DEFAULT 'Admin'"
];

foreach ($columnMigrations as $alterSql) {
    try {
        @mysqli_query($conn, $alterSql);
    } catch (Exception $e) {
        // Ignore if column already exists
    }
}
?>