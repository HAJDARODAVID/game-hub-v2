<?php

namespace App\Actions\Rooms;

use App\Enums\InviteStatus;
use App\Models\RoomInvite;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class CancelInvite extends RoomAction
{
    /**
     * The host withdraws a pending invite, freeing the seat.
     */
    public function handle(RoomInvite $invite, User $host): void
    {
        Gate::forUser($host)->authorize('manage', $invite->room);

        RoomInvite::whereKey($invite->id)
            ->pending()
            ->update(['status' => InviteStatus::Cancelled, 'responded_at' => now()]);
    }
}
