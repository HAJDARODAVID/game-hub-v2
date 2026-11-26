<?php

namespace App\Actions\Rooms;

use App\Enums\InviteStatus;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CloseRoom extends RoomAction
{
    /**
     * Close a room that isn't mid-game:
     *  - waiting room: only the host can close it
     *  - after a match: any player can (instead of agreeing to a rematch)
     * Pending invites are cancelled; the room stays in the history of everyone who was seated in it.
     */
    public function handle(Room $room, User $user): void
    {
        if ($room->status === RoomStatus::Finished) {
            if (! $room->hasPlayer($user)) {
                throw new AuthorizationException();
            }
        } else {
            Gate::forUser($user)->authorize('manage', $room);
        }

        if (! in_array($room->status, [RoomStatus::Waiting, RoomStatus::Finished], true)) {
            $this->fail('room', __("A room can't be closed while a game is running."));
        }

        DB::transaction(function () use ($room) {
            $room->update(['status' => RoomStatus::Closed, 'closed_at' => now()]);

            $room->invites()->pending()->update([
                'status' => InviteStatus::Cancelled,
                'responded_at' => now(),
            ]);
        });
    }
}
