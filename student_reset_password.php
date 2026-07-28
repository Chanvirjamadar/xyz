<?php
session_start();
include("db.php");
date_default_timezone_set('Asia/Kolkata');

$message = "";
$msg_type = "";
$email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';

if (isset($_POST['verify_otp_submit'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $otp = mysqli_real_escape_string($conn, trim($_POST['otp']));
    $new_pass = trim($_POST['new_password']);
    $confirm_pass = trim($_POST['confirm_password']);
    $now = date("Y-m-d H:i:s");

    if (empty($email) || empty($otp) || empty($new_pass) || empty($confirm_pass)) {
        $message = "Please fill in all required fields.";
        $msg_type = "error";
    } elseif ($new_pass !== $confirm_pass) {
        $message = "Passwords do not match. Please try again.";
        $msg_type = "error";
    } elseif (strlen($new_pass) < 4) {
        $message = "Password must be at least 4 characters long.";
        $msg_type = "error";
    } else {
        // Verify OTP against password_reset table
        $check_query = mysqli_query($conn, "SELECT * FROM password_reset WHERE TRIM(email) = '$email' AND TRIM(otp) = '$otp' AND expiry > '$now'");

        if ($check_query && mysqli_num_rows($check_query) > 0) {
            // Update student password
            $update = mysqli_query($conn, "UPDATE student SET password = '$new_pass' WHERE TRIM(email) = '$email'");

            if ($update) {
                // Delete OTP after successful reset
                mysqli_query($conn, "DELETE FROM password_reset WHERE TRIM(email) = '$email'");
                unset($_SESSION['reset_email']);
                $message = "Your password has been reset successfully! You can now log in with your new password.";
                $msg_type = "success";
            } else {
                $message = "Failed to update password in database.";
                $msg_type = "error";
            }
        } else {
            $message = "Invalid or expired OTP. Please request a new OTP.";
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
    <title>Verify OTP & Reset Password | Student Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --bg-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --text-main: #1f2937;
            --text-muted: #6b7280;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-gradient);
            padding: 20px;
        }

        .card {
            background: white;
            padding: 40px;
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            text-align: center;
        }

        .icon-box {
            width: 64px;
            height: 64px;
            background: #eef2ff;
            color: var(--primary);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 20px;
        }

        h2 { font-size: 24px; font-weight: 800; color: var(--text-main); margin-bottom: 8px; }
        p.subtitle { color: var(--text-muted); font-size: 14px; margin-bottom: 25px; line-height: 1.5; }

        .input-group {
            position: relative;
            margin-bottom: 18px;
            text-align: left;
        }

        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 500;
            outline: none;
            transition: 0.3s;
        }

        .input-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .otp-input {
            letter-spacing: 4px;
            font-size: 18px !important;
            font-weight: 700 !important;
            text-align: center;
            padding-left: 16px !important;
        }

        button {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 14px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bcf0da; }

        .btn-proceed {
            display: inline-block;
            margin-top: 15px;
            padding: 12px 24px;
            background: #10b981;
            color: white;
            text-decoration: none;
            font-weight: 700;
            border-radius: 12px;
        }

        .back { display: inline-block; margin-top: 20px; text-decoration: none; color: var(--text-muted); font-size: 14px; font-weight: 600; }
        .back:hover { color: var(--primary); }
    </style>
</head>
<body>

<div class="card">
    <div class="icon-box">
        <i class="fa-solid fa-shield-halved"></i>
    </div>
    <h2>Enter OTP & Reset Password</h2>
    <p class="subtitle">Enter the 6-digit OTP code sent to your email to verify and reset your password.</p>

    <?php if($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>">
            <i class="fa-solid <?php echo ($msg_type == 'success') ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
            <div><?php echo $message; ?></div>
        </div>
    <?php endif; ?>

    <?php if($msg_type == 'success'): ?>
        <a href="student_login.php" class="btn-proceed">
            <i class="fa-solid fa-right-to-bracket"></i> Proceed to Login
        </a>
    <?php else: ?>
        <form method="POST">
            <div class="input-group">
                <input type="email" name="email" placeholder="Registered Email" value="<?php echo htmlspecialchars($email); ?>" required>
                <i class="fa-solid fa-envelope"></i>
            </div>

            <div class="input-group">
                <input type="text" name="otp" class="otp-input" maxlength="6" placeholder="Enter 6-Digit OTP" required>
            </div>

            <div class="input-group">
                <input type="password" name="new_password" placeholder="New Password" required>
                <i class="fa-solid fa-lock"></i>
            </div>

            <div class="input-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                <i class="fa-solid fa-key"></i>
            </div>

            <button type="submit" name="verify_otp_submit">
                <i class="fa-solid fa-check-double"></i> Verify OTP & Reset Password
            </button>
        </form>
    <?php endif; ?>

    <a href="student_forget_password.php" class="back">← Resend OTP / Change Email</a>
</div>

</body>
</html>
