<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Policies\PaymentPolicy;
use App\Policies\PaymentReceiptPolicy;
use App\Services\Ocr\CloudOCRProvider;
use App\Services\Ocr\FakeOCRProvider;
use App\Services\Ocr\OCRProvider;
use App\Services\Ocr\TesseractOCRProvider;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);

        $this->app->bind(OCRProvider::class, function ($app) {
            $provider = config('services.ocr.provider', 'tesseract');
            return match ($provider) {
                'cloud', 'ai' => new CloudOCRProvider(),
                'fake' => new FakeOCRProvider(),
                default => new TesseractOCRProvider(),
            };
        });
    }

    public function boot(): void
    {
        // Explicit schema to keep MySQL happy on shared hosting.
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);

        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PaymentReceipt::class, PaymentReceiptPolicy::class);
    }
}
