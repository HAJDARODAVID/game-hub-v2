<?php

namespace App\Actions\Matches;

use App\Actions\Rooms\RoomAction;
use App\Enums\MatchStatus;
use App\Enums\RoomStatus;
use App\Games\GameRegistry;
use App\Games\InvalidMove;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class MakeMove extends RoomAction
{
    public function __construct(private GameRegistry $games) {}

    /**
     * Validate a move with the game's engine and save the new state.
     * The match row is locked so two simultaneous clicks can't both be applied.
     */
    public function handle(GameMatch $match, User $player, array $move): GameMatch
    {
        return DB::transaction(function () use ($match, $player, $move) {
            $match = GameMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();
            $room = $match->room;

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
