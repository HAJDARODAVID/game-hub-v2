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
