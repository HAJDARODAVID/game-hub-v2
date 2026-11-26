<?php

namespace Tests\Feature;

use App\Actions\Matches\MakeMove;
use App\Actions\Matches\RequestRematch;
use App\Actions\Matches\StartMatch;
use App\Actions\Rooms\CloseRoom;
use App\Actions\Rooms\CreateRoom;
use App\Actions\Rooms\InvitePlayer;
use App\Actions\Rooms\RespondToInvite;
use App\Enums\InviteStatus;
use App\Enums\MatchStatus;
use App\Enums\RoomStatus;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MatchesTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private User $host;
    private User $friend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['slug' => 'tic-tac-toe', 'title' => 'Tic Tac Toe', 'min_players' => 2, 'max_players' => 2]);
        $this->host = User::factory()->create(['username' => 'host']);
        $this->friend = User::factory()->create(['username' => 'friend']);
    }

    /** A room with host (seat 1) and friend (seat 2). */
    private function fullRoom(): Room
    {
        $room = app(CreateRoom::class)->handle($this->host, $this->game);
        $invite = app(InvitePlayer::class)->handle($room, $this->host, 'friend');
        app(RespondToInvite::class)->accept($invite, $this->friend);

        return $room->fresh();
    }

    private function move(GameMatch $match, User $player, int $cell): GameMatch
    {
        return app(MakeMove::class)->handle($match, $player, ['cell' => $cell]);
    }

    public function test_host_starts_a_match_and_seat_one_plays_x(): void
    {
        $room = $this->fullRoom();

        $match = app(StartMatch::class)->handle($room, $this->host);

        $this->assertSame(RoomStatus::Playing, $room->fresh()->status);
        $this->assertSame(MatchStatus::Playing, $match->status);
        $this->assertSame(['X' => $this->host->id, 'O' => $this->friend->id], $match->state['players']);
    }

    public function test_cannot_start_without_enough_players(): void
    {
        $room = app(CreateRoom::class)->handle($this->host, $this->game);

        $this->expectException(ValidationException::class);
        app(StartMatch::class)->handle($room, $this->host);
    }

    public function test_only_the_host_can_start(): void
    {
        $this->expectException(AuthorizationException::class);
        app(StartMatch::class)->handle($this->fullRoom(), $this->friend);
    }

    public function test_cannot_start_twice(): void
    {
        $room = $this->fullRoom();
        app(StartMatch::class)->handle($room, $this->host);

        $this->expectException(ValidationException::class);
        app(StartMatch::class)->handle($room->fresh(), $this->host);
    }

    public function test_game_without_an_engine_cannot_start(): void
    {
        $other = Game::factory()->create(['slug' => 'not-built-yet', 'min_players' => 1, 'max_players' => 2]);
        $room = app(CreateRoom::class)->handle($this->host, $other);

        try {
            app(StartMatch::class)->handle($room, $this->host);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString("can't be played", $e->errors()['room'][0]);
        }
    }

    public function test_starting_cancels_pending_invites(): void
    {
        // A spare seat so an invite can still be pending when the 2 seated players start
        $this->game->update(['max_players' => 3]);
        User::factory()->create(['username' => 'third']);

        $room = $this->fullRoom();
        $pending = app(InvitePlayer::class)->handle($room, $this->host, 'third');

        app(StartMatch::class)->handle($room, $this->host);

        $this->assertSame(InviteStatus::Cancelled, $pending->fresh()->status);
    }

    public function test_a_full_match_finishes_the_room_and_records_the_winner(): void
    {
        $room = $this->fullRoom();
        $match = app(StartMatch::class)->handle($room, $this->host);

        // host = X: 0, 1, 2 (top row); friend = O: 3, 4
        $this->move($match, $this->host, 0);
        $this->move($match, $this->friend, 3);
        $this->move($match, $this->host, 1);
        $this->move($match, $this->friend, 4);
        $match = $this->move($match, $this->host, 2);

        $this->assertSame(MatchStatus::Finished, $match->status);
        $this->assertSame($this->host->id, $match->winner_id);
        $this->assertNotNull($match->finished_at);
        $this->assertSame(RoomStatus::Finished, $room->fresh()->status);
    }

    public function test_draw_has_no_winner(): void
    {
        $match = app(StartMatch::class)->handle($this->fullRoom(), $this->host);

        foreach ([0, 1, 2, 4, 3, 5, 7, 6, 8] as $i => $cell) {
            $match = $this->move($match, $i % 2 === 0 ? $this->host : $this->friend, $cell);
        }

        $this->assertTrue($match->isDraw());
    }

    public function test_playing_out_of_turn_is_rejected(): void
    {
        $match = app(StartMatch::class)->handle($this->fullRoom(), $this->host);

        try {
            $this->move($match, $this->friend, 0);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('not your turn', $e->errors()['move'][0]);
        }
    }

    public function test_non_players_cannot_move(): void
    {
        $match = app(StartMatch::class)->handle($this->fullRoom(), $this->host);

        $this->expectException(AuthorizationException::class);
        $this->move($match, User::factory()->create(), 0);
    }

    /** Start a match and let the host (X) win with the top row. */
    private function finishedRoom(): Room
    {
        $room = $this->fullRoom();
        $match = app(StartMatch::class)->handle($room, $this->host);
        foreach ([0, 3, 1, 4, 2] as $i => $cell) {
            $this->move($match, $i % 2 === 0 ? $this->host : $this->friend, $cell);
        }

        return $room->fresh();
    }

    public function test_rematch_starts_only_when_both_players_say_yes(): void
    {
        $room = $this->finishedRoom();

        $this->assertNull(app(RequestRematch::class)->handle($room, $this->friend));   // loser asks
        $this->assertSame(RoomStatus::Finished, $room->fresh()->status);
        $this->assertSame([$this->friend->id], $room->matches()->latest('id')->first()->rematchVotes());

        $second = app(RequestRematch::class)->handle($room, $this->host);              // winner says yes

        $this->assertNotNull($second);
        $this->assertSame(RoomStatus::Playing, $room->fresh()->status);
        $this->assertSame(2, $room->matches()->count());
        $this->assertSame($this->friend->id, $second->state['players']['X'], 'first move alternates');
    }

    public function test_asking_twice_does_not_start_alone(): void
    {
        $room = $this->finishedRoom();

        app(RequestRematch::class)->handle($room, $this->host);
        $this->assertNull(app(RequestRematch::class)->handle($room, $this->host));

        $this->assertSame(1, $room->matches()->count());
    }

    public function test_host_can_no_longer_force_a_rematch(): void
    {
        $this->expectException(ValidationException::class);
        app(StartMatch::class)->handle($this->finishedRoom(), $this->host);
    }

    public function test_rematch_needs_a_finished_game_and_a_seated_player(): void
    {
        $room = $this->fullRoom();
        app(StartMatch::class)->handle($room, $this->host);

        try {
            app(RequestRematch::class)->handle($room, $this->host);    // still playing
            $this->fail('Expected a validation error.');
        } catch (ValidationException) {
        }

        $this->expectException(AuthorizationException::class);
        app(RequestRematch::class)->handle($this->finishedRoom(), User::factory()->create());
    }

    public function test_any_player_can_close_after_a_match(): void
    {
        $room = $this->finishedRoom();

        app(CloseRoom::class)->handle($room, $this->friend);

        $this->assertSame(RoomStatus::Closed, $room->fresh()->status);

        $this->expectException(ValidationException::class);
        app(RequestRematch::class)->handle($room->fresh(), $this->host);
    }

    public function test_only_the_host_closes_a_waiting_room(): void
    {
        $this->expectException(AuthorizationException::class);
        app(CloseRoom::class)->handle($this->fullRoom(), $this->friend);
    }

    public function test_rematch_flow_on_the_room_page(): void
    {
        $room = $this->finishedRoom();

        // Both see the choice
        Livewire::actingAs($this->host)->test('rooms.show', ['room' => $room])
            ->assertSee('Play again?')->assertSee('Close room');

        // Friend asks → waits
        Livewire::actingAs($this->friend)->test('rooms.show', ['room' => $room])
            ->call('playAgain')
            ->assertSee('Waiting for host to answer')
            ->assertDontSee('Play again?');

        // Host sees the request and accepts → new match
        Livewire::actingAs($this->host)->test('rooms.show', ['room' => $room])
            ->assertSee('friend wants a rematch!')
            ->assertSee('Yes!')
            ->call('playAgain')
            ->assertHasNoErrors()
            ->assertDontSee('Play again?');

        $this->assertSame(RoomStatus::Playing, $room->fresh()->status);
    }

    public function test_host_can_close_after_a_match_but_not_during(): void
    {
        $room = $this->fullRoom();
        app(StartMatch::class)->handle($room, $this->host);

        try {
            app(CloseRoom::class)->handle($room->fresh(), $this->host);
            $this->fail('Expected a validation error.');
        } catch (ValidationException) {
            $this->assertSame(RoomStatus::Playing, $room->fresh()->status);
        }
    }

    // ---- Livewire ---------------------------------------------------------

    public function test_host_starts_from_the_room_page(): void
    {
        $room = $this->fullRoom();

        Livewire::actingAs($this->host)
            ->test('rooms.show', ['room' => $room])
            ->assertSee('Start game')
            ->call('startGame')
            ->assertHasNoErrors()
            ->assertSee('Your turn');

        $this->assertSame(RoomStatus::Playing, $room->fresh()->status);
    }

    public function test_players_and_invites_cards_are_hidden_once_a_match_exists(): void
    {
        $room = $this->fullRoom();

        $component = Livewire::actingAs($this->host)
            ->test('rooms.show', ['room' => $room])
            ->assertSee('PLAYERS')
            ->assertSee('INVITES');

        $component->call('startGame')
            ->assertDontSee('PLAYERS')
            ->assertDontSee('INVITES')
            ->assertSee('Your turn');
    }

    public function test_board_plays_moves_and_announces_the_winner(): void
    {
        $match = app(StartMatch::class)->handle($this->fullRoom(), $this->host);
        $this->move($match, $this->host, 0);
        $this->move($match, $this->friend, 3);
        $this->move($match, $this->host, 1);
        $this->move($match, $this->friend, 4);

        Livewire::actingAs($this->host)
            ->test('games.tic-tac-toe.board', ['match' => $match->fresh()])
            ->assertSee('Your turn')
            ->call('play', 2)
            ->assertHasNoErrors()
            ->assertSee('You won!')
            ->assertDispatched('match-finished');

        Livewire::actingAs($this->friend)
            ->test('games.tic-tac-toe.board', ['match' => $match->fresh()])
            ->assertSee('host won');
    }

    public function test_board_shows_errors_for_invalid_moves(): void
    {
        $match = app(StartMatch::class)->handle($this->fullRoom(), $this->host);

        Livewire::actingAs($this->friend)
            ->test('games.tic-tac-toe.board', ['match' => $match])
            ->assertSee('Waiting for host')
            ->call('play', 0)
            ->assertHasErrors('move');
    }
}
