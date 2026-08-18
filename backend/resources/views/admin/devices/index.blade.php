@extends('layouts.admin')

@section('title', __('messages.devices'))

@section('content')
<h1 class="mt-0">{{ __('messages.devices') }}</h1>

<div class="card">
    <table class="data">
        <tr>
            <th>Device ID</th><th>{{ __('messages.name') }}</th><th>{{ __('messages.status') }}</th>
            <th>Last sync</th><th>Last seen</th>
        </tr>
        @forelse($devices as $d)
            <tr>
                <td class="mono">{{ $d->device_id }}</td>
                <td>{{ $d->name }}</td>
                <td><span class="badge {{ $d->isActive() ? 'badge-success' : 'badge-danger' }}">{{ $d->status }}</span></td>
                <td>{{ $d->last_synced_at ?? '—' }}</td>
                <td>{{ $d->last_seen_at ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5">{{ __('messages.no_data') }}</td></tr>
        @endforelse
    </table>
</div>
@endsection
