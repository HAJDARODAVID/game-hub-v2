<?php

use App\Models\RoomInvite;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /** Classes for the link, so the same badge fits the navbar and the phone footer. */
    public string $linkClass = 'btn btn-dark btn-sm';

    #[Computed]
    #[On('invites-changed')]
    public function count(): int
    {
        return RoomInvite::pendingFor(Auth::user())->count();
    }
};
?>

{{-- ".visible": only polls while on screen, so the hidden navbar/footer copy stays idle --}}
<a href="{{ route('invites.index') }}" class="{{ $linkClass }} position-relative invites-badge" wire:poll.30s.visible
    aria-label="{{ trans_choice('{0} My invites|{1} My invites: 1 pending|[2,*] My invites: :count pending', $this->count) }}">
    <i class="bi bi-envelope"></i>
    @if ($this->count > 0)
        <span class="badge rounded-pill invites-badge__count">{{ $this->count > 9 ? '9+' : $this->count }}</span>
    @endif
</a>
