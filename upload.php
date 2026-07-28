<?php
session_start(); require_once '../db.php';

if (!isset($_SESSION['staff'])) {
    header("Location: ../staff_login.php");
    exit();
}

$error = "";
$success = "";

// Get subjects for dropdown
$subjectsResult = mysqli_query($conn, "SELECT * FROM library_subjects ORDER BY subject_name");

if (isset($_POST['upload'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $staff_id = $_SESSION['staff']; // set at login

    // Handle new subject creation if selected
    if ($_POST['subject_id'] === '__new__') {
        $newSubjectName = trim($_POST['new_subject_name']);
        if (empty($newSubjectName)) {
            $error = "Please enter a name for the new subject.";
            $subject_id = 0;
        } else {
            $newSubjectEsc = mysqli_real_escape_string($conn, $newSubjectName);
            mysqli_query($conn, "INSERT INTO library_subjects (subject_name) VALUES ('$newSubjectEsc')");
            $subject_id = mysqli_insert_id($conn);
        }
    } else {
        $subject_id = (int)$_POST['subject_id'];
    }

    if (empty($title) || empty($_FILES['resource_file']['name'])) {
        $error = "Title and file are required.";
    } else {
        $file = $_FILES['resource_file'];

        // Validate size (max 50MB)
        $maxSize = 50 * 1024 * 1024;
        $originalName = basename($file['name']);
        $file_ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $allowedExts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'png', 'jpg', 'jpeg'];

        if (!in_array($file_ext, $allowedExts)) {
            $error = "Invalid file type. Allowed extensions: PDF, DOC, DOCX, PPT, PPTX, ZIP, PNG, JPG, JPEG.";
        } elseif ($file['size'] > $maxSize) {
            $error = "File too large. Max 50MB allowed.";
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = "Upload failed. Please try again.";
        } else {
            // Generate a unique filename to avoid collisions
            $uniqueName = uniqid('res_', true) . '.' . $file_ext;
            $destination = __DIR__ . '/uploads/' . $uniqueName;
            
            if (!is_dir(__DIR__ . '/uploads/')) {
                mkdir(__DIR__ . '/uploads/', 0777, true);
            }

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $titleEsc = mysqli_real_escape_string($conn, $title);
                $descEsc = mysqli_real_escape_string($conn, $description);
                $origNameEsc = mysqli_real_escape_string($conn, $originalName);
                $staffEsc = mysqli_real_escape_string($conn, $staff_id);

                $sql = "INSERT INTO library_resources 
                        (title, description, file_path, original_filename, resource_type, subject_id, uploader_id, status)
                        VALUES ('$titleEsc', '$descEsc', '$uniqueName', '$origNameEsc', '$file_ext', $subject_id, '$staffEsc', 'approved')";

                if (mysqli_query($conn, $sql)) {
                    $success = "Resource uploaded successfully! It is now live for all students.";
                } else {
                    $error = "Database error: " . mysqli_error($conn);
                }
            } else {
                $error = "Failed to save the file on the server.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Resource - Library</title>
    <style>
        :root {
            --primary: #ea580c;
            --bg: #fff7ed;
            --card-bg: #ffffff;
            --text-main: #431407;
            --text-muted: #9a6a52;
            --border: #fed7aa;
            --danger: #ef4444;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text-main); margin: 0; padding: 30px; }
        .container { max-width: 600px; margin: 0 auto; background: var(--card-bg); border: 1px solid var(--border); padding: 30px; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        h1 { color: var(--text-main); margin-top: 0; font-size: 24px; }
        label { display: block; margin: 15px 0 5px; font-weight: bold; color: var(--text-main); font-size: 14px; }
        input[type=text], input[type=file], select, textarea { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; box-sizing: border-box; background: var(--bg); color: var(--text-main); font-size: 14px; }
        textarea { height: 90px; }
        button { margin-top: 20px; padding: 12px 25px; background: var(--primary); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 700; }
        button:hover { opacity: 0.9; }
        .error { background: rgba(239, 68, 68, 0.15); color: var(--danger); padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }
        .success { background: rgba(21, 128, 61, 0.15); color: #27ae60; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }
        .back { display: inline-block; margin-bottom: 15px; color: var(--primary); text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
<div class="container">
    <a href="../staff_library.php" class="back">&larr; Back to Staff Library</a>
    <h1>Upload New Resource</h1>

    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Title</label>
        <input type="text" name="title" required>

        <label>Description</label>
        <textarea name="description"></textarea>

        <label>Subject</label>
        <select name="subject_id" id="subject_id" onchange="toggleNewSubject(this)" required>
            <option value="">-- Select Subject --</option>
            <?php mysqli_data_seek($subjectsResult, 0); while ($subj = mysqli_fetch_assoc($subjectsResult)): ?>
                <option value="<?php echo $subj['subject_id']; ?>"><?php echo htmlspecialchars($subj['subject_name']); ?></option>
            <?php endwhile; ?>
            <option value="__new__">+ Add New Subject</option>
        </select>

        <div id="new_subject_box" style="display:none;">
            <label>New Subject Name</label>
            <input type="text" name="new_subject_name" id="new_subject_name">
        </div>

        <script>
        function toggleNewSubject(sel) {
            document.getElementById('new_subject_box').style.display = 
                (sel.value === '__new__') ? 'block' : 'none';
            document.getElementById('new_subject_name').required = (sel.value === '__new__');
        }
        </script>

        <label>Resource File (PDF, DOC, DOCX, PPT, PPTX, ZIP, PNG, JPG - max 50MB)</label>
        <input type="file" name="resource_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.png,.jpg,.jpeg" required>

        <button type="submit" name="upload">Upload Resource</button>
    </form>
</div>
</body>
</html>
