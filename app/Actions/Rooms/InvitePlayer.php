<?php

namespace App\Actions\Rooms;

use App\Broadcasting\RoomEvents;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InvitePlayer extends RoomAction
{
    /**
     * The host invites another player by gamertag.
     * Pending invites count towards the room's capacity, so an accepted invite always has a seat.
     */
    public function handle(Room $room, User $host, string $username): RoomInvite
    {
        Gate::forUser($host)->authorize('manage', $room);

        $username = trim($username);
        if ($username === '') {
            $this->fail('username', __('Enter a gamertag.'));
        }

        return DB::transaction(function () use ($room, $host, $username) {
            $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($room->status !== RoomStatus::Waiting) {
                $this->fail('username', __('This room is no longer accepting players.'));
            }

            $invitee = User::where('username', $username)->first();

            if (! $invitee) {
                $this->fail('username', __('No player with that gamertag.'));
            }
            if ($invitee->is($host)) {
                $this->fail('username', __("You can't invite yourself."));
            }
            if ($room->hasPlayer($invitee)) {
                $this->fail('username', __(':name is already in this room.', ['name' => $invitee->username]));
            }
            if ($room->pendingInviteFor($invitee)) {
                $this->fail('username', __(':name is already invited.', ['name' => $invitee->username]));
            }

            $seatsTaken = $room->players()->count() + $room->invites()->pending()->count();
            if ($seatsTaken >= $room->game->max_players) {
                $this->fail('username', __('The room is full (pending invites count too).'));
            }

            $invite = $room->invites()->create([
                'inviter_id' => $host->id,
                'invitee_id' => $invitee->id,
            ]);

            RoomEvents::changed($room);   // the invitee is a pending invitee now, so they're notified too

            return $invite;
        });
    }
}
