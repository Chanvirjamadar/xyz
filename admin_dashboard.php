<?php
session_start();
require_once __DIR__ . "/db.php";

// Security Check
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$adminUsername = $_SESSION['admin'];
$adminName = $_SESSION['admin_name'] ?? 'System Administrator';
$currentPage = 'admin_dashboard.php';

$feedbackMsg = "";
$feedbackType = "";

// Detect primary key column name on student table (id vs student_id)
$studentPkCol = "id";
$colCheck = @$conn->query("SHOW COLUMNS FROM student LIKE 'student_id'");
if ($colCheck && $colCheck->num_rows > 0) {
    $studentPkCol = "student_id";
}

// ── SCHEMA-SAFE DATABASE HELPERS ──────────────────────────────────────
function safeInsert($conn, $table, $data) {
    $existingCols = [];
    $colRes = @$conn->query("SHOW COLUMNS FROM `$table`");
    if ($colRes) {
        while ($cRow = $colRes->fetch_assoc()) {
            $existingCols[] = strtolower($cRow['Field']);
        }
    }
    $fields = [];
    $values = [];
    foreach ($data as $k => $v) {
        if (in_array(strtolower($k), $existingCols)) {
            $fields[] = "`$k`";
            $values[] = "'" . mysqli_real_escape_string($conn, $v) . "'";
        }
    }
    if (empty($fields)) return false;
    $sql = "INSERT INTO `$table` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
    return $conn->query($sql);
}

function safeUpdate($conn, $table, $data, $whereClause) {
    $existingCols = [];
    $colRes = @$conn->query("SHOW COLUMNS FROM `$table`");
    if ($colRes) {
        while ($cRow = $colRes->fetch_assoc()) {
            $existingCols[] = strtolower($cRow['Field']);
        }
    }
    $sets = [];
    foreach ($data as $k => $v) {
        if (in_array(strtolower($k), $existingCols)) {
            $sets[] = "`$k`='" . mysqli_real_escape_string($conn, $v) . "'";
        }
    }
    if (empty($sets)) return false;
    $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE $whereClause";
    return $conn->query($sql);
}

// Universal Spreadsheet Parser (Excel .xlsx/.xls HTML/XML & CSV/TSV)
function parseSpreadsheetRows($tmpFile) {
    $rows = [];
    $content = @file_get_contents($tmpFile);
    if (!$content) return $rows;

    // Check if file is HTML/XML table exported by Excel
    if (strpos($content, '<table') !== false || strpos($content, '<tr') !== false) {
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $content, $trMatches);
        foreach ($trMatches[1] as $tr) {
            preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $tr, $tdMatches);
            if (!empty($tdMatches[1])) {
                $row = array_map(function($cell) {
                    return trim(html_entity_decode(strip_tags($cell)));
                }, $tdMatches[1]);
                $rows[] = $row;
            }
        }
    } else {
        // Plain text CSV / TSV spreadsheet parsing
        $handle = @fopen($tmpFile, "r");
        if ($handle !== FALSE) {
            $firstLine = fgets($handle);
            rewind($handle);
            $delimiter = ",";
            if (substr_count($firstLine, "\t") > substr_count($firstLine, ",")) {
                $delimiter = "\t";
            } elseif (substr_count($firstLine, ";") > substr_count($firstLine, ",")) {
                $delimiter = ";";
            }

            while (($data = fgetcsv($handle, 2000, $delimiter)) !== FALSE) {
                if (!empty($data) && count(array_filter($data)) > 0) {
                    $rows[] = array_map('trim', $data);
                }
            }
            fclose($handle);
        }
    }
    return $rows;
}

// ── HANDLE ADMIN POST ACTIONS ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_action'])) {
    $action = $_POST['admin_action'];

    // 1. ADD STUDENT
    if ($action === 'add_student') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '12345');
        $prn = trim($_POST['prn'] ?? '');
        $roll_no = trim($_POST['roll_no'] ?? '');
        $branch = trim($_POST['branch'] ?? 'Computer Engineering');
        $semester = trim($_POST['semester'] ?? 'Semester 4');
        $phone = trim($_POST['phone'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Male');
        $address = trim($_POST['address'] ?? '');
        $cgpa = floatval($_POST['cgpa'] ?? 8.5);
        $attendance = intval($_POST['attendance'] ?? 90);
        $fee_status = mysqli_real_escape_string($conn, $_POST['fee_status'] ?? 'Paid');

        if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
            $feedbackMsg = "Validation Error: Mobile number must be exactly 10 digits (numbers only).";
            $feedbackType = "danger";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $feedbackMsg = "Validation Error: Please enter a valid email address.";
            $feedbackType = "danger";
        } elseif (!empty($name) && !empty($email)) {
            $sData = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'prn' => $prn,
                'roll_no' => $roll_no,
                'branch' => $branch,
                'semester' => $semester,
                'phone' => $phone,
                'gender' => $gender,
                'address' => $address
            ];
            if (safeInsert($conn, 'student', $sData)) {
                $newStudentId = $conn->insert_id;
                // Add academic record
                @$conn->query("INSERT INTO student_academic (student_id, cgpa, attendance, fee_status) VALUES ('$newStudentId', $cgpa, $attendance, '$fee_status') ON DUPLICATE KEY UPDATE cgpa=$cgpa, attendance=$attendance, fee_status='$fee_status'");
                
                // Sync student_profile table
                $cleanRoll = mysqli_real_escape_string($conn, $roll_no);
                $cleanPrn = mysqli_real_escape_string($conn, $prn);
                $cleanBranch = mysqli_real_escape_string($conn, $branch);
                $cleanSem = mysqli_real_escape_string($conn, $semester);
                $cleanPhone = mysqli_real_escape_string($conn, $phone);
                $cleanGender = mysqli_real_escape_string($conn, $gender);
                $cleanAddr = mysqli_real_escape_string($conn, $address);
                @$conn->query("INSERT INTO student_profile (student_id, roll_no, prn, department, semester, mobile, gender, address, cgpa, attendance, fees_status) VALUES ('$newStudentId', '$cleanRoll', '$cleanPrn', '$cleanBranch', '$cleanSem', '$cleanPhone', '$cleanGender', '$cleanAddr', $cgpa, $attendance, '$fee_status') ON DUPLICATE KEY UPDATE roll_no='$cleanRoll', prn='$cleanPrn', department='$cleanBranch', semester='$cleanSem', mobile='$cleanPhone', gender='$cleanGender', address='$cleanAddr', cgpa=$cgpa, attendance=$attendance, fees_status='$fee_status'");

                // Log activity
                @$conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$adminName', 'Admin', 'Create', 'Added new student: $name ($email)')");

                $feedbackMsg = "Student '$name' added successfully!";
                $feedbackType = "success";
            } else {
                $feedbackMsg = "Error adding student: " . $conn->error;
                $feedbackType = "danger";
            }
        } else {
            $feedbackMsg = "Student name and email are required.";
            $feedbackType = "warning";
        }
    }

    // 2. EDIT STUDENT
    elseif ($action === 'edit_student') {
        $student_id = intval($_POST['student_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $prn = trim($_POST['prn'] ?? '');
        $roll_no = trim($_POST['roll_no'] ?? '');
        $branch = trim($_POST['branch'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $cgpa = floatval($_POST['cgpa'] ?? 8.5);
        $attendance = intval($_POST['attendance'] ?? 90);
        $fee_status = mysqli_real_escape_string($conn, $_POST['fee_status'] ?? 'Paid');

        if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
            $feedbackMsg = "Validation Error: Student mobile number must be exactly 10 digits (numbers only).";
            $feedbackType = "danger";
        } elseif ($student_id > 0 && !empty($name)) {
            $sData = [
                'name' => $name,
                'email' => $email,
                'prn' => $prn,
                'roll_no' => $roll_no,
                'branch' => $branch,
                'semester' => $semester,
                'phone' => $phone
            ];
            safeUpdate($conn, 'student', $sData, "`$studentPkCol`=$student_id");

            // Update academic details
            @$conn->query("INSERT INTO student_academic (student_id, cgpa, attendance, fee_status) VALUES ('$student_id', $cgpa, $attendance, '$fee_status') ON DUPLICATE KEY UPDATE cgpa=$cgpa, attendance=$attendance, fee_status='$fee_status'");

            // Sync student_profile table
            $cleanRoll = mysqli_real_escape_string($conn, $roll_no);
            $cleanPrn = mysqli_real_escape_string($conn, $prn);
            $cleanBranch = mysqli_real_escape_string($conn, $branch);
            $cleanSem = mysqli_real_escape_string($conn, $semester);
            $cleanPhone = mysqli_real_escape_string($conn, $phone);
            @$conn->query("INSERT INTO student_profile (student_id, roll_no, prn, department, semester, mobile, cgpa, attendance, fees_status) VALUES ('$student_id', '$cleanRoll', '$cleanPrn', '$cleanBranch', '$cleanSem', '$cleanPhone', $cgpa, $attendance, '$fee_status') ON DUPLICATE KEY UPDATE roll_no='$cleanRoll', prn='$cleanPrn', department='$cleanBranch', semester='$cleanSem', mobile='$cleanPhone', cgpa=$cgpa, attendance=$attendance, fees_status='$fee_status'");

            // Log activity
            @$conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$adminName', 'Admin', 'Update', 'Updated student details for: $name (ID: $student_id)')");

            $feedbackMsg = "Student details updated successfully!";
            $feedbackType = "success";
        }
    }

    // 3. DELETE STUDENT
    elseif ($action === 'delete_student') {
        $student_id = intval($_POST['student_id'] ?? 0);
        if ($student_id > 0) {
            $sRes = $conn->query("SELECT name FROM student WHERE `$studentPkCol`=$student_id");
            $sName = ($sRow = $sRes->fetch_assoc()) ? $sRow['name'] : "Student #$student_id";

            $conn->query("DELETE FROM student WHERE `$studentPkCol`=$student_id");
            $conn->query("DELETE FROM student_academic WHERE student_id='$student_id'");
            $conn->query("DELETE FROM student_profile WHERE student_id='$student_id'");

            // Log activity
            $conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$adminName', 'Admin', 'Delete', 'Deleted student account: $sName')");

            $feedbackMsg = "Student account deleted.";
            $feedbackType = "success";
        }
    }

    // 4. ADD STAFF
    elseif ($action === 'add_staff') {
        $staff_id = trim($_POST['staff_id'] ?? 'S' . time());
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '123456');
        $department = trim($_POST['department'] ?? 'Computer Engineering');
        $designation = trim($_POST['designation'] ?? 'Assistant Professor');
        $phone = trim($_POST['phone'] ?? '');
        $qualification = trim($_POST['qualification'] ?? 'M.Tech');
        $gender = trim($_POST['gender'] ?? 'Male');

        if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
            $feedbackMsg = "Validation Error: Faculty mobile number must be exactly 10 digits (numbers only).";
            $feedbackType = "danger";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $feedbackMsg = "Validation Error: Please enter a valid email address.";
            $feedbackType = "danger";
        } elseif (!empty($name) && !empty($email)) {
            $stData = [
                'staff_id' => $staff_id,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'department' => $department,
                'designation' => $designation,
                'phone' => $phone,
                'qualification' => $qualification,
                'gender' => $gender
            ];
            if (safeInsert($conn, 'staff_profile', $stData)) {
                // Log activity
                $conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$adminName', 'Admin', 'Create', 'Added new staff member: $name ($department)')");

                $feedbackMsg = "Staff member '$name' added successfully!";
                $feedbackType = "success";
            } else {
                $feedbackMsg = "Error adding staff: " . $conn->error;
                $feedbackType = "danger";
            }
        } else {
            $feedbackMsg = "Staff name and email are required.";
            $feedbackType = "warning";
        }
    }

    // 5. EDIT STAFF
    elseif ($action === 'edit_staff') {
        $id = intval($_POST['id'] ?? 0);
        $staff_id = trim($_POST['staff_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
            $feedbackMsg = "Validation Error: Faculty mobile number must be exactly 10 digits (numbers only).";
            $feedbackType = "danger";
        } elseif ($id > 0 && !empty($name)) {
            $stData = [
                'staff_id' => $staff_id,
                'name' => $name,
                'email' => $email,
                'department' => $department,
                'designation' => $designation,
                'phone' => $phone
            ];
            safeUpdate($conn, 'staff_profile', $stData, "id=$id");

            // Log activity
            $conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$adminName', 'Admin', 'Update', 'Updated faculty details for: $name ($staff_id)')");

            $feedbackMsg = "Staff details updated successfully!";
            $feedbackType = "success";
        }
    }

    // 6. DELETE STAFF
    elseif ($action === 'delete_staff') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stRes = $conn->query("SELECT name FROM staff_profile WHERE id=$id");
            $stName = ($stRow = $stRes->fetch_assoc()) ? $stRow['name'] : "Staff #$id";

            $conn->query("DELETE FROM staff_profile WHERE id=$id");

            // Log activity
            $conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$adminName', 'Admin', 'Delete', 'Deleted faculty account: $stName')");

            $feedbackMsg = "Staff member deleted.";
            $feedbackType = "success";
        }
    }

    // 7. BULK IMPORT STUDENTS (CSV / EXCEL SPREADSHEET FILE UPLOAD)
    elseif ($action === 'bulk_import_students') {
        if (isset($_FILES['student_csv']) && $_FILES['student_csv']['error'] === UPLOAD_ERR_OK) {
            $tmpFile = $_FILES['student_csv']['tmp_name'];
            $rows = parseSpreadsheetRows($tmpFile);
            $importedCount = 0;
            $rowNum = 0;

            foreach ($rows as $data) {
                $rowNum++;
                if ($rowNum === 1 && (strtolower(trim($data[0])) === 'name' || strtolower(trim($data[0])) === 'full name')) {
                    continue;
                }
                if (count($data) >= 2) {
                    $name = trim($data[0]);
                    $email = trim($data[1]);
                    $password = isset($data[2]) && trim($data[2]) !== '' ? trim($data[2]) : '12345';
                    $prn = isset($data[3]) ? trim($data[3]) : '';
                    $roll_no = isset($data[4]) ? trim($data[4]) : '';
                    $branch = isset($data[5]) && trim($data[5]) !== '' ? trim($data[5]) : 'Computer Engineering';
                    $semester = isset($data[6]) && trim($data[6]) !== '' ? trim($data[6]) : 'Semester 4';
                    $phone = isset($data[7]) ? trim($data[7]) : '';
                    $cgpa = isset($data[8]) ? floatval($data[8]) : 8.5;
                    $attendance = isset($data[9]) ? intval($data[9]) : 90;
                    $fee_status = isset($data[10]) && trim($data[10]) !== '' ? mysqli_real_escape_string($conn, trim($data[10])) : 'Paid';

                    if (!empty($name) && !empty($email)) {
                        $sData = [
                            'name' => $name,
                            'email' => $email,
                            'password' => $password,
                            'prn' => $prn,
                            'roll_no' => $roll_no,
                            'branch' => $branch,
                            'semester' => $semester,
                            'phone' => $phone
                        ];
                        if (safeInsert($conn, 'student', $sData)) {
                            $newId = $conn->insert_id;
                            @$conn->query("INSERT INTO student_academic (student_id, cgpa, attendance, fee_status) VALUES ('$newId', $cgpa, $attendance, '$fee_status') ON DUPLICATE KEY UPDATE cgpa=$cgpa, attendance=$attendance, fee_status='$fee_status'");
                            
                            $cleanRoll = mysqli_real_escape_string($conn, $roll_no);
                            $cleanPrn = mysqli_real_escape_string($conn, $prn);
                            $cleanBranch = mysqli_real_escape_string($conn, $branch);
                            $cleanSem = mysqli_real_escape_string($conn, $semester);
                            $cleanPhone = mysqli_real_escape_string($conn, $phone);
                            @$conn->query("INSERT INTO student_profile (student_id, roll_no, prn, department, semester, mobile, cgpa, attendance, fees_status) VALUES ('$newId', '$cleanRoll', '$cleanPrn', '$cleanBranch', '$cleanSem', '$cleanPhone', $cgpa, $attendance, '$fee_status') ON DUPLICATE KEY UPDATE roll_no='$cleanRoll', prn='$cleanPrn', department='$cleanBranch', semester='$cleanSem', mobile='$cleanPhone', cgpa=$cgpa, attendance=$attendance, fees_status='$fee_status'");

                            $importedCount++;
                        }
                    }
                }
            }
            $conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$adminName', 'Admin', 'Bulk Import', 'Bulk imported $importedCount student records via Excel/CSV file')");

            $feedbackMsg = "Bulk Import Complete: Successfully added $importedCount student records!";
            $feedbackType = "success";
        } else {
            $feedbackMsg = "Please select a valid Excel or CSV file to upload.";
            $feedbackType = "warning";
        }
    }

    // 8. BULK IMPORT STAFF (CSV / EXCEL SPREADSHEET FILE UPLOAD)
    elseif ($action === 'bulk_import_staff') {
        if (isset($_FILES['staff_csv']) && $_FILES['staff_csv']['error'] === UPLOAD_ERR_OK) {
            $tmpFile = $_FILES['staff_csv']['tmp_name'];
            $rows = parseSpreadsheetRows($tmpFile);
            $importedCount = 0;
            $rowNum = 0;

            foreach ($rows as $data) {
                $rowNum++;
                if ($rowNum === 1 && (strtolower(trim($data[0])) === 'staff_id' || strtolower(trim($data[0])) === 'staff id')) {
                    continue;
                }
                if (count($data) >= 3) {
                    $staff_id = trim($data[0]);
                    $name = trim($data[1]);
                    $email = trim($data[2]);
                    $password = isset($data[3]) && trim($data[3]) !== '' ? trim($data[3]) : '123456';
                    $department = isset($data[4]) && trim($data[4]) !== '' ? trim($data[4]) : 'Computer Engineering';
                    $designation = isset($data[5]) && trim($data[5]) !== '' ? trim($data[5]) : 'Assistant Professor';
                    $phone = isset($data[6]) ? trim($data[6]) : '';
                    $qualification = isset($data[7]) && trim($data[7]) !== '' ? trim($data[7]) : 'M.Tech';

                    if (!empty($name) && !empty($email)) {
                        $stData = [
                            'staff_id' => $staff_id,
                            'name' => $name,
                            'email' => $email,
                            'password' => $password,
                            'department' => $department,
                            'designation' => $designation,
                            'phone' => $phone,
                            'qualification' => $qualification
                        ];
                        if (safeInsert($conn, 'staff_profile', $stData)) {
                            $importedCount++;
                        }
                    }
                }
            }
            $conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$adminName', 'Admin', 'Bulk Import', 'Bulk imported $importedCount faculty records via Excel/CSV file')");

            $feedbackMsg = "Bulk Import Complete: Successfully added $importedCount faculty records!";
            $feedbackType = "success";
        } else {
            $feedbackMsg = "Please select a valid Excel or CSV file to upload.";
            $feedbackType = "warning";
        }
    }

    // 9. DELETE SINGLE ACTIVITY LOG ENTRY
    elseif ($action === 'delete_activity') {
        $activity_id = intval($_POST['activity_id'] ?? 0);
        if ($activity_id > 0) {
            $conn->query("DELETE FROM portal_activity WHERE id = $activity_id");
            $feedbackMsg = "Activity log entry deleted successfully.";
            $feedbackType = "success";
        }
    }

    // 11. CREATE ANNOUNCEMENT
    elseif ($action === 'create_announcement') {
        $title = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $target_audience = trim($_POST['target_audience'] ?? 'both');

        if (!empty($title) && !empty($message)) {
            $aData = [
                'title' => $title,
                'message' => $message,
                'target_audience' => $target_audience,
                'posted_by' => $adminName
            ];
            if (safeInsert($conn, 'announcements', $aData)) {
                $audName = ($target_audience === 'student') ? 'Students Only' : (($target_audience === 'staff') ? 'Faculty / Staff Only' : 'Both Students & Faculty');
                @$conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$adminName', 'Admin', 'Announcement', 'Broadcasted announcement to $audName: $title')");

                $feedbackMsg = "Announcement '$title' broadcasted successfully to $audName!";
                $feedbackType = "success";
            } else {
                $feedbackMsg = "Error creating announcement: " . $conn->error;
                $feedbackType = "danger";
            }
        } else {
            $feedbackMsg = "Announcement title and message content are required.";
            $feedbackType = "warning";
        }
    }

    // 12. DELETE ANNOUNCEMENT
    elseif ($action === 'delete_announcement') {
        $announcement_id = intval($_POST['announcement_id'] ?? 0);
        if ($announcement_id > 0) {
            $conn->query("DELETE FROM announcements WHERE id = $announcement_id");
            @$conn->query("DELETE FROM announcement_reads WHERE announcement_id = $announcement_id");

            @$conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$adminName', 'Admin', 'Delete Announcement', 'Deleted announcement ID #$announcement_id')");

            $feedbackMsg = "Announcement deleted successfully.";
            $feedbackType = "success";
        }
    }
}

// ── FETCH STATS & DATA FOR DASHBOARD ─────────────────────────────────
$studentCount = ($r = $conn->query("SELECT COUNT(*) as c FROM student")) ? $r->fetch_assoc()['c'] : 0;
$staffCount = ($r = $conn->query("SELECT COUNT(*) as c FROM staff_profile")) ? $r->fetch_assoc()['c'] : 0;
$activityCount = ($r = $conn->query("SELECT COUNT(*) as c FROM portal_activity")) ? $r->fetch_assoc()['c'] : 0;
$materialCount = ($r = $conn->query("SELECT COUNT(*) as c FROM study_materials")) ? $r->fetch_assoc()['c'] : 0;
$paperCount = ($r = $conn->query("SELECT COUNT(*) as c FROM question_bank")) ? $r->fetch_assoc()['c'] : 0;
$queryCount = ($r = $conn->query("SELECT COUNT(*) as c FROM queries")) ? $r->fetch_assoc()['c'] : 0;
$announcementCount = ($r = $conn->query("SELECT COUNT(*) as c FROM announcements")) ? $r->fetch_assoc()['c'] : 0;

$announcementsRes = $conn->query("SELECT * FROM announcements ORDER BY id DESC");

// Detect primary key column name on student table (id vs student_id)
$studentPkCol = "id";
$colCheck = @$conn->query("SHOW COLUMNS FROM student LIKE 'student_id'");
if ($colCheck && $colCheck->num_rows > 0) {
    $studentPkCol = "student_id";
}

// Fetch Students
$studentSearch = isset($_GET['search_student']) ? trim($_GET['search_student']) : '';
$studentWhere = "";
if ($studentSearch !== '') {
    $sSafe = mysqli_real_escape_string($conn, $studentSearch);
    $studentWhere = " WHERE s.name LIKE '%$sSafe%' OR s.email LIKE '%$sSafe%' OR s.prn LIKE '%$sSafe%' OR s.roll_no LIKE '%$sSafe%' ";
}
$studentsRes = $conn->query("
    SELECT s.*, s.$studentPkCol as id_val, sa.cgpa, sa.attendance, sa.fee_status 
    FROM student s 
    LEFT JOIN student_academic sa ON (s.$studentPkCol = sa.student_id OR CAST(s.$studentPkCol AS CHAR) = sa.student_id)
    $studentWhere 
    ORDER BY s.$studentPkCol DESC
");

// Fetch Staff
$staffSearch = isset($_GET['search_staff']) ? trim($_GET['search_staff']) : '';
$staffWhere = "";
if ($staffSearch !== '') {
    $stSafe = mysqli_real_escape_string($conn, $staffSearch);
    $staffWhere = " WHERE name LIKE '%$stSafe%' OR email LIKE '%$stSafe%' OR staff_id LIKE '%$stSafe%' OR department LIKE '%$stSafe%' ";
}
$staffRes = $conn->query("SELECT * FROM staff_profile $staffWhere ORDER BY id DESC");

// Fetch Dual Dashboard Activity Logs
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : 'all';
$roleWhere = "";
if ($roleFilter === 'student') {
    $roleWhere = " WHERE user_role = 'Student' ";
} elseif ($roleFilter === 'staff') {
    $roleWhere = " WHERE user_role = 'Staff' ";
} elseif ($roleFilter === 'admin') {
    $roleWhere = " WHERE user_role = 'Admin' ";
}

$activityRes = $conn->query("SELECT * FROM portal_activity $roleWhere ORDER BY created_at DESC LIMIT 100");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Dashboard | ZEALHUB</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #4361ee;
            --primary-hover: #3a56d4;
            --bg: #f8fafc;
            --header-bg: #ffffff;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --glow: rgba(67, 97, 238, 0.15);
            --input-bg: #f8fafc;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
        }

        [data-theme="dark"] {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg: #0f172a;
            --header-bg: #1e293b;
            --sidebar-bg: #1e293b;
            --card-bg: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --glow: rgba(99, 102, 241, 0.25);
            --input-bg: #0f172a;
        }

        [data-theme="yellow"], [data-theme="golden"] {
            --primary: #d97706;
            --primary-hover: #b45309;
            --bg: #fefce8;
            --header-bg: #ffffff;
            --sidebar-bg: #713f12;
            --card-bg: #ffffff;
            --text-main: #422006;
            --text-muted: #854d0e;
            --border: #fef08a;
            --glow: rgba(217, 119, 6, 0.2);
            --input-bg: #fefce8;
        }

        [data-theme="sunset"] {
            --primary: #ea580c;
            --primary-hover: #c2410c;
            --bg: #fff7ed;
            --header-bg: #ffffff;
            --sidebar-bg: #7c2d12;
            --card-bg: #ffffff;
            --text-main: #431407;
            --text-muted: #9a6a52;
            --border: #fed7aa;
            --glow: rgba(234, 88, 12, 0.2);
            --input-bg: #fff7ed;
        }

        [data-theme="ocean"] {
            --primary: #0891b2;
            --primary-hover: #0e7490;
            --bg: #ecfeff;
            --header-bg: #ffffff;
            --sidebar-bg: #164e63;
            --card-bg: #ffffff;
            --text-main: #164e63;
            --text-muted: #5b8a99;
            --border: #a5f3fc;
            --glow: rgba(8, 145, 178, 0.2);
            --input-bg: #ecfeff;
        }

        [data-theme="midnight"] {
            --primary: #38bdf8;
            --primary-hover: #0284c7;
            --bg: #030712;
            --header-bg: #0b0f19;
            --sidebar-bg: #111827;
            --card-bg: #111827;
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
            --border: #1f2937;
            --glow: rgba(56, 189, 248, 0.25);
            --input-bg: #030712;
        }

        [data-theme="forest"] {
            --primary: #15803d;
            --primary-hover: #166534;
            --bg: #f0fdf4;
            --header-bg: #ffffff;
            --sidebar-bg: #14532d;
            --card-bg: #ffffff;
            --text-main: #14532d;
            --text-muted: #4d7c62;
            --border: #bbf7d0;
            --glow: rgba(21, 128, 61, 0.2);
            --input-bg: #f0fdf4;
        }

        [data-theme="pink"] {
            --primary: #db2777;
            --primary-hover: #be185d;
            --bg: #fdf2f8;
            --header-bg: #ffffff;
            --sidebar-bg: #831843;
            --card-bg: #ffffff;
            --text-main: #500724;
            --text-muted: #9d174d;
            --border: #fbcfe8;
            --glow: rgba(219, 39, 119, 0.2);
            --input-bg: #fdf2f8;
        }

        [data-theme="purple"] {
            --primary: #9333ea;
            --primary-hover: #7e22ce;
            --bg: #faf5ff;
            --header-bg: #ffffff;
            --sidebar-bg: #581c87;
            --card-bg: #ffffff;
            --text-main: #3b0764;
            --text-muted: #7e22ce;
            --border: #e9d5ff;
            --glow: rgba(147, 51, 234, 0.2);
            --input-bg: #faf5ff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        body {
            background: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
        }

        /* HEADER */
        .header {
            height: 72px;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.04);
        }

        .header-left { display: flex; align-items: center; gap: 15px; }

        .menu-btn {
            background: var(--primary);
            color: #ffffff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            box-shadow: 0 4px 14px var(--glow);
            transition: all 0.25s ease;
        }

        .menu-btn:hover {
            transform: scale(1.05);
            background: var(--primary-hover);
        }

        .logo {
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            color: #ffffff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
        }

        .logo-text { display: flex; flex-direction: column; }
        .brand-name { font-size: 20px; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; line-height: 1.1; }
        .brand-tag { font-size: 9px; font-weight: 800; color: var(--danger); letter-spacing: 1px; text-transform: uppercase; }

        .header-right { display: flex; align-items: center; gap: 14px; }

        .icon-btn {
            background: var(--input-bg);
            color: var(--text-main);
            border: 1px solid var(--border);
            width: 42px;
            height: 42px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.2s ease;
        }

        .icon-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

        .profile-pill {
            display: flex; align-items: center; gap: 10px; padding: 6px 14px; border-radius: 14px; border: 1px solid var(--border); background: var(--card-bg); text-decoration: none; color: inherit;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            height: calc(100vh - 72px);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            position: fixed;
            top: 72px;
            left: 0;
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            z-index: 999;
            overflow-y: auto;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.sidebar-collapsed .sidebar {
            transform: translateX(-100%);
        }

        body.sidebar-collapsed .main-content {
            margin-left: 0 !important;
        }

        .sidebar button, .sidebar a {
            background: transparent;
            border: none;
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            text-align: left;
            transition: all 0.2s ease;
        }

        .sidebar button:hover, .sidebar button.active, .sidebar a:hover {
            background: var(--primary);
            color: #ffffff !important;
            box-shadow: 0 4px 14px var(--glow);
        }

        .sidebar a.btn-logout { margin-top: auto; background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .sidebar a.btn-logout:hover { background: var(--danger); color: white !important; }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            margin-top: 72px;
            padding: 30px;
            min-height: calc(100vh - 72px);
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .page-header-flex {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 15px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 22px;
            border-radius: 20px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        /* TAB PANELS */
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        .card-box {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border);
            padding: 25px;
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block; font-size: 12px; font-weight: 700; color: var(--text-main); margin-bottom: 6px; text-transform: uppercase;
        }

        .form-control {
            width: 100%; padding: 11px 14px; border-radius: 12px; border: 1px solid var(--border); background: var(--input-bg); color: var(--text-main); font-size: 13.5px; outline: none;
        }

        .form-control:focus { border-color: var(--primary); }

        .btn-submit {
            padding: 12px 24px; background: var(--primary); color: white; border: none; border-radius: 12px; font-weight: 800; font-size: 13.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
        }

        .btn-submit:hover { opacity: 0.9; }

        .custom-table {
            width: 100%; border-collapse: collapse; text-align: left;
        }

        .custom-table th {
            background: var(--bg); padding: 14px 18px; font-size: 12px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border);
        }

        .custom-table td {
            padding: 16px 18px; border-bottom: 1px solid var(--border); font-size: 13.5px;
        }

        .badge-pill {
            padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;
        }

        .badge-success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .badge-primary { background: rgba(67, 97, 238, 0.15); color: var(--primary); }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        .action-btn-sm {
            padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration: none;
        }

        .btn-edit { background: rgba(67, 97, 238, 0.1); color: var(--primary); }
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

        .activity-item {
            padding: 16px; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; gap: 14px;
        }

        .activity-icon {
            width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;
        }

        /* MODAL */
        .modal {
            position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 3000; padding: 20px;
        }
        .modal.active { display: flex; }
        .modal-card {
            background: var(--card-bg); padding: 30px; border-radius: 24px; width: min(90%, 650px); border: 1px solid var(--border); max-height: 90vh; overflow-y: auto;
        }

        .alert { padding: 14px 18px; border-radius: 12px; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .alert-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }

        .theme-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 3000; padding: 20px; }
        .theme-modal.active { display: flex; }
        .theme-card { background: var(--card-bg); padding: 30px; border-radius: 24px; width: min(90%, 440px); border: 1px solid var(--border); text-align: center; }
        .theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; }
        .theme-opt { padding: 14px; border-radius: 14px; border: 2px solid var(--border); cursor: pointer; font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 10px; color: var(--text-main); }

        .footer { margin-top: 40px; padding: 20px 0; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; color: var(--text-muted); font-size: 13px; }

        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); width: 260px !important; }
            .main-content { margin-left: 0 !important; padding: 20px 15px; }
        }
    </style>
</head>

<body data-theme="light">

    <!-- HEADER -->
    <header class="header">
        <div class="header-left">
            <button type="button" class="menu-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Toggle Sidebar Navigation">
                <i class="fa-solid fa-bars"></i>
            </button>
            <a href="admin_dashboard.php" class="logo">
                <div class="logo-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="logo-text">
                    <span class="brand-name">ZEALHUB</span>
                    <span class="brand-tag">ADMIN GOVERNANCE</span>
                </div>
            </a>
        </div>

        <div class="header-right">
            <button class="icon-btn" id="themeBtn" type="button" title="Choose Theme">
                <i class="fa-solid fa-palette"></i>
            </button>

            <a href="admin_profile.php" class="profile-pill" style="border-color: var(--primary);">
                <div style="text-align: right;">
                    <p style="font-size: 11px; font-weight: 800; line-height: 1.2;"><?= htmlspecialchars($adminName) ?></p>
                    <p style="font-size: 9px; color: var(--danger); font-weight: 800;">SUPER ADMIN</p>
                </div>
                <div style="width: 34px; height: 34px; background: var(--danger); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px;">
                    AD
                </div>
            </a>

            <a href="admin_logout.php" class="icon-btn" title="Logout" style="color: var(--danger);">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <button type="button" class="tab-link active" onclick="switchMainTab(this, 'tab-overview')">
            <i class="fa-solid fa-chart-pie"></i> <span>Dashboard Overview</span>
        </button>
        <button type="button" class="tab-link" onclick="switchMainTab(this, 'tab-students')">
            <i class="fa-solid fa-user-graduate"></i> <span>Student Management</span>
        </button>
        <button type="button" class="tab-link" onclick="switchMainTab(this, 'tab-staff')">
            <i class="fa-solid fa-chalkboard-user"></i> <span>Staff Management</span>
        </button>
        <button type="button" class="tab-link" onclick="switchMainTab(this, 'tab-activity')">
            <i class="fa-solid fa-bolt"></i> <span>Dual Activity Logs</span>
        </button>
        <button type="button" class="tab-link" onclick="switchMainTab(this, 'tab-announcements')">
            <i class="fa-solid fa-bullhorn" style="color: #ea580c;"></i> <span>Announcements</span>
        </button>
        <a href="admin_profile.php" class="tab-link">
            <i class="fa-solid fa-user-shield" style="color: #6366f1;"></i> <span>Admin Profile</span>
        </a>
        <button type="button" class="tab-link" id="sidebarThemeBtn" onclick="toggleThemeModal(true)" style="color: #f59e0b; margin-top: 10px;">
            <i class="fa-solid fa-palette"></i> <span>Choose Portal Theme</span>
        </button>
        <a href="admin_logout.php" class="btn-logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span>
        </a>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <?php if (!empty($feedbackMsg)): ?>
            <div class="alert alert-<?= $feedbackType ?>">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($feedbackMsg) ?>
            </div>
        <?php endif; ?>

        <!-- TAB 1: OVERVIEW -->
        <div id="tab-overview" class="tab-panel active">
            <div class="page-header-flex">
                <div>
                    <h1 style="font-size: 26px; font-weight: 800;">Admin Control Center ⚡</h1>
                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Monitor student & staff accounts, manage data, and audit real-time portal activity.</p>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.15); color: var(--primary);">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <div>
                        <p style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Total Students</p>
                        <h2 style="font-size: 24px; font-weight: 800; color: var(--text-main);"><?= $studentCount ?></h2>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;">
                        <i class="fa-solid fa-chalkboard-user"></i>
                    </div>
                    <div>
                        <p style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Total Faculty / Staff</p>
                        <h2 style="font-size: 24px; font-weight: 800; color: var(--text-main);"><?= $staffCount ?></h2>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <div>
                        <p style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Activity Events</p>
                        <h2 style="font-size: 24px; font-weight: 800; color: var(--text-main);"><?= $activityCount ?></h2>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                    <div>
                        <p style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Materials & Papers</p>
                        <h2 style="font-size: 24px; font-weight: 800; color: var(--text-main);"><?= $materialCount + $paperCount ?></h2>
                    </div>
                </div>
            </div>

            <!-- Quick Overview Cards -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                <div class="card-box">
                    <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 15px;"><i class="fa-solid fa-users" style="color: var(--primary);"></i> User Roster Summary</h3>
                    <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.6; margin-bottom: 20px;">
                        Manage enrolled students, update faculty specializations, assign departments, and maintain authentication security credentials across both portals.
                    </p>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" onclick="switchMainTab(document.querySelectorAll('.tab-link')[1], 'tab-students')" class="btn-submit"><i class="fa-solid fa-user-plus"></i> Manage Students</button>
                        <button type="button" onclick="switchMainTab(document.querySelectorAll('.tab-link')[2], 'tab-staff')" class="btn-submit" style="background: var(--success);"><i class="fa-solid fa-user-tie"></i> Manage Staff</button>
                    </div>
                </div>

                <div class="card-box">
                    <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 15px;"><i class="fa-solid fa-eye" style="color: #f59e0b;"></i> Dashboard Activity Audit</h3>
                    <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.6; margin-bottom: 20px;">
                        Real-time audit log tracking study material uploads, question paper releases, query submissions, and faculty responses across Student and Staff dashboards.
                    </p>
                    <button type="button" onclick="switchMainTab(document.querySelectorAll('.tab-link')[3], 'tab-activity')" class="btn-submit" style="background: #f59e0b;"><i class="fa-solid fa-list-check"></i> View Full Activity Feed</button>
                </div>
            </div>
        </div>

        <!-- TAB 2: STUDENT MANAGEMENT -->
        <div id="tab-students" class="tab-panel">
            <div class="page-header-flex">
                <div>
                    <h1 style="font-size: 26px; font-weight: 800;">Student Data Management 👨‍🎓</h1>
                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Add new student records, update enrollment details, CGPA, and attendance metrics.</p>
                </div>
            </div>

            <!-- ADD STUDENT FORM -->
            <div class="card-box">
                <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 20px;"><i class="fa-solid fa-user-plus" style="color: var(--primary);"></i> Register New Student</h3>
                <form method="POST">
                    <input type="hidden" name="admin_action" value="add_student">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Rahul Sharma" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" class="form-control" placeholder="rahul@zealhub.edu" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="text" name="password" class="form-control" value="12345" required>
                        </div>
                        <div class="form-group">
                            <label>PRN Number</label>
                            <input type="text" name="prn" class="form-control" placeholder="PRN2026001">
                        </div>
                        <div class="form-group">
                            <label>Roll Number</label>
                            <input type="text" name="roll_no" class="form-control" placeholder="CS401">
                        </div>
                        <div class="form-group">
                            <label>Branch / Department</label>
                            <input type="text" name="branch" class="form-control" value="Computer Engineering">
                        </div>
                        <div class="form-group">
                            <label>Semester</label>
                            <input type="text" name="semester" class="form-control" value="Semester 4">
                        </div>
                        <div class="form-group">
                            <label>Mobile Number (10 Digits Only) *</label>
                            <input type="text" name="phone" class="form-control" placeholder="10-digit mobile (e.g. 9876543210)" pattern="[0-9]{10}" maxlength="10" minlength="10" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="form-group">
                            <label>CGPA</label>
                            <input type="number" step="0.1" name="cgpa" class="form-control" value="8.5">
                        </div>
                        <div class="form-group">
                            <label>Attendance %</label>
                            <input type="number" name="attendance" class="form-control" value="92">
                        </div>
                        <div class="form-group">
                            <label>Fees Status</label>
                            <select name="fee_status" class="form-control">
                                <option value="Paid">Paid</option>
                                <option value="Pending">Pending</option>
                                <option value="Partial">Partial</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-plus"></i> Save Student Record</button>
                </form>
            </div>

            <!-- BULK UPLOAD STUDENT DATA VIA EXCEL / CSV SPREADSHEET -->
            <div class="card-box">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="font-size: 18px; font-weight: 800;"><i class="fa-solid fa-file-excel" style="color: var(--primary);"></i> Bulk Import Students via Excel / CSV Upload</h3>
                    <a href="data:text/csv;charset=utf-8,Name,Email,Password,PRN,RollNo,Branch,Semester,Phone,CGPA,Attendance,FeeStatus%0AAmit%20Kumar,amit@zealhub.edu,12345,PRN2026099,CS409,Computer%20Engineering,Semester%204,9876543210,8.9,94,Paid" download="sample_students.csv" class="action-btn-sm btn-edit" style="padding: 8px 14px; text-decoration: none;">
                        <i class="fa-solid fa-download"></i> Download Sample Spreadsheet
                    </a>
                </div>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 18px;">Select an Excel (.xlsx, .xls) or CSV (.csv, .tsv) file containing multiple student records to import them directly into the database.</p>
                <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="admin_action" value="bulk_import_students">
                    <input type="file" name="student_csv" accept=".csv, .xlsx, .xls, .tsv, .txt" class="form-control" style="width: auto; flex: 1; min-width: 250px;" required>
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-cloud-arrow-up"></i> Upload & Import Student Excel / CSV</button>
                </form>
            </div>

            <!-- STUDENT LIST TABLE -->
            <div class="card-box" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 17px; font-weight: 800;">Enrolled Student Directory</h3>
                    <form method="GET" style="display: flex; gap: 10px;">
                        <input type="text" name="search_student" class="form-control" style="width: 260px;" placeholder="Search name, PRN, email..." value="<?= htmlspecialchars($studentSearch) ?>">
                        <button type="submit" class="btn-submit"><i class="fa-solid fa-search"></i></button>
                    </form>
                </div>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>PRN & Roll</th>
                            <th>Branch / Sem</th>
                            <th>CGPA & Attendance</th>
                            <th>Fees</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($studentsRes && $studentsRes->num_rows > 0): ?>
                            <?php while ($s = $studentsRes->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong style="display:block; color:var(--text-main); font-weight: 700;"><?= htmlspecialchars($s['name']) ?></strong>
                                        <small style="color:var(--text-muted); font-size:11px;"><?= htmlspecialchars($s['email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-pill badge-primary">PRN: <?= htmlspecialchars($s['prn'] ?: 'N/A') ?></span>
                                        <small style="display:block; color:var(--text-muted); font-size:11px; margin-top:2px;">Roll: <?= htmlspecialchars($s['roll_no'] ?: 'N/A') ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($s['branch'] ?: 'Computer Eng') ?></strong>
                                        <small style="display:block; color:var(--text-muted); font-size:11px;"><?= htmlspecialchars($s['semester'] ?: 'Sem 4') ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-pill badge-success">CGPA: <?= htmlspecialchars($s['cgpa'] ?: '8.5') ?></span>
                                        <span class="badge-pill badge-warning"><?= htmlspecialchars($s['attendance'] ? $s['attendance'].'%' : '90%') ?> Attd</span>
                                    </td>
                                    <td>
                                        <span class="badge-pill <?= ($s['fee_status'] === 'Paid') ? 'badge-success' : 'badge-danger' ?>"><?= htmlspecialchars($s['fee_status'] ?: 'Paid') ?></span>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button type="button" onclick='openEditStudentModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, "UTF-8") ?>)' class="action-btn-sm btn-edit"><i class="fa-solid fa-pen"></i> Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this student?');">
                                            <input type="hidden" name="admin_action" value="delete_student">
                                            <input type="hidden" name="student_id" value="<?= $s['id_val'] ?>">
                                            <button type="submit" class="action-btn-sm btn-delete"><i class="fa-solid fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">No student records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: STAFF MANAGEMENT -->
        <div id="tab-staff" class="tab-panel">
            <div class="page-header-flex">
                <div>
                    <h1 style="font-size: 26px; font-weight: 800;">Faculty & Staff Management 👨‍🏫</h1>
                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Add faculty members, assign designations, and update staff department profiles.</p>
                </div>
            </div>

            <!-- ADD STAFF FORM -->
            <div class="card-box">
                <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 20px;"><i class="fa-solid fa-user-tie" style="color: var(--success);"></i> Add New Staff Member</h3>
                <form method="POST">
                    <input type="hidden" name="admin_action" value="add_staff">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Staff ID *</label>
                            <input type="text" name="staff_id" class="form-control" placeholder="e.g. S005" required>
                        </div>
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Dr. Rajesh Kulkarni" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" class="form-control" placeholder="rajesh@zealhub.edu" required>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="text" name="password" class="form-control" value="123456" required>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" name="department" class="form-control" value="Computer Engineering">
                        </div>
                        <div class="form-group">
                            <label>Designation</label>
                            <input type="text" name="designation" class="form-control" value="Assistant Professor">
                        </div>
                        <div class="form-group">
                            <label>Mobile Number (10 Digits Only) *</label>
                            <input type="text" name="phone" class="form-control" placeholder="10-digit mobile (e.g. 9876543210)" pattern="[0-9]{10}" maxlength="10" minlength="10" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="form-group">
                            <label>Qualification</label>
                            <input type="text" name="qualification" class="form-control" value="Ph.D. / M.Tech">
                        </div>
                    </div>
                    <button type="submit" class="btn-submit" style="background: var(--success);"><i class="fa-solid fa-plus"></i> Save Faculty Record</button>
                </form>
            </div>

            <!-- BULK UPLOAD STAFF DATA VIA EXCEL / CSV SPREADSHEET -->
            <div class="card-box">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="font-size: 18px; font-weight: 800;"><i class="fa-solid fa-file-excel" style="color: var(--success);"></i> Bulk Import Faculty via Excel / CSV Upload</h3>
                    <a href="data:text/csv;charset=utf-8,StaffID,Name,Email,Password,Department,Designation,Phone,Qualification%0AS009,Dr.%20Vikram%20Patil,vikram@zealhub.edu,123456,Computer%20Engineering,Associate%20Professor,9876543211,Ph.D" download="sample_staff.csv" class="action-btn-sm btn-edit" style="padding: 8px 14px; text-decoration: none;">
                        <i class="fa-solid fa-download"></i> Download Sample Spreadsheet
                    </a>
                </div>
                <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 18px;">Select an Excel (.xlsx, .xls) or CSV (.csv, .tsv) file containing multiple faculty records to import them directly into the database.</p>
                <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="admin_action" value="bulk_import_staff">
                    <input type="file" name="staff_csv" accept=".csv, .xlsx, .xls, .tsv, .txt" class="form-control" style="width: auto; flex: 1; min-width: 250px;" required>
                    <button type="submit" class="btn-submit" style="background: var(--success);"><i class="fa-solid fa-cloud-arrow-up"></i> Upload & Import Faculty Excel / CSV</button>
                </form>
            </div>

            <!-- STAFF DIRECTORY TABLE -->
            <div class="card-box" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 17px; font-weight: 800;">Faculty Directory</h3>
                    <form method="GET" style="display: flex; gap: 10px;">
                        <input type="text" name="search_staff" class="form-control" style="width: 260px;" placeholder="Search name, Staff ID, dept..." value="<?= htmlspecialchars($staffSearch) ?>">
                        <button type="submit" class="btn-submit" style="background: var(--success);"><i class="fa-solid fa-search"></i></button>
                    </form>
                </div>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Faculty Member</th>
                            <th>Staff ID</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Phone</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($staffRes && $staffRes->num_rows > 0): ?>
                            <?php while ($st = $staffRes->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong style="display:block; color:var(--text-main); font-weight: 700;"><?= htmlspecialchars($st['name']) ?></strong>
                                        <small style="color:var(--text-muted); font-size:11px;"><?= htmlspecialchars($st['email']) ?></small>
                                    </td>
                                    <td><span class="badge-pill badge-primary"><?= htmlspecialchars($st['staff_id']) ?></span></td>
                                    <td><strong><?= htmlspecialchars($st['department'] ?: 'IT Department') ?></strong></td>
                                    <td><span class="badge-pill badge-warning"><?= htmlspecialchars($st['designation'] ?: 'Faculty') ?></span></td>
                                    <td><?= htmlspecialchars($st['phone'] ?: ($st['mobile_no'] ?: 'N/A')) ?></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button type="button" onclick='openEditStaffModal(<?= htmlspecialchars(json_encode($st), ENT_QUOTES, "UTF-8") ?>)' class="action-btn-sm btn-edit"><i class="fa-solid fa-pen"></i> Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
                                            <input type="hidden" name="admin_action" value="delete_staff">
                                            <input type="hidden" name="id" value="<?= $st['id'] ?>">
                                            <button type="submit" class="action-btn-sm btn-delete"><i class="fa-solid fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">No staff records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 4: DUAL ACTIVITY LOGS -->
        <div id="tab-activity" class="tab-panel">
            <div class="page-header-flex">
                <div>
                    <h1 style="font-size: 26px; font-weight: 800;">Dual Dashboard Activity Logs ⚡</h1>
                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Audit live events from both Student and Staff portal dashboards.</p>
                </div>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <a href="admin_dashboard.php?role=all" class="badge-pill <?= ($roleFilter==='all')?'badge-primary':'badge-warning' ?>" style="padding: 8px 14px; text-decoration:none;">All Activity</a>
                    <a href="admin_dashboard.php?role=staff" class="badge-pill <?= ($roleFilter==='staff')?'badge-primary':'badge-warning' ?>" style="padding: 8px 14px; text-decoration:none;">Staff Actions</a>
                    <a href="admin_dashboard.php?role=student" class="badge-pill <?= ($roleFilter==='student')?'badge-primary':'badge-warning' ?>" style="padding: 8px 14px; text-decoration:none;">Student Actions</a>
                    
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to clear ALL activity logs? This action cannot be undone.');">
                        <input type="hidden" name="admin_action" value="clear_all_activity">
                        <button type="submit" class="action-btn-sm btn-delete" style="padding: 8px 14px; background: var(--danger); color: white;"><i class="fa-solid fa-broom"></i> Clear All Logs</button>
                    </form>
                </div>
            </div>

            <div class="card-box" style="padding: 0;">
                <?php if ($activityRes && $activityRes->num_rows > 0): ?>
                    <?php while ($act = $activityRes->fetch_assoc()): ?>
                        <?php 
                        $isStaff = (strtolower($act['user_role']) === 'staff');
                        $isAdmin = (strtolower($act['user_role']) === 'admin');
                        $iconClass = 'fa-user-graduate';
                        $iconBg = 'rgba(67, 97, 238, 0.15)';
                        $iconColor = 'var(--primary)';
                        
                        if ($isStaff) {
                            $iconClass = 'fa-chalkboard-user';
                            $iconBg = 'rgba(16, 185, 129, 0.15)';
                            $iconColor = '#10b981';
                        } elseif ($isAdmin) {
                            $iconClass = 'fa-shield-halved';
                            $iconBg = 'rgba(239, 68, 68, 0.15)';
                            $iconColor = '#ef4444';
                        }
                        ?>
                        <div class="activity-item" style="display: flex; align-items: center; gap: 15px; padding: 18px 22px; border-bottom: 1px solid var(--border);">
                            <div class="activity-icon" style="background: <?= $iconBg ?>; color: <?= $iconColor ?>;">
                                <i class="fa-solid <?= $iconClass ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px; flex-wrap: wrap; gap: 6px;">
                                    <strong><?= htmlspecialchars($act['user_name']) ?> <span class="badge-pill <?= $isStaff ? 'badge-success' : ($isAdmin ? 'badge-danger' : 'badge-primary') ?>"><?= htmlspecialchars($act['user_role']) ?></span></strong>
                                    <small style="color: var(--text-muted); font-size: 11px;"><i class="fa-regular fa-clock"></i> <?= date('M d, Y h:i A', strtotime($act['created_at'])) ?></small>
                                </div>
                                <p style="font-size: 13.5px; color: var(--text-main); margin: 0;"><?= htmlspecialchars($act['message']) ?></p>
                            </div>
                            <div style="margin-left: 10px;">
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this activity log entry?');">
                                    <input type="hidden" name="admin_action" value="delete_activity">
                                    <input type="hidden" name="activity_id" value="<?= $act['id'] ?>">
                                    <button type="submit" class="action-btn-sm btn-delete" title="Delete Log Entry" style="padding: 6px 10px;"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px; color: var(--text-muted);">
                        <i class="fa-solid fa-list-check" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px; display: block;"></i>
                        No activity logs recorded yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 5: ANNOUNCEMENT MANAGEMENT -->
        <div id="tab-announcements" class="tab-panel">
            <div class="page-header-flex">
                <div>
                    <h1 style="font-size: 26px; font-weight: 800;">Portal Announcement Management 📢</h1>
                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Broadcast targeted announcements directly to Students, Faculty / Staff, or Both simultaneously.</p>
                </div>
            </div>

            <!-- CREATE ANNOUNCEMENT FORM -->
            <div class="card-box">
                <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 20px;"><i class="fa-solid fa-bullhorn" style="color: #ea580c;"></i> Broadcast New Announcement</h3>
                <form method="POST">
                    <input type="hidden" name="admin_action" value="create_announcement">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: 1 / -2;">
                            <label>Announcement Title *</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Mid-Term Examination Schedule Announcement" required>
                        </div>
                        <div class="form-group">
                            <label>Target Audience *</label>
                            <select name="target_audience" class="form-control" required style="font-weight: 700;">
                                <option value="both">📢 Both Students & Staff</option>
                                <option value="student">👨‍🎓 Students Only</option>
                                <option value="staff">👩‍🏫 Faculty / Staff Only</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Announcement Message Content *</label>
                            <textarea name="message" class="form-control" rows="4" placeholder="Enter complete announcement details, instructions, or notifications..." required></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit" style="background: #ea580c;"><i class="fa-solid fa-paper-plane"></i> Post & Broadcast Announcement</button>
                </form>
            </div>

            <!-- ANNOUNCEMENTS DIRECTORY TABLE -->
            <div class="card-box" style="padding: 0; overflow: hidden;">
                <div style="padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 17px; font-weight: 800;">Active Announcements Feed</h3>
                    <span class="badge-pill badge-primary">Total: <?= $announcementCount ?></span>
                </div>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Announcement Title & Content</th>
                            <th>Target Audience</th>
                            <th>Posted By</th>
                            <th>Date Broadcast</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($announcementsRes && $announcementsRes->num_rows > 0): ?>
                            <?php while ($anc = $announcementsRes->fetch_assoc()): ?>
                                <?php 
                                $aud = $anc['target_audience'] ?? 'both';
                                $audBadge = 'badge-warning';
                                $audText = '📢 Both Students & Staff';
                                if ($aud === 'student') {
                                    $audBadge = 'badge-primary';
                                    $audText = '👨‍🎓 Students Only';
                                } elseif ($aud === 'staff') {
                                    $audBadge = 'badge-success';
                                    $audText = '👩‍🏫 Faculty / Staff';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong style="display:block; color:var(--text-main); font-weight: 800; font-size: 14.5px;"><?= htmlspecialchars($anc['title']) ?></strong>
                                        <p style="color:var(--text-muted); font-size:12.5px; margin-top: 4px; line-height: 1.4;"><?= nl2br(htmlspecialchars($anc['message'])) ?></p>
                                    </td>
                                    <td>
                                        <span class="badge-pill <?= $audBadge ?>" style="font-weight: 700;"><?= $audText ?></span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($anc['posted_by'] ?? 'Admin') ?></strong></td>
                                    <td><small style="color:var(--text-muted); font-size: 11.5px;"><?= date('M d, Y h:i A', strtotime($anc['created_at'])) ?></small></td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                            <input type="hidden" name="admin_action" value="delete_announcement">
                                            <input type="hidden" name="announcement_id" value="<?= $anc['id'] ?>">
                                            <button type="submit" class="action-btn-sm btn-delete"><i class="fa-solid fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);">No announcements posted yet. Use the form above to broadcast one.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FOOTER -->
        <footer class="footer">
            <div>© <?= date('Y') ?> <strong>ZEALHUB Academic Portal Governance</strong></div>
            <div>Admin Control Panel</div>
        </footer>
    </main>

    <!-- EDIT STUDENT MODAL -->
    <div id="editStudentModal" class="modal">
        <div class="modal-card">
            <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 20px; color: var(--text-main);">Edit Student Account</h3>
            <form method="POST">
                <input type="hidden" name="admin_action" value="edit_student">
                <input type="hidden" name="student_id" id="edit_s_id">
                <div class="form-grid">
                    <div class="form-group"><label>Name</label><input type="text" name="name" id="edit_s_name" class="form-control" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_s_email" class="form-control" required></div>
                    <div class="form-group"><label>PRN</label><input type="text" name="prn" id="edit_s_prn" class="form-control"></div>
                    <div class="form-group"><label>Roll No</label><input type="text" name="roll_no" id="edit_s_roll" class="form-control"></div>
                    <div class="form-group"><label>Branch</label><input type="text" name="branch" id="edit_s_branch" class="form-control"></div>
                    <div class="form-group"><label>Semester</label><input type="text" name="semester" id="edit_s_sem" class="form-control"></div>
                    <div class="form-group"><label>Mobile Phone (10 Digits Only)</label><input type="text" name="phone" id="edit_s_phone" class="form-control" pattern="[0-9]{10}" maxlength="10" minlength="10" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"></div>
                    <div class="form-group"><label>CGPA</label><input type="number" step="0.1" name="cgpa" id="edit_s_cgpa" class="form-control"></div>
                    <div class="form-group"><label>Attendance %</label><input type="number" name="attendance" id="edit_s_attd" class="form-control"></div>
                    <div class="form-group">
                        <label>Fees Status</label>
                        <select name="fee_status" id="edit_s_fees" class="form-control">
                            <option value="Paid">Paid</option>
                            <option value="Pending">Pending</option>
                            <option value="Partial">Partial</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-save"></i> Save Changes</button>
                    <button type="button" onclick="closeModal('editStudentModal')" class="action-btn-sm btn-delete" style="padding: 10px 20px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT STAFF MODAL -->
    <div id="editStaffModal" class="modal">
        <div class="modal-card">
            <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 20px; color: var(--text-main);">Edit Faculty Account</h3>
            <form method="POST">
                <input type="hidden" name="admin_action" value="edit_staff">
                <input type="hidden" name="id" id="edit_st_pk">
                <div class="form-grid">
                    <div class="form-group"><label>Staff ID</label><input type="text" name="staff_id" id="edit_st_id" class="form-control" required></div>
                    <div class="form-group"><label>Name</label><input type="text" name="name" id="edit_st_name" class="form-control" required></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_st_email" class="form-control" required></div>
                    <div class="form-group"><label>Department</label><input type="text" name="department" id="edit_st_dept" class="form-control"></div>
                    <div class="form-group"><label>Designation</label><input type="text" name="designation" id="edit_st_desig" class="form-control"></div>
                    <div class="form-group"><label>Mobile Phone (10 Digits Only)</label><input type="text" name="phone" id="edit_st_phone" class="form-control" pattern="[0-9]{10}" maxlength="10" minlength="10" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')"></div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="btn-submit" style="background: var(--success);"><i class="fa-solid fa-save"></i> Save Changes</button>
                    <button type="button" onclick="closeModal('editStaffModal')" class="action-btn-sm btn-delete" style="padding: 10px 20px;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- THEME MODAL -->
    <div id="themeModal" class="theme-modal">
        <div class="theme-card" style="width: min(92%, 520px);">
            <h3 style="font-size: 20px; font-weight: 800; color: var(--text-main);"><i class="fa-solid fa-palette" style="color: var(--primary);"></i> Choose Portal Theme</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Select your preferred color scheme for Admin Governance Control Center</p>
            
            <div class="theme-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-top: 20px;">
                <div class="theme-opt" data-theme="light"><span style="width:14px;height:14px;background:#4361ee;border-radius:50%;display:inline-block;"></span> ☀️ Light Blue</div>
                <div class="theme-opt" data-theme="dark"><span style="width:14px;height:14px;background:#6366f1;border-radius:50%;display:inline-block;"></span> 🌙 Dark Mode</div>
                <div class="theme-opt" data-theme="yellow"><span style="width:14px;height:14px;background:#d97706;border-radius:50%;display:inline-block;"></span> 💛 Golden Yellow</div>
                <div class="theme-opt" data-theme="sunset"><span style="width:14px;height:14px;background:#ea580c;border-radius:50%;display:inline-block;"></span> 🌅 Sunset Orange</div>
                <div class="theme-opt" data-theme="ocean"><span style="width:14px;height:14px;background:#0891b2;border-radius:50%;display:inline-block;"></span> 🌊 Ocean Cyan</div>
                <div class="theme-opt" data-theme="midnight"><span style="width:14px;height:14px;background:#38bdf8;border-radius:50%;display:inline-block;"></span> 🌌 Midnight Navy</div>
                <div class="theme-opt" data-theme="forest"><span style="width:14px;height:14px;background:#15803d;border-radius:50%;display:inline-block;"></span> 🌲 Forest Emerald</div>
                <div class="theme-opt" data-theme="pink"><span style="width:14px;height:14px;background:#db2777;border-radius:50%;display:inline-block;"></span> 🌸 Light Pink</div>
                <div class="theme-opt" data-theme="purple"><span style="width:14px;height:14px;background:#9333ea;border-radius:50%;display:inline-block;"></span> 🔮 Royal Purple</div>
            </div>
            
            <button type="button" onclick="toggleThemeModal(false)" style="margin-top:22px; width:100%; padding:12px; border:none; background:var(--primary); color:white; border-radius:14px; cursor:pointer; font-weight:800; font-size:14px;">Apply & Close</button>
        </div>
    </div>

    <script>
        function switchMainTab(btn, tabId) {
            document.querySelectorAll('.tab-link').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        function openEditStudentModal(data) {
            document.getElementById('edit_s_id').value = data.id_val || data.student_id || data.id;
            document.getElementById('edit_s_name').value = data.name || '';
            document.getElementById('edit_s_email').value = data.email || '';
            document.getElementById('edit_s_prn').value = data.prn || '';
            document.getElementById('edit_s_roll').value = data.roll_no || '';
            document.getElementById('edit_s_branch').value = data.branch || '';
            document.getElementById('edit_s_sem').value = data.semester || '';
            document.getElementById('edit_s_phone').value = data.phone || '';
            document.getElementById('edit_s_cgpa').value = data.cgpa || 8.5;
            document.getElementById('edit_s_attd').value = data.attendance || 90;
            document.getElementById('edit_s_fees').value = data.fee_status || 'Paid';
            document.getElementById('editStudentModal').classList.add('active');
        }

        function openEditStaffModal(data) {
            document.getElementById('edit_st_pk').value = data.id;
            document.getElementById('edit_st_id').value = data.staff_id || '';
            document.getElementById('edit_st_name').value = data.name || '';
            document.getElementById('edit_st_email').value = data.email || '';
            document.getElementById('edit_st_dept').value = data.department || '';
            document.getElementById('edit_st_desig').value = data.designation || '';
            document.getElementById('edit_st_phone').value = data.phone || data.mobile_no || '';
            document.getElementById('editStaffModal').classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function toggleThemeModal(show) {
            const modal = document.getElementById('themeModal');
            if (modal) {
                modal.classList.toggle('active', show ?? !modal.classList.contains('active'));
            }
        }

        const themeHeaderBtn = document.getElementById('themeBtn');
        if (themeHeaderBtn) {
            themeHeaderBtn.addEventListener('click', () => toggleThemeModal());
        }

        document.querySelectorAll('.theme-opt').forEach(opt => {
            opt.addEventListener('click', () => {
                const key = opt.dataset.theme;
                document.body.setAttribute('data-theme', key);
                localStorage.setItem('admin-theme', key);
                localStorage.setItem('user-theme', key);
                toggleThemeModal(false);
            });
        });

        function toggleSidebar() {
            if (window.innerWidth <= 900) {
                const sb = document.querySelector('.sidebar');
                if (sb) {
                    sb.classList.toggle('mobile-open');
                    if (sb.classList.contains('mobile-open')) {
                        sb.style.transform = 'translateX(0px)';
                    } else {
                        sb.style.transform = 'translateX(-100%)';
                    }
                }
            } else {
                document.body.classList.toggle('sidebar-collapsed');
            }
        }

        const savedTheme = localStorage.getItem('admin-theme') || localStorage.getItem('user-theme') || 'light';
        document.body.setAttribute('data-theme', savedTheme);
    </script>
</body>
</html>
