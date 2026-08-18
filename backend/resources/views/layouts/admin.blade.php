<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="topbar">
    <div class="container wide topbar-inner">
        <div class="brand">⚙️ {{ __('messages.brand') }}</div>
        <div class="nav-links">
            <a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a>
            <a href="{{ route('admin.payments.index') }}">{{ __('messages.payments') }}</a>
            <a href="{{ route('admin.banks.index') }}">{{ __('messages.banks') }}</a>
            <a href="{{ route('admin.devices') }}">{{ __('messages.devices') }}</a>
            <a href="{{ route('admin.customers') }}">{{ __('messages.customers') }}</a>
            <a href="{{ route('admin.audit') }}">{{ __('messages.audit') }}</a>
            <a href="{{ route('admin.reports.payments') }}">{{ __('messages.reports') }}</a>
            <a href="{{ route('admin.settings') }}">{{ __('messages.settings') }}</a>
            @auth
                <span class="muted">{{ auth()->user()->name }}</span>
                <a href="{{ route('web.locale', app()->getLocale() === 'fa' ? 'en' : 'fa') }}">
                    {{ app()->getLocale() === 'fa' ? '🇬🇧' : '🇮🇷' }}
                </a>
                <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                    @csrf
                    <button class="btn btn-sm btn-danger" type="submit">{{ __('messages.logout') }}</button>
                </form>
            @endauth
        </div>
    </div>
</div>
<main class="container wide mt-4" style="min-height:70vh;">
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @yield('content')
</main>
</body>
</html>
