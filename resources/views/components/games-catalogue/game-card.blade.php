@props(['game'])

<a href="{{ route('games.lobby', $game) }}" class="card game-card text-decoration-none">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ mb_strtoupper($game->title) }}</span>
        <span class="game-card__players"><i class="bi bi-people me-1"></i>{{ $game->max_players }}</span>
    </div>

    <div class="card-body game-card__cover" @if ($game->cover_url) style="background-image: url('{{ $game->cover_url }}')" @endif>
        @unless ($game->cover_url)
            <i class="bi bi-controller"></i>
        @endunless
    </div>
</a>
