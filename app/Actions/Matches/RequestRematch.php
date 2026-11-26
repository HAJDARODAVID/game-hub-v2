<?php

namespace App\Actions\Matches;

use App\Actions\Rooms\RoomAction;
use App\Broadcasting\RoomEvents;
use App\Enums\RoomStatus;
use App\Models\GameMatch;
use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RequestRematch extends RoomAction
{
    public function __construct(private StartMatch $startMatch) {}

    /**
     * A player says "play again" after a match. The first vote asks the others;
     * once every seated player has voted, the next match starts.
     *
     * @return GameMatch|null the new match, or null while waiting for the other players
     */
    public function handle(Room $room, User $player): ?GameMatch
    {
        return DB::transaction(function () use ($room, $player) {
            $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();

            if (! $room->hasPlayer($player)) {
                throw new AuthorizationException();
            }
            if ($room->status !== RoomStatus::Finished) {
                $this->fail('room', $room->status === RoomStatus::Closed
                    ? __('This room is closed.')
                    : __('There is no finished game to replay.'));
            }

            // The room row lock above already serialises votes for this room
            $match = $room->matches()->latest('id')->firstOrFail();

            $votes = $match->rematchVotes();
            if (! in_array($player->id, $votes, true)) {
                $votes[] = $player->id;
                $match->update(['rematch_votes' => $votes]);
            }

            $playerIds = $room->players()->pluck('user_id')->map(fn ($id) => (int) $id)->all();

            if (array_diff($playerIds, $votes) !== []) {
                RoomEvents::roomOnly($room);   // the others see "… wants a rematch!"

                return null;   // still waiting for someone
            }

            return $this->startMatch->begin($room);
        });
    }
}
