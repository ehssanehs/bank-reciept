<?php

return [

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'mode' => env('TELEGRAM_MODE', 'longpolling'),
        'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),
        'link_code_ttl_minutes' => (int) env('TELEGRAM_LINK_CODE_TTL_MINUTES', 10),
        'timeout' => 15,
    ],

    'ocr' => [
        'provider' => env('OCR_PROVIDER', 'tesseract'),
        'api_key' => env('OCR_API_KEY'),
        'api_url' => env('OCR_API_URL'),
        'confidence_threshold' => (float) env('OCR_CONFIDENCE_THRESHOLD', 0.75),
        'max_attempts' => (int) env('OCR_MAX_ATTEMPTS', 3),
        'fake_file' => env('OCR_FAKE_FILE', ''),
        'tesseract_binary' => env('TESSERACT_BINARY', 'tesseract'),
    ],

    'matching' => [
        'time_tolerance_minutes' => (int) env('PAYMENT_MATCH_TIME_TOLERANCE', 15),
        'auto_approval_enabled' => filter_var(env('AUTO_APPROVAL_ENABLED', true), FILTER_VALIDATE_BOOL),
        'allowed_currencies' => array_filter(array_map('trim', explode(',', env('ALLOWED_CURRENCIES', 'IRR,USD,EUR')))),
        'auto_approve_confidence_threshold' => (float) env('AUTO_APPROVE_CONFIDENCE_THRESHOLD', 0.75),
    ],

    'receipts' => [
        'max_size_mb' => (int) env('MAX_RECEIPT_SIZE_MB', 10),
        'allowed_mime' => array_filter(array_map('trim', explode(',', env('RECEIPT_ALLOWED_MIME', 'jpg,jpeg,png,webp,pdf')))),
        'retention_days' => (int) env('RECEIPT_RETENTION_DAYS', 365),
        'signed_url_ttl_minutes' => (int) env('SIGNED_URL_TTL_MINUTES', 30),
    ],

    'fraud' => [
        'max_receipts_per_payment' => (int) env('FRAUD_MAX_RECEIPTS_PER_PAYMENT', 5),
        'max_payments_per_customer_day' => (int) env('FRAUD_MAX_PAYMENTS_PER_CUSTOMER_DAY', 20),
        'max_telegram_payments_day' => (int) env('FRAUD_MAX_TELEGRAM_PAYMENTS_DAY', 20),
        'risk_auto_review_threshold' => (int) env('FRAUD_RISK_AUTO_REVIEW_THRESHOLD', 50),
        'future_tolerance_minutes' => (int) env('FRAUD_FUTURE_TOLERANCE_MINUTES', 30),
        'verification_window_hours' => (int) env('FRAUD_VERIFICATION_WINDOW_HOURS', 72),
    ],

    'devices' => [
        'replay_ttl' => (int) env('DEVICE_SIGNATURE_REPLAY_TTL', 300),
        'token_ttl' => (int) env('DEVICE_TOKEN_TTL', 86400),
    ],

    'pagination' => [
        'per_page' => (int) env('PERPAGE_DEFAULT', 25),
    ],

];
