# Payment Matching Rules

The matching engine (`app/Services/Matching/PaymentMatchingEngine.php`) compares OCR
evidence against **trusted, unused** bank transactions.

## Rule A — Strong match → auto VERIFIED

Requires **all** of:
- `tracking_number` matches (case-insensitive),
- `amount` matches,
- bank consistency (if the receipt names a bank, it must match the transaction bank),
- transaction timestamp within the configured tolerance,
- transaction type acceptable,
- transaction is `AVAILABLE` (unused),
- OCR confidence ≥ `AUTO_APPROVE_CONFIDENCE_THRESHOLD`,
- risk score < `FRAUD_RISK_AUTO_REVIEW_THRESHOLD`,
- `AUTO_APPROVAL_ENABLED=true`.

## Rule B — Alternative strong match → auto VERIFIED

When the tracking number is absent:
- `amount` matches,
- bank consistency,
- `|receipt_timestamp − transaction_timestamp| ≤ PAYMENT_MATCH_TIME_TOLERANCE` minutes,
- transaction is unused + the above confidence/risk/auto conditions.

## Rule C — Weak match → PENDING_REVIEW

Only `amount` matches (tracking differs or absent + no strong alternative).
Never auto-approved.

## No candidate → PENDING_REVIEW

If no matching trusted transaction is found, the payment goes to manual review.

## Atomic single-use

When Rule A/B passes, the engine:
1. opens a DB transaction,
2. `SELECT ... FOR UPDATE` the transaction,
3. checks it is still `AVAILABLE`,
4. sets `status = USED` and `used_by_payment_id = payment.id`,
5. commits.

The unique index on `used_by_payment_id` backs this up at the database level.

## Configuration

| Setting (env)                             | Default |
|-------------------------------------------|---------|
| `PAYMENT_MATCH_TIME_TOLERANCE` (minutes)  | 15      |
| `AUTO_APPROVE_CONFIDENCE_THRESHOLD`       | 0.75    |
| `OCR_CONFIDENCE_THRESHOLD`                | 0.75    |
| `AUTO_APPROVAL_ENABLED`                   | true    |
| `FRAUD_RISK_AUTO_REVIEW_THRESHOLD`        | 50      |
| `FRAUD_VERIFICATION_WINDOW_HOURS`         | 72      |

These are also editable per-installation via Admin → Settings (stored in
`system_settings` with environment fallback).
