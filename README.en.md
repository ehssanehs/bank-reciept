# Automated Bank Payment Verification System — English Guide

> **Security principle:** a receipt image can be edited or forged. The system **never**
> auto-approves a payment based on the receipt/OCR alone. The authoritative source is
> the **real bank SMS** received on a dedicated Android phone. A payment becomes
> `VERIFIED` only when the receipt evidence matches a trusted, **unused** bank
> transaction and all configured checks pass. When uncertain, the payment goes to
> `PENDING_REVIEW` for manual review.

```
Bank → SMS → Android App → HTTPS → Backend → (Database · Telegram Bot)
                                      └→ Payment Matching Engine → VERIFIED / PENDING_REVIEW
```

## What this system does

1. A dedicated Android phone receives bank SMS.
2. The Android app stores each SMS locally (offline-first) and uploads it securely.
3. The backend parses the SMS into a trusted **bank transaction**.
4. A customer creates a payment and uploads a receipt (website or Telegram).
5. The backend runs OCR on the receipt and normalizes numbers/dates.
6. The **matching engine** compares the receipt against trusted bank transactions.
7. If all configured conditions pass → the payment is `VERIFIED` automatically.
8. Otherwise → `PENDING_REVIEW` (manual review), never guessed.

---

## Repository layout

```
/backend     Laravel 11 application (API + admin panel + customer web + Telegram bot)
/android     Kotlin Android app (SMS relay with offline-first local queue)
/frontend    Optional Vite/Tailwind asset pipeline for the web UI
/database    Database design notes (migrations live in backend/database/migrations)
/docker      Dockerfile, nginx, supervisor, php config
/docs        Supplementary documentation
/tests       Test strategy notes (automated tests live in backend/tests)
```

## Technology

| Layer      | Choice                                          |
|------------|-------------------------------------------------|
| Backend    | PHP 8.3+, Laravel 11, REST API                  |
| Database   | MySQL 8 / MariaDB (SQLite for tests)            |
| Queue      | Redis + Laravel queue (dead-letter via failed_jobs) |
| Web        | Blade (server-rendered) + Tailwind, RTL/LTR     |
| Android    | Kotlin, Room, WorkManager, Retrofit/OkHttp      |
| Telegram   | Bot API (long-polling or webhook), async jobs   |
| OCR        | Pluggable `OCRProvider` (Tesseract default)     |

---

# Part 1 — Deploy the backend

## Option A — Deploy with Docker (recommended)

**Step 1.1** — Clone the repository.

```bash
git clone https://github.com/ehssanehs/bank-reciept.git
cd bank-reciept
```

**Step 1.2** — Create your environment file and edit it.

```bash
cp .env.example .env
nano .env
```

Set at least these values:

```dotenv
APP_URL=https://pay.example.com
APP_KEY=                    # generate below
DB_PASSWORD=strong_db_password
DB_ROOT_PASSWORD=strong_root_password
TELEGRAM_BOT_TOKEN=123456:ABC-DEF-...   # from @BotFather
TELEGRAM_WEBHOOK_SECRET=<long random string>
TELEGRAM_ADMIN_CHAT_ID=123456789
OCR_PROVIDER=tesseract
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change_me_strong_password
```

**Step 1.3** — Generate the Laravel application key.

```bash
docker compose run --rm app php artisan key:generate --show
# copy the output into APP_KEY= in .env
```

**Step 1.4** — Build and start the containers.

```bash
docker compose up -d --build
```

Migrations run automatically on first boot.

**Step 1.5** — Seed roles, banks, and the first admin account.

```bash
docker compose exec app php artisan db:seed --force
```

The admin account is created from `ADMIN_EMAIL` / `ADMIN_PASSWORD`.

**Step 1.6** — Verify it is running.

```bash
curl http://localhost:8080/health
# {"status":"ok","app":"Bank Payment Verification","time":"..."}
```

Open `http://localhost:8080` and log in at `/login`.

---

## Option B — Deploy on a bare Ubuntu server

**Step 2.1** — Install system packages.

```bash
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-bcmath php8.3-gd php8.3-intl php8.3-zip \
  php8.3-redis composer nginx mysql-server redis-server supervisor \
  tesseract-ocr tesseract-ocr-fas tesseract-ocr-eng imagemagick poppler-utils
```

**Step 2.2** — Create the database and user.

```sql
CREATE DATABASE bank_payment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bank_payment'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON bank_payment.* TO 'bank_payment'@'localhost';
FLUSH PRIVILEGES;
```

**Step 2.3** — Install the application.

```bash
cd /var/www
git clone https://github.com/ehssanehs/bank-reciept.git bankpay
cd bankpay/backend
composer install --no-dev --prefer-dist
cp .env.example .env
php artisan key:generate
# edit .env → DB_*, REDIS_*, TELEGRAM_*, OCR_*, ADMIN_*
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
```

**Step 2.4** — Configure workers with supervisor.

```bash
sudo cp /var/www/bankpay/docker/supervisord/supervisord.conf /etc/supervisor/conf.d/bankpay.conf
# adapt the paths inside the file
sudo supervisorctl reread && sudo supervisorctl update
```

**Step 2.5** — Configure nginx and HTTPS.

Use `docker/nginx/nginx.conf` as a template (set `root` to
`/var/www/bankpay/backend/public`). Then enable HTTPS:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d pay.example.com
```

**Step 2.6** — Add the scheduler to cron.

```cron
* * * * * cd /var/www/bankpay/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

# Part 2 — Configure Telegram

**Step 3.1** — Create a bot with [@BotFather](https://t.me/BotFather), copy its token into
`TELEGRAM_BOT_TOKEN`.

**Step 3.2** — Choose a delivery mode.

- **Long-polling (simplest):** the supervisor config already runs
  `php artisan telegram:poll`. No public HTTPS endpoint is needed.
- **Webhook:** set `TELEGRAM_MODE=webhook` and `TELEGRAM_WEBHOOK_URL=`, then run:

  ```bash
  php artisan telegram:set-webhook
  ```

**Step 3.3** — Set `TELEGRAM_ADMIN_CHAT_ID` to the chat/group id that should receive
payment notifications.

The bot supports these commands in both **English** and **Persian**:

| Command   | Meaning                                        |
|-----------|------------------------------------------------|
| `/start`  | Greeting + command list                        |
| `/pay`    | Start a payment (enter payment code, send receipt image) |
| `/status` | Show the latest payment status                 |
| `/link`   | Generate a one-time account-linking code       |
| `/help`   | Show help                                      |

---

# Part 3 — Configure the Android app

**Step 4.1** — Register a device from the admin panel (or via
`POST /api/v1/auth/register-device`). You get a `device_id`, `api_key`, and `secret`.
Store these securely — the secret is shown only once.

**Step 4.2** — Build and install the app.

```bash
cd android
./gradlew assembleDebug
adb install app/build/outputs/apk/debug/app-debug.apk
```

**Step 4.3** — Open **Settings** in the app and enter:
- Backend URL (`https://pay.example.com`)
- Device ID, Device name
- API key and Device secret
- Bank sender patterns (comma separated, e.g. `BANK,MELI,Sepah,Tejarat`)

**Step 4.4** — Make the app the default SMS handler (required on Android 4.4+ for full
message bodies):

```bash
adb shell imessaging.set_sms_default_package
# or use the system Settings → Apps → Default apps → SMS
```

**Step 4.5** — Tap **Test connection** and then **Manual sync**.

> The app saves every SMS locally first, then uploads. If the phone is offline,
> messages stay in the local queue and are retried automatically with exponential
> backoff — nothing is lost.

---

# Part 4 — Configure banks & SMS parsing

**Step 5.1** — Go to **Admin → Banks → Create bank**.

**Step 5.2** — Fill in:
- **Code** (unique, e.g. `MELI`), **Name**, **Currency**
- **Sender patterns** — comma separated names/senders that identify this bank's SMS
- **Parser** — the parser class (default `DefaultBankParser`)
- Optional regex **patterns** for amount / tracking / date / time

**Step 5.3** — Save. The bank is now active and its SMS will be parsed and stored as
trusted bank transactions.

> Parsers are pluggable — see `docs/SMS-FORMATS.md`. No bank-specific logic is
> hardcoded in the core pipeline.

---

# Part 5 — Configure OCR

**Step 6.1** — Set `OCR_PROVIDER=tesseract` (default) in `.env`. Tesseract with Persian
and English language packs is bundled in the Docker image.

**Step 6.2** — To use a cloud/AI provider instead:

```dotenv
OCR_PROVIDER=cloud
OCR_API_URL=https://your-ocr-provider.com/api
OCR_API_KEY=your_key
```

**Step 6.3** — Tune `OCR_CONFIDENCE_THRESHOLD` (default `0.75`). OCR output is evidence
only — the matching engine independently compares it against the bank transaction.

---

# Part 6 — Configure automatic verification rules

**Step 7.1** — Admin → **Settings** (or environment variables) lets you set:

| Setting                            | Default | Meaning                                  |
|------------------------------------|---------|------------------------------------------|
| `PAYMENT_MATCH_TIME_TOLERANCE`     | 15 min  | ± time window for matching               |
| `OCR_CONFIDENCE_THRESHOLD`         | 0.75    | minimum OCR confidence                   |
| `AUTO_APPROVE_CONFIDENCE_THRESHOLD`| 0.75    | minimum confidence for auto-approval     |
| `AUTO_APPROVAL_ENABLED`            | true    | master switch for auto-approval          |
| `FRAUD_RISK_AUTO_REVIEW_THRESHOLD` | 50      | risk score above this → manual review    |
| `FRAUD_VERIFICATION_WINDOW_HOURS`  | 72      | max age of a valid transaction           |
| `ALLOWED_CURRENCIES`               | IRR,USD,EUR | accepted currencies                  |
| `MAX_RECEIPT_SIZE_MB`              | 10      | max upload size                          |

**Step 7.2** — Matching rules (see `docs/MATCHING-RULES.md`):
- **Rule A (strong):** tracking + amount + bank + type + time + unused → auto `VERIFIED`.
- **Rule B (alternative strong):** no tracking, but amount + time + identifiers → auto `VERIFIED`.
- **Rule C (weak):** only amount matches → `PENDING_REVIEW`.

---

# Part 7 — Run workers

Workers are already configured by supervisor (Docker or bare server). To run them
manually:

```bash
php artisan queue:work redis --tries=3 --timeout=300   # process OCR/matching/Telegram jobs
php artisan schedule:work                               # recurring jobs (expiry, retention)
php artisan telegram:poll                               # Telegram long-polling (if used)
```

---

# Part 8 — How to use the system

### For customers

1. **Website:** open `/pay`, enter the amount, submit → you get a payment page with a
   one-time upload token. Upload the receipt image (JPG/PNG/WEBP/PDF, ≤ 10 MB).
2. **Telegram:** `/start`, then `/pay`, enter the payment code, send the receipt photo
   or document.
3. The page/bot shows the result: ✅ **Verified**, ⏳ **Pending review**, or ❌ **Rejected**.

### For admins

1. Log in at `/login`.
2. **Dashboard** shows totals: verified today, pending review, rejected, failed OCR,
   SMS received, unmatched transactions, duplicate attempts, Telegram messages.
3. **Payments** — filter by date/bank/customer/amount/status/tracking. Open a payment
   to see customer, order, receipt, OCR data, bank SMS, parsed transaction, matching
   result, risk score, timeline, and audit log.
4. **Approve / Reject** a `PENDING_REVIEW` payment — a reason is required.
5. **Banks / Devices / Users / Settings / Audit / Customers / Reports** for full
   administration.

---

# Part 9 — Run tests

```bash
cd backend
composer install
php artisan test
```

Tests use an in-memory SQLite DB and a fake OCR provider — no external services needed.
See `docs/TESTING.md` for the coverage matrix.

---

# Part 10 — Backup, restore & monitoring

- **Backup:**
  ```bash
  mysqldump -u bank_payment -p bank_payment > /backups/bankpay_$(date +%F).sql
  tar -czf /backups/receipts_$(date +%F).tar.gz /var/www/bankpay/backend/storage/app/private/receipts
  ```
- **Restore:**
  ```bash
  mysql -u bank_payment -p bank_payment < bankpay_2026-01-01.sql
  tar -xzf receipts_2026-01-01.tar.gz -C /var/www/bankpay/backend/storage/app/private/
  chown -R www-data:www-data /var/www/bankpay/backend/storage
  sudo supervisorctl restart all
  ```
- **Health checks:** `GET /health`, `/health/database`, `/health/queue`, `/health/cache`.

---

## Documentation index

- [`INSTALL.md`](INSTALL.md) — setup details
- [`DEPLOYMENT.md`](DEPLOYMENT.md) — production deployment, workers, backup/restore
- [`API.md`](API.md) — all endpoints with examples
- [`SECURITY.md`](SECURITY.md) — threat model & security checklist
- [`ARCHITECTURE.md`](ARCHITECTURE.md) — design, schema, matching algorithm
- [`docs/`](docs) — OCR, SMS formats, matching rules, edge cases, testing

## Persian guide

🇮🇷 Read the Persian version: [`README.fa.md`](README.fa.md)
