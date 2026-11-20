@extends('layouts.login')

@section('content')
<div class="container">
    <div class="d-flex justify-content-center">
        <img src="{{ asset('img/logo.jpg') }}" alt="{{ config('app.name', 'Game Hub') }}" width="280" height="100">
    </div>

    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card auth-card">
                <div class="card-body">
                    <form method="POST" action="{{ route('register') }}">
                        @csrf

                        <div class="mb-3">
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input id="username" type="text" class="form-control @error('username') is-invalid @enderror" name="username" value="{{ old('username') }}" placeholder="{{ __('Gamertag') }}" aria-label="{{ __('Gamertag') }}" required minlength="3" maxlength="30" autocomplete="username" autofocus>
                                @error('username')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
                                <input id="first_name" type="text" class="form-control @error('first_name') is-invalid @enderror" name="first_name" value="{{ old('first_name') }}" placeholder="{{ __('First name') }}" aria-label="{{ __('First name') }}" required autocomplete="given-name">
                                <input id="last_name" type="text" class="form-control @error('last_name') is-invalid @enderror" name="last_name" value="{{ old('last_name') }}" placeholder="{{ __('Last name') }}" aria-label="{{ __('Last name') }}" required autocomplete="family-name">
                            </div>
                            @foreach (['first_name', 'last_name'] as $field)
                                @error($field)
                                    <span class="invalid-feedback d-block" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            @endforeach
                        </div>

                        <div class="mb-3">
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="bi bi-envelope-at"></i></span>
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="{{ __('Email') }}" aria-label="{{ __('Email') }}" required autocomplete="email">
                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="{{ __('Password') }}" aria-label="{{ __('Password') }}" required autocomplete="new-password">
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" placeholder="{{ __('Confirm password') }}" aria-label="{{ __('Confirm password') }}" required autocomplete="new-password">
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-dark btn-lg w-100">
                                REGISTER
                            </button>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a class="btn text-white" href="{{ route('login') }}">{{ __('Already have an account? Login') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-3">
        <p class="text-white">
            {{ mb_strtoupper(\Illuminate\Support\Arr::random(\App\Models\FunnySayings::PHRASE)) }}
        </p>
    </div>
</div>
@endsection
