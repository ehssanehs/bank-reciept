<?php

namespace App\Console\Commands;

use App\Jobs\TelegramWebhookJob;
use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Runs Telegram long-polling. Kept running by supervisor/systemd.
 * Works with or without a webhook.
 */
class TelegramLongPollingCommand extends Command
{
    protected $signature = 'telegram:poll {--timeout=25}';

    protected $description = 'Poll the Telegram Bot API for updates and enqueue them.';

    public function handle(TelegramService $telegram): int
    {
        $token = config('services.telegram.bot_token');
        if ($token === '') {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        $offset = (int) Cache::get('telegram.offset', 0);

        $response = Http::timeout((int) $this->option('timeout') + 5)
            ->post('https://api.telegram.org/bot'.$token.'/getUpdates', [
                'offset' => $offset,
                'timeout' => (int) $this->option('timeout'),
            ]);

        if ($response->failed()) {
            $this->warn('getUpdates failed (HTTP '.$response->status().'), retrying.');
            sleep(3);

            return self::SUCCESS;
        }

        $json = $response->json();
        foreach ($json['result'] ?? [] as $update) {
            TelegramWebhookJob::dispatch($update);
            $offset = max($offset, (int) ($update['update_id'] ?? 0) + 1);
        }

        Cache::forever('telegram.offset', $offset);

        return self::SUCCESS;
    }
}
