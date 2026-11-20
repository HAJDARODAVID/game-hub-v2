<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // Placeholder catalogue until games live in the database.
        $games = [
            ['title' => 'Scotland Yard', 'name' => 'scotland-yard', 'cover' => 'scotland-yard'],
            ['title' => 'Toe Tac Tic', 'name' => 'toe-tac-tic', 'cover' => null],
        ];

        return view('home', compact('games'));
    }
}
