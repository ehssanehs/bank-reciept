# Installation

This document covers setting up the system in **Docker** and on a **bare Ubuntu server**.

---

## Option A — Docker (recommended)

Prerequisites: Docker + Docker Compose.

```bash
# 1. Copy environment and edit it
cp .env.example .env
nano .env
```

Minimum `.env` values you must set:

```dotenv
APP_KEY=            # run: docker compose run --rm app php artisan key:generate --show
APP_URL=https://pay.example.com
DB_PASSWORD=strong_db_password
DB_ROOT_PASSWORD=strong_root_password
TELEGRAM_BOT_TOKEN=123456:ABC-DEF...
TELEGRAM_WEBHOOK_SECRET=<long random string>
TELEGRAM_ADMIN_CHAT_ID=123456789
OCR_PROVIDER=tesseract
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change_me_strong_password
```

```bash
# 2. Start services
docker compose up -d --build

# 3. Run migrations (auto-runs on first boot too) and seed
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force

# 4. Optional: set Telegram webhook instead of long-polling
docker compose exec app php artisan telegram:set-webhook
```

Web is served on **port 8080** (`http://<server>:8080`). Put nginx/a reverse proxy in
front for HTTPS (see DEPLOYMENT.md).

---

## Option B — Bare Ubuntu 22.04/24.04

### 1. System packages

```bash
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-bcmath php8.3-gd php8.3-intl php8.3-zip \
  php8.3-redis composer nginx mysql-server redis-server supervisor \
  tesseract-ocr tesseract-ocr-fas tesseract-ocr-eng imagemagick poppler-utils
```

### 2. Database

```sql
CREATE DATABASE bank_payment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bank_payment'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON bank_payment.* TO 'bank_payment'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Application

```bash
cd /var/www
git clone <repo> bankpay && cd bankpay/backend
composer install --no-dev --prefer-dist
cp .env.example .env
php artisan key:generate
# edit .env → DB_*, REDIS_*, TELEGRAM_*, OCR_*, ADMIN_*
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
```

### 4. Workers (supervisor)

```bash
sudo cp /var/www/bankpay/docker/supervisord/supervisord.conf /etc/supervisor/conf.d/bankpay.conf
# adapt paths, then:
sudo supervisorctl reread && sudo supervisorctl update
```

### 5. Nginx + HTTPS

See `docker/nginx/nginx.conf` for the server block (point `root` to
`/var/www/bankpay/backend/public`). For HTTPS use Certbot:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d pay.example.com
```

### 6. Scheduler (cron)

```cron
* * * * * cd /var/www/bankpay/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## Running tests

```bash
cd backend
cp .env.example .env.testing        # optional
php artisan test
```

Tests run against an in-memory SQLite DB with a fake OCR provider
(`OCR_PROVIDER=fake`) — no external services required.

---

## Creating the first admin

`php artisan db:seed` runs `AdminUserSeeder`, which creates a user from
`ADMIN_EMAIL` / `ADMIN_PASSWORD` and assigns the **Super Admin** role.

---

## Telegram: webhook vs long-polling

- **longpolling (default):** run `php artisan telegram:poll` continuously
  (it's included in the supervisor config). Requires no public HTTPS endpoint.
- **webhook:** set `TELEGRAM_MODE=webhook`, `TELEGRAM_WEBHOOK_URL=`, run
  `php artisan telegram:set-webhook`, and route `POST /api/v1/telegram/webhook`
  with the `X-Telegram-Secret` header.
