<?php

namespace App\Games\ToeTacTic;

use App\Games\Contracts\GameEngine;
use App\Games\GameResult;
use App\Games\InvalidMove;
use InvalidArgumentException;

/**
 * Reverse Tic Tac Toe on a 4×4 board: whoever completes three in a row LOSES.
 *
 * State:
 *  board   list of 16 cells (index 0–15, row by row), each null, "X" or "O"
 *  players ["X" => userId, "O" => userId]   X moves first
 *  turn    "X" | "O"
 *  loser   null | "X" | "O"   the symbol that made three in a row
 *  line    null | [a, b, c]   the losing cells
 *  moves   int
 *
 * Move: ["cell" => 0–15]
 */
class ToeTacTicEngine implements GameEngine
{
    public const SIZE = 4;

    public const CELLS = self::SIZE * self::SIZE;

    /** @var list<array{int,int,int}>|null every run of 3 cells (rows, columns, both diagonals) */
    private static ?array $lines = null;

    /** @return list<array{int,int,int}> */
    public static function lines(): array
    {
        if (self::$lines !== null) {
            return self::$lines;
        }

        $lines = [];
        $directions = [[0, 1], [1, 0], [1, 1], [1, -1]];   // right, down, down-right, down-left

        for ($r = 0; $r < self::SIZE; $r++) {
            for ($c = 0; $c < self::SIZE; $c++) {
                foreach ($directions as [$dr, $dc]) {
                    $endR = $r + 2 * $dr;
                    $endC = $c + 2 * $dc;
                    if ($endR < self::SIZE && $endC >= 0 && $endC < self::SIZE) {
                        $lines[] = [
                            $r * self::SIZE + $c,
                            ($r + $dr) * self::SIZE + ($c + $dc),
                            $endR * self::SIZE + $endC,
                        ];
                    }
                }
            }
        }

        return self::$lines = $lines;
    }

    public function initialState(array $playerIds, array $settings = []): array
    {
        if (count($playerIds) !== 2) {
            throw new InvalidArgumentException('ToeTacTic needs exactly 2 players.');
        }

        [$x, $o] = array_values($playerIds);

        return [
            'board' => array_fill(0, self::CELLS, null),
            'players' => ['X' => $x, 'O' => $o],
            'turn' => 'X',
            'loser' => null,
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

        $cell = filter_var($move['cell'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => self::CELLS - 1]]);
        if ($cell === false) {
            throw new InvalidMove(__('That is not a cell on the board.'));
        }
        if ($state['board'][$cell] !== null) {
            throw new InvalidMove(__('That cell is already taken.'));
        }

        $symbol = $state['turn'];
        $state['board'][$cell] = $symbol;
        $state['moves']++;

        foreach (self::lines() as $line) {
            if (in_array($cell, $line, true)
                && $state['board'][$line[0]] === $symbol
                && $state['board'][$line[1]] === $symbol
                && $state['board'][$line[2]] === $symbol) {
                $state['loser'] = $symbol;
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
        if ($state['loser'] !== null) {
            $winner = $state['loser'] === 'X' ? 'O' : 'X';

            return GameResult::win((int) $state['players'][$winner]);
        }
        if ($state['moves'] >= self::CELLS) {
            return GameResult::draw();
        }

        return null;
    }

    public function boardComponent(): string
    {
        return 'games.toe-tac-tic.board';
    }
}
