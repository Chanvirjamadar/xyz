<?php
session_start();
include("db.php");
include("SmtpMailer.php");
date_default_timezone_set('Asia/Kolkata');

$message = "";
$error = "";

if (isset($_POST['request_link'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    
    // 1. Check if email exists in student table
    $user_query = mysqli_query($conn, "SELECT * FROM student WHERE TRIM(email) = '$email'");
    
    if ($user_query && mysqli_num_rows($user_query) > 0) {
        $student = mysqli_fetch_assoc($user_query);
        $student_name = !empty($student['name']) ? $student['name'] : 'Student';
        
        // 2. Generate 6-digit OTP and expiration (15 mins)
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $expiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));
        
        // 3. Save OTP in password_reset table
        mysqli_query($conn, "DELETE FROM password_reset WHERE email = '$email'");
        $insert = mysqli_query($conn, "INSERT INTO password_reset (email, otp, expiry) VALUES ('$email', '$otp', '$expiry')");
        
        // 4. Send Email via SmtpMailer (with fallback for local dev)
        $mailer = new SmtpMailer();
        $mailSent = @$mailer->sendOTP($email, $otp, $student_name);
        
        $_SESSION['reset_email'] = $email;
        if ($mailSent) {
            $message = "A 6-digit OTP has been sent to <strong>" . htmlspecialchars($email) . "</strong>. <br><br><a href='student_reset_password.php' style='color:#4f46e5; font-weight:bold; text-decoration:underline;'>Click here to Enter OTP & Reset Password</a>";
        } else {
            $message = "OTP Generated! <strong>(Development Mode: OTP is <u>$otp</u>)</strong>. <br><br><a href='student_reset_password.php' style='color:#4f46e5; font-weight:bold; text-decoration:underline;'>Click here to Enter OTP & Reset Password</a>";
        }
    } else {
        $error = "No student account found with that email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | ZealHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #4f46e5; --bg: #f3f4f6; }
        body { font-family: 'Inter', sans-serif; background: #667eea; height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 20px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); text-align: center; }
        h2 { margin-bottom: 10px; color: #1f2937; }
        p { color: #6b7280; font-size: 14px; margin-bottom: 25px; }
        input { width: 100%; padding: 12px; margin-bottom: 20px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: var(--primary); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #dc2626; }
        .alert-success { background: #dcfce7; color: #16a34a; }
        .back { display: block; margin-top: 20px; text-decoration: none; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>

<div class="card">
    <h2>Forgot Password?</h2>
    <p>Enter your email and we'll send you a link to reset your password.</p>

    <?php if($error) echo "<div class='alert alert-error'>$error</div>"; ?>
    <?php if($message) echo "<div class='alert alert-success'>$message</div>"; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Enter your registered email" required>
        <button type="submit" name="request_link">Send Reset Link</button>
    </form>

    <a href="student_login.php" class="back">← Back to Login</a>
</div>

</body>
</html>