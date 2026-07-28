<?php
require 'db.php';
$conn->query("CREATE TABLE IF NOT EXISTS library_subjects (
    subject_id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS library_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT,
    uploader_id VARCHAR(50),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    downloads_count INT DEFAULT 0,
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES library_subjects(subject_id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS library_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    rater_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES library_resources(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS library_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    resource_id INT NOT NULL,
    rater_id VARCHAR(50) NOT NULL,
    rating INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES library_resources(id) ON DELETE CASCADE
)");

echo "Tables created successfully!";
?>
