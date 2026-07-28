<?php
// Start output buffering to catch any accidental echos or warnings
ob_start();
session_start();
include("db.php");

// Set header to JSON
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Unknown error occurred'];

try {
    if (!isset($_SESSION['student'])) {
        throw new Exception('Session expired. Please login again.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $student_id = $_SESSION['student'];
        $category = $_POST['category'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';

        if (empty($category) || empty($message)) {
            throw new Exception('Required fields are missing.');
        }

        // Check if database connection exists
        if (!$conn) {
            throw new Exception('Database connection failed.');
        }

        $query = "INSERT INTO queries (student_id, category, subject, message, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            throw new Exception('Query Preparation Failed: ' . $conn->error);
        }

        $stmt->bind_param("ssss", $student_id, $category, $subject, $message);

        if ($stmt->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Query submitted successfully!';
        } else {
            throw new Exception('Execute failed: ' . $stmt->error);
        }
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Clear any accidental output (like PHP warnings) before sending JSON
ob_clean();
echo json_encode($response);
exit();
