@extends('layouts.main-app')

@section('content')
<div class="container">
    <div class="d-flex align-items-center gap-3 mb-3">
        <a href="{{ route('home') }}" class="btn btn-dark btn-sm" aria-label="{{ __('Back to catalogue') }}">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 text-white mb-0">{{ $game->title }}</h1>
            <small class="text-white-50">{{ $game->description }} · {{ $game->players_label }}</small>
        </div>
    </div>

    <livewire:lobby.rooms :game="$game" />
</div>
@endsection
