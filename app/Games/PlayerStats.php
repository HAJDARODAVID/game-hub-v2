<?php

namespace App\Games;

use App\Enums\MatchStatus;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\User;

/**
 * A player's record in one game, counted from finished matches in rooms they were seated in.
 * A forfeit counts as a loss for the player who left and a win for the opponent.
 */
final readonly class PlayerStats
{
    public function __construct(
        public int $played,
        public int $wins,
        public int $losses,
        public int $draws,
    ) {}

    public static function for(User $user, Game $game): self
    {
        $row = GameMatch::query()
            ->where('status', MatchStatus::Finished)
            ->whereHas('room', fn ($r) => $r
                ->where('game_id', $game->id)
                ->whereHas('players', fn ($p) => $p->where('user_id', $user->id)))
            ->selectRaw('count(*) as played')
            ->selectRaw('coalesce(sum(case when winner_id = ? then 1 else 0 end), 0) as wins', [$user->id])
            ->selectRaw('coalesce(sum(case when winner_id is null and forfeited_by is null then 1 else 0 end), 0) as draws')
            ->toBase()
            ->first();

        $played = (int) $row->played;
        $wins = (int) $row->wins;
        $draws = (int) $row->draws;

        return new self($played, $wins, $played - $wins - $draws, $draws);
    }

    /** Wins as a whole percentage of matches played (0 when nothing played yet). */
    public function winRate(): int
    {
        return $this->played > 0 ? (int) round($this->wins / $this->played * 100) : 0;
    }
}
