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

$error = "";

// Initialize login attempts
if (!isset($_SESSION['staff_login_attempts'])) {
    $_SESSION['staff_login_attempts'] = 0;
}

// If attempts reach 5, redirect
if ($_SESSION['staff_login_attempts'] >= 5) {
    header("Location: staff_forget_password.php");
    exit();
}

if (isset($_POST['login'])) {
    // Sanitize inputs to prevent SQL Injection
    $id = mysqli_real_escape_string($conn, trim($_POST['staffid']));
    $pass = trim($_POST['password']);

    $sql = "SELECT * FROM staff_profile WHERE email='$id'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        // Checking both hashed and plain (for your initial data entry)
        if (password_verify($pass, $row['password']) || $pass === $row['password']) {
            $_SESSION['staff_login_attempts'] = 0;
            $_SESSION['staff'] = $row['staff_id'];
            $_SESSION['staff_name'] = $row['name'];
            header("Location: staff_dashboard.php");
            exit();
        } else {
            $_SESSION['staff_login_attempts']++;
            $remaining = 5 - $_SESSION['staff_login_attempts'];
            if ($_SESSION['staff_login_attempts'] >= 5) {
                header("Location: staff_forget_password.php");
                exit();
            } else {
                $error = "Invalid credentials. <b>$remaining</b> attempts remaining.";
            }
        }
    } else {
        $_SESSION['staff_login_attempts']++;
        $remaining = 5 - $_SESSION['staff_login_attempts'];
        if ($_SESSION['staff_login_attempts'] >= 5) {
            header("Location: staff_forget_password.php");
            exit();
        } else {
            $error = "Invalid credentials. <b>$remaining</b> attempts remaining.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login | ZEELHUB Academy</title>
    <!-- Google Fonts & Font Awesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-glow: rgba(79, 70, 229, 0.35);
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #cbd5e1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            /* Brightened Campus Background Overlay */
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.40), rgba(30, 27, 75, 0.50)),
                        url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?q=80&w=1920&auto=format&fit=crop') no-repeat center center/cover;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Glow Effect */
        body::before {
            content: '';
            position: absolute;
            width: 320px;
            height: 320px;
            background: rgba(79, 70, 229, 0.25);
            filter: blur(100px);
            border-radius: 50%;
            top: 5%;
            left: 15%;
            z-index: 0;
        }

        /* Login Card Container */
        .login-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 28px;
            padding: 44px 38px 30px;
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.45), 
                        0 0 0 1px rgba(255, 255, 255, 0.5);
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Header */
        .card-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .badge-academic {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eef2ff;
            color: var(--primary);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 16px;
            border: 1px solid #e0e7ff;
        }

        .card-header h2 {
            font-size: 26px;
            color: var(--text-dark);
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .card-header p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 6px;
        }

        /* Form Controls */
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group i.input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .input-group .toggle-password {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            font-size: 16px;
            transition: color 0.3s;
        }

        .input-group .toggle-password:hover {
            color: var(--primary);
        }

        .input-group input {
            width: 100%;
            padding: 16px 48px 16px 50px;
            border: 2px solid var(--border-color);
            background: #f8fafc;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
            outline: none;
            transition: all 0.3s ease;
        }

        .input-group input::placeholder {
            color: #94a3b8;
            font-weight: 500;
        }

        .input-group input:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-glow);
        }

        .input-group input:focus + i.input-icon {
            color: var(--primary);
        }

        /* Helpers Row */
        .helper-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 26px;
            font-size: 13px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-weight: 600;
            cursor: pointer;
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .helper-row a {
            text-decoration: none;
            color: var(--primary);
            font-weight: 700;
            transition: color 0.2s;
        }

        .helper-row a:hover {
            color: var(--primary-hover);
            text-decoration: underline;
        }

        /* Login Button */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            transform: translateY(-2px);
            box-shadow: 0 14px 24px -5px rgba(79, 70, 229, 0.5);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Error Banner */
        .error-msg {
            background: #fef2f2;
            color: #991b1b;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 22px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #fee2e2;
        }

        .error-msg i {
            font-size: 16px;
            color: #ef4444;
        }

        /* Account Locked View */
        .lock-card {
            text-align: center;
            padding: 10px 0;
        }

        .lock-card .lock-icon-wrap {
            width: 72px;
            height: 72px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 1px solid #fee2e2;
        }

        .lock-card i {
            font-size: 32px;
            color: #ef4444;
        }

        /* Footer & Help Button */
        .card-footer {
            margin-top: 28px;
            text-align: center;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 500;
            border-top: 1px solid #f1f5f9;
            padding-top: 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .btn-help {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary);
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-help:hover {
            background: #eef2ff;
            border-color: #c7d2fe;
            color: var(--primary-hover);
            transform: translateY(-1px);
        }

        /* Modal Overlay & Pop-up Box */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            padding: 20px;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-card {
            background: #ffffff;
            width: 100%;
            max-width: 480px;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .modal-overlay.active .modal-card {
            transform: scale(1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: var(--primary);
        }

        .close-modal {
            background: #f1f5f9;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .close-modal:hover {
            background: #fee2e2;
            color: #ef4444;
        }

        .help-section {
            margin-bottom: 20px;
        }

        .help-section h4 {
            font-size: 14px;
            color: var(--text-dark);
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .help-steps {
            list-style: none;
            padding-left: 0;
        }

        .help-steps li {
            position: relative;
            padding-left: 28px;
            margin-bottom: 10px;
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .help-steps li::before {
            content: attr(data-step);
            position: absolute;
            left: 0;
            top: 0;
            width: 20px;
            height: 20px;
            background: #eef2ff;
            color: var(--primary);
            font-weight: 800;
            font-size: 11px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .contact-box {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            padding: 14px;
            border-radius: 12px;
            font-size: 13px;
            color: var(--text-dark);
        }

        .contact-box div {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 6px;
            color: var(--text-muted);
        }

        .btn-modal-close {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            background: var(--text-dark);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-modal-close:hover {
            background: #1e293b;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 32px 24px 24px;
                border-radius: 22px;
            }
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="card-header">
        <div class="badge-academic">
            <i class="fa-solid fa-graduation-cap"></i> ZEALHUB ACADEMY
        </div>
        <h2>Staff Portal</h2>
        <p>Institutional Access Control</p>
    </div>

    <?php if($error != ""): ?>
        <div class="error-msg">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <?php if($_SESSION['staff_login_attempts'] < 5): ?>
        <form method="POST">
            <div class="input-group">
                <input type="email" name="staffid" placeholder="Official Email Address" required autocomplete="username">
                <i class="fa-solid fa-envelope input-icon"></i>
            </div>

            <div class="input-group">
                <input type="password" name="password" id="passwordField" placeholder="Account Password" required autocomplete="current-password">
                <i class="fa-solid fa-lock input-icon"></i>
                <!-- Eye Toggle Icon -->
                <i class="fa-solid fa-eye toggle-password" id="eyeIcon"></i>
            </div>

            <div class="helper-row">
                <label class="remember-me">
                    <input type="checkbox"> Remember me
                </label>
                <a href="staff_forget_password.php">Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="btn-login">
                <span>Sign In to Portal</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>
    <?php else: ?>
        <div class="lock-card">
            <div class="lock-icon-wrap">
                <i class="fa-solid fa-user-lock"></i>
            </div>
            <h3 style="color: #0f172a; margin-bottom: 8px; font-weight: 800;">Account Locked</h3>
            <p style="color: #64748b; font-size: 13px; line-height: 1.5;">Maximum login attempts exceeded for security compliance.</p>
            <a href="staff_forget_password.php" style="text-decoration: none;">
                <button type="button" class="btn-login" style="margin-top: 20px; background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); box-shadow: 0 10px 20px -5px rgba(239, 68, 68, 0.4);">
                    <i class="fa-solid fa-key"></i> Verify & Unlock
                </button>
            </a>
        </div>
    <?php endif; ?>

    <div class="card-footer">
        <div>&copy; 2026 Examination Portal • Authorization Required</div>
        <!-- Pop-Up Help Trigger Button -->
        <button type="button" class="btn-help" id="openHelpModal">
            <i class="fa-solid fa-circle-question"></i> Need Help?
        </button>
    </div>
</div>

<!-- ================= HELP POPUP MODAL ================= -->
<div class="modal-overlay" id="helpModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-shield-halved"></i> Login Assistance</h3>
            <button type="button" class="close-modal" id="closeHelpModal">&times;</button>
        </div>

        <div class="help-section">
            <h4><i class="fa-solid fa-key" style="color: #4f46e5;"></i> Forgotten Password Steps:</h4>
            <ul class="help-steps">
                <li data-step="1">Click on the <b>Forgot Password?</b> link on the login screen.</li>
                <li data-step="2">Enter your registered institutional <b>staff email address</b>.</li>
                <li data-step="3">Check your inbox for the password reset verification code / OTP.</li>
                <li data-step="4">Enter the code and set your new account password.</li>
            </ul>
        </div>

        <div class="help-section">
            <h4><i class="fa-solid fa-lock" style="color: #ef4444;"></i> Account Locked Policy:</h4>
            <p style="font-size: 13px; color: var(--text-muted); line-height: 1.5;">
                For security reasons, <b>5 consecutive invalid attempts</b> will temporarily lock your access. Click <b>"Verify & Unlock"</b> to verify identity via email.
            </p>
        </div>

        <div class="contact-box">
            <strong style="color: var(--text-dark);">Need Technical Desk Support?</strong>
            <div><i class="fa-solid fa-envelope" style="color: var(--primary);"></i> support@zealhubacademy.com</div>
            <div><i class="fa-solid fa-phone" style="color: var(--primary);"></i> Academic IT Ext: +1 (800) 555-0199</div>
        </div>

        <button type="button" class="btn-modal-close" id="btnGotIt">Got It, Thanks!</button>
    </div>
</div>

<script>
    // Password Visibility Toggle
    const passwordField = document.getElementById('passwordField');
    const eyeIcon = document.getElementById('eyeIcon');

    if (eyeIcon && passwordField) {
        eyeIcon.addEventListener('click', function () {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

    // Help Modal Pop-up Toggle Logic
    const openHelpModal = document.getElementById('openHelpModal');
    const closeHelpModal = document.getElementById('closeHelpModal');
    const btnGotIt = document.getElementById('btnGotIt');
    const helpModal = document.getElementById('helpModal');

    // Open Modal
    openHelpModal.addEventListener('click', function () {
        helpModal.classList.add('active');
    });

    // Close Modal via 'X' Button
    closeHelpModal.addEventListener('click', function () {
        helpModal.classList.remove('active');
    });

    // Close Modal via 'Got It' Button
    btnGotIt.addEventListener('click', function () {
        helpModal.classList.remove('active');
    });

    // Close Modal when clicking outside the modal content box
    window.addEventListener('click', function (e) {
        if (e.target === helpModal) {
            helpModal.classList.remove('active');
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