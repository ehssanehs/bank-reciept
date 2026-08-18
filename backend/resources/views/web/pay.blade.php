@extends('layouts.web')

@section('title', __('messages.pay'))

@section('content')
<div class="card" style="max-width:520px;margin:0 auto;">
    <h2 class="mt-0">{{ __('messages.create_payment') }}</h2>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('web.pay.create') }}">
        @csrf
        <div class="field">
            <label for="amount">{{ __('messages.amount') }}</label>
            <input type="number" step="any" name="amount" id="amount" required value="{{ old('amount') }}">
        </div>
        <div class="field">
            <label for="currency">{{ __('messages.currency') }}</label>
            <select name="currency" id="currency">
                <option value="IRR" {{ old('currency') === 'IRR' ? 'selected' : '' }}>IRR</option>
                <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>USD</option>
                <option value="EUR" {{ old('currency') === 'EUR' ? 'selected' : '' }}>EUR</option>
            </select>
        </div>
        <div class="field">
            <label for="order_id">{{ __('messages.order_id') }}</label>
            <input type="text" name="order_id" id="order_id" value="{{ old('order_id') }}">
        </div>
        <button type="submit" class="btn btn-primary">{{ __('messages.submit') }}</button>
    </form>
</div>
@endsection
