<?php

namespace App\Models;

use App\Enums\InviteStatus;
use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomInvite extends Model
{
    protected $fillable = ['room_id', 'inviter_id', 'invitee_id', 'status', 'responded_at'];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => InviteStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->where('status', InviteStatus::Pending);
    }

    /** Invites a user can still answer: pending, to a room that is still waiting for players. */
    #[Scope]
    protected function pendingFor(Builder $query, User $user): void
    {
        $query->pending()
            ->where('invitee_id', $user->id)
            ->whereHas('room', fn (Builder $r) => $r->where('status', RoomStatus::Waiting));
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }
}
