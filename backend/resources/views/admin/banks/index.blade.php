@extends('layouts.admin')

@section('title', __('messages.banks'))

@section('content')
<h1 class="mt-0">{{ __('messages.banks') }}</h1>

<div class="card">
    <a href="{{ route('admin.banks.create') }}" class="btn btn-primary mb-2">{{ __('messages.create_bank') }}</a>
    <table class="data">
        <tr>
            <th>Code</th><th>{{ __('messages.name') }}</th><th>{{ __('messages.currency') }}</th>
            <th>{{ __('messages.sender_patterns') }}</th><th>{{ __('messages.is_active') }}</th><th>{{ __('messages.actions') }}</th>
        </tr>
        @forelse($banks as $bank)
            <tr>
                <td class="mono">{{ $bank->code }}</td>
                <td>{{ $bank->name }}</td>
                <td>{{ $bank->currency }}</td>
                <td class="mono">{{ implode(', ', $bank->sender_patterns ?? []) }}</td>
                <td>{{ $bank->is_active ? '✓' : '—' }}</td>
                <td><a class="btn btn-sm" href="{{ route('admin.banks.show', $bank) }}">{{ __('messages.view') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="6">{{ __('messages.no_data') }}</td></tr>
        @endforelse
    </table>
    <div class="pagination">{{ $banks->links() }}</div>
</div>
@endsection
