<?php

namespace Tests\Unit\Games;

use App\Games\GameRegistry;
use App\Games\InvalidMove;
use App\Games\ToeTacTic\ToeTacTicEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;   // boots Laravel: the engine uses __() for its messages

/**
 * Board indices:
 *   0  1  2  3
 *   4  5  6  7
 *   8  9 10 11
 *  12 13 14 15
 */
class ToeTacTicEngineTest extends TestCase
{
    private const X = 10;   // user ids
    private const O = 20;

    private ToeTacTicEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new ToeTacTicEngine();
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

    public function test_board_has_16_cells_and_x_moves_first(): void
    {
        $state = $this->engine->initialState([self::X, self::O]);

        $this->assertCount(16, $state['board']);
        $this->assertSame(self::X, $this->engine->currentPlayerId($state));
        $this->assertNull($this->engine->result($state));
    }

    public function test_there_are_24_lines_of_three(): void
    {
        // rows 4×2 + columns 4×2 + diagonals 4 + anti-diagonals 4
        $this->assertCount(24, ToeTacTicEngine::lines());
    }

    public function test_turns_alternate(): void
    {
        $state = $this->play([5]);

        $this->assertSame('X', $state['board'][5]);
        $this->assertSame(self::O, $this->engine->currentPlayerId($state));
    }

    public static function losingGames(): array
    {
        // X completes the line on its 3rd move; O plays harmless cells in between
        return [
            'row left' => [[0, 15, 1, 12, 2], [0, 1, 2]],
            'row right' => [[5, 0, 6, 12, 7], [5, 6, 7]],
            'column' => [[3, 0, 7, 12, 11], [3, 7, 11]],
            'column bottom' => [[5, 0, 9, 3, 13], [5, 9, 13]],
            'diagonal' => [[1, 0, 6, 12, 11], [1, 6, 11]],
            'anti-diagonal' => [[7, 0, 10, 15, 13], [7, 10, 13]],
        ];
    }

    #[DataProvider('losingGames')]
    public function test_three_in_a_row_loses(array $cells, array $line): void
    {
        $state = $this->play($cells);

        $this->assertSame('X', $state['loser']);
        $this->assertSame(self::O, $this->engine->result($state)?->winnerId);
        $this->assertSame($line, $state['line']);
        $this->assertNull($this->engine->currentPlayerId($state));
    }

    public function test_four_in_a_row_needs_three_first(): void
    {
        // X: 0, 1, 3 then 2 → completes 0-1-2 (and 1-2-3): X loses
        $state = $this->play([0, 15, 1, 12, 3, 9, 2]);

        $this->assertSame(self::O, $this->engine->result($state)?->winnerId);
    }

    public function test_o_can_lose(): void
    {
        $state = $this->play([0, 4, 15, 5, 12, 6]);

        $this->assertSame('O', $state['loser']);
        $this->assertSame(self::X, $this->engine->result($state)?->winnerId);
    }

    public function test_full_board_without_a_line_is_a_draw(): void
    {
        // X X O O / O O X X / X X O O / O O X X
        $x = [0, 1, 6, 7, 8, 9, 14, 15];
        $o = [2, 3, 4, 5, 10, 11, 12, 13];
        $state = $this->play(array_merge(...array_map(null, $x, $o)));

        $this->assertNull($state['loser']);
        $this->assertTrue($this->engine->result($state)?->isDraw());
    }

    public function test_line_on_the_last_move_is_a_loss_not_a_draw(): void
    {
        // Same as the draw board, but O's last move (15) completes 5-10-15
        // X X O O / O O X X / X X O O / O O X ·  → swap so O ends on 15
        $x = [0, 1, 6, 7, 8, 9, 14, 13];
        $o = [2, 3, 4, 5, 11, 12, 10, 15];
        $state = $this->play(array_merge(...array_map(null, $x, $o)));

        $this->assertSame(16, $state['moves']);
        $this->assertSame('O', $state['loser']);
        $this->assertSame(self::X, $this->engine->result($state)?->winnerId);
    }

    public static function invalidMoves(): array
    {
        return [
            'wrong player' => [self::O, ['cell' => 0], 'not your turn'],
            'outside board' => [self::X, ['cell' => 16], 'not a cell'],
            'negative' => [self::X, ['cell' => -1], 'not a cell'],
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
        $state = $this->play([5]);

        $this->expectExceptionMessage('already taken');
        $this->engine->applyMove($state, self::O, ['cell' => 5]);
    }

    public function test_no_moves_after_the_game_is_over(): void
    {
        $state = $this->play([0, 15, 1, 12, 2]);

        $this->expectExceptionMessage('already over');
        $this->engine->applyMove($state, self::O, ['cell' => 8]);
    }

    public function test_registered_in_the_game_registry(): void
    {
        $engine = app(GameRegistry::class)->for('toe-tac-tic');

        $this->assertInstanceOf(ToeTacTicEngine::class, $engine);
        $this->assertSame('games.toe-tac-tic.board', $engine->boardComponent());
    }
}
