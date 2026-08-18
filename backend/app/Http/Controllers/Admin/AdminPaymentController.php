<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Enums\BankTransactionStatus;
use App\Enums\MatchType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\Payment;
use App\Models\PaymentMatch;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminPaymentController extends Controller
{
    public function __construct(
        private readonly PaymentStateMachine $stateMachine,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $query = Payment::query()->with(['customer', 'matchedTransaction']);

        // Filters
        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('bank_id'), fn ($q) => $q->whereHas('matchedTransaction', fn ($t) => $t->where('bank_id', $request->input('bank_id'))))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->input('customer_id')))
            ->when($request->filled('currency'), fn ($q) => $q->where('currency', $request->input('currency')))
            ->when($request->filled('amount'), fn ($q) => $q->where('amount', $request->input('amount')))
            ->when($request->filled('tracking_number'), fn ($q) => $q->where('tracking_number', $request->input('tracking_number')))
            ->when($request->filled('code'), fn ($q) => $q->where('code', 'like', '%'.$request->input('code').'%'))
            ->when($request->filled('device_id'), fn ($q) => $q->whereHas('matchedTransaction', fn ($t) => $t->where('device_id', $request->input('device_id'))))
            ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', $request->input('date_to')));

        $payments = $query->latest()->paginate(config('services.pagination.per_page', 25))->withQueryString();

        return view('admin.payments.index', [
            'payments' => $payments,
            'statuses' => array_column(PaymentStatus::cases(), 'value'),
            'filters' => $request->all(),
        ]);
    }

    /** JSON listing used by the admin API. */
    public function indexJson(Request $request): \Illuminate\Http\JsonResponse
    {
        $payments = Payment::query()
            ->with(['customer', 'matchedTransaction'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('code'), fn ($q) => $q->where('code', 'like', '%'.$request->input('code').'%'))
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 25));

        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function show(Payment $payment): View
    {
        $payment->load([
            'customer', 'matchedTransaction.bank', 'receipts', 'matches', 'ocrResults', 'user',
        ]);

        $auditLogs = $payment->auditLogs()->latest()->limit(100)->get();

        return view('admin.payments.show', compact('payment', 'auditLogs'));
    }

    public function approve(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
            'transaction_id' => ['nullable', 'string', 'exists:bank_transactions,id'],
        ]);

        if ($payment->isTerminal()) {
            throw ValidationException::withMessages(['payment' => 'Payment is already closed.']);
        }

        $transactionId = $data['transaction_id'] ?? $payment->matched_transaction_id;

        DB::transaction(function () use ($payment, $data, $transactionId, $request) {
            // Reserve the matched transaction if present
            if ($transactionId) {
                $locked = BankTransaction::query()->lockForUpdate()->find($transactionId);
                if ($locked !== null && $locked->status === BankTransactionStatus::AVAILABLE && $locked->used_by_payment_id === null) {
                    $locked->forceFill([
                        'status' => BankTransactionStatus::USED,
                        'used_by_payment_id' => $payment->id,
                    ])->save();
                    $payment->matched_transaction_id = $transactionId;
                }
            }

            $payment->forceFill([
                'recommendation' => 'ADMIN APPROVED',
                'verification_notes' => $data['reason'],
            ])->save();

            $this->stateMachine->transition($payment, PaymentStatus::VERIFIED, $data['reason'], $request->user());
        });

        $this->audit->log(AuditEvent::ADMIN_APPROVED, userId: $request->user()?->id, paymentId: $payment->id,
            oldStatus: $payment->status->value, newStatus: PaymentStatus::VERIFIED->value,
            metadata: ['reason' => $data['reason']]);

        return redirect()->route('admin.payments.show', $payment)->with('status', 'Payment approved.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5'],
        ]);

        if ($payment->isTerminal()) {
            throw ValidationException::withMessages(['payment' => 'Payment is already closed.']);
        }

        $this->stateMachine->transition($payment, PaymentStatus::REJECTED, $data['reason'], $request->user());

        $this->audit->log(AuditEvent::ADMIN_REJECTED, userId: $request->user()?->id, paymentId: $payment->id,
            oldStatus: $payment->status->value, newStatus: PaymentStatus::REJECTED->value,
            metadata: ['reason' => $data['reason']]);

        return redirect()->route('admin.payments.show', $payment)->with('status', 'Payment rejected.');
    }
}
