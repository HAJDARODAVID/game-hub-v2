<?php

namespace App\Actions\Rooms;

use App\Broadcasting\RoomEvents;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\User;

class LeaveRoom extends RoomAction
{
    /**
     * A (non-host) player leaves a room before the game starts.
     * They never played in it, so it also disappears from their list.
     */
    public function handle(Room $room, User $user): void
    {
        if ($room->isHost($user)) {
            $this->fail('room', __('As the host, close the room instead.'));
        }
        if ($room->status !== RoomStatus::Waiting) {
            $this->fail('room', __("You can't leave once the game has started."));
        }

        $room->players()->where('user_id', $user->id)->delete();

        RoomEvents::changed($room, [$user->id]);
    }
}
