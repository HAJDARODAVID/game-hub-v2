<?php

use App\Models\Room;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    /** Classes for the link, so it can match the footer (or navbar) buttons. */
    public string $linkClass = 'btn btn-dark btn-sm';

    /** Room page the user is currently on, so we don't link to the page they're already viewing. */
    #[Locked]
    public ?int $currentRoomId = null;

    public function mount(): void
    {
        $room = request()->route('room');
        $this->currentRoomId = $room instanceof Room ? $room->id : null;
    }

    /** The user's running match (most recently started), if any. */
    #[Computed]
    public function room(): ?Room
    {
        return Room::playingWith(Auth::user())
            ->latest('started_at')
            ->latest('id')
            ->first();
    }
};
?>

{{-- Polls so the button appears when a match starts, until real-time updates (Reverb) are added.
     (Not ".visible": the wrapper is empty when there's no match, so it would never count as visible.) --}}
<div class="d-flex" wire:poll.15s>
    @if ($this->room && $this->room->id !== $currentRoomId)
        <a href="{{ route('rooms.show', $this->room) }}" class="{{ $linkClass }} active-match-btn"
            aria-label="{{ __('Back to your game in :room', ['room' => $this->room->name]) }}"
            title="{{ __('Back to your game') }}">
            <i class="bi bi-controller"></i>
            <span class="active-match-btn__dot" aria-hidden="true"></span>
        </a>
    @endif
</div>
