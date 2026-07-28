<?php
session_start();
include("db.php");

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

if (!isset($_SESSION['staff'])) {
    header("Location: staff_login.php");
    exit();
}

$staffID   = $_SESSION['staff'];
$staffName = isset($_SESSION['staff_name']) ? $_SESSION['staff_name'] : $staffID;

// ── Auto-create lab_records if it doesn't exist ────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `lab_records` (
    `record_id`     INT AUTO_INCREMENT PRIMARY KEY,
    `student_name`  VARCHAR(100)  DEFAULT NULL,
    `student_id`    INT           DEFAULT NULL,
    `language`      VARCHAR(50)   DEFAULT NULL,
    `program_title` VARCHAR(100)  DEFAULT NULL,
    `source_code`   LONGTEXT      DEFAULT NULL,
    `output`        TEXT          DEFAULT NULL,
    `submitted_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// ── Auto-create coding_history if it doesn't exist ────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `coding_history` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `student_id`     INT           DEFAULT 1,
    `language`       VARCHAR(50)   DEFAULT NULL,
    `code`           LONGTEXT      DEFAULT NULL,
    `program_input`  TEXT          DEFAULT NULL,
    `program_output` TEXT          DEFAULT NULL,
    `output`         TEXT          DEFAULT NULL,
    `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Pending queries for badge
$pendingRes     = $conn->query("SELECT COUNT(*) as total FROM queries WHERE status='pending'");
$pendingQueries = ($pendingRes) ? $pendingRes->fetch_assoc()['total'] : 0;



// Student submissions (lab_records)
$submissionsRes   = $conn->query("SELECT COUNT(*) as total FROM lab_records WHERE student_name IS NOT NULL");
$totalSubmissions = ($submissionsRes) ? $submissionsRes->fetch_assoc()['total'] : 0;

// Recent 10 lab record submissions
$recentRecords = $conn->query(
    "SELECT student_name, language, program_title, output, submitted_at
     FROM lab_records
     WHERE student_name IS NOT NULL
     ORDER BY submitted_at DESC
     LIMIT 10"
);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coding Lab | ZEALHUB</title>
    <meta name="description" content="ZEALHUB Staff Coding Lab – Monitor student submissions and practice code yourself.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">

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

        .tab-bar { display: flex; gap: 10px; margin-bottom: 22px; }
        .tab-btn {
            background: var(--card-bg); border: 1px solid var(--border); color: var(--text-muted);
            border-radius: 12px; padding: 10px 22px; font-weight: 700; font-size: 14px;
            cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s;
        }
        .tab-btn.active, .tab-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

        .lab-header-banner {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 50%, #06b6d4 100%);
            border-radius: 20px; color: #fff; padding: 24px 30px;
            box-shadow: 0 10px 25px rgba(79,70,229,0.25);
            margin-bottom: 22px; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: space-between;
        }
        .lab-header-banner h1 { font-size: 24px; font-weight: 800; margin-bottom: 4px; }
        .lab-header-banner p  { font-size: 13px; opacity: 0.85; }
        .lang-badges { display: flex; gap: 8px; flex-wrap: wrap; }
        .lang-badge {
            background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px; padding: 3px 12px; font-size: 11px; font-weight: 700;
        }

        .lab-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 22px; }
        .stat-card-custom { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px; padding: 18px; display: flex; align-items: center; gap: 14px; transition: all 0.3s; box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
        .stat-card-custom:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .stat-icon-wrapper { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }

        .submissions-panel { background: var(--card-bg); border: 1px solid var(--border); border-radius: 20px; padding: 24px; }
        .submissions-panel h3 { font-size: 18px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th { background: rgba(99,102,241,0.07); padding: 12px 14px; text-align: left; font-weight: 700; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        tbody td { padding: 12px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tbody tr:hover { background: rgba(99,102,241,0.04); }
        .lang-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: rgba(99,102,241,0.12); color: var(--primary); }
        .output-preview { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-muted); font-family: 'Fira Code', monospace; font-size: 12px; }

        .ide-container {
            background: #1e1e2e; border-radius: 18px; border: 1px solid rgba(255,255,255,0.12);
            overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.3);
            display: flex; flex-direction: column; height: calc(100vh - 370px); min-height: 480px;
        }
        .ide-toolbar {
            background: #181825; padding: 10px 18px; border-bottom: 1px solid rgba(255,255,255,0.08);
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
        }
        .ide-toolbar-left, .ide-toolbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ide-select { background: #2a2a3c; color: #f1f5f9; border: 1px solid rgba(255,255,255,0.18); border-radius: 10px; padding: 7px 14px; font-weight: 600; font-size: 13px; outline: none; cursor: pointer; }
        .btn-run-code { background: linear-gradient(135deg,#10b981 0%,#059669 100%); color: white; font-weight: 700; border: none; border-radius: 10px; padding: 8px 18px; display: inline-flex; align-items: center; gap: 7px; cursor: pointer; box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
        .btn-run-code:hover { transform: scale(1.03); }
        .btn-ide-tool { background: rgba(255,255,255,0.07); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.14); border-radius: 10px; padding: 7px 13px; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-ide-tool:hover { background: rgba(255,255,255,0.16); color: white; }
        .autosave-badge { font-size: 11px; color: #94a3b8; display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.05); padding: 5px 11px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.09); }
        .pulse-dot { width: 7px; height: 7px; background: #10b981; border-radius: 50%; display: inline-block; }
        
        .ide-workspace { display: flex; flex: 1; overflow: hidden; }
        #monacoEditorContainer { flex: 1; height: 100%; min-height: 300px; }
        .ide-console-panel { width: 360px; background: #14141e; border-left: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; overflow: hidden; }
        .console-header { background: #1a1a27; padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,0.08); font-weight: 700; color: #cbd5e1; font-size: 12px; display: flex; align-items: center; justify-content: space-between; }
        .console-body { flex: 1; display: flex; flex-direction: column; padding: 10px; gap: 10px; overflow-y: auto; }
        .console-input-area label { font-size: 11px; color: #94a3b8; font-weight: 600; margin-bottom: 5px; display: block; }
        .console-input-area textarea { width: 100%; background: #0f0f17; border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: #e2e8f0; font-family: 'Fira Code',monospace; font-size: 12px; padding: 8px; resize: vertical; outline: none; min-height: 60px; }

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
            .ide-workspace { flex-direction: column; }
            .ide-console-panel { width: 100%; height: 250px; }
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

            .lab-header-banner {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
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
        <a href="staff_lab.php" class="active"><i class="fa-solid fa-flask"></i> <span>Coding Lab</span></a>
        <a href="staff_library.php"><i class="fa-solid fa-book-open-reader"></i> <span>Library</span></a>
        <a href="staff_logout.php" class="btn-logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span></a>
        </aside>

    <!-- MAIN CONTENT (Open/Pushed by default) -->
    <main class="main-content pushed" id="mainContent">

        <div class="lab-header-banner">
            <div>
                <h1><i class="fa-solid fa-flask" style="margin-right:10px;"></i>Staff Coding Lab</h1>
                <p>Practice coding or monitor student lab submissions with multi-language execution support.</p>
            </div>
            <div class="lang-badges">
                <span class="lang-badge">C</span><span class="lang-badge">C++</span>
                <span class="lang-badge">Java</span><span class="lang-badge">Python</span>
                <span class="lang-badge">PHP</span><span class="lang-badge">JS</span>
                <span class="lang-badge">HTML</span><span class="lang-badge">SQL</span>
            </div>
        </div>

        <div class="lab-stats">
            <div class="stat-card-custom">
                <div class="stat-icon-wrapper" style="background:rgba(99,102,241,0.12);color:#6366f1;">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <p style="font-size:11px;color:var(--text-muted);font-weight:600;">STUDENT SUBMISSIONS</p>
                    <h3 style="font-size:22px;font-weight:800;"><?= $totalSubmissions ?></h3>
                </div>
            </div>
            <div class="stat-card-custom">
                <div class="stat-icon-wrapper" style="background:rgba(16,185,129,0.12);color:#10b981;">
                    <i class="fa-solid fa-code"></i>
                </div>
                <div>
                    <p style="font-size:11px;color:var(--text-muted);font-weight:600;">LANGUAGES SUPPORTED</p>
                    <h3 style="font-size:22px;font-weight:800;">8</h3>
                </div>
            </div>
            <div class="stat-card-custom">
                <div class="stat-icon-wrapper" style="background:rgba(245,158,11,0.12);color:#f59e0b;">
                    <i class="fa-solid fa-bolt-lightning"></i>
                </div>
                <div>
                    <p style="font-size:11px;color:var(--text-muted);font-weight:600;">MULTI-COMPILER</p>
                    <h3 style="font-size:22px;font-weight:800;">READY</h3>
                </div>
            </div>
        </div>

        <div class="tab-bar">
            <button class="tab-btn active" id="tabIde" onclick="showTab('ide')">
                <i class="fa-solid fa-code"></i> IDE Practice
            </button>
            <button class="tab-btn" id="tabSubmissions" onclick="showTab('submissions')">
                <i class="fa-solid fa-users"></i> Student Submissions (<?= $totalSubmissions ?>)
            </button>
        </div>

        <!-- TAB: IDE -->
        <div id="tabContentIde">
            <div class="ide-container">
                <div class="ide-toolbar">
                    <div class="ide-toolbar-left">
                        <select class="ide-select" id="languageSelect">
                            <option value="python">🐍 Python</option>
                            <option value="c">⚙️ C</option>
                            <option value="cpp">⚙️ C++</option>
                            <option value="java">☕ Java</option>
                            <option value="php">🐘 PHP</option>
                            <option value="javascript">🌐 JavaScript</option>
                            <option value="html">🎨 HTML / CSS</option>
                            <option value="sql">🗄️ SQL</option>
                        </select>
                        <button class="btn-run-code" id="runCodeBtn">
                            <i class="fa-solid fa-play"></i> Run Code
                        </button>
                        <button class="btn-ide-tool" id="clearCodeBtn">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </button>
                    </div>
                    <div class="ide-toolbar-right">
                        <span class="autosave-badge">
                            <span class="pulse-dot"></span> Execution Engine Active
                        </span>
                    </div>
                </div>
                <div class="ide-workspace">
                    <div id="monacoEditorContainer"></div>
                    <div class="ide-console-panel">
                        <div class="console-header">
                            <span><i class="fa-solid fa-terminal" style="margin-right:6px;color:#38bdf8;"></i>Console</span>
                            <button class="btn-ide-tool" id="clearConsoleBtn" style="padding:3px 10px;font-size:11px;">
                                <i class="fa-solid fa-trash"></i> Clear
                            </button>
                        </div>
                        <div class="console-body">
                            <div class="console-input-area">
                                <label><i class="fa-solid fa-keyboard" style="margin-right:5px;"></i>Program Input (stdin)</label>
                                <textarea id="programInput" placeholder="Enter input for your program here..."></textarea>
                            </div>
                            <div style="flex:1; display:flex; flex-direction:column;">
                                <label style="font-size:11px;color:#94a3b8;font-weight:600;display:block;margin-bottom:5px;">
                                    <i class="fa-solid fa-square-terminal" style="margin-right:5px;color:#38bdf8;"></i>Output
                                </label>
                                <pre id="consoleOutput" style="flex:1;background:#09090e;border:1px solid rgba(255,255,255,0.08);border-radius:8px;color:#38bdf8;font-family:'Fira Code',monospace;font-size:12.5px;padding:10px;white-space:pre-wrap;word-break:break-word;overflow-y:auto;min-height:180px;">// Output will appear here after running code...</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: Submissions -->
        <div id="tabContentSubmissions" style="display:none;">
            <div class="submissions-panel">
                <h3><i class="fa-solid fa-list-check" style="color:var(--primary);"></i> Recent Student Lab Submissions</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Language</th>
                                <th>Program</th>
                                <th>Output Preview</th>
                                <th>Submitted At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentRecords && $recentRecords->num_rows > 0): $i = 1; ?>
                                <?php while ($rec = $recentRecords->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td><strong><?= htmlspecialchars($rec['student_name'] ?? '—') ?></strong></td>
                                        <td><span class="lang-pill"><?= strtoupper(htmlspecialchars($rec['language'] ?? '—')) ?></span></td>
                                        <td><?= htmlspecialchars($rec['program_title'] ?? '—') ?></td>
                                        <td class="output-preview"><?= htmlspecialchars(substr($rec['output'] ?? '', 0, 60)) ?></td>
                                        <td style="font-size:12px;color:var(--text-muted);"><?= date('d M Y, h:i A', strtotime($rec['submitted_at'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);">
                                    <i class="fa-solid fa-inbox fa-2x" style="margin-bottom:10px;display:block;"></i>
                                    No student submissions yet.
                                </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/loader.min.js"></script>
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

        // Tab Switcher
        function showTab(name) {
            document.getElementById('tabContentIde').style.display          = name === 'ide'          ? 'block' : 'none';
            document.getElementById('tabContentSubmissions').style.display  = name === 'submissions'  ? 'block' : 'none';
            document.getElementById('tabIde').classList.toggle('active',         name === 'ide');
            document.getElementById('tabSubmissions').classList.toggle('active', name === 'submissions');
        }

        // Monaco Editor & Execution Setup
        let editor;
        const sampleCodes = {
            python: 'print("Hello from ZEALHUB Coding Lab!")\nfor i in range(5):\n    print(f"Count: {i}")',
            c: '#include <stdio.h>\n\nint main() {\n    printf("Hello from C Execution!\\n");\n    return 0;\n}',
            cpp: '#include <iostream>\nusing namespace std;\n\nint main() {\n    cout << "Hello from C++ Execution!" << endl;\n    return 0;\n}',
            java: 'public class Main {\n    public static void main(String[] args) {\n        System.out.println("Hello from Java Execution!");\n    }\n}',
            php: '<?php\necho "Hello from PHP Execution!\\n";\nfor ($i = 1; $i <= 3; $i++) {\n    echo "Iteration: $i\\n";\n}',
            javascript: 'console.log("Hello from JavaScript Execution!");\nconst numbers = [1, 2, 3, 4, 5];\nconsole.log("Sum:", numbers.reduce((a, b) => a + b, 0));',
            html: '<!DOCTYPE html>\n<html>\n<head>\n<style>body{font-family:sans-serif;color:#38bdf8;padding:20px;}</style>\n</head>\n<body>\n<h1>ZEALHUB HTML Lab</h1>\n<p>HTML & CSS preview renders smoothly.</p>\n</body>\n</html>',
            sql: 'CREATE TABLE students (id INT, name TEXT);\nINSERT INTO students VALUES (1, "Alice"), (2, "Bob");\nSELECT * FROM students;'
        };

        const pistonLangs = {
            python: { language: 'python', version: '3.10.0' },
            c: { language: 'c', version: '10.2.0' },
            cpp: { language: 'cpp', version: '10.2.0' },
            java: { language: 'java', version: '15.0.2' },
            php: { language: 'php', version: '8.2.3' },
            javascript: { language: 'javascript', version: '18.15.0' },
            sql: { language: 'sqlite3', version: '3.36.0' }
        };

        require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs' } });
        require(['vs/editor/editor.main'], function () {
            editor = monaco.editor.create(document.getElementById('monacoEditorContainer'), {
                value: sampleCodes.python,
                language: 'python',
                theme: 'vs-dark',
                automaticLayout: true,
                fontSize: 14,
                minimap: { enabled: false }
            });
        });

        const langSelect = document.getElementById('languageSelect');
        langSelect.addEventListener('change', (e) => {
            const selected = e.target.value;
            const monacoLang = selected === 'cpp' || selected === 'c' ? 'cpp' : (selected === 'html' ? 'html' : selected);
            if (editor) {
                monaco.editor.setModelLanguage(editor.getModel(), monacoLang);
                editor.setValue(sampleCodes[selected] || '');
            }
        });

        document.getElementById('clearCodeBtn')?.addEventListener('click', () => {
            const selected = langSelect.value;
            if (editor) editor.setValue(sampleCodes[selected] || '');
        });

        document.getElementById('clearConsoleBtn')?.addEventListener('click', () => {
            document.getElementById('consoleOutput').innerText = '// Console cleared.';
        });

        // Execution Handler
        document.getElementById('runCodeBtn').addEventListener('click', async () => {
            const consoleOut = document.getElementById('consoleOutput');
            const code = editor ? editor.getValue() : '';
            const lang = langSelect.value;
            const stdin = document.getElementById('programInput').value;

            consoleOut.innerText = '⏳ Compiling & Running code...';

            if (lang === 'javascript') {
                try {
                    let logs = [];
                    const customConsole = { log: (...args) => logs.push(args.map(a => typeof a === 'object' ? JSON.stringify(a) : a).join(' ')) };
                    const runFn = new Function('console', code);
                    runFn(customConsole);
                    consoleOut.innerText = logs.length > 0 ? logs.join('\n') : 'Code executed successfully with no output.';
                } catch (err) {
                    consoleOut.innerText = 'Runtime Error:\n' + err.message;
                }
                return;
            }

            if (lang === 'html') {
                consoleOut.innerText = 'Rendering HTML output...\n\n' + code.replace(/<[^>]*>?/gm, '');
                return;
            }

            const config = pistonLangs[lang];
            if (!config) {
                consoleOut.innerText = 'Execution not supported for selected language.';
                return;
            }

            try {
                const res = await fetch('https://emkc.org/api/v2/piston/execute', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        language: config.language,
                        version: config.version,
                        files: [{ content: code }],
                        stdin: stdin
                    })
                });
                const data = await res.json();
                if (data.run) {
                    const output = data.run.output || data.run.stderr || 'Program executed with no output.';
                    consoleOut.innerText = output;
                } else {
                    consoleOut.innerText = 'Execution Error: Unable to retrieve compilation output.';
                }
            } catch (err) {
                consoleOut.innerText = 'Network/Execution Error: ' + err.message;
            }
        });
    
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