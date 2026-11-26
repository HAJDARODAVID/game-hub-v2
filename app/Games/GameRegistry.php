<?php

namespace App\Games;

use App\Games\Contracts\GameEngine;
use App\Games\TicTacToe\TicTacToeEngine;
use App\Models\Game;
use InvalidArgumentException;

/**
 * Maps a game's slug to the engine that implements its rules.
 * To add a game: write an engine + board component, register it here, seed the games row.
 */
class GameRegistry
{
    /** @var array<string, class-string<GameEngine>> */
    private const ENGINES = [
        'tic-tac-toe' => TicTacToeEngine::class,
    ];

    public function has(Game|string $game): bool
    {
        return isset(self::ENGINES[$this->slug($game)]);
    }

    public function for(Game|string $game): GameEngine
    {
        $slug = $this->slug($game);

        if (! isset(self::ENGINES[$slug])) {
            throw new InvalidArgumentException("No engine registered for game [{$slug}].");
        }

        return app(self::ENGINES[$slug]);
    }

    private function slug(Game|string $game): string
    {
        return $game instanceof Game ? $game->slug : $game;
    }
}
