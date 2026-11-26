<?php

namespace App\Actions\Matches;

use App\Actions\Rooms\RoomAction;
use App\Broadcasting\RoomEvents;
use App\Enums\InviteStatus;
use App\Enums\MatchStatus;
use App\Enums\RoomStatus;
use App\Games\GameRegistry;
use App\Models\GameMatch;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StartMatch extends RoomAction
{
    public function __construct(private GameRegistry $games) {}

    /**
     * The host starts the first match of a waiting room once enough players are seated.
     * Rematches go through RequestRematch (every player has to agree).
     */
    public function handle(Room $room, User $host): GameMatch
    {
        Gate::forUser($host)->authorize('manage', $room);

        return DB::transaction(function () use ($room) {
            $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();

            if ($room->status !== RoomStatus::Waiting) {
                $this->fail('room', match ($room->status) {
                    RoomStatus::Playing => __('A game is already running.'),
                    RoomStatus::Closed => __('This room is closed.'),
                    default => __('Every player has to agree to play again.'),
                });
            }

            return $this->begin($room);
        });
    }

    /**
     * Create the next match in a room. Call inside a transaction with the room row locked.
     * The first move rotates: match 1 → seat 1, match 2 → seat 2, ...
     */
    public function begin(Room $room): GameMatch
    {
        if (! $this->games->has($room->game)) {
            $this->fail('room', __("This game can't be played yet."));
        }

        $playerIds = $room->players()->orderBy('seat')->pluck('user_id')->all();

        if (count($playerIds) < $room->game->min_players) {
            $this->fail('room', __('Waiting for players: :count needed.', ['count' => $room->game->min_players]));
        }

        $offset = $room->matches()->count() % count($playerIds);
        $turnOrder = [...array_slice($playerIds, $offset), ...array_slice($playerIds, 0, $offset)];

        $match = $room->matches()->create([
            'status' => MatchStatus::Playing,
            'state' => $this->games->for($room->game)->initialState($turnOrder, $room->settings ?? []),
            'started_at' => now(),
        ]);

        $room->update([
            'status' => RoomStatus::Playing,
            'started_at' => $room->started_at ?? now(),
            'finished_at' => null,
        ]);

        // Nobody can join a running game
        $cancelledInvitees = $room->invites()->pending()->pluck('invitee_id');
        $room->invites()->pending()->update([
            'status' => InviteStatus::Cancelled,
            'responded_at' => now(),
        ]);

        RoomEvents::changed($room, $cancelledInvitees);

        return $match;
    }
}
