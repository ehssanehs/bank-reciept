@extends('layouts.admin')

@section('title', __('messages.report'))

@section('content')
<h1 class="mt-0">{{ __('messages.report') }}</h1>

<div class="grid grid-3">
    <div class="card stat"><div class="num">{{ $totalCount }}</div><div class="lbl">{{ __('messages.total') }}</div></div>
    <div class="card stat"><div class="num">{{ number_format($totalVolume) }}</div><div class="lbl">{{ __('messages.volume') }} (IRR)</div></div>
    <div class="card stat"><div class="num">{{ count($summary) }}</div><div class="lbl">Statuses</div></div>
</div>

<div class="card">
    <h3 class="mt-0">By status</h3>
    <table class="data">
        @foreach($summary as $status => $total)
            <tr><td>{{ $status }}</td><td>{{ $total }}</td></tr>
        @endforeach
    </table>
</div>

<div class="card">
    <h3 class="mt-0">{{ __('messages.by_day') }}</h3>
    <table class="data">
        <tr><th>{{ __('messages.date') }}</th><th>{{ __('messages.status') }}</th><th>{{ __('messages.total') }}</th></tr>
        @forelse($byDay as $row)
            <tr><td>{{ $row->day }}</td><td>{{ $row->status }}</td><td>{{ $row->total }}</td></tr>
        @empty
            <tr><td colspan="3">{{ __('messages.no_data') }}</td></tr>
        @endforelse
    </table>
</div>

<div class="card">
    <table class="data">
        <tr><th>{{ __('messages.code') }}</th><th>{{ __('messages.status') }}</th><th>{{ __('messages.amount') }}</th><th>{{ __('messages.created_at') }}</th></tr>
        @forelse($rows as $p)
            <tr>
                <td class="mono">{{ $p->code }}</td>
                <td>{{ $p->status->value }}</td>
                <td>{{ number_format((float)$p->amount) }} {{ $p->currency }}</td>
                <td>{{ $p->created_at }}</td>
            </tr>
        @empty
            <tr><td colspan="4">{{ __('messages.no_data') }}</td></tr>
        @endforelse
    </table>
    <div class="pagination">{{ $rows->links() }}</div>
</div>
@endsection
