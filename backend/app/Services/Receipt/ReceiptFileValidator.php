<?php

namespace App\Services\Receipt;

use Illuminate\Validation\ValidationException;

/**
 * Validates an uploaded receipt as an untrusted file:
 *  - MIME type AND actual magic bytes (never trust the extension)
 *  - file size limit
 *  - image dimension limits
 */
final class ReceiptFileValidator
{
    private const MIME_BY_EXT = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
    ];

    public const MAX_DIMENSION = 12000;

    /**
     * @return array{mime_type:string,extension:string,width:?int,height:?int,size:int}
     */
    public function validate(string $path, string $originalName): array
    {
        if (!file_exists($path)) {
            throw ValidationException::withMessages(['file' => 'The uploaded file is missing.']);
        }

        $size = filesize($path);
        $maxSize = config('services.receipts.max_size_mb', 10) * 1024 * 1024;
        if ($size === false || $size <= 0) {
            throw ValidationException::withMessages(['file' => 'The uploaded file is empty.']);
        }
        if ($size > $maxSize) {
            throw ValidationException::withMessages(['file' => 'The uploaded file exceeds the maximum allowed size.']);
        }

        // Magic bytes (MIME detection by content, not extension)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path) ?: 'application/octet-stream';

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $expectedMime = self::MIME_BY_EXT[$ext] ?? null;

        $allowedMimes = array_map(fn ($e) => self::MIME_BY_EXT[$e], $this->allowedExtensions());

        if (!in_array($mime, $allowedMimes, true)) {
            throw ValidationException::withMessages(['file' => 'File type not allowed.']);
        }

        // The claimed extension must be consistent with the actual content.
        if ($expectedMime !== null && $expectedMime !== $mime) {
            throw ValidationException::withMessages(['file' => 'File content does not match its extension.']);
        }

        $width = null;
        $height = null;
        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($path);
            if ($info !== false) {
                [$width, $height] = $info;
                if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
                    throw ValidationException::withMessages(['file' => 'Image dimensions are too large.']);
                }
            }
        }

        return [
            'mime_type' => $mime,
            'extension' => $ext,
            'width' => $width,
            'height' => $height,
            'size' => (int) $size,
        ];
    }

    /** @return array<int,string> */
    private function allowedExtensions(): array
    {
        $configured = config('services.receipts.allowed_mime', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);

        return array_values(array_filter($configured, fn ($e) => isset(self::MIME_BY_EXT[$e])));
    }
}
