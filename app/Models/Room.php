<?php

namespace App\Models;

use App\Enums\InviteStatus;
use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    protected $fillable = [
        'game_id',
        'host_id',
        'name',
        'status',
        'settings',
        'started_at',
        'finished_at',
        'closed_at',
    ];

    protected $attributes = [
        'status' => 'waiting',
    ];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'settings' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(RoomPlayer::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(RoomInvite::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class);
    }

    /** The current or most recent match. */
    public function latestMatch(): HasOne
    {
        return $this->hasOne(GameMatch::class)->latestOfMany();
    }

    /**
     * Rooms a user may see: rooms they host, rooms they are (or were) a player in,
     * and rooms they have a pending invite to.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $q) use ($user) {
            $q->where('host_id', $user->id)
                ->orWhereHas('players', fn (Builder $p) => $p->where('user_id', $user->id))
                ->orWhereHas('invites', fn (Builder $i) => $i
                    ->where('invitee_id', $user->id)
                    ->where('status', InviteStatus::Pending));
        });
    }

    /** Rooms where this user is seated and a match is being played right now. */
    #[Scope]
    protected function playingWith(Builder $query, User $user): void
    {
        $query->where('status', RoomStatus::Playing)
            ->whereHas('players', fn (Builder $p) => $p->where('user_id', $user->id));
    }

    /** Live rooms first, then history; newest first within each group. */
    #[Scope]
    protected function openFirst(Builder $query): void
    {
        $open = array_map(fn (RoomStatus $s) => $s->value, RoomStatus::open());

        $query->orderByRaw(
            'case when status in (' . implode(',', array_fill(0, count($open), '?')) . ') then 0 else 1 end',
            $open
        )->latest()->orderByDesc('id');
    }

    public function isHost(User $user): bool
    {
        return $this->host_id === $user->id;
    }

    public function hasPlayer(User $user): bool
    {
        return $this->players()->where('user_id', $user->id)->exists();
    }

    public function pendingInviteFor(User $user): ?RoomInvite
    {
        return $this->invites()->pending()->where('invitee_id', $user->id)->first();
    }
}
