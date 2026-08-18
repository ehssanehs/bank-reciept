<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelegramWebhookSecret
{
    /**
     * Guards the Telegram webhook with a shared secret header
     * (constant-time comparison).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.telegram.webhook_secret', '');
        $provided = (string) $request->header('X-Telegram-Secret', '');

        if ($expected === '' || !hash_equals($expected, $provided)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
