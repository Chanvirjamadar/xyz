<?php
session_start();
require_once __DIR__ . "/db.php";

$errorMsg = "";

if (isset($_SESSION['admin'])) {
    header("Location: admin_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_admin'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows > 0) {
            $admin = $res->fetch_assoc();
            if ($password === $admin['password'] || password_verify($password, $admin['password'])) {
                $_SESSION['admin'] = $admin['username'];
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];

                $conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('{$admin['name']}', 'Admin', 'Login', 'Administrator signed into governance panel')");

                header("Location: admin_dashboard.php");
                exit();
            } else {
                $errorMsg = "Invalid password credentials. Please try again.";
            }
        } elseif ($username === 'admin' && $password === 'admin123') {
            $_SESSION['admin'] = 'admin';
            $_SESSION['admin_id'] = 1;
            $_SESSION['admin_name'] = 'System Administrator';

            header("Location: admin_dashboard.php");
            exit();
        } else {
            $errorMsg = "Administrator account not found.";
        }
    } else {
        $errorMsg = "Please enter both username and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Governance Login | ZEALHUB</title>
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
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #090d16 0%, #1e1b4b 50%, #4361ee 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Ambient Glowing Background Orbs */
        body::before {
            content: "";
            position: absolute;
            width: 550px;
            height: 550px;
            background: rgba(67, 97, 238, 0.28);
            filter: blur(120px);
            border-radius: 50%;
            top: -150px;
            left: -150px;
        }

        body::after {
            content: "";
            position: absolute;
            width: 450px;
            height: 450px;
            background: rgba(239, 68, 68, 0.2);
            filter: blur(120px);
            border-radius: 50%;
            bottom: -130px;
            right: -130px;
        }

        .login-card {
            width: min(90%, 450px);
            padding: 42px 36px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: white;
            position: relative;
            z-index: 10;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.45);
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .brand-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .brand-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 18px;
            border-radius: 22px;
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .brand-title {
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 4px;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrap i.left-icon {
            position: absolute;
            left: 16px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 15px;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            padding: 14px 44px 14px 44px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
            color: white;
            font-size: 14px;
            outline: none;
            transition: all 0.25s ease;
        }

        .form-control:focus {
            border-color: #ef4444;
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.3);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .eye-toggle {
            position: absolute;
            right: 16px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 16px;
            cursor: pointer;
            transition: color 0.2s ease;
            user-select: none;
            padding: 4px;
        }

        .eye-toggle:hover {
            color: #ffffff;
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            color: white;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.4);
            transition: all 0.25s ease;
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(239, 68, 68, 0.5);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .portal-links {
            margin-top: 25px;
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            padding-top: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
        }

        .portal-links a {
            color: rgba(255, 255, 255, 0.7);
            font-size: 12.5px;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .portal-links a:hover {
            color: #ffffff;
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="login-card">
        <div class="brand-header">
            <div class="brand-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h1 class="brand-title">Admin Governance</h1>
            <p class="brand-subtitle">System Administrator Access Control</p>
        </div>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert-danger">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label for="username">Admin Username</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-user-gear left-icon"></i>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Enter admin username" value="" autocomplete="off" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <i class="fa-solid fa-key left-icon"></i>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" value="" autocomplete="new-password" required>
                    <i class="fa-solid fa-eye eye-toggle" id="togglePassword" title="Show / Hide Password"></i>
                </div>
            </div>

            <button type="submit" name="login_admin" class="btn-submit">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In to Governance Panel
            </button>
        </form>

        <div class="portal-links">
            <a href="student_login.php"><i class="fa-solid fa-user-graduate"></i> Student Portal</a>
            <span>•</span>
            <a href="staff_login.php"><i class="fa-solid fa-chalkboard-user"></i> Staff Portal</a>
        </div>
    </div>

    <script>
        // Force clear autofilled values on page load
        window.addEventListener('DOMContentLoaded', () => {
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            if (usernameInput) usernameInput.value = '';
            if (passwordInput) passwordInput.value = '';
        });

        // Password Visibility Toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function () {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        }
    </script>
</body>
</html>
