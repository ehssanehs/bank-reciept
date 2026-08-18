@extends('layouts.admin')

@section('title', __('messages.users'))

@section('content')
<h1 class="mt-0">{{ __('messages.users') }}</h1>

<div class="card">
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary mb-2">{{ __('messages.create_user') }}</a>
    <table class="data">
        <tr><th>{{ __('messages.name') }}</th><th>{{ __('messages.email') }}</th><th>{{ __('messages.role') }}</th><th>{{ __('messages.locale') }}</th><th>{{ __('messages.actions') }}</th></tr>
        @forelse($users as $u)
            <tr>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->roles->pluck('slug')->implode(', ') }}</td>
                <td>{{ $u->locale }}</td>
                <td><a class="btn btn-sm" href="{{ route('admin.users.edit', $u) }}">{{ __('messages.edit') }}</a></td>
            </tr>
        @empty
            <tr><td colspan="5">{{ __('messages.no_data') }}</td></tr>
        @endforelse
    </table>
</div>
@endsection
