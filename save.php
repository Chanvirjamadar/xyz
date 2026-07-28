<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Fixed path: file is in root directory
require_once __DIR__ . '/db.php';

// Auto-create coding_drafts table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS `coding_drafts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT DEFAULT 1,
    `language` VARCHAR(50) NOT NULL,
    `code` LONGTEXT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_draft` (`student_id`, `language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Determine user ID
$student_id = 1;
if (isset($_SESSION['student'])) {
    $student_id = intval($_SESSION['student']);
} elseif (isset($_SESSION['student_id'])) {
    $student_id = intval($_SESSION['student_id']);
}

// Parse input: supports both JSON body AND form POST
$code     = '';
$language = '';

$rawInput = file_get_contents('php://input');
$json     = json_decode($rawInput, true);

if ($json && is_array($json)) {
    $code     = isset($json['code'])     ? $json['code']             : '';
    $language = isset($json['language']) ? trim($json['language'])   : '';
} else {
    $code     = isset($_POST['code'])     ? $_POST['code']            : '';
    $language = isset($_POST['language']) ? trim($_POST['language'])  : '';
}

if (empty(trim($language))) {
    echo json_encode(['status' => 'error', 'message' => 'Language missing']);
    exit();
}

$stmt = $conn->prepare(
    "INSERT INTO `coding_drafts` (`student_id`, `language`, `code`, `updated_at`)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE `code` = VALUES(`code`), `updated_at` = NOW()"
);

if ($stmt) {
    $stmt->bind_param("iss", $student_id, $language, $code);
    if ($stmt->execute()) {
        $savedAt = date('h:i:s A');
        echo json_encode([
            'status'   => 'success',
            'message'  => 'Saved successfully',
            'saved_at' => $savedAt,   // lab_ide.js expects 'saved_at'
            'time'     => $savedAt    // script.js compat
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => $conn->error]);
}
?>
