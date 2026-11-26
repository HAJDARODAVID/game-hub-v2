<?php

namespace App\Actions\Rooms;

use App\Broadcasting\RoomEvents;
use App\Enums\RoomStatus;
use App\Models\Game;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateRoom extends RoomAction
{
    /**
     * Create a room for a game; the host takes seat 1.
     */
    public function handle(User $host, Game $game, ?string $name = null): Room
    {
        if (! $game->is_active) {
            $this->fail('room', __('This game is not available.'));
        }

        $name = trim((string) $name) ?: __(":name's room", ['name' => $host->username]);

        return DB::transaction(function () use ($host, $game, $name) {
            $room = Room::create([
                'game_id' => $game->id,
                'host_id' => $host->id,
                'name' => mb_substr($name, 0, 60),
                'status' => RoomStatus::Waiting,
            ]);

            $room->players()->create([
                'user_id' => $host->id,
                'seat' => 1,
                'joined_at' => now(),
            ]);

            RoomEvents::changed($room);

            return $room;
        });
    }
}
