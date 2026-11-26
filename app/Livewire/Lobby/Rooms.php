<?php

namespace App\Livewire\Lobby;

use App\Actions\Rooms\CreateRoom;
use App\Actions\Rooms\RespondToInvite;
use App\Enums\RoomStatus;
use App\Games\PlayerStats;
use App\Models\Game;
use App\Models\Room;
use App\Models\RoomInvite;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Rooms extends Component
{
    #[Locked]
    public Game $game;

    /** The player's record in this game. */
    #[Computed]
    public function stats(): PlayerStats
    {
        return PlayerStats::for(Auth::user(), $this->game);
    }

    /**
     * Rooms of this game the player is seated in that are still open
     * (waiting, playing, or finished and waiting for a rematch). Closed rooms are history.
     */
    #[Computed]
    public function myRooms()
    {
        return Room::where('game_id', $this->game->id)
            ->whereIn('status', [RoomStatus::Waiting, RoomStatus::Playing, RoomStatus::Finished])
            ->whereHas('players', fn ($q) => $q->where('user_id', Auth::id()))
            ->with('host')
            ->withCount('players')
            ->latest('updated_at')
            ->latest('id')
            ->get();
    }

    /** Pending invites to rooms of this game. */
    #[Computed]
    public function invites()
    {
        return RoomInvite::pendingFor(Auth::user())
            ->whereHas('room', fn ($q) => $q->where('game_id', $this->game->id))
            ->with(['room' => fn ($q) => $q->withCount('players'), 'inviter'])
            ->latest()
            ->get();
    }

    public function create()
    {
        $this->resetErrorBag();

        $room = app(CreateRoom::class)->handle(Auth::user(), $this->game);

        return $this->redirectRoute('rooms.show', $room);
    }

    public function accept(int $inviteId)
    {
        $this->resetErrorBag();

        $invite = RoomInvite::findOrFail($inviteId);
        $room = app(RespondToInvite::class)->accept($invite, Auth::user());

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
        return view('livewire.lobby.rooms');
    }
}
