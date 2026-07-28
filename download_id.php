<?php
session_start();

// Allow access when a logged-in student requests their own card or when an id is provided in the URL (e.g., profile.php?id=123)
if (!isset($_SESSION['student']) && empty($_GET['id'])) {
    header("Location: student_login.php");
    exit();
}

require_once __DIR__ . "/includes/database.php";

// Prefer explicit ?id=... when provided; otherwise fall back to the logged-in student id
if (!empty($_GET['id']) && ctype_digit($_GET['id'])) {
    $requestedId = (int)$_GET['id'];
    // Only allow viewing another student's ID if user is an admin (session flag 'is_admin')
    if (!isset($_SESSION['student']) || ((int)$_SESSION['student'] !== $requestedId && empty($_SESSION['is_admin']))) {
        http_response_code(403);
        die('Unauthorized access to student ID card.');
    }
    $studentId = $requestedId;
} else {
    $studentId = (int)($_SESSION['student'] ?? 0);
}

$studentResult = function_exists('getStudentById') ? getStudentById($studentId) : null;
if (!$studentResult || empty($studentResult['success']) || empty($studentResult['data'])) {
    die('Student record not found.');
}
$student = $studentResult['data'];

// Securely resolve photo filename and ensure it exists inside uploads folder
$photo = '';
if (!empty($student['photo'])) {
    $photoName = basename($student['photo']);
    $uploadsDir = realpath(__DIR__ . '/assets/uploads/photos');
    $candidate = $uploadsDir ? realpath($uploadsDir . DIRECTORY_SEPARATOR . $photoName) : false;
    if ($candidate && strpos($candidate, $uploadsDir) === 0 && file_exists($candidate)) {
        // Safe to expose relative path
        $photo = 'assets/uploads/photos/' . $photoName;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>ID Card</title>

<style>

body{
font-family:Arial;
display:flex;
justify-content:center;
padding:40px;
background:#eee;
}

.card{

width:340px;
border:3px solid #0d47a1;
border-radius:12px;
padding:20px;
background:white;
text-align:center;

}

.card img{
border-radius:50%;
width:100px;
height:100px;
object-fit:cover;
}

h2{
color:#0d47a1;
}

button{
margin-top:20px;
padding:10px 20px;
cursor:pointer;
}

@media print{

button{
display:none;
}

body{
background:white;
}

}

</style>

</head>

<body>

<div class="card">

<h2>STUDENT ID CARD</h2>

<?php
if (!empty($photo)) {
    echo '<img src="'.htmlspecialchars($photo, ENT_QUOTES, 'UTF-8').'" alt="Student Photo">';
} else {
    echo '<img src="https://ui-avatars.com/api/?name='.urlencode($student['name'] ?? 'Student').'&background=0d47a1&color=fff" alt="Student Photo">';
}
?>

<h3><?php echo htmlspecialchars($student['name'] ?? 'Student', ENT_QUOTES, 'UTF-8'); ?></h3>

<p><b>PRN :</b> <?php echo htmlspecialchars($student['prn'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>

<p><b>Department :</b> <?php echo htmlspecialchars($student['department'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>

<p><b>Semester :</b> <?php echo htmlspecialchars($student['semester'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>

<?php
$qrText = !empty($student['generated_id']) ? $student['generated_id'] : ('STU-'.str_pad($studentId, 6, '0', STR_PAD_LEFT));
?>
<img src="generate_qr.php?text=<?php echo urlencode($qrText); ?>&size=120" width="90">

<br>

<button onclick="window.print()">

Download / Print

</button>

</div>

</body>
</html>
