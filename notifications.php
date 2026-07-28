<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// 1. MARK ALL NOTIFICATIONS AS READ
if ($action === 'mark_all_read') {
    if (isset($_SESSION['staff'])) {
        $staffID = $_SESSION['staff'];
        // Get all unread announcement IDs for staff
        $sql = "
            SELECT a.id 
            FROM announcements a 
            WHERE (a.target_audience IS NULL OR a.target_audience IN ('all', 'both', 'staff'))
            AND a.id NOT IN (
                SELECT announcement_id FROM staff_announcement_reads WHERE staff_id = ?
            )
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $staffID);
        $stmt->execute();
        $res = $stmt->get_result();

        $insStmt = $conn->prepare("INSERT IGNORE INTO staff_announcement_reads (staff_id, announcement_id) VALUES (?, ?)");
        while ($row = $res->fetch_assoc()) {
            $ancId = $row['id'];
            $insStmt->bind_param("si", $staffID, $ancId);
            $insStmt->execute();
        }

        echo json_encode(['success' => true, 'unread_count' => 0, 'role' => 'staff']);
        exit();
    } elseif (isset($_SESSION['student'])) {
        $studentID = $_SESSION['student'];
        // Get all unread announcement IDs for student
        $sql = "
            SELECT a.id 
            FROM announcements a 
            WHERE (a.target_audience IS NULL OR a.target_audience IN ('all', 'both', 'student'))
            AND a.id NOT IN (
                SELECT announcement_id FROM announcement_reads WHERE student_id = ?
            )
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $studentID);
        $stmt->execute();
        $res = $stmt->get_result();

        $insStmt = $conn->prepare("INSERT IGNORE INTO announcement_reads (student_id, announcement_id) VALUES (?, ?)");
        while ($row = $res->fetch_assoc()) {
            $ancId = $row['id'];
            $insStmt->bind_param("si", $studentID, $ancId);
            $insStmt->execute();
        }

        echo json_encode(['success' => true, 'unread_count' => 0, 'role' => 'student']);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit();
    }
}

// 2. MARK SINGLE ANNOUNCEMENT AS READ
elseif ($action === 'mark_single_read') {
    $ancId = intval($_GET['announcement_id'] ?? ($_POST['announcement_id'] ?? 0));
    if ($ancId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
        exit();
    }

    if (isset($_SESSION['staff'])) {
        $staffID = $_SESSION['staff'];
        $insStmt = $conn->prepare("INSERT IGNORE INTO staff_announcement_reads (staff_id, announcement_id) VALUES (?, ?)");
        $insStmt->bind_param("si", $staffID, $ancId);
        $insStmt->execute();

        // Calculate remaining unread count
        $stmtUnread = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM announcements a 
            WHERE (a.target_audience IS NULL OR a.target_audience IN ('all', 'both', 'staff'))
            AND a.id NOT IN (
                SELECT announcement_id FROM staff_announcement_reads WHERE staff_id = ?
            )
        ");
        $stmtUnread->bind_param("s", $staffID);
        $stmtUnread->execute();
        $remCount = $stmtUnread->get_result()->fetch_assoc()['total'] ?? 0;

        echo json_encode(['success' => true, 'unread_count' => $remCount, 'role' => 'staff']);
        exit();
    } elseif (isset($_SESSION['student'])) {
        $studentID = $_SESSION['student'];
        $insStmt = $conn->prepare("INSERT IGNORE INTO announcement_reads (student_id, announcement_id) VALUES (?, ?)");
        $insStmt->bind_param("si", $studentID, $ancId);
        $insStmt->execute();

        // Calculate remaining unread count
        $stmtUnread = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM announcements a 
            WHERE (a.target_audience IS NULL OR a.target_audience IN ('all', 'both', 'student'))
            AND a.id NOT IN (
                SELECT announcement_id FROM announcement_reads WHERE student_id = ?
            )
        ");
        $stmtUnread->bind_param("s", $studentID);
        $stmtUnread->execute();
        $remCount = $stmtUnread->get_result()->fetch_assoc()['total'] ?? 0;

        echo json_encode(['success' => true, 'unread_count' => $remCount, 'role' => 'student']);
        exit();
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
