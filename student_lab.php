<?php
session_start();
include("db.php");

// 1. Authentication Check
if (!isset($_SESSION['student'])) {
    header("Location: student_login.php");
    exit();
}

$id = $_SESSION['student'];
$studentID = $id;
$currentPage = basename($_SERVER['PHP_SELF']);

function isActive($pageName, $currentPage) {
    return ($pageName === $currentPage) ? 'active' : '';
}

// 2. Fetch Student Data
$stmt = $conn->prepare("SELECT name FROM student WHERE id = ?");
$stmt->bind_param("s", $id);
$stmt->execute();
$result = $stmt->get_result();
$studentName = ($row = $result->fetch_assoc()) ? $row['name'] : "Student";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="lab_custom.css">

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

        .header-right { display: flex; align-items: center; gap: 14px; position: relative; }

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

        .sidebar.collapsed { width: 80px; padding: 20px 10px; }

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

        .sidebar.collapsed a { justify-content: center; padding: 12px; }
        .sidebar.collapsed a span { display: none; }

        .sidebar a:hover, .sidebar a.active {
            background: var(--primary);
            color: #ffffff !important;
            box-shadow: 0 4px 14px var(--glow);
        }

        .sidebar a.btn-logout { margin-top: auto; background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .sidebar a.btn-logout:hover { background: var(--danger); color: white !important; }

        /* MAIN CONTENT - MARGIN LEFT MATCHES OPEN SIDEBAR */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 30px;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.3s ease;
        }

        .main-content.collapsed { margin-left: 80px; }

        .dashboard-header-flex {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 15px;
        }

        .greeting-h1 { font-size: 26px; font-weight: 800; margin: 0; }
        .sub-text { color: var(--text-muted); font-size: 14px; margin-top: 4px; }

        .date-display {
            font-size: 13px; font-weight: 700; color: var(--text-muted); background: var(--card-bg); padding: 10px 18px; border-radius: 12px; border: 1px solid var(--border); display: flex; align-items: center; gap: 8px;
        }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }

        .stat-card {
            background: var(--card-bg); border: 1px solid var(--border); border-radius: 18px; padding: 20px; display: flex; align-items: center; gap: 16px;
        }

        .stat-icon { width: 50px; height: 50px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .bg-blue { background: rgba(67, 97, 238, 0.1); color: var(--primary); }
        .bg-green { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .bg-purple { background: rgba(147, 51, 234, 0.1); color: #9333ea; }
        .stat-label { font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; }
        .stat-value { font-size: 22px; font-weight: 800; margin: 0; }

        .tab-bar { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-btn { padding: 12px 20px; border-radius: 12px; border: 1px solid var(--border); background: var(--card-bg); color: var(--text-main); font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 13.5px; }
        .tab-btn.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 14px var(--glow); }

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
        <!-- Dashboard Header Flex -->
        <div class="dashboard-header-flex">
            <div>
                <h1 class="greeting-h1"><i class="fa-solid fa-flask" style="color:var(--primary); margin-right:8px;"></i>Coding Lab</h1>
                <p class="sub-text">Practice coding, test logic, or monitor student lab submissions.</p>
            </div>
            <div class="date-display">
                <i class="fa-regular fa-calendar-check"></i>
                <span id="date-now"></span>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-blue"><i class="fa-solid fa-users"></i></div>
                <div>
                    <p class="stat-label">Submissions</p>
                    <h2 class="stat-value"><?= $totalSubmissions ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-green"><i class="fa-solid fa-code"></i></div>
                <div>
                    <p class="stat-label">Languages</p>
                    <h2 class="stat-value">8</h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-purple"><i class="fa-solid fa-bolt-lightning"></i></div>
                <div>
                    <p class="stat-label">Live Execution</p>
                    <h2 class="stat-value">Active</h2>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
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
                        <select class="ide-select" id="languageSelect" aria-label="Select Language">
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
                        <button class="btn-ide-tool" id="saveCodeBtn">
                            <i class="fa-solid fa-floppy-disk"></i> Save
                        </button>
                        <button class="btn-ide-tool" id="historyModalBtn">
                            <i class="fa-solid fa-clock-rotate-left"></i> History
                        </button>
                    </div>
                    <div class="ide-toolbar-right">
                        <span class="autosave-badge" id="saveStatusBadge">
                            <span class="pulse-dot"></span> Auto-save active
                        </span>
                    </div>
                </div>
                <div class="ide-workspace">
                    <div id="monacoEditorContainer"></div>
                    <div class="ide-console-panel">
                        <div class="console-header">
                            <span><i class="fa-solid fa-terminal" style="margin-right:6px;color:#38bdf8;"></i>Console</span>
                            <button class="btn-ide-tool" id="clearConsoleBtn" style="padding:3px 10px;font-size:11px;">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                        <div class="console-body">
                            <div class="console-input-area">
                                <label><i class="fa-solid fa-keyboard" style="margin-right:5px;"></i>Program Input (stdin)</label>
                                <textarea id="programInput" placeholder="Enter input for your program here..."></textarea>
                            </div>
                            <div>
                                <label style="font-size:11px;color:#94a3b8;font-weight:600;display:block;margin-bottom:5px;">
                                    <i class="fa-solid fa-square-terminal" style="margin-right:5px;color:#38bdf8;"></i>Output
                                </label>
                                <pre id="consoleOutput" style="flex:1;background:#09090e;border:1px solid rgba(255,255,255,0.08);border-radius:8px;color:#38bdf8;font-family:'Fira Code',monospace;font-size:12.5px;padding:10px;white-space:pre-wrap;word-break:break-word;overflow-y:auto;min-height:200px;">// Output will appear here after running code...</pre>
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
                                <tr>
                                    <td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);">
                                        <i class="fa-solid fa-inbox fa-2x" style="margin-bottom:10px;display:block;"></i>
                                        No student submissions yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                <a href="student_lab.php">Coding Lab</a>
                <a href="student_raise_queries.php">Support</a>
            </div>
        </footer>
    </main>

    <!-- HISTORY MODAL -->
    <div class="hist-modal-overlay" id="histModalOverlay">
        <div class="hist-modal">
            <div class="hist-modal-header">
                <h3><i class="fa-solid fa-clock-rotate-left" style="margin-right:8px;"></i>My Coding History</h3>
                <button class="hist-close-btn" id="histCloseBtn">&#x2715;</button>
            </div>
            <div id="historyListContainer">
                <p style="text-align:center;color:#94a3b8;padding:20px;">Loading history...</p>
            </div>
        </div>
    </div>

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

    <!-- Monaco Editor Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.45.0/min/vs/loader.min.js"></script>
    <script src="lab_ide.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const dateElement = document.getElementById('date-now');
            if (dateElement) dateElement.innerText = new Date().toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        });

        function showTab(name) {
            document.getElementById('tabContentIde').style.display = name === 'ide' ? 'block' : 'none';
            document.getElementById('tabContentSubmissions').style.display = name === 'submissions' ? 'block' : 'none';
            document.getElementById('tabIde').classList.toggle('active', name === 'ide');
            document.getElementById('tabSubmissions').classList.toggle('active', name === 'submissions');
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

        const histModalOverlay = document.getElementById('histModalOverlay');
        const histCloseBtn = document.getElementById('histCloseBtn');
        if (histCloseBtn) histCloseBtn.addEventListener('click', () => histModalOverlay.classList.remove('active'));
        histModalOverlay?.addEventListener('click', (e) => {
            if (e.target === histModalOverlay) histModalOverlay.classList.remove('active');
        });

        function openCodingHistoryModal() {
            const listContainer = document.getElementById('historyListContainer');
            if (!listContainer) return;
            listContainer.innerHTML = '<p style="text-align:center;color:#94a3b8;padding:20px;"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>Loading...</p>';
            histModalOverlay.classList.add('active');

            fetch('history.php?action=list')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.data.length > 0) {
                        let html = '';
                        data.data.forEach(item => {
                            html += `<div class="history-item-card" onclick="loadHistoryItem(${item.id})">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <span style="background:#6366f1;color:white;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">${item.language}</span>
                            <small style="color:#94a3b8;font-size:11px;"><i class="fa-regular fa-clock" style="margin-right:4px;"></i>${item.date}</small>
                        </div>
                        <code style="color:#38bdf8;font-size:12px;">${escapeHtml(item.snippet)}</code>
                    </div>`;
                        });
                        listContainer.innerHTML = html;
                    } else {
                        listContainer.innerHTML = '<div style="text-align:center;padding:30px;color:#94a3b8;"><i class="fa-solid fa-box-open fa-2x" style="margin-bottom:10px;display:block;"></i>No history found.</div>';
                    }
                })
                .catch(() => {
                    listContainer.innerHTML = '<p style="color:#ef4444;text-align:center;padding:20px;">Failed to load history.</p>';
                });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    
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