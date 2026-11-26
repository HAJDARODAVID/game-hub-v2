<?php

namespace App\Livewire\Invites;

use App\Actions\Rooms\RespondToInvite;
use App\Models\RoomInvite;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class InviteList extends Component
{
    /** Real-time: refresh when an invite arrives or goes away (Reverb). */
    protected function getListeners(): array
    {
        return ['echo-private:user.' . Auth::id() . ',PlayerUpdated' => '$refresh'];
    }

    /** Pending invites to me, across all games. */
    #[Computed]
    public function invites()
    {
        return RoomInvite::pendingFor(Auth::user())
            ->with(['room.game', 'inviter'])
            ->latest()
            ->get();
    }

    public function accept(int $inviteId)
    {
        $this->resetErrorBag();

        $room = app(RespondToInvite::class)->accept(RoomInvite::findOrFail($inviteId), Auth::user());

        return $this->redirectRoute('rooms.show', $room);
    }

    public function decline(int $inviteId): void
    {
        $this->resetErrorBag();

        app(RespondToInvite::class)->decline(RoomInvite::findOrFail($inviteId), Auth::user());

        unset($this->invites);
        $this->dispatch('invites-changed');
    }

    public function render(): View
    {
        return view('livewire.invites.invite-list');
    }
}
