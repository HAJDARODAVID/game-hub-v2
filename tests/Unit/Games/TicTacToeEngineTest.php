<?php

namespace Tests\Unit\Games;

use App\Games\InvalidMove;
use App\Games\TicTacToe\TicTacToeEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;   // boots Laravel: the engine uses __() for its messages

class TicTacToeEngineTest extends TestCase
{
    private const X = 10;   // user ids
    private const O = 20;

    private TicTacToeEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new TicTacToeEngine();
    }

    /** Play cells alternately, X first. */
    private function play(array $cells): array
    {
        $state = $this->engine->initialState([self::X, self::O]);
        foreach ($cells as $cell) {
            $state = $this->engine->applyMove($state, $this->engine->currentPlayerId($state), ['cell' => $cell]);
        }

        return $state;
    }

    public function test_x_moves_first(): void
    {
        $state = $this->engine->initialState([self::X, self::O]);

        $this->assertSame(self::X, $this->engine->currentPlayerId($state));
        $this->assertNull($this->engine->result($state));
    }

    public function test_turns_alternate(): void
    {
        $state = $this->play([4]);

        $this->assertSame('X', $state['board'][4]);
        $this->assertSame(self::O, $this->engine->currentPlayerId($state));
    }

    public static function winningGames(): array
    {
        return [
            'row' => [[0, 3, 1, 4, 2], [0, 1, 2]],
            'column' => [[1, 0, 4, 2, 7], [1, 4, 7]],
            'diagonal' => [[0, 1, 4, 2, 8], [0, 4, 8]],
            'anti-diagonal' => [[2, 0, 4, 1, 6], [2, 4, 6]],
        ];
    }

    #[DataProvider('winningGames')]
    public function test_three_in_a_row_wins(array $cells, array $line): void
    {
        $state = $this->play($cells);

        $this->assertSame(self::X, $this->engine->result($state)?->winnerId);
        $this->assertSame($line, $state['line']);
        $this->assertNull($this->engine->currentPlayerId($state));
    }

    public function test_o_can_win(): void
    {
        $state = $this->play([0, 3, 1, 4, 8, 5]);

        $this->assertSame(self::O, $this->engine->result($state)?->winnerId);
    }

    public function test_full_board_without_a_line_is_a_draw(): void
    {
        // X O X / X O O / O X X
        $state = $this->play([0, 1, 2, 4, 3, 5, 7, 6, 8]);

        $this->assertTrue($this->engine->result($state)?->isDraw());
    }

    public function test_win_on_the_last_move_is_a_win_not_a_draw(): void
    {
        // X O X / O X O / O X X  → X completes the diagonal with the 9th move
        $state = $this->play([0, 1, 2, 3, 4, 6, 7, 5, 8]);

        $this->assertSame(self::X, $this->engine->result($state)?->winnerId);
    }

    public static function invalidMoves(): array
    {
        return [
            'wrong player' => [self::O, ['cell' => 0], 'not your turn'],
            'outside board' => [self::X, ['cell' => 9], 'not a cell'],
            'not a number' => [self::X, ['cell' => 'a'], 'not a cell'],
            'missing cell' => [self::X, [], 'not a cell'],
        ];
    }

    #[DataProvider('invalidMoves')]
    public function test_invalid_moves_are_rejected(int $player, array $move, string $message): void
    {
        $this->expectException(InvalidMove::class);
        $this->expectExceptionMessage($message);

        $this->engine->applyMove($this->engine->initialState([self::X, self::O]), $player, $move);
    }

    public function test_taken_cell_is_rejected(): void
    {
        $state = $this->play([4]);

        $this->expectExceptionMessage('already taken');
        $this->engine->applyMove($state, self::O, ['cell' => 4]);
    }

    public function test_no_moves_after_the_game_is_over(): void
    {
        $state = $this->play([0, 3, 1, 4, 2]);

        $this->expectExceptionMessage('already over');
        $this->engine->applyMove($state, self::O, ['cell' => 8]);
    }
}
