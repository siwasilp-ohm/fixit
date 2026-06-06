<?php
// Generates simple PNG icons for PWA
// Run once: php generate.php
$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);
    imageantialias($img, true);
    $bg   = imagecolorallocate($img, 21, 101, 192);   // #1565C0
    $fg   = imagecolorallocate($img, 255, 255, 255);
    imagefill($img, 0, 0, $bg);
    // Rounded look: draw white wrench text
    $fs = intval($size * 0.45);
    $x  = intval($size * 0.28);
    $y  = intval($size * 0.72);
    imagettftext($img, $fs, 0, $x, $y, $fg, __DIR__ . '/../../vendor/font.ttf', '🔧') ?: imagestring($img, 5, $x, (int)($size/2-10), 'F', $fg);
    imagepng($img, __DIR__ . "/icon-{$size}.png");
    imagedestroy($img);
    echo "Generated icon-{$size}.png\n";
}
