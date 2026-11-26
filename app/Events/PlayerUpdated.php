<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something changed for this player: an invite arrived or went away, one of their rooms
 * changed, a match started or ended. Drives the lobby, invites page, envelope badge and
 * the footer "back to your game" button.
 *
 * Sent immediately (no queue worker needed). Dispatch it through App\Broadcasting\RoomEvents.
 */
class PlayerUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $userId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastWith(): array
    {
        return [];
    }
}
