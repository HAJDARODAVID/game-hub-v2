@props(['games'])

<div class="card catalogue">
    <div class="card-header">
        {{ mb_strtoupper(__('Games catalogue')) }}
    </div>

    <div class="card-body">
        @if ($games->isNotEmpty())
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
                @foreach ($games as $game)
                    <div class="col">
                        <x-games-catalogue.game-card :game="$game" />
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-white-50 mb-0">{{ __('No games available yet.') }}</p>
        @endif
    </div>
</div>
