<?php

namespace App\Actions\Rooms;

use App\Enums\InviteStatus;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CloseRoom extends RoomAction
{
    /**
     * The host closes a waiting room. Pending invites are cancelled;
     * the room stays in the history of everyone who was seated in it.
     */
    public function handle(Room $room, User $host): void
    {
        Gate::forUser($host)->authorize('manage', $room);

        if ($room->status !== RoomStatus::Waiting) {
            $this->fail('room', __('Only a waiting room can be closed.'));
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
