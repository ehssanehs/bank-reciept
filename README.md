# Automated Bank Payment Verification System / سامانه خودکار تأیید پرداخت بانکی

A complete, deployable system that verifies bank payments by matching **customer-submitted
receipts** (via website or Telegram) against **real bank SMS** received on a dedicated
Android phone. The authoritative source is always the bank SMS; the receipt/OCR is treated
as evidence only.

سیستمی کامل و قابل استقرار برای تأیید پرداخت‌های بانکی با تطبیق **رسید ارسالی مشتری**
(از وب‌سایت یا تلگرام) با **پیامک واقعی بانک** که روی تلفن اندرویدی اختصاصی دریافت می‌شود.
منبع معتبر همیشه پیامک بانک است و رسید/OCR فقط به‌عنوان شواهد در نظر گرفته می‌شود.

```
Bank → SMS → Android App → HTTPS → Backend → (Database · Telegram Bot)
                                      └→ Payment Matching Engine → VERIFIED / PENDING_REVIEW
```

> **Security principle / اصل امنیتی:** the system never auto-approves a payment based on
> the receipt/OCR alone — only a deterministic match against a trusted, **unused** bank
> transaction triggers `VERIFIED`. When uncertain → `PENDING_REVIEW`.
>
> سیستم هرگز پرداختی را صرفاً بر اساس رسید/OCR تأیید خودکار نمی‌کند؛ فقط تطبیق قطعی با
> تراکنش بانکی معتبر و **مصرف‌نشده** به `VERIFIED` می‌انجامد. در صورت عدم قطعیت →
> `PENDING_REVIEW`.

---

## Choose your language / انتخاب زبان

- 🇬🇧 **[English — complete step-by-step guide](README.en.md)** —
  deployment (Docker or Ubuntu), Telegram, Android, banks, OCR, verification rules,
  workers, usage, tests, backup/restore.
- 🇮🇷 **[فارسی — راهنمای کامل گام‌به‌گام](README.fa.md)** —
  استقرار (Docker یا اوبونتو)، تلگرام، اندروید، بانک‌ها، OCR، قوانین تأیید، ورکرها،
  استفاده، تست‌ها، پشتیبان‌گیری/بازیابی.

---

## Repository layout / ساختار مخزن

```
/backend     Laravel 11 application (API + admin panel + customer web + Telegram bot)
/android     Kotlin Android app (SMS relay with offline-first local queue)
/frontend    Optional Vite/Tailwind asset pipeline for the web UI
/database    Database design notes (migrations live in backend/database/migrations)
/docker      Dockerfile, nginx, supervisor, php config
/docs        Supplementary documentation
/tests       Test strategy notes (automated tests live in backend/tests)
```

## Quick start (Docker) / شروع سریع

```bash
cp .env.example .env        # edit: APP_KEY, DB_*, TELEGRAM_BOT_TOKEN, ADMIN_*
docker compose up -d --build
docker compose exec app php artisan db:seed   # roles, banks, first admin
```

Then open `http://localhost:8080` and log in at `/login`.
سپس آدرس `http://localhost:8080` را باز کرده و از `/login` وارد شوید.

## Documentation / مستندات

| English                        | فارسی                              |
|--------------------------------|------------------------------------|
| [`README.en.md`](README.en.md) | [`README.fa.md`](README.fa.md)     |
| [`INSTALL.md`](INSTALL.md)     | راهنمای نصب                          |
| [`DEPLOYMENT.md`](DEPLOYMENT.md) | راهنمای استقرار                    |
| [`API.md`](API.md)             | مرجع API                           |
| [`SECURITY.md`](SECURITY.md)   | راهنمای امنیت                      |
| [`ARCHITECTURE.md`](ARCHITECTURE.md) | معماری و طراحی               |

## License / مجوز

MIT
