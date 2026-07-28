<?php
session_start(); require_once '../db.php';


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$preview = isset($_GET['preview']) ? true : false;

$sql = "SELECT * FROM library_resources WHERE id = $id AND status = 'approved'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) === 0) {
    die("File not found.");
}

$row = mysqli_fetch_assoc($result);
$filePath = __DIR__ . '/uploads/' . $row['file_path'];

if (!file_exists($filePath)) {
    die("File missing on server.");
}

// Increment counter (download only, not preview)
if (!$preview) {
    mysqli_query($conn, "UPDATE library_resources SET downloads_count = downloads_count + 1 WHERE id = $id");
}

$mime = mime_content_type($filePath);
header('Content-Type: ' . $mime);
if ($preview) {
    header('Content-Disposition: inline; filename="' . $row['original_filename'] . '"');
} else {
    header('Content-Disposition: attachment; filename="' . $row['original_filename'] . '"');
}
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit();
?>
