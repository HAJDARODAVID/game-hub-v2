<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    /**
     * Same rule as Room::visibleTo(): host, (past) player, or pending invite.
     */
    public function view(User $user, Room $room): bool
    {
        return $room->isHost($user)
            || $room->hasPlayer($user)
            || $room->pendingInviteFor($user) !== null;
    }

    /**
     * Invite / cancel invites / close the room.
     */
    public function manage(User $user, Room $room): bool
    {
        return $room->isHost($user);
    }
}
