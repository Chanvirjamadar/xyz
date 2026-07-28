<?php
session_start();
include("db.php");

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
            overflow: hidden;
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
            gap: 10px;
        }

        .help-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s ease;
            background: #f8fafc;
            padding: 6px 14px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }

        .help-link:hover {
            background: #eef2ff;
            color: var(--primary-hover);
            transform: translateY(-1px);
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
            <i class="fa-solid fa-graduation-cap"></i> ZEELHUB ACADEMY
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
        <!-- Help Link Button -->
        <a href="staff_help.php" class="help-link">
            <i class="fa-solid fa-circle-question"></i> Need Help?
        </a>
    </div>
</div>

<script>
    // Show/Hide Password Toggle
    const passwordField = document.getElementById('passwordField');
    const eyeIcon = document.getElementById('eyeIcon');

    if (eyeIcon && passwordField) {
        eyeIcon.addEventListener('click', function () {
            // Toggle type
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            // Toggle icon classes
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
</script>

</body>
</html>