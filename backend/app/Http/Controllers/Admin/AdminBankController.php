<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminBankController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.banks.index', ['banks' => Bank::query()->orderBy('code')->paginate(25)]);
    }

    public function create(): View
    {
        return view('admin.banks.form', ['bank' => new Bank()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $bank = Bank::query()->create($data);

        $this->audit->log(AuditEvent::BANK_CONFIG_CHANGED, userId: $request->user()?->id, metadata: ['bank_id' => $bank->id, 'action' => 'create']);

        return redirect()->route('admin.banks.show', $bank)->with('status', 'Bank created.');
    }

    public function show(Bank $bank): View
    {
        return view('admin.banks.show', compact('bank'));
    }

    public function edit(Bank $bank): View
    {
        return view('admin.banks.form', compact('bank'));
    }

    public function update(Request $request, Bank $bank): RedirectResponse
    {
        $data = $this->validated($request);
        $bank->update($data);

        $this->audit->log(AuditEvent::BANK_CONFIG_CHANGED, userId: $request->user()?->id, metadata: ['bank_id' => $bank->id, 'action' => 'update']);

        return redirect()->route('admin.banks.show', $bank)->with('status', 'Bank updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:8'],
            'sender_patterns' => ['required', 'string', 'max:500'],
            'parser_class' => ['nullable', 'string', 'max:255'],
            'amount_pattern' => ['nullable', 'string', 'max:255'],
            'tracking_pattern' => ['nullable', 'string', 'max:255'],
            'date_pattern' => ['nullable', 'string', 'max:255'],
            'time_pattern' => ['nullable', 'string', 'max:255'],
            'transaction_type_rules' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Normalize comma-separated sender patterns into an array.
        $data['sender_patterns'] = array_values(array_filter(
            array_map('trim', explode(',', $data['sender_patterns'])),
            fn ($p) => $p !== ''
        ));
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }
}
