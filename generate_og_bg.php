<?php
$width = 1200;
$height = 630;
$image = imagecreatetruecolor($width, $height);

// We want a nice blue gradient (e.g., #3b82f6 to #1d4ed8)
// Let's do a diagonal gradient
$colorStart = [59, 130, 246]; // #3b82f6
$colorEnd = [30, 58, 138]; // #1e3a8a

for ($y = 0; $y < $height; $y++) {
    for ($x = 0; $x < $width; $x++) {
        // Factor from 0 to 1 based on diagonal position
        $factor = ($x + $y) / ($width + $height);
        
        $r = $colorStart[0] + ($colorEnd[0] - $colorStart[0]) * $factor;
        $g = $colorStart[1] + ($colorEnd[1] - $colorStart[1]) * $factor;
        $b = $colorStart[2] + ($colorEnd[2] - $colorStart[2]) * $factor;
        
        $color = imagecolorallocate($image, (int)$r, (int)$g, (int)$b);
        imagesetpixel($image, $x, $y, $color);
    }
}

// Ensure the directory exists
@mkdir(__DIR__ . '/storage/app/images', 0755, true);
imagepng($image, __DIR__ . '/storage/app/images/og-background.png');
imagedestroy($image);
echo "Background generated.\n";
