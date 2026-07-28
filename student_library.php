<?php
session_start();
include('db.php');

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['student'])) {
    header('Location: student_login.php');
    exit();
}

$id = $_SESSION['student'];
$studentID = $id;
$currentPage = basename($_SERVER['PHP_SELF']);

function isActive($pageName, $currentPage) {
    return ($pageName === $currentPage) ? 'active' : '';
}

// Fetch Student Name
$query = "SELECT name FROM student WHERE id='$id'";
$result = mysqli_query($conn, $query);
$studentName = ($result && $row = mysqli_fetch_assoc($result)) ? $row['name'] : "Student";
$initials = strtoupper(substr(preg_replace('/\s+/', ' ', trim($studentName)), 0, 2)) ?: 'ST';

// --- LIBRARY LOGIC ---
$rater_id = isset($_SESSION['student']) ? 'student_' . $_SESSION['student'] : (isset($_SESSION['staff']) ? 'staff_' . $_SESSION['staff'] : (isset($_SESSION['staff_id']) ? 'staff_' . $_SESSION['staff_id'] : 'guest'));
$raterEsc = mysqli_real_escape_string($conn, $rater_id);

// Fetch favorites
$favIds = [];
$favRes = mysqli_query($conn, "SELECT resource_id FROM library_favorites WHERE rater_id='$raterEsc'");
if ($favRes) {
    while ($favRow = mysqli_fetch_assoc($favRes)) {
        $favIds[] = $favRow['resource_id'];
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$subject_filter = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$show_favs = isset($_GET['favorites']) && $_GET['favorites'] == '1';

// Trending Resources
$trendingResult = mysqli_query($conn, "SELECT r.*, s.subject_name,
    (SELECT AVG(rating) FROM library_ratings WHERE resource_id = r.id) as avg_rating,
    (SELECT COUNT(*) FROM library_ratings WHERE resource_id = r.id) as rating_count
    FROM library_resources r
    LEFT JOIN library_subjects s ON r.subject_id = s.subject_id
    WHERE r.status = 'approved'
    ORDER BY r.downloads_count DESC, r.views_count DESC
    LIMIT 4");

// Main Gallery SQL
$sql = "SELECT r.*, s.subject_name,
    (SELECT AVG(rating) FROM library_ratings WHERE resource_id = r.id) as avg_rating,
    (SELECT COUNT(*) FROM library_ratings WHERE resource_id = r.id) as rating_count
    FROM library_resources r
    LEFT JOIN library_subjects s ON r.subject_id = s.subject_id
    WHERE r.status = 'approved'";

if ($search !== '') {
    $searchEsc = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (r.title LIKE '%$searchEsc%' OR r.description LIKE '%$searchEsc%')";
}
if ($subject_filter > 0) {
    $sql .= " AND r.subject_id = $subject_filter";
}
if ($show_favs) {
    if (count($favIds) > 0) {
        $sql .= " AND r.id IN (" . implode(',', array_map('intval', $favIds)) . ")";
    } else {
        $sql .= " AND 1=0";
    }
}
$sql .= " ORDER BY r.created_at DESC";

$result = mysqli_query($conn, $sql);
$totalInView = $result ? mysqli_num_rows($result) : 0;
$subjectsResult = mysqli_query($conn, "SELECT * FROM library_subjects ORDER BY subject_name");

// Header Stats
$notifCountQuery = "SELECT COUNT(*) as total FROM announcements a WHERE a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE student_id = '$id')";
$notifCount = ($res = $conn->query($notifCountQuery)) ? $res->fetch_assoc()['total'] : 0;
$notifications = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
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
    <title>Digital Library | ZEALHUB</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

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
            --star-yellow: #f59e0b;
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

        .hero-banner {
            background: linear-gradient(135deg, var(--primary) 0%, #3a0ca3 100%);
            color: white; border-radius: 24px; padding: 30px; margin-bottom: 30px; box-shadow: 0 10px 25px var(--glow);
        }

        .hero-banner h1 { font-size: 26px; font-weight: 800; margin-bottom: 6px; }

        .search-bar-row { display: flex; gap: 12px; margin-top: 20px; }
        .search-bar-row input {
            flex: 1; padding: 12px 20px; border-radius: 14px; border: none; font-size: 14px; outline: none; background: rgba(255, 255, 255, 0.95); color: #0f172a;
        }

        .search-bar-row button {
            padding: 12px 24px; background: #0f172a; color: white; border: none; border-radius: 14px; font-weight: 700; cursor: pointer;
        }

        .section-title { font-size: 18px; font-weight: 800; margin: 25px 0 15px; display: flex; align-items: center; gap: 10px; }

        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }

        .resource-card {
            background: var(--card-bg); border: 1px solid var(--border); border-radius: 18px; overflow: hidden; display: flex; flex-direction: column; transition: all 0.25s ease;
        }

        .resource-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px var(--glow); border-color: var(--primary); }

        .preview-area {
            height: 140px; background: var(--bg); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;
        }

        .fav-btn {
            position: absolute; top: 10px; right: 10px; background: white; border: 1px solid var(--border); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-muted); transition: all 0.2s;
        }

        .fav-btn.favorited { color: var(--danger); border-color: var(--danger); }

        .card-body { padding: 16px; flex: 1; display: flex; flex-direction: column; }
        .subject-tag { font-size: 10px; font-weight: 800; text-transform: uppercase; color: var(--primary); background: rgba(67, 97, 238, 0.1); padding: 3px 8px; border-radius: 6px; display: inline-block; margin-bottom: 8px; }
        .card-body h3 { font-size: 15px; font-weight: 700; margin-bottom: 6px; color: var(--text-main); }
        .card-body p { font-size: 12px; color: var(--text-muted); line-height: 1.4; margin-bottom: 12px; flex: 1; }

        .card-footer { padding: 12px 16px; background: var(--bg); border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .stats-text { font-size: 11px; color: var(--text-muted); display: flex; gap: 10px; }
        .view-link { text-decoration: none; padding: 6px 14px; background: var(--primary); color: white; border-radius: 8px; font-size: 12px; font-weight: 700; }

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
            .search-bar-row { flex-direction: column; }
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
        <!-- HERO BANNER -->
        <div class="hero-banner">
            <h1>Digital Reference Library 🏛️</h1>
            <p>Explore approved reference books, e-books, research documents, and subject guides.</p>
            <form method="GET" class="search-bar-row">
                <input type="text" name="search" placeholder="Search reference books, authors, or topics..." value="<?= htmlspecialchars($search); ?>">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            </form>
        </div>

        <div class="section-title"><i class="fa-solid fa-book-open" style="color:var(--primary);"></i> Reference Collection (<?= $totalInView ?>)</div>
        <div class="grid-container">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php 
                    $isFav = in_array($row['id'], $favIds);
                    $avg = round($row['avg_rating'] ?? 0, 1);
                    ?>
                    <div class="resource-card">
                        <div class="preview-area">
                            <canvas class="pdf-thumb" data-pdf="<?= htmlspecialchars($row['file_path']); ?>"></canvas>
                            <button class="fav-btn <?= $isFav ? 'favorited' : ''; ?>" data-id="<?= $row['id']; ?>" onclick="toggleFavorite(this)">
                                <i class="<?= $isFav ? 'fa-solid' : 'fa-regular'; ?> fa-heart"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <span class="subject-tag"><?= htmlspecialchars($row['subject_name'] ?? 'General'); ?></span>
                            <h3><?= htmlspecialchars($row['title']); ?></h3>
                            <p><?= htmlspecialchars(substr($row['description'] ?? '', 0, 75)); ?>...</p>
                        </div>
                        <div class="card-footer">
                            <span class="stats-text">
                                <span><i class="fa-regular fa-eye"></i> <?= $row['views_count']; ?></span>
                                <span><i class="fa-solid fa-download"></i> <?= $row['downloads_count']; ?></span>
                            </span>
                            <a href="<?= htmlspecialchars($row['file_path']); ?>" class="view-link" target="_blank">View PDF</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 60px; color: var(--text-muted); background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border);">
                    <i class="fa-solid fa-book-bookmark" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px; display: block;"></i>
                    No library resources available at the moment.
                </div>
            <?php endif; ?>
        </div>

        <!-- FOOTER -->
        <footer class="footer">
            <div>
                © <?= date('Y') ?> <strong>ZEALHUB Academic Portal</strong>. All rights reserved.
            </div>
            <div style="display: flex; gap: 15px;">
                <a href="student_dashboard.php">Dashboard</a>
                <a href="student_library.php">Library</a>
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

        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            document.querySelectorAll('.pdf-thumb').forEach(canvas => {
                const url = canvas.getAttribute('data-pdf');
                if (url) {
                    pdfjsLib.getDocument(url).promise.then(pdf => {
                        pdf.getPage(1).then(page => {
                            const viewport = page.getViewport({ scale: 0.35 });
                            canvas.width = viewport.width;
                            canvas.height = viewport.height;
                            page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport });
                        });
                    }).catch(err => console.log('Thumbnail load:', err));
                }
            });
        }

        function toggleFavorite(btn) {
            const resourceId = btn.getAttribute('data-id');
            fetch('library_only/favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'resource_id=' + resourceId
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.classList.toggle('favorited', data.favorited);
                    const icon = btn.querySelector('i');
                    if (data.favorited) {
                        icon.className = 'fa-solid fa-heart';
                    } else {
                        icon.className = 'fa-regular fa-heart';
                    }
                }
            });
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