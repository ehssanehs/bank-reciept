@extends('layouts.admin')

@section('title', __('messages.audit'))

@section('content')
<h1 class="mt-0">{{ __('messages.audit') }}</h1>

<div class="card">
    <form method="GET" action="{{ route('admin.audit') }}" class="filters">
        <div class="field"><label>Event</label><input type="text" name="event" value="{{ request('event') }}"></div>
        <div class="field"><label>{{ __('messages.date_from') }}</label><input type="date" name="date_from" value="{{ request('date_from') }}"></div>
        <div class="field"><label>{{ __('messages.date_to') }}</label><input type="date" name="date_to" value="{{ request('date_to') }}"></div>
        <div class="field"><label>&nbsp;</label><button class="btn btn-primary">{{ __('messages.apply') }}</button></div>
    </form>
</div>

<div class="card">
    <table class="data">
        <tr><th>Event</th><th>{{ __('messages.payment') }}</th><th>IP</th><th>Old</th><th>New</th><th>{{ __('messages.created_at') }}</th></tr>
        @forelse($logs as $log)
            <tr>
                <td class="mono">{{ $log->event->value }}</td>
                <td>{{ $log->payment_id ? (substr($log->payment_id, 0, 8)) : '—' }}</td>
                <td class="mono">{{ $log->ip }}</td>
                <td>{{ $log->old_status }}</td>
                <td>{{ $log->new_status }}</td>
                <td>{{ $log->created_at }}</td>
            </tr>
        @empty
            <tr><td colspan="6">{{ __('messages.no_data') }}</td></tr>
        @endforelse
    </table>
    <div class="pagination">{{ $logs->links() }}</div>
</div>
@endsection
