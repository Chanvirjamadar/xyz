<?php
session_start(); require_once '../db.php';



header('Content-Type: application/json');

$resource_id = (int)($_POST['resource_id'] ?? 0);
$rater_id = isset($_SESSION['student']) ? 'student_' . $_SESSION['student'] : (isset($_SESSION['staff']) ? 'staff_' . $_SESSION['staff'] : (isset($_SESSION['staff_id']) ? 'staff_' . $_SESSION['staff_id'] : 'guest'));
$raterEsc = mysqli_real_escape_string($conn, $rater_id);

$check = mysqli_query($conn, "SELECT id FROM library_favorites WHERE resource_id=$resource_id AND rater_id='$raterEsc'");

if (mysqli_num_rows($check) > 0) {
    mysqli_query($conn, "DELETE FROM library_favorites WHERE resource_id=$resource_id AND rater_id='$raterEsc'");
    echo json_encode(['success' => true, 'favorited' => false]);
} else {
    mysqli_query($conn, "INSERT INTO library_favorites (resource_id, rater_id) VALUES ($resource_id, '$raterEsc')");
    echo json_encode(['success' => true, 'favorited' => true]);
}
?>

