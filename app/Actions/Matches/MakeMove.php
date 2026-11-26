<?php

namespace App\Actions\Matches;

use App\Actions\Rooms\RoomAction;
use App\Enums\MatchStatus;
use App\Enums\RoomStatus;
use App\Games\GameRegistry;
use App\Games\InvalidMove;
use App\Models\GameMatch;
use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class MakeMove extends RoomAction
{
    public function __construct(private GameRegistry $games) {}

    /**
     * Validate a move with the game's engine and save the new state.
     * Rows are locked so two simultaneous clicks (or a click and a forfeit) can't both be applied.
     * Lock order is always room → match (same as CloseRoom / StartMatch) to avoid deadlocks.
     */
    public function handle(GameMatch $match, User $player, array $move): GameMatch
    {
        return DB::transaction(function () use ($match, $player, $move) {
            $room = Room::whereKey($match->room_id)->lockForUpdate()->firstOrFail();
            $match = GameMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! $room->hasPlayer($player)) {
                throw new AuthorizationException();
            }
            if ($match->status !== MatchStatus::Playing) {
                $this->fail('move', __('This game is over.'));
            }

            $engine = $this->games->for($room->game);

            try {
                $match->state = $engine->applyMove($match->state, $player->id, $move);
            } catch (InvalidMove $e) {
                $this->fail('move', $e->getMessage());
            }

            if ($result = $engine->result($match->state)) {
                $match->status = MatchStatus::Finished;
                $match->winner_id = $result->winnerId;
                $match->finished_at = now();

                $room->update(['status' => RoomStatus::Finished, 'finished_at' => now()]);
            }

            $match->save();

            return $match;
        });
    }
}
