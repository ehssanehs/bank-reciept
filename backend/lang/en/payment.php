<?php

return [
    'status' => [
        'CREATED' => 'Created',
        'AWAITING_RECEIPT' => 'Awaiting receipt',
        'RECEIPT_RECEIVED' => 'Receipt received',
        'OCR_PROCESSING' => 'Processing receipt (OCR)',
        'MATCHING' => 'Matching',
        'VERIFIED' => 'Verified',
        'REJECTED' => 'Rejected',
        'PENDING_REVIEW' => 'Pending review',
        'EXPIRED' => 'Expired',
        'CANCELLED' => 'Cancelled',
    ],
    'messages' => [
        'verified' => '✅ Your payment has been verified successfully.',
        'pending_review' => '⏳ Your payment is being reviewed.',
        'rejected' => '❌ The payment could not be verified.',
    ],
];
