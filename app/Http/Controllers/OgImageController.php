<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class OgImageController extends Controller
{
    public function show(Article $article): Response
    {
        $width = 1200;
        $height = 630;
        $image = imagecreatetruecolor($width, $height);

        // Background: Blue gradient
        $colorStart = [59, 130, 246]; // #3b82f6
        $colorEnd = [30, 58, 138]; // #1e3a8a

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $factor = ($x + $y) / ($width + $height);
                $r = $colorStart[0] + ($colorEnd[0] - $colorStart[0]) * $factor;
                $g = $colorStart[1] + ($colorEnd[1] - $colorStart[1]) * $factor;
                $b = $colorStart[2] + ($colorEnd[2] - $colorStart[2]) * $factor;
                $color = imagecolorallocate($image, (int)$r, (int)$g, (int)$b);
                imagesetpixel($image, $x, $y, $color);
            }
        }

        // Draw an abstract watermark shape (e.g. a shield or a large B) on the right
        // We can draw some polygons to look like a shield
        $watermarkColor = imagecolorallocatealpha($image, 255, 255, 255, 110); // Very transparent white
        $shieldPoints = [
            800, 100, // Top left
            1100, 100, // Top right
            1100, 400, // Bottom right (curve starts)
            950, 550,  // Bottom middle (point)
            800, 400,  // Bottom left
        ];
        // Draw lines manually for a thick stroke
        imagesetthickness($image, 15);
        imagepolygon($image, $shieldPoints, 5, $watermarkColor);
        imagesetthickness($image, 1);

        $fontBoldSrc = realpath(resource_path('fonts/Inter-Bold.ttf'));
        $fontMediumSrc = realpath(resource_path('fonts/Inter-Medium.ttf'));

        // Workaround for Linux GD/FreeType strict permissions and open_basedir issues
        $fontBold = sys_get_temp_dir() . '/Inter-Bold-' . md5_file($fontBoldSrc) . '.ttf';
        $fontMedium = sys_get_temp_dir() . '/Inter-Medium-' . md5_file($fontMediumSrc) . '.ttf';
        
        if (!file_exists($fontBold)) @copy($fontBoldSrc, $fontBold);
        if (!file_exists($fontMedium)) @copy($fontMediumSrc, $fontMedium);

        if (!file_exists($fontBold) || !file_exists($fontMedium)) {
            abort(500, "Font files not readable or missing.");
        }
        $white = imagecolorallocate($image, 255, 255, 255);
        $pillBg = imagecolorallocatealpha($image, 255, 255, 255, 90); // Translucent white pill

        // Text variables
        $siteName = 'BUSINESSKIT';
        $categoryName = Str::upper($article->topic_key ?? 'CONSEILS PRO');
        $title = $article->title;

        // Site Name
        imagettftext($image, 24, 0, 80, 100, $white, $fontBold, $siteName);

        // Category Pill
        $bbox = imagettfbbox(16, 0, $fontMedium, $categoryName);
        $textWidth = abs($bbox[4] - $bbox[0]);
        $textHeight = abs($bbox[5] - $bbox[1]);
        
        $pillX = 80;
        $pillY = 140;
        $pillPaddingX = 20;
        $pillPaddingY = 12;
        
        // Draw rounded rectangle for pill (using standard filled rectangle for now, or filled polygon for rounded)
        imagefilledrectangle(
            $image, 
            $pillX, 
            $pillY, 
            $pillX + $textWidth + ($pillPaddingX * 2), 
            $pillY + $textHeight + ($pillPaddingY * 2), 
            $pillBg
        );
        // Draw Category Text
        imagettftext(
            $image, 
            16, 
            0, 
            $pillX + $pillPaddingX, 
            $pillY + $textHeight + $pillPaddingY, 
            $white, 
            $fontMedium, 
            $categoryName
        );

        // Title
        // Word wrap title if too long
        $wrappedTitle = $this->wrapText(70, 0, $fontBold, $title, 900);
        imagettftext($image, 70, 0, 80, 360, $white, $fontBold, $wrappedTitle);

        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);

        return response($imageData, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function wrapText($fontSize, $angle, $fontFace, $string, $width): string
    {
        $ret = "";
        $arr = explode(' ', $string);
        foreach ($arr as $word) {
            $teststring = $ret . ' ' . $word;
            $testbox = imagettfbbox($fontSize, $angle, $fontFace, $teststring);
            if ($testbox[2] > $width) {
                $ret .= ($ret == "" ? "" : "\n") . $word;
            } else {
                $ret .= ($ret == "" ? "" : ' ') . $word;
            }
        }
        return $ret;
    }
}
