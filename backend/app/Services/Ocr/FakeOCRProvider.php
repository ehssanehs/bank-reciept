<?php

namespace App\Services\Ocr;

/**
 * Deterministic OCR provider used ONLY in tests (OCR_PROVIDER=fake).
 *
 * Reads a JSON fixture of expected fields from services.ocr.fake_file so that
 * end-to-end tests can drive the full pipeline without an OCR engine.
 * Never used in production.
 */
class FakeOCRProvider implements OCRProvider
{
    public function extract(string $imagePath, array $options = []): OCRResult
    {
        $rawText = $options['raw_text'] ?? '';

        $fixturePath = (string) config('services.ocr.fake_file', '');
        $fields = [];

        if ($fixturePath !== '' && file_exists($fixturePath)) {
            $decoded = json_decode((string) file_get_contents($fixturePath), true);
            if (is_array($decoded)) {
                $rawText = $decoded['raw_text'] ?? $rawText;
                $source = $decoded['fields'] ?? $decoded;
                foreach ((array) $source as $key => $value) {
                    if (is_array($value)) {
                        $fields[$key] = $value;
                    } elseif ($value !== null) {
                        $fields[$key] = ['value' => $value, 'confidence' => 0.99, 'source' => 'ocr'];
                    }
                }
            }
        }

        if ($fields === []) {
            // sensible default used when no fixture provided
            $fields = [
                'amount' => ['value' => 5000000, 'confidence' => 0.99, 'source' => 'ocr'],
                'tracking_number' => ['value' => '845621', 'confidence' => 0.99, 'source' => 'ocr'],
                'currency' => ['value' => 'IRR', 'confidence' => 0.99, 'source' => 'ocr'],
            ];
        }

        return new OCRResult($rawText, $fields, 'fake', 0.99);
    }
}
