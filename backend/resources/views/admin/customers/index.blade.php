@extends('layouts.admin')

@section('title', __('messages.customers'))

@section('content')
<h1 class="mt-0">{{ __('messages.customers') }}</h1>

<div class="card">
    <form method="GET" action="{{ route('admin.customers') }}" class="filters">
        <div class="field"><label>{{ __('messages.search') }}</label><input type="text" name="q" value="{{ request('q') }}"></div>
        <div class="field"><label>&nbsp;</label><button class="btn btn-primary">{{ __('messages.apply') }}</button></div>
    </form>
</div>

<div class="card">
    <table class="data">
        <tr><th>Code</th><th>{{ __('messages.name') }}</th><th>{{ __('messages.email') }}</th><th>Phone</th><th>#{{ __('messages.payments') }}</th></tr>
        @forelse($customers as $c)
            <tr>
                <td class="mono">{{ $c->code }}</td>
                <td><a href="{{ route('admin.customers.show', $c) }}">{{ $c->name ?? '—' }}</a></td>
                <td>{{ $c->email ?? '—' }}</td>
                <td>{{ $c->phone ?? '—' }}</td>
                <td>{{ $c->payments_count }}</td>
            </tr>
        @empty
            <tr><td colspan="5">{{ __('messages.no_data') }}</td></tr>
        @endforelse
    </table>
</div>
@endsection
