<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['student'])) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$theme = isset($_POST['theme']) ? $_POST['theme'] : 'light';
$theme = in_array($theme, ['light', 'dark'], true) ? $theme : 'light';
$studentId = (int) $_SESSION['student'];

$check = $conn->query("SHOW COLUMNS FROM student LIKE 'theme'");
if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE student ADD COLUMN theme VARCHAR(20) NOT NULL DEFAULT 'light'");
}

$stmt = $conn->prepare("UPDATE student SET theme = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param('si', $theme, $studentId);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $stmt->error]);
        $stmt->close();
        exit;
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

echo json_encode(['success' => true, 'theme' => $theme]);
