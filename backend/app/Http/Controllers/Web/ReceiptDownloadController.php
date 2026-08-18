<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves receipt files to authorized admins only. Receipts are stored outside
 * the public web root; no public URLs are ever exposed.
 */
class ReceiptDownloadController extends Controller
{
    public function download(PaymentReceipt $receipt): StreamedResponse
    {
        $this->authorize('view', $receipt);

        $storage = Storage::disk($receipt->disk);
        abort_unless($storage->exists($receipt->path), 404);

        return $storage->download($receipt->path, $receipt->original_name);
    }
}
