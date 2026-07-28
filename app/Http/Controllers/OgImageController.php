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

        // Background: Dark navy (e.g., #171f2c)
        $bg = imagecolorallocate($image, 23, 31, 44);
        imagefill($image, 0, 0, $bg);

        // Colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $grey = imagecolorallocate($image, 180, 188, 204);
        $blue = imagecolorallocate($image, 59, 130, 246); // BusinessKit Blue

        // Draw a large clipboard icon outline on the right
        // The icon is drawn with thick white lines
        imagesetthickness($image, 8);
        $iconX = 750;
        $iconY = 80;
        $iconW = 350;
        $iconH = 480;
        
        // Outer box
        imagerectangle($image, $iconX, $iconY, $iconX + $iconW, $iconY + $iconH, $white);
        
        // The clip at the top
        $clipW = 140;
        $clipH = 40;
        $clipX = $iconX + ($iconW / 2) - ($clipW / 2);
        $clipY = $iconY - ($clipH / 2);
        
        // Clear the top border under the clip by drawing a thick background line
        imagesetthickness($image, 12);
        imageline($image, $clipX + 10, $iconY, $clipX + $clipW - 10, $iconY, $bg);
        
        // Draw the clip box
        imagesetthickness($image, 8);
        imagerectangle($image, $clipX, $clipY, $clipX + $clipW, $clipY + $clipH, $white);
        // Clip detail (inner line)
        imageline($image, $clipX + 20, $clipY + 20, $clipX + $clipW - 20, $clipY + 20, $white);
        
        // Horizontal lines inside the clipboard
        $lineW = 200;
        $lineX = $iconX + ($iconW / 2) - ($lineW / 2);
        imageline($image, $lineX, $iconY + 160, $lineX + $lineW, $iconY + 160, $white);
        imageline($image, $lineX, $iconY + 260, $lineX + $lineW, $iconY + 260, $white);
        imageline($image, $lineX, $iconY + 360, $lineX + $lineW, $iconY + 360, $white);
        
        // Reset thickness
        imagesetthickness($image, 1);

        // Fonts
        $fontBold = resource_path('fonts/Inter-Bold.ttf');
        $fontMedium = resource_path('fonts/Inter-Medium.ttf');

        if (!file_exists($fontBold) || !file_exists($fontMedium)) {
            abort(500, "Font files not readable or missing.");
        }

        // Text variables
        $siteName = 'BUSINESSKIT';
        $title = $article->title;
        $subtitle = $article->topic_key ? Str::title(str_replace('_', ' ', $article->topic_key)) . ' — 100% gratuit.' : 'Comparatifs, tests et guides — 100% gratuit.';

        // Site Name (Blue)
        imagettftext($image, 20, 0, 80, 220, $blue, $fontBold, $siteName);

        // Title
        // Word wrap title if too long
        $wrappedTitle = $this->wrapText(65, 0, $fontBold, $title, 620);
        imagettftext($image, 65, 0, 80, 320, $white, $fontBold, $wrappedTitle);

        // Subtitle (Grey)
        // Draw it below the title. We need to calculate how many lines the title took.
        $titleLines = substr_count($wrappedTitle, "\n") + 1;
        $subtitleY = 320 + ($titleLines * 80) + 20;
        imagettftext($image, 24, 0, 80, $subtitleY, $grey, $fontMedium, $subtitle);

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
