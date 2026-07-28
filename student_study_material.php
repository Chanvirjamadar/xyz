<?php
session_start();
include("db.php");

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Security Check
if (!isset($_SESSION['student'])) {
    header("Location: student_login.php");
    exit();
}

$studentID = $_SESSION['student'];
$currentPage = basename($_SERVER['PHP_SELF']);

function isActive($pageName, $currentPage) {
    return ($pageName === $currentPage) ? 'active' : '';
}

// Fetch Student Name
$stmt = $conn->prepare("SELECT name FROM student WHERE id = ?");
$stmt->bind_param("s", $studentID);
$stmt->execute();
$resStudent = $stmt->get_result();
$studentName = ($row = $resStudent->fetch_assoc()) ? $row['name'] : "Student";
$initials = strtoupper(substr(preg_replace('/\s+/', ' ', trim($studentName)), 0, 2)) ?: 'ST';

// Fetch Unread Announcements Count
$stmtUnread = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM announcements a 
    WHERE a.id NOT IN (
        SELECT announcement_id FROM announcement_reads WHERE student_id = ?
    )
");
$stmtUnread->bind_param("s", $studentID);
$stmtUnread->execute();
$notifCount = $stmtUnread->get_result()->fetch_assoc()['total'] ?? 0;

$notifications = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
$notifArray = [];
if ($notifications) {
    while ($nRow = $notifications->fetch_assoc()) {
        $notifArray[] = $nRow;
    }
}

// --- STUDY MATERIAL LOGIC ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchQuery = "";
if ($search !== '') {
    $safeSearch = "%" . mysqli_real_escape_string($conn, $search) . "%";
    $searchQuery = " AND (sm.subject LIKE '$safeSearch' OR sm.title LIKE '$safeSearch' OR sp.name LIKE '$safeSearch') ";
}

// Fetch Materials with Faculty Info
$dataQuery = "SELECT sm.*, sp.name as faculty_name 
              FROM study_materials sm 
              LEFT JOIN staff_profile sp ON sm.uploaded_by = sp.staff_id 
              WHERE 1=1 $searchQuery 
              ORDER BY sm.upload_date DESC";

$material_res = $conn->query($dataQuery);
$totalFiles = ($material_res) ? $material_res->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Materials | ZEALHUB</title>
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

        .notif-dropdown {
            position: absolute;
            top: 55px;
            right: 140px;
            width: 320px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
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

        .notif-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
            color: inherit;
            display: block;
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
        }

        /* SIDEBAR - OPEN BY DEFAULT */
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
            transition: width 0.3s ease;
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
        }

        /* MAIN CONTENT - MARGIN LEFT MATCHES OPEN SIDEBAR */
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

        .page-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px;
        }

        .page-header-flex h1 {
            font-size: 26px;
            font-weight: 800;
        }

        .total-badge {
            background: var(--card-bg);
            padding: 10px 20px;
            border-radius: 14px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .badge-icon {
            width: 40px;
            height: 40px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        /* SEARCH SECTION */
        .search-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 18px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            gap: 12px;
        }

        .search-input {
            flex: 1;
            padding: 12px 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--input-bg);
            color: var(--text-main);
            font-size: 14px;
            outline: none;
        }

        .search-input:focus {
            border-color: var(--primary);
        }

        .btn-search {
            padding: 12px 24px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-search:hover {
            opacity: 0.9;
        }

        /* TABLE */
        .table-container {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .custom-table th {
            background: var(--bg);
            padding: 16px 20px;
            font-size: 12px;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        .custom-table td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .custom-table tr:last-child td {
            border-bottom: none;
        }

        .avatar-sm {
            width: 36px;
            height: 36px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 12px;
            flex-shrink: 0;
        }

        .subject-badge {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
        }

        .material-title {
            font-weight: 700;
            color: var(--text-main);
            display: block;
        }

        .btn-view, .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            margin-left: 6px;
            transition: all 0.2s ease;
        }

        .btn-view {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .btn-view:hover {
            background: var(--primary);
            color: white;
        }

        .btn-download {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .btn-download:hover {
            background: var(--success);
            color: white;
        }

        /* THEME MODAL */
        .theme-modal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
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

        @media (max-width: 1100px) {
            .header-nav { display: none; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 260px !important; }
            .sidebar.collapsed { transform: translateX(0); }
            .main-content, .main-content.collapsed { margin-left: 0 !important; padding: 20px 15px; }
            .search-form { flex-direction: column; }
            .btn-search { width: 100%; justify-content: center; }
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
                <h1>Study Materials</h1>
                <p style="color: var(--text-muted); font-size: 14px;">Download & review course notes provided by faculty</p>
            </div>
            <div class="total-badge">
                <div class="badge-icon"><i class="fa-solid fa-folder-open"></i></div>
                <div>
                    <p>TOTAL MATERIALS</p>
                    <h2><?= $totalFiles ?></h2>
                </div>
            </div>
        </div>

        <!-- SEARCH FORM -->
        <div class="search-card">
            <form method="GET" class="search-form">
                <input type="text" name="search" class="search-input" placeholder="Search by subject, material title, or faculty name..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                <?php if ($search !== ''): ?>
                    <a href="student_study_material.php" style="padding: 12px 18px; background: var(--bg); border: 1px solid var(--border); border-radius: 12px; text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- MATERIALS TABLE -->
        <div class="table-container">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Uploaded By</th>
                        <th>Subject</th>
                        <th>Material Title & Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($material_res && $material_res->num_rows > 0): ?>
                        <?php while ($row = $material_res->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div class="avatar-sm">
                                            <?= strtoupper(substr($row['faculty_name'] ?? 'F', 0, 2)) ?>
                                        </div>
                                        <strong style="font-weight: 600;"><?= htmlspecialchars($row['faculty_name'] ?? $row['uploaded_by']) ?></strong>
                                    </div>
                                </td>
                                <td><span class="subject-badge"><?= htmlspecialchars($row['subject']) ?></span></td>
                                <td>
                                    <span class="material-title"><?= htmlspecialchars($row['title']) ?></span>
                                    <small style="color: var(--text-muted); font-size: 11px;">
                                        <i class="fa-regular fa-calendar" style="margin-right: 4px;"></i>
                                        <?= date('M d, Y', strtotime($row['upload_date'])) ?>
                                    </small>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="<?= htmlspecialchars($row['file_path']) ?>" class="btn-view" target="_blank">
                                        <i class="fa-solid fa-eye"></i> View
                                    </a>
                                    <a href="<?= htmlspecialchars($row['file_path']) ?>" class="btn-download" download>
                                        <i class="fa-solid fa-download"></i> Download
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 60px; color: var(--text-muted);">
                                <i class="fa-solid fa-file-circle-xmark" style="font-size: 3rem; margin-bottom: 15px; display: block; opacity: 0.3;"></i>
                                <span style="font-size: 1rem; font-weight: 600;">No study materials found.</span><br>
                                <small>Try adjusting your search criteria.</small>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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