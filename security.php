<?php

if (!function_exists('getStudentQRUrl')) {
    function getStudentQRUrl($studentId) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Normalize host: allow only safe characters (letters, numbers, dot, dash, colon)
        $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', $host);
        if (empty($host)) $host = 'localhost';
        $scriptDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        return $protocol . '://' . $host . $scriptDir . '/generate_qr.php?text=' . urlencode((string) $studentId) . '&size=200&t=' . time();
    }
}
