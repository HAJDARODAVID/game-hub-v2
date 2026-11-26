<?php

namespace App\Broadcasting;

use App\Events\PlayerUpdated;
use App\Events\RoomUpdated;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells browsers that something changed, so Livewire components re-render instantly.
 *
 * - Runs after the current database transaction commits, so listeners never read stale data.
 * - Never breaks the game: if Reverb is down the change is still saved, a warning is logged,
 *   and pages catch up through their slow fallback polling.
 */
class RoomEvents
{
    /**
     * A room changed. Notifies the room channel, plus every seated player, every pending
     * invitee, and any extra users (e.g. someone whose invite was just cancelled).
     *
     * @param  iterable<int>  $alsoNotify  user ids
     */
    public static function changed(Room|int $room, iterable $alsoNotify = []): void
    {
        $roomId = $room instanceof Room ? $room->id : $room;
        $extra = collect($alsoNotify)->map(fn ($id) => (int) $id)->all();

        DB::afterCommit(function () use ($roomId, $extra) {
            static::send(new RoomUpdated($roomId));

            $room = Room::find($roomId);
            $userIds = collect($extra);

            if ($room) {
                $userIds = $userIds
                    ->merge($room->players()->pluck('user_id'))
                    ->merge($room->invites()->pending()->pluck('invitee_id'));
            }

            $userIds->map(fn ($id) => (int) $id)->unique()->each(
                fn (int $id) => static::send(new PlayerUpdated($id))
            );
        });
    }

    /**
     * Only the room view changed (e.g. a move that didn't end the match).
     */
    public static function roomOnly(Room|int $room): void
    {
        $roomId = $room instanceof Room ? $room->id : $room;

        DB::afterCommit(fn () => static::send(new RoomUpdated($roomId)));
    }

    private static function send(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            Log::warning('Real-time update not sent (is Reverb running?): ' . $e->getMessage(), [
                'event' => $event::class,
            ]);
        }
    }
}
