<?php

namespace App\Services\Receipt;

use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\Storage;

/**
 * Enforces the configurable receipt retention period: automatically deletes
 * old receipt records and their stored files.
 */
class ReceiptRetentionService
{
    public function prune(): int
    {
        $days = (int) config('services.receipts.retention_days', 365);
        if ($days <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($days);
        $count = 0;

        PaymentReceipt::query()
            ->where('created_at', '<', $cutoff)
            ->chunkById(200, function ($receipts) use (&$count) {
                foreach ($receipts as $receipt) {
                    $storage = Storage::disk($receipt->disk);
                    if ($storage->exists($receipt->path)) {
                        $storage->delete($receipt->path);
                    }
                    $receipt->delete();
                    $count++;
                }
            });

        return $count;
    }
}
