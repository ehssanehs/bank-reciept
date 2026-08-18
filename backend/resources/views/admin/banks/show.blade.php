@extends('layouts.admin')

@section('title', $bank->name)

@section('content')
<h1 class="mt-0">{{ $bank->name }} <span class="muted mono">({{ $bank->code }})</span></h1>

<div class="card" style="max-width:640px;">
    <table class="data">
        <tr><td>{{ __('messages.currency') }}</td><td>{{ $bank->currency }}</td></tr>
        <tr><td>{{ __('messages.sender_patterns') }}</td><td class="mono">{{ implode(', ', $bank->sender_patterns ?? []) }}</td></tr>
        <tr><td>{{ __('messages.parser') }}</td><td class="mono">{{ $bank->parser_class ?? 'default' }}</td></tr>
        <tr><td>Amount pattern</td><td class="mono">{{ $bank->amount_pattern ?? '—' }}</td></tr>
        <tr><td>Tracking pattern</td><td class="mono">{{ $bank->tracking_pattern ?? '—' }}</td></tr>
        <tr><td>Date pattern</td><td class="mono">{{ $bank->date_pattern ?? '—' }}</td></tr>
        <tr><td>Time pattern</td><td class="mono">{{ $bank->time_pattern ?? '—' }}</td></tr>
        <tr><td>{{ __('messages.is_active') }}</td><td>{{ $bank->is_active ? '✓' : '—' }}</td></tr>
    </table>
    <a href="{{ route('admin.banks.edit', $bank) }}" class="btn btn-primary mt-2">{{ __('messages.edit') }}</a>
</div>
@endsection
