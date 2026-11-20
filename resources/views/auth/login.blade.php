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
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-3">
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input id="username" type="text" class="form-control @error('username') is-invalid @enderror" name="username" value="{{ old('username') }}" placeholder="{{ __('Gamertag') }}" aria-label="{{ __('Gamertag') }}" required autocomplete="username" autofocus>
                                @error('username')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="input-group has-validation">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="{{ __('Password') }}" aria-label="{{ __('Password') }}" required autocomplete="current-password">
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-dark btn-lg w-100">
                                LOGIN
                            </button>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                <label class="form-check-label text-white" for="remember">
                                    {{ __('Remember me') }}
                                </label>
                            </div>

                            @if (Route::has('register'))
                                <a class="btn text-white" href="{{ route('register') }}">{{ __('Register') }}</a>
                            @endif
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
