<?php

namespace App\Services\Ocr;

use RuntimeException;

/**
 * Prepares an uploaded receipt image for OCR without modifying the original:
 * corrects orientation, downsizes, converts to grayscale and boosts contrast.
 */
final class ImagePreprocessor
{
    public const MAX_DIMENSION = 2400;

    /**
     * @return array{width:int,height:int,out_path:string}
     */
    public function process(string $imagePath, ?string $destPath = null): array
    {
        if (!file_exists($imagePath)) {
            throw new RuntimeException('Image not found.');
        }

        $info = @getimagesize($imagePath);
        if ($info === false) {
            throw new RuntimeException('Unreadable image.');
        }
        [$width, $height, $type] = $info;

        $image = $this->load($imagePath, $type);
        if ($image === null) {
            throw new RuntimeException('Unsupported image type.');
        }

        // Fix EXIF orientation (JPEG only)
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($imagePath);
            $orientation = $exif['Orientation'] ?? 1;
            $image = $this->applyOrientation($image, (int) $orientation);
        }

        // Downscale very large images
        [$width, $height] = $this->resizeIfNeeded($image, $width, $height);

        // Grayscale + contrast boost
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_CONTRAST, -35);

        $outPath = $destPath ?: tempnam(sys_get_temp_dir(), 'ocr_img_').'.png';
        imagepng($image, $outPath);
        imagedestroy($image);

        return ['width' => $width, 'height' => $height, 'out_path' => $outPath];
    }

    private function load(string $path, int $type): ?\GdImage
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path) ?: null,
            IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            default => null,
        };
    }

    private function applyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        switch ($orientation) {
            case 3: $image = imagerotate($image, 180, 0); break;
            case 6: $image = imagerotate($image, -90, 0); break;
            case 8: $image = imagerotate($image, 90, 0); break;
        }

        return $image;
    }

    /** @return array{0:int,1:int} */
    private function resizeIfNeeded(\GdImage $image, int $width, int $height): array
    {
        $max = max($width, $height);
        if ($max <= self::MAX_DIMENSION) {
            return [$width, $height];
        }

        $scale = self::MAX_DIMENSION / $max;
        $newW = (int) round($width * $scale);
        $newH = (int) round($height * $scale);

        $resized = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);

        // Replace content of original handle
        $tmp = $resized;
        imagecopy($image, $tmp, 0, 0, 0, 0, $newW, $newH);

        return [$newW, $newH];
    }
}
