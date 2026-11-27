{{-- Moves arrive in real time (Reverb); slow polling while the match runs is only a safety net --}}
<div class="card catalogue mb-3" @if ($match->isPlaying()) wire:poll.30s="refreshMatch" @endif>
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ mb_strtoupper(__('Game')) }}</span>
        <span class="ttt-status @if ($myTurn) ttt-status--mine @endif">
            @if ($match->isPlaying())
                {{ $myTurn ? __('Your turn') : __('Waiting for :name', ['name' => $turnName]) }}
            @elseif ($match->wasForfeited())
                @if ($match->forfeited_by === auth()->id())
                    {{ __('You left the game') }}
                @elseif ($match->winner_id === auth()->id())
                    <i class="bi bi-trophy-fill me-1"></i>{{ __(':name left the game — you win!', ['name' => $this->names[$match->forfeited_by] ?? '']) }}
                @else
                    {{ __(':name left the game', ['name' => $this->names[$match->forfeited_by] ?? '']) }}
                @endif
            @elseif ($match->isDraw())
                {{ __("It's a draw") }}
            @elseif ($match->winner_id === auth()->id())
                <i class="bi bi-trophy-fill me-1"></i>{{ __(':name made three in a row — you win!', ['name' => $loserName]) }}
            @elseif ($state['loser'] === $mySymbol)
                {{ __('You made three in a row — you lose') }}
            @else
                {{ __(':name made three in a row and lost', ['name' => $loserName]) }}
            @endif
        </span>
    </div>

    <div class="card-body">
        {{-- Players --}}
        <div class="d-flex justify-content-center align-items-center gap-3 mb-2">
            @foreach (['X', 'O'] as $symbol)
                @php($playerId = $state['players'][$symbol])
                <div class="ttt-player @if ($match->isPlaying() && $state['turn'] === $symbol) ttt-player--active @endif">
                    <span class="ttt-mark ttt-mark--{{ strtolower($symbol) }}">{{ $symbol }}</span>
                    {{ $this->names[$playerId] ?? '?' }}
                    @if ($symbol === $mySymbol)
                        <small class="text-white-50">({{ __('you') }})</small>
                    @endif
                </div>
                @if ($symbol === 'X')
                    <span class="text-white-50">vs</span>
                @endif
            @endforeach
        </div>

        <p class="text-center text-white-50 small mb-3">{{ __("Don't make three in a row — whoever does, loses.") }}</p>

        {{-- Board --}}
        <div class="ttt-board ttt-board--4" role="grid" aria-label="{{ __('ToeTacTic board') }}">
            @foreach ($state['board'] as $i => $cell)
                <button type="button"
                    class="ttt-cell @if (in_array($i, $line, true)) ttt-cell--lose @endif"
                    wire:click="play({{ $i }})"
                    wire:loading.attr="disabled"
                    @disabled(! $myTurn || $cell !== null)
                    aria-label="{{ __('Cell :n', ['n' => $i + 1]) }}{{ $cell ? ': ' . $cell : '' }}">
                    @if ($cell)
                        <span class="ttt-mark ttt-mark--{{ strtolower($cell) }}">{{ $cell }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        @error('move')
            <div class="text-danger text-center small mt-2">{{ $message }}</div>
        @enderror
    </div>
</div>
