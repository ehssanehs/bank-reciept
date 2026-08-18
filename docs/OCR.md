# OCR

OCR is an **abstraction** (`app/Services/Ocr/OCRProvider`). Providers:

- `TesseractOCRProvider` — default, local Tesseract with Persian+English packs.
- `CloudOCRProvider` — configurable cloud/AI adapter (`OCR_API_URL`, `OCR_API_KEY`).
- `FakeOCRProvider` — tests only (`OCR_PROVIDER=fake`).

## Preprocessing (`ImagePreprocessor`)

1. Detect & fix orientation (EXIF).
2. Downscale if the largest dimension exceeds 2400 px.
3. Convert to grayscale.
4. Boost contrast.
5. Never modifies the original image — output is a separate temp file.

PDFs: first page rasterized via `pdftoppm` before OCR.

## Field extraction (`OCRFieldExtractor`)

Outputs structured fields, each with `value`, `confidence`, `source`:
`amount`, `currency`, `tracking_number`, `reference_number`, `card_number`,
`account_number`, `sender`, `receiver`, `merchant`, `transaction_date`,
`transaction_time`, `normalized_timestamp`.

OCR output is **evidence only**. The matching engine independently compares it
against the trusted bank transaction. Low confidence never auto-approves.

## Configuration

| Env | Default | Meaning |
|-----|---------|---------|
| `OCR_PROVIDER` | `tesseract` | provider key |
| `OCR_CONFIDENCE_THRESHOLD` | `0.75` | minimum confidence to consider |
| `OCR_MAX_ATTEMPTS` | `3` | retry attempts |
| `OCR_API_URL` / `OCR_API_KEY` | — | cloud provider credentials |
