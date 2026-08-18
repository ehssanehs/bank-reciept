@extends('layouts.admin')

@section('title', $user->exists ? __('messages.edit_user') : __('messages.create_user'))

@section('content')
<h1 class="mt-0">{{ $user->exists ? __('messages.edit_user') : __('messages.create_user') }}</h1>

<div class="card" style="max-width:560px;">
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if($user->exists) @method('PUT') @endif

        <div class="field"><label>{{ __('messages.name') }}</label><input type="text" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div class="field"><label>{{ __('messages.email') }}</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
        <div class="field"><label>Password {{ $user->exists ? '(leave blank to keep)' : '' }}</label>
            <input type="password" name="password" {{ $user->exists ? '' : 'required' }}></div>
        <div class="field">
            <label>{{ __('messages.role') }}</label>
            <select name="role">
                @foreach($roles as $role)
                    <option value="{{ $role->slug }}"
                        {{ old('role', $user->roles->pluck('slug')->first()) === $role->slug ? 'selected' : '' }}>
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label>{{ __('messages.locale') }}</label>
            <select name="locale">
                <option value="en" {{ old('locale', $user->locale ?? 'en') === 'en' ? 'selected' : '' }}>English</option>
                <option value="fa" {{ old('locale', $user->locale) === 'fa' ? 'selected' : '' }}>فارسی</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">{{ __('messages.save') }}</button>
    </form>
</div>
@endsection
