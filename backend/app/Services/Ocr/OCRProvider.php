<?php

namespace App\Services\Ocr;

/**
 * OCR abstraction. The whole system depends on this interface, never on a
 * single provider. Implementations must be resilient to provider outages
 * (throwing a clear exception that the queue can retry).
 */
interface OCRProvider
{
    /**
     * Perform OCR on an image file and return raw text plus structured fields.
     *
     * @param string $imagePath absolute path to a (preprocessed) image
     */
    public function extract(string $imagePath, array $options = []): OCRResult;
}
