<?php
$size = (int)($_GET['size'] ?? 192);
$size = in_array($size,[72,96,128,144,152,192,384,512]) ? $size : 192;

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=86400');
echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 100 100">
  <rect width="100" height="100" rx="22" fill="#1565C0"/>
  <text x="50" y="67" font-size="52" text-anchor="middle" fill="white" font-family="Arial">🔧</text>
</svg>
SVG;
