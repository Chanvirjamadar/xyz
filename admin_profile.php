<?php
session_start();
require_once __DIR__ . "/db.php";

// Auth check
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$adminUsername = $_SESSION['admin'];
$adminId = $_SESSION['admin_id'] ?? 1;

$feedbackMsg = "";
$feedbackType = "";

// Fetch current admin profile details
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ? OR id = ?");
$stmt->bind_param("si", $adminUsername, $adminId);
$stmt->execute();
$adminData = $stmt->get_result()->fetch_assoc();

if (!$adminData) {
    // Default fallback values if admin row not found
    $adminData = [
        'id' => $adminId,
        'username' => $adminUsername,
        'name' => $_SESSION['admin_name'] ?? 'System Administrator',
        'email' => 'admin@zealhub.edu',
        'password' => 'admin123',
        'created_at' => date('Y-m-d H:i:s')
    ];
}

$adminName = $adminData['name'];

// ── HANDLE PROFILE UPDATE ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Validation
    if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
        $feedbackMsg = "Validation Error: Mobile number must be exactly 10 numeric digits (0-9 only).";
        $feedbackType = "danger";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $feedbackMsg = "Validation Error: Please enter a valid email address.";
        $feedbackType = "danger";
    } elseif (!empty($name) && !empty($email) && !empty($username)) {
        
        // Ensure username is not taken by another admin
        $checkUser = $conn->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
        $checkUser->bind_param("si", $username, $adminData['id']);
        $checkUser->execute();
        if ($checkUser->get_result()->num_rows > 0) {
            $feedbackMsg = "Username '$username' is already taken by another administrator.";
            $feedbackType = "danger";
        } else {
            // Determine password to set
            $finalPassword = !empty($newPassword) ? $newPassword : $adminData['password'];

            // Safe column migration for optional phone column on admin_users table
            try {
                @$conn->query("ALTER TABLE `admin_users` ADD COLUMN `phone` VARCHAR(30) DEFAULT NULL");
            } catch (Exception $e) {}

            $updateStmt = $conn->prepare("UPDATE admin_users SET name = ?, email = ?, username = ?, password = ? WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("ssssi", $name, $email, $username, $finalPassword, $adminData['id']);
                if ($updateStmt->execute()) {
                    // Update session variables
                    $_SESSION['admin'] = $username;
                    $_SESSION['admin_name'] = $name;

                    // Log activity
                    $conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$name', 'Admin', 'Profile Update', 'Updated administrator profile details')");

                    // Refresh local data
                    $adminData['name'] = $name;
                    $adminData['email'] = $email;
                    $adminData['username'] = $username;
                    $adminData['password'] = $finalPassword;
                    $adminName = $name;

                    $feedbackMsg = "Admin Profile updated successfully!";
                    $feedbackType = "success";
                } else {
                    $feedbackMsg = "Error updating profile: " . $conn->error;
                    $feedbackType = "danger";
                }
            }
        }
    } else {
        $feedbackMsg = "Name, email, and username are required fields.";
        $feedbackType = "warning";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile Governance | ZEALHUB</title>
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

        [data-theme="yellow"], [data-theme="golden"] {
            --primary: #d97706;
            --primary-hover: #b45309;
            --bg: #fefce8;
            --header-bg: #ffffff;
            --sidebar-bg: #713f12;
            --card-bg: #ffffff;
            --text-main: #422006;
            --text-muted: #854d0e;
            --border: #fef08a;
            --glow: rgba(217, 119, 6, 0.2);
            --input-bg: #fefce8;
        }

        [data-theme="sunset"] {
            --primary: #ea580c;
            --primary-hover: #c2410c;
            --bg: #fff7ed;
            --header-bg: #ffffff;
            --sidebar-bg: #7c2d12;
            --card-bg: #ffffff;
            --text-main: #431407;
            --text-muted: #9a6a52;
            --border: #fed7aa;
            --glow: rgba(234, 88, 12, 0.2);
            --input-bg: #fff7ed;
        }

        [data-theme="ocean"] {
            --primary: #0891b2;
            --primary-hover: #0e7490;
            --bg: #ecfeff;
            --header-bg: #ffffff;
            --sidebar-bg: #164e63;
            --card-bg: #ffffff;
            --text-main: #164e63;
            --text-muted: #5b8a99;
            --border: #a5f3fc;
            --glow: rgba(8, 145, 178, 0.2);
            --input-bg: #ecfeff;
        }

        [data-theme="midnight"] {
            --primary: #38bdf8;
            --primary-hover: #0284c7;
            --bg: #030712;
            --header-bg: #0b0f19;
            --sidebar-bg: #111827;
            --card-bg: #111827;
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
            --border: #1f2937;
            --glow: rgba(56, 189, 248, 0.25);
            --input-bg: #030712;
        }

        [data-theme="forest"] {
            --primary: #15803d;
            --primary-hover: #166534;
            --bg: #f0fdf4;
            --header-bg: #ffffff;
            --sidebar-bg: #14532d;
            --card-bg: #ffffff;
            --text-main: #14532d;
            --text-muted: #4d7c62;
            --border: #bbf7d0;
            --glow: rgba(21, 128, 61, 0.2);
            --input-bg: #f0fdf4;
        }

        [data-theme="pink"] {
            --primary: #db2777;
            --primary-hover: #be185d;
            --bg: #fdf2f8;
            --header-bg: #ffffff;
            --sidebar-bg: #831843;
            --card-bg: #ffffff;
            --text-main: #500724;
            --text-muted: #9d174d;
            --border: #fbcfe8;
            --glow: rgba(219, 39, 119, 0.2);
            --input-bg: #fdf2f8;
        }

        [data-theme="purple"] {
            --primary: #9333ea;
            --primary-hover: #7e22ce;
            --bg: #faf5ff;
            --header-bg: #ffffff;
            --sidebar-bg: #581c87;
            --card-bg: #ffffff;
            --text-main: #3b0764;
            --text-muted: #7e22ce;
            --border: #e9d5ff;
            --glow: rgba(147, 51, 234, 0.2);
            --input-bg: #faf5ff;
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
            height: 72px;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.04);
        }

        .header-left { display: flex; align-items: center; gap: 15px; }

        .menu-btn {
            background: var(--primary);
            color: #ffffff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            box-shadow: 0 4px 14px var(--glow);
            transition: all 0.25s ease;
        }

        .menu-btn:hover { transform: scale(1.05); background: var(--primary-hover); }

        .logo {
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            color: #ffffff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
        }

        .logo-text { display: flex; flex-direction: column; }
        .brand-name { font-size: 20px; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; line-height: 1.1; }
        .brand-tag { font-size: 9px; font-weight: 800; color: var(--danger); letter-spacing: 1px; text-transform: uppercase; }

        .header-right { display: flex; align-items: center; gap: 14px; }

        .icon-btn {
            background: var(--input-bg);
            color: var(--text-main);
            border: 1px solid var(--border);
            width: 42px;
            height: 42px;
            border-radius: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: all 0.2s ease;
        }

        .icon-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

        .profile-pill {
            display: flex; align-items: center; gap: 10px; padding: 6px 14px; border-radius: 14px; border: 1px solid var(--border); background: var(--card-bg); text-decoration: none; color: inherit;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            height: calc(100vh - 72px);
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border);
            position: fixed;
            top: 72px;
            left: 0;
            padding: 20px 15px;
            display: flex;
            flex-direction: column;
            z-index: 999;
            overflow-y: auto;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body.sidebar-collapsed .sidebar { transform: translateX(-100%); }
        body.sidebar-collapsed .main-content { margin-left: 0 !important; }

        .sidebar button, .sidebar a {
            background: transparent;
            border: none;
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            text-align: left;
            transition: all 0.2s ease;
        }

        .sidebar button:hover, .sidebar button.active, .sidebar a:hover, .sidebar a.active {
            background: var(--primary);
            color: #ffffff !important;
            box-shadow: 0 4px 14px var(--glow);
        }

        .sidebar a.btn-logout { margin-top: auto; background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .sidebar a.btn-logout:hover { background: var(--danger); color: white !important; }

        /* MAIN CONTENT */
        .main-content {
            margin-left: 260px;
            margin-top: 72px;
            padding: 30px;
            min-height: calc(100vh - 72px);
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .card-box {
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border);
            padding: 28px;
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 12.5px; font-weight: 800; color: var(--text-main); text-transform: uppercase; letter-spacing: 0.5px; }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--input-bg);
            color: var(--text-main);
            font-size: 14px;
            font-weight: 600;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--glow);
            background: var(--card-bg);
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px var(--glow);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            background: var(--primary-hover);
        }

        .alert { padding: 14px 18px; border-radius: 12px; font-size: 13.5px; font-weight: 600; margin-bottom: 20px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .alert-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }

        .theme-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 3000; padding: 20px; }
        .theme-modal.active { display: flex; }
        .theme-card { background: var(--card-bg); padding: 30px; border-radius: 24px; width: min(90%, 440px); border: 1px solid var(--border); text-align: center; }
        .theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; }
        .theme-opt { padding: 14px; border-radius: 14px; border: 2px solid var(--border); cursor: pointer; font-weight: 700; font-size: 13px; display: flex; align-items: center; gap: 10px; color: var(--text-main); }

        .footer { margin-top: 40px; padding: 20px 0; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; color: var(--text-muted); font-size: 13px; }

        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); width: 260px !important; }
            .main-content { margin-left: 0 !important; padding: 20px 15px; }
        }
    </style>
</head>

<body data-theme="light">

    <!-- HEADER -->
    <header class="header">
        <div class="header-left">
            <button type="button" class="menu-btn" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Toggle Sidebar Navigation">
                <i class="fa-solid fa-bars"></i>
            </button>
            <a href="admin_dashboard.php" class="logo">
                <div class="logo-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <div class="logo-text">
                    <span class="brand-name">ZEALHUB</span>
                    <span class="brand-tag">ADMIN GOVERNANCE</span>
                </div>
            </a>
        </div>

        <div class="header-right">
            <button class="icon-btn" id="themeBtn" type="button" title="Choose Theme">
                <i class="fa-solid fa-palette"></i>
            </button>

            <a href="admin_profile.php" class="profile-pill" style="border-color: var(--primary);">
                <div style="text-align: right;">
                    <p style="font-size: 11px; font-weight: 800; line-height: 1.2;"><?= htmlspecialchars($adminName) ?></p>
                    <p style="font-size: 9px; color: var(--danger); font-weight: 800;">SUPER ADMIN</p>
                </div>
                <div style="width: 34px; height: 34px; background: var(--danger); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px;">
                    AD
                </div>
            </a>

            <a href="admin_logout.php" class="icon-btn" title="Logout" style="color: var(--danger);">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </header>

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <a href="admin_dashboard.php" class="tab-link">
            <i class="fa-solid fa-chart-pie"></i> <span>Dashboard Overview</span>
        </a>
        <a href="admin_dashboard.php?tab=students" class="tab-link">
            <i class="fa-solid fa-user-graduate"></i> <span>Student Management</span>
        </a>
        <a href="admin_dashboard.php?tab=staff" class="tab-link">
            <i class="fa-solid fa-chalkboard-user"></i> <span>Staff Management</span>
        </a>
        <a href="admin_dashboard.php?tab=activity" class="tab-link">
            <i class="fa-solid fa-bolt"></i> <span>Dual Activity Logs</span>
        </a>
        <a href="admin_profile.php" class="tab-link active">
            <i class="fa-solid fa-user-shield" style="color: #6366f1;"></i> <span>Admin Profile</span>
        </a>
        <button type="button" class="tab-link" id="sidebarThemeBtn" onclick="toggleThemeModal(true)" style="color: #f59e0b; margin-top: 10px;">
            <i class="fa-solid fa-palette"></i> <span>Choose Portal Theme</span>
        </button>
        <a href="admin_logout.php" class="btn-logout">
            <i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span>
        </a>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">

        <?php if (!empty($feedbackMsg)): ?>
            <div class="alert alert-<?= $feedbackType ?>">
                <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($feedbackMsg) ?>
            </div>
        <?php endif; ?>

        <div class="page-header-flex" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 15px;">
            <div>
                <h1 style="font-size: 26px; font-weight: 800;">Admin Governance Profile 🛡️</h1>
                <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">View and update your administrator credentials, email, and authentication password.</p>
            </div>
            <a href="admin_dashboard.php" class="btn-submit" style="background: var(--input-bg); color: var(--text-main); border: 1px solid var(--border); box-shadow: none;">
                <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- PROFILE OVERVIEW & EDIT FORM GRID -->
        <div style="display: grid; grid-template-columns: 320px 1fr; gap: 25px;">
            
            <!-- OVERVIEW CARD -->
            <div class="card-box" style="text-align: center;">
                <div style="width: 100px; height: 100px; background: linear-gradient(135deg, var(--danger) 0%, #b91c1c 100%); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 36px; font-weight: 800; margin: 0 auto 20px; box-shadow: 0 8px 24px rgba(239, 68, 68, 0.3);">
                    AD
                </div>
                <h2 style="font-size: 20px; font-weight: 800; margin-bottom: 4px;"><?= htmlspecialchars($adminData['name']) ?></h2>
                <span class="badge-pill" style="background: rgba(239, 68, 68, 0.15); color: var(--danger); font-size: 11px; font-weight: 800; padding: 4px 12px; border-radius: 20px; display: inline-block; margin-bottom: 15px;">
                    SUPER ADMINISTRATOR
                </span>

                <div style="text-align: left; background: var(--input-bg); padding: 18px; border-radius: 16px; border: 1px solid var(--border); margin-top: 10px;">
                    <div style="margin-bottom: 12px;">
                        <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block;">Username</span>
                        <strong style="font-size: 13.5px; color: var(--text-main);"><?= htmlspecialchars($adminData['username']) ?></strong>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block;">Email Address</span>
                        <strong style="font-size: 13.5px; color: var(--text-main);"><?= htmlspecialchars($adminData['email']) ?></strong>
                    </div>
                    <div>
                        <span style="font-size: 11px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; display: block;">Account Created</span>
                        <strong style="font-size: 13.5px; color: var(--text-main);"><?= date('M d, Y', strtotime($adminData['created_at'] ?? 'now')) ?></strong>
                    </div>
                </div>
            </div>

            <!-- EDIT FORM CARD -->
            <div class="card-box">
                <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 20px;"><i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> Edit Profile Details</h3>
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($adminData['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($adminData['email']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($adminData['username']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Mobile Phone (10 Digits Only)</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($adminData['phone'] ?? '') ?>" placeholder="10-digit mobile (e.g. 9876543210)" pattern="[0-9]{10}" maxlength="10" minlength="10" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>New Password (Leave blank to keep current password)</label>
                            <div style="position: relative;">
                                <input type="password" name="new_password" id="newPasswordInput" class="form-control" placeholder="Enter new password (optional)" style="padding-right: 44px;">
                                <i class="fa-solid fa-eye" id="togglePassIcon" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-muted);" onclick="togglePassVisibility()"></i>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-save"></i> Save Profile Changes</button>
                </form>
            </div>

        </div>

        <!-- FOOTER -->
        <footer class="footer">
            <div>© <?= date('Y') ?> <strong>ZEALHUB Academic Portal Governance</strong></div>
            <div>Admin Control Panel</div>
        </footer>
    </main>

    <!-- THEME MODAL -->
    <div id="themeModal" class="theme-modal">
        <div class="theme-card" style="width: min(92%, 520px);">
            <h3 style="font-size: 20px; font-weight: 800; color: var(--text-main);"><i class="fa-solid fa-palette" style="color: var(--primary);"></i> Choose Portal Theme</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">Select your preferred color scheme for Admin Governance Control Center</p>
            
            <div class="theme-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-top: 20px;">
                <div class="theme-opt" data-theme="light"><span style="width:14px;height:14px;background:#4361ee;border-radius:50%;display:inline-block;"></span> ☀️ Light Blue</div>
                <div class="theme-opt" data-theme="dark"><span style="width:14px;height:14px;background:#6366f1;border-radius:50%;display:inline-block;"></span> 🌙 Dark Mode</div>
                <div class="theme-opt" data-theme="yellow"><span style="width:14px;height:14px;background:#d97706;border-radius:50%;display:inline-block;"></span> 💛 Golden Yellow</div>
                <div class="theme-opt" data-theme="sunset"><span style="width:14px;height:14px;background:#ea580c;border-radius:50%;display:inline-block;"></span> 🌅 Sunset Orange</div>
                <div class="theme-opt" data-theme="ocean"><span style="width:14px;height:14px;background:#0891b2;border-radius:50%;display:inline-block;"></span> 🌊 Ocean Cyan</div>
                <div class="theme-opt" data-theme="midnight"><span style="width:14px;height:14px;background:#38bdf8;border-radius:50%;display:inline-block;"></span> 🌌 Midnight Navy</div>
                <div class="theme-opt" data-theme="forest"><span style="width:14px;height:14px;background:#15803d;border-radius:50%;display:inline-block;"></span> 🌲 Forest Emerald</div>
                <div class="theme-opt" data-theme="pink"><span style="width:14px;height:14px;background:#db2777;border-radius:50%;display:inline-block;"></span> 🌸 Light Pink</div>
                <div class="theme-opt" data-theme="purple"><span style="width:14px;height:14px;background:#9333ea;border-radius:50%;display:inline-block;"></span> 🔮 Royal Purple</div>
            </div>
            
            <button type="button" onclick="toggleThemeModal(false)" style="margin-top:22px; width:100%; padding:12px; border:none; background:var(--primary); color:white; border-radius:14px; cursor:pointer; font-weight:800; font-size:14px;">Apply & Close</button>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            if (window.innerWidth <= 900) {
                const sb = document.querySelector('.sidebar');
                if (sb) {
                    sb.classList.toggle('mobile-open');
                    if (sb.classList.contains('mobile-open')) {
                        sb.style.transform = 'translateX(0px)';
                    } else {
                        sb.style.transform = 'translateX(-100%)';
                    }
                }
            } else {
                document.body.classList.toggle('sidebar-collapsed');
            }
        }

        function togglePassVisibility() {
            const passInput = document.getElementById('newPasswordInput');
            const icon = document.getElementById('togglePassIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function toggleThemeModal(show) {
            const modal = document.getElementById('themeModal');
            if (modal) {
                modal.classList.toggle('active', show ?? !modal.classList.contains('active'));
            }
        }

        const themeHeaderBtn = document.getElementById('themeBtn');
        if (themeHeaderBtn) {
            themeHeaderBtn.addEventListener('click', () => toggleThemeModal());
        }

        document.querySelectorAll('.theme-opt').forEach(opt => {
            opt.addEventListener('click', () => {
                const key = opt.dataset.theme;
                document.body.setAttribute('data-theme', key);
                localStorage.setItem('admin-theme', key);
                localStorage.setItem('user-theme', key);
                toggleThemeModal(false);
            });
        });

        const savedTheme = localStorage.getItem('admin-theme') || localStorage.getItem('user-theme') || 'light';
        document.body.setAttribute('data-theme', savedTheme);
    </script>
</body>
</html>
