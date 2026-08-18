@extends('layouts.web')

@section('title', __('messages.login'))

@section('content')
<div class="card" style="max-width:420px;margin:0 auto;">
    <h2 class="mt-0">{{ __('messages.login') }}</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ url('/login') }}">
        @csrf
        <div class="field">
            <label for="email">{{ __('messages.email') }}</label>
            <input type="email" name="email" id="email" required autofocus value="{{ old('email') }}">
        </div>
        <div class="field">
            <label for="password">{{ __('messages.password') }}</label>
            <input type="password" name="password" id="password" required>
        </div>
        <div class="field">
            <label><input type="checkbox" name="remember" value="1"> {{ __('messages.remember') }}</label>
        </div>
        <button type="submit" class="btn btn-primary">{{ __('messages.sign_in') }}</button>
    </form>
</div>
@endsection
