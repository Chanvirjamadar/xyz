<?php
session_start();
require_once __DIR__ . "/db.php";

if (isset($_SESSION['staff'])) {
    $staffID = $_SESSION['staff'];
    $conn->query("CREATE TABLE IF NOT EXISTS `staff_announcement_reads` (
        `staff_id` VARCHAR(100) NOT NULL,
        `announcement_id` INT NOT NULL,
        `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`staff_id`, `announcement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $res = $conn->query("SELECT id FROM announcements WHERE (target_audience IS NULL OR target_audience IN ('all', 'both', 'staff'))");
    if ($res) {
        $stmt = $conn->prepare("INSERT IGNORE INTO staff_announcement_reads (staff_id, announcement_id) VALUES (?, ?)");
        while ($row = $res->fetch_assoc()) {
            $ancId = $row['id'];
            $stmt->bind_param("si", $staffID, $ancId);
            $stmt->execute();
        }
    }
    header("Location: staff_dashboard.php");
    exit();
} elseif (isset($_SESSION['student'])) {
    $studentID = $_SESSION['student'];
    $conn->query("CREATE TABLE IF NOT EXISTS `announcement_reads` (
        `student_id` VARCHAR(100) NOT NULL,
        `announcement_id` INT NOT NULL,
        `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`student_id`, `announcement_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $res = $conn->query("SELECT id FROM announcements WHERE (target_audience IS NULL OR target_audience IN ('all', 'both', 'student'))");
    if ($res) {
        $stmt = $conn->prepare("INSERT IGNORE INTO announcement_reads (student_id, announcement_id) VALUES (?, ?)");
        while ($row = $res->fetch_assoc()) {
            $ancId = $row['id'];
            $stmt->bind_param("si", $studentID, $ancId);
            $stmt->execute();
        }
    }
    header("Location: student_dashboard.php");
    exit();
} else {
    header("Location: main_page.php");
    exit();
}