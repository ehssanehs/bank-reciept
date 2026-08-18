<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankSms;
use App\Models\BankTransaction;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Models\TelegramMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->startOfDay();

        $stats = [
            'total_payments' => Payment::query()->count(),
            'verified_today' => Payment::query()->where('status', 'VERIFIED')->where('updated_at', '>=', $today)->count(),
            'pending_review' => Payment::query()->where('status', 'PENDING_REVIEW')->count(),
            'rejected' => Payment::query()->where('status', 'REJECTED')->count(),
            'failed_ocr' => DB::table('ocr_results')->where('status', 'failed')->count(),
            'sms_received' => BankSms::query()->count(),
            'unmatched_transactions' => BankTransaction::query()->whereNull('used_by_payment_id')->count(),
            'duplicate_attempts' => DB::table('bank_sms')->where('status', 'duplicate')->count(),
            'telegram_messages' => TelegramMessage::query()->count(),
        ];

        $recentPayments = Payment::query()
            ->with(['customer', 'matchedTransaction'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentPayments'));
    }
}
