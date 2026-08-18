# Tests

The automated test suite lives in the Laravel application:

```
backend/tests/unit/      unit tests (normalizers, parser, OCR extractor, Jalali)
backend/tests/feature/   integration & end-to-end tests (Android→API, upload→OCR→matching,
                         duplicate/concurrency, security)
```

## Run

```bash
cd backend
composer install
php artisan test
```

Uses an in-memory SQLite DB and a fake OCR provider — no external services.

See [docs/TESTING.md](../docs/TESTING.md) for the full coverage matrix.
