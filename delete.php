<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['staff'])) {
    header("Location: ../staff_login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $res = mysqli_query($conn, "SELECT file_path FROM library_resources WHERE id = $id");
    if ($row = mysqli_fetch_assoc($res)) {
        $filePath = __DIR__ . '/uploads/' . $row['file_path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        mysqli_query($conn, "DELETE FROM library_resources WHERE id = $id");
        mysqli_query($conn, "DELETE FROM library_favorites WHERE resource_id = $id");
        mysqli_query($conn, "DELETE FROM library_ratings WHERE resource_id = $id");
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? '../staff_library.php';
header("Location: " . $referer);
exit();
?>
