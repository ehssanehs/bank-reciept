@extends('layouts.admin')

@section('title', __('messages.payment_detail').' '.$payment->code)

@section('content')
<h1 class="mt-0">{{ __('messages.payment_detail') }}: <span class="mono">{{ $payment->code }}</span></h1>

<div class="grid grid-2">
    <div class="card">
        <h3 class="mt-0">{{ __('messages.customer') }}</h3>
        <p class="mb-0">{{ $payment->customer?->name ?? '—' }}<br>
        <span class="muted">{{ $payment->customer?->email ?? '—' }} · {{ $payment->customer?->phone ?? '—' }}</span></p>

        <h4 class="mt-4 mb-2">{{ __('messages.order') }}</h4>
        <p class="mb-0">Order ID: {{ $payment->order_id ?? '—' }}<br>
        Amount: <strong>{{ number_format((float)$payment->amount) }} {{ $payment->currency }}</strong></p>

        <h4 class="mt-4 mb-2">{{ __('messages.status') }}</h4>
        <span class="badge badge-info">{{ $payment->status->value }}</span>
        <span class="muted"> · {{ __('messages.risk') }}: {{ $payment->risk_score }}/100</span>

        <h4 class="mt-4 mb-2">{{ __('messages.recommendation') }}</h4>
        <p class="mb-0">{{ $payment->recommendation ?? '—' }}</p>
        @if($payment->verification_notes)
            <p class="muted mb-0 mt-2">{{ $payment->verification_notes }}</p>
        @endif
    </div>

    <div class="card">
        <h3 class="mt-0">{{ __('messages.receipt') }}</h3>
        @forelse($payment->receipts as $receipt)
            <div class="mb-2">
                <strong>{{ $receipt->original_name }}</strong>
                <span class="badge badge-info">{{ $receipt->status }}</span><br>
                <span class="muted mono">{{ $receipt->sha256 }}</span><br>
                <a class="btn btn-sm mt-2" href="{{ route('receipts.download', $receipt) }}">{{ __('messages.receipt') }}</a>
            </div>
        @empty
            <p class="muted">{{ __('messages.no_data') }}</p>
        @endforelse

        <h4 class="mt-4 mb-2">{{ __('messages.ocr_data') }}</h4>
        @forelse($payment->ocrResults as $ocr)
            <div class="mb-2">
                <span class="badge {{ $ocr->isSuccess() ? 'badge-success' : 'badge-danger' }}">{{ $ocr->status }}</span>
                <span class="muted"> confidence {{ round($ocr->confidence, 2) }} · {{ $ocr->provider }}</span>
                <table class="data mt-2">
                    @foreach(($ocr->normalized_fields ?: []) as $k => $f)
                        <tr><td class="muted">{{ $k }}</td><td class="mono">{{ $f['value'] ?? '—' }}</td><td>{{ isset($f['confidence']) ? round($f['confidence'], 2) : '' }}</td></tr>
                    @endforeach
                </table>
            </div>
        @empty
            <p class="muted">{{ __('messages.no_data') }}</p>
        @endforelse
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h3 class="mt-0">{{ __('messages.parsed_transaction') }}</h3>
        @if($payment->matchedTransaction)
            <table class="data">
                <tr><td>Bank</td><td>{{ $payment->matchedTransaction->bank?->name }}</td></tr>
                <tr><td>Amount</td><td>{{ number_format((float)$payment->matchedTransaction->amount) }} {{ $payment->matchedTransaction->currency }}</td></tr>
                <tr><td>Tracking</td><td class="mono">{{ $payment->matchedTransaction->tracking_number ?? '—' }}</td></tr>
                <tr><td>Type</td><td>{{ $payment->matchedTransaction->transaction_type ?? '—' }}</td></tr>
                <tr><td>Timestamp</td><td>{{ $payment->matchedTransaction->normalized_timestamp ?? '—' }}</td></tr>
                <tr><td>{{ __('messages.status') }}</td><td>{{ $payment->matchedTransaction->status->value }}</td></tr>
            </table>
        @else
            <p class="muted">{{ __('messages.no_data') }}</p>
        @endif

        <h4 class="mt-4 mb-2">{{ __('messages.bank_sms') }}</h4>
        @if($payment->matchedTransaction?->bankSms)
            <pre class="mono">{{ $payment->matchedTransaction->bankSms->message_body }}</pre>
        @else
            <p class="muted">—</p>
        @endif
    </div>

    <div class="card">
        <h3 class="mt-0">{{ __('messages.matching_result') }}</h3>
        @forelse($payment->matches as $m)
            <table class="data">
                <tr><td>Match type</td><td>{{ $m->match_type->value }}</td></tr>
                <tr><td>Score</td><td>{{ $m->score }}</td></tr>
                <tr><td>Fields</td><td>{{ implode(', ', $m->matched_fields ?? []) }}</td></tr>
                <tr><td>{{ __('messages.recommendation') }}</td><td>{{ $payment->recommendation }}</td></tr>
            </table>
        @empty
            <p class="muted">{{ __('messages.no_data') }}</p>
        @endforelse

        @if(in_array($payment->status->value, ['PENDING_REVIEW', 'RECEIPT_RECEIVED', 'MATCHING', 'AWAITING_RECEIPT']))
            <h4 class="mt-4 mb-2">{{ __('messages.approve_payment') }} / {{ __('messages.reject_payment') }}</h4>
            <form method="POST" action="{{ route('admin.payments.approve', $payment) }}" class="mb-2">
                @csrf
                <div class="field">
                    <label>{{ __('messages.reason') }} ({{ __('messages.approve') }})</label>
                    <textarea name="reason" required rows="2"></textarea>
                </div>
                <button class="btn btn-success">{{ __('messages.approve') }}</button>
            </form>
            <form method="POST" action="{{ route('admin.payments.reject', $payment) }}">
                @csrf
                <div class="field">
                    <label>{{ __('messages.reason') }} ({{ __('messages.reject') }})</label>
                    <textarea name="reason" required rows="2"></textarea>
                </div>
                <button class="btn btn-danger">{{ __('messages.reject') }}</button>
            </form>
        @endif
    </div>
</div>

<div class="card">
    <h3 class="mt-0">{{ __('messages.timeline') }}</h3>
    <ul class="timeline">
        @forelse($auditLogs as $log)
            <li>
                <span class="dot"></span>
                <strong>{{ $log->event->value }}</strong>
                <span class="muted">· {{ $log->created_at }}</span>
                @if($log->old_status || $log->new_status)
                    <div class="muted">{{ $log->old_status }} → {{ $log->new_status }}</div>
                @endif
                <div class="mono muted">{{ json_encode($log->metadata, JSON_UNESCAPED_UNICODE) }}</div>
            </li>
        @empty
            <li class="muted">{{ __('messages.no_data') }}</li>
        @endforelse
    </ul>
</div>
@endsection
