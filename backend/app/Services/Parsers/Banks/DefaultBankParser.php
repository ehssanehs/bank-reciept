<?php

namespace App\Services\Parsers\Banks;

use App\Models\Bank;
use App\Services\Normalizers\DateTimeNormalizer;
use App\Services\Normalizers\NumberNormalizer;
use App\Services\Parsers\BankParserInterface;
use App\Services\Parsers\SmsParsedData;

/**
 * Configurable, heuristic bank-SMS parser.
 *
 * Uses per-bank regex patterns when provided (amount_pattern, tracking_pattern,
 * date_pattern, time_pattern) and falls back to generic keyword/position
 * heuristics that work for Persian and English messages. The raw message is
 * always preserved.
 */
class DefaultBankParser implements BankParserInterface
{
    private const TYPE_KEYWORDS = [
        'credit' => ['واریز', 'بستانکار', 'افزایش', 'deposit', 'credit', 'کسر نمی', 'وارد', 'received'],
        'debit' => ['برداشت', 'بدهکار', 'کسر', 'withdrawal', 'debit', 'پرداخت', 'خرج'],
        'transfer' => ['انتقال', 'transfer', 'حواله', 'کارت به کارت'],
        'purchase' => ['خرید', 'purchase', 'pos'],
    ];

    private const AMOUNT_LABELS = ['مبلغ', 'amount', 'value', 'total', 'مابه‌التفاوت'];

    private const TRACKING_LABELS = [
        'شماره پیگیری', 'کد پیگیری', 'کد رهگیری', 'شماره رهگیری',
        'tracking', 'tracking number', 'reference', 'ref', 'کد پیام', 'شماره پیام',
        'ردیف', 'شماره سند',
    ];

    public function parse(Bank $bank, string $rawMessage): SmsParsedData
    {
        $raw = $rawMessage;
        $norm = NumberNormalizer::toAsciiDigits($rawMessage);
        $lower = mb_strtolower($norm, 'UTF-8');

        $confidence = [];

        $amount = $this->extractAmount($bank, $norm, $lower, $confidence);
        $tracking = $this->extractTracking($bank, $norm, $lower, $confidence);
        $reference = $this->extractReference($norm, $lower);
        $card = $this->extractCardNumber($norm);
        $account = $this->extractAccountNumber($norm);
        $balance = $this->extractBalance($norm, $lower);
        [$dateStr, $timeStr] = $this->extractDateAndTime($bank, $norm, $lower);
        $dateTime = (new DateTimeNormalizer())->normalize($dateStr, $timeStr, $bank->timezone ?? null);

        $type = $this->extractTransactionType($lower);

        return new SmsParsedData(
            bankCode: $bank->code,
            transactionType: $type,
            amount: $amount,
            currency: $this->detectCurrency($norm, $lower) ?? $bank->currency ?? 'IRR',
            trackingNumber: $tracking,
            referenceNumber: $reference,
            cardNumber: $card,
            accountNumber: $account,
            originalDate: $dateStr ?: null,
            originalTime: $timeStr ?: null,
            normalizedTimestamp: $dateTime['normalized_timestamp'] ?? null,
            timezone: $dateTime['timezone'] ?? null,
            balance: $balance,
            raw: ['raw_message' => $raw, 'normalized' => $norm],
            confidence: $confidence,
        );
    }

    private function extractTransactionType(string $lower): ?string
    {
        foreach (self::TYPE_KEYWORDS as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $type;
                }
            }
        }

        return null;
    }

    private function extractAmount(Bank $bank, string $norm, string $lower, array &$confidence): ?int
    {
        // 1) Bank-configured pattern
        if (!empty($bank->amount_pattern)) {
            $m = $this->firstMatch($bank->amount_pattern, $norm);
            if ($m !== null) {
                $v = NumberNormalizer::toInt($m);
                if ($v !== null) {
                    $confidence['amount'] = 0.9;
                    return $v;
                }
            }
        }

        // 2) Number nearest to a currency keyword
        $currencyPos = mb_strpos($lower, 'ریال') !== false ? mb_strpos($norm, 'ریال')
            : (mb_strpos($lower, 'rial') !== false ? mb_stripos($norm, 'rial') : false);

        if ($currencyPos !== false) {
            $candidate = $this->nearestNumberBefore($norm, $currencyPos, 40);
            if ($candidate !== null) {
                $v = NumberNormalizer::toInt($candidate);
                if ($v !== null) {
                    $confidence['amount'] = 0.85;
                    return $v;
                }
            }
        }

        // 3) Number following an amount label
        foreach (self::AMOUNT_LABELS as $label) {
            $pos = mb_stripos($norm, $label);
            if ($pos !== false) {
                $segment = mb_substr($norm, $pos, 60);
                if (preg_match('/\d[\d\s,٬]*/', $segment, $mm) === 1) {
                    $v = NumberNormalizer::toInt($mm[0]);
                    if ($v !== null) {
                        $confidence['amount'] = 0.8;
                        return $v;
                    }
                }
            }
        }

        // 4) Fallback: largest standalone money-looking number
        $numbers = $this->allNumbers($norm);
        if ($numbers !== []) {
            $largest = null;
            foreach ($numbers as $num) {
                if ($num > 0 && ($largest === null || $num > $largest)) {
                    $largest = $num;
                }
            }
            if ($largest !== null && $largest > 10000) {
                $confidence['amount'] = 0.6;
                return $largest;
            }
        }

        return null;
    }

    private function extractTracking(Bank $bank, string $norm, string $lower, array &$confidence): ?string
    {
        if (!empty($bank->tracking_pattern)) {
            $m = $this->firstMatch($bank->tracking_pattern, $norm);
            if ($m !== null) {
                $confidence['tracking_number'] = 0.9;
                return $m;
            }
        }

        foreach (self::TRACKING_LABELS as $label) {
            $pos = mb_stripos($lower, $label);
            if ($pos === false) {
                continue;
            }
            $segment = mb_substr($norm, $pos + mb_strlen($label), 40);
            if (preg_match('/\s*[:：-]?\s*([A-Za-z0-9]{4,16})/', $segment, $mm) === 1) {
                $confidence['tracking_number'] = 0.85;
                return $mm[1];
            }
        }

        return null;
    }

    private function extractReference(string $norm, string $lower): ?string
    {
        foreach (['شماره مرجع', 'مرجع', 'reference no', 'ref no', 'شماره حواله', 'حواله'] as $label) {
            $pos = mb_stripos($lower, $label);
            if ($pos === false) {
                continue;
            }
            $segment = mb_substr($norm, $pos + mb_strlen($label), 40);
            if (preg_match('/\s*[:：-]?\s*([A-Za-z0-9]{4,20})/', $segment, $mm) === 1) {
                return $mm[1];
            }
        }

        return null;
    }

    private function extractCardNumber(string $norm): ?string
    {
        if (preg_match('/\b(\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4})\b/', $norm, $mm) === 1) {
            return preg_replace('/[\s-]/', '', $mm[1]);
        }

        return null;
    }

    private function extractAccountNumber(string $norm): ?string
    {
        foreach (['شماره حساب', 'account', 'شبا', 'شماره شبا', 'iban'] as $label) {
            $pos = mb_stripos($norm, $label);
            if ($pos === false) {
                continue;
            }
            $segment = mb_substr($norm, $pos + mb_strlen($label), 40);
            if (preg_match('/\s*[:：-]?\s*([A-Z0-9]{6,26})/i', $segment, $mm) === 1) {
                return $mm[1];
            }
        }

        return null;
    }

    private function extractBalance(string $norm, string $lower): ?int
    {
        foreach (['موجودی', 'balance', 'مانده'] as $label) {
            $pos = mb_stripos($norm, $label);
            if ($pos !== false) {
                $segment = mb_substr($norm, $pos, 40);
                if (preg_match('/\d[\d\s,٬]*/', $segment, $mm) === 1) {
                    $v = NumberNormalizer::toInt($mm[0]);
                    if ($v !== null) {
                        return $v;
                    }
                }
            }
        }

        return null;
    }

    /** @return array{0:?string,1:?string} [date, time] */
    private function extractDateAndTime(Bank $bank, string $norm, string $lower): array
    {
        $date = null;
        $time = null;

        if (!empty($bank->date_pattern)) {
            $m = $this->firstMatch($bank->date_pattern, $norm);
            if ($m !== null) {
                $date = $m;
            }
        } else {
            if (preg_match('/\b(\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})\b/', $norm, $mm) === 1) {
                $date = $mm[1];
            } elseif (preg_match('/\b(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4})\b/', $norm, $mm) === 1) {
                $date = $mm[1];
            }
        }

        if (!empty($bank->time_pattern)) {
            $m = $this->firstMatch($bank->time_pattern, $norm);
            if ($m !== null) {
                $time = $m;
            }
        } else {
            foreach (['ساعت', 'ساعت', 'time'] as $label) {
                $pos = mb_stripos($norm, $label);
                if ($pos !== false) {
                    if (preg_match('/\d{1,2}:\d{2}(:\d{2})?/', mb_substr($norm, $pos, 20), $mm) === 1) {
                        $time = $mm[0];
                        break;
                    }
                }
            }
            if ($time === null && preg_match('/\b(\d{1,2}:\d{2}(:\d{2})?)\b/', $norm, $mm) === 1) {
                $time = $mm[1];
            }
        }

        return [$date, $time];
    }

    private function detectCurrency(string $norm, string $lower): ?string
    {
        foreach ([
            'IRR' => ['ریال', 'rial', 'rials', 'تومان', 'toman'],
            'USD' => ['دلار', 'dollar', 'usd'],
            'EUR' => ['یورو', 'euro'],
            'AED' => ['درهم', 'dirham'],
        ] as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function nearestNumberBefore(string $norm, int $pos, int $window): ?string
    {
        $start = max(0, $pos - $window);
        $segment = mb_substr($norm, $start, $pos - $start);
        if (preg_match('/\d[\d\s,٬]*$/', $segment, $mm) === 1) {
            return $mm[0];
        }

        return null;
    }

    /** @return array<int,int> */
    private function allNumbers(string $norm): array
    {
        preg_match_all('/\b\d[\d\s,٬]*\b/', $norm, $mm);
        $out = [];
        foreach ($mm[0] ?? [] as $m) {
            $v = NumberNormalizer::toInt($m);
            if ($v !== null) {
                $out[] = $v;
            }
        }

        return $out;
    }

    private function firstMatch(string $pattern, string $subject): ?string
    {
        if (@preg_match($pattern, $subject, $mm) === 1) {
            return $mm[1] ?? $mm[0];
        }

        return null;
    }
}
