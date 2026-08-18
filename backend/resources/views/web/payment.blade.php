@extends('layouts.web')

@section('title', __('messages.payment').' '.$payment->code)

@section('content')
<div class="card" style="max-width:640px;margin:0 auto;">
    <h2 class="mt-0">{{ __('messages.payment') }}: <span class="mono">{{ $payment->code }}</span></h2>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <table class="data mb-2">
        <tr><td>{{ __('messages.amount') }}</td><td><strong>{{ number_format((float)$payment->amount) }} {{ $payment->currency }}</strong></td></tr>
        <tr><td>{{ __('messages.order_id') }}</td><td>{{ $payment->order_id ?? '—' }}</td></tr>
        <tr><td>{{ __('messages.status') }}</td>
            <td>
                <span class="badge
                    {{ $payment->status->value === 'VERIFIED' ? 'badge-success'
                       : ($payment->status->value === 'REJECTED' ? 'badge-danger'
                       : ($payment->status->value === 'PENDING_REVIEW' ? 'badge-warning' : 'badge-info')) }}">
                    {{ __('payment.status.'.$payment->status->value) }}
                </span>
            </td></tr>
        <tr><td>{{ __('messages.risk_score') }}</td><td>{{ $payment->risk_score }}/100</td></tr>
    </table>

    @if($payment->status->value === 'VERIFIED')
        <div class="alert alert-success">{{ __('payment.messages.verified') }}</div>
    @elseif($payment->status->value === 'PENDING_REVIEW')
        <div class="alert alert-warning">{{ __('payment.messages.pending_review') }}</div>
    @elseif($payment->status->value === 'REJECTED')
        <div class="alert alert-danger">{{ __('payment.messages.rejected') }}</div>
    @else
        <form method="POST" action="{{ route('web.payment.receipt', $payment->upload_token) }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label for="receipt">{{ __('messages.receipt') }}</label>
                <input type="file" name="receipt" id="receipt" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                <small class="muted">{{ __('messages.receipt_hint') }}</small>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('messages.upload_receipt') }}</button>
        </form>
    @endif

    @if($payment->receipts->isNotEmpty())
        <h4 class="mt-4 mb-2">{{ __('messages.receipts') }}</h4>
        <table class="data">
            <tr><th>{{ __('messages.date') }}</th><th>{{ __('messages.file') }}</th><th>{{ __('messages.status') }}</th></tr>
            @foreach($payment->receipts as $receipt)
                <tr>
                    <td>{{ $receipt->created_at }}</td>
                    <td>{{ $receipt->original_name }}</td>
                    <td><span class="badge badge-info">{{ $receipt->status }}</span></td>
                </tr>
            @endforeach
        </table>
    @endif
</div>
@endsection
