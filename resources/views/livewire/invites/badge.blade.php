{{--
    With hideDuringMatch: plain 15s polling (same rhythm as the game button), because while hidden
    the wrapper is empty and would never count as "visible" — the badge must still come back.
    Otherwise ".visible" polls only while on screen.
--}}
<div class="d-flex" @if ($hideDuringMatch) wire:poll.15s @else wire:poll.30s.visible @endif>
    @unless ($this->hidden)
        <a href="{{ route('invites.index') }}" class="{{ $linkClass }} position-relative invites-badge"
            aria-label="{{ trans_choice('{0} My invites|{1} My invites: 1 pending|[2,*] My invites: :count pending', $this->count) }}">
            <i class="bi bi-envelope"></i>
            @if ($this->count > 0)
                <span class="badge rounded-pill invites-badge__count">{{ $this->count > 9 ? '9+' : $this->count }}</span>
            @endif
        </a>
    @endunless
</div>
