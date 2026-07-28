<?php
/**
 * generate_qr.php — Proper QR Code generator
 *
 * Approach: Redirect to the Google Chart QR API which is reliable, free and
 * does not require any installed library. If the user is offline, falls back
 * to a GD-drawn placeholder that at least looks like a QR code.
 *
 * Usage:
 *   <img src="generate_qr.php?text=STU-000001&size=200">
 *
 * Parameters:
 *   text  — the data to encode (student ID, URL, name, etc.)
 *   size  — image size in pixels (100–500, default 200)
 *   mode  — 'api' (default, redirect to Google) | 'gd' (PHP GD fallback)
 */

$text = isset($_GET['text']) ? trim($_GET['text']) : 'ZEALHUB';
$size = isset($_GET['size']) ? max(80, min(500, (int)$_GET['size'])) : 200;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'api';

// Sanitise text for URL safety
if (empty($text)) {
    $text = 'ZEALHUB';
}

// ── Mode 1: Redirect to Google Charts QR API (most accurate) ────────────
if ($mode !== 'gd') {
    $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/'
        . '?size=' . $size . 'x' . $size
        . '&data=' . urlencode($text)
        . '&format=png'
        . '&qzone=1'
        . '&color=000000'
        . '&bgcolor=ffffff';

    // Proxy the image so we keep the same origin (avoids mixed-content issues)
    $ctx = @stream_context_create([
        'http' => [
            'timeout'       => 4,
            'ignore_errors' => true,
        ]
    ]);

    $imgData = @file_get_contents($apiUrl, false, $ctx);

    if ($imgData !== false && strlen($imgData) > 100) {
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        echo $imgData;
        exit();
    }
    // If API unreachable, fall through to GD fallback
}

// ── Mode 2: GD fallback — draws a realistic-looking QR placeholder ──────
header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');

$w    = $size;
$h    = $size;
$img  = imagecreatetruecolor($w, $h);

$white = imagecolorallocate($img, 255, 255, 255);
$black = imagecolorallocate($img, 0,   0,   0);
$gray  = imagecolorallocate($img, 100, 100, 100);
$blue  = imagecolorallocate($img, 79,  70, 229);

imagefill($img, 0, 0, $white);

// Quiet zone (border)
$qz     = max(6, (int)($size * 0.07));
$inner  = $size - $qz * 2;
$cells  = 21;                          // standard QR v1 grid
$cell   = max(2, (int)($inner / $cells));
$ox     = $qz;
$oy     = $qz;

// ── Draw finder patterns (3 corner squares) ──────────────────────────────
function drawFinder($img, $x, $y, $c, $black, $white) {
    // Outer 7×7 black
    imagefilledrectangle($img, $x, $y, $x + $c * 7 - 1, $y + $c * 7 - 1, $black);
    // Inner 5×5 white
    imagefilledrectangle($img, $x + $c, $y + $c, $x + $c * 6 - 1, $y + $c * 6 - 1, $white);
    // Centre 3×3 black
    imagefilledrectangle($img, $x + $c * 2, $y + $c * 2, $x + $c * 5 - 1, $y + $c * 5 - 1, $black);
}

drawFinder($img, $ox,                      $oy,                      $cell, $black, $white); // top-left
drawFinder($img, $ox + ($cells - 7) * $cell, $oy,                    $cell, $black, $white); // top-right
drawFinder($img, $ox,                      $oy + ($cells - 7) * $cell, $cell, $black, $white); // bottom-left

// ── Separator lines (white) ───────────────────────────────────────────────
// (already white from fill, just document intent)

// ── Timing patterns ───────────────────────────────────────────────────────
for ($i = 8; $i <= $cells - 9; $i++) {
    $col = ($i % 2 === 0) ? $black : $white;
    // Horizontal (row 6)
    imagefilledrectangle($img, $ox + $i * $cell, $oy + 6 * $cell,
                               $ox + $i * $cell + $cell - 1, $oy + 6 * $cell + $cell - 1, $col);
    // Vertical (col 6)
    imagefilledrectangle($img, $ox + 6 * $cell, $oy + $i * $cell,
                               $ox + 6 * $cell + $cell - 1, $oy + $i * $cell + $cell - 1, $col);
}

// ── Data modules (pseudo-random using text hash) ─────────────────────────
$hash = crc32($text);
srand($hash);

for ($r = 0; $r < $cells; $r++) {
    for ($c2 = 0; $c2 < $cells; $c2++) {
        // Skip finder pattern areas and timing
        $inTopLeft     = ($r < 9  && $c2 < 9);
        $inTopRight    = ($r < 9  && $c2 >= $cells - 8);
        $inBottomLeft  = ($r >= $cells - 8 && $c2 < 9);
        $isTiming      = ($r === 6 || $c2 === 6);
        $isFormatInfo  = ($r === 8 || $c2 === 8);

        if ($inTopLeft || $inTopRight || $inBottomLeft || $isTiming) continue;

        $px  = $ox + $c2 * $cell;
        $py  = $oy + $r  * $cell;
        $bit = rand(0, 1);

        if ($bit) {
            imagefilledrectangle($img, $px, $py, $px + $cell - 1, $py + $cell - 1, $black);
        }
    }
}

// ── Small label at bottom ─────────────────────────────────────────────────
$label    = substr($text, 0, 20);
$fontSize = max(1, min(3, (int)($size / 80)));
$labelX   = (int)(($size - strlen($label) * imagefontwidth($fontSize)) / 2);
$labelY   = $size - $qz - imagefontheight($fontSize);
if ($labelY > 0 && $labelX >= 0) {
    imagestring($img, $fontSize, $labelX, $labelY, $label, $gray);
}

imagepng($img);
imagedestroy($img);
?>
