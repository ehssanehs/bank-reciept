<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessReceiptJob;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Services\Receipt\ReceiptUploadService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly ReceiptUploadService $receipts,
    ) {}

    /**
     * Create a payment for the authenticated user/customer.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'order_id' => ['sometimes', 'string', 'max:128'],
            'tracking_number' => ['sometimes', 'string', 'max:64'],
        ]);

        $customer = $request->user()?->customer ?? null;
        $payment = $this->payments->createPayment(
            $data,
            customer: $customer,
            user: $request->user()
        );

        return response()->json([
            'success' => true,
            'data' => $this->paymentPayload($payment),
        ], 201);
    }

    /**
     * Public payment status by one-time upload token.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $payment = $this->payments->findByUploadToken($token);
        if ($payment === null) {
            return response()->json(['success' => false, 'message' => 'Payment not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->paymentPayload($payment)]);
    }

    /**
     * Public receipt upload for a payment, addressed by one-time upload token.
     */
    public function uploadReceipt(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'receipt' => ['required', 'file'],
        ]);

        $payment = $this->payments->findByUploadToken($token);
        if ($payment === null) {
            return response()->json(['success' => false, 'message' => 'Payment not found or token expired.'], 404);
        }
        if ($payment->isTerminal()) {
            return response()->json(['success' => false, 'message' => 'Payment is already closed.'], 422);
        }

        $receipt = DB::transaction(function () use ($request, $payment) {
            $receipt = $this->receipts->store($payment, $request->file('receipt'), ['via' => 'web']);
            $this->payments->markReceiptReceived($payment);

            return $receipt;
        });

        ProcessReceiptJob::dispatch($receipt->id);

        return response()->json([
            'success' => true,
            'message' => 'Receipt received and queued for verification.',
            'data' => $this->paymentPayload($payment),
        ]);
    }

    private function paymentPayload(Payment $payment): array
    {
        return [
            'id' => $payment->code,
            'upload_token' => $payment->upload_token,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'order_id' => $payment->order_id,
            'status' => $payment->status->value,
            'risk_score' => $payment->risk_score,
            'ocr_confidence' => $payment->ocr_confidence,
            'created_at' => $payment->created_at?->toIso8601String(),
            'expires_at' => $payment->expires_at?->toIso8601String(),
        ];
    }
}
