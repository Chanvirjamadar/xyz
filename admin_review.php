<?php
session_start(); require_once '../db.php';
require_once 'includes/admin_check.php'; // staff only

$message = "";

// Handle approve/reject actions
if (isset($_POST['action']) && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        mysqli_query($conn, "UPDATE library_resources SET status='approved' WHERE id=$id");
        $message = "Resource approved.";
    } elseif ($action === 'reject') {
        mysqli_query($conn, "UPDATE library_resources SET status='rejected' WHERE id=$id");
        $message = "Resource rejected.";
    } elseif ($action === 'delete') {
        // Get file path first to delete the physical file too
        $res = mysqli_query($conn, "SELECT file_path FROM library_resources WHERE id=$id");
        $row = mysqli_fetch_assoc($res);
        if ($row) {
            $filePath = __DIR__ . '/uploads/' . $row['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
        mysqli_query($conn, "DELETE FROM library_resources WHERE id=$id");
        $message = "Resource deleted.";
    }
}

// Fetch all pending resources
$pending = mysqli_query($conn, "SELECT r.*, s.subject_name FROM library_resources r 
                                  LEFT JOIN library_subjects s ON r.subject_id = s.subject_id
                                  WHERE r.status = 'pending' ORDER BY r.created_at ASC");

// Fetch approved/rejected too, for management
$others = mysqli_query($conn, "SELECT r.*, s.subject_name FROM library_resources r 
                                  LEFT JOIN library_subjects s ON r.subject_id = s.subject_id
                                  WHERE r.status != 'pending' ORDER BY r.created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Review - Library</title>
    <link rel="stylesheet" href="../assets/themes.css">
   <style>
    body { font-family: Arial, sans-serif; background: var(--bg); color: var(--text-main); margin: 0; padding: 20px; }
    .container { max-width: 900px; margin: 0 auto; }
    h1 { color: var(--text-main); }
    h2 { color: var(--text-main); margin-top: 30px; font-size: 18px; border-bottom: 2px solid var(--border); padding-bottom: 8px; }
    .message { background: rgba(21, 128, 61, 0.15); color: #27ae60; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    .item { background: var(--card-bg); border: 1px solid var(--border); padding: 15px 20px; border-radius: 8px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
    .item-info h3 { margin: 0 0 5px 0; color: var(--text-main); font-size: 15px; }
    .item-info p { margin: 0; font-size: 13px; color: var(--text-muted); }
    .badge { display: inline-block; font-size: 11px; padding: 3px 8px; border-radius: 10px; margin-right: 8px; }
    .badge.pending { background: #fff3cd; color: #856404; }
    .badge.approved { background: #d4edda; color: #155724; }
    .badge.rejected { background: #f8d7da; color: #721c24; }
    .actions button { margin-left: 6px; padding: 6px 14px; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; color: white; }
    .approve { background: #27ae60; }
    .reject { background: #e67e22; }
    .delete { background: #c0392b; }
    .back { display: inline-block; margin-bottom: 15px; color: var(--primary); text-decoration: none; }
    .empty { color: var(--text-muted); font-style: italic; padding: 10px 0; }
</style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back">&larr; Back to Library</a>
    <a href="upload.php" class="back" style="float:right;">Upload New Resource &rarr;</a>
    <h1>Admin Review Panel</h1>

    <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <h2>Pending Approval</h2>
    <?php if (mysqli_num_rows($pending) === 0): ?>
        <p class="empty">No pending resources.</p>
    <?php else: ?>
        <?php while ($row = mysqli_fetch_assoc($pending)): ?>
            <div class="item">
                <div class="item-info">
                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                    <p><?php echo htmlspecialchars($row['subject_name'] ?? 'General'); ?> · Uploaded by <?php echo htmlspecialchars($row['uploaded_by']); ?> · <?php echo $row['created_at']; ?></p>
                </div>
                <div class="actions">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="action" value="approve" class="approve">Approve</button>
                        <button type="submit" name="action" value="reject" class="reject">Reject</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <h2>Approved / Rejected</h2>
    <?php if (mysqli_num_rows($others) === 0): ?>
        <p class="empty">No resources yet.</p>
    <?php else: ?>
        <?php while ($row = mysqli_fetch_assoc($others)): ?>
            <div class="item">
                <div class="item-info">
                    <span class="badge <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span>
                    <h3 style="display:inline;"><?php echo htmlspecialchars($row['title']); ?></h3>
                    <p><?php echo htmlspecialchars($row['subject_name'] ?? 'General'); ?> · <?php echo $row['created_at']; ?></p>
                </div>
                <div class="actions">
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this resource permanently?');">
                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="action" value="delete" class="delete">Delete</button>
                    </form>
                </div>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>
<script src="../assets/theme-switcher.js"></script>
</body>
</html>
