<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper over the Telegram Bot API.
 */
class TelegramService
{
    public function __construct(private readonly ?string $token = null)
    {
        $this->token = $token ?: (string) config('services.telegram.bot_token');
    }

    private function baseUrl(): string
    {
        if ($this->token === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN is not configured.');
        }

        return 'https://api.telegram.org/bot'.$this->token;
    }

    public function call(string $method, array $params = []): array
    {
        $response = Http::timeout((int) config('services.telegram.timeout', 15))
            ->post($this->baseUrl().'/'.$method, $params);

        if ($response->failed()) {
            throw new RuntimeException("Telegram API error ({$method}): ".$response->status());
        }

        $json = $response->json();
        if (!($json['ok'] ?? false)) {
            throw new RuntimeException('Telegram API error: '.($json['description'] ?? 'unknown'));
        }

        return $json['result'] ?? [];
    }

    public function sendMessage(int|string $chatId, string $text, string $parseMode = 'HTML', array $replyMarkup = []): array
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ];
        if ($replyMarkup !== []) {
            $params['reply_markup'] = json_encode($replyMarkup, JSON_UNESCAPED_UNICODE);
        }

        return $this->call('sendMessage', $params);
    }

    public function sendPhoto(int|string $chatId, string $photoPath, string $caption = ''): array
    {
        $response = Http::timeout((int) config('services.telegram.timeout', 15))
            ->attach('photo', file_get_contents($photoPath), basename($photoPath))
            ->post($this->baseUrl().'/sendPhoto', [
                'chat_id' => $chatId,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Telegram sendPhoto failed: '.$response->status());
        }

        return $response->json()['result'] ?? [];
    }

    /** @return array{file_path?:string,file_size?:int} */
    public function getFile(string $fileId): array
    {
        return $this->call('getFile', ['file_id' => $fileId]);
    }

    /** Download a file to a local temp path, returns the path. */
    public function downloadFile(string $fileId): string
    {
        $file = $this->getFile($fileId);
        $path = $file['file_path'] ?? null;
        if ($path === null) {
            throw new RuntimeException('Telegram file has no path.');
        }

        $url = 'https://api.telegram.org/file/bot'.$this->token.'/'.$path;
        $tmp = tempnam(sys_get_temp_dir(), 'tg_');
        $bytes = file_get_contents($url);
        if ($bytes === false) {
            throw new RuntimeException('Failed to download Telegram file.');
        }
        file_put_contents($tmp, $bytes);

        return $tmp;
    }

    public function setWebhook(string $url, ?string $secret): array
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'drop_pending_updates' => true,
        ]);
    }

    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook', ['drop_pending_updates' => true]);
    }

    public function getMe(): array
    {
        return $this->call('getMe');
    }
}
