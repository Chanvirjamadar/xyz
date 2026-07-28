<?php
session_start();
include("db.php");
include("SmtpMailer.php");

$message = ""; 
$msg_type = "";
date_default_timezone_set('Asia/Kolkata');

// --- SMTP CONFIGURATION CREDENTIALS ---
$smtp_host = "smtp.gmail.com";
$smtp_port = 465; // 465 for SSL
$smtp_user = "shivputrajamadar057@gmail.com"; // Sender Email
$smtp_pass = "wtwegsjptxthquig";            // App Password

if (isset($_POST['reset_request'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));

    // Check if staff email exists
    $stmt = $conn->prepare("SELECT id, name FROM staff_profile WHERE TRIM(email) = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $staff = $result->fetch_assoc();
        $token = bin2hex(random_bytes(32));
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $expiry = date("Y-m-d H:i:s", strtotime("+30 minutes"));

        // Store reset token & expiry in staff_profile table
        $update_stmt = $conn->prepare("UPDATE staff_profile SET reset_token = ?, token_expiry = ? WHERE TRIM(email) = ?");
        $update_stmt->bind_param("sss", $token, $expiry, $email);
        $update_stmt->execute();

        // Also store OTP in password_reset table
        mysqli_query($conn, "DELETE FROM password_reset WHERE TRIM(email) = '$email'");
        $insert_otp = $conn->prepare("INSERT INTO password_reset (email, otp, expiry) VALUES (?, ?, ?)");
        $insert_otp->bind_param("sss", $email, $otp, $expiry);
        $insert_otp->execute();

        // Build dynamic reset password URL
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        $reset_link = "$protocol://$host$dir/staff_reset_password.php?token=" . $token;

        // Build HTML Email Body with OTP & Reset Link
        $subject = "Password Reset OTP & Link - Staff Portal";
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h1 style='color: #4f46e5; margin: 0; font-size: 24px;'>Staff Administration Portal</h1>
                <p style='color: #64748b; font-size: 14px; margin-top: 4px;'>Password Reset Request</p>
            </div>
            <div style='padding: 20px; background-color: #f8fafc; border-radius: 12px; margin-bottom: 20px;'>
                <p style='color: #334155; font-size: 15px; margin-top: 0;'>Hello <strong>" . htmlspecialchars($staff['name']) . "</strong>,</p>
                <p style='color: #475569; font-size: 14px; line-height: 1.6;'>
                    We received a request to reset your password for the Staff Portal. You can verify using your 6-digit OTP code below, or click the direct reset link. Both are valid for <strong>30 minutes</strong>.
                </p>
                <div style='text-align: center; margin: 25px 0;'>
                    <div style='font-size: 13px; color: #64748b; margin-bottom: 6px;'>YOUR 6-DIGIT OTP CODE:</div>
                    <span style='background: #4f46e5; color: #ffffff; font-size: 32px; font-weight: 800; letter-spacing: 6px; padding: 12px 30px; border-radius: 10px; display: inline-block;'>$otp</span>
                </div>
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='$reset_link' style='background-color: #059669; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; font-size: 15px;'>Direct Reset Link</a>
                </div>
                <p style='color: #64748b; font-size: 13px; text-align: center;'>If you did not request a password reset, please ignore this email.</p>
            </div>
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='color: #94a3b8; font-size: 12px; text-align: center; margin: 0;'>Staff Administration Portal • Secured Automated System</p>
        </div>";

        $smtp = new SmtpMailer($smtp_host, $smtp_port, $smtp_user, $smtp_pass);
        $mailSent = @$smtp->send($email, $subject, $body);
        
        $_SESSION['reset_email'] = $email;
        if ($mailSent) {
            $message = "An OTP code and password reset link have been sent to <strong>" . htmlspecialchars($email) . "</strong>. Check your inbox! <br><br><a href='staff_reset_password.php' style='color:#4f46e5; font-weight:bold;'>Click here to Enter OTP directly</a>";
            $msg_type = "success";
        } else {
            $message = "OTP & Token Generated! <strong>(Development Mode: OTP is <u>$otp</u>)</strong>. <br><br><a href='staff_reset_password.php?token=$token' style='color:#4f46e5; font-weight:bold;'>Click here to Reset Password directly</a>";
            $msg_type = "success";
        }
    } else {
        $message = "No staff account found with that email address.";
        $msg_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | ZEELHUB Academy</title>
    <!-- Google Fonts & FontAwesome -->
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

        /* Card Container */
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
            margin-bottom: 28px;
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
            line-height: 1.5;
        }

        /* Form Controls */
        .input-group {
            position: relative;
            margin-bottom: 24px;
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

        .input-group input {
            width: 100%;
            padding: 16px 20px 16px 50px;
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

        /* Submit Button */
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

        /* Alert Notifications */
        .alert {
            padding: 14px 16px;
            margin-bottom: 22px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            text-align: left;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fef3c7;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2;
        }

        .alert i {
            font-size: 16px;
            margin-top: 2px;
        }

        .alert-success i { color: #22c55e; }
        .alert-warning i { color: #f59e0b; }
        .alert-error i { color: #ef4444; }

        /* Footer & Back Link */
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

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            background: #eef2ff;
            border-color: #c7d2fe;
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
        <h2>Forgot Password?</h2>
        <p>Enter your official staff email address to receive a secure password reset link and OTP.</p>
    </div>

    <?php if($message != ""): ?>
        <div class="alert alert-<?php echo $msg_type; ?>">
            <i class="fa-solid <?php echo ($msg_type == 'success') ? 'fa-circle-check' : (($msg_type == 'warning') ? 'fa-triangle-exclamation' : 'fa-circle-exclamation'); ?>"></i>
            <div><?php echo $message; ?></div>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <input type="email" name="email" placeholder="staff@example.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" autocomplete="email">
            <i class="fa-solid fa-envelope input-icon"></i>
        </div>

        <button type="submit" name="reset_request" class="btn-login">
            <span>Send Reset Link</span>
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </form>

    <div class="card-footer">
        <a href="staff_login.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Login
        </a>
        <div>&copy; 2026 Examination Portal • Authorization Required</div>
    </div>
</div>

</body>
</html>