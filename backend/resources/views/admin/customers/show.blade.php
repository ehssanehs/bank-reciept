@extends('layouts.admin')

@section('title', $customer->name ?? $customer->code)

@section('content')
<h1 class="mt-0">{{ $customer->name ?? $customer->code }}</h1>

<div class="card" style="max-width:640px;">
    <table class="data">
        <tr><td>Code</td><td class="mono">{{ $customer->code }}</td></tr>
        <tr><td>{{ __('messages.email') }}</td><td>{{ $customer->email ?? '—' }}</td></tr>
        <tr><td>Phone</td><td>{{ $customer->phone ?? '—' }}</td></tr>
        <tr><td>{{ __('messages.locale') }}</td><td>{{ $customer->locale }}</td></tr>
    </table>
</div>

<div class="card">
    <h3 class="mt-0">{{ __('messages.payments') }}</h3>
    <table class="data">
        <tr><th>{{ __('messages.code') }}</th><th>{{ __('messages.amount') }}</th><th>{{ __('messages.status') }}</th><th>{{ __('messages.created_at') }}</th></tr>
        @forelse($customer->payments as $p)
            <tr>
                <td class="mono">{{ $p->code }}</td>
                <td>{{ number_format((float)$p->amount) }} {{ $p->currency }}</td>
                <td>{{ $p->status->value }}</td>
                <td>{{ $p->created_at }}</td>
            </tr>
        @empty
            <tr><td colspan="4">{{ __('messages.no_data') }}</td></tr>
        @endforelse
    </table>
</div>
@endsection
