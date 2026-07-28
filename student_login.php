<?php
session_start();
require_once __DIR__ . "/db.php";

// Initialize dynamic password if not set (Default: 12345)
if (!isset($_SESSION['student_password'])) {
    $_SESSION['student_password'] = "12345";
}

$error = "";

// Initialize login attempts
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

if (isset($_POST['login'])) {
    $id = trim($_POST['studentid'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    $authenticated = false;

    // 1. Authenticate against student table in MySQL DB
    if (!empty($id)) {
        $stmt = $conn->prepare("SELECT * FROM student WHERE CAST(id AS CHAR) = ? OR prn = ? OR roll_no = ? OR email = ?");
        if ($stmt) {
            $stmt->bind_param("ssss", $id, $id, $id, $id);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                $studentRow = $res->fetch_assoc();
                $dbPass = $studentRow['password'] ?? '12345';

                if ($pass === $dbPass || password_verify($pass, $dbPass)) {
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['student'] = $studentRow['id'];
                    $_SESSION['student_name'] = $studentRow['name'];

                    $stName = mysqli_real_escape_string($conn, $studentRow['name']);
                    @$conn->query("INSERT INTO portal_activity (user_name, user_role, action_type, message) VALUES ('$stName', 'Student', 'Login', 'Student logged into dashboard')");

                    $authenticated = true;
                }
            }
        }
    }

    // 2. Default hardcoded fallback check (e.g. Student ID "123" or "1")
    if (!$authenticated && ($id === "123" || $id === "1") && ($pass === $_SESSION['student_password'] || $pass === "12345")) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['student'] = "123";
        $authenticated = true;
    }

    if ($authenticated) {
        header("Location: student_dashboard.php");
        exit();
    } else {
        $_SESSION['login_attempts']++;
        $remaining = 5 - $_SESSION['login_attempts'];

        if ($_SESSION['login_attempts'] >= 5) {
            $error = "Account Locked! Please reset your password.";
        } else {
            $error = "Invalid Credentials! Check your Student ID and Password. Attempts left: <b>$remaining</b>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal | Institutional Access Control</title>
    <!-- Plus Jakarta Sans Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        :root {
            --primary: #5850ec;
            --primary-hover: #4338ca;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --bg-input: #f8fafc;
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Plus Jakarta Sans', sans-serif; 
        }

        body {
            height: 100vh;
            width: 100vw;
            display: flex;
            justify-content: center;
            align-items: center;
            /* Bright & Vibrant Academic Campus Background Image */
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(15, 23, 42, 0.15)), 
                        url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?q=80&w=1920&auto=format&fit=crop') no-repeat center center/cover;
            padding: 20px;
            overflow: hidden;
        }

        /* Centered Floating Card */
        .login-card {
            width: 100%;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(12px);
            border-radius: 32px;
            padding: 45px 40px;
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.22), 0 0 0 1px rgba(255, 255, 255, 0.6) inset;
            text-align: center;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Top Pill Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eef2ff;
            color: #4f46e5;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
        }

        .login-card h2 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 6px;
        }

        .login-card p.subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 30px;
        }

        /* Input Fields */
        .input-group {
            position: relative;
            margin-bottom: 18px;
        }

        .input-group input {
            width: 100%;
            padding: 16px 48px 16px 48px;
            background: var(--bg-input);
            border: 1.5px solid var(--border-color);
            border-radius: 16px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-main);
            outline: none;
            transition: all 0.25s ease;
        }

        .input-group input::placeholder {
            color: #94a3b8;
        }

        .input-group input:focus {
            background: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(88, 80, 236, 0.12);
        }

        .input-group i.input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
            pointer-events: none;
            transition: 0.2s;
        }

        .input-group input:focus ~ i.input-icon {
            color: var(--primary);
        }

        .input-group i.toggle-password {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
            cursor: pointer;
            padding: 4px;
            transition: 0.2s;
        }

        .input-group i.toggle-password:hover {
            color: var(--primary);
        }

        /* Error Message Box */
        .error-msg {
            background: #fff1f2;
            color: #e11d48;
            padding: 12px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 1px solid #ffe4e6;
        }

        /* Form Row: Remember me & Forgot Password */
        .flex-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-size: 13.5px;
        }

        .flex-row label {
            color: #475569;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .flex-row label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            accent-color: var(--primary);
            cursor: pointer;
        }

        .flex-row a {
            text-decoration: none;
            color: var(--primary);
            font-weight: 700;
            transition: color 0.2s;
        }

        .flex-row a:hover {
            color: var(--primary-hover);
        }

        /* Main Submit Button */
        .btn-submit {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 16px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(79, 70, 229, 0.5);
        }

        .btn-submit:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Footer inside card */
        .card-footer {
            margin-top: 35px;
        }

        .copyright-text {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 15px;
        }

        /* Need Help Pill Button */
        .help-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f1f5f9;
            color: #4f46e5;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s;
        }

        .help-btn:hover {
            background: #e2e8f0;
        }

        @media (max-width: 520px) {
            .login-card {
                padding: 35px 25px;
                border-radius: 24px;
            }
        }
    </style>
</head>
<body>

<div class="login-card">
    <!-- Top Pill Badge -->
    <div class="badge">
        <i class="fa-solid fa-graduation-cap"></i> ZEELHUB ACADEMY
    </div>

    <!-- Headers -->
    <h2>Student Portal</h2>
    <p class="subtitle">Institutional Access Control</p>

    <!-- Error Message Display -->
    <?php if(!empty($error)): ?>
        <div class="error-msg">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST">
        <div class="input-group">
            <input type="text" name="studentid" placeholder="Student ID / Official Email" required>
            <i class="fa-regular fa-envelope input-icon"></i>
        </div>

        <div class="input-group">
            <input type="password" name="password" id="passwordInput" placeholder="Account Password" required 
            <?php echo ($_SESSION['login_attempts'] >= 5) ? 'disabled' : ''; ?>>
            <i class="fa-solid fa-lock input-icon"></i>
            <i class="fa-regular fa-eye toggle-password" id="togglePassword" title="Show/Hide Password"></i>
        </div>

        <div class="flex-row">
            <label>
                <input type="checkbox"> 
                Remember me
            </label>
            <a href="student_forget_password.php">Forgot Password?</a>
        </div>

        <?php if($_SESSION['login_attempts'] < 5): ?>
            <button name="login" type="submit" class="btn-submit">
                Sign In to Portal <i class="fa-solid fa-arrow-right"></i>
            </button>
        <?php else: ?>
            <a href="student_forget_password.php" style="text-decoration:none;">
                <button type="button" class="btn-submit" style="background:#e11d48; box-shadow:none;">
                    Reset My Account
                </button>
            </a>
        <?php endif; ?>
    </form>

    <!-- Footer Area -->
    <div class="card-footer">
        <div class="copyright-text">
            &copy; 2026 Examination Portal • Authorization Required
        </div>
        <a href="#" class="help-btn">
            <i class="fa-solid fa-circle-question"></i> Need Help?
        </a>
    </div>
</div>

<script>
    // Eye Icon Password Toggle Script
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('passwordInput');

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye / eye-slash icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
</script>

</body>
</html>