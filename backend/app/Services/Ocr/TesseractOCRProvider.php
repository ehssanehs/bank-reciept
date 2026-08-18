<?php

namespace App\Services\Ocr;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Local OCR using the Tesseract engine (with Persian + English language packs).
 */
class TesseractOCRProvider implements OCRProvider
{
    public function __construct(private readonly ?string $binary = null)
    {
        $this->binary ??= config('services.ocr.tesseract_binary', 'tesseract');
    }

    public function extract(string $imagePath, array $options = []): OCRResult
    {
        if (!file_exists($imagePath)) {
            throw new RuntimeException('OCR: image not found.');
        }

        $lang = $options['lang'] ?? 'fas+eng';

        $outFile = tempnam(sys_get_temp_dir(), 'ocr_').'.txt';

        $command = [
            $this->binary, $imagePath, str_replace('.txt', '', $outFile),
            '-l', $lang, '--psm', '6',
        ];

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run();

        $rawText = '';
        if (file_exists($outFile)) {
            $rawText = (string) file_get_contents($outFile);
            @unlink($outFile);
        }

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Tesseract failed: '.$process->getErrorOutput());
        }

        $fields = (new OCRFieldExtractor())->extract($rawText);

        return new OCRResult(
            rawText: trim($rawText),
            fields: $fields,
            provider: 'tesseract',
            overallConfidence: $this->estimateConfidence($fields),
        );
    }

    private function estimateConfidence(array $fields): float
    {
        if ($fields === []) {
            return 0.0;
        }
        $sum = 0.0;
        $count = 0;
        foreach ($fields as $field) {
            $sum += (float) $field['confidence'];
            $count++;
        }

        return $count === 0 ? 0.0 : round($sum / $count, 3);
    }
}
