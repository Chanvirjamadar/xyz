<?php
session_start();
include("db.php");
date_default_timezone_set('Asia/Kolkata');

$message = ""; 
$msg_type = "";
$show_form = false;
$token = "";
$otp_mode = false;
$email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
$now = date("Y-m-d H:i:s");

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = mysqli_real_escape_string($conn, trim($_GET['token']));

    // Check if token is valid and not expired
    $stmt = $conn->prepare("SELECT id, name, email FROM staff_profile WHERE reset_token = ? AND token_expiry > ?");
    $stmt->bind_param("ss", $token, $now);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $show_form = true;
    } else {
        $message = "Invalid or expired password reset link. Please request a new link or enter your OTP below.";
        $msg_type = "error";
        $otp_mode = true;
    }
} else {
    $otp_mode = true;
    $show_form = true;
}

if (isset($_POST['submit_new_pass'])) {
    $pass = trim($_POST['password']);
    $cpass = trim($_POST['confirm_password']);
    $post_otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    $post_email = isset($_POST['email']) ? trim($_POST['email']) : $email;

    if (empty($pass) || empty($cpass)) {
        $message = "Password fields cannot be empty.";
        $msg_type = "error";
    } elseif ($pass !== $cpass) {
        $message = "Passwords do not match. Please try again.";
        $msg_type = "error";
    } elseif (strlen($pass) < 4) {
        $message = "Password must be at least 4 characters long.";
        $msg_type = "error";
    } else {
        $verified = false;
        $target_email = "";

        if (!empty($token)) {
            // Verify token
            $stmt = $conn->prepare("SELECT email FROM staff_profile WHERE reset_token = ? AND token_expiry > ?");
            $stmt->bind_param("ss", $token, $now);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $target_email = $row['email'];
                $verified = true;
            }
        } elseif (!empty($post_otp) && !empty($post_email)) {
            // Verify OTP
            $stmt = $conn->prepare("SELECT email FROM password_reset WHERE TRIM(email) = ? AND TRIM(otp) = ? AND expiry > ?");
            $stmt->bind_param("sss", $post_email, $post_otp, $now);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $target_email = $row['email'];
                $verified = true;
            }
        }

        if ($verified && !empty($target_email)) {
            // Update password in staff_profile table
            $update_stmt = $conn->prepare("UPDATE staff_profile SET password = ?, reset_token = NULL, token_expiry = NULL WHERE TRIM(email) = ?");
            $update_stmt->bind_param("ss", $pass, $target_email);
            
            if ($update_stmt->execute()) {
                mysqli_query($conn, "DELETE FROM password_reset WHERE TRIM(email) = '$target_email'");
                unset($_SESSION['reset_email']);
                $message = "Your password has been reset successfully! You can now log in with your new password.";
                $msg_type = "success";
                $show_form = false;
            } else {
                $message = "Failed to update password. Please try again.";
                $msg_type = "error";
            }
        } else {
            $message = "Invalid or expired OTP / reset link. Please check your credentials and try again.";
            $msg_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Staff Portal</title>
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: var(--bg-gradient);
            padding: 20px;
        }

        .card {
            background: var(--glass-bg);
            padding: 45px 40px;
            border-radius: 28px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 440px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .icon-lock {
            width: 70px;
            height: 70px;
            background: #eef2ff;
            color: var(--primary);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
        }

        h2 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        p.subtitle {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group i.left-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            transition: 0.3s;
        }

        .input-group i.toggle-pass {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            transition: 0.3s;
        }

        .input-group i.toggle-pass:hover {
            color: var(--primary);
        }

        .input-group input {
            width: 100%;
            padding: 16px 45px 16px 52px;
            border: 2px solid #f1f5f9;
            border-radius: 16px;
            font-size: 15px;
            font-weight: 500;
            transition: 0.3s;
            outline: none;
            color: var(--text-main);
            background: #fff;
        }

        .input-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px;
            width: 100%;
            border-radius: 16px;
            cursor: pointer;
            font-weight: 700;
            font-size: 16px;
            transition: 0.3s;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .alert {
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 500;
            text-align: left;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bcf0da; }
        .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        .btn-login-redirect {
            display: inline-block;
            margin-top: 15px;
            padding: 14px 24px;
            background: #10b981;
            color: white;
            text-decoration: none;
            font-weight: 700;
            border-radius: 14px;
            transition: 0.3s;
        }

        .btn-login-redirect:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 25px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }

        .back-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-lock">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h2>Reset Password</h2>
        <p class="subtitle">Enter and confirm your new password below.</p>

        <?php if($message != ""): ?>
            <div class="alert alert-<?php echo $msg_type; ?>">
                <i class="fa-solid <?php echo ($msg_type == 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>" style="margin-top:2px;"></i>
                <div><?php echo $message; ?></div>
            </div>
        <?php endif; ?>

        <?php if($msg_type == 'success'): ?>
            <a href="staff_login.php" class="btn-login-redirect">
                <i class="fa-solid fa-right-to-bracket"></i> Proceed to Login
            </a>
        <?php endif; ?>

        <?php if($show_form): ?>
            <form method="POST">
                <?php if($otp_mode): ?>
                    <div class="input-group">
                        <input type="email" name="email" placeholder="Registered Email Address" value="<?php echo htmlspecialchars($email); ?>" required>
                        <i class="fa-solid fa-envelope left-icon"></i>
                    </div>
                    <div class="input-group">
                        <input type="text" name="otp" maxlength="6" placeholder="Enter 6-Digit OTP Code" style="letter-spacing: 4px; font-weight: bold; text-align: center;" required>
                        <i class="fa-solid fa-key left-icon"></i>
                    </div>
                <?php endif; ?>

                <div class="input-group">
                    <input type="password" id="pass1" name="password" placeholder="New Password" required>
                    <i class="fa-solid fa-lock left-icon"></i>
                    <i class="fa-solid fa-eye toggle-pass" onclick="toggleVisibility('pass1', this)"></i>
                </div>

                <div class="input-group">
                    <input type="password" id="pass2" name="confirm_password" placeholder="Confirm Password" required>
                    <i class="fa-solid fa-shield-halved left-icon"></i>
                    <i class="fa-solid fa-eye toggle-pass" onclick="toggleVisibility('pass2', this)"></i>
                </div>

                <button type="submit" name="submit_new_pass" class="btn">
                    <i class="fa-solid fa-check-circle"></i> Verify & Update Password
                </button>
            </form>
        <?php endif; ?>

        <?php if(!$show_form && $msg_type != 'success'): ?>
            <a href="staff_forget_password.php" class="back-link">
                <i class="fa-solid fa-rotate-left"></i> Request New Reset Link
            </a>
        <?php endif; ?>
    </div>

    <script>
        function toggleVisibility(fieldId, icon) {
            const field = document.getElementById(fieldId);
            if (field.type === "password") {
                field.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                field.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>