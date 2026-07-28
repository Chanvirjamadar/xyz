<?php
session_start();
include("db.php");

// Security Check
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

// Fetch pending queries count for header badge
$pendingQueries = ($res = $conn->query("SELECT COUNT(*) as total FROM queries WHERE status='pending'")) ? $res->fetch_assoc()['total'] : 0;



// Library Data Fetching
$rater_id = isset($_SESSION['student']) ? 'student_' . $_SESSION['student'] : (isset($_SESSION['staff']) ? 'staff_' . $_SESSION['staff'] : (isset($_SESSION['staff_id']) ? 'staff_' . $_SESSION['staff_id'] : 'guest'));
$raterEsc = mysqli_real_escape_string($conn, $rater_id);

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

$trendingResult = mysqli_query($conn, "SELECT r.*, s.subject_name,
    (SELECT AVG(rating) FROM library_ratings WHERE resource_id = r.id) as avg_rating,
    (SELECT COUNT(*) FROM library_ratings WHERE resource_id = r.id) as rating_count
    FROM library_resources r
    LEFT JOIN library_subjects s ON r.subject_id = s.subject_id
    WHERE r.status = 'approved'
    ORDER BY r.downloads_count DESC, r.views_count DESC
    LIMIT 3");

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
$subjectsResult = mysqli_query($conn, "SELECT * FROM library_subjects ORDER BY subject_name");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Virtual Library | ZEALHUB</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

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

        /* Library Styles */
        .container { max-width: 100%; margin: 0 auto; }
        h2.section-title { font-size: 18px; color: var(--text-main); margin: 25px 0 12px; font-weight: 800; }
        
        .filters { 
            background: var(--card-bg); 
            border: 1px solid var(--border); 
            padding: 15px 20px; 
            border-radius: 16px; 
            margin-bottom: 25px; 
            display: flex; 
            gap: 12px; 
            flex-wrap: wrap; 
            align-items: center; 
        }

        .view-tabs { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .tab-btn { padding: 10px 20px; border-radius: 12px; text-decoration: none; font-size: 13px; font-weight: 700; background: var(--card-bg); border: 1px solid var(--border); color: var(--text-main); }
        .tab-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

        .filters input[type=text] { flex: 1; min-width: 220px; padding: 10px 15px; border: 1px solid var(--border); border-radius: 10px; background: var(--input-bg); color: var(--text-main); outline: none; font-size: 13px; }
        .filters select { padding: 10px 15px; border: 1px solid var(--border); border-radius: 10px; background: var(--input-bg); color: var(--text-main); outline: none; font-size: 13px; cursor: pointer; }
        .filters button { padding: 10px 20px; border: none; border-radius: 10px; background: var(--primary); color: white; cursor: pointer; font-weight: 700; font-size: 13px; }

        .trending-row { display: flex; gap: 20px; overflow-x: auto; padding-bottom: 15px; }
        .trending-row .card { min-width: 260px; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
        
        .card { 
            background: var(--card-bg); 
            border: 1px solid var(--border); 
            border-radius: 20px; 
            padding: 20px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.02); 
            position: relative; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
        }

        .card .thumb-wrap { width: 100%; height: 160px; background: var(--input-bg); border-radius: 12px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:12px; border: 1px solid var(--border); }
        .card canvas.pdf-thumb { max-width: 100%; max-height: 100%; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
        
        .fav-btn { position:absolute; top:15px; right:15px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 50%; width:36px; height:36px; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; color: var(--text-muted); }
        .fav-btn.favorited { color: #e63946; border-color:#e63946; }

        .card h3 { margin: 0 0 8px 0; font-size: 16px; color: var(--text-main); font-weight: 700; }
        .card p { font-size: 13px; color: var(--text-muted); margin: 5px 0; line-height: 1.4; }
        .lib-badge { display: inline-block; background: rgba(99, 102, 241, 0.1); color: var(--primary); font-size: 10px; font-weight: 800; padding: 4px 10px; border-radius: 8px; margin-bottom: 10px; width: fit-content; text-transform: uppercase; }
        .stars { color: #f5a623; font-size: 13px; }

        .card a.view-link { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 15px; background: var(--primary); color: white; text-decoration: none; border-radius: 10px; font-size: 12px; font-weight: 700; }
        .card a.view-link:hover { opacity: 0.9; }

        .empty { text-align: center; color: var(--text-muted); padding: 50px; grid-column: 1 / -1; font-weight: 600; }

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

        @media (max-width: 992px) {
            .main-content.pushed { margin-left: 80px; }
        }

        @media (max-width: 768px) {
            .header { padding: 0 15px; }
            .logo span { display: none; }
            .sidebar { transform: translateX(-100%); width: 260px !important; box-shadow: 10px 0 30px rgba(0,0,0,0.15); align-items: flex-start; padding-left: 20px; }
            .sidebar a { width: 90%; justify-content: flex-start; padding-left: 15px; }
            .sidebar a span { display: inline; }
            .sidebar.expanded { transform: translateX(0); }
            .main-content, .main-content.pushed { margin-left: 0 !important; padding: 20px 15px; }
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

            <a href="staff_profile.php" style="display: flex; align-items: center; gap: 12px; padding: 6px 15px; border-radius: 12px; border: 1px solid var(--border); background: var(--card-bg); text-decoration: none; color: inherit;">
                <div style="text-align: right;">
                    <p style="font-size: 11px; font-weight: 800;"><?= htmlspecialchars($staffName) ?></p>
                    <p style="font-size: 9px; color: var(--text-muted);">FACULTY</p>
                </div>
                <div style="width: 32px; height: 32px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px;">SF</div>
            </a>
        </div>
    </header>

    <!-- SIDEBAR (Identical Dashboard Sequence & Open by Default) -->
    <aside class="sidebar expanded" id="sidebar">
        <a href="staff_dashboard.php"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
        <a href="staff_studymaterial.php"><i class="fa-solid fa-cloud-arrow-up"></i> <span>Upload Materials</span></a>
        <a href="staff_questionbank.php"><i class="fa-solid fa-file-circle-plus"></i> <span>Question Bank</span></a>
        <a href="staff_syllabus.php"><i class="fa-solid fa-scroll"></i> <span>Syllabus</span></a>
        <a href="staff_queries.php"><i class="fa-solid fa-clipboard-question"></i> <span>Student Queries</span></a>
        <a href="staff_alert.php"><i class="fa-solid fa-bell"></i> <span>Announcements</span></a>
        <a href="staff_lab.php"><i class="fa-solid fa-flask"></i> <span>Coding Lab</span></a>
        <a href="staff_library.php" class="active"><i class="fa-solid fa-book-open-reader"></i> <span>Library</span></a>
        <a href="staff_logout.php" class="btn-logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span></a>
        </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content pushed" id="mainContent">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <div>
                    <h1 style="font-size: 28px; font-weight: 800;">📚 Virtual Library</h1>
                    <p style="color: var(--text-muted);">Browse, upload, and read digital academic resources.</p>
                </div>
            </div>

            <!-- View Tabs -->
            <div class="view-tabs">
                <a href="staff_library.php" class="tab-btn <?php echo !$show_favs ? 'active' : ''; ?>">📖 All Resources</a>
                <a href="?favorites=1" class="tab-btn <?php echo $show_favs ? 'active' : ''; ?>">❤ My Favorites (<?php echo count($favIds); ?>)</a>
                <a href="library_only/upload.php" class="tab-btn" style="background: var(--primary); color: white; margin-left: auto;"><i class="fa-solid fa-plus"></i> Upload New Resource</a>
            </div>

            <!-- Trending Section -->
            <?php if (!$show_favs && mysqli_num_rows($trendingResult) > 0): ?>
                <h2 class="section-title">🔥 Trending This Week</h2>
                <div class="trending-row">
                    <?php while ($t = mysqli_fetch_assoc($trendingResult)): ?>
                        <div class="card">
                            <div>
                                <div class="thumb-wrap"><canvas class="pdf-thumb" data-pdf="library_only/download.php?id=<?php echo $t['id']; ?>&preview=1"></canvas></div>
                                <span class="lib-badge"><?php echo htmlspecialchars($t['subject_name'] ?? 'General'); ?></span>
                                <h3><?php echo htmlspecialchars($t['title']); ?></h3>
                                <p>⬇ <?php echo $t['downloads_count']; ?> downloads · 👁 <?php echo $t['views_count']; ?> views</p>
                            </div>
                            <div style="display: flex; gap: 10px; margin-top: 15px;">
                                <a href="library_only/view.php?id=<?php echo $t['id']; ?>" class="view-link" style="flex:1;">View / Download</a>
                                <a href="library_only/delete.php?id=<?php echo $t['id']; ?>" onclick="return confirm('Are you sure you want to delete this resource?');" class="view-link" style="background: var(--danger);"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

            <!-- Search & Filters -->
            <form class="filters" method="GET">
                <?php if ($show_favs): ?><input type="hidden" name="favorites" value="1"><?php endif; ?>
                <input type="text" name="search" placeholder="Search by title or description..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="subject_id">
                    <option value="0">All Subjects</option>
                    <?php mysqli_data_seek($subjectsResult, 0); while ($subj = mysqli_fetch_assoc($subjectsResult)): ?>
                        <option value="<?php echo $subj['subject_id']; ?>" <?php echo ($subject_filter == $subj['subject_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subj['subject_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            </form>

            <!-- Resources Grid -->
            <h2 class="section-title"><?php echo $show_favs ? "❤ Favorited Resources" : "📂 All Library Resources"; ?></h2>
            <div class="grid">
                <?php if (mysqli_num_rows($result) === 0): ?>
                    <div class="empty"><?php echo $show_favs ? "You haven't favorited anything yet." : "No resources found matching your search."; ?></div>
                <?php else: ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): 
                        $isFav = in_array($row['id'], $favIds);
                        $avg = $row['avg_rating'] ? round($row['avg_rating'], 1) : 0;
                        $cnt = $row['rating_count'];
                    ?>
                        <div class="card">
                            <button class="fav-btn <?php echo $isFav ? 'favorited' : ''; ?>" data-id="<?php echo $row['id']; ?>" onclick="toggleFavorite(this)"><?php echo $isFav ? '♥' : '♡'; ?></button>
                            <div>
                                <div class="thumb-wrap"><canvas class="pdf-thumb" data-pdf="library_only/download.php?id=<?php echo $row['id']; ?>&preview=1"></canvas></div>
                                <span class="lib-badge"><?php echo htmlspecialchars($row['subject_name'] ?? 'General'); ?></span>
                                <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                                <p><?php echo htmlspecialchars(substr($row['description'], 0, 80)); ?>...</p>
                                <p class="stars"><?php echo $avg > 0 ? str_repeat('★', round($avg)) . str_repeat('☆', 5-round($avg)) . " ($avg, $cnt)" : 'No ratings yet'; ?></p>
                                <p>👁 <?php echo $row['views_count']; ?> views · ⬇ <?php echo $row['downloads_count']; ?> downloads</p>
                            </div>
                            <div style="display:flex; gap:8px; margin-top:15px;">
                                <a href="library_only/view.php?id=<?php echo $row['id']; ?>" class="view-link" style="flex:1;">View / Download</a> 
                                <a href="library_only/delete.php?id=<?php echo $row['id']; ?>" class="view-link" onclick="return confirm('Are you sure you want to delete this resource?')" style="background: var(--danger);"><i class="fa-solid fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
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
        const menuBtn = document.getElementById('menuBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        if (menuBtn && sidebar && mainContent) {
            menuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('expanded');
                mainContent.classList.toggle('pushed');
            });
        }

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
            const selected = themes[key] || themes.sunset;
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

        const savedTheme = localStorage.getItem('user-theme') || 'light';
        applyTheme(savedTheme);

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        document.querySelectorAll('.pdf-thumb').forEach(canvas => {
            const url = canvas.getAttribute('data-pdf');
            pdfjsLib.getDocument(url).promise.then(pdf => {
                pdf.getPage(1).then(page => {
                    const viewport = page.getViewport({ scale: 0.4 });
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport });
                });
            }).catch(err => console.log('Thumbnail info:', err));
        });

        function toggleFavorite(btn) {
            fetch('library_only/favorite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'resource_id=' + btn.getAttribute('data-id')
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    btn.textContent = data.favorited ? '♥' : '♡';
                    btn.classList.toggle('favorited', data.favorited);
                }
            });
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