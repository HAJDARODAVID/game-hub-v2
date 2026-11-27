<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

/**
 * The game catalogue. Safe to run repeatedly: games are matched by slug.
 */
class GameSeeder extends Seeder
{
    public function run(): void
    {
        $games = [
            [
                'slug' => 'tic-tac-toe',
                'title' => 'Tic Tac Toe',
                'description' => 'Get three in a row before your opponent does.',
                'cover' => 'img/games/covers/tic-tac-toe.svg',
                'min_players' => 2,
                'max_players' => 2,
                'sort_order' => 10,
            ],
            [
                'slug' => 'toe-tac-tic',
                'title' => 'ToeTacTic',
                'description' => 'A 4×4 board where three in a row makes you lose. Force your opponent into it.',
                'cover' => 'img/games/covers/toe-tac-tic.svg',
                'min_players' => 2,
                'max_players' => 2,
                'sort_order' => 20,
            ],
        ];

        foreach ($games as $game) {
            Game::updateOrCreate(['slug' => $game['slug']], $game);
        }
    }
}
