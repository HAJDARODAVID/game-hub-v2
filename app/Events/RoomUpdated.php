<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something changed in a room (players, invites, status, a move, a rematch vote…).
 * Listeners just re-render from the database, so the payload stays minimal.
 *
 * Sent immediately (no queue worker needed). Dispatch it through App\Broadcasting\RoomEvents.
 */
class RoomUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $roomId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("room.{$this->roomId}")];
    }

    public function broadcastWith(): array
    {
        return ['roomId' => $this->roomId];
    }
}
