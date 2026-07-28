<?php
session_start(); require_once '../db.php';



header('Content-Type: application/json');

$resource_id = (int)($_POST['resource_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);

if ($resource_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

$rater_id = isset($_SESSION['student']) ? 'student_' . $_SESSION['student'] : (isset($_SESSION['staff']) ? 'staff_' . $_SESSION['staff'] : (isset($_SESSION['staff_id']) ? 'staff_' . $_SESSION['staff_id'] : 'guest'));
$raterEsc = mysqli_real_escape_string($conn, $rater_id);

$sql = "INSERT INTO library_ratings (resource_id, rater_id, rating) 
        VALUES ($resource_id, '$raterEsc', $rating)
        ON DUPLICATE KEY UPDATE rating = $rating";
mysqli_query($conn, $sql);

$avgRes = mysqli_query($conn, "SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM library_ratings WHERE resource_id = $resource_id");
$avgRow = mysqli_fetch_assoc($avgRes);

echo json_encode([
    'success' => true,
    'avg_rating' => round($avgRow['avg_rating'], 1),
    'total_ratings' => $avgRow['total']
]);
?>

