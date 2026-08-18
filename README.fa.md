# سامانه خودکار تأیید پرداخت بانکی — راهنمای فارسی

> **اصل امنیتی مهم:** تصویر رسید می‌تواند ویرایش یا جعلی باشد. این سیستم **هرگز** پرداختی
> را صرفاً بر اساس رسید یا خروجی OCR تأیید نمی‌کند. منبع معتبر و قطعی، **پیامک واقعی بانک**
> است که روی تلفن اندرویدی اختصاصی دریافت می‌شود. پرداخت فقط زمانی `VERIFIED` (تأییدشده)
> می‌شود که رسید با یک تراکنش بانکی معتبر و **مصرف‌نشده** مطابقت داده شود و همهٔ شروط
> پیکربندی‌شده برقرار باشد. در صورت عدم قطعیت، پرداخت به وضعیت `PENDING_REVIEW`
> (در انتظار بررسی دستی) می‌رود.

```
بانک → پیامک → اپ اندروید → HTTPS → بک‌اند → (پایگاه داده · ربات تلگرام)
                                        └→ موتور تطبیق پرداخت → VERIFIED / PENDING_REVIEW
```

## این سیستم چه کاری انجام می‌دهد

1. یک تلفن اندرویدی اختصاصی، پیامک‌های بانکی را دریافت می‌کند.
2. اپ اندروید هر پیامک را ابتدا به‌صورت محلی ذخیره می‌کند (آفلاین‌محور) و سپس امن ارسال می‌کند.
3. بک‌اند پیامک را به یک **تراکنش بانکی** معتبر تبدیل (پارس) می‌کند.
4. مشتری یک پرداخت ایجاد کرده و رسید را بارگذاری می‌کند (وب‌سایت یا تلگرام).
5. بک‌اند روی رسید OCR اجرا کرده و اعداد/تاریخ‌ها را نرمال‌سازی می‌کند.
6. **موتور تطبیق**، رسید را با تراکنش‌های بانکی معتبر مقایسه می‌کند.
7. اگر همهٔ شروط پیکربندی‌شده برقرار باشد → پرداخت خودکار `VERIFIED` می‌شود.
8. در غیر این صورت → `PENDING_REVIEW` (بررسی دستی)؛ هرگز حدس زده نمی‌شود.

---

## ساختار مخزن

```
/backend     اپلیکیشن لاراول ۱۱ (API + پنل مدیریت + وب مشتری + ربات تلگرام)
/android     اپ اندروید کاتلین (رله پیامک با صف محلی آفلاین‌محور)
/frontend    خط لوله اختیاری دارایی Vite/Tailwind برای رابط وب
/database    یادداشت‌های طراحی پایگاه داده (مایگریشن‌ها در backend/database/migrations)
/docker      Dockerfile، nginx، supervisor، پیکربندی PHP
/docs        مستندات تکمیلی
/tests       یادداشت‌های راهبرد تست (تست‌های خودکار در backend/tests)
```

## فناوری‌ها

| لایه      | انتخاب                                            |
|-----------|---------------------------------------------------|
| بک‌اند    | PHP 8.3+، لاراول ۱۱، REST API                     |
| پایگاه داده | MySQL 8 / MariaDB (SQLite برای تست)             |
| صف       | Redis + صف لاراول (بن بست از طریق failed_jobs)   |
| وب        | Blade (رندر سمت سرور) + Tailwind، راست‌چین/چپ‌چین |
| اندروید   | کاتلین، Room، WorkManager، Retrofit/OkHttp        |
| تلگرام    | Bot API (long-polling یا webhook)، کارهای ناهمگام |
| OCR       | رابط قابل تعویض `OCRProvider` (پیش‌فرض Tesseract) |

---

# بخش ۱ — راه‌اندازی بک‌اند

## گزینه A — راه‌اندازی با Docker (پیشنهادی)

**مرحله ۱.۱** — دریافت مخزن.

```bash
git clone https://github.com/ehssanehs/bank-reciept.git
cd bank-reciept
```

**مرحله ۱.۲** — ساخت و ویرایش فایل محیط.

```bash
cp .env.example .env
nano .env
```

حداقل این مقادیر را تنظیم کنید:

```dotenv
APP_URL=https://pay.example.com
APP_KEY=                    # در زیر تولید می‌شود
DB_PASSWORD=strong_db_password
DB_ROOT_PASSWORD=strong_root_password
TELEGRAM_BOT_TOKEN=123456:ABC-DEF-...   # از @BotFather
TELEGRAM_WEBHOOK_SECRET=<یک رشته تصادفی طولانی>
TELEGRAM_ADMIN_CHAT_ID=123456789
OCR_PROVIDER=tesseract
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change_me_strong_password
```

**مرحله ۱.۳** — تولید کلید اپلیکیشن لاراول.

```bash
docker compose run --rm app php artisan key:generate --show
# خروجی را در APP_KEY= در فایل .env قرار دهید
```

**مرحله ۱.۴** — ساخت و اجرای کانتینرها.

```bash
docker compose up -d --build
```

مایگریشن‌ها در اولین اجرا به‌صورت خودکار اعمال می‌شوند.

**مرحله ۱.۵** — اجرای سیدرها برای نقش‌ها، بانک‌ها و حساب مدیر.

```bash
docker compose exec app php artisan db:seed --force
```

حساب مدیر از `ADMIN_EMAIL` / `ADMIN_PASSWORD` ساخته می‌شود.

**مرحله ۱.۶** — بررسی اجرای سیستم.

```bash
curl http://localhost:8080/health
# {"status":"ok","app":"Bank Payment Verification","time":"..."}
```

آدرس `http://localhost:8080` را باز کرده و از `/login` وارد شوید.

---

## گزینه B — راه‌اندازی روی سرور اوبونتو

**مرحله ۲.۱** — نصب بسته‌های سیستم.

```bash
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-bcmath php8.3-gd php8.3-intl php8.3-zip \
  php8.3-redis composer nginx mysql-server redis-server supervisor \
  tesseract-ocr tesseract-ocr-fas tesseract-ocr-eng imagemagick poppler-utils
```

**مرحله ۲.۲** — ساخت پایگاه داده و کاربر.

```sql
CREATE DATABASE bank_payment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'bank_payment'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON bank_payment.* TO 'bank_payment'@'localhost';
FLUSH PRIVILEGES;
```

**مرحله ۲.۳** — نصب اپلیکیشن.

```bash
cd /var/www
git clone https://github.com/ehssanehs/bank-reciept.git bankpay
cd bankpay/backend
composer install --no-dev --prefer-dist
cp .env.example .env
php artisan key:generate
# ویرایش .env → DB_*, REDIS_*, TELEGRAM_*, OCR_*, ADMIN_*
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
```

**مرحله ۲.۴** — پیکربندی ورکرها با supervisor.

```bash
sudo cp /var/www/bankpay/docker/supervisord/supervisord.conf /etc/supervisor/conf.d/bankpay.conf
# مسیرها را داخل فایل مطابق سرور خود اصلاح کنید
sudo supervisorctl reread && sudo supervisorctl update
```

**مرحله ۲.۵** — پیکربندی nginx و HTTPS.

از `docker/nginx/nginx.conf` به‌عنوان الگو استفاده کنید (`root` را روی
`/var/www/bankpay/backend/public` بگذارید). سپس HTTPS را فعال کنید:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d pay.example.com
```

**مرحله ۲.۶** — افزودن زمان‌بند به cron.

```cron
* * * * * cd /var/www/bankpay/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

# بخش ۲ — پیکربندی تلگرام

**مرحله ۳.۱** — با [@BotFather](https://t.me/BotFather) یک ربات بسازید و توکن آن را در
`TELEGRAM_BOT_TOKEN` قرار دهید.

**مرحله ۳.۲** — حالت تحویل را انتخاب کنید.

- **Long-polling (ساده‌ترین):** تنظیمات supervisor همین حالا
  `php artisan telegram:poll` را اجرا می‌کند. به آدرس HTTPS عمومی نیازی نیست.
- **Webhook:** مقدار `TELEGRAM_MODE=webhook` و `TELEGRAM_WEBHOOK_URL=` را بگذارید و سپس:

  ```bash
  php artisan telegram:set-webhook
  ```

**مرحله ۳.۳** — `TELEGRAM_ADMIN_CHAT_ID` را روی شناسهٔ چت/گروهی بگذارید که قرار است
اطلاع‌رسانی پرداخت‌ها را دریافت کند.

ربات این دستورها را به دو زبان **فارسی** و **انگلیسی** پشتیبانی می‌کند:

| دستور     | معنا                                        |
|-----------|---------------------------------------------|
| `/start`  | خوش‌آمد + فهرست دستورها                     |
| `/pay`    | شروع پرداخت (وارد کردن کد، ارسال تصویر رسید) |
| `/status` | نمایش آخرین وضعیت پرداخت                    |
| `/link`   | ساخت کد یک‌بارمصرف اتصال حساب               |
| `/help`   | نمایش راهنما                                |

---

# بخش ۳ — پیکربندی اپ اندروید

**مرحله ۴.۱** — یک دستگاه را از پنل مدیریت (یا از طریق
`POST /api/v1/auth/register-device`) ثبت کنید. یک `device_id`، `api_key` و `secret`
دریافت می‌کنید. این‌ها را امن نگه دارید — رمز فقط یک‌بار نمایش داده می‌شود.

**مرحله ۴.۲** — اپ را بسازید و نصب کنید.

```bash
cd android
./gradlew assembleDebug
adb install app/build/outputs/apk/debug/app-debug.apk
```

**مرحله ۴.۳** — در اپ، **Settings** را باز کرده و وارد کنید:
- آدرس بک‌اند (`https://pay.example.com`)
- شناسه دستگاه، نام دستگاه
- کلید API و رمز دستگاه
- الگوهای فرستنده بانک (با ویرگول جدا، مثل `BANK,MELI,Sepah,Tejarat`)

**مرحله ۴.۴** — اپ را به‌عنوان هندلر پیش‌فرض پیامک تنظیم کنید (در اندروید ۴.۴+ برای
دریافت متن کامل پیام الزامی است):

```bash
adb shell imessaging.set_sms_default_package
# یا: تنظیمات سیستم → اپ‌ها → اپ‌های پیش‌فرض → پیامک
```

**مرحله ۴.۵** — **Test connection** و سپس **Manual sync** را بزنید.

> اپ ابتدا هر پیامک را محلی ذخیره می‌کند و سپس ارسال می‌کند. اگر تلفن آفلاین باشد،
> پیام‌ها در صف محلی می‌مانند و به‌صورت خودکار با بک‌آف نمایی دوباره ارسال می‌شوند —
> هیچ‌چیز از دست نمی‌رود.

---

# بخش ۴ — پیکربندی بانک‌ها و پارس پیامک

**مرحله ۵.۱** — به **Admin → Banks → Create bank** بروید.

**مرحله ۵.۲** — موارد زیر را پر کنید:
- **Code** (یکتا، مثل `MELI`)، **Name**، **Currency**
- **Sender patterns** — نام‌ها/فرستنده‌های جدا شده با ویرگول که پیامک این بانک را مشخص می‌کنند
- **Parser** — کلاس پارسر (پیش‌فرض `DefaultBankParser`)
- الگوهای regex اختیاری برای مبلغ / پیگیری / تاریخ / زمان

**مرحله ۵.۳** — ذخیره کنید. بانک فعال می‌شود و پیامک‌های آن پارس و به‌عنوان تراکنش‌های
بانکی معتبر ذخیره می‌شوند.

> پارسرها قابل تعویض هستند — ببینید `docs/SMS-FORMATS.md`. هیچ منطق وابسته به بانک
> مشخصی در هستهٔ سیستم سخت‌کد نشده است.

---

# بخش ۵ — پیکربندی OCR

**مرحله ۶.۱** — در `.env` مقدار `OCR_PROVIDER=tesseract` (پیش‌فرض) را بگذارید. Tesseract
با زبان‌های فارسی و انگلیسی در تصویر Docker قرار دارد.

**مرحله ۶.۲** — برای استفاده از سرویس ابری/هوش مصنوعی:

```dotenv
OCR_PROVIDER=cloud
OCR_API_URL=https://your-ocr-provider.com/api
OCR_API_KEY=your_key
```

**مرحله ۶.۳** — `OCR_CONFIDENCE_THRESHOLD` (پیش‌فرض `0.75`) را تنظیم کنید. خروجی OCR فقط
شواهد است — موتور تطبیق به‌صورت مستقل آن را با تراکنش بانکی مقایسه می‌کند.

---

# بخش ۶ — پیکربندی قوانین تأیید خودکار

**مرحله ۷.۱** — Admin → **Settings** (یا متغیرهای محیطی):

| تنظیم                              | پیش‌فرض  | معنا                                  |
|-------------------------------------|----------|----------------------------------------|
| `PAYMENT_MATCH_TIME_TOLERANCE`      | ۱۵ دقیقه | بازهٔ زمانی ± برای تطبیق              |
| `OCR_CONFIDENCE_THRESHOLD`          | 0.75     | حداقل اطمینان OCR                     |
| `AUTO_APPROVE_CONFIDENCE_THRESHOLD` | 0.75     | حداقل اطمینان برای تأیید خودکار       |
| `AUTO_APPROVAL_ENABLED`             | true     | کلید اصلی تأیید خودکار                |
| `FRAUD_RISK_AUTO_REVIEW_THRESHOLD`  | 50       | ریسک بالاتر از این → بررسی دستی       |
| `FRAUD_VERIFICATION_WINDOW_HOURS`   | 72       | حداکثر سن یک تراکنش معتبر             |
| `ALLOWED_CURRENCIES`                | IRR,USD,EUR | ارزهای مجاز                        |
| `MAX_RECEIPT_SIZE_MB`               | 10       | حداکثر حجم بارگذاری                   |

**مرحله ۷.۲** — قوانین تطبیق (ببینید `docs/MATCHING-RULES.md`):
- **قانون A (قوی):** پیگیری + مبلغ + بانک + نوع + زمان + مصرف‌نشده → `VERIFIED` خودکار.
- **قانون B (قوی جایگزین):** بدون پیگیری، اما مبلغ + زمان + شناسه‌ها → `VERIFIED` خودکار.
- **قانون C (ضعیف):** فقط مبلغ مطابقت دارد → `PENDING_REVIEW`.

---

# بخش ۷ — اجرای ورکرها

ورکرها توسط supervisor (در Docker یا سرور) پیکربندی شده‌اند. برای اجرای دستی:

```bash
php artisan queue:work redis --tries=3 --timeout=300   # پردازش کارهای OCR/تطبیق/تلگرام
php artisan schedule:work                               # کارهای دوره‌ای (انقضا، نگهداری)
php artisan telegram:poll                               # پولینگ تلگرام (در صورت استفاده)
```

---

# بخش ۸ — نحوه استفاده از سیستم

### برای مشتریان

1. **وب‌سایت:** آدرس `/pay` را باز کنید، مبلغ را وارد و ثبت کنید → صفحهٔ پرداخت با
   توکن یک‌بارمصرف دریافت می‌کنید. تصویر رسید را بارگذاری کنید
   (JPG/PNG/WEBP/PDF، حداکثر ۱۰ مگابایت).
2. **تلگرام:** `/start` سپس `/pay`، کد پرداخت را وارد و عکس/مدرک رسید را بفرستید.
3. صفحه/ربات نتیجه را نشان می‌دهد: ✅ **تأییدشده**، ⏳ **در انتظار بررسی**، یا ❌ **ردشده**.

### برای مدیران

1. از `/login` وارد شوید.
2. **داشبورد** مجموع‌ها را نشان می‌دهد: تأییدشده امروز، در انتظار بررسی، ردشده،
   OCR ناموفق، پیامک دریافتی، تراکنش‌های بی‌تطبیق، تلاش تکراری، پیام‌های تلگرام.
3. **پرداخت‌ها** — فیلتر بر اساس تاریخ/بانک/مشتری/مبلغ/وضعیت/پیگیری. با باز کردن یک
   پرداخت، مشتری، سفارش، رسید، داده‌های OCR، پیامک بانک، تراکنش پارس‌شده، نتیجهٔ
   تطبیق، امتیاز ریسک، تایم‌لاین و گزارش رویداد را می‌بینید.
4. **تأیید / رد** پرداخت `PENDING_REVIEW` — درج دلیل الزامی است.
5. **بانک‌ها / دستگاه‌ها / کاربران / تنظیمات / گزارش رویداد / مشتریان / گزارش‌ها**
   برای مدیریت کامل.

---

# بخش ۹ — اجرای تست‌ها

```bash
cd backend
composer install
php artisan test
```

تست‌ها از پایگاه داده SQLite در حافظه و یک تأمین‌کننده OCR ساختگی استفاده می‌کنند —
به سرویس خارجی نیازی نیست. ماتریس پوشش را در `docs/TESTING.md` ببینید.

---

# بخش ۱۰ — پشتیبان‌گیری، بازیابی و پایش

- **پشتیبان‌گیری:**
  ```bash
  mysqldump -u bank_payment -p bank_payment > /backups/bankpay_$(date +%F).sql
  tar -czf /backups/receipts_$(date +%F).tar.gz /var/www/bankpay/backend/storage/app/private/receipts
  ```
- **بازیابی:**
  ```bash
  mysql -u bank_payment -p bank_payment < bankpay_2026-01-01.sql
  tar -xzf receipts_2026-01-01.tar.gz -C /var/www/bankpay/backend/storage/app/private/
  chown -R www-data:www-data /var/www/bankpay/backend/storage
  sudo supervisorctl restart all
  ```
- **بررسی سلامت:** `GET /health`، `/health/database`، `/health/queue`، `/health/cache`.

---

## فهرست مستندات

- [`INSTALL.md`](INSTALL.md) — جزئیات نصب
- [`DEPLOYMENT.md`](DEPLOYMENT.md) — استقرار تولید، ورکرها، پشتیبان‌گیری/بازیابی
- [`API.md`](API.md) — همهٔ نقطه‌ها با مثال
- [`SECURITY.md`](SECURITY.md) — مدل تهدید و چک‌لیست امنیت
- [`ARCHITECTURE.md`](ARCHITECTURE.md) — طراحی، ساختار پایگاه داده، الگوریتم تطبیق
- [`docs/`](docs) — OCR، فرمت‌های پیامک، قوانین تطبیق، موارد مرزی، تست

## راهنمای انگلیسی

🇬🇧 Read the English version: [`README.en.md`](README.en.md)
