<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        Bank::query()->updateOrCreate(['code' => 'MELI'], [
            'name' => 'بانک ملی ایران',
            'currency' => 'IRR',
            'sender_patterns' => ['MELI', 'Melli', 'بانک ملی', 'melli'],
            'parser_class' => \App\Services\Parsers\Banks\DefaultBankParser::class,
            'is_active' => true,
        ]);

        Bank::query()->updateOrCreate(['code' => 'SEPAH'], [
            'name' => 'بانک سپه',
            'currency' => 'IRR',
            'sender_patterns' => ['Sepah', 'SEPAH', 'بانک سپه', 'spb'],
            'parser_class' => \App\Services\Parsers\Banks\DefaultBankParser::class,
            'is_active' => true,
        ]);

        Bank::query()->updateOrCreate(['code' => 'EXAMPLE'], [
            'name' => 'Example Bank',
            'currency' => 'IRR',
            'sender_patterns' => ['BANK', 'ExampleBank', 'example'],
            'parser_class' => \App\Services\Parsers\Banks\DefaultBankParser::class,
            'amount_pattern' => '/مبلغ\s*[:：]?\s*([\d,٬]+)/u',
            'tracking_pattern' => '/شماره پیگیری\s*[:：]?\s*([A-Za-z0-9]+)/u',
            'date_pattern' => '/(\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})/',
            'time_pattern' => '/(\d{1,2}:\d{2}(?::\d{2})?)/',
            'is_active' => true,
        ]);

        Bank::query()->updateOrCreate(['code' => 'TEST'], [
            'name' => 'Test Bank (sandbox)',
            'currency' => 'IRR',
            'sender_patterns' => ['TESTSMS', 'TestBank'],
            'parser_class' => \App\Services\Parsers\Banks\DefaultBankParser::class,
            'is_active' => false,
        ]);
    }
}
