@extends('layouts.web')

@section('title', __('messages.link_account'))

@section('content')
<div class="card" style="max-width:560px;margin:0 auto;">
    <h2 class="mt-0">{{ __('messages.link_account') }}</h2>

    @if(session('link_code'))
        <div class="alert alert-success">
            <p class="mb-0">{{ __('messages.link_code') }}:</p>
            <strong class="mono" style="font-size:26px;">{{ session('link_code') }}</strong>
            <p class="mb-0 mt-2">{{ __('messages.link_expires', ['ttl' => session('link_ttl', 10)]) }}</p>
        </div>
    @endif

    <p>{{ __('messages.link_instructions') }}</p>

    <form method="POST" action="{{ route('web.link.generate') }}">
        @csrf
        <button type="submit" class="btn btn-primary">{{ __('messages.generate_link_code') }}</button>
    </form>
</div>
@endsection
