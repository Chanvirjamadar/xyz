<?php
require 'db.php';
$conn->query("INSERT IGNORE INTO library_subjects (subject_name) VALUES ('Computer Science'), ('Mathematics'), ('Physics'), ('General')");
echo 'Inserted subjects';
?>
