<?php

namespace App\Services\Ocr;

/**
 * Output of an OCR provider: raw text plus the extracted structured fields.
 * Each field carries value, confidence and source.
 */
final class OCRResult
{
    /**
     * @param string                $rawText  raw OCR text (preserved)
     * @param array<string,array{value:mixed,confidence:float,source:string}> $fields
     */
    public function __construct(
        public readonly string $rawText,
        public readonly array $fields = [],
        public readonly string $provider = 'unknown',
        public readonly float $overallConfidence = 0.0,
    ) {}

    public function toArray(): array
    {
        return [
            'raw_text' => $this->rawText,
            'fields' => $this->fields,
            'provider' => $this->provider,
            'overall_confidence' => $this->overallConfidence,
        ];
    }
}
