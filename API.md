# API Reference

Base URL: `https://your-domain.com/api/v1`

All responses are JSON. Errors use HTTP status codes with
`{"success": false, "message": "...", "errors": {...}}`.

---

## Authentication

### Web / Admin (sanctum token)

```
POST /api/v1/auth/login
Body: { "email": "...", "password": "..." }
```
```json
{ "success": true, "token": "1|abc...", "user": { "id": "...", "name": "...", "email": "..." } }
```

Include `Authorization: Bearer <token>` for protected endpoints.

### Device (HMAC) authentication

Android requests are authenticated with headers:

| Header          | Value                                   |
|-----------------|-----------------------------------------|
| `X-Device-Id`   | the registered `device_id`              |
| `X-API-Key`     | the device API key                      |
| `X-Timestamp`   | Unix seconds                            |
| `X-Signature`   | `HMAC-SHA256(secret, "METHOD\nPATH\nBODY\nTS")` |
| `X-Nonce`       | unique per request (replay protection)  |

---

## Device registration (admin)

```
POST /api/v1/auth/register-device      (auth: Bearer, role super_admin|admin)
Body: { "device_id": "android-1", "name": "Main phone" }
```
```json
{
  "success": true,
  "device": { "id": "...", "device_id": "android-1", "name": "Main phone" },
  "api_key": "bpk_...",
  "secret": "...",
  "warning": "Store these credentials securely. The secret is shown only once."
}
```

---

## Android: upload bank SMS

```
POST /api/v1/android/sms      (device.auth)
Body: {
  "messages": [
    {
      "local_id": "uuid-from-phone",
      "sender": "BANK",
      "message": "واریز مبلغ ۵٬۰۰۰٬۰۰۰ ریال به شماره پیگیری 845621 ...",
      "received_at": "2026-08-18 14:32:00"
    }
  ]
}
```
```json
{ "success": true, "data": { "accepted": 1, "duplicates": 0, "ignored": 0, "parsed": 1 } }
```
- Duplicate SMS (same `device_id|sender|body|received_at`) are rejected via a
  unique hash constraint and counted in `duplicates`.

```
GET /api/v1/android/status    (device.auth)
```
```json
{ "success": true, "data": { "server_time": "...", "device": { "id": "android-1", "status": "active", "last_synced_at": "..." } } }
```

---

## Payments

```
POST /api/v1/payments      (auth: Bearer)
Body: { "amount": 5000000, "currency": "IRR", "order_id": "ORD-1", "tracking_number": "845621" }
```
```json
{
  "success": true,
  "data": {
    "id": "PMTAB12CD", "upload_token": "<48-char token>", "amount": 5000000.0,
    "currency": "IRR", "order_id": "ORD-1", "status": "AWAITING_RECEIPT",
    "risk_score": 0, "ocr_confidence": 0.0, "created_at": "...", "expires_at": "..."
  }
}
```

### Public receipt upload (one-time token)

```
POST /api/v1/payments/{token}/receipt      (public, rate-limited)
Multipart: receipt=<file>  (jpg, jpeg, png, webp, pdf; ≤ 10 MB)
```
```json
{
  "success": true,
  "message": "Receipt received and queued for verification.",
  "data": { "... payment status ..." }
}
```
Triggers an async job: `ProcessReceiptJob → OCR → VerifyPaymentJob → Matching`.

### Payment status (public)

```
GET /api/v1/payments/{token}
```

---

## Telegram webhook

```
POST /api/v1/telegram/webhook     (header X-Telegram-Secret: <secret>)
Body: raw Telegram Update (JSON)
```
Responds `200` immediately; the update is processed asynchronously.

---

## Admin API (Bearer + RBAC)

```
GET /api/v1/admin/payments?status=PENDING_REVIEW&per_page=25
```
```json
{ "success": true, "data": { "data": [ ...paginated payments... ] } }
```

Web admin actions (session-auth, RBAC):
- `GET/POST /admin/...` (dashboard, payments, banks, devices, users, settings,
  audit, customers, reports)
- `POST /admin/payments/{id}/approve` — body `{ reason }` (role reviewer+)
- `POST /admin/payments/{id}/reject` — body `{ reason }` (role reviewer+)
- `GET /receipts/{receipt}/download` — authorized, signed access

---

## Health

```
GET /api/v1/health
GET /api/v1/health/database
GET /api/v1/health/queue
GET /api/v1/health/cache
GET /up
```

## Status codes

| Code | Meaning                                              |
|------|------------------------------------------------------|
| 200  | Success                                              |
| 201  | Created                                              |
| 401  | Unauthenticated / missing device auth                |
| 403  | Forbidden (role/permission) / invalid signature      |
| 404  | Not found                                            |
| 409  | Conflict (duplicate)                                 |
| 422  | Validation error                                     |
| 429  | Rate limited                                         |
