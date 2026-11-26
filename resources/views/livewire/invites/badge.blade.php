{{--
    Updates arrive in real time (Reverb); polling is only a slow safety net.
    With hideDuringMatch the wrapper can be empty, so it can't use ".visible" (it would never count as visible).
--}}
<div class="d-flex" @if ($hideDuringMatch) wire:poll.60s @else wire:poll.60s.visible @endif>
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
