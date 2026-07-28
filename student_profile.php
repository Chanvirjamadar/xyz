<?php
session_start();

if (!isset($_SESSION['student'])) {
    header("Location: student_login.php");
    exit();
}

require_once __DIR__ . "/includes/database.php";
require_once __DIR__ . "/includes/security.php";
require_once __DIR__ . "/includes/qr_generator.php";

$studentId = $_SESSION['student'];
$studentID = $studentId;
$currentPage = basename($_SERVER['PHP_SELF']);

function isActive($pageName, $currentPage) {
    return ($pageName === $currentPage) ? 'active' : '';
}

$studentResult = getStudentById($studentId);
$student = ($studentResult['success'] && $studentResult['data']) ? $studentResult['data'] : [
    'student_id' => $studentId,
    'name' => $_SESSION['student_name'] ?? 'Student',
    'email' => 'student@zealhub.edu',
    'roll_no' => '123',
    'prn' => 'PRN123',
    'department' => 'Computer Science & Engineering',
    'semester' => 'Semester 4',
    'cgpa' => '8.8',
    'attendance' => '94',
    'fees_status' => 'Paid'
];
$idMessage = '';
$idMessageType = '';

// CSRF protection for ID actions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $idMessage = 'Invalid request (CSRF token mismatch).';
        $idMessageType = 'danger';
    } else {
        if ($_POST['action'] === 'generate_id') {
            $generatedId = saveStudentGeneratedId($conn ?? null, $studentId);
            if ($generatedId) {
                $idMessage = 'Student ID generated successfully.';
                $idMessageType = 'success';
            } else {
                $idMessage = 'Unable to generate the student ID right now.';
                $idMessageType = 'danger';
            }
        } elseif ($_POST['action'] === 'toggle_active_id') {
            $activeValue = !empty($_POST['activate_id']) ? 1 : 0;
            if (setStudentActiveId($conn ?? null, $studentId, $activeValue)) {
                $idMessage = $activeValue ? 'Student ID has been activated.' : 'Student ID has been deactivated.';
                $idMessageType = 'success';
            } else {
                $idMessage = 'Unable to update the ID activation status.';
                $idMessageType = 'danger';
            }
        }
        $studentResult = getStudentById($studentId);
        if ($studentResult['success'] && $studentResult['data']) {
            $student = $studentResult['data'];
        }
    }
}

// Fetch Notifications for Header
$notifCountQuery = "SELECT COUNT(*) as total FROM announcements a WHERE a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE student_id = '$studentId')";
$notifCount = ($res = $conn->query($notifCountQuery)) ? $res->fetch_assoc()['total'] : 0;
$notifications = $conn->query("SELECT a.* FROM announcements a ORDER BY a.created_at DESC LIMIT 5");
$notifArray = [];
if ($notifications) {
    while ($nRow = $notifications->fetch_assoc()) {
        $notifArray[] = $nRow;
    }
}

$studentName = $student['name'] ?? 'Student';
$initials = strtoupper(substr(preg_replace('/\s+/', ' ', trim($studentName)), 0, 2)) ?: 'ST';
$email = $student['email'] ?? 'N/A';
$mobile = $student['student_mobile'] ?? ($student['mobile'] ?? 'N/A');
$roll = $student['roll'] ?? ($student['roll_no'] ?? 'N/A');
$prn = $student['prn'] ?? 'N/A';
$department = $student['department'] ?? 'Science & Engineering';
$semester = $student['semester'] ?? 'Semester 4';
$dob = $student['dob'] ?? 'N/A';
$gender = $student['gender'] ?? 'N/A';
$bloodGroup = $student['blood_group'] ?? 'N/A';
$aadhaar = $student['aadhaar_no'] ?? 'N/A';
$abcId = $student['abc_id'] ?? 'N/A';
$generatedId = $student['generated_id'] ?? '';
$activeId = !empty($student['active_id']);
$address = $student['address'] ?? '';
$city = $student['city'] ?? '';
$state = $student['state'] ?? '';
$pincode = $student['pincode'] ?? '';
$fullAddress = trim("$address, $city, $state $pincode", " ,");
if (empty($fullAddress)) $fullAddress = 'Not Provided';

$cgpa = $student['cgpa'] ?? '8.8';
$attendance = isset($student['attendance']) ? $student['attendance'] . '%' : '94%';
$feeStatus = $student['fees_status'] ?? ($student['fee_status'] ?? 'Paid');

$fatherName = $student['father_name'] ?? 'N/A';
$fatherMobile = $student['father_mobile'] ?? 'N/A';
$motherName = $student['mother_name'] ?? 'N/A';
$motherMobile = $student['mother_mobile'] ?? 'N/A';
$guardianName = $student['guardian_name'] ?? 'N/A';
$guardianMobile = $student['guardian_mobile'] ?? 'N/A';
$emergencyContact = !empty($student['emergency_contact']) ? $student['emergency_contact'] : ($student['mobile'] ?? 'N/A');

// Calculate Profile Completion %
$profileFields = ['name', 'email', 'mobile', 'prn', 'roll_no', 'department', 'semester', 'dob', 'gender', 'blood_group', 'address', 'father_name', 'mother_name', 'emergency_contact'];
$filledCount = 0;
foreach ($profileFields as $field) {
    if (!empty($student[$field])) $filledCount++;
}
$completionPercent = round(($filledCount / count($profileFields)) * 100);

$qrUrl = getStudentQRUrl($studentId);
$photo = '';
if (!empty($student['photo'])) {
    $photoName = basename($student['photo']);
    $uploadsDir = realpath(__DIR__ . '/assets/uploads/photos');
    $candidate = $uploadsDir ? realpath($uploadsDir . DIRECTORY_SEPARATOR . $photoName) : false;
    if ($candidate && strpos($candidate, $uploadsDir) === 0 && file_exists($candidate)) {
        $photo = 'assets/uploads/photos/' . $photoName;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | ZEALHUB</title>
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
            height: 70px;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 25px;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }

        .header-left { display: flex; align-items: center; gap: 18px; }

        .menu-btn {
            background: var(--primary); color: white; border: none; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px;
        }

        .logo {
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: transform 0.2s ease;
        }

        .logo:hover { transform: translateY(-1px); }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--primary) 0%, #3a0ca3 100%);
            color: #ffffff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 14px var(--glow);
        }

        .logo-text { display: flex; flex-direction: column; }

        .brand-name {
            font-size: 20px;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.5px;
            line-height: 1.1;
        }

        .brand-tag {
            font-size: 9px;
            font-weight: 800;
            color: var(--text-muted);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .header-center {
            flex: 1;
            max-width: 440px;
            margin: 0 25px;
        }

        .header-search {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 8px 16px;
            transition: all 0.25s ease;
        }

        .header-search:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--glow);
            background: var(--card-bg);
        }

        .header-search i { color: var(--text-muted); font-size: 14px; }

        .header-search input {
            border: none;
            background: transparent;
            outline: none;
            color: var(--text-main);
            font-size: 13.5px;
            font-weight: 500;
            width: 100%;
        }

        .header-nav a:hover, .header-nav a.active { color: var(--primary); background: rgba(67, 97, 238, 0.08); }
        .header-right { display: flex; align-items: center; gap: 14px; position: relative; }

        .icon-btn {
            background: var(--card-bg); color: var(--text-main); border: 1px solid var(--border); width: 42px; height: 42px; border-radius: 12px; cursor: pointer; position: relative; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 16px;
        }

        .icon-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

        .badge {
            position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; font-size: 10px; padding: 2px 6px; border-radius: 50%; border: 2px solid var(--header-bg); font-weight: 800;
        }

        .notif-dropdown {
            position: absolute; top: 55px; right: 140px; width: 320px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.12); display: none; flex-direction: column; z-index: 2000; overflow: hidden;
        }

        .notif-dropdown.active { display: flex; }
        .notif-header { padding: 14px 16px; background: var(--bg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .notif-item { padding: 12px 16px; border-bottom: 1px solid var(--border); text-decoration: none; color: inherit; display: block; }

        .profile-pill {
            display: flex; align-items: center; gap: 10px; padding: 6px 14px; border-radius: 14px; border: 1px solid var(--border); background: var(--card-bg); text-decoration: none; color: inherit;
        }

        /* SIDEBAR - OPEN BY DEFAULT */
        .sidebar {
            width: 260px; height: calc(100vh - 70px); background: var(--sidebar-bg); border-right: 1px solid var(--border); position: fixed; top: 70px; left: 0; padding: 20px 15px; display: flex; flex-direction: column; z-index: 999; overflow-y: auto; transition: width 0.3s ease;
        }

        .sidebar.collapsed { width: 80px; padding: 20px 10px; }

        .sidebar a {
            color: var(--text-muted); text-decoration: none; display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; margin-bottom: 8px; font-size: 14px; font-weight: 600; transition: all 0.2s ease; white-space: nowrap;
        }

        .sidebar.collapsed a { justify-content: center; padding: 12px; }
        .sidebar.collapsed a span { display: none; }

        .sidebar a:hover, .sidebar a.active {
            background: var(--primary); color: #ffffff !important; box-shadow: 0 4px 14px var(--glow);
        }

        .sidebar a.btn-logout { margin-top: auto; background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .sidebar a.btn-logout:hover { background: var(--danger); color: white !important; }

        /* MAIN CONTENT - MARGIN LEFT MATCHES OPEN SIDEBAR */
        .main-content {
            margin-left: 260px; margin-top: 70px; padding: 30px; min-height: calc(100vh - 70px); transition: margin-left 0.3s ease;
        }

        .main-content.collapsed { margin-left: 80px; }

        .page-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }

        .btn-edit { background: var(--primary); color: white; text-decoration: none; padding: 10px 20px; border-radius: 12px; font-size: 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; }

        .profile-card { background: var(--card-bg); border-radius: 24px; border: 1px solid var(--border); margin-bottom: 30px; overflow: hidden; }
        .profile-banner { height: 160px; background: linear-gradient(135deg, var(--primary) 0%, #3a0ca3 100%); position: relative; }
        .banner-tag { position: absolute; top: 18px; right: 20px; background: rgba(255,255,255,0.2); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3); border-radius: 20px; padding: 6px 16px; color: #fff; font-size: 12px; font-weight: 700; }
        .banner-info { position: absolute; bottom: 20px; left: 2rem; color: rgba(255,255,255,0.9); font-size: 14px; font-weight: 600; }

        .profile-avatar-wrapper { margin-top: -50px; padding: 0 2rem; display: flex; justify-content: space-between; align-items: flex-end; position: relative; z-index: 2; margin-bottom: 20px; }
        .profile-img { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid var(--card-bg); box-shadow: 0 8px 24px var(--glow); background: var(--bg); }

        .profile-actions { display: flex; gap: 10px; padding-bottom: 10px; }
        .btn-outline { border: 1px solid var(--primary); color: var(--primary); background: transparent; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; display: flex; align-items: center; gap: 6px; }

        .progress-box { padding: 20px 30px; border-bottom: 1px solid var(--border); }
        .progress-header { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; font-weight: 700; color: var(--text-main); }
        .progress-bar-bg { background: var(--bg); height: 8px; border-radius: 4px; overflow: hidden; }
        .progress-bar-fill { background: var(--primary); height: 100%; border-radius: 4px; }

        .id-manage-box { padding: 25px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .id-badges { display: flex; gap: 10px; margin-top: 8px; }
        .badge-pill { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }

        .id-form { display: flex; gap: 15px; align-items: center; }
        .switch-wrap { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-solid { background: var(--primary); color: white; border: none; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; }

        .alert { padding: 12px 20px; border-radius: 12px; font-size: 14px; font-weight: 600; margin: 0 30px 20px; }
        .alert-success { background: rgba(16, 185, 129, 0.15); color: #047857; }
        .alert-danger { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }

        .tabs-nav { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; }
        .tab-btn { background: var(--card-bg); border: 1px solid var(--border); color: var(--text-main); padding: 12px 24px; border-radius: 24px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap; }
        .tab-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 8px 16px var(--glow); }

        .tab-content { display: none; background: var(--card-bg); border: 1px solid var(--border); border-radius: 24px; padding: 30px; }
        .tab-content.active { display: block; }

        .section-title { font-size: 18px; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; color: var(--text-main); }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; }
        .info-group { display: flex; flex-direction: column; gap: 6px; }
        .info-label { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
        .info-value { font-size: 15px; font-weight: 600; color: var(--text-main); }
        .info-card { background: var(--bg); border: 1px solid var(--border); border-radius: 16px; padding: 20px; }

        .theme-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 3000; padding: 20px; }
        .theme-modal.active { display: flex; }
        .theme-card { background: var(--card-bg); padding: 30px; border-radius: 24px; width: min(90%, 440px); border: 1px solid var(--border); text-align: center; }
        .theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; }
        .theme-opt { padding: 14px; border-radius: 14px; border: 2px solid var(--border); cursor: pointer; font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 10px; color: var(--text-main); }
        .theme-opt:hover { border-color: var(--primary); background: var(--bg); }

        .footer { margin-top: 40px; padding: 20px 0; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; color: var(--text-muted); font-size: 13px; }
        .footer a { color: var(--primary); text-decoration: none; font-weight: 600; }

        @media (max-width: 1100px) { .header-nav { display: none; } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 260px !important; }
            .sidebar.collapsed { transform: translateX(0); }
            .main-content, .main-content.collapsed { margin-left: 0 !important; padding: 20px 15px; }
        }
    
        /* RESPONSIVE & COLLAPSIBLE SIDEBAR */
        .sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .main-content {
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar.collapsed {
            transform: translateX(-100%);
        }
        .main-content.collapsed {
            margin-left: 0 !important;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                top: 72px;
                left: 0;
                height: calc(100vh - 72px);
                z-index: 1000;
                width: 260px !important;
            }
            .sidebar.collapsed, .sidebar.mobile-open {
                transform: translateX(0) !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 20px 15px;
            }
        }
</style>
</head>

<body data-theme="light">

    <!-- ATTRACTIVE HEADER -->
    <header class="header">
        <div class="header-left">
            <button type="button" class="menu-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Toggle Sidebar Navigation" style="background: var(--primary); color: #ffffff; border: none; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-right: 12px;"><i class="fa-solid fa-bars"></i></button>
            <a href="student_dashboard.php" class="logo">
                <div class="logo-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                <div class="logo-text">
                    <span class="brand-name">ZEALHUB</span>
                    <span class="brand-tag">ACADEMIC PORTAL</span>
                </div>
            </a>
        </div>

        <div class="header-center">
            <div class="header-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" placeholder="Search portal resources, materials, subjects..." id="globalHeaderSearch">
            </div>
        </div>

        <div class="header-right">
            <button class="icon-btn" id="notifBtn" type="button" title="Announcements">
                <i class="fa-solid fa-bell"></i>
                <?php if ($notifCount > 0): ?>
                    <span class="badge" id="notifBadge"><?= $notifCount ?></span>
                <?php endif; ?>
            </button>

            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <h4 style="font-size: 14px; font-weight: 700;"><i class="fa-solid fa-bell"></i> Announcements</h4>
                    <a href="student_alert.php" style="font-size: 11px; color: var(--primary); font-weight: 700; text-decoration: none;">View All</a>
                </div>
                <?php if (!empty($notifArray)): ?>
                    <?php foreach ($notifArray as $nItem): ?>
                        <a href="student_alert.php" class="notif-item">
                            <strong><?= htmlspecialchars($nItem['title']) ?></strong>
                            <small><?= date('M d, H:i', strtotime($nItem['created_at'])) ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 16px; text-align: center; font-size: 12px; color: var(--text-muted);">
                        No announcements available.
                    </div>
                <?php endif; ?>
            </div>

            <button class="icon-btn" id="themeBtn" type="button" title="Choose Theme" aria-label="Choose Theme">
                <i class="fa-solid fa-palette" id="themeIcon"></i>
            </button>

            <a href="student_profile.php" class="profile-pill" title="My Profile">
                <div style="text-align: right;">
                    <p style="font-size: 11px; font-weight: 800; line-height: 1.2;"><?= htmlspecialchars($studentName) ?></p>
                    <p style="font-size: 9px; color: var(--text-muted); font-weight: 700;">STUDENT</p>
                </div>
                <div style="width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px;">
                    <?= $initials ?>
                </div>
            </a>

            <a href="student_logout.php" class="icon-btn" title="Logout" style="color: var(--danger);">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <!-- SIDEBAR - OPEN BY DEFAULT -->
    <aside class="sidebar" id="sidebar">
        <a href="student_dashboard.php" class="<?= isActive('student_dashboard.php', $currentPage) ?>">
            <i class="fa-solid fa-house"></i> <span>Dashboard</span>
        </a>
        <a href="student_study_material.php" class="<?= isActive('student_study_material.php', $currentPage) ?>">
            <i class="fa-solid fa-cloud-arrow-down"></i> <span>Study Materials</span>
        </a>
        <a href="student_questionbank.php" class="<?= isActive('student_questionbank.php', $currentPage) ?>">
            <i class="fa-solid fa-file-circle-check"></i> <span>Question Bank</span>
        </a>
        <a href="student_syllabus.php" class="<?= isActive('student_syllabus.php', $currentPage) ?>">
            <i class="fa-solid fa-scroll"></i> <span>Syllabus</span>
        </a>
        <a href="student_raise_queries.php" class="<?= isActive('student_raise_queries.php', $currentPage) ?>">
            <i class="fa-solid fa-clipboard-question"></i> <span>Ask Queries</span>
        </a>
        <a href="student_alert.php" class="<?= isActive('student_alert.php', $currentPage) ?>">
            <i class="fa-solid fa-bell"></i> <span>Announcements</span>
        </a>
        <a href="student_lab.php" class="<?= isActive('student_lab.php', $currentPage) ?>">
            <i class="fa-solid fa-flask"></i> <span>Coding Lab</span>
        </a>
        <a href="student_library.php" class="<?= isActive('student_library.php', $currentPage) ?>">
            <i class="fa-solid fa-book-open-reader"></i> <span>Library</span>
        </a>
        <a href="student_profile.php" class="<?= isActive('student_profile.php', $currentPage) ?>">
            <i class="fa-solid fa-user"></i> <span>My Profile</span>
        </a>
        <a href="student_logout.php" class="btn-logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span>
        </a>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content" id="mainContent">
        <div class="page-header-flex">
            <div>
                <h1 style="font-size: 26px; font-weight: 800;">My Student Profile 👤</h1>
                <p style="color: var(--text-muted); font-size: 14px;">Manage your personal details, academic metrics, and digital ID card.</p>
            </div>
            <a href="edit_profile.php" class="btn-edit"><i class="fa-solid fa-pen"></i> Edit Profile</a>
        </div>

        <div class="profile-card">
            <div class="profile-banner">
                <div class="banner-tag"><i class="fa-solid fa-building-columns"></i> ZEALHUB ERP</div>
                <div class="banner-info"><?= htmlspecialchars($department) ?> &bull; <?= htmlspecialchars($semester) ?></div>
            </div>

            <div class="profile-avatar-wrapper">
                <div>
                    <?php if ($photo): ?>
                        <img src="<?= htmlspecialchars($photo) ?>" alt="Student Photo" class="profile-img" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($studentName) ?>&background=random&color=fff'">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($studentName) ?>&background=random&color=fff" alt="Student Photo" class="profile-img">
                    <?php endif; ?>
                </div>
                <div class="profile-actions">
                    <a href="download_id.php?id=<?= urlencode($studentId) ?>" class="btn-outline"><i class="fa-solid fa-id-card"></i> Download ID Card</a>
                </div>
            </div>

            <div style="padding: 0 30px 20px;">
                <h2 style="color: var(--text-main); font-weight: 800;"><?= htmlspecialchars($studentName) ?></h2>
                <p style="color: var(--text-muted); font-size: 14px; font-weight: 600;"><?= htmlspecialchars($email) ?> &bull; Roll: <?= htmlspecialchars($roll) ?></p>
            </div>

            <div class="progress-box">
                <div class="progress-header">
                    <span><i class="fa-solid fa-shield-halved"></i> Profile Completeness</span>
                    <span style="color: var(--primary);"><?= $completionPercent ?>%</span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?= $completionPercent ?>%;"></div>
                </div>
            </div>

            <div class="id-manage-box">
                <div>
                    <h4 style="color: var(--text-main); font-weight: 700;"><i class="fa-solid fa-id-card"></i> Student ID Management</h4>
                    <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">Generate or update your digital active student credential.</p>
                    <div class="id-badges">
                        <span class="badge-pill" style="background: var(--glow); color: var(--primary);"><i class="fa-solid fa-fingerprint"></i> <?= htmlspecialchars($generatedId ?: 'Not generated') ?></span>
                        <span class="badge-pill" style="background: <?= $activeId ? 'rgba(16,185,129,0.15)' : 'rgba(100,116,139,0.15)' ?>; color: <?= $activeId ? '#10b981' : 'var(--text-muted)' ?>;">
                            <i class="fa-solid <?= $activeId ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i> <?= $activeId ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>
                <form method="post" class="id-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <button type="submit" name="action" value="generate_id" class="btn-outline"><i class="fa-solid fa-rotate"></i> Generate ID</button>
                    <label class="switch-wrap">
                        <input type="checkbox" name="activate_id" value="1" <?= $activeId ? 'checked' : '' ?>> Activate ID
                    </label>
                    <button type="submit" name="action" value="toggle_active_id" class="btn-solid"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                </form>
            </div>

            <?php if ($idMessage): ?>
                <div class="alert alert-<?= $idMessageType ?>"><?= htmlspecialchars($idMessage) ?></div>
            <?php endif; ?>
        </div>

        <div class="tabs-nav">
            <button class="tab-btn active" onclick="switchTab(this, 'personal')"><i class="fa-solid fa-user"></i> Personal Info</button>
            <button class="tab-btn" onclick="switchTab(this, 'academic')"><i class="fa-solid fa-graduation-cap"></i> Academic Record</button>
            <button class="tab-btn" onclick="switchTab(this, 'parent')"><i class="fa-solid fa-users"></i> Parent & Emergency</button>
            <button class="tab-btn" onclick="switchTab(this, 'qr')"><i class="fa-solid fa-qrcode"></i> Digital QR Code</button>
        </div>

        <div id="personal" class="tab-content active">
            <div class="section-title"><i class="fa-solid fa-address-card"></i> Personal Identification & Contact</div>
            <div class="info-grid">
                <div class="info-group"><div class="info-label">Full Name</div><div class="info-value"><?= htmlspecialchars($studentName) ?></div></div>
                <div class="info-group"><div class="info-label">Email Address</div><div class="info-value"><?= htmlspecialchars($email) ?></div></div>
                <div class="info-group"><div class="info-label">Mobile Number</div><div class="info-value"><?= htmlspecialchars($mobile) ?></div></div>
                <div class="info-group"><div class="info-label">Emergency Phone</div><div class="info-value" style="color:#ef4444;"><i class="fa-solid fa-phone"></i> <?= htmlspecialchars($emergencyContact) ?></div></div>
                <div class="info-group"><div class="info-label">Date of Birth</div><div class="info-value"><?= htmlspecialchars($dob) ?></div></div>
                <div class="info-group"><div class="info-label">Gender</div><div class="info-value"><?= htmlspecialchars($gender) ?></div></div>
                <div class="info-group"><div class="info-label">Blood Group</div><div class="info-value"><?= htmlspecialchars($bloodGroup) ?></div></div>
                <div class="info-group"><div class="info-label">Aadhaar Card No</div><div class="info-value"><?= htmlspecialchars($aadhaar) ?></div></div>
                <div class="info-group" style="grid-column: 1 / -1;"><div class="info-label">Residential Address</div><div class="info-value"><?= htmlspecialchars($fullAddress) ?></div></div>
            </div>
        </div>

        <div id="academic" class="tab-content">
            <div class="section-title"><i class="fa-solid fa-award"></i> Academic Performance & Record</div>
            <div class="info-grid">
                <div class="info-group"><div class="info-label">PRN Number</div><div class="info-value"><?= htmlspecialchars($prn) ?></div></div>
                <div class="info-group"><div class="info-label">Roll Number</div><div class="info-value"><?= htmlspecialchars($roll) ?></div></div>
                <div class="info-group"><div class="info-label">Department</div><div class="info-value"><?= htmlspecialchars($department) ?></div></div>
                <div class="info-group"><div class="info-label">Current Semester</div><div class="info-value"><?= htmlspecialchars($semester) ?></div></div>
                <div class="info-group"><div class="info-label">Cumulative GPA</div><div class="info-value" style="color:var(--primary); font-size:22px; font-weight:800;"><?= htmlspecialchars($cgpa) ?> / 10</div></div>
                <div class="info-group"><div class="info-label">Total Attendance</div><div class="info-value" style="color:#10b981; font-size:22px; font-weight:800;"><?= htmlspecialchars($attendance) ?></div></div>
                <div class="info-group"><div class="info-label">Fees Status</div><div class="info-value"><span style="background:rgba(16,185,129,0.15); color:#10b981; padding:4px 10px; border-radius:8px; font-weight:700; font-size:12px;"><?= htmlspecialchars($feeStatus) ?></span></div></div>
            </div>
        </div>

        <div id="parent" class="tab-content">
            <div class="section-title"><i class="fa-solid fa-people-roof"></i> Parent & Emergency Contacts</div>
            <div class="info-grid">
                <div class="info-card">
                    <h4 style="margin-bottom:15px; color:var(--primary);"><i class="fa-solid fa-user-tie"></i> Father's Details</h4>
                    <div class="info-group" style="margin-bottom:10px;"><div class="info-label">Name</div><div class="info-value"><?= htmlspecialchars($fatherName) ?></div></div>
                    <div class="info-group"><div class="info-label">Mobile</div><div class="info-value"><?= htmlspecialchars($fatherMobile) ?></div></div>
                </div>
                <div class="info-card">
                    <h4 style="margin-bottom:15px; color:var(--primary);"><i class="fa-solid fa-person-breastfeeding"></i> Mother's Details</h4>
                    <div class="info-group" style="margin-bottom:10px;"><div class="info-label">Name</div><div class="info-value"><?= htmlspecialchars($motherName) ?></div></div>
                    <div class="info-group"><div class="info-label">Mobile</div><div class="info-value"><?= htmlspecialchars($motherMobile) ?></div></div>
                </div>
                <div class="info-card">
                    <h4 style="margin-bottom:15px; color:var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Guardian Info</h4>
                    <div class="info-group" style="margin-bottom:10px;"><div class="info-label">Name</div><div class="info-value"><?= htmlspecialchars($guardianName) ?></div></div>
                    <div class="info-group"><div class="info-label">Mobile</div><div class="info-value"><?= htmlspecialchars($guardianMobile) ?></div></div>
                </div>
            </div>
        </div>

        <div id="qr" class="tab-content" style="text-align: center;">
            <div class="section-title" style="justify-content: center;"><i class="fa-solid fa-qrcode"></i> Official Digital Identity QR</div>
            <p style="color: var(--text-muted); margin-bottom: 25px;">Scan this QR code to verify official student enrollment status.</p>
            <div style="background: white; padding: 20px; border-radius: 20px; display: inline-block; box-shadow: 0 10px 20px rgba(0,0,0,0.05); border: 1px solid var(--border); margin-bottom: 30px;">
                <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR" width="200" height="200">
            </div>
            <div>
                <a href="view_qr.php?id=<?= $studentId ?>" target="_blank" class="btn-outline" style="display:inline-flex;"><i class="fa-solid fa-up-right-from-square"></i> Open Portal</a>
                <a href="download_id.php?id=<?= urlencode($studentId) ?>" class="btn-solid" style="display:inline-flex; margin-left:10px;"><i class="fa-solid fa-download"></i> Download Card</a>
            </div>
        </div>

        <!-- FOOTER -->
        <footer class="footer">
            <div>
                © <?= date('Y') ?> <strong>ZEALHUB Academic Portal</strong>. All rights reserved.
            </div>
            <div style="display: flex; gap: 15px;">
                <a href="student_dashboard.php">Dashboard</a>
                <a href="student_profile.php">Profile</a>
                <a href="student_raise_queries.php">Support</a>
            </div>
        </footer>
    </main>

    <!-- THEME MODAL -->
    <div id="themeModal" class="theme-modal">
        <div class="theme-card">
            <h3 style="font-size: 18px; font-weight: 800; color: var(--text-main);">Choose Theme</h3>
            <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">Select your preferred color scheme</p>
            <div class="theme-grid">
                <div class="theme-opt" data-theme="light"><span style="width:14px;height:14px;background:#4361ee;border-radius:50%;"></span> Light</div>
                <div class="theme-opt" data-theme="dark"><span style="width:14px;height:14px;background:#6366f1;border-radius:50%;"></span> Dark</div>
                <div class="theme-opt" data-theme="sunset"><span style="width:14px;height:14px;background:#ea580c;border-radius:50%;"></span> Sunset</div>
                <div class="theme-opt" data-theme="ocean"><span style="width:14px;height:14px;background:#0891b2;border-radius:50%;"></span> Ocean</div>
                <div class="theme-opt" data-theme="midnight"><span style="width:14px;height:14px;background:#1e293b;border-radius:50%;"></span> Midnight</div>
                <div class="theme-opt" data-theme="forest"><span style="width:14px;height:14px;background:#15803d;border-radius:50%;"></span> Forest</div>
                <div class="theme-opt" data-theme="pink"><span style="width:14px;height:14px;background:#ec4899;border-radius:50%;"></span> Light Pink</div>
            </div>
            <button type="button" id="closeThemeModal" style="margin-top:20px; width:100%; padding:12px; border:none; background:var(--primary); color:white; border-radius:12px; cursor:pointer; font-weight:700; font-size:14px;">Done</button>
        </div>
    </div>

    <script>
        function switchTab(btn, tabId) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        if (menuBtn && sidebar && mainContent) {
            menuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            });
        }

        const notifBtn = document.getElementById('notifBtn');
        const notifDropdown = document.getElementById('notifDropdown');
        if (notifBtn && notifDropdown) {
            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.classList.toggle('active');
            });
            document.addEventListener('click', (e) => {
                if (!notifDropdown.contains(e.target) && e.target !== notifBtn) {
                    notifDropdown.classList.remove('active');
                }
            });
        }

        const themeBtn = document.getElementById('themeBtn');
        const themeModal = document.getElementById('themeModal');
        const closeThemeModalBtn = document.getElementById('closeThemeModal');

        const themes = {
            light: { primary: '#4361ee', bg: '#f8fafc', header: '#ffffff', sidebar: '#ffffff', card: '#ffffff', text: '#0f172a', muted: '#64748b', border: '#e2e8f0', glow: 'rgba(67, 97, 238, 0.15)', input: '#f8fafc' },
            dark: { primary: '#6366f1', bg: '#0f172a', header: '#1e293b', sidebar: '#1e293b', card: '#1e293b', text: '#f8fafc', muted: '#94a3b8', border: '#334155', glow: 'rgba(99,102,241,0.25)', input: '#0f172a' },
            sunset: { primary: '#ea580c', bg: '#fff7ed', header: '#ffffff', sidebar: '#ffffff', card: '#ffffff', text: '#431407', muted: '#9a6a52', border: '#fed7aa', glow: 'rgba(234, 88, 12, 0.2)', input: '#fff7ed' },
            ocean: { primary: '#0891b2', bg: '#ecfeff', header: '#ffffff', sidebar: '#ffffff', card: '#ffffff', text: '#083344', muted: '#5b8a99', border: '#a5f3fc', glow: 'rgba(8, 145, 178, 0.2)', input: '#ecfeff' },
            midnight: { primary: '#6366f1', bg: '#090d16', header: '#0f172a', sidebar: '#0f172a', card: '#0f172a', text: '#f8fafc', muted: '#94a3b8', border: '#1e293b', glow: 'rgba(99, 102, 241, 0.3)', input: '#090d16' },
            forest: { primary: '#15803d', bg: '#f0fdf4', header: '#ffffff', sidebar: '#ffffff', card: '#ffffff', text: '#052e16', muted: '#4d7c62', border: '#bbf7d0', glow: 'rgba(21, 128, 61, 0.2)', input: '#f0fdf4' },
            pink: { primary: '#ec4899', bg: '#fff5f7', header: '#ffffff', sidebar: '#ffffff', card: '#ffffff', text: '#4a1034', muted: '#9f4b70', border: '#fbcfe8', glow: 'rgba(236, 72, 153, 0.2)', input: '#fff5f7' }
        };

        function applyTheme(key) {
            const selected = themes[key] || themes.light;
            const root = document.documentElement;
            root.style.setProperty('--primary', selected.primary);
            root.style.setProperty('--bg', selected.bg);
            root.style.setProperty('--header-bg', selected.header);
            root.style.setProperty('--sidebar-bg', selected.sidebar);
            root.style.setProperty('--card-bg', selected.card);
            root.style.setProperty('--text-main', selected.text);
            root.style.setProperty('--text-muted', selected.muted);
            root.style.setProperty('--border', selected.border);
            root.style.setProperty('--glow', selected.glow);
            root.style.setProperty('--input-bg', selected.input);
            document.body.setAttribute('data-theme', key);
        }

        function toggleThemeModal(force) {
            if (!themeModal) return;
            themeModal.classList.toggle('active', force ?? !themeModal.classList.contains('active'));
        }

        function changeTheme(key) {
            applyTheme(key);
            localStorage.setItem('user-theme', key);
            toggleThemeModal(false);
        }

        if (themeBtn) themeBtn.addEventListener('click', () => toggleThemeModal());
        if (closeThemeModalBtn) closeThemeModalBtn.addEventListener('click', () => toggleThemeModal(false));
        document.querySelectorAll('.theme-opt').forEach(opt => {
            opt.addEventListener('click', () => changeTheme(opt.dataset.theme));
        });

        const savedTheme = localStorage.getItem('user-theme') || 'light';
        applyTheme(savedTheme);
    
        function toggleSidebar() {
            const sb = document.querySelector('.sidebar');
            const main = document.querySelector('.main-content');
            if (window.innerWidth <= 768) {
                if (sb) sb.classList.toggle('collapsed');
                if (sb) sb.classList.toggle('mobile-open');
            } else {
                if (sb) sb.classList.toggle('collapsed');
                if (main) main.classList.toggle('collapsed');
            }
        }
</script>
</body>
</html>
