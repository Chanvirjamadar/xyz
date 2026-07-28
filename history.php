<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Fixed path: file is in root directory
require_once __DIR__ . '/db.php';

// Auto-create coding_history table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `coding_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT DEFAULT 1,
    `language` VARCHAR(50) DEFAULT NULL,
    `code` LONGTEXT DEFAULT NULL,
    `program_input` TEXT DEFAULT NULL,
    `program_output` TEXT DEFAULT NULL,
    `output` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Determine user ID
$student_id = 1;
if (isset($_SESSION['student'])) {
    $student_id = intval($_SESSION['student']);
} elseif (isset($_SESSION['student_id'])) {
    $student_id = intval($_SESSION['student_id']);
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// ── GET a single history record ───────────────────────────────────────────
if ($action === 'get') {
    $id   = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $stmt = $conn->prepare(
        "SELECT `id`,
                COALESCE(NULLIF(`language`, ''), 'python') AS `language`,
                `code`,
                `program_input`,
                COALESCE(`program_output`, `output`, '') AS `program_output`,
                COALESCE(`created_at`, NOW()) AS `created_at`
         FROM `coding_history`
         WHERE `id` = ?"
    );
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            echo json_encode(['status' => 'success', 'data' => $row]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Record not found']);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
    exit();
}

// ── LIST history for current user ─────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT `id`,
            COALESCE(NULLIF(`language`, ''), 'python') AS `language`,
            `code`,
            COALESCE(`created_at`, NOW()) AS `created_at`
     FROM `coding_history`
     WHERE `student_id` = ?
     ORDER BY `id` DESC
     LIMIT 30"
);

if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res  = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $list[] = [
            'id'       => $row['id'],
            'language' => strtoupper($row['language']),
            'snippet'  => mb_strimwidth(strip_tags($row['code']), 0, 60, '...'),
            'date'     => date('d M Y, h:i A', strtotime($row['created_at']))
        ];
    }
    echo json_encode(['status' => 'success', 'data' => $list]);
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}
?>
