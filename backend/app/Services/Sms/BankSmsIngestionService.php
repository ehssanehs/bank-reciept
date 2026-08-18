<?php

namespace App\Services\Sms;

use App\Enums\AuditEvent;
use App\Enums\BankTransactionStatus;
use App\Models\Bank;
use App\Models\BankSms;
use App\Models\BankTransaction;
use App\Models\Device;
use App\Services\Audit\AuditLogger;
use App\Services\Parsers\BankParserRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handles bank SMS arriving from the Android device: deduplicates, attributes
 * to a bank, parses and stores the resulting bank transaction.
 */
class BankSmsIngestionService
{
    public function __construct(
        private readonly BankParserRegistry $registry,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param array<int,array{
     *     local_id?: string,
     *     sender: string,
     *     message: string,
     *     received_at?: ?string
     * }> $messages
     * @return array{accepted:int,duplicates:int,ignored:int,parsed:int}
     */
    public function ingest(Device $device, array $messages): array
    {
        $counts = ['accepted' => 0, 'duplicates' => 0, 'ignored' => 0, 'parsed' => 0];

        foreach ($messages as $message) {
            $sender = trim((string) ($message['sender'] ?? ''));
            $body = (string) ($message['message'] ?? '');
            $receivedAt = $message['received_at'] ?? null;
            $localId = (string) ($message['local_id'] ?? '');

            if ($sender === '' || $body === '') {
                continue;
            }

            $bank = $this->resolveBank($sender);

            if ($bank === null) {
                $counts['ignored']++;
                $this->audit->log(AuditEvent::SMS_RECEIVED, deviceId: $device->id, metadata: [
                    'sender' => $sender, 'local_id' => $localId, 'result' => 'sender_not_recognized',
                ]);
                continue;
            }

            $hash = BankSms::computeHash($device->device_id, $sender, $body, $receivedAt);

            $duplicate = BankSms::query()
                ->where('device_id', $device->id)
                ->where('sms_hash', $hash)
                ->exists();

            if ($duplicate) {
                $counts['duplicates']++;
                $this->audit->log(AuditEvent::SMS_DUPLICATE_REJECTED, deviceId: $device->id, metadata: [
                    'sender' => $sender, 'local_id' => $localId,
                ]);
                continue;
            }

            $sms = BankSms::query()->create([
                'device_id' => $device->id,
                'bank_id' => $bank->id,
                'local_id' => $localId,
                'sender' => $sender,
                'message_body' => $body,
                'received_at' => $receivedAt ? new \DateTimeImmutable($receivedAt) : now(),
                'sms_hash' => $hash,
                'status' => 'received',
                'raw' => $message,
            ]);

            $counts['accepted']++;
            $this->audit->log(AuditEvent::SMS_RECEIVED, deviceId: $device->id, metadata: [
                'bank_sms_id' => $sms->id, 'sender' => $sender, 'local_id' => $localId,
            ]);

            if ($this->storeTransaction($sms, $bank)) {
                $counts['parsed']++;
            }
        }

        $device->forceFill(['last_synced_at' => now()])->save();

        return $counts;
    }

    private function resolveBank(string $sender): ?Bank
    {
        $senderLower = mb_strtolower(trim($sender), 'UTF-8');
        $patterns = [];

        // gather patterns from all active banks
        Bank::query()->where('is_active', true)->orderBy('code')->get()
            ->each(function (Bank $bank) use (&$patterns) {
                foreach ($bank->senderPatterns() as $pattern) {
                    $patterns[$pattern] = $bank;
                }
            });

        foreach ($patterns as $pattern => $bank) {
            if ($senderLower === $pattern || str_contains($senderLower, $pattern)) {
                return $bank;
            }
        }

        return null;
    }

    private function storeTransaction(BankSms $sms, Bank $bank): bool
    {
        $parsed = $this->registry->parserFor($bank)->parse($bank, $sms->message_body);

        $this->audit->log(AuditEvent::SMS_PARSED, deviceId: $sms->device_id, metadata: [
            'bank_sms_id' => $sms->id,
            'parsed' => $parsed->toArray(),
        ]);

        $hashFields = array_filter([
            'bank_id' => $bank->id,
            'amount' => $parsed->amount,
            'tracking' => $parsed->trackingNumber,
            'reference' => $parsed->referenceNumber,
            'card' => $parsed->cardNumber,
            'ts' => $parsed->normalizedTimestamp,
        ]);

        try {
            return DB::transaction(function () use ($sms, $bank, $parsed, $hashFields) {
                $tx = BankTransaction::query()->create([
                    'bank_sms_id' => $sms->id,
                    'bank_id' => $bank->id,
                    'device_id' => $sms->device_id,
                    'transaction_type' => $parsed->transactionType,
                    'amount' => $parsed->amount,
                    'currency' => $parsed->currency,
                    'tracking_number' => $parsed->trackingNumber,
                    'reference_number' => $parsed->referenceNumber,
                    'card_number' => $parsed->cardNumber,
                    'account_number' => $parsed->accountNumber,
                    'original_date' => $parsed->originalDate,
                    'original_time' => $parsed->originalTime,
                    'transaction_date' => $parsed->normalizedTimestamp ? substr($parsed->normalizedTimestamp, 0, 10) : null,
                    'transaction_time' => $parsed->normalizedTimestamp ? substr($parsed->normalizedTimestamp, 11, 8) : null,
                    'normalized_timestamp' => $parsed->normalizedTimestamp,
                    'timezone' => $parsed->timezone,
                    'raw_message' => $sms->message_body,
                    'parsed' => $parsed->toArray(),
                    'hash' => BankTransaction::computeHash($hashFields),
                    'status' => BankTransactionStatus::AVAILABLE,
                    'first_seen_at' => now(),
                ]);

                $sms->forceFill(['status' => 'parsed'])->save();

                return true;
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to store bank transaction', [
                'bank_sms_id' => $sms->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
