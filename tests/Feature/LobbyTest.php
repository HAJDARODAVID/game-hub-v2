<?php

namespace Tests\Feature;

use App\Actions\Matches\MakeMove;
use App\Actions\Matches\RequestRematch;
use App\Actions\Matches\StartMatch;
use App\Actions\Rooms\CloseRoom;
use App\Actions\Rooms\CreateRoom;
use App\Actions\Rooms\InvitePlayer;
use App\Actions\Rooms\RespondToInvite;
use App\Games\PlayerStats;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LobbyTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private User $me;
    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['slug' => 'tic-tac-toe', 'title' => 'Tic Tac Toe', 'min_players' => 2, 'max_players' => 2]);
        $this->me = User::factory()->create(['username' => 'me']);
        $this->ana = User::factory()->create(['username' => 'ana']);
    }

    private function room(User $host, string $name, ?User $guest = null): Room
    {
        $room = app(CreateRoom::class)->handle($host, $this->game, $name);
        if ($guest) {
            app(RespondToInvite::class)->accept(app(InvitePlayer::class)->handle($room, $host, $guest->username), $guest);
        }

        return $room->fresh();
    }

    /** Play a finished match where X wins (top row), or a draw. */
    private function play(GameMatch $match, User $x, User $o, bool $draw = false): GameMatch
    {
        $cells = $draw ? [0, 1, 2, 4, 3, 5, 7, 6, 8] : [0, 3, 1, 4, 2];
        foreach ($cells as $i => $cell) {
            $match = app(MakeMove::class)->handle($match, $i % 2 === 0 ? $x : $o, ['cell' => $cell]);
        }

        return $match;
    }

    public function test_my_rooms_shows_only_my_open_rooms(): void
    {
        $this->room($this->me, 'My waiting room');
        $this->room($this->ana, 'Ana room I joined', $this->me);
        $this->room($this->ana, 'Ana private room');                       // not mine

        $closed = $this->room($this->me, 'My closed room');
        app(CloseRoom::class)->handle($closed, $this->me);                 // history: hidden

        Livewire::actingAs($this->me)
            ->test('lobby.rooms', ['game' => $this->game])
            ->assertSee('My waiting room')
            ->assertSee('Ana room I joined')
            ->assertDontSee('Ana private room')
            ->assertDontSee('My closed room');
    }

    public function test_invites_section_lists_pending_invites_for_this_game_only(): void
    {
        $room = $this->room($this->ana, 'Ana challenge');
        app(InvitePlayer::class)->handle($room, $this->ana, 'me');

        $other = Game::factory()->create(['slug' => 'other']);
        $otherRoom = app(CreateRoom::class)->handle($this->ana, $other, 'Other game room');
        app(InvitePlayer::class)->handle($otherRoom, $this->ana, 'me');

        Livewire::actingAs($this->me)
            ->test('lobby.rooms', ['game' => $this->game])
            ->assertSee('Ana challenge')
            ->assertSee('ana invited you')
            ->assertDontSee('Other game room');
    }

    public function test_layout_order_header_with_create_then_stats_invites_rooms(): void
    {
        $this->actingAs($this->me)
            ->get(route('games.lobby', $this->game))
            ->assertOk()
            ->assertSeeInOrder(['Tic Tac Toe', 'Create room', 'YOUR STATISTICS', 'INVITES', 'MY ROOMS']);
    }

    public function test_empty_lobby_shows_empty_states(): void
    {
        Livewire::actingAs($this->me)
            ->test('lobby.rooms', ['game' => $this->game])
            ->assertSee('YOUR STATISTICS')
            ->assertSee('No open rooms')
            ->assertSee('No pending invites.');
    }

    public function test_statistics_count_wins_losses_draws_and_forfeits(): void
    {
        $room = $this->room($this->me, 'Series', $this->ana);

        // 1: me (X) wins
        $this->play(app(StartMatch::class)->handle($room, $this->me), $this->me, $this->ana);
        // 2: rematch, ana is X and wins
        app(RequestRematch::class)->handle($room, $this->me);
        $this->play(app(RequestRematch::class)->handle($room, $this->ana), $this->ana, $this->me);
        // 3: rematch, me X, draw
        app(RequestRematch::class)->handle($room, $this->me);
        $this->play(app(RequestRematch::class)->handle($room, $this->ana), $this->me, $this->ana, draw: true);
        // 4: rematch, ana leaves mid-game → my win
        app(RequestRematch::class)->handle($room, $this->me);
        app(RequestRematch::class)->handle($room, $this->ana);
        app(CloseRoom::class)->handle($room, $this->ana);

        $mine = PlayerStats::for($this->me, $this->game);
        $this->assertSame([4, 2, 1, 1], [$mine->played, $mine->wins, $mine->losses, $mine->draws]);
        $this->assertSame(50, $mine->winRate());

        $hers = PlayerStats::for($this->ana, $this->game);
        $this->assertSame([4, 1, 2, 1], [$hers->played, $hers->wins, $hers->losses, $hers->draws]);

        $nobody = PlayerStats::for(User::factory()->create(), $this->game);
        $this->assertSame([0, 0, 0, 0, 0], [$nobody->played, $nobody->wins, $nobody->losses, $nobody->draws, $nobody->winRate()]);
    }

    public function test_statistics_are_per_game(): void
    {
        $other = Game::factory()->create(['slug' => 'other-game']);

        $room = $this->room($this->me, 'Series', $this->ana);
        $this->play(app(StartMatch::class)->handle($room, $this->me), $this->me, $this->ana);

        $this->assertSame(1, PlayerStats::for($this->me, $this->game)->wins);
        $this->assertSame(0, PlayerStats::for($this->me, $other)->played);
    }
}
