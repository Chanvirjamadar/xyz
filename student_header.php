<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once("db.php");

// 1. Authentication Check
if (!isset($_SESSION['student'])) {
    header("Location: student_login.php");
    exit();
}

$student_id = $_SESSION['student'];

// 2. Fetch Student Data
$stmt = $conn->prepare("SELECT name FROM student WHERE id = ?");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$studentName = ($row = $result->fetch_assoc()) ? $row['name'] : "Student";
$initials = strtoupper(substr($studentName, 0, 2));

// 3. Fetch Statistics Counts
$paperCount = ($res = $conn->query("SELECT COUNT(*) as total FROM question_bank")) ? $res->fetch_assoc()['total'] : 0;
$materialCount = ($res = $conn->query("SELECT COUNT(*) as total FROM study_materials")) ? $res->fetch_assoc()['total'] : 0;

// ── Auto-create announcement_reads tracking table if not exists ────────
$conn->query("CREATE TABLE IF NOT EXISTS `announcement_reads` (
    `student_id` VARCHAR(50) NOT NULL,
    `announcement_id` INT NOT NULL,
    `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`student_id`, `announcement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Handle "Mark Read" Action ──────────────────────────────────────────
if (isset($_GET['mark_read'])) {
    $annId = intval($_GET['mark_read']);
    if ($annId > 0) {
        $stmtRead = $conn->prepare("INSERT IGNORE INTO announcement_reads (student_id, announcement_id) VALUES (?, ?)");
        $stmtRead->bind_param("si", $student_id, $annId);
        $stmtRead->execute();
    } elseif ($_GET['mark_read'] === 'all') {
        $conn->query("INSERT IGNORE INTO announcement_reads (student_id, announcement_id) SELECT '$student_id', id FROM announcements");
    }
    header("Location: student_dashboard.php");
    exit();
}

// 4. Fetch Unread Notifications & Dropdown List
$headerUnreadCount = 0;
$headerNotifList = [];

if (!empty($student_id)) {
    // Unread count
    $stmtUnread = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM announcements a 
        WHERE a.id NOT IN (
            SELECT announcement_id FROM announcement_reads WHERE student_id = ?
        )
    ");
    $stmtUnread->bind_param("s", $student_id);
    $stmtUnread->execute();
    $headerUnreadCount = $stmtUnread->get_result()->fetch_assoc()['total'] ?? 0;

    // Fetch top 5 announcements
    $stmtNotifs = $conn->prepare("
        SELECT a.*, 
               (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id AND ar.student_id = ?) as is_read
        FROM announcements a 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $stmtNotifs->bind_param("s", $student_id);
    $stmtNotifs->execute();
    $resNotifs = $stmtNotifs->get_result();
    while ($r = $resNotifs->fetch_assoc()) {
        $headerNotifList[] = $r;
    }
}

$pageTitle = "Student Dashboard | ZEALHUB";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ── THEME PRESETS ────────────────────────────────────────────────── */
        :root {
            --primary: #10b981;
            --primary-hover: #059669;
            --bg: #f3f4f9;
            --header-bg: #ffffff;
            --sidebar-bg: #15803d;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --glow: rgba(16, 185, 129, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: background 0.3s, color 0.3s, border-color 0.3s;
        }

        body {
            background: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
        }

        /* ── Header ───────────────────────────────────────────────────────── */
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

        .logo {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
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
            font-size: 16px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .icon-btn-wrap {
            position: relative;
        }

        .icon-btn {
            background: var(--card-bg);
            color: var(--text-main);
            border: 1px solid var(--border);
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            position: relative;
        }

        .icon-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* ── RED NOTIFICATION DOT ────────────────────────────────────────── */
        .notification-dot {
            position: absolute;
            top: 9px;
            right: 9px;
            width: 10px;
            height: 10px;
            background-color: #ef4444;
            border-radius: 50%;
            border: 2px solid var(--header-bg);
            animation: pulseDot 2s infinite;
        }

        @keyframes pulseDot {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 5px rgba(239, 68, 68, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        /* ── Dropdown Panel ───────────────────────────────────────────────── */
        .notif-dropdown {
            position: absolute;
            top: 50px;
            right: 0;
            width: 320px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            display: none;
            flex-direction: column;
            z-index: 1100;
            overflow: hidden;
        }

        .notif-dropdown.active {
            display: flex;
        }

        .notif-dropdown-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 800;
            font-size: 14px;
        }

        .notif-dropdown-body {
            max-height: 280px;
            overflow-y: auto;
        }

        .notif-dropdown-item {
            padding: 12px 18px;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
            color: var(--text-main);
            display: block;
        }

        .notif-dropdown-item:last-child {
            border-bottom: none;
        }

        .notif-dropdown-item:hover {
            background: rgba(16, 185, 129, 0.05);
        }

        /* ── Sidebar (EXPANDED BY DEFAULT) ───────────────────────────────── */
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
            transition: width 0.3s ease;
        }

        .sidebar.expanded {
            width: 260px;
            align-items: flex-start;
            padding-left: 15px;
        }

        .sidebar a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            margin-bottom: 8px;
            border-radius: 12px;
            font-size: 18px;
            transition: all 0.2s ease;
        }

        .sidebar.expanded a {
            width: 92%;
            justify-content: flex-start;
            padding-left: 15px;
        }

        .sidebar a span {
            display: none;
            font-size: 14px;
            font-weight: 600;
            margin-left: 15px;
        }

        .sidebar.expanded a span {
            display: inline;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff !important;
        }

        /* Sidebar Logout Link Styling */
        .sidebar a.logout-link {
            margin-top: auto;
            margin-bottom: 20px;
            background: rgba(239, 68, 68, 0.25);
            color: #ffffff;
        }

        .sidebar a.logout-link:hover {
            background: #ef4444;
        }

        /* ── Main Content Area ────────────────────────────────────────────── */
        .main-content {
            margin-left: 80px;
            margin-top: 75px;
            padding: 30px;
            min-height: calc(100vh - 75px);
            transition: margin-left 0.3s ease;
        }

        .main-content.pushed {
            margin-left: 260px;
        }

        /* ── DASHBOARD ELEMENTS ──────────────────────────────────────────── */
        .dashboard-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px;
        }

        .greeting-h1 {
            font-size: 26px;
            font-weight: 800;
            margin: 0;
        }

        .sub-text {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }

        .date-display {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            background: var(--card-bg);
            padding: 10px 18px;
            border-radius: 12px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 22px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            color: inherit;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: var(--primary);
            box-shadow: 0 8px 20px var(--glow);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-right: 18px;
        }

        .bg-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .bg-green { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .bg-red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .stat-label {
            font-size: 11px;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            margin: 0;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 900;
            margin: 0;
        }

        .unread-badge-pill {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #ef4444;
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 12px;
            text-transform: uppercase;
        }

        /* Quick Access Grid */
        .access-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 15px;
            margin-bottom: 35px;
        }

        .access-card {
            background: var(--card-bg);
            padding: 22px 15px;
            border-radius: 18px;
            text-align: center;
            text-decoration: none;
            color: var(--text-main);
            border: 1px solid var(--border);
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .access-card i {
            font-size: 24px;
            color: var(--primary);
        }

        .access-card span {
            font-size: 13px;
            font-weight: 700;
        }

        .access-card:hover {
            background: var(--primary);
            color: #ffffff;
            transform: scale(1.02);
        }

        .access-card:hover i {
            color: #ffffff;
        }

        /* Bottom Content Section */
        .bottom-row {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .content-box {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 20px;
            border: 1px solid var(--border);
        }

        .content-box h3 {
            font-size: 1.05rem;
            font-weight: 800;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notif-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s;
        }

        .notif-item:last-child {
            border: none;
        }

        .notif-item strong {
            display: block;
            font-size: 13.5px;
            color: var(--text-main);
            margin-bottom: 3px;
        }

        .unread-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            margin-right: 6px;
        }

        .about-us-box {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 20px;
            border: 1px solid var(--border);
            margin-top: 10px;
        }

        .app-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 13px;
            color: var(--text-muted);
        }

        @media (max-width: 900px) {
            .main-content.pushed { margin-left: 80px; }
            .sidebar.expanded { width: 80px; align-items: center; padding-left: 0; }
            .sidebar.expanded a span { display: none; }
            .bottom-row { grid-template-columns: 1fr; }
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
<body>

    <!-- ── HEADER ─────────────────────────────────────────────────────────── -->
    <header class="header">
        <div class="header-left">
            <button type="button" class="menu-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Toggle Sidebar Navigation" style="background: var(--primary); color: #ffffff; border: none; width: 40px; height: 40px; border-radius: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; margin-right: 12px;"><i class="fa-solid fa-bars"></i></button>
            <button class="menu-btn" id="menuBtn" aria-label="Toggle Navigation"><i class="fa-solid fa-bars"></i></button>
            <a href="student_dashboard.php" class="logo"><i class="fa-solid fa-graduation-cap"></i> ZEALHUB</a>
        </div>
        <div class="header-right">
            <!-- Theme Palette Toggle Button -->
            <button class="icon-btn" id="themeBtn" type="button" aria-label="Choose Theme" onclick="alert('Theme selector coming soon!')">
                <i class="fa-solid fa-palette"></i>
            </button>

            <!-- Notification Dropdown Toggle -->
            <div class="icon-btn-wrap">
                <button class="icon-btn" id="notifDropdownBtn" type="button" aria-label="Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($headerUnreadCount > 0): ?>
                        <span class="notification-dot"></span>
                    <?php endif; ?>
                </button>

                <!-- Dropdown Menu -->
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dropdown-header">
                        <span>Notifications</span>
                        <a href="student_dashboard.php?mark_read=all" style="font-size:11px; color:var(--primary); text-decoration:none;">Mark all read</a>
                    </div>
                    <div class="notif-dropdown-body">
                        <?php if (!empty($headerNotifList)): ?>
                            <?php foreach ($headerNotifList as $hn): ?>
                                <a href="student_dashboard.php?mark_read=<?= $hn['id'] ?>" class="notif-dropdown-item">
                                    <strong style="font-size:13px; display:block;"><?= htmlspecialchars($hn['title']) ?></strong>
                                    <span style="font-size:12px; color:var(--text-muted); display:block; margin-top:2px;"><?= htmlspecialchars(substr($hn['message'] ?? '', 0, 50)) ?>...</span>
                                    <small style="font-size:10px; color:var(--text-muted); font-weight:600; margin-top:4px; display:block;">
                                        <?= date('M d, H:i', strtotime($hn['created_at'])) ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding:20px; text-align:center; color:var(--text-muted); font-size:12px;">No notifications.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Badge -->
            <a href="student_profile.php" style="display:flex;align-items:center;gap:12px;padding:6px 15px;border-radius:12px;border:1px solid var(--border);background:var(--card-bg);text-decoration:none;color:inherit;">
                <div style="text-align:right;">
                    <p style="font-size:11px;font-weight:800;margin:0;line-height:1.2;"><?= htmlspecialchars($studentName) ?></p>
                    <p style="font-size:9px;color:var(--text-muted);margin:0;">STUDENT</p>
                </div>
                <div style="width:32px;height:32px;background:var(--primary);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;"><?= $initials ?></div>
            </a>
        </div>
    </header>

    <!-- ── SIDEBAR (EXPANDED BY DEFAULT) ───────────────────────────────── -->
    <aside class="sidebar expanded" id="sidebar">
        <a href="student_dashboard.php" class="active"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
        <a href="student_study_material.php"><i class="fa-solid fa-cloud-arrow-down"></i> <span>Study Materials</span></a>
        <a href="student_questionbank.php"><i class="fa-solid fa-file-circle-check"></i> <span>Question Bank</span></a>
        <a href="student_syllabus.php"><i class="fa-solid fa-scroll"></i> <span>Syllabus</span></a>
        <a href="student_raise_queries.php"><i class="fa-solid fa-clipboard-question"></i> <span>Ask Queries</span></a>
        <a href="student_alert.php"><i class="fa-solid fa-bell"></i> <span>Announcements</span></a>
        <a href="student_lab.php"><i class="fa-solid fa-flask"></i> <span>Coding Lab</span></a>
        <a href="student_library.php"><i class="fa-solid fa-book-open-reader"></i> <span>Library</span></a>
        
        <!-- Sidebar Logout Button -->
        <a href="student_logout.php" class="logout-link"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span></a>
    </aside>

    <!-- ── MAIN CONTENT AREA ────────────────────────────────────────────── -->
    <main class="main-content pushed" id="mainContent">

        <div class="dashboard-header-flex">
            <div>
                <h1 class="greeting-h1" id="greeting">Hello, <?= htmlspecialchars($studentName) ?>! 👋</h1>
                <p class="sub-text">Welcome back to your academic command center.</p>
            </div>
            <div class="date-display">
                <i class="fa-regular fa-calendar-check"></i>
                <span id="date-now"></span>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-grid">
            <a href="student_questionbank.php" class="stat-card">
                <div class="stat-icon bg-blue"><i class="fa-solid fa-file-lines"></i></div>
                <div>
                    <p class="stat-label">Papers</p>
                    <h2 class="stat-value"><?= $paperCount ?></h2>
                </div>
            </a>
            <a href="student_study_material.php" class="stat-card">
                <div class="stat-icon bg-green"><i class="fa-solid fa-book"></i></div>
                <div>
                    <p class="stat-label">Notes</p>
                    <h2 class="stat-value"><?= $materialCount ?></h2>
                </div>
            </a>
            <a href="student_dashboard.php?mark_read=all" class="stat-card">
                <?php if ($headerUnreadCount > 0): ?>
                    <span class="unread-badge-pill"><?= $headerUnreadCount ?> New</span>
                <?php endif; ?>
                <div class="stat-icon bg-red"><i class="fa-solid fa-bell"></i></div>
                <div>
                    <p class="stat-label">Unread Alerts</p>
                    <h2 class="stat-value"><?= $headerUnreadCount ?></h2>
                </div>
            </a>
        </div>

        <!-- Quick Access Grid -->
        <h3 style="margin-bottom: 18px; font-weight: 800;">Quick Access</h3>
        <div class="access-grid">
            <a href="student_questionbank.php" class="access-card">
                <i class="fa-solid fa-file-circle-question"></i>
                <span>Question Bank</span>
            </a>
            <a href="student_study_material.php" class="access-card">
                <i class="fa-solid fa-file-pdf"></i>
                <span>Study Material</span>
            </a>
            <a href="student_syllabus.php" class="access-card">
                <i class="fa-solid fa-list-ul"></i>
                <span>Syllabus</span>
            </a>
            <a href="student_library.php" class="access-card">
                <i class="fa-solid fa-book-open-reader"></i>
                <span>Library</span>
            </a>
            <a href="student_lab.php" class="access-card">
                <i class="fa-solid fa-flask"></i>
                <span>Coding Lab</span>
            </a>
            <a href="student_raise_queries.php" class="access-card">
                <i class="fa-solid fa-clipboard-question"></i>
                <span>Ask Queries</span>
            </a>
            <a href="student_raise_queries.php" class="access-card">
                <i class="fa-solid fa-headset"></i>
                <span>Help & Support</span>
            </a>
        </div>

        <div class="bottom-row">
            <div class="content-box">
                <h3><i class="fa-solid fa-circle-info" style="color: #3b82f6;"></i> Portal Updates</h3>
                <p style="color: var(--text-muted); line-height: 1.6; font-size: 0.92rem; margin: 0;">
                    Access all semester-wise resources. New features like faculty query responses and material alerts are now live.
                </p>
            </div>

            <div class="content-box">
                <h3 style="justify-content: space-between;">
                    <span><i class="fa-solid fa-bullhorn" style="color: #f59e0b;"></i> Recent Announcements</span>
                    <a href="student_dashboard.php?mark_read=all" style="font-size: 12px; font-weight: 700; color: var(--primary); text-decoration: none;">View All &rarr;</a>
                </h3>
                <div style="max-height: 200px; overflow-y: auto;">
                    <?php if (!empty($headerNotifList)): ?>
                        <?php foreach ($headerNotifList as $n): ?>
                            <a href="student_dashboard.php?mark_read=<?= $n['id'] ?>" class="notif-item" style="text-decoration: none; display: block;">
                                <strong>
                                    <?php if ($n['is_read'] == 0): ?>
                                        <span class="unread-dot"></span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($n['title']) ?>
                                </strong>
                                <p style="color:var(--text-muted); font-size:12px; margin:2px 0;"><?= htmlspecialchars(substr($n['message'] ?? '', 0, 85)) ?>...</p>
                                <small style="color: var(--text-muted); font-size: 10px; font-weight:600;">
                                    <?= isset($n['created_at']) ? date('M d, H:i', strtotime($n['created_at'])) : '' ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-size: 0.88rem; font-style: italic; padding: 10px 0;">No announcements posted yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- About Section -->
        <div class="about-us-box">
            <h3 style="color: var(--primary); font-weight: 800; margin-bottom: 12px; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-graduation-cap"></i> About ZEALHUB
            </h3>
            <p style="color: var(--text-muted); line-height: 1.6; font-size: 0.95rem;">
                <strong>ZEALHUB Academic Study Portal</strong> is an integrated digital platform engineered to provide students and faculty with unified access to course materials, question banks, semester syllabi, interactive coding labs, and virtual e-libraries.
            </p>
        </div>

        <footer class="app-footer">
            &copy; 2026 ZEALHUB Academic Study Portal • Secure Access Control
        </footer>

    </main>

    <!-- JavaScript Actions -->
    <script>
        // Sidebar Toggle Script
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        if (menuBtn && sidebar) {
            menuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('expanded');
                if (mainContent) mainContent.classList.toggle('pushed');
            });
        }

        // Notification Dropdown Toggle Script
        const notifBtn = document.getElementById('notifDropdownBtn');
        const notifDropdown = document.getElementById('notifDropdown');
        
        if (notifBtn && notifDropdown) {
            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notifDropdown.classList.toggle('active');
            });

            document.addEventListener('click', (e) => {
                if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                    notifDropdown.classList.remove('active');
                }
            });
        }

        // Dynamic Greeting & Date Script
        document.addEventListener("DOMContentLoaded", function () {
            const greetingElement = document.getElementById('greeting');
            const hour = new Date().getHours();
            const name = "<?= htmlspecialchars($studentName) ?>";
            let welcomeText = (hour < 12) ? "Good Morning" : (hour < 17) ? "Good Afternoon" : "Good Evening";
            if (greetingElement) greetingElement.innerText = `${welcomeText}, ${name}! 👋`;

            const dateElement = document.getElementById('date-now');
            if (dateElement) dateElement.innerText = new Date().toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        });
    
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