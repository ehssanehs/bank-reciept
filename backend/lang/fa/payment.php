<?php

return [
    'status' => [
        'CREATED' => 'ایجاد شده',
        'AWAITING_RECEIPT' => 'در انتظار رسید',
        'RECEIPT_RECEIVED' => 'رسید دریافت شد',
        'OCR_PROCESSING' => 'در حال پردازش رسید (OCR)',
        'MATCHING' => 'در حال تطبیق',
        'VERIFIED' => 'تأیید شده',
        'REJECTED' => 'رد شده',
        'PENDING_REVIEW' => 'در انتظار بررسی دستی',
        'EXPIRED' => 'منقضی شده',
        'CANCELLED' => 'لغو شده',
    ],
    'messages' => [
        'verified' => '✅ پرداخت شما با موفقیت تأیید شد.',
        'pending_review' => '⏳ پرداخت شما در حال بررسی است.',
        'rejected' => '❌ پرداخت قابل تأیید نبود.',
    ],
];
