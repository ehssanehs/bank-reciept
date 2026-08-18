# Bank SMS Formats

Bank SMS formats differ by bank. The system uses a **strategy/plugin architecture**:
each `banks` row names a `parser_class` (default: `DefaultBankParser`) plus optional regex
patterns for amount/tracking/date/time.

## Default heuristic parser

Handles Persian & English, Persian/Arabic/English digits, RTL, multiple separators,
and preserves the raw message. It extracts via:

- **Transaction type** — keywords: `واریز/بستانکار` (credit), `برداشت/بدهکار` (debit),
  `انتقال/حواله` (transfer), `خرید` (purchase).
- **Amount** — bank `amount_pattern`, else number nearest the currency keyword
  (`ریال`, `تومان`, `rial`, `$`, …), else the largest money-like number.
- **Tracking number** — bank `tracking_pattern`, else after keywords
  `شماره پیگیری`, `کد پیگیری`, `tracking`, `reference`, `ref`, …
- **Card/account** — 16-digit card pattern; account after `شماره حساب`, `شبا`, `iban`.
- **Date/time** — bank `date_pattern`/`time_pattern`, else year-first date tokens
  (`1405/05/27` → Jalali, `2024/05/27` → Gregorian) and `HH:MM(:SS)`.

## Example records

```json
{
  "bank": "EXAMPLE",
  "transaction_type": "credit",
  "amount": 5000000,
  "currency": "IRR",
  "tracking_number": "845621",
  "original_date": "1405/05/27",
  "original_time": "14:32",
  "normalized_timestamp": "2026-08-17 10:02:00",
  "timezone": "Asia/Tehran",
  "raw_message": "واریز مبلغ ۵٬۰۰۰٬۰۰۰ ریال ..."
}
```

## Adding a bank-specific parser

Create a class implementing `BankParserInterface` and reference it via the bank's
`parser_class` field in the admin UI. There is no bank-specific logic hardcoded in
the core pipeline.
