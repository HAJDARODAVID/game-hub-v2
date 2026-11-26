<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single round played in a room. ("Match" is a reserved word in PHP.)
 */
class GameMatch extends Model
{
    protected $fillable = ['room_id', 'status', 'state', 'winner_id', 'rematch_votes', 'started_at', 'finished_at'];

    protected $attributes = [
        'status' => 'playing',
    ];

    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'state' => 'array',
            'rematch_votes' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function isPlaying(): bool
    {
        return $this->status === MatchStatus::Playing;
    }

    public function isDraw(): bool
    {
        return $this->status === MatchStatus::Finished && $this->winner_id === null;
    }

    /** @return list<int> user ids who asked for a rematch */
    public function rematchVotes(): array
    {
        return array_map('intval', $this->rematch_votes ?? []);
    }

    public function hasRematchVote(User $user): bool
    {
        return in_array($user->id, $this->rematchVotes(), true);
    }
}
