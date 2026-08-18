@extends('layouts.admin')

@section('title', __('messages.dashboard'))

@section('content')
<h1 class="mt-0">{{ __('messages.dashboard') }}</h1>

<div class="grid grid-3">
    <div class="card stat"><div class="num">{{ $stats['total_payments'] }}</div><div class="lbl">{{ __('messages.total_payments') }}</div></div>
    <div class="card stat"><div class="num">{{ $stats['verified_today'] }}</div><div class="lbl">{{ __('messages.verified_today') }}</div></div>
    <div class="card stat"><div class="num">{{ $stats['pending_review'] }}</div><div class="lbl">{{ __('messages.pending_review') }}</div></div>
    <div class="card stat"><div class="num">{{ $stats['rejected'] }}</div><div class="lbl">{{ __('messages.rejected') }}</div></div>
    <div class="card stat"><div class="num">{{ $stats['failed_ocr'] }}</div><div class="lbl">{{ __('messages.failed_ocr') }}</div></div>
    <div class="card stat"><div class="num">{{ $stats['sms_received'] }}</div><div class="lbl">{{ __('messages.sms_received') }}</div></div>
    <div class="card stat"><div class="num">{{ $stats['unmatched_transactions'] }}</div><div class="lbl">{{ __('messages.unmatched_transactions') }}</div></div>
    <div class="card stat"><div class="num">{{ $stats['duplicate_attempts'] }}</div><div class="lbl">{{ __('messages.duplicate_attempts') }}</div></div>
    <div class="card stat"><div class="num">{{ $stats['telegram_messages'] }}</div><div class="lbl">{{ __('messages.telegram_messages') }}</div></div>
</div>

<h2 class="mt-4">{{ __('messages.payments') }}</h2>
<div class="card">
    <table class="data">
        <tr>
            <th>{{ __('messages.code') }}</th><th>{{ __('messages.amount') }}</th>
            <th>{{ __('messages.status') }}</th><th>{{ __('messages.risk') }}</th><th>{{ __('messages.created_at') }}</th>
        </tr>
        @forelse($recentPayments as $p)
            <tr>
                <td><a href="{{ route('admin.payments.show', $p) }}" class="mono">{{ $p->code }}</a></td>
                <td>{{ number_format((float)$p->amount) }} {{ $p->currency }}</td>
                <td><span class="badge badge-info">{{ $p->status->value }}</span></td>
                <td>{{ $p->risk_score }}/100</td>
                <td>{{ $p->created_at }}</td>
            </tr>
        @empty
            <tr><td colspan="5">{{ __('messages.no_data') }}</td></tr>
        @endforelse
    </table>
</div>
@endsection
