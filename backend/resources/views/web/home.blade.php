@extends('layouts.web')

@section('title', __('messages.home'))

@section('content')
<div class="card text-center">
    <h1 class="mt-0">{{ __('messages.welcome_title') }}</h1>
    <p class="muted">{{ __('messages.welcome_subtitle') }}</p>
    <a href="{{ route('web.pay') }}" class="btn btn-primary">{{ __('messages.pay') }}</a>
</div>
@endsection
