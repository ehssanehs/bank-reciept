<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook {--url=} {--clear}';

    protected $description = 'Set (or clear) the Telegram bot webhook.';

    public function handle(TelegramService $telegram): int
    {
        if ($this->option('clear')) {
            $telegram->deleteWebhook();
            $this->info('Webhook cleared.');

            return self::SUCCESS;
        }

        $url = $this->option('url') ?: (string) config('services.telegram.webhook_url');
        if ($url === '') {
            $this->error('Provide --url or set TELEGRAM_WEBHOOK_URL.');

            return self::FAILURE;
        }

        $secret = (string) config('services.telegram.webhook_secret');
        $result = $telegram->setWebhook($url, $secret ?: null);
        $this->info('Webhook set: '.json_encode($result));

        return self::SUCCESS;
    }
}
