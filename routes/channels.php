<?php

use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/*
| Private channels for real-time updates (Reverb). A browser may only listen
| to a channel when the callback returns true for the logged-in user.
*/

// Everything that happens in a room: same rule as opening the room page
// (host, seated or past player, or pending invite).
Broadcast::channel('room.{room}', function (User $user, Room $room) {
    return Gate::forUser($user)->allows('view', $room);
});

// Personal updates: invites, my rooms, my running match.
Broadcast::channel('user.{id}', function (User $user, int $id) {
    return $user->id === $id;
});
