<?php
session_start();

$isConfirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'true';

if ($isConfirmed) {
    // Perform session destruction on explicit user confirmation
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isConfirmed ? 'Student Logged Out' : 'Confirm Logout' ?> | ZEALHUB</title>
    <?php if ($isConfirmed): ?>
        <meta http-equiv="refresh" content="3;url=student_login.php">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #4361ee 100%);
            overflow: hidden;
            position: relative;
        }

        /* Ambient Orbs */
        body::before {
            content: "";
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(67, 97, 238, 0.25);
            filter: blur(80px);
            border-radius: 50%;
            top: -150px;
            left: -150px;
        }

        body::after {
            content: "";
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(99, 102, 241, 0.2);
            filter: blur(80px);
            border-radius: 50%;
            bottom: -120px;
            right: -120px;
        }

        .logout-box {
            width: min(90%, 460px);
            padding: 40px 30px;
            text-align: center;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            position: relative;
            z-index: 10;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.35);
        }

        .icon-wrapper {
            width: 85px;
            height: 85px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .icon-wrapper.danger {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
            color: #f87171;
        }

        .icon-wrapper.success {
            background: rgba(16, 185, 129, 0.2);
            border-color: rgba(16, 185, 129, 0.4);
            color: #34d399;
        }

        h1 {
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        p {
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.85);
            font-size: 14.5px;
            margin-bottom: 25px;
        }

        .loader {
            width: 80%;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            overflow: hidden;
            margin: 20px auto 25px;
        }

        .progress {
            width: 0%;
            height: 100%;
            background: #ffffff;
            animation: load 3s linear forwards;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 13px 26px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s ease;
            cursor: pointer;
        }

        .btn-primary {
            background: #ffffff;
            color: #1e1b4b;
        }

        .btn-primary:hover {
            background: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.3);
        }

        .btn-danger {
            background: #ef4444;
            color: #ffffff;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-cancel {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .footer {
            margin-top: 25px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }

        @keyframes load {
            from { width: 0%; }
            to { width: 100%; }
        }

        @media (max-width: 480px) {
            .btn-group { flex-direction: column; width: 100%; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>

<body>

    <div class="logout-box">
        <?php if ($isConfirmed): ?>
            <!-- CONFIRMED LOGOUT VIEW -->
            <div class="icon-wrapper success">
                <i class="fa-solid fa-check"></i>
            </div>
            <h1>Logged Out Successfully</h1>
            <p>You have been signed out of the <strong>ZEALHUB Student Portal</strong>.<br>Redirecting to student login page...</p>

            <div class="loader">
                <div class="progress"></div>
            </div>

            <div class="btn-group">
                <a href="student_login.php" class="btn btn-primary">
                    <i class="fa-solid fa-right-to-bracket"></i> Login Again
                </a>
                <a href="student_dashboard.php" class="btn btn-cancel">
                    <i class="fa-solid fa-xmark"></i> Cancel / Dashboard
                </a>
            </div>
        <?php else: ?>
            <!-- CONFIRMATION PROMPT VIEW -->
            <div class="icon-wrapper danger">
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>
            <h1>Confirm Student Logout</h1>
            <p>Are you sure you want to log out of your <strong>ZEALHUB Student Account</strong>?</p>

            <div class="btn-group">
                <a href="student_dashboard.php" class="btn btn-cancel">
                    <i class="fa-solid fa-xmark"></i> Cancel
                </a>
                <a href="student_logout.php?confirm=true" class="btn btn-danger">
                    <i class="fa-solid fa-right-from-bracket"></i> Yes, Logout
                </a>
            </div>
        <?php endif; ?>

        <div class="footer">
            © <?= date('Y') ?> ZEALHUB Academic Portal
        </div>
    </div>

</body>
</html>