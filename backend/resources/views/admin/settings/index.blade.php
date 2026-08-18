@extends('layouts.admin')

@section('title', __('messages.settings'))

@section('content')
<h1 class="mt-0">{{ __('messages.settings') }}</h1>

<div class="card" style="max-width:720px;">
    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        <table class="data">
            <tr>
                <th>Key</th><th>Value</th><th>Group</th>
            </tr>
            @foreach($settings as $setting)
                @continue($sensitive[$setting->key] ?? false)
                <tr>
                    <td class="mono">{{ $setting->key }}</td>
                    <td><input type="text" name="settings[{{ $setting->key }}]" value="{{ is_array($setting->value) ? json_encode($setting->value) : $setting->value }}"></td>
                    <td>{{ $setting->group }}</td>
                </tr>
            @endforeach
        </table>
        <p class="muted">Sensitive secrets (Telegram token, API keys) are managed via environment variables only.</p>
        <button type="submit" class="btn btn-primary">{{ __('messages.save') }}</button>
    </form>
</div>
@endsection
