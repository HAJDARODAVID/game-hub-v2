<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Support\Facades\Gate;

class RoomController extends Controller
{
    /**
     * The waiting room. Only the host, (past) players and invited players may open it.
     */
    public function show(Room $room)
    {
        Gate::authorize('view', $room);

        $room->load('game', 'host');

        return view('rooms.show', compact('room'));
    }
}
