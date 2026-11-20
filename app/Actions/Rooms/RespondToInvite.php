<?php

namespace App\Actions\Rooms;

use App\Enums\InviteStatus;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomInvite;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RespondToInvite extends RoomAction
{
    /**
     * Accept an invite: the player takes the next free seat.
     */
    public function accept(RoomInvite $invite, User $user): Room
    {
        $this->ensureInvitee($invite, $user);

        return DB::transaction(function () use ($invite, $user) {
            $room = Room::whereKey($invite->room_id)->lockForUpdate()->firstOrFail();
            $invite = RoomInvite::whereKey($invite->id)->lockForUpdate()->firstOrFail();

            if ($invite->status !== InviteStatus::Pending) {
                $this->fail('invite', __('This invite is no longer valid.'));
            }
            if ($room->status !== RoomStatus::Waiting || $room->players()->count() >= $room->game->max_players) {
                $this->fail('invite', __('This room is no longer accepting players.'));
            }

            $room->players()->create([
                'user_id' => $user->id,
                'seat' => (int) $room->players()->max('seat') + 1,
                'joined_at' => now(),
            ]);

            $invite->update(['status' => InviteStatus::Accepted, 'responded_at' => now()]);

            return $room;
        });
    }

    /**
     * Decline an invite: the room disappears from the player's list.
     */
    public function decline(RoomInvite $invite, User $user): void
    {
        $this->ensureInvitee($invite, $user);

        RoomInvite::whereKey($invite->id)
            ->pending()
            ->update(['status' => InviteStatus::Declined, 'responded_at' => now()]);
    }

    private function ensureInvitee(RoomInvite $invite, User $user): void
    {
        if ($invite->invitee_id !== $user->id) {
            throw new AuthorizationException();
        }
    }
}
