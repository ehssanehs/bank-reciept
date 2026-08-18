# Edge cases & how the system handles them

| # | Scenario | Handling |
|---|----------|----------|
| 1 | Receipt uploaded before SMS arrives | Payment stays `AWAITING_RECEIPT`/`RECEIPT_RECEIVED`; when the SMS later arrives, matching is retried via the queue / review queue. |
| 2 | SMS arrives before receipt | Transaction stored `AVAILABLE`; matched when the receipt is later uploaded. |
| 3 | Receipt amount ≠ SMS amount | `FraudDetector` flags `amount_mismatch`; not a strong match → `PENDING_REVIEW`. |
| 4 | Tracking number differs | Not a strong match → review. |
| 5 | Tracking missing from receipt | Uses Rule B (alternative strong) or goes to review. |
| 6 | OCR cannot read the receipt | `OCR_FAILED` audited; job retries; payment reviewed. |
| 7 | Blurry image | Preprocessor + low confidence → blocked from auto-approval → review. |
| 8 | Rotated image | EXIF orientation correction in `ImagePreprocessor`. |
| 9 | Persian digits | `NumberNormalizer` converts ۰-۹ → 0-9. |
| 10 | Arabic digits | `NumberNormalizer` converts ٠-٩ → 0-9. |
| 11 | Persian digits in SMS | Same normalization in the bank parser. |
| 12 | Multipart SMS | Android concatenates parts before upload; SMS is preserved raw. |
| 13 | Same receipt uploaded twice | SHA-256 + perceptual hash → `FraudDetector` flags. |
| 14 | Same transaction submitted by 2 customers | Unique `used_by_payment_id` + row lock → second goes to review. |
| 15 | Android offline | Room queue + WorkManager retry with backoff; nothing lost. |
| 16 | Backend unavailable | Android retries; jobs persist. |
| 17 | Telegram unavailable | Notifications retry via queue; never claim success. |
| 18 | OCR provider outage | Job fails into queue and retries. |
| 19 | Customer sends multiple receipts | Stored; count vs `FRAUD_MAX_RECEIPTS_PER_PAYMENT`. |
| 20 | Unrelated image | OCR yields no fields → no candidate → review. |
| 21 | PDF receipt | First page rasterized (`pdftoppm`) then OCR. |
| 22 | Screenshot | Treated as an image; OCR/matching decide (evidence-only). |
| 23 | Edited receipt | Perceptual hash differs; amount/tracking mismatch caught; review. |
| 24 | Transaction older than window | `FRAUD_VERIFICATION_WINDOW_HOURS` → risk flag → review. |
| 25 | Transaction already verified | `status != AVAILABLE` → no candidate → review. |
| 26 | Two payments simultaneously | Row lock + unique constraint → exactly one `VERIFIED`. |
