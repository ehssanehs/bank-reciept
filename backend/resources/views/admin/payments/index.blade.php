@extends('layouts.admin')

@section('title', __('messages.payments'))

@section('content')
<h1 class="mt-0">{{ __('messages.payments') }}</h1>

<div class="card">
    <form method="GET" action="{{ route('admin.payments.index') }}" class="filters">
        <div class="field">
            <label>{{ __('messages.code') }}</label>
            <input type="text" name="code" value="{{ $filters['code'] ?? '' }}">
        </div>
        <div class="field">
            <label>{{ __('messages.status') }}</label>
            <select name="status">
                <option value="">{{ __('messages.all') }}</option>
                @foreach($statuses as $s)
                    <option value="{{ $s }}" {{ ($filters['status'] ?? '') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>{{ __('messages.amount') }}</label>
            <input type="number" name="amount" value="{{ $filters['amount'] ?? '' }}">
        </div>
        <div class="field">
            <label>{{ __('messages.date_from') }}</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div class="field">
            <label>{{ __('messages.date_to') }}</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary">{{ __('messages.apply') }}</button>
            <a class="btn" href="{{ route('admin.payments.index') }}">{{ __('messages.reset') }}</a>
        </div>
    </form>
</div>

<div class="card">
    <table class="data">
        <tr>
            <th>{{ __('messages.code') }}</th><th>{{ __('messages.customer') }}</th>
            <th>{{ __('messages.amount') }}</th><th>{{ __('messages.status') }}</th>
            <th>{{ __('messages.risk') }}</th><th>{{ __('messages.created_at') }}</th><th>{{ __('messages.actions') }}</th>
        </tr>
        @forelse($payments as $p)
            <tr>
                <td class="mono">{{ $p->code }}</td>
                <td>{{ $p->customer?->name ?? '—' }}</td>
                <td>{{ number_format((float)$p->amount) }} {{ $p->currency }}</td>
                <td>
                    <span class="badge
                        {{ $p->status->value === 'VERIFIED' ? 'badge-success'
                           : ($p->status->value === 'REJECTED' ? 'badge-danger'
                           : ($p->status->value === 'PENDING_REVIEW' ? 'badge-warning' : 'badge-info')) }}">
                        {{ $p->status->value }}
                    </span>
                </td>
                <td>{{ $p->risk_score }}/100</td>
                <td>{{ $p->created_at }}</td>
                <td><a class="btn btn-sm" href="{{ route('admin.payments.show', $p) }}">{{ __('messages.view') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="7">{{ __('messages.no_data') }}</td></tr>
        @endforelse
    </table>
    <div class="pagination">{{ $payments->links() }}</div>
</div>
@endsection
