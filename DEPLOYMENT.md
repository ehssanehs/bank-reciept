# Deployment

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Strong `APP_KEY`, database passwords, `TELEGRAM_WEBHOOK_SECRET`
- [ ] HTTPS enforced (nginx + Certbot, or a TLS-terminating reverse proxy)
- [ ] Queue worker running (supervisor) and scheduler running (supervisor/cron)
- [ ] Redis reachable; `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`
- [ ] `php artisan config:cache`, `route:cache`, `view:cache` (done by the app container)
- [ ] `storage/app/private` is **not** web-accessible (nginx denies `/storage/`)

## Components

| Component | Runs as | Notes |
|-----------|---------|-------|
| `app` | php-fpm | serves the app (nginx proxies to it) |
| `worker` | `queue:work redis` + `schedule:work` | async OCR, matching, Telegram, notifications |
| `nginx` | reverse proxy | serves `public/` |
| `mysql` | database | ACID transactions for the single-use guarantee |
| `redis` | cache + queue | required for the matching lock cache & queues |

## Concurrency / single-use guarantee

Auto-approval is wrapped in a DB transaction with a row lock
(`SELECT ... FOR UPDATE`) plus a **unique index** on
`bank_transactions.used_by_payment_id`. This guarantees one bank transaction is
consumed by at most one payment, even under concurrent submissions.

## Backup

```bash
# Database
mysqldump -u bank_payment -p bank_payment > /backups/bankpay_$(date +%F).sql

# Uploaded receipts (private)
tar -czf /backups/receipts_$(date +%F).tar.gz \
  /var/www/bankpay/backend/storage/app/private/receipts

# Environment (secrets!) — store separately & encrypted
cp /var/www/bankpay/backend/.env /backups/.env.bak
```

## Restore

```bash
# 1. Restore DB
mysql -u bank_payment -p bank_payment < bankpay_2026-01-01.sql

# 2. Restore receipts
tar -xzf receipts_2026-01-01.tar.gz -C /var/www/bankpay/backend/storage/app/private/

# 3. Restore .env and restart
chown -R www-data:www-data /var/www/bankpay/backend/storage
sudo supervisorctl restart all
```

## Reliability

- **Queue failures** → land in `failed_jobs` (dead-letter). Jobs retry with
  exponential backoff (`backoffMinutes`).
- **Android offline** → messages persist in Room and are retried by WorkManager.
- **Telegram/OCR outage** → jobs fail into the queue and retry; nothing is lost.
- **Duplicate SMS/receipt/webhook** → idempotent via unique constraints and
  message_id dedup.

## Health checks

| Endpoint          | Purpose                     |
|-------------------|-----------------------------|
| `GET /up`         | Laravel health               |
| `GET /api/v1/health`        | app up     |
| `GET /api/v1/health/database` | DB connectivity |
| `GET /api/v1/health/queue`  | queue driver                |
| `GET /api/v1/health/cache`  | cache connectivity          |
