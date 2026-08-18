# Architecture

This document describes the architecture of the **Automated Bank Payment Verification System**.
It is the reference design that all code in this repository implements.

---

## 1. High-Level System

```
                ┌────────────────────────────────────────────────────────┐
                │                        BANKS                            │
                │              (send SMS on transactions)                 │
                └───────────────────────┬────────────────────────────────┘
                                        │ SMS
                                        ▼
                ┌────────────────────────────────────────────────────────┐
                │              ANDROID APP (dedicated phone)             │
                │  SMSReceiver → Normalizer → Room queue → Uploader      │
                └───────────────────────┬────────────────────────────────┘
                                        │ HTTPS (device_id + API key + HMAC)
                                        ▼
                ┌────────────────────────────────────────────────────────┐
                │                    BACKEND (Laravel)                    │
                │                                                        │
                │  SMS Ingestion → BankParser → BankTransaction           │
                │                                                        │
                │  Receipt upload ← Website / Telegram Bot               │
                │        └→ OCR abstraction → structured fields          │
                │                                                        │
                │  PaymentMatchingEngine ──→ VERIFIED / PENDING_REVIEW    │
                │                                                        │
                │  Fraud/Risk scoring · Audit · Notifications · RBAC     │
                └──────────────┬──────────────────┬──────────────────────┘
                               │                  │
                     MySQL/MariaDB           Redis (queues/cache)
                               │                  │
                     Telegram Bot  ←──────────────┘ (webhook → job)
                               │
                               ▼
                     Customers (Telegram / Website)
```

### Trust model (critical)

- **Authoritative source** = the bank SMS captured by the dedicated Android device and
  stored as a `bank_transaction` record.
- **Customer-provided evidence** = the receipt image and its OCR output.
- A payment is **only** auto-approved (`VERIFIED`) when the evidence is matched
  **deterministically** against a **trusted, unused** bank transaction and all configured
  checks pass. The receipt/OCR alone is never sufficient.

---

## 2. Technology Selection

| Layer        | Choice                                  | Rationale                                                                 |
|--------------|-----------------------------------------|---------------------------------------------------------------------------|
| Backend      | PHP 8.3+ / Laravel 11                   | Required by spec; mature queue, auth, migrations, i18n, testing.          |
| Database     | MySQL 8 / MariaDB (SQLite for tests)    | ACID transactions + row locking for the atomic "one transaction → one payment" rule. |
| Cache/Queue  | Redis + Laravel Horizon-compatible      | Queues for OCR, Telegram, notifications, matching; dead-letter support.   |
| Web frontend | Laravel Blade + Tailwind (RTL aware)    | Ships inside the backend; no separate Node runtime needed to deploy.      |
| Android      | Kotlin + Jetpack + WorkManager + Room + Retrofit | Modern, secure, offline-first.                              |
| Telegram     | Bot API long-polling + optional webhook | Async processing via jobs; returns 200 quickly.                           |
| OCR          | Pluggable `OCRProvider` interface       | Default `TesseractOCR`; optional cloud/AI provider behind same interface. |
| Deployment   | Docker Compose + nginx + supervisor     | Reproducible; also runnable without Docker.                               |

---

## 3. Module Map (Laravel `app/`)

| Module              | Primary classes / services                                        |
|---------------------|-------------------------------------------------------------------|
| Authentication      | `app/Http/Middleware/` + `app/Services/Auth/DeviceAuthenticator`   |
| Users / RBAC        | `app/Models/User`, `Role`, `Permission`, `app/Policies/*`          |
| Customers           | `app/Models/Customer`, `app/Services/CustomerLinkService`          |
| Payments            | `app/Models/Payment`, `app/Enums/PaymentStatus` (state machine)    |
| Bank SMS            | `app/Models/BankSms`, `app/Services/Sms/`                          |
| Bank Parsing        | `app/Services/Parsers/BankParserInterface` + per-bank parsers      |
| Receipt Processing  | `app/Services/Receipt/ReceiptProcessor`, `ReceiptUploadService`    |
| OCR                 | `app/Services/Ocr/OCRProvider` + `TesseractOCRProvider`            |
| Payment Matching    | `app/Services/Matching/PaymentMatchingEngine`, `MatchingRules`     |
| Fraud / Risk        | `app/Services/Fraud/FraudDetector` (risk score 0–100)              |
| Telegram            | `app/Services/Telegram/TelegramService`, `TelegramController`      |
| Notifications       | `app/Notifications/*`, queued                                       |
| Admin               | `app/Http/Controllers/Admin/*`, Blade views                         |
| Audit Logs          | `app/Services/Audit/AuditLogger`, `app/Models/AuditLog`            |
| Settings            | `app/Services/Settings/SettingsService` (cached key/value)         |
| Devices             | `app/Models/Device`, device auth                                    |
| Banks               | `app/Models/Bank`, admin CRUD + parser registry                     |
| Reports             | `app/Services/Reports/*`                                            |

---

## 4. Database Schema (summary)

See `database/migrations/` for full definitions. Key entities:

```
users            customers        devices          banks
  id (uuid)        id (uuid)        id (uuid)         id (uuid)
  role_id          user_id          device_id(uniq)   code(uniq)
  ...              telegram_user_id api_key_hash      name
                   link_code        secret_hash       currency
                                    status            sender_patterns(json)
                                    last_synced_at    parser_class

bank_sms                  bank_transactions           payments
  id(uuid)                  id(uuid)                    id(uuid)
  device_id                 bank_sms_id                 customer_id
  sender                    bank_id                     user_id
  message_body              transaction_type            order_id
  received_at               amount                      amount
  sms_hash (unique)         currency                    currency
  status                    tracking_number             tracking_number
                            reference_number            status (enum)
                            normalized_timestamp        risk_score
                            hash (unique)               ocr_confidence
                            status (available/used)     matched_transaction_id

payment_receipts  ocr_results      payment_matches      telegram_users
  id               id               id                   id (chat_id)
  payment_id       receipt_id       payment_id           linked_customer_id
  upload_token     provider         bank_transaction_id  link_code
  sha256           extracted_fields match_type           language
  perceptual_hash  raw_text         score                status
  path             confidence       matched_fields
  mime/size/wh     status

telegram_messages  audit_logs        system_settings    notifications
  id                id                key (unique)        id
  chat_id           event             value               user_id/customer_id
  message_id        user_id           group               type/channel
  text/media        payment_id        encrypted           payload
  status            device_id                           status
                    old/new status                      sent_at
                    metadata(json)
```

Indexes are defined on `tracking_number`, `amount`, `transaction_date`,
`normalized_timestamp`, `bank_id`, `device_id`, `payment_id`, `status`, `hash`,
`sms_hash`, `sha256` — see migration files.

---

## 5. API Surface

Base URL `/api/v1`. All routes are versioned, validated, rate-limited, and (except the
public receipt-upload and telegram-webhook) authenticated.

| Method | Path                                        | Purpose                                  |
|--------|---------------------------------------------|------------------------------------------|
| POST   | `/api/v1/auth/register-device`              | Device registration (returns API key)    |
| POST   | `/api/v1/auth/device/token`                 | Rotating device token exchange           |
| POST   | `/api/v1/android/sms`                       | Android uploads bank SMS (HMAC-signed)   |
| GET    | `/api/v1/android/status`                    | Android sync status / server time        |
| POST   | `/api/v1/payments`                          | Customer creates a payment               |
| GET    | `/api/v1/payments/{token}`                  | Customer payment status (by upload token)|
| POST   | `/api/v1/payments/{token}/receipt`          | Public receipt upload (one-time token)   |
| POST   | `/api/v1/telegram/webhook`                  | Telegram updates (secret-guarded)        |
| POST   | `/api/v1/auth/login`                        | Web/admin login (sanctum)                |
| GET    | `/api/v1/admin/*`                           | Admin read endpoints (RBAC)              |
| POST   | `/api/v1/admin/payments/{id}/approve`       | Manual approval (reason required)        |
| POST   | `/api/v1/admin/payments/{id}/reject`        | Manual rejection (reason required)       |
| GET    | `/health` `/health/database` `/health/queue`| Health checks                            |

Full documentation and examples: [`API.md`](API.md).

---

## 6. Payment Lifecycle (state machine)

```
CREATED ──► AWAITING_RECEIPT ──► RECEIPT_RECEIVED ──► OCR_PROCESSING ──► MATCHING
                                                                           │
          ┌───────────┬──────────────┬────────────────────────────────────┘
          ▼           ▼              ▼
       VERIFIED   PENDING_REVIEW   REJECTED
          │           │               │
          └── EXPIRED / CANCELLED (any earlier state, configurable window)
```

- Transitions are enforced by `app/Services/Payments/PaymentStateMachine`.
- Every transition writes an `audit_logs` row with `old_status` / `new_status`.
- `VERIFIED` is terminal and only reachable via the matching engine (or an admin whose
  manual action is itself logged and RBAC-gated).

---

## 7. Matching Algorithm

`PaymentMatchingEngine` runs rules in order and short-circuits:

1. **Rule A — Strong match** → auto `VERIFIED`
   `tracking_number` AND `amount` AND `bank` AND valid transaction type AND
   transaction unused, all exact after normalization, OCR confidence ≥ threshold.

2. **Rule B — Alternative strong match** → auto `VERIFIED`
   when tracking number absent: `amount` AND `bank` AND
   `|normalized_timestamp - sms| ≤ time_tolerance` AND extra identifier fields AND unused.

3. **Rule C — Weak match** (only amount, or confidence below threshold) → `PENDING_REVIEW`.

### Atomic single-use guarantee
Auto-approval runs inside a database transaction and reserves the transaction with a
`SELECT ... FOR UPDATE` lock plus a **unique index** on
`bank_transactions.used_by_payment_id` (nullable, single) and a
`bank_transactions.status` guard. Two simultaneous submissions for the same bank
transaction cannot both succeed — see `PaymentMatchingEngine::reserveTransaction`.

---

## 8. OCR Architecture

```
UploadedFile ──► ReceiptProcessor
                  ├─ image validation (magic bytes, dims, size)
                  ├─ re-encode + store original (immutable)
                  ├─ preprocess: orientation, deskew, resize, denoise, contrast
                  ├─ OCRProvider::extract()  → raw text + per-field confidence
                  └─ OCRFieldExtractor → normalized structured fields
```

`OCRProvider` interface keeps providers swappable:
`TesseractOCRProvider` (default), and a documented `CloudOCRProvider` adapter.

OCR output is **evidence only**. Final authority is the bank transaction.

---

## 9. Security Model

- All traffic HTTPS; TLS terminates at nginx.
- Device auth = `device_id` + hashed API key + **HMAC-SHA256 request signature**
  (`X-Signature` + timestamp) with replay window (`X-Timestamp` ± replay_ttl).
- Duplicate SMS protection: SHA-256 of `(device_id|sender|body|received_at)` with a
  unique constraint.
- Duplicate receipt protection: SHA-256 + perceptual hash of file bytes.
- RBAC with roles: `super_admin`, `admin`, `payment_reviewer`, `support`, `read_only`.
- Receipts stored outside web root; downloads served via short-lived signed URLs.
- Secrets never committed; all via environment variables (see `.env.example`).

---

## 10. Deployment Architecture

Docker Compose services: `nginx`, `app` (php-fpm), `worker` (queue + scheduler),
`mysql`, `redis`. Long-running jobs handled by a supervisor-managed `queue:work`.
See `DEPLOYMENT.md` and `docker/`.

---

## 11. Android Architecture

Layered, offline-first Kotlin app:

- `SmsReceiver` → `SmsParser` (sender whitelist/config) → persist to Room queue.
- `SmsUploadWorker` (WorkManager, exponential backoff) → Retrofit + OkHttp
  (HTTPS, HMAC signing) → marks `uploaded`/`failed`.
- `SettingsActivity` exposes backend URL, device name/ID, sync status, pending/failed
  counts, test & manual sync.
- Device secret stored in `EncryptedSharedPreferences`.

See `android/README.md`.

---

## 12. Assumptions & Design Decisions

- **Dedicated phone / sideloaded deployment** is the supported model (per spec 4.1).
  The app uses `RECEIVE_SMS` + `READ_SMS` and, on Android 4.4+, must either be the
  default SMS handler or use a privileged broadcast. The app documents (and enforces
  via a check + user guidance) that it must be set as default SMS handler on modern
  Android; it does **not** attempt to bypass platform restrictions.
- Telegram runs in long-polling mode by default for simpler deployment; webhook mode
  is fully supported and configured via env.
- Currency default `IRR`; time tolerance and OCR confidence threshold are configurable.
- OCR default provider is Tesseract (must be installed in the app container); a cloud
  provider can be enabled by env without code changes.
- All money amounts are stored as decimal in minor-currency-safe precision and
  normalized to canonical English digits internally.
