# Database

The schema is defined by **Laravel migrations** in
[`backend/database/migrations`](../backend/database/migrations) — those are the source of
truth. Run with `php artisan migrate`.

## Tables

| Table                 | Purpose                                              | Key unique constraints / indexes              |
|-----------------------|------------------------------------------------------|-----------------------------------------------|
| `users`               | Admin / staff accounts                               | `email` unique                                |
| `roles` / `permissions` / `role_permission` / `role_user` | RBAC                              | `slug` unique                                 |
| `customers`           | Customers (optional website link)                    | `code` index, `link_code`                     |
| `devices`             | Android devices (credential hashes/encrypted)        | `device_id` unique                            |
| `banks`               | Bank config (sender patterns, parser, regexes)       | `code` unique                                 |
| `bank_sms`            | Raw SMS records                                      | unique `(device_id, sms_hash)`                |
| `bank_transactions`   | Parsed, trusted transactions                         | `hash` unique, `tracking_number`, `amount`, `transaction_date`, `status`, unique `used_by_payment_id` |
| `payments`            | Payment lifecycle                                    | `code` unique, `upload_token`, `status`, `amount`, `created_at` |
| `payment_receipts`    | Uploaded receipts (hash + storage meta)              | `sha256`, `perceptual_hash`                   |
| `ocr_results`         | OCR output per receipt                               | `status`, `(payment_id, created_at)`          |
| `payment_matches`     | Match engine decisions                               | `match_type`, `(payment_id, created_at)`      |
| `telegram_users` / `telegram_messages` | Telegram identity + messages        | `chat_id` PK, unique `(chat_id, message_id)`  |
| `audit_logs`          | Append-only audit trail                              | `event`, `created_at`, `payment_id`, `device_id`, `user_id` |
| `system_settings`     | Admin-configurable business rules                    | `key` unique                                  |
| `notifications`       | Web/Telegram notifications                           | `status`, `(payment_id, created_at)`          |
| `jobs` / `job_batches` / `failed_jobs` | Laravel queue (dead-letter)          | standard                                       |
| `cache` / `cache_locks` | Cache store                                        | `key` PK                                      |

## The single-use guarantee

`bank_transactions.used_by_payment_id` is a **nullable unique** column. In MySQL,
multiple `NULL`s are allowed, so many transactions are available, but once a transaction
is claimed its value becomes the payment id — and a second claim violates the unique
index. Combined with `SELECT ... FOR UPDATE` in the matching engine, concurrent
verification of the same transaction cannot double-spend it.

## Seeders

- `RolesAndPermissionsSeeder` — the 5 roles + permission matrix.
- `BankSeeder` — example banks (national bank, Sepah, a configurable Example Bank, a
  disabled Test Bank).
- `AdminUserSeeder` — first super-admin from `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
