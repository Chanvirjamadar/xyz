<?php
session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/staff_helpers.php";

if (!isset($_SESSION['staff'])) {
    header("Location: staff_login.php");
    exit();
}

$staffID = $_SESSION['staff'];
$staffName = isset($_SESSION['staff_name']) ? $_SESSION['staff_name'] : $staffID;

// Ensure Staff Announcement Reads Table Exists
$conn->query("CREATE TABLE IF NOT EXISTS `staff_announcement_reads` (
    `staff_id` VARCHAR(100) NOT NULL,
    `announcement_id` INT NOT NULL,
    `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`staff_id`, `announcement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fetch Unread Announcements Count for Staff
$stmtUnreadStaff = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM announcements a 
    WHERE (a.target_audience IS NULL OR a.target_audience IN ('all', 'both', 'staff'))
    AND a.id NOT IN (
        SELECT announcement_id FROM staff_announcement_reads WHERE staff_id = ?
    )
");
$stmtUnreadStaff->bind_param("s", $staffID);
$stmtUnreadStaff->execute();
$notifCount = $stmtUnreadStaff->get_result()->fetch_assoc()['total'] ?? 0;

// Fetch Recent Announcements for Staff with Read Status
$stmtNotifStaff = $conn->prepare("
    SELECT a.*, 
    (SELECT COUNT(*) FROM staff_announcement_reads sar WHERE sar.announcement_id = a.id AND sar.staff_id = ?) as is_read
    FROM announcements a 
    WHERE (a.target_audience IS NULL OR a.target_audience IN ('all', 'both', 'staff')) 
    ORDER BY a.created_at DESC LIMIT 5
");
$stmtNotifStaff->bind_param("s", $staffID);
$stmtNotifStaff->execute();
$notifications = $stmtNotifStaff->get_result();
$notifArray = [];
if ($notifications) {
    while ($nRow = $notifications->fetch_assoc()) {
        $notifArray[] = $nRow;
    }
}

$message = "";
$msg_type = "";

// Handle Update Logic
if (isset($_POST['update'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $mobile_no = mysqli_real_escape_string($conn, $_POST['mobile_no']);
    $qualification = mysqli_real_escape_string($conn, $_POST['qualification']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);

    $photoQuery = "";
    if (isset($_FILES['photo']) && $_FILES['photo']['name'] != "") {
        $folder = "uploads/staff/";
        if (!is_dir($folder)) mkdir($folder, 0777, true);
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $photo = time() . "_" . basename($_FILES['photo']['name']);
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $folder . $photo)) {
                $photoQuery = ", photo='$photo'";
            }
        }
    }

    $update = "UPDATE staff_profile SET 
        name='$name', email='$email', phone='$phone', mobile_no='$mobile_no',
        qualification='$qualification', department='$department', designation='$designation',
        gender='$gender', dob='$dob', address='$address' $photoQuery
        WHERE staff_id='$staffID'";

    if ($conn->query($update)) {
        $_SESSION['staff_name'] = $name;
        $staffName = $name;
        $message = "Profile updated successfully!";
        $msg_type = "success";
    } else {
        $message = "Failed to update profile: " . $conn->error;
        $msg_type = "error";
    }
}

// Fetch Staff Details
$staffRow = function_exists('getStaffProfile') ? getStaffProfile($conn, $staffID) : null;
if (!$staffRow) {
    // Fallback if record is missing
    $res = $conn->query("SELECT * FROM staff_profile WHERE staff_id='$staffID'");
    if ($res && $res->num_rows > 0) {
        $staffRow = $res->fetch_assoc();
    } else {
        $staffRow = [
            'staff_id' => $staffID,
            'name' => $staffName,
            'email' => '',
            'phone' => '',
            'mobile_no' => '',
            'qualification' => 'Faculty',
            'department' => 'General',
            'designation' => 'Assistant Professor',
            'gender' => 'Male',
            'dob' => '',
            'address' => '',
            'photo' => ''
        ];
    }
}

$photo = function_exists('getStaffPhotoUrl') ? getStaffPhotoUrl($staffRow) : 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
$subjects = function_exists('getSubjectsList') ? getSubjectsList($conn, $staffRow['department']) : [];
$uploadStats = function_exists('getStaffUploadStats') ? getStaffUploadStats($conn, $staffRow['name']) : ['materials' => 0, 'question_bank' => 0];

// Pending queries count for header badge
$pendingQueries = ($res = $conn->query("SELECT COUNT(*) as total FROM queries WHERE status='pending'")) ? $res->fetch_assoc()['total'] : 0;



// QR Verification URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$qrText = "{$protocol}://{$host}/staff_verify.php?id=" . $staffRow['staff_id'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Profile | ZEALHUB</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #6366f1; 
            --bg: #f3f4f9;
            --header-bg: #ffffff;
            --sidebar-bg: #ffffff;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --glow: rgba(99, 102, 241, 0.3);
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --input-bg: #f8fafc;
        }

        [data-theme="dark"] {
            --bg: #0f172a;
            --header-bg: #0f172a;
            --sidebar-bg: #1e293b;
            --card-bg: #1e293b;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border: #334155;
            --glow: rgba(99, 102, 241, 0.5);
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
            overflow-x: hidden;
        }

        /* --- HEADER --- */
        .header {
            height: 75px;
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
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .icon-btn {
            background: var(--card-bg);
            color: var(--text-main);
            border: 1px solid var(--border);
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .icon-btn:hover {
            background: var(--primary);
            color: white;
        }

        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 50%;
            border: 2px solid var(--header-bg);
            font-weight: 800;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: 80px;
            height: calc(100vh - 75px);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            position: fixed;
            top: 75px;
            left: 0;
            padding-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 999;
            overflow-y: auto;
        }

        .sidebar.expanded {
            width: 260px;
            align-items: flex-start;
            padding-left: 20px;
        }

        .sidebar a {
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            margin-bottom: 12px;
            border-radius: 12px;
            flex-shrink: 0;
        }

        .sidebar.expanded a {
            width: 90%;
            justify-content: flex-start;
            padding-left: 15px;
        }

        .sidebar a span {
            display: none;
            font-size: 14px;
            font-weight: 600;
            margin-left: 15px;
            white-space: nowrap;
        }

        .sidebar.expanded a span {
            display: inline;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: var(--primary);
            color: white !important;
            box-shadow: 0 0 15px var(--glow);
        }

        /* --- MAIN CONTENT --- */
        .main-content {
            margin-left: 80px;
            margin-top: 75px;
            padding: 30px;
            min-height: calc(100vh - 75px);
        }

        .main-content.pushed {
            margin-left: 260px;
        }

        /* --- ID CARD MODERN --- */
        .id-card-modern {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 24px;
            color: white;
            padding: 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
            border: 1px solid var(--border);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .id-main {
            display: flex;
            gap: 25px;
            align-items: center;
        }

        .id-photo-wrapper {
            position: relative;
            width: 130px;
            height: 130px;
            border-radius: 20px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            background: var(--input-bg);
            flex-shrink: 0;
        }

        .id-photo-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .id-photo-overlay {
            position: absolute;
            bottom: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            font-size: 11px;
            text-align: center;
            padding: 5px 0;
            cursor: pointer;
            opacity: 0;
            transition: 0.3s;
        }

        .id-photo-wrapper:hover .id-photo-overlay {
            opacity: 1;
        }

        .id-info h2 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .id-badge {
            display: inline-block;
            background: var(--primary);
            padding: 4px 12px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .id-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            font-size: 13px;
        }

        .id-item label {
            display: block;
            color: #94a3b8;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
        }

        .id-item span {
            font-weight: 600;
            color: #f1f5f9;
        }

        .id-qr-box {
            background: white;
            padding: 10px;
            border-radius: 16px;
            text-align: center;
        }

        .id-qr-box img {
            width: 100px;
            height: 100px;
        }

        .id-qr-box p {
            color: #1e293b;
            font-size: 9px;
            font-weight: 800;
            margin-top: 4px;
        }

        /* --- CARDS & FORM --- */
        .staff-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid var(--border);
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        }

        .staff-card h3 {
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-main);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .form-control-static {
            width: 100%;
            padding: 11px 15px;
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 13.5px;
            color: var(--text-main);
            outline: none;
            transition: 0.3s;
        }

        .is-editing .form-control-static {
            background: var(--card-bg);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--glow);
        }

        /* Subjects Grid */
        .subject-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .subject-pill {
            background: var(--input-bg);
            border: 1px solid var(--border);
            padding: 15px;
            border-radius: 14px;
            transition: 0.3s;
        }

        .subject-pill:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .subject-pill .code {
            font-size: 11px;
            font-weight: 800;
            color: var(--primary);
        }

        .subject-pill .name {
            font-size: 13.5px;
            font-weight: 700;
            margin: 4px 0;
            color: var(--text-main);
        }

        .btn-edit-float {
            background: var(--primary);
            color: white;
            padding: 10px 22px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }

        .btn-edit-float:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .action-bar {
            position: sticky;
            bottom: 20px;
            background: var(--card-bg);
            padding: 15px 25px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            display: none;
            justify-content: center;
            gap: 15px;
            z-index: 100;
            border: 1px solid var(--primary);
        }

        .btn-save {
            background: var(--success);
            color: white;
            padding: 10px 22px;
            border-radius: 12px;
            border: none;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-cancel {
            background: var(--input-bg);
            color: var(--text-main);
            padding: 10px 22px;
            border-radius: 12px;
            border: 1px solid var(--border);
            font-weight: 700;
            cursor: pointer;
        }

        /* --- THEME MODAL --- */
        .theme-modal {
            position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: none;
            align-items: center; justify-content: center; z-index: 3000; padding: 20px;
        }
        .theme-modal.active { display: flex; }
        .theme-card {
            background: var(--card-bg); padding: 30px; border-radius: 24px; width: min(90%, 420px);
            border: 1px solid var(--border); text-align: center;
        }
        .theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        .theme-opt {
            padding: 15px; border-radius: 15px; border: 2px solid var(--border); cursor: pointer; font-weight: 700;
            display: flex; align-items: center; gap: 10px;
        }
        .theme-opt:hover { border-color: var(--primary); background: var(--bg); }

        /* --- RESPONSIVE MEDIA QUERIES --- */
        @media (max-width: 992px) {
            .main-content.pushed {
                margin-left: 80px;
            }
            .id-card-modern {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            .id-main {
                flex-direction: column;
            }
            .id-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 0 15px;
            }

            .logo span {
                display: none;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 260px !important;
                box-shadow: 10px 0 30px rgba(0,0,0,0.15);
                align-items: flex-start;
                padding-left: 20px;
            }

            .sidebar a {
                width: 90%;
                justify-content: flex-start;
                padding-left: 15px;
            }

            .sidebar a span {
                display: inline;
            }

            .sidebar.expanded {
                transform: translateX(0);
            }

            .main-content,
            .main-content.pushed {
                margin-left: 0 !important;
                padding: 20px 15px;
            }
        }
    </style>
</head>

<body data-theme="light">

    <!-- HEADER -->
    <header class="header">
        <div class="header-left">
            <button class="menu-btn" id="menuBtn" aria-label="Toggle Navigation"><i class="fa-solid fa-bars"></i></button>
            <a href="staff_dashboard.php" class="logo"><i class="fa-solid fa-graduation-cap"></i> <span>ZEALHUB</span></a>
        </div>
        <div class="header-right">

            <!-- Notifications Icon & Dropdown -->
            <button class="icon-btn" id="notifBtn" type="button" title="Announcements" style="position: relative;">
                <i class="fa-solid fa-bell"></i>
                <?php if ($notifCount > 0): ?>
                    <span class="badge" id="notifBadge" style="position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; font-size: 10px; padding: 2px 6px; border-radius: 50%; font-weight: 800;"><?= $notifCount ?></span>
                <?php endif; ?>
            </button>

            <div class="notif-dropdown" id="notifDropdown" style="position: absolute; top: 65px; right: 180px; width: 320px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: none; flex-direction: column; z-index: 2000; overflow: hidden;">
                <div style="padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="font-size: 13.5px; font-weight: 800; margin: 0;"><i class="fa-solid fa-bell" style="color: var(--primary);"></i> Announcements</h4>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="#" onclick="markAllNotificationsRead(event)" style="font-size: 11px; color: var(--primary); font-weight: 800; text-decoration: none;"><i class="fa-solid fa-check-double"></i> Mark All Read</a>
                        <a href="staff_alert.php" style="font-size: 11px; color: var(--text-muted); font-weight: 700; text-decoration: none;">View All</a>
                    </div>
                </div>
                <div id="notifItemsContainer">
                <?php if (!empty($notifArray)): ?>
                    <?php foreach ($notifArray as $nItem): ?>
                        <?php $isRead = !empty($nItem['is_read']); ?>
                        <a href="staff_alert.php" onclick="markSingleRead(event, <?= $nItem['id'] ?>)" class="notif-item-link" style="padding: 12px 16px; border-bottom: 1px solid var(--border); text-decoration: none; color: var(--text-main); display: block; background: <?= $isRead ? 'transparent' : 'rgba(99, 102, 241, 0.08)' ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <strong style="font-size: 13px; display: block; font-weight: <?= $isRead ? '600' : '800' ?>;"><?= htmlspecialchars($nItem['title']) ?></strong>
                                <?php if (!$isRead): ?>
                                    <span class="unread-dot" style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%; display: inline-block; margin-left: 6px; flex-shrink: 0;"></span>
                                <?php endif; ?>
                            </div>
                            <small style="color: var(--text-muted); font-size: 11px;"><?= date('M d, H:i', strtotime($nItem['created_at'])) ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 16px; text-align: center; font-size: 12px; color: var(--text-muted);">
                        No announcements available.
                    </div>
                <?php endif; ?>
                </div>
            </div>

            <a href="staff_queries.php" class="icon-btn" title="Student Queries">
                <i class="fa-solid fa-message"></i>
                <?php if ($pendingQueries > 0): ?>
                    <span class="badge" id="queryBadge"><?= $pendingQueries ?></span>
                <?php endif; ?>
            </a>

            <button class="icon-btn" id="themeBtn" type="button" aria-label="Choose theme" title="Change Theme"><i class="fa-solid fa-moon" id="themeIcon"></i></button>

            <a href="staff_profile.php" style="display: flex; align-items: center; gap: 10px; padding: 6px 12px; border-radius: 12px; border: 1px solid var(--border); background: var(--card-bg); text-decoration: none; color: inherit;">
                <div style="text-align: right;">
                    <p style="font-size: 11px; font-weight: 800;"><?= htmlspecialchars($staffName) ?></p>
                    <p style="font-size: 9px; color: var(--text-muted);">FACULTY</p>
                </div>
                <div style="width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px;">SF</div>
            </a>
        </div>
    </header>

    <!-- SIDEBAR (Open by Default via 'expanded' class) -->
    <aside class="sidebar expanded" id="sidebar">
        <a href="staff_dashboard.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
        <a href="staff_studymaterial.php"><i class="fa-solid fa-cloud-arrow-up"></i> <span>Upload Materials</span></a>
        <a href="staff_questionbank.php"><i class="fa-solid fa-file-circle-plus"></i> <span>Question Bank</span></a>
        <a href="staff_syllabus.php"><i class="fa-solid fa-scroll"></i> <span>Syllabus</span></a>
        <a href="staff_queries.php"><i class="fa-solid fa-clipboard-question"></i> <span>Student Queries</span></a>
        <a href="staff_alert.php"><i class="fa-solid fa-bell"></i> <span>Announcements</span></a>
        <a href="staff_lab.php"><i class="fa-solid fa-flask"></i> <span>Coding Lab</span></a>
        <a href="staff_library.php"><i class="fa-solid fa-book-open-reader"></i> <span>Library</span></a>
        <a href="staff_logout.php" class="btn-logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span></a>
        </aside>

    <!-- MAIN CONTENT (Open/Pushed by default) -->
    <main class="main-content pushed" id="mainContent">
        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-size:28px; font-weight:800;">Faculty Profile</h1>
                <p style="color:var(--text-muted);">Manage your academic identity and professional records.</p>
            </div>
            <button type="button" class="btn-edit-float" id="editBtn" onclick="toggleEditMode()">
                <i class="fa-solid fa-pen-to-square"></i> Edit Profile
            </button>
        </div>

        <?php if (!empty($message)): ?>
            <div style="padding:15px; border-radius:12px; margin-bottom:20px; font-size:14px; font-weight:700; background: <?= ($msg_type == 'success') ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; color: <?= ($msg_type == 'success') ? 'var(--success)' : 'var(--danger)' ?>; border: 1px solid <?= ($msg_type == 'success') ? 'var(--success)' : 'var(--danger)' ?>;">
                <i class="fa-solid <?= ($msg_type == 'success') ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                <?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Identity Card Section -->
        <div class="id-card-modern">
            <div class="id-main">
                <div class="id-photo-wrapper">
                    <img src="<?= htmlspecialchars($photo); ?>" id="idPreview" alt="Profile">
                    <div class="id-photo-overlay" onclick="document.getElementById('photoInput').click()">
                        <i class="fa-solid fa-camera"></i> Change
                    </div>
                </div>
                <div class="id-info">
                    <span class="id-badge">Verified Faculty</span>
                    <h2><?= htmlspecialchars($staffRow['name']); ?></h2>
                    <div class="id-grid">
                        <div class="id-item">
                            <label>Department</label>
                            <span><?= htmlspecialchars($staffRow['department'] ?: 'Not Assigned'); ?></span>
                        </div>
                        <div class="id-item">
                            <label>Staff ID</label>
                            <span><?= htmlspecialchars($staffRow['staff_id']); ?></span>
                        </div>
                        <div class="id-item">
                            <label>Designation</label>
                            <span><?= htmlspecialchars($staffRow['designation'] ?: 'Faculty'); ?></span>
                        </div>
                        <div class="id-item">
                            <label>Qualification</label>
                            <span><?= htmlspecialchars($staffRow['qualification'] ?: 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="id-qr-section">
                <div class="id-qr-box">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($qrText); ?>" alt="QR">
                    <p>SCAN TO VERIFY</p>
                </div>
            </div>
        </div>

        <!-- Details Form -->
        <form method="post" enctype="multipart/form-data" id="staffProfileForm">
            <input type="file" name="photo" id="photoInput" style="display:none;" onchange="handlePhotoPreview(event)">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 25px;">
                <!-- Personal Info Card -->
                <div class="staff-card">
                    <h3><i class="fa-solid fa-user-gear" style="color:var(--primary)"></i> Personal Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" class="form-control-static" value="<?= htmlspecialchars($staffRow['name']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="form-control-static" value="<?= htmlspecialchars($staffRow['email']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Mobile Number</label>
                            <input type="text" name="mobile_no" class="form-control-static" value="<?= htmlspecialchars($staffRow['mobile_no']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Alternative Phone</label>
                            <input type="text" name="phone" class="form-control-static" value="<?= htmlspecialchars($staffRow['phone']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender" class="form-control-static" disabled>
                                <option <?= ($staffRow['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option <?= ($staffRow['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" class="form-control-static" value="<?= htmlspecialchars($staffRow['dob']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:10px;">
                        <label>Residential Address</label>
                        <textarea name="address" class="form-control-static" rows="2" readonly><?= htmlspecialchars($staffRow['address']); ?></textarea>
                    </div>
                </div>

                <!-- Academic Records Card -->
                <div class="staff-card">
                    <h3><i class="fa-solid fa-graduation-cap" style="color:var(--primary)"></i> Academic Records</h3>
                    <div class="form-group">
                        <label>Highest Qualification</label>
                        <input type="text" name="qualification" class="form-control-static" value="<?= htmlspecialchars($staffRow['qualification']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Primary Department</label>
                        <input type="text" name="department" class="form-control-static" value="<?= htmlspecialchars($staffRow['department']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Current Designation</label>
                        <input type="text" name="designation" class="form-control-static" value="<?= htmlspecialchars($staffRow['designation']); ?>" readonly>
                    </div>
                    <hr style="border-color:var(--border); margin: 20px 0;">
                    <div style="display:flex; justify-content: space-around; text-align:center;">
                        <div>
                            <h3 style="margin:0; justify-content:center; color:var(--primary);"><?= $uploadStats['materials']; ?></h3>
                            <small style="color:var(--text-muted); font-size:11px; font-weight:700;">Study Materials</small>
                        </div>
                        <div style="border-left: 1px solid var(--border);"></div>
                        <div>
                            <h3 style="margin:0; justify-content:center; color:var(--primary);"><?= $uploadStats['question_bank']; ?></h3>
                            <small style="color:var(--text-muted); font-size:11px; font-weight:700;">Question Banks</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assigned Subjects Card -->
            <div class="staff-card">
                <h3><i class="fa-solid fa-book" style="color:var(--primary)"></i> Assigned Subjects</h3>
                <?php if (!empty($subjects)): ?>
                    <div class="subject-grid">
                        <?php foreach ($subjects as $sub): ?>
                            <div class="subject-pill">
                                <div class="code"><?= htmlspecialchars($sub['subject_code']); ?></div>
                                <div class="name"><?= htmlspecialchars($sub['subject_name']); ?></div>
                                <div style="font-size:11px; color:var(--text-muted); font-weight:600;">
                                    <?= htmlspecialchars($sub['semester']); ?> Sem • <?= htmlspecialchars($sub['branch']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center; padding:20px; color:var(--text-muted);">
                        <i class="fa-solid fa-folder-open" style="font-size: 2rem; margin-bottom: 10px; display:block;"></i>
                        <p style="font-size: 13px;">No subjects linked to your department yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sticky Save/Cancel Bar -->
            <div class="action-bar" id="actionBar">
                <button type="submit" name="update" class="btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
                <button type="button" class="btn-cancel" onclick="location.reload()">
                    Cancel
                </button>
            </div>
        </form>
    </main>

    <!-- THEME MODAL -->
    <div id="themeModal" class="theme-modal">
        <div class="theme-card">
            <h3>Choose a Theme</h3>
            <div class="theme-grid">
                <div class="theme-opt" data-theme="light"><span style="width:15px;height:15px;background:#4361ee;border-radius:50%;"></span> Light</div>
                <div class="theme-opt" data-theme="dark"><span style="width:15px;height:15px;background:#0f172a;border-radius:50%;"></span> Dark</div>
                <div class="theme-opt" data-theme="sunset"><span style="width:15px;height:15px;background:#ea580c;border-radius:50%;"></span> Sunset</div>
                <div class="theme-opt" data-theme="ocean"><span style="width:15px;height:15px;background:#0891b2;border-radius:50%;"></span> Ocean</div>
                <div class="theme-opt" data-theme="midnight"><span style="width:15px;height:15px;background:#1e293b;border-radius:50%;"></span> Midnight</div>
                <div class="theme-opt" data-theme="forest"><span style="width:15px;height:15px;background:#15803d;border-radius:50%;"></span> Forest</div>
                <div class="theme-opt" data-theme="pink"><span style="width:15px;height:15px;background:#ec4899;border-radius:50%;"></span> Light Pink</div>
            </div>
            <button type="button" id="closeThemeModal" style="margin-top:20px; width:100%; padding:10px; border:none; background:var(--primary); color:white; border-radius:10px; cursor:pointer; font-weight:700;">Close</button>
        </div>
    </div>

    <script>
        // Sidebar Toggle
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        if (menuBtn && sidebar && mainContent) {
            menuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('expanded');
                mainContent.classList.toggle('pushed');
            });
        }

        // Theme Switcher & Persistence
        const themeBtn = document.getElementById('themeBtn');
        const themeIcon = document.getElementById('themeIcon');
        const themeModal = document.getElementById('themeModal');
        const closeThemeModalBtn = document.getElementById('closeThemeModal');

        const themes = {
            light: { primary: '#4361ee', bg: '#f3f4f9', header: '#ffffff', sidebar: '#ffffff', card: '#ffffff', text: '#1e293b', muted: '#64748b', border: '#e2e8f0', glow: 'rgba(67, 97, 238, 0.2)' },
            dark: { primary: '#6366f1', bg: '#0f172a', header: '#0f172a', sidebar: '#1e293b', card: '#1e293b', text: '#f1f5f9', muted: '#94a3b8', border: '#334155', glow: 'rgba(99,102,241,0.2)' },
            sunset: { primary: '#ea580c', bg: '#fff7ed', header: '#ffffff', sidebar: '#7c2d12', card: '#ffffff', text: '#431407', muted: '#9a6a52', border: '#fed7aa', glow: 'rgba(234, 88, 12, 0.2)' },
            ocean: { primary: '#0891b2', bg: '#ecfeff', header: '#ffffff', sidebar: '#164e63', card: '#ffffff', text: '#083344', muted: '#5b8a99', border: '#a5f3fc', glow: 'rgba(8, 145, 178, 0.2)' },
            midnight: { primary: '#6366f1', bg: '#0f172a', header: '#0f172a', sidebar: '#1e293b', card: '#1e293b', text: '#f1f5f9', muted: '#94a3b8', border: '#334155', glow: 'rgba(99,102,241,0.2)' },
            forest: { primary: '#15803d', bg: '#f0fdf4', header: '#ffffff', sidebar: '#14532d', card: '#ffffff', text: '#052e16', muted: '#4d7c62', border: '#bbf7d0', glow: 'rgba(21, 128, 61, 0.2)' },
            pink: { primary: '#ec4899', bg: '#fff5f7', header: '#ffffff', sidebar: '#be185d', card: '#ffffff', text: '#4a1034', muted: '#9f4b70', border: '#fbcfe8', glow: 'rgba(236, 72, 153, 0.2)' }
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
            document.body.setAttribute('data-theme', key);
            if (themeIcon) themeIcon.className = 'fa-solid fa-palette';
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

        if (themeBtn) {
            themeBtn.addEventListener('click', () => toggleThemeModal());
        }
        if (closeThemeModalBtn) {
            closeThemeModalBtn.addEventListener('click', () => toggleThemeModal(false));
        }
        document.querySelectorAll('.theme-opt').forEach(opt => {
            opt.addEventListener('click', () => changeTheme(opt.dataset.theme));
        });
        window.addEventListener('storage', (event) => {
            if (event.key === 'user-theme' && event.newValue) {
                applyTheme(event.newValue);
            }
        });

        const savedTheme = localStorage.getItem('user-theme') || 'light';
        applyTheme(savedTheme);

        // Edit Mode Functions
        function toggleEditMode() {
            const form = document.getElementById('staffProfileForm');
            const actionBar = document.getElementById('actionBar');
            const editBtn = document.getElementById('editBtn');
            const inputs = form.querySelectorAll('.form-control-static');

            form.classList.toggle('is-editing');
            const isEditing = form.classList.contains('is-editing');

            inputs.forEach(input => {
                if (input.tagName === 'SELECT') {
                    input.disabled = !isEditing;
                } else {
                    input.readOnly = !isEditing;
                }
            });

            actionBar.style.display = isEditing ? 'flex' : 'none';
            editBtn.style.display = isEditing ? 'none' : 'flex';
        }

        function handlePhotoPreview(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('idPreview').src = e.target.result;
                }
                reader.readAsDataURL(file);
                if (!document.getElementById('staffProfileForm').classList.contains('is-editing')) {
                    toggleEditMode();
                }
            }
        }
    
        // Notification Read Functions
        function markAllNotificationsRead(e) {
            if (e) e.preventDefault();
            fetch('api/notifications.php?action=mark_all_read')
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        const badge = document.getElementById('notifBadge');
                        if (badge) badge.style.display = 'none';
                        document.querySelectorAll('.unread-dot').forEach(dot => dot.remove());
                        document.querySelectorAll('.notif-item-link').forEach(link => {
                            link.style.background = 'transparent';
                        });
                    }
                }).catch(err => console.error(err));
        }

        function markSingleRead(e, ancId) {
            fetch('api/notifications.php?action=mark_single_read&announcement_id=' + ancId)
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        const badge = document.getElementById('notifBadge');
                        if (badge) {
                            if (data.unread_count > 0) {
                                badge.innerText = data.unread_count;
                                badge.style.display = 'inline-block';
                            } else {
                                badge.style.display = 'none';
                            }
                        }
                    }
                }).catch(err => console.error(err));
        }

        // Notification Dropdown Toggle
        const notifBtn = document.getElementById('notifBtn');
        const notifDropdown = document.getElementById('notifDropdown');
        if (notifBtn && notifDropdown) {
            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.style.display = (notifDropdown.style.display === 'flex') ? 'none' : 'flex';
            });
            document.addEventListener('click', (e) => {
                if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                    notifDropdown.style.display = 'none';
                }
            });
        }
</script>
</body>
</html>