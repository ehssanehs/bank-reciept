<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'amount' => 5000000,
            'currency' => 'IRR',
            'code' => Payment::generateCode(),
            'status' => PaymentStatus::CREATED,
            'risk_score' => 0,
            'ocr_confidence' => 0.0,
            'upload_token' => Payment::generateUploadToken(),
        ];
    }
}
