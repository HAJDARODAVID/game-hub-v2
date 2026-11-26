@extends('layouts.main-app')

@section('content')
<div class="container">
    <div class="d-flex align-items-center gap-3 mb-3">
        <a href="{{ route('home') }}" class="btn btn-dark btn-sm" aria-label="{{ __('Back to catalogue') }}">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h4 text-white mb-0">{{ __('My invites') }}</h1>
    </div>

    <livewire:invites.invite-list />
</div>
@endsection
