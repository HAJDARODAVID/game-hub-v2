<?php

namespace App\Livewire\Matches;

use App\Models\Room;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ActiveButton extends Component
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

    public function render(): View
    {
        return view('livewire.matches.active-button');
    }
}
