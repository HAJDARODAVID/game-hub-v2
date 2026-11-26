<?php

namespace App\Games\Contracts;

use App\Games\GameResult;
use App\Games\InvalidMove;

/**
 * The rules of one game. Engines are pure: they take a state array and return a new one,
 * with no database or HTTP access, so they are easy to unit test.
 * Rooms, invites and matches are handled generically outside the engine.
 */
interface GameEngine
{
    /**
     * Initial state for a new match.
     *
     * @param  list<int>  $playerIds  user ids in turn order (first id moves first)
     */
    public function initialState(array $playerIds, array $settings = []): array;

    /**
     * Apply a move and return the new state.
     *
     * @throws InvalidMove when the move is not allowed (shown to the player)
     */
    public function applyMove(array $state, int $playerId, array $move): array;

    /** Whose turn it is, or null when the match is over. */
    public function currentPlayerId(array $state): ?int;

    /** The outcome, or null while the match is still running. */
    public function result(array $state): ?GameResult;

    /** Livewire component that renders the board, e.g. "games.tic-tac-toe.board". */
    public function boardComponent(): string;
}
