<?php

namespace Tests\Feature;

use App\Actions\Matches\MakeMove;
use App\Actions\Matches\RequestRematch;
use App\Actions\Matches\StartMatch;
use App\Actions\Rooms\CancelInvite;
use App\Actions\Rooms\CloseRoom;
use App\Actions\Rooms\CreateRoom;
use App\Actions\Rooms\InvitePlayer;
use App\Actions\Rooms\RespondToInvite;
use App\Events\PlayerUpdated;
use App\Events\RoomUpdated;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Room;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private User $host;
    private User $friend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['slug' => 'tic-tac-toe', 'min_players' => 2, 'max_players' => 2]);
        $this->host = User::factory()->create(['username' => 'host']);
        $this->friend = User::factory()->create(['username' => 'friend']);
    }

    private function fullRoom(): Room
    {
        $room = app(CreateRoom::class)->handle($this->host, $this->game);
        app(RespondToInvite::class)->accept(app(InvitePlayer::class)->handle($room, $this->host, 'friend'), $this->friend);

        return $room->fresh();
    }

    private function assertPlayersNotified(array $users): void
    {
        foreach ($users as $user) {
            Event::assertDispatched(PlayerUpdated::class, fn (PlayerUpdated $e) => $e->userId === $user->id);
        }
    }

    // ---- Who gets notified ------------------------------------------------

    public function test_invite_notifies_the_room_and_the_invitee(): void
    {
        $room = app(CreateRoom::class)->handle($this->host, $this->game);
        Event::fake([RoomUpdated::class, PlayerUpdated::class]);

        app(InvitePlayer::class)->handle($room, $this->host, 'friend');

        Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $e) => $e->roomId === $room->id);
        $this->assertPlayersNotified([$this->host, $this->friend]);
    }

    public function test_declined_and_cancelled_invites_still_notify_the_invitee(): void
    {
        $room = app(CreateRoom::class)->handle($this->host, $this->game);
        $invite = app(InvitePlayer::class)->handle($room, $this->host, 'friend');

        Event::fake([RoomUpdated::class, PlayerUpdated::class]);
        app(CancelInvite::class)->handle($invite, $this->host);
        $this->assertPlayersNotified([$this->host, $this->friend]);

        $invite = app(InvitePlayer::class)->handle($room, $this->host, 'friend');
        Event::fake([RoomUpdated::class, PlayerUpdated::class]);
        app(RespondToInvite::class)->decline($invite, $this->friend);
        $this->assertPlayersNotified([$this->host, $this->friend]);
    }

    public function test_a_normal_move_only_updates_the_room(): void
    {
        $match = app(StartMatch::class)->handle($this->fullRoom(), $this->host);
        Event::fake([RoomUpdated::class, PlayerUpdated::class]);

        app(MakeMove::class)->handle($match, $this->host, ['cell' => 4]);

        Event::assertDispatched(RoomUpdated::class);
        Event::assertNotDispatched(PlayerUpdated::class);
    }

    public function test_a_winning_move_also_notifies_both_players(): void
    {
        $match = app(StartMatch::class)->handle($this->fullRoom(), $this->host);
        foreach ([[$this->host, 0], [$this->friend, 3], [$this->host, 1], [$this->friend, 4]] as [$p, $c]) {
            app(MakeMove::class)->handle($match, $p, ['cell' => $c]);
        }

        Event::fake([RoomUpdated::class, PlayerUpdated::class]);
        app(MakeMove::class)->handle($match, $this->host, ['cell' => 2]);

        $this->assertPlayersNotified([$this->host, $this->friend]);   // lobby stats + footer button
    }

    public function test_start_rematch_and_forfeit_notify_both_players(): void
    {
        $room = $this->fullRoom();

        Event::fake([RoomUpdated::class, PlayerUpdated::class]);
        $match = app(StartMatch::class)->handle($room, $this->host);
        $this->assertPlayersNotified([$this->host, $this->friend]);

        Event::fake([RoomUpdated::class, PlayerUpdated::class]);
        app(CloseRoom::class)->handle($room, $this->friend);   // forfeit
        Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $e) => $e->roomId === $room->id);
        $this->assertPlayersNotified([$this->host, $this->friend]);
    }

    public function test_a_rematch_vote_updates_the_room(): void
    {
        $room = $this->fullRoom();
        $match = app(StartMatch::class)->handle($room, $this->host);
        foreach ([[$this->host, 0], [$this->friend, 3], [$this->host, 1], [$this->friend, 4], [$this->host, 2]] as [$p, $c]) {
            app(MakeMove::class)->handle($match, $p, ['cell' => $c]);
        }

        Event::fake([RoomUpdated::class, PlayerUpdated::class]);
        app(RequestRematch::class)->handle($room, $this->friend);

        Event::assertDispatched(RoomUpdated::class, fn (RoomUpdated $e) => $e->roomId === $room->id);
    }

    public function test_events_use_the_private_channels(): void
    {
        $this->assertSame('private-room.7', (new RoomUpdated(7))->broadcastOn()[0]->name);
        $this->assertSame('private-user.3', (new PlayerUpdated(3))->broadcastOn()[0]->name);
    }

    // ---- Reverb down must not break the game ------------------------------

    public function test_moves_still_work_when_reverb_is_unreachable(): void
    {
        $match = app(StartMatch::class)->handle($this->fullRoom(), $this->host);

        // Point broadcasting at a port where nothing listens
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 1,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);
        app(BroadcastManager::class)->purge('reverb');
        Log::spy();

        $match = app(MakeMove::class)->handle($match, $this->host, ['cell' => 4]);

        $this->assertSame('X', $match->fresh()->state['board'][4]);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'Reverb'))->atLeast()->once();
    }

    // ---- Channel authorization --------------------------------------------

    public function test_only_allowed_users_can_listen_to_a_room_or_user_channel(): void
    {
        $room = $this->fullRoom();
        $stranger = User::factory()->create();

        // Sign channel auth locally with a fake Reverb app (no server needed)
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => '1',
        ]);
        app(BroadcastManager::class)->purge();
        require base_path('routes/channels.php');

        $auth = fn (User $user, string $channel) => $this->actingAs($user)
            ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => $channel]);

        $auth($this->friend, "private-room.{$room->id}")->assertOk();
        $auth($stranger, "private-room.{$room->id}")->assertForbidden();
        $auth($this->host, "private-user.{$this->host->id}")->assertOk();
        $auth($this->host, "private-user.{$this->friend->id}")->assertForbidden();
    }

    // ---- Livewire listens and refreshes -----------------------------------

    public function test_components_listen_on_the_right_channels(): void
    {
        $room = $this->fullRoom();
        $match = app(StartMatch::class)->handle($room, $this->host);

        $listeners = fn (string $name, array $params) => array_keys(
            (fn () => $this->getListeners())->call(Livewire::actingAs($this->host)->test($name, $params)->instance())
        );

        $this->assertSame(["echo-private:room.{$room->id},RoomUpdated"], $listeners('rooms.show', ['room' => $room->fresh()]));
        $this->assertSame(["echo-private:room.{$room->id},RoomUpdated"], $listeners('games.tic-tac-toe.board', ['match' => $match]));

        foreach (['lobby.rooms' => ['game' => $this->game], 'invites.invite-list' => [], 'invites.badge' => [], 'matches.active-button' => []] as $name => $params) {
            $this->assertSame(["echo-private:user.{$this->host->id},PlayerUpdated"], $listeners($name, $params), $name);
        }
    }

    public function test_opponent_board_shows_the_move_when_the_event_arrives(): void
    {
        $room = $this->fullRoom();
        $match = app(StartMatch::class)->handle($room, $this->host);

        $friendBoard = Livewire::actingAs($this->friend)
            ->test('games.tic-tac-toe.board', ['match' => $match])
            ->assertSee('Waiting for host');

        app(MakeMove::class)->handle($match, $this->host, ['cell' => 4]);

        $friendBoard->dispatch("echo-private:room.{$room->id},RoomUpdated", ['roomId' => $room->id])
            ->assertSee('Your turn');
    }

    public function test_invitee_badge_counts_up_when_the_event_arrives(): void
    {
        $badge = Livewire::actingAs($this->friend)->test('invites.badge')->assertDontSeeHtml('invites-badge__count');

        $room = app(CreateRoom::class)->handle($this->host, $this->game);
        app(InvitePlayer::class)->handle($room, $this->host, 'friend');

        $badge->dispatch("echo-private:user.{$this->friend->id},PlayerUpdated")
            ->assertSeeHtml('invites-badge__count');
    }
}
