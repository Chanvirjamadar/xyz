<?php
class SmtpMailer {
    private $host;
    private $port;
    private $user;
    private $pass;

    public function __construct($host = "smtp.gmail.com", $port = 465, $user = "shivputrajamadar057@gmail.com", $pass = "wtwegsjptxthquig") {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function send($to, $subject, $message) {
        $header = "To: $to\r\n";
        $header .= "From: ZealHub Study Portal <".$this->user.">\r\n";
        $header .= "Subject: $subject\r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "Content-Type: text/html; charset=UTF-8\r\n";
        $header .= "Content-Transfer-Encoding: 7bit\r\n\r\n";

        // Open connection (SSL)
        $socket = @fsockopen("ssl://" . $this->host, $this->port, $errno, $errstr, 30);
        if (!$socket) return false;

        $greeting = $this->getResponse($socket);
        if (substr($greeting, 0, 3) != "220") { fclose($socket); return false; }

        fwrite($socket, "EHLO " . $this->host . "\r\n");
        $this->getResponse($socket);

        fwrite($socket, "AUTH LOGIN\r\n");
        $this->getResponse($socket);

        fwrite($socket, base64_encode($this->user) . "\r\n");
        $this->getResponse($socket);

        fwrite($socket, base64_encode($this->pass) . "\r\n");
        $auth_resp = $this->getResponse($socket);
        if (substr($auth_resp, 0, 3) != "235") { fclose($socket); return false; }

        fwrite($socket, "MAIL FROM: <" . $this->user . ">\r\n");
        $this->getResponse($socket);

        fwrite($socket, "RCPT TO: <" . $to . ">\r\n");
        $this->getResponse($socket);

        fwrite($socket, "DATA\r\n");
        $this->getResponse($socket);

        fwrite($socket, $header . $message . "\r\n.\r\n");
        $data_resp = $this->getResponse($socket);

        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        
        return (substr($data_resp, 0, 3) == "250");
    }

    public function sendOTP($to, $otp, $name = "User") {
        $subject = "Your Password Reset OTP - ZealHub Portal";
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 25px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h1 style='color: #4f46e5; margin: 0; font-size: 24px;'>ZealHub Portal</h1>
                <p style='color: #64748b; font-size: 14px; margin-top: 4px;'>Password Reset Verification Code</p>
            </div>
            <div style='padding: 20px; background-color: #f8fafc; border-radius: 12px; margin-bottom: 20px;'>
                <p style='color: #334155; font-size: 15px; margin-top: 0;'>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p style='color: #475569; font-size: 14px; line-height: 1.6;'>
                    You requested a password reset for your account. Please use the following 6-digit One-Time Password (OTP) to verify your identity. This code is valid for <strong>15 minutes</strong>.
                </p>
                <div style='text-align: center; margin: 25px 0;'>
                    <span style='background: #4f46e5; color: #ffffff; font-size: 32px; font-weight: 800; letter-spacing: 6px; padding: 12px 30px; border-radius: 10px; display: inline-block;'>$otp</span>
                </div>
                <p style='color: #dc2626; font-size: 13px; font-weight: 600; text-align: center;'>
                    ⚠️ Do not share this OTP with anyone. Portal staff will never ask for your code.
                </p>
            </div>
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='color: #94a3b8; font-size: 12px; text-align: center; margin: 0;'>
                ZealHub Study Material Portal • Secure Automated Mailer
            </p>
        </div>";

        return $this->send($to, $subject, $body);
    }

    private function getResponse($socket) {
        $response = "";
        while ($str = fgets($socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $response;
    }
}
?>