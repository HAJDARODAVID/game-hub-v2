@props(['game'])

@php
    $cover = !empty($game['cover']) && file_exists(public_path('img/games/catalogue/' . $game['cover'] . '.png'))
        ? asset('img/games/catalogue/' . $game['cover'] . '.png')
        : null;
@endphp

{{-- Links to "#" until game pages exist --}}
<a href="{{ $game['url'] ?? '#' }}" class="card game-card text-decoration-none">
    <div class="card-header">
        {{ mb_strtoupper($game['title']) }}
    </div>

    <div class="card-body game-card__cover" @if ($cover) style="background-image: url('{{ $cover }}')" @endif>
        @unless ($cover)
            <i class="bi bi-controller"></i>
        @endunless
    </div>
</a>
