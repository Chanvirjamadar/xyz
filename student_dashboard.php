<?php
session_start();
include("db.php");

// Security Check
if (!isset($_SESSION['student'])) {
    header("Location: student_login.php");
    exit();
}

$studentID = $_SESSION['student'];
$currentPage = basename($_SERVER['PHP_SELF']);

// Helper function for active menu link
function isActive($pageName, $currentPage)
{
    return ($pageName === $currentPage) ? 'active' : '';
}

// Fetch Student Name
$stmt = $conn->prepare("SELECT name FROM student WHERE id = ?");
$stmt->bind_param("s", $studentID);
$stmt->execute();
$resStudent = $stmt->get_result();
$studentName = ($row = $resStudent->fetch_assoc()) ? $row['name'] : "Student";
$initials = strtoupper(substr(preg_replace('/\s+/', ' ', trim($studentName)), 0, 2)) ?: 'ST';

// Fetch Counts for Stats
$paperCount = ($res = $conn->query("SELECT COUNT(*) as total FROM question_bank")) ? $res->fetch_assoc()['total'] : 0;
$materialCount = ($res = $conn->query("SELECT COUNT(*) as total FROM study_materials")) ? $res->fetch_assoc()['total'] : 0;

// Ensure Announcement Reads Table Exists
$conn->query("CREATE TABLE IF NOT EXISTS `announcement_reads` (
    `student_id` VARCHAR(50) NOT NULL,
    `announcement_id` INT NOT NULL,
    `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`student_id`, `announcement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fetch Unread Announcements Count
$stmtUnread = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM announcements a 
    WHERE (a.target_audience IS NULL OR a.target_audience IN ('all', 'both', 'student'))
    AND a.id NOT IN (
        SELECT announcement_id FROM announcement_reads WHERE student_id = ?
    )
");
$stmtUnread->bind_param("s", $studentID);
$stmtUnread->execute();
$notifCount = $stmtUnread->get_result()->fetch_assoc()['total'] ?? 0;

// Fetch Recent Announcements with Read Status
$stmtNotifStudent = $conn->prepare("
    SELECT a.*, 
    (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id AND ar.student_id = ?) as is_read
    FROM announcements a 
    WHERE (a.target_audience IS NULL OR a.target_audience IN ('all', 'both', 'student')) 
    ORDER BY a.created_at DESC LIMIT 5
");
$stmtNotifStudent->bind_param("s", $studentID);
$stmtNotifStudent->execute();
$notifications = $stmtNotifStudent->get_result();
$notifArray = [];
if ($notifications) {
    while ($nRow = $notifications->fetch_assoc()) {
        $notifArray[] = $nRow;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | ZEALHUB</title>
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
            transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }

        body {
            background: var(--bg);
            color: var(--text-main);
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* --- HEADER --- */
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .menu-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            box-shadow: 0 4px 12px var(--glow);
        }

        .menu-btn:hover {
            transform: scale(1.05);
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

        .header-right {
            display: flex;
            align-items: center;
            gap: 14px;
            position: relative;
        }

        .icon-btn {
            background: var(--card-bg);
            color: var(--text-main);
            border: 1px solid var(--border);
            width: 42px;
            height: 42px;
            border-radius: 12px;
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 16px;
        }

        .icon-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px var(--glow);
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

        /* Notification Dropdown */
        .notif-dropdown {
            position: absolute;
            top: 55px;
            right: 140px;
            width: 320px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
            display: none;
            flex-direction: column;
            z-index: 2000;
            overflow: hidden;
        }

        .notif-dropdown.active {
            display: flex;
        }

        .notif-header {
            padding: 14px 16px;
            background: var(--bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notif-header h4 {
            font-size: 14px;
            font-weight: 700;
        }

        .notif-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .notif-item:hover {
            background: rgba(67, 97, 238, 0.04);
        }

        .notif-item strong {
            font-size: 13px;
            color: var(--text-main);
            display: block;
        }

        .notif-item small {
            font-size: 11px;
            color: var(--text-muted);
        }

        .profile-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }

        .profile-pill:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px var(--glow);
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: 260px;
            height: calc(100vh - 70px);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            position: fixed;
            top: 70px;
            left: 0;
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            z-index: 999;
            overflow-y: auto;
            transition: width 0.3s ease, padding 0.3s ease;
        }

        .sidebar.collapsed {
            width: 80px;
            padding: 20px 10px;
        }

        .sidebar a {
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .sidebar.collapsed a {
            justify-content: center;
            padding: 12px;
        }

        .sidebar.collapsed a span {
            display: none;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: var(--primary);
            color: #ffffff !important;
            box-shadow: 0 4px 14px var(--glow);
        }

        .sidebar a.btn-logout {
            margin-top: auto;
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .sidebar a.btn-logout:hover {
            background: var(--danger);
            color: white !important;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
        }

        /* --- MAIN CONTENT --- */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 30px;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.3s ease;
        }

        .main-content.collapsed {
            margin-left: 80px;
        }

        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, #3a0ca3 100%);
            color: white;
            padding: 30px;
            border-radius: 24px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px var(--glow);
            position: relative;
            overflow: hidden;
        }

        .welcome-banner h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .welcome-banner p {
            font-size: 14px;
            opacity: 0.9;
        }

        .stats-grid,
        .action-grid {
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
            text-decoration: none;
            color: inherit;
            transition: all 0.25s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: 0 8px 25px var(--glow);
        }

        .action-btn {
            background: var(--card-bg);
            padding: 24px 20px;
            border-radius: 20px;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            text-decoration: none;
            color: var(--text-main);
            font-weight: 700;
            font-size: 14px;
            gap: 12px;
            transition: all 0.25s ease;
        }

        .action-btn:hover {
            border-color: var(--primary);
            box-shadow: 0 10px 25px var(--glow);
            transform: translateY(-5px);
            color: var(--primary);
        }

        .icon-box {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: var(--card-bg);
            padding: 26px;
            border-radius: 24px;
            border: 1px solid var(--border);
        }

        .contact-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            border: 1px solid var(--border);
            transition: all 0.2s ease;
        }

        .contact-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--glow);
        }

        /* --- THEME MODAL --- */
        .theme-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000;
            padding: 20px;
        }

        .theme-modal.active {
            display: flex;
        }

        .theme-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 24px;
            width: min(90%, 440px);
            border: 1px solid var(--border);
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .theme-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 20px;
        }

        .theme-opt {
            padding: 14px;
            border-radius: 14px;
            border: 2px solid var(--border);
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-main);
        }

        .theme-opt:hover {
            border-color: var(--primary);
            background: var(--bg);
        }

        /* --- FOOTER --- */
        .footer {
            margin-top: 40px;
            padding: 20px 0;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-muted);
            font-size: 13px;
        }

        .footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        /* RESPONSIVE */
        @media (max-width: 1100px) {
            .header-nav {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 0 15px;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 260px !important;
            }

            .sidebar.collapsed {
                transform: translateX(0);
            }

            .main-content,
            .main-content.collapsed {
                margin-left: 0 !important;
                padding: 20px 15px;
            }

            .notif-dropdown {
                right: 10px;
                width: 290px;
            }
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
            <button type="button" class="menu-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Toggle Sidebar Navigation" style="background: var(--primary); color: #ffffff; border: none; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-right: 12px;">
                <i class="fa-solid fa-bars"></i>
            </button>
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
            <!-- Notifications Icon & Dropdown -->
            <button class="icon-btn" id="notifBtn" type="button" title="Announcements">
                <i class="fa-solid fa-bell"></i>
                <?php if ($notifCount > 0): ?>
                    <span class="badge" id="notifBadge"><?= $notifCount ?></span>
                <?php endif; ?>
            </button>

            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <h4><i class="fa-solid fa-bell"></i> Announcements</h4>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="#" onclick="markAllNotificationsRead(event)" style="font-size: 11px; color: var(--primary); font-weight: 800; text-decoration: none;"><i class="fa-solid fa-check-double"></i> Mark All Read</a>
                        <a href="student_alert.php" style="font-size: 11px; color: var(--text-muted); font-weight: 700; text-decoration: none;">View All</a>
                    </div>
                </div>
                <div id="notifItemsContainer">
                <?php if (!empty($notifArray)): ?>
                    <?php foreach ($notifArray as $nItem): ?>
                        <?php $isRead = !empty($nItem['is_read']); ?>
                        <a href="student_alert.php" onclick="markSingleRead(event, <?= $nItem['id'] ?>)" class="notif-item notif-item-link" style="background: <?= $isRead ? 'transparent' : 'rgba(67, 97, 238, 0.08)' ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
                                <strong style="font-size: 13px; font-weight: <?= $isRead ? '600' : '800' ?>;"><?= htmlspecialchars($nItem['title']) ?></strong>
                                <?php if (!$isRead): ?>
                                    <span class="unread-dot" style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%; display: inline-block; margin-left: 6px; flex-shrink: 0;"></span>
                                <?php endif; ?>
                            </div>
                            <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;"><?= date('M d, H:i', strtotime($nItem['created_at'])) ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 16px; text-align: center; font-size: 12px; color: var(--text-muted);">
                        No announcements available.
                    </div>
                <?php endif; ?>
                </div>
            </div>

            <!-- Theme Switcher Palette Icon -->
            <button class="icon-btn" id="themeBtn" type="button" title="Choose Theme" aria-label="Choose Theme">
                <i class="fa-solid fa-palette" id="themeIcon"></i>
            </button>

            <!-- Student Profile Link -->
            <a href="student_profile.php" class="profile-pill" title="My Profile">
                <div style="text-align: right;">
                    <p style="font-size: 11px; font-weight: 800; line-height: 1.2;"><?= htmlspecialchars($studentName) ?></p>
                    <p style="font-size: 9px; color: var(--text-muted); font-weight: 700;">STUDENT</p>
                </div>
                <div style="width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px;">
                    <?= $initials ?>
                </div>
            </a>

            <!-- Header Logout -->
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

    <!-- MAIN CONTENT - MARGIN LEFT MATCHES OPEN SIDEBAR -->
    <main class="main-content" id="mainContent">
        <!-- WELCOME BANNER -->
        <div class="welcome-banner">
            <h1 id="greeting">Welcome</h1>
            <p>Access your course materials, syllabi, question papers, coding lab, and academic tools seamlessly.</p>
        </div>

        <!-- STATS GRID -->
        <div class="stats-grid">
            <a href="student_questionbank.php" class="stat-card">
                <div class="icon-box" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--text-muted); font-weight:700;">QUESTION PAPERS</p>
                    <h2 style="font-size: 22px; font-weight: 800; color: var(--text-main);"><?= $paperCount ?></h2>
                </div>
            </a>

            <a href="student_study_material.php" class="stat-card">
                <div class="icon-box" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                    <i class="fa-solid fa-book"></i>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--text-muted); font-weight:700;">STUDY MATERIALS</p>
                    <h2 style="font-size: 22px; font-weight: 800; color: var(--text-main);"><?= $materialCount ?></h2>
                </div>
            </a>

            <a href="student_alert.php" class="stat-card">
                <div class="icon-box" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div>
                    <p style="font-size: 11px; color: var(--text-muted); font-weight:700;">UNREAD ANNOUNCEMENTS</p>
                    <h2 style="font-size: 22px; font-weight: 800; color: var(--text-main);"><?= $notifCount ?></h2>
                </div>
            </a>
        </div>

        <!-- QUICK ACCESS MODULES -->
        <h3 style="margin-bottom: 16px; font-size: 18px; font-weight: 800;">Quick Access</h3>
        <div class="action-grid">
            <a href="student_study_material.php" class="action-btn">
                <i class="fa-solid fa-file-pdf" style="font-size: 32px; color: var(--primary);"></i>
                <span>Study Material</span>
            </a>

            <a href="student_questionbank.php" class="action-btn">
                <i class="fa-solid fa-file-circle-question" style="font-size: 32px; color: var(--primary);"></i>
                <span>Question Bank</span>
            </a>

            <a href="student_syllabus.php" class="action-btn">
                <i class="fa-solid fa-list-ul" style="font-size: 32px; color: var(--primary);"></i>
                <span>Syllabus</span>
            </a>

            <a href="student_lab.php" class="action-btn">
                <i class="fa-solid fa-flask" style="font-size: 32px; color: var(--primary);"></i>
                <span>Coding Lab</span>
            </a>

            <a href="student_library.php" class="action-btn">
                <i class="fa-solid fa-book-open-reader" style="font-size: 32px; color: var(--primary);"></i>
                <span>Library</span>
            </a>

            <a href="student_raise_queries.php" class="action-btn">
                <i class="fa-solid fa-clipboard-question" style="font-size: 32px; color: var(--primary);"></i>
                <span>Ask Queries</span>
            </a>

            <a href="student_profile.php" class="action-btn">
                <i class="fa-solid fa-id-card" style="font-size: 32px; color: var(--primary);"></i>
                <span>My Profile</span>
            </a>
        </div>

        <!-- SOLUTION MAKER EMBED -->
        <?php
        $smUserRole = 'student';
        $smInitials = $initials;
        $smUserName = $studentName;
        
        ?>

        <!-- INFO & RECENT ALERTS GRID -->
        <div class="info-grid">
            <div class="info-card">
                <h3 style="color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; font-size: 16px;">
                    <i class="fa-solid fa-graduation-cap"></i> About ZEALHUB
                </h3>
                <p style="font-size: 13.5px; line-height: 1.6; color: var(--text-muted);">
                    <strong>ZEALHUB Academic Portal</strong> provides centralized access to course notes, syllabus guides, question banks, interactive virtual labs, and direct query channels between students and faculty.
                </p>
            </div>

            <div class="info-card">
                <h3 style="color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; font-size: 16px;">
                    <i class="fa-solid fa-headset"></i> Help & Support
                </h3>
                <p style="font-size: 13px; line-height: 1.5; color: var(--text-muted); margin-bottom: 12px;">
                    Need help or technical assistance? Contact system administrator:
                </p>
                <a href="mailto:shayamkanthale@gmail.com?subject=Student%20Support%20-%20ZEALHUB" class="contact-link" style="background: rgba(67, 97, 238, 0.08); color: var(--primary); margin-bottom: 10px;">
                    <i class="fa-solid fa-envelope"></i> shayamkanthale@gmail.com
                </a>
                <a href="tel:8551082199" class="contact-link" style="background: rgba(16, 185, 129, 0.08); color: var(--success);">
                    <i class="fa-solid fa-phone"></i> +91 8551082199
                </a>
            </div>

            <div class="info-card">
                <h3 style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px; font-size: 16px;">
                    <i class="fa-solid fa-clock-rotate-left" style="color:var(--primary)"></i> Recent Announcements
                </h3>
                <div style="max-height: 150px; overflow-y: auto;">
                    <?php if (!empty($notifArray)): ?>
                        <?php foreach ($notifArray as $n): ?>
                            <div style="padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13px;">
                                <strong style="display: block; color: var(--text-main);"><?= htmlspecialchars($n['title']) ?></strong>
                                <small style="color: var(--text-muted); font-size: 10px;"><?= date('M d, H:i', strtotime($n['created_at'])) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="font-size: 12px; color: var(--text-muted);">No announcements posted yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <footer class="footer">
            <div>
                © <?= date('Y') ?> <strong>ZEALHUB Academic Portal</strong>. All rights reserved.
            </div>
            <div style="display: flex; gap: 15px;">
                <a href="student_dashboard.php">Dashboard</a>
                <a href="student_study_material.php">Materials</a>
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
        // Sidebar Toggle Script
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        if (menuBtn && sidebar && mainContent) {
            menuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('collapsed');
            });
        }

        // Notification Dropdown Toggle
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

        // Theme Switcher & Persistence
        const themeBtn = document.getElementById('themeBtn');
        const themeIcon = document.getElementById('themeIcon');
        const themeModal = document.getElementById('themeModal');
        const closeThemeModalBtn = document.getElementById('closeThemeModal');

        const themes = {
            light: {
                primary: '#4361ee',
                bg: '#f8fafc',
                header: '#ffffff',
                sidebar: '#ffffff',
                card: '#ffffff',
                text: '#0f172a',
                muted: '#64748b',
                border: '#e2e8f0',
                glow: 'rgba(67, 97, 238, 0.15)',
                input: '#f8fafc'
            },
            dark: {
                primary: '#6366f1',
                bg: '#0f172a',
                header: '#1e293b',
                sidebar: '#1e293b',
                card: '#1e293b',
                text: '#f8fafc',
                muted: '#94a3b8',
                border: '#334155',
                glow: 'rgba(99,102,241,0.25)',
                input: '#0f172a'
            },
            sunset: {
                primary: '#ea580c',
                bg: '#fff7ed',
                header: '#ffffff',
                sidebar: '#ffffff',
                card: '#ffffff',
                text: '#431407',
                muted: '#9a6a52',
                border: '#fed7aa',
                glow: 'rgba(234, 88, 12, 0.2)',
                input: '#fff7ed'
            },
            ocean: {
                primary: '#0891b2',
                bg: '#ecfeff',
                header: '#ffffff',
                sidebar: '#ffffff',
                card: '#ffffff',
                text: '#083344',
                muted: '#5b8a99',
                border: '#a5f3fc',
                glow: 'rgba(8, 145, 178, 0.2)',
                input: '#ecfeff'
            },
            midnight: {
                primary: '#6366f1',
                bg: '#090d16',
                header: '#0f172a',
                sidebar: '#0f172a',
                card: '#0f172a',
                text: '#f8fafc',
                muted: '#94a3b8',
                border: '#1e293b',
                glow: 'rgba(99, 102, 241, 0.3)',
                input: '#090d16'
            },
            forest: {
                primary: '#15803d',
                bg: '#f0fdf4',
                header: '#ffffff',
                sidebar: '#ffffff',
                card: '#ffffff',
                text: '#052e16',
                muted: '#4d7c62',
                border: '#bbf7d0',
                glow: 'rgba(21, 128, 61, 0.2)',
                input: '#f0fdf4'
            },
            pink: {
                primary: '#ec4899',
                bg: '#fff5f7',
                header: '#ffffff',
                sidebar: '#ffffff',
                card: '#ffffff',
                text: '#4a1034',
                muted: '#9f4b70',
                border: '#fbcfe8',
                glow: 'rgba(236, 72, 153, 0.2)',
                input: '#fff5f7'
            }
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

        if (themeBtn) {
            themeBtn.addEventListener('click', () => toggleThemeModal());
        }
        if (closeThemeModalBtn) {
            closeThemeModalBtn.addEventListener('click', () => toggleThemeModal(false));
        }
        document.querySelectorAll('.theme-opt').forEach(opt => {
            opt.addEventListener('click', () => changeTheme(opt.dataset.theme));
        });

        const savedTheme = localStorage.getItem('user-theme') || 'light';
        applyTheme(savedTheme);

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

        function toggleSidebar() {
            const sb = document.querySelector('.sidebar');
            const main = document.querySelector('.main-content');
            if (window.innerWidth <= 768) {
                if (sb) sb.classList.toggle('collapsed');
            } else {
                if (sb) sb.classList.toggle('collapsed');
                if (main) main.classList.toggle('collapsed');
            }
        }

        // Dynamic Greeting
        const hour = new Date().getHours();
        const student = "<?= htmlspecialchars($studentName) ?>";
        let greet = (hour < 12) ? "Good Morning" : (hour < 17) ? "Good Afternoon" : "Good Evening";
        const greetEl = document.getElementById('greeting');
        if (greetEl) greetEl.innerText = `${greet}, ${student}! 🎓`;
    </script>
</body>

</html>