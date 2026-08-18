<?php

namespace App\Services\Ocr;

/**
 * Computes cryptographic (SHA-256) and perceptual hashes for uploaded images.
 */
final class ImageHasher
{
    public static function sha256(string $path): string
    {
        return hash_file('sha256', $path);
    }

    /**
     * Perceptual aHash: 64-bit hash of the 8x8 grayscale average.
     * Detects visually identical / slightly modified receipts.
     */
    public static function perceptualHash(string $path): string
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return '';
        }
        [$w, $h, $type] = $info;

        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (!$img) {
            return '';
        }

        $small = imagecreatetruecolor(8, 8);
        imagecopyresampled($small, $img, 0, 0, 0, 0, 8, 8, $w, $h);
        imagefilter($small, IMG_FILTER_GRAYSCALE);

        $pixels = [];
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $gray = ($rgb >> 16) & 0xFF; // red channel ~ luminance after grayscale
                $pixels[] = $gray;
            }
        }

        $avg = array_sum($pixels) / count($pixels);
        $hash = '';
        foreach ($pixels as $p) {
            $hash .= ($p >= $avg) ? '1' : '0';
        }

        imagedestroy($small);
        imagedestroy($img);

        return $hash;
    }

    public static function hammingDistance(string $a, string $b): int
    {
        if (strlen($a) !== strlen($b)) {
            return PHP_INT_MAX;
        }
        $distance = 0;
        for ($i = 0, $l = strlen($a); $i < $l; $i++) {
            if ($a[$i] !== $b[$i]) {
                $distance++;
            }
        }

        return $distance;
    }
}
