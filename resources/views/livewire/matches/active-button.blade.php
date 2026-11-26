{{-- Appears in real time (Reverb). Slow polling is a safety net (not ".visible": the wrapper is empty when there is no match) --}}
<div class="d-flex" wire:poll.60s>
    @if ($this->room && $this->room->id !== $currentRoomId)
        <a href="{{ route('rooms.show', $this->room) }}" class="{{ $linkClass }} active-match-btn"
            aria-label="{{ __('Back to your game in :room', ['room' => $this->room->name]) }}"
            title="{{ __('Back to your game') }}">
            <i class="bi bi-controller"></i>
            <span class="active-match-btn__dot" aria-hidden="true"></span>
        </a>
    @endif
</div>
