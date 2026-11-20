<?php

namespace App\Http\Controllers;

use App\Models\Game;

class GameController extends Controller
{
    /**
     * A game's lobby: open rooms + create a room (rooms come in the next step).
     */
    public function lobby(Game $game)
    {
        abort_unless($game->is_active, 404);

        return view('games.lobby', compact('game'));
    }
}
