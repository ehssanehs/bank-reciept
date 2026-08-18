<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Example cloud/AI OCR adapter. Configure OCR_PROVIDER=cloud, OCR_API_URL and
 * OCR_API_KEY. The adapter posts the image and expects either plain text or a
 * JSON document with a `text` field. Override/extend for your provider's API.
 */
class CloudOCRProvider implements OCRProvider
{
    public function extract(string $imagePath, array $options = []): OCRResult
    {
        $url = (string) config('services.ocr.api_url');
        $key = (string) config('services.ocr.api_key');

        if ($url === '') {
            throw new RuntimeException('OCR cloud provider not configured (OCR_API_URL).');
        }

        $response = Http::timeout(60)
            ->withToken($key)
            ->attach('image', file_get_contents($imagePath), basename($imagePath))
            ->post($url);

        if ($response->failed()) {
            throw new RuntimeException('Cloud OCR failed: '.$response->status());
        }

        $body = $response->body();
        $rawText = $body;

        if (str_starts_with(ltrim($body), '{')) {
            $json = $response->json();
            $rawText = (string) ($json['text'] ?? $json['result'] ?? $json['data']['text'] ?? '');
        }

        $fields = (new OCRFieldExtractor())->extract($rawText);

        return new OCRResult(trim($rawText), $fields, 'cloud', $this->estimateConfidence($fields));
    }

    private function estimateConfidence(array $fields): float
    {
        if ($fields === []) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($fields as $field) {
            $sum += (float) $field['confidence'];
        }

        return round($sum / count($fields), 3);
    }
}
