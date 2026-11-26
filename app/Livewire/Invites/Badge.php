<?php

namespace App\Livewire\Invites;

use App\Models\Room;
use App\Models\RoomInvite;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Badge extends Component
{
    /** Real-time: update the count, and hide/show around matches (Reverb). */
    protected function getListeners(): array
    {
        return ['echo-private:user.' . Auth::id() . ',PlayerUpdated' => '$refresh'];
    }

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

    public function render(): View
    {
        return view('livewire.invites.badge');
    }
}
