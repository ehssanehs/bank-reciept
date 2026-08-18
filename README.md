# Automated Bank Payment Verification System

A complete, deployable system that verifies bank payments by matching **customer-submitted
receipts** (via website or Telegram) against **real bank SMS** received on a dedicated
Android phone. The authoritative source is always the bank SMS; the receipt/OCR is treated
as evidence only.

```
Bank → SMS → Android App → HTTPS → Backend → (Database, Telegram Bot)
                                     └→ Payment Matching Engine → VERIFIED / PENDING_REVIEW
```

> **Security principle:** a receipt image can be edited or forged. The system never
> auto-approves a payment based on the receipt/OCR alone — only a deterministic match
> against a trusted, unused bank transaction triggers `VERIFIED`.

## Repository layout

```
/backend     Laravel 11 application (API + admin panel + customer web + Telegram bot)
/android     Kotlin Android app (SMS relay with offline-first local queue)
/frontend    Optional Vite/Tailwind asset pipeline for the web UI
/database    Database schema & design notes (migrations live in backend/database/migrations)
/docker      Dockerfile, nginx, supervisor, php config
/docs        Supplementary documentation
/tests       Test strategy notes (automated tests live in backend/tests)
```

## Technology

| Component  | Choice                                  |
|------------|-----------------------------------------|
| Backend    | PHP 8.3+, Laravel 11, REST API           |
| Database   | MySQL 8 / MariaDB (SQLite for tests)     |
| Queue      | Redis + Laravel queue (dead-letter via failed_jobs) |
| Web        | Blade (server-rendered) + Tailwind, RTL/LTR |
| Android    | Kotlin, Room, WorkManager, Retrofit/OkHttp |
| Telegram   | Bot API (long-polling or webhook), async jobs |
| OCR        | Pluggable `OCRProvider` (Tesseract default) |

## Quick start with Docker

```bash
cp .env.example .env
# edit .env — set APP_KEY (php artisan key:generate), DB_*, TELEGRAM_BOT_TOKEN, etc.
docker compose up -d --build
docker compose exec app php artisan db:seed   # first run: roles, banks, admin account
```

Then:

- Web: `http://localhost:8080`
- Health: `http://localhost:8080/health`
- First admin login from `ADMIN_EMAIL` / `ADMIN_PASSWORD` in `.env`.

## The 13 setup steps (from the spec)

1. **Configure the database** — see `INSTALL.md`. Migrations auto-run in the app container.
2. **Configure the backend** — copy `.env.example` → `.env`, set `APP_KEY`, `APP_URL`,
   `DB_*`, `REDIS_*`. See `INSTALL.md`.
3. **Configure Telegram Bot** — create a bot with BotFather, set `TELEGRAM_BOT_TOKEN`,
   `TELEGRAM_WEBHOOK_SECRET`, `TELEGRAM_ADMIN_CHAT_ID`, and `TELEGRAM_MODE`
   (`longpolling` or `webhook`). See `INSTALL.md`.
4. **Configure the Android app** — see `android/README.md`.
5. **Register a device** — via the admin panel/API
   `POST /api/v1/auth/register-device`; store the returned `api_key` + `secret` on the phone.
6. **Configure banks** — Admin → Banks (name, code, currency, sender patterns, parser,
   patterns).
7. **Configure SMS parsing** — per-bank parser class + regex patterns in the bank record.
8. **Configure OCR** — `OCR_PROVIDER=tesseract` (default) or `cloud`; set
   `OCR_CONFIDENCE_THRESHOLD`.
9. **Configure automatic verification rules** — Admin → Settings: time tolerance,
   confidence threshold, `AUTO_APPROVAL_ENABLED`, risk thresholds.
10. **Run workers** — `php artisan queue:work` (supervisor in Docker) and
    `php artisan schedule:work`; optionally `php artisan telegram:poll`.
11. **Deploy the web application** — see `DEPLOYMENT.md` (nginx, supervisor, HTTPS).
12. **Run tests** — `cd backend && php artisan test` (see `INSTALL.md`).
13. **Create the first admin** — run the `AdminUserSeeder` / `php artisan db:seed`
    (uses `ADMIN_EMAIL` / `ADMIN_PASSWORD`).

## Full docs

- [`INSTALL.md`](INSTALL.md) — setup on a server and in Docker.
- [`DEPLOYMENT.md`](DEPLOYMENT.md) — production deployment, workers, backup/restore.
- [`API.md`](API.md) — all endpoints with examples.
- [`SECURITY.md`](SECURITY.md) — threat model and security checklist.
- [`ARCHITECTURE.md`](ARCHITECTURE.md) — design, schema, matching algorithm.
- [`docs/`](docs) — additional notes.

## License

MIT
