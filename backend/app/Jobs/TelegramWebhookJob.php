<?php

namespace App\Jobs;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TelegramWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoffMinutes = [1, 5, 30];

    public function __construct(public readonly array $update) {}

    public function handle(TelegramBotService $bot): void
    {
        $bot->handleUpdate($this->update);
    }
}
