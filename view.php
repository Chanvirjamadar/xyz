<?php
session_start();
require_once '../db.php';

// Rater ID setup
$rater_id = isset($_SESSION['student']) ? 'student_' . $_SESSION['student'] : (isset($_SESSION['staff']) ? 'staff_' . $_SESSION['staff'] : (isset($_SESSION['staff_id']) ? 'staff_' . $_SESSION['staff_id'] : 'guest'));
$raterEsc = mysqli_real_escape_string($conn, $rater_id);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "SELECT r.*, s.subject_name,
    (SELECT AVG(rating) FROM library_ratings WHERE resource_id = r.id) as avg_rating,
    (SELECT COUNT(*) FROM library_ratings WHERE resource_id = r.id) as rating_count
    FROM library_resources r
    LEFT JOIN library_subjects s ON r.subject_id = s.subject_id
    WHERE r.id = $id AND r.status = 'approved'";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) === 0) { 
    die("<div style='font-family:sans-serif; text-align:center; padding:50px; color:#64748b;'><h2>Resource not found or pending approval.</h2><a href='javascript:history.back()' style='color:#6366f1; text-decoration:none;'>&larr; Go Back</a></div>"); 
}
$row = mysqli_fetch_assoc($result);

// Increment view count
mysqli_query($conn, "UPDATE library_resources SET views_count = views_count + 1 WHERE id = $id");

$favCheck = mysqli_query($conn, "SELECT id FROM library_favorites WHERE resource_id=$id AND rater_id='$raterEsc'");
$isFav = mysqli_num_rows($favCheck) > 0;

$myRatingRes = mysqli_query($conn, "SELECT rating FROM library_ratings WHERE resource_id=$id AND rater_id='$raterEsc'");
$myRating = 0;
if ($mr = mysqli_fetch_assoc($myRatingRes)) { $myRating = $mr['rating']; }

$avg = $row['avg_rating'] ? round($row['avg_rating'], 1) : 0;
$cnt = $row['rating_count'];

$related = mysqli_query($conn, "SELECT * FROM library_resources 
    WHERE subject_id = " . (int)$row['subject_id'] . " AND id != $id AND status='approved' 
    ORDER BY downloads_count DESC LIMIT 4");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($row['title']); ?> | ZEALHUB Library</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #6366f1;
            --bg: #f3f4f9;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --glow: rgba(99, 102, 241, 0.25);
            --input-bg: #f8fafc;
            --danger: #ef4444;
            --star-gold: #f59e0b;
        }

        [data-theme="dark"] {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --border: #334155;
            --glow: rgba(99, 102, 241, 0.4);
            --input-bg: #0f172a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.3s ease;
        }

        body {
            background: var(--bg);
            color: var(--text-main);
            padding: 30px 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1050px;
            margin: 0 auto;
        }

        /* Top Action Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--card-bg);
            color: var(--text-main);
            border: 1px solid var(--border);
            border-radius: 12px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        .btn-back:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateX(-3px);
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 4px 15px var(--glow);
        }

        .btn-download:hover {
            opacity: 0.92;
            transform: translateY(-2px);
        }

        .fav-btn {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text-muted);
            border-radius: 12px;
            width: 42px;
            height: 42px;
            font-size: 18px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .fav-btn:hover, .fav-btn.favorited {
            color: var(--danger);
            border-color: var(--danger);
            background: rgba(239, 68, 68, 0.08);
        }

        /* Resource Card */
        .main-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            margin-bottom: 30px;
        }

        .subject-tag {
            display: inline-block;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            font-size: 11px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .title {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .meta-strip {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .meta-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            background: var(--input-bg);
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .meta-pill i {
            color: var(--primary);
        }

        .description {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 25px;
        }

        /* Rating Block */
        .rating-block {
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .star-picker {
            display: flex;
            gap: 6px;
        }

        .star-picker span {
            font-size: 24px;
            cursor: pointer;
            color: var(--border);
            transition: color 0.2s ease, transform 0.2s ease;
        }

        .star-picker span:hover,
        .star-picker span.filled {
            color: var(--star-gold);
            transform: scale(1.15);
        }

        /* Viewer Container */
        .viewer-container {
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            background: #1e1e24;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .viewer-header {
            background: var(--input-bg);
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
        }

        iframe {
            width: 100%;
            height: 720px;
            border: none;
            display: block;
        }

        /* Related Grid */
        .related-title {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 15px;
        }

        .related-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .related-card:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px var(--glow);
        }

        .related-card h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .related-card a {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        @media (max-width: 640px) {
            iframe { height: 500px; }
            .rating-block { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Top Bar -->
    <div class="top-bar">
        <a href="javascript:history.back()" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> Back to Library
        </a>
        <div class="top-actions">
            <button class="fav-btn <?php echo $isFav ? 'favorited' : ''; ?>" id="favBtn" data-id="<?php echo $row['id']; ?>" onclick="toggleFavorite()" title="Favorite Resource">
                <i class="fa-<?php echo $isFav ? 'solid' : 'regular'; ?> fa-heart"></i>
            </button>
            <a href="download.php?id=<?php echo $row['id']; ?>" class="btn-download">
                <i class="fa-solid fa-download"></i> Download PDF
            </a>
        </div>
    </div>

    <!-- Main Card -->
    <div class="main-card">
        <span class="subject-tag"><?php echo htmlspecialchars($row['subject_name'] ?? 'General'); ?></span>
        <h1 class="title"><?php echo htmlspecialchars($row['title']); ?></h1>

        <div class="meta-strip">
            <div class="meta-pill"><i class="fa-regular fa-eye"></i> <?php echo $row['views_count']; ?> Views</div>
            <div class="meta-pill"><i class="fa-solid fa-file-arrow-down"></i> <?php echo $row['downloads_count']; ?> Downloads</div>
            <div class="meta-pill"><i class="fa-solid fa-star" style="color:var(--star-gold);"></i> <?php echo $avg > 0 ? "$avg / 5 ($cnt ratings)" : 'No ratings yet'; ?></div>
        </div>

        <?php if (!empty($row['description'])): ?>
            <p class="description"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
        <?php endif; ?>

        <!-- Rating Section -->
        <div class="rating-block">
            <div>
                <strong style="display:block; font-size:13px; margin-bottom:3px;">Rate this Document</strong>
                <span id="ratingMsg" style="font-size:12px; color: var(--text-muted);">Click a star to submit your rating.</span>
            </div>
            <div class="star-picker" id="starPicker">
                <?php for ($i=1; $i<=5; $i++): ?>
                    <span data-value="<?php echo $i; ?>" class="<?php echo $i <= $myRating ? 'filled' : ''; ?>">★</span>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Embedded Viewer Wrapper -->
        <div class="viewer-container">
            <div class="viewer-header">
                <span><i class="fa-regular fa-file-pdf" style="color:var(--primary); margin-right:6px;"></i> Document Preview</span>
                <span style="font-size:11px; color:var(--text-muted);">Interactive PDF Viewer</span>
            </div>
            <iframe src="download.php?id=<?php echo $row['id']; ?>&preview=1"></iframe>
        </div>
    </div>

    <!-- Related Resources -->
    <?php if (mysqli_num_rows($related) > 0): ?>
    <h3 class="related-title"><i class="fa-solid fa-layer-group" style="color:var(--primary);"></i> Related Resources</h3>
    <div class="related-grid">
        <?php while ($rel = mysqli_fetch_assoc($related)): ?>
            <div class="related-card">
                <h4><?php echo htmlspecialchars($rel['title']); ?></h4>
                <a href="view.php?id=<?php echo $rel['id']; ?>">
                    Read Resource <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Apply Theme from LocalStorage automatically
const themes = {
    light: { primary: '#4361ee', bg: '#f3f4f9', card: '#ffffff', text: '#1e293b', muted: '#64748b', border: '#e2e8f0', glow: 'rgba(67, 97, 238, 0.2)' },
    dark: { primary: '#6366f1', bg: '#0f172a', card: '#1e293b', text: '#f1f5f9', muted: '#94a3b8', border: '#334155', glow: 'rgba(99,102,241,0.2)' },
    sunset: { primary: '#ea580c', bg: '#fff7ed', card: '#ffffff', text: '#431407', muted: '#9a6a52', border: '#fed7aa', glow: 'rgba(234, 88, 12, 0.2)' },
    ocean: { primary: '#0891b2', bg: '#ecfeff', card: '#ffffff', text: '#083344', muted: '#5b8a99', border: '#a5f3fc', glow: 'rgba(8, 145, 178, 0.2)' },
    midnight: { primary: '#6366f1', bg: '#0f172a', card: '#1e293b', text: '#f1f5f9', muted: '#94a3b8', border: '#334155', glow: 'rgba(99,102,241,0.2)' },
    forest: { primary: '#15803d', bg: '#f0fdf4', card: '#ffffff', text: '#052e16', muted: '#4d7c62', border: '#bbf7d0', glow: 'rgba(21, 128, 61, 0.2)' },
    pink: { primary: '#ec4899', bg: '#fff5f7', card: '#ffffff', text: '#4a1034', muted: '#9f4b70', border: '#fbcfe8', glow: 'rgba(236, 72, 153, 0.2)' }
};

function applyTheme(key) {
    const selected = themes[key] || themes.light;
    const root = document.documentElement;
    root.style.setProperty('--primary', selected.primary);
    root.style.setProperty('--bg', selected.bg);
    root.style.setProperty('--card-bg', selected.card);
    root.style.setProperty('--text-main', selected.text);
    root.style.setProperty('--text-muted', selected.muted);
    root.style.setProperty('--border', selected.border);
    root.style.setProperty('--glow', selected.glow);
    document.body.setAttribute('data-theme', key);
}

const savedTheme = localStorage.getItem('user-theme') || 'light';
applyTheme(savedTheme);

// Toggle Favorite logic
function toggleFavorite() {
    const btn = document.getElementById('favBtn');
    const icon = btn.querySelector('i');
    fetch('favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'resource_id=' + btn.getAttribute('data-id')
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            btn.classList.toggle('favorited', data.favorited);
            icon.className = data.favorited ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
        }
    });
}

// Star Rating Picker
document.querySelectorAll('#starPicker span').forEach(star => {
    star.addEventListener('click', () => {
        const value = star.getAttribute('data-value');
        fetch('rate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'resource_id=<?php echo $row['id']; ?>&rating=' + value
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('#starPicker span').forEach(s => {
                    s.classList.toggle('filled', s.getAttribute('data-value') <= value);
                });
                document.getElementById('ratingMsg').textContent = 'Thank you! New average: ' + data.avg_rating + ' ★ (' + data.total_ratings + ' ratings)';
            }
        });
    });
});
</script>
</body>
</html>