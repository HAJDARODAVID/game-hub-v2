<?php

use App\Models\Room;
use App\Models\RoomInvite;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /** Classes for the link, so the badge can match the surrounding buttons. */
    public string $linkClass = 'btn btn-dark btn-sm';

    /** Footer: make room for the "back to your game" button while a match is running. */
    public bool $hideDuringMatch = false;

    #[Computed]
    #[On('invites-changed')]
    public function count(): int
    {
        return RoomInvite::pendingFor(Auth::user())->count();
    }

    #[Computed]
    public function hidden(): bool
    {
        return $this->hideDuringMatch && Room::playingWith(Auth::user())->exists();
    }
};
?>

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
