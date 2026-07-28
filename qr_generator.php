<?php
// Consolidated QR helper: defer to includes/security.php which defines getStudentQRUrl
require_once __DIR__ . '/security.php';

if (!function_exists('generateStudentQR')) {
    function generateStudentQR($studentId) {
        return [
            'success' => true,
            'path' => 'generate_qr.php?text=' . urlencode((string) $studentId) . '&size=200&t=' . time(),
            'url' => function_exists('getStudentQRUrl') ? getStudentQRUrl($studentId) : ('generate_qr.php?text=' . urlencode((string)$studentId))
        ];
    }
}
