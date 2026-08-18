<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Services\Receipt\ReceiptUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WebController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly ReceiptUploadService $receipts,
    ) {}

    public function home(): View
    {
        return view('web.home');
    }

    public function showPayForm(): View
    {
        return view('web.pay');
    }

    public function createPay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'order_id' => ['sometimes', 'string', 'max:128'],
            'tracking_number' => ['sometimes', 'string', 'max:64'],
        ]);

        $user = Auth::user();
        $customer = $user ? Customer::query()->where('user_id', $user->id)->first() : null;

        $payment = $this->payments->createPayment(
            $data,
            customer: $customer,
            user: $user,
        );

        return redirect()->route('web.payment', $payment->upload_token);
    }

    public function showPayment(string $token): View
    {
        $payment = $this->payments->findByUploadToken($token);
        if ($payment === null) {
            abort(404, 'Payment not found.');
        }

        $payment->load(['receipts', 'customer']);

        return view('web.payment', compact('payment'));
    }

    public function uploadReceipt(Request $request, string $token): RedirectResponse
    {
        $request->validate(['receipt' => ['required', 'file']]);

        $payment = $this->payments->findByUploadToken($token);
        if ($payment === null || $payment->isTerminal()) {
            return back()->withErrors(['receipt' => 'Payment not found or closed.']);
        }

        try {
            DB::transaction(function () use ($request, $payment) {
                $this->receipts->store($payment, $request->file('receipt'), ['via' => 'web']);
                $this->payments->markReceiptReceived($payment);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('web.payment', $token)->with('status', 'Receipt uploaded. Verification in progress.');
    }

    public function link(): View
    {
        $user = Auth::user();

        return view('web.link', ['customer' => $user ? Customer::query()->where('user_id', $user->id)->first() : null]);
    }

    public function generateLink(Request $request): RedirectResponse
    {
        $user = Auth::user() ?? abort(403);

        $customer = Customer::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'code' => 'CUS'.strtoupper(substr(str_replace('-', '', (string) $user->id), 0, 8)),
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'is_active' => true,
            ]
        );

        $code = (string) random_int(100000, 999999);
        $ttl = (int) config('services.telegram.link_code_ttl_minutes', 10);

        $customer->forceFill([
            'link_code' => $code,
            'link_code_expires_at' => now()->addMinutes($ttl),
        ])->save();

        return redirect()->route('web.link')->with('link_code', $code)->with('link_ttl', $ttl);
    }
}
