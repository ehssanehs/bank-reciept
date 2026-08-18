<?php

namespace App\Services\Receipt;

use App\Enums\AuditEvent;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Services\Audit\AuditLogger;
use App\Services\Ocr\ImageHasher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReceiptUploadService
{
    public function __construct(
        private readonly ReceiptFileValidator $validator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Store an uploaded receipt for a payment and return the persisted model.
     *
     * @param array{via:string,chat_id?:?int,user_id?:?string} $meta
     */
    public function store(Payment $payment, UploadedFile|string $source, array $meta = []): PaymentReceipt
    {
        $originalName = $source instanceof UploadedFile ? $source->getClientOriginalName() : basename($source);

        if ($source instanceof UploadedFile) {
            $tmpPath = $source->getRealPath();
            $mime = $source->getMimeType();
        } else {
            $tmpPath = $source;
            $mime = null;
        }

        $validated = $this->validator->validate($tmpPath, $originalName);

        $diskName = config('filesystems.default', 'local');
        $sha256 = ImageHasher::sha256($tmpPath);
        $perceptual = $validated['mime_type'] === 'application/pdf' ? '' : ImageHasher::perceptualHash($tmpPath);

        $storedName = Str::uuid().'.'.$validated['extension'];
        $relativePath = 'payments/'.$payment->id.'/'.$storedName;

        $storage = Storage::disk($diskName);
        if ($source instanceof UploadedFile) {
            $storage->putFileAs(dirname($relativePath), $source, $storedName);
        } else {
            $storage->put($relativePath, file_get_contents($tmpPath));
        }

        $receipt = PaymentReceipt::query()->create([
            'payment_id' => $payment->id,
            'upload_token' => Payment::generateUploadToken(),
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'path' => $relativePath,
            'disk' => $diskName,
            'mime_type' => $validated['mime_type'],
            'size' => $validated['size'],
            'width' => $validated['width'],
            'height' => $validated['height'],
            'sha256' => $sha256,
            'perceptual_hash' => $perceptual,
            'status' => 'stored',
            'uploaded_via' => $meta['via'] ?? 'web',
            'telegram_user_id' => $meta['chat_id'] ?? null,
            'uploaded_by_user_id' => $meta['user_id'] ?? null,
        ]);

        $this->audit->log(AuditEvent::RECEIPT_UPLOADED, paymentId: $payment->id, metadata: [
            'receipt_id' => $receipt->id,
            'mime' => $validated['mime_type'],
            'size' => $validated['size'],
            'sha256' => $sha256,
            'via' => $meta['via'] ?? 'web',
        ]);

        return $receipt;
    }
}
