<?php

namespace App\Games\TicTacToe;

use App\Games\Contracts\GameEngine;
use App\Games\GameResult;
use App\Games\InvalidMove;
use InvalidArgumentException;

/**
 * State:
 *  board   list of 9 cells (index 0–8, row by row), each null, "X" or "O"
 *  players ["X" => userId, "O" => userId]   X moves first
 *  turn    "X" | "O"
 *  winner  null | "X" | "O"
 *  line    null | [a, b, c]   the winning cells
 *  moves   int
 *
 * Move: ["cell" => 0–8]
 */
class TicTacToeEngine implements GameEngine
{
    private const LINES = [
        [0, 1, 2], [3, 4, 5], [6, 7, 8],   // rows
        [0, 3, 6], [1, 4, 7], [2, 5, 8],   // columns
        [0, 4, 8], [2, 4, 6],              // diagonals
    ];

    public function initialState(array $playerIds, array $settings = []): array
    {
        if (count($playerIds) !== 2) {
            throw new InvalidArgumentException('Tic Tac Toe needs exactly 2 players.');
        }

        [$x, $o] = array_values($playerIds);

        return [
            'board' => array_fill(0, 9, null),
            'players' => ['X' => $x, 'O' => $o],
            'turn' => 'X',
            'winner' => null,
            'line' => null,
            'moves' => 0,
        ];
    }

    public function applyMove(array $state, int $playerId, array $move): array
    {
        if ($this->result($state) !== null) {
            throw new InvalidMove(__('The game is already over.'));
        }
        if ($this->currentPlayerId($state) !== $playerId) {
            throw new InvalidMove(__("It's not your turn."));
        }

        $cell = filter_var($move['cell'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 8]]);
        if ($cell === false) {
            throw new InvalidMove(__('That is not a cell on the board.'));
        }
        if ($state['board'][$cell] !== null) {
            throw new InvalidMove(__('That cell is already taken.'));
        }

        $symbol = $state['turn'];
        $state['board'][$cell] = $symbol;
        $state['moves']++;

        foreach (self::LINES as $line) {
            if ($state['board'][$line[0]] === $symbol
                && $state['board'][$line[1]] === $symbol
                && $state['board'][$line[2]] === $symbol) {
                $state['winner'] = $symbol;
                $state['line'] = $line;
                break;
            }
        }

        $state['turn'] = $symbol === 'X' ? 'O' : 'X';

        return $state;
    }

    public function currentPlayerId(array $state): ?int
    {
        return $this->result($state) === null ? (int) $state['players'][$state['turn']] : null;
    }

    public function result(array $state): ?GameResult
    {
        if ($state['winner'] !== null) {
            return GameResult::win((int) $state['players'][$state['winner']]);
        }
        if ($state['moves'] >= 9) {
            return GameResult::draw();
        }

        return null;
    }

    public function boardComponent(): string
    {
        return 'games.tic-tac-toe.board';
    }
}
