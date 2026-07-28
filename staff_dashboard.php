<?php
session_start();
include("db.php");

// Security Check
if (!isset($_SESSION['staff'])) {
    header("Location: staff_login.php");
    exit();
}

$staffID = $_SESSION['staff'];

// Use the session name if it exists, otherwise use the ID
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

// Fetch Counts for Stats
$paperCount = ($res = $conn->query("SELECT COUNT(*) as total FROM question_bank")) ? $res->fetch_assoc()['total'] : 0;
$materialCount = ($res = $conn->query("SELECT COUNT(*) as total FROM study_materials")) ? $res->fetch_assoc()['total'] : 0;
$pendingQueries = ($res = $conn->query("SELECT COUNT(*) as total FROM queries WHERE status='pending'")) ? $res->fetch_assoc()['total'] : 0;


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | ZEALHUB</title>
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

        .stats-grid,
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card,
        .action-btn {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 20px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: inherit;
        }

        .action-btn {
            flex-direction: column;
            text-align: center;
            padding: 25px;
        }

        .action-btn:hover {
            border-color: var(--primary);
            box-shadow: 0 0 20px var(--glow);
            transform: translateY(-5px);
        }

        .icon-box {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: var(--card-bg);
            padding: 25px;
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
            word-break: break-all;
        }

        .contact-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--glow);
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

            .stats-grid,
            .action-grid,
            .info-grid {
                grid-template-columns: 1fr;
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

    <!-- SIDEBAR (Open by Default via 'expanded' class, exact sequence preserved) -->
    <aside class="sidebar expanded" id="sidebar">
        <a href="staff_dashboard.php" class="active"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
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
        <div style="margin-bottom: 30px;">
            <h1 id="greeting">Welcome</h1>
            <p style="color: var(--text-muted);">Manage your academic resources and student interactions.</p>
        </div>

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon-box" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);"><i class="fa-solid fa-file-lines"></i></div>
                <div>
                    <p style="font-size: 11px; color: var(--text-muted);">Question banks uploaded</p>
                    <h2><?= $paperCount ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-box" style="background: rgba(16, 185, 129, 0.1); color: var(--success);"><i class="fa-solid fa-book"></i></div>
                <div>
                    <p style="font-size: 11px; color: var(--text-muted);">Study material uploaded</p>
                    <h2><?= $materialCount ?></h2>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon-box" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);"><i class="fa-solid fa-circle-question"></i></div>
                <div>
                    <p style="font-size: 11px; color: var(--text-muted);">Pending queries</p>
                    <h2 id="statPending"><?= $pendingQueries ?></h2>
                </div>
            </div>
        </div>

        <h3 style="margin-bottom: 15px;">Quick Management</h3>
        <div class="action-grid">
            <a href="staff_studymaterial.php" class="action-btn">
                <i class="fa-solid fa-upload" style="font-size: 28px; color: var(--primary); margin-bottom: 8px;"></i>
                <span>Study Materials</span>
            </a>
            <a href="staff_questionbank.php" class="action-btn">
                <i class="fa-solid fa-folder-plus" style="font-size: 28px; color: var(--primary); margin-bottom: 8px;"></i>
                <span>Question Bank</span>
            </a>
            <a href="staff_queries.php" class="action-btn">
                <i class="fa-solid fa-reply-all" style="font-size: 28px; color: var(--primary); margin-bottom: 8px;"></i>
                <span>Resolve Queries</span>
            </a>
            <a href="staff_alert.php" class="action-btn">
                <i class="fa-solid fa-bullhorn" style="font-size: 28px; color: var(--primary); margin-bottom: 8px;"></i>
                <span>Post Announcement</span>
            </a>
            </div>

        <?php
        $smUserRole = 'staff';
        $smInitials = strtoupper(substr(preg_replace('/\s+/', ' ', trim($staffName)), 0, 2)) ?: 'SF';
        $smUserName = $staffName;
        
        ?>

        <!-- INFO GRID -->
        <div class="info-grid">
            <!-- About Section -->
            <div class="info-card">
                <h3 style="color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-graduation-cap"></i> About ZEALHUB
                </h3>
                <p style="font-size: 13.5px; line-height: 1.6; color: var(--text-muted);">
                    <strong>ZEALHUB Academic Portal</strong> is designed to streamline academic content management, enable seamless distribution of course notes, question banks, and facilitate direct doubt resolution between faculty and students.
                </p>
            </div>

            <!-- Help Section -->
            <div class="info-card">
                <h3 style="color: var(--primary); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-headset"></i> Help & Support
                </h3>
                <p style="font-size: 13px; line-height: 1.5; color: var(--text-muted); margin-bottom: 12px;">
                    For system administrator assistance or portal queries, contact us directly:
                </p>
                
                <a href="mailto:shayamkanthale@gmail.com?subject=Faculty%20Support%20-%20ZEALHUB" class="contact-link" style="background: rgba(99, 102, 241, 0.08); color: var(--primary); margin-bottom: 10px;">
                    <i class="fa-solid fa-envelope"></i> shayamkanthale@gmail.com
                </a>

                <a href="tel:8551082199" class="contact-link" style="background: rgba(16, 185, 129, 0.08); color: var(--success);">
                    <i class="fa-solid fa-phone"></i> +91 8551082199
                </a>
            </div>

            <!-- Recent Alerts Section -->
            <div class="info-card">
                <h3 style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-clock-rotate-left" style="color:var(--primary)"></i> Recent Announcements
                </h3>
                <div style="max-height: 140px; overflow-y: auto;">
                    <?php if ($notifications && $notifications->num_rows > 0): ?>
                        <?php while ($n = $notifications->fetch_assoc()): ?>
                            <div style="padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 13px;">
                                <strong style="display: block; color: var(--text-main);"><?= htmlspecialchars($n['title']) ?></strong>
                                <small style="color: var(--text-muted); font-size: 10px;"><?= date('M d, H:i', strtotime($n['created_at'])) ?></small>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="font-size: 12px; color: var(--text-muted);">No announcements sent yet.</p>
                    <?php endif; ?>
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

        // Greeting
        const hour = new Date().getHours();
        const staff = "<?= htmlspecialchars($staffName) ?>";
        let greet = (hour < 12) ? "Good Morning" : (hour < 17) ? "Good Afternoon" : "Good Evening";
        const greetEl = document.getElementById('greeting');
        if (greetEl) greetEl.innerText = `${greet}, ${staff}! 👨‍🏫`;

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

        // Real-time Poll Queries
        function pollQueries() {
            fetch('api/queries.php?action=poll_pending_queries_count')
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        const badge = document.getElementById('queryBadge');
                        const statText = document.getElementById('statPending');
                        if (data.pending_count > 0) {
                            if (badge) badge.innerText = data.pending_count;
                            if (statText) statText.innerText = data.pending_count;
                        }
                    }
                }).catch(err => console.log(err));
        }
        setInterval(pollQueries, 10000);
    </script>
</body>
</html>