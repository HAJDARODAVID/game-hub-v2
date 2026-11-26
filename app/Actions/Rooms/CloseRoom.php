<?php

namespace App\Actions\Rooms;

use App\Enums\InviteStatus;
use App\Enums\MatchStatus;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CloseRoom extends RoomAction
{
    /**
     * Close a room:
     *  - waiting room: only the host can close it
     *  - during a match: any player can leave; they forfeit, their opponent wins
     *  - after a match: any player can close it (instead of a rematch)
     * Pending invites are cancelled; the room stays in the history of everyone who was seated in it.
     */
    public function handle(Room $room, User $user): void
    {
        DB::transaction(function () use ($room, $user) {
            $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();

            match ($room->status) {
                RoomStatus::Closed => $this->fail('room', __('This room is already closed.')),
                RoomStatus::Waiting => Gate::forUser($user)->authorize('manage', $room),
                RoomStatus::Playing, RoomStatus::Finished => $room->hasPlayer($user) ?: throw new AuthorizationException(),
            };

            if ($room->status === RoomStatus::Playing) {
                $this->forfeit($room, $user);
            }

            $room->update(['status' => RoomStatus::Closed, 'closed_at' => now()]);

            $room->invites()->pending()->update([
                'status' => InviteStatus::Cancelled,
                'responded_at' => now(),
            ]);
        });
    }

    /**
     * End the running match: the leaving player loses. With exactly one opponent left,
     * that opponent wins; with several, nobody is declared winner.
     */
    private function forfeit(Room $room, User $leaver): void
    {
        $match = $room->matches()->where('status', MatchStatus::Playing)->latest('id')->lockForUpdate()->first();

        if (! $match) {
            return;
        }

        $opponents = $room->players()->where('user_id', '!=', $leaver->id)->pluck('user_id');

        $match->update([
            'status' => MatchStatus::Finished,
            'winner_id' => $opponents->count() === 1 ? $opponents->first() : null,
            'forfeited_by' => $leaver->id,
            'finished_at' => now(),
        ]);
    }
}
