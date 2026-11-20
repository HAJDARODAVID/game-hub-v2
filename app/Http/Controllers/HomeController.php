<?php

namespace App\Http\Controllers;

use App\Models\Game;

class HomeController extends Controller
{
    /**
     * The games catalogue.
     */
    public function index()
    {
        $games = Game::active()->get();

        return view('home', compact('games'));
    }
}
