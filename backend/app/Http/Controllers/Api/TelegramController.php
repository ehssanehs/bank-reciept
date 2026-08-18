<?php

namespace App\Http\Controllers\Api;

use App\Jobs\TelegramWebhookJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Telegram webhook entry point.
 *
 * Validates the shared secret, enqueues the update and returns immediately —
 * expensive work (download, OCR, matching) happens in the queue, never here.
 */
class TelegramController extends Controller
{
    public function webhook(Request $request): JsonResponse
    {
        $update = $request->all();

        // Reject malformed updates
        if (!is_array($update) || ($update === [])) {
            return response()->json(['success' => false, 'message' => 'Empty update.'], 400);
        }

        TelegramWebhookJob::dispatch($update);

        return response()->json(['success' => true]);
    }
}
