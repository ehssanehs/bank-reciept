# Security

## Trust model (the core rule)

- **Authoritative:** the bank SMS captured by the dedicated Android device and stored as a
  `bank_transaction`.
- **Evidence:** the receipt image and its OCR output.
- A payment is auto-`VERIFIED` **only** when the evidence matches a trusted, **unused**
  bank transaction and all configured checks pass (amount, tracking, bank, date/time,
  transaction type, duplicate usage, OCR confidence, risk score).
- Never auto-approve on OCR evidence alone.

## In transit

- HTTPS only; TLS terminates at nginx/reverse proxy.
- Device requests are HMAC-SHA256 signed with a per-device secret; `X-Timestamp`
  replay window; `X-Nonce` replay prevention (cache).
- Telegram webhook guarded by a shared secret header (`X-Telegram-Secret`).

## At rest

- Credentials stored **only** as hashes or encrypted values
  (`devices.api_key_hash`, `devices.secret_encrypted`).
- Receipts stored **outside** the public web root; served only via an authorized,
  policy-gated download route.
- Secrets never committed — all via environment variables.

## Authentication & authorization

- Users authenticate with password + Sanctum tokens (API) or session (web).
- **RBAC roles:** `super_admin`, `admin`, `payment_reviewer`, `support`, `read_only`.
- Fine-grained permissions: `view_payments`, `view_receipts`, `view_bank_sms`,
  `approve_payments`, `reject_payments`, `manage_banks`, `manage_bank_parsers`,
  `manage_telegram`, `manage_users`, `manage_settings`, `view_audit`, `view_reports`,
  `manage_devices`, `manage_matching_rules`.
- Policies enforce per-entity authorization (e.g. receipt download requires
  `view_receipts`).

## Input hardening

- **File uploads:** magic-byte + MIME + size + dimension validation; secure internal
  filenames; no extension trust; re-encoding before OCR; never executed.
- **Validation** on all request inputs; ORM (Eloquent) prevents SQL injection;
  Blade escapes output (XSS-safe by default).
- **Rate limiting** on login, receipt upload, and device endpoints.

## Duplicate & replay protection

- Bank SMS: unique `(device_id, sms_hash)` constraint → duplicates rejected.
- Receipts: `sha256` + perceptual hash stored; fraud detector flags repeats.
- Bank transactions: **unique index on `used_by_payment_id`** + row-level locking
  guarantee one-transaction → one-payment (race-safe).
- Telegram updates: deduplicated on `(chat_id, message_id)`.

## Audit

- Every important event (SMS, OCR, matching, verify/reject, admin actions, settings
  changes) is written to `audit_logs` **and** to an append-only JSON audit log channel.
- Audit rows are **not editable** from the admin UI.

## Secrets checklist

Never commit: Telegram bot token, webhook secret, DB passwords, `APP_KEY`,
Android API keys/secrets, OCR API keys. Use `.env` only (`.gitignore` protects it).

## Logging hygiene

Structured logs never record passwords, tokens, or full sensitive financial data
unnecessarily. The audit channel is separate from application logs.

## Rate of exposure

Receipts and SMS are financial data — restrict admin access by role, honor the
configured retention period (automatic deletion), and keep downloads authenticated.
