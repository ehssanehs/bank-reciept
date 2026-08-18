@extends('layouts.admin')

@section('title', $bank->exists ? __('messages.edit').' '.$bank->name : __('messages.create_bank'))

@section('content')
<h1 class="mt-0">{{ $bank->exists ? __('messages.edit') : __('messages.create_bank') }}</h1>

<div class="card" style="max-width:720px;">
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $bank->exists ? route('admin.banks.update', $bank) : route('admin.banks.store') }}">
        @csrf
        @if($bank->exists) @method('PUT') @endif

        <div class="grid grid-2">
            <div class="field"><label>Code</label><input type="text" name="code" value="{{ old('code', $bank->code) }}" required></div>
            <div class="field"><label>{{ __('messages.name') }}</label><input type="text" name="name" value="{{ old('name', $bank->name) }}" required></div>
        </div>
        <div class="field"><label>{{ __('messages.currency') }}</label>
            <input type="text" name="currency" value="{{ old('currency', $bank->currency ?? 'IRR') }}">
        </div>
        <div class="field">
            <label>{{ __('messages.sender_patterns') }} (comma separated)</label>
            <input type="text" name="sender_patterns" value="{{ implode(', ', old('sender_patterns', $bank->sender_patterns ?? [])) }}" placeholder="MELI,Melli,melli" required>
        </div>
        <div class="field">
            <label>{{ __('messages.parser') }}</label>
            <select name="parser_class">
                @foreach(\App\Services\Parsers\BankParserRegistry::available() as $class)
                    <option value="{{ $class }}" {{ old('parser_class', $bank->parser_class) === $class ? 'selected' : '' }}>{{ class_basename($class) }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid grid-2">
            <div class="field"><label>Amount pattern</label><input type="text" name="amount_pattern" value="{{ old('amount_pattern', $bank->amount_pattern) }}" class="mono"></div>
            <div class="field"><label>Tracking pattern</label><input type="text" name="tracking_pattern" value="{{ old('tracking_pattern', $bank->tracking_pattern) }}" class="mono"></div>
            <div class="field"><label>Date pattern</label><input type="text" name="date_pattern" value="{{ old('date_pattern', $bank->date_pattern) }}" class="mono"></div>
            <div class="field"><label>Time pattern</label><input type="text" name="time_pattern" value="{{ old('time_pattern', $bank->time_pattern) }}" class="mono"></div>
        </div>
        <div class="field">
            <label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $bank->is_active ?? true) ? 'checked' : '' }}> {{ __('messages.is_active') }}</label>
        </div>
        <button type="submit" class="btn btn-primary">{{ __('messages.save') }}</button>
    </form>
</div>
@endsection
