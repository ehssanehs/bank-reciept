<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSettingController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => $this->settings->all(),
            'sensitive' => $this->sensitiveKeys(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($data['settings'] as $key => $value) {
            if ($this->settings->isSensitive($key)) {
                continue;
            }
            $this->settings->set($key, $value);
        }

        $this->audit->log(AuditEvent::SETTING_CHANGED, userId: $request->user()?->id, metadata: [
            'keys' => array_keys($data['settings']),
        ]);

        return redirect()->route('admin.settings')->with('status', 'Settings saved.');
    }

    private function sensitiveKeys(): array
    {
        return [
            'telegram.bot_token' => 'TELEGRAM_BOT_TOKEN',
            'telegram.webhook_secret' => 'TELEGRAM_WEBHOOK_SECRET',
            'android.api_key' => 'ANDROID_API_KEY',
            'ocr.api_key' => 'OCR_API_KEY',
        ];
    }
}
