<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="topbar">
    <div class="container topbar-inner">
        <div class="brand">{{ __('messages.brand') }}</div>
        <div class="nav-links">
            <a href="{{ route('web.home') }}">{{ __('messages.home') }}</a>
            <a href="{{ route('web.pay') }}">{{ __('messages.pay') }}</a>
            <a href="{{ route('web.locale', app()->getLocale() === 'fa' ? 'en' : 'fa') }}">
                {{ app()->getLocale() === 'fa' ? '🇬🇧 English' : '🇮🇷 فارسی' }}
            </a>
        </div>
    </div>
</div>
<main class="container mt-4" style="min-height:70vh;">
    @yield('content')
</main>
<footer class="container muted mt-4 mb-2" style="padding-bottom:24px;">
    &copy; {{ date('Y') }} {{ config('app.name') }}
</footer>
</body>
</html>
