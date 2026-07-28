<?php
session_start(); require_once '../db.php';



$rater_id = isset($_SESSION['student']) ? 'student_' . $_SESSION['student'] : (isset($_SESSION['staff']) ? 'staff_' . $_SESSION['staff'] : (isset($_SESSION['staff_id']) ? 'staff_' . $_SESSION['staff_id'] : 'guest'));
$raterEsc = mysqli_real_escape_string($conn, $rater_id);

$favIds = [];
$favRes = mysqli_query($conn, "SELECT resource_id FROM library_favorites WHERE rater_id='$raterEsc'");
while ($favRow = mysqli_fetch_assoc($favRes)) { $favIds[] = $favRow['resource_id']; }

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
<html>
<head>
    <title>Virtual Library</title>
    <link rel="stylesheet" href="../assets/themes.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; background: var(--bg); color: var(--text-main); margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        h1 { color: var(--text-main); }
        h2.section-title { font-size: 16px; color: var(--text-main); margin: 25px 0 12px; }
        .filters { background: var(--card-bg); border: 1px solid var(--border); padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .view-tabs { display: flex; gap: 10px; margin-bottom: 15px; }
.tab-btn { padding: 8px 18px; border-radius: 20px; text-decoration: none; font-size: 13px; font-weight: 600; background: var(--card-bg); border: 1px solid var(--border); color: var(--text-main); }
.tab-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .filters input[type=text] { flex: 1; min-width: 200px; padding: 8px; border: 1px solid var(--border); border-radius: 5px; background: var(--bg); color: var(--text-main); }
        .filters select { padding: 8px 12px; border: 1px solid var(--border); border-radius: 5px; background: var(--bg); color: var(--text-main); }
        .filters button { padding: 8px 12px; border: none; border-radius: 5px; background: var(--primary); color: white; cursor: pointer; }
        .filters .fav-link { text-decoration: none; font-size: 13px; color: var(--primary); font-weight: 600; }
        .trending-row { display: flex; gap: 15px; overflow-x: auto; padding-bottom: 10px; }
        .trending-row .card { min-width: 220px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; padding: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: relative; }
        .card .thumb-wrap { width: 100%; height: 150px; background: var(--bg); border-radius: 6px; display:flex; align-items:center; justify-content:center; overflow:hidden; margin-bottom:10px; }
        .card canvas.pdf-thumb { max-width: 100%; max-height: 100%; box-shadow: 0 2px 6px rgba(0,0,0,0.15); }
        .fav-btn { position:absolute; top:10px; right:10px; background: var(--card-bg); border: 1px solid var(--border); border-radius: 50%; width:32px; height:32px; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; }
        .fav-btn.favorited { color: #e63946; border-color:#e63946; }
        .card h3 { margin: 0 0 8px 0; font-size: 16px; color: var(--text-main); }
        .card p { font-size: 13px; color: var(--text-muted); margin: 5px 0; }
        .badge { display: inline-block; background: var(--glow); color: var(--primary); font-size: 11px; padding: 3px 8px; border-radius: 10px; margin-bottom: 8px; }
        .stars { color: #f5a623; font-size: 13px; }
        .card a.view-link { display: inline-block; margin-top: 10px; padding: 6px 14px; background: var(--primary); color: white; text-decoration: none; border-radius: 5px; font-size: 13px; }
        .card a.view-link:hover { background: var(--primary-hover); }
        .empty { text-align: center; color: var(--text-muted); padding: 40px; }
    </style>
</head>
<body>
<div class="container">
    <h1>📚 Virtual Library</h1>

    <?php if (!$show_favs && mysqli_num_rows($trendingResult) > 0): ?>
    <h2 class="section-title">🔥 Trending This Week</h2>
    <div class="trending-row">
        <?php while ($t = mysqli_fetch_assoc($trendingResult)): ?>
            <div class="card">
                <div class="thumb-wrap"><canvas class="pdf-thumb" data-pdf="download.php?id=<?php echo $t['id']; ?>&preview=1"></canvas></div>
                <span class="badge"><?php echo htmlspecialchars($t['subject_name'] ?? 'General'); ?></span>
                <h3><?php echo htmlspecialchars($t['title']); ?></h3>
                <p>⬇ <?php echo $t['downloads_count']; ?> downloads · 👁 <?php echo $t['views_count']; ?> views</p>
                <a href="view.php?id=<?php echo $t['id']; ?>" class="view-link">View / Download</a>
            </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

 <div class="view-tabs">
        <a href="index.php" class="tab-btn <?php echo !$show_favs ? 'active' : ''; ?>">📖 All Resources</a>
        <a href="?favorites=1" class="tab-btn <?php echo $show_favs ? 'active' : ''; ?>">❤ My Favorites (<?php echo count($favIds); ?>)</a>
    </div>

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
        <button type="submit">Search</button>
    </form>

    <div class="grid">
        <?php if (mysqli_num_rows($result) === 0): ?>
            <div class="empty"><?php echo $show_favs ? "You haven't favorited anything yet." : "No resources found."; ?></div>
        <?php else: ?>
            <?php while ($row = mysqli_fetch_assoc($result)): 
                $isFav = in_array($row['id'], $favIds);
                $avg = $row['avg_rating'] ? round($row['avg_rating'], 1) : 0;
                $cnt = $row['rating_count'];
            ?>
                <div class="card">
                    <button class="fav-btn <?php echo $isFav ? 'favorited' : ''; ?>" data-id="<?php echo $row['id']; ?>" onclick="toggleFavorite(this)"><?php echo $isFav ? '♥' : '♡'; ?></button>
                    <div class="thumb-wrap"><canvas class="pdf-thumb" data-pdf="download.php?id=<?php echo $row['id']; ?>&preview=1"></canvas></div>
                    <span class="badge"><?php echo htmlspecialchars($row['subject_name'] ?? 'General'); ?></span>
                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                    <p><?php echo htmlspecialchars(substr($row['description'], 0, 90)); ?>...</p>
                    <p class="stars"><?php echo $avg > 0 ? str_repeat('★', round($avg)) . str_repeat('☆', 5-round($avg)) . " ($avg, $cnt)" : 'No ratings yet'; ?></p>
                    <p>👁 <?php echo $row['views_count']; ?> views · ⬇ <?php echo $row['downloads_count']; ?> downloads</p>
                    <a href="view.php?id=<?php echo $row['id']; ?>" class="view-link">View / Download</a>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/theme-switcher.js"></script>
<script>
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
        }).catch(err => console.log('Thumbnail failed', err));
    });

    function toggleFavorite(btn) {
        fetch('favorite.php', {
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
</script>
</body>
</html>

