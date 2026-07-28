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
    case 'raise':
        $studentId = requireStudent();

        $category = isset($_POST['category']) ? trim($_POST['category']) : '';
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';

        // Validation
        $validCategories = ['questionbank', 'syllabus', 'timetable', 'other'];
        if (!in_array($category, $validCategories)) {
            echo json_encode(["success" => false, "message" => "Invalid category"]);
            exit();
        }
        if (empty($message)) {
            echo json_encode(["success" => false, "message" => "Message is required"]);
            exit();
        }

        $query = "INSERT INTO queries (student_id, category, subject, message, status) VALUES (?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isss", $studentId, $category, $subject, $message);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Query raised successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Database error raising query"]);
        }
        break;

    case 'fetch_student':
        $studentId = requireStudent();

        // Fetch queries
        $query = "SELECT * FROM queries WHERE student_id = ? ORDER BY created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();

        $queries = [];
        $queryIds = [];
        while ($row = $result->fetch_assoc()) {
            $row['replies'] = [];
            $queries[$row['id']] = $row;
            $queryIds[] = (int)$row['id'];
        }

        // Fetch replies if there are queries
        if (!empty($queryIds)) {
            $inClause = implode(',', $queryIds);
            $repliesQuery = "SELECT qr.*, sp.name AS staff_name 
                             FROM query_replies qr
                             LEFT JOIN staff_profile sp ON qr.staff_id = sp.staff_id
                             WHERE qr.query_id IN ($inClause)
                             ORDER BY qr.replied_at ASC";
            $repliesRes = $conn->query($repliesQuery);
            if ($repliesRes) {
                while ($replyRow = $repliesRes->fetch_assoc()) {
                    $queries[$replyRow['query_id']]['replies'][] = [
                        "id" => (int)$replyRow['id'],
                        "reply" => $replyRow['reply'],
                        "replied_at" => $replyRow['replied_at'],
                        "staff_id" => $replyRow['staff_id'],
                        "staff_name" => $replyRow['staff_name'] ?? 'Staff Member'
                    ];
                }
            }
        }

        echo json_encode(["success" => true, "queries" => array_values($queries)]);
        break;

    case 'fetch_staff':
        $staffId = requireStaff();

        $category = isset($_GET['category']) ? trim($_GET['category']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';

        // Base Query
        $sql = "SELECT q.*, s.name AS student_name, s.email AS student_email 
                FROM queries q
                JOIN student s ON q.student_id = s.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if (!empty($category) && $category !== 'all') {
            $sql .= " AND q.category = ?";
            $params[] = $category;
            $types .= "s";
        }

        if (!empty($status) && $status !== 'all') {
            $sql .= " AND q.status = ?";
            $params[] = $status;
            $types .= "s";
        }

        $sql .= " ORDER BY q.created_at DESC";

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $queries = [];
        $queryIds = [];
        while ($row = $result->fetch_assoc()) {
            $row['replies'] = [];
            $queries[$row['id']] = $row;
            $queryIds[] = (int)$row['id'];
        }

        // Fetch replies if there are queries
        if (!empty($queryIds)) {
            $inClause = implode(',', $queryIds);
            $repliesQuery = "SELECT qr.*, sp.name AS staff_name 
                             FROM query_replies qr
                             LEFT JOIN staff_profile sp ON qr.staff_id = sp.staff_id
                             WHERE qr.query_id IN ($inClause)
                             ORDER BY qr.replied_at ASC";
            $repliesRes = $conn->query($repliesQuery);
            if ($repliesRes) {
                while ($replyRow = $repliesRes->fetch_assoc()) {
                    $queries[$replyRow['query_id']]['replies'][] = [
                        "id" => (int)$replyRow['id'],
                        "reply" => $replyRow['reply'],
                        "replied_at" => $replyRow['replied_at'],
                        "staff_id" => $replyRow['staff_id'],
                        "staff_name" => $replyRow['staff_name'] ?? 'Staff Member'
                    ];
                }
            }
        }

        echo json_encode(["success" => true, "queries" => array_values($queries)]);
        break;

    case 'reply':
        $staffId = requireStaff();

        $queryId = isset($_POST['query_id']) ? (int)$_POST['query_id'] : 0;
        $reply = isset($_POST['reply']) ? trim($_POST['reply']) : '';

        if (!$queryId || empty($reply)) {
            echo json_encode(["success" => false, "message" => "Query ID and Reply are required"]);
            exit();
        }

        // Verify query exists
        $check = $conn->prepare("SELECT id FROM queries WHERE id = ?");
        $check->bind_param("i", $queryId);
        $check->execute();
        if ($check->get_result()->num_rows == 0) {
            echo json_encode(["success" => false, "message" => "Query not found"]);
            exit();
        }

        // Transaction to ensure atomic insertions
        $conn->begin_transaction();

        $insertQuery = "INSERT INTO query_replies (query_id, staff_id, reply) VALUES (?, ?, ?)";
        $stmt1 = $conn->prepare($insertQuery);
        $stmt1->bind_param("iss", $queryId, $staffId, $reply);

        $updateQuery = "UPDATE queries SET status = 'answered' WHERE id = ?";
        $stmt2 = $conn->prepare($updateQuery);
        $stmt2->bind_param("i", $queryId);

        if ($stmt1->execute() && $stmt2->execute()) {
            $conn->commit();
            echo json_encode(["success" => true, "message" => "Reply submitted successfully and status updated"]);
        } else {
            $conn->rollback();
            echo json_encode(["success" => false, "message" => "Database error saving reply"]);
        }
        break;

    case 'poll_replies_count':
        $studentId = requireStudent();
        $lastSeenReplyId = isset($_GET['last_seen_id']) ? (int)$_GET['last_seen_id'] : 0;

        // Count replies for this student's queries with id > lastSeenReplyId
        $query = "SELECT COUNT(qr.id) as total 
                  FROM query_replies qr
                  JOIN queries q ON qr.query_id = q.id
                  WHERE q.student_id = ? AND qr.id > ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $studentId, $lastSeenReplyId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        echo json_encode(["success" => true, "unread_replies" => (int)$res['total']]);
        break;

    case 'poll_pending_queries_count':
        $staffId = requireStaff();

        $query = "SELECT COUNT(*) as total FROM queries WHERE status = 'pending'";
        $res = $conn->query($query)->fetch_assoc();

        echo json_encode(["success" => true, "pending_count" => (int)$res['total']]);
        break;

    default:
        echo json_encode(["success" => false, "message" => "Unknown action"]);
        break;
}
