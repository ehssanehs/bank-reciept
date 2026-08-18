# Testing

Automated tests live in `backend/tests` (PHPUnit). Run:

```bash
cd backend
composer install
php artisan test
```

Tests run against an in-memory SQLite DB with a **fake OCR provider**
(`OCR_PROVIDER=fake`) — no external services required.

## Coverage

| Area | Test file | Requirements covered |
|------|-----------|---------------------|
| Persian digit normalization | `tests/Unit/NumberNormalizerTest.php` | 9 |
| Amount parsing | `tests/Unit/MoneyNormalizerTest.php` | 13 |
| Date parsing / Jalali conversion | `tests/Unit/JalaliTest.php`, `DateTimeNormalizerTest.php` | 14 |
| SMS parsing | `tests/Unit/BankParserTest.php` | 5 |
| OCR field normalization | `tests/Unit/OCRFieldExtractorTest.php` | 12 |
| Matching rules | `tests/Feature/PaymentFlowTest.php` | 15–19 |
| Duplicate detection / concurrency | `tests/Feature/DuplicateTransactionTest.php` | 18, 25, 26, 43 |
| Android → API (HMAC, dupes, auth) | `tests/Feature/AndroidSmsUploadTest.php` | 4, 28 |
| Receipt upload → OCR → matching → VERIFIED | `tests/Feature/EndToEndVerificationTest.php` | 52 |
| Security (unauthorized, invalid files, escalation, replay) | `tests/Feature/SecurityTest.php` | 28, 44 |

## Security tests included

- Unauthorized API access → 401
- Non-admin device registration → 403
- Read-only user attempting to approve a payment → 403
- Admin approval without a reason → validation error
- Invalid file (extension/content mismatch) → 422
- Oversized file → 422
- Unknown payment token → 404
- Invalid device signature → 403
- Stale timestamp → 403
- Duplicate SMS → rejected
