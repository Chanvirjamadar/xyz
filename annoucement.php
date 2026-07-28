<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!headers_sent()) {
    header('Content-Type: application/json');
}
// Dynamic DB path resolution
if (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} elseif (file_exists(__DIR__ . '/../db.php')) {
    require_once __DIR__ . '/../db.php';
} else {
    echo json_encode(["success" => false, "message" => "Database configuration file not found"]);
    exit();
}

// Action handler
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if (!$action) {
    echo json_encode(["success" => false, "message" => "No action specified"]);
    exit();
}

// Helper to check student role
function requireStudent()
{
    if (!isset($_SESSION['student'])) {
        echo json_encode(["success" => false, "message" => "Unauthorized student access"]);
        exit();
    }
    return $_SESSION['student'];
}

// Helper to check staff role
function requireStaff()
{
    if (!isset($_SESSION['staff'])) {
        echo json_encode(["success" => false, "message" => "Unauthorized staff access"]);
        exit();
    }
    return $_SESSION['staff'];
}

switch ($action) {
    case 'fetch':
        $studentId = requireStudent();

        // Fetch all announcements, with is_read status
        $query = "SELECT a.*, COALESCE(ar.is_read, 0) AS is_read 
                  FROM announcements a
                  LEFT JOIN announcement_reads ar ON a.id = ar.announcement_id AND ar.student_id = ?
                  ORDER BY a.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();

        $announcements = [];
        while ($row = $result->fetch_assoc()) {
            $announcements[] = [
                "id" => (int)$row['id'],
                "title" => $row['title'],
                "message" => $row['message'],
                "created_by" => $row['created_by'],
                "created_at" => $row['created_at'],
                "is_read" => (int)$row['is_read']
            ];
        }

        echo json_encode(["success" => true, "announcements" => $announcements]);
        break;

    case 'unread_count':
        $studentId = requireStudent();

        $query = "SELECT COUNT(*) as total 
                  FROM announcements a
                  WHERE a.id NOT IN (
                      SELECT announcement_id FROM announcement_reads WHERE student_id = ?
                  )";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        echo json_encode(["success" => true, "unread_count" => (int)$res['total']]);
        break;

    case 'mark_read':
        $studentId = requireStudent();

        // Read input
        $announcementId = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        if (!$announcementId) {
            echo json_encode(["success" => false, "message" => "Missing announcement ID"]);
            exit();
        }

        // Check if read record already exists
        $checkQuery = "SELECT id FROM announcement_reads WHERE announcement_id = ? AND student_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("ii", $announcementId, $studentId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            $insertQuery = "INSERT INTO announcement_reads (announcement_id, student_id, is_read) VALUES (?, ?, 1)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("ii", $announcementId, $studentId);
            $stmt->execute();
        }

        echo json_encode(["success" => true, "message" => "Announcement marked as read"]);
        break;

    case 'mark_all_read':
        $studentId = requireStudent();

        // Insert read status for all unread announcements
        $query = "INSERT INTO announcement_reads (announcement_id, student_id, is_read)
                  SELECT a.id, ?, 1
                  FROM announcements a
                  WHERE a.id NOT IN (
                      SELECT announcement_id FROM announcement_reads WHERE student_id = ?
                  )";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $studentId, $studentId);
        $stmt->execute();

        echo json_encode(["success" => true, "message" => "All announcements marked as read"]);
        break;

    case 'create':
        $staffId = requireStaff();

        // Parse input
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';

        if (empty($title) || empty($message)) {
            echo json_encode(["success" => false, "message" => "Title and Message are required"]);
            exit();
        }

        $query = "INSERT INTO announcements (title, message, created_by) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $title, $message, $staffId);
        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Announcement created successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Database error creating announcement"]);
        }
        break;

    default:
        echo json_encode(["success" => false, "message" => "Unknown action"]);
        break;
}
