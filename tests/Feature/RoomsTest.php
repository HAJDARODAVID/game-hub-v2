<?php

namespace Tests\Feature;

use App\Actions\Rooms\CancelInvite;
use App\Actions\Rooms\CloseRoom;
use App\Actions\Rooms\CreateRoom;
use App\Actions\Rooms\InvitePlayer;
use App\Actions\Rooms\LeaveRoom;
use App\Actions\Rooms\RespondToInvite;
use App\Enums\InviteStatus;
use App\Enums\RoomStatus;
use App\Models\Game;
use App\Models\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RoomsTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private User $host;
    private User $friend;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create(['slug' => 'tic-tac-toe', 'title' => 'Tic Tac Toe', 'max_players' => 2]);
        $this->host = User::factory()->create(['username' => 'host']);
        $this->friend = User::factory()->create(['username' => 'friend']);
        $this->stranger = User::factory()->create(['username' => 'stranger']);
    }

    private function createRoom(?User $host = null): Room
    {
        return app(CreateRoom::class)->handle($host ?? $this->host, $this->game);
    }

    private function invite(Room $room, string $username = 'friend')
    {
        return app(InvitePlayer::class)->handle($room, $this->host, $username);
    }

    private function visibleIds(User $user): array
    {
        return Room::visibleTo($user)->pluck('id')->all();
    }

    // ---- Visibility -------------------------------------------------------

    public function test_host_sees_their_room_and_strangers_do_not(): void
    {
        $room = $this->createRoom();

        $this->assertSame([$room->id], $this->visibleIds($this->host));
        $this->assertSame([], $this->visibleIds($this->stranger));
    }

    public function test_pending_invite_makes_room_visible_until_declined(): void
    {
        $room = $this->createRoom();
        $invite = $this->invite($room);

        $this->assertSame([$room->id], $this->visibleIds($this->friend));

        app(RespondToInvite::class)->decline($invite, $this->friend);

        $this->assertSame([], $this->visibleIds($this->friend));
    }

    public function test_cancelled_invite_hides_room(): void
    {
        $room = $this->createRoom();
        $invite = $this->invite($room);

        app(CancelInvite::class)->handle($invite, $this->host);

        $this->assertSame([], $this->visibleIds($this->friend));
    }

    public function test_players_keep_seeing_a_closed_room_as_history(): void
    {
        $room = $this->createRoom();
        app(RespondToInvite::class)->accept($this->invite($room), $this->friend);

        app(CloseRoom::class)->handle($room, $this->host);

        $this->assertSame(RoomStatus::Closed, $room->fresh()->status);
        $this->assertSame([$room->id], $this->visibleIds($this->friend));
        $this->assertSame([$room->id], $this->visibleIds($this->host));
    }

    public function test_leaving_before_the_game_hides_the_room(): void
    {
        $room = $this->createRoom();
        app(RespondToInvite::class)->accept($this->invite($room), $this->friend);

        app(LeaveRoom::class)->handle($room, $this->friend);

        $this->assertSame([], $this->visibleIds($this->friend));
    }

    public function test_room_page_access_follows_visibility(): void
    {
        $room = $this->createRoom();
        $this->invite($room);

        $this->actingAs($this->host)->get(route('rooms.show', $room))->assertOk();
        $this->actingAs($this->friend)->get(route('rooms.show', $room))->assertOk()->assertSee('invited you');
        $this->actingAs($this->stranger)->get(route('rooms.show', $room))->assertForbidden();
    }

    // ---- Rules ------------------------------------------------------------

    public function test_creating_a_room_seats_the_host(): void
    {
        $room = $this->createRoom();

        $this->assertSame(RoomStatus::Waiting, $room->status);
        $this->assertSame("host's room", $room->name);
        $this->assertTrue($room->hasPlayer($this->host));
        $this->assertSame(1, $room->players()->first()->seat);
    }

    public function test_accepting_an_invite_takes_the_next_seat(): void
    {
        $room = $this->createRoom();
        $invite = $this->invite($room);

        app(RespondToInvite::class)->accept($invite, $this->friend);

        $this->assertSame(InviteStatus::Accepted, $invite->fresh()->status);
        $this->assertSame(2, $room->players()->where('user_id', $this->friend->id)->value('seat'));
    }

    public function test_pending_invites_count_towards_capacity(): void
    {
        $room = $this->createRoom();
        $this->invite($room, 'friend');           // host + 1 invite = 2 = max

        $this->expectException(ValidationException::class);
        $this->invite($room, 'stranger');
    }

    /** @dataProvider invalidInvites */
    public function test_invalid_invites_are_rejected(string $username, string $expected): void
    {
        $room = $this->createRoom();
        if ($username === 'friend') {
            $this->invite($room, 'friend');       // invite twice
        }

        try {
            $this->invite($room, $username);
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($expected, $e->errors()['username'][0]);
        }
    }

    public static function invalidInvites(): array
    {
        return [
            'unknown gamertag' => ['nobody', 'No player'],
            'self' => ['host', 'yourself'],
            'already invited' => ['friend', 'already invited'],
            'empty' => ['  ', 'Enter a gamertag'],
        ];
    }

    public function test_only_the_host_can_invite_or_close(): void
    {
        $room = $this->createRoom();

        $this->expectException(AuthorizationException::class);
        app(InvitePlayer::class)->handle($room, $this->stranger, 'friend');
    }

    public function test_only_the_invitee_can_answer_an_invite(): void
    {
        $invite = $this->invite($this->createRoom());

        $this->expectException(AuthorizationException::class);
        app(RespondToInvite::class)->accept($invite, $this->stranger);
    }

    public function test_closing_cancels_pending_invites_and_blocks_accepting(): void
    {
        $room = $this->createRoom();
        $invite = $this->invite($room);

        app(CloseRoom::class)->handle($room, $this->host);

        $this->assertSame(InviteStatus::Cancelled, $invite->fresh()->status);

        $this->expectException(ValidationException::class);
        app(RespondToInvite::class)->accept($invite->fresh(), $this->friend);
    }

    public function test_host_cannot_leave(): void
    {
        $this->expectException(ValidationException::class);
        app(LeaveRoom::class)->handle($this->createRoom(), $this->host);
    }

    // ---- Livewire ---------------------------------------------------------

    public function test_lobby_lists_only_visible_rooms_and_creates_rooms(): void
    {
        $mine = $this->createRoom();
        $theirs = $this->createRoom($this->stranger);

        Livewire::actingAs($this->host)
            ->test('lobby.rooms', ['game' => $this->game])
            ->assertSee($mine->name)
            ->assertDontSee($theirs->name)
            ->call('create')
            ->assertRedirect(route('rooms.show', Room::latest('id')->first()));

        $this->assertSame(3, Room::count());
    }

    public function test_invitee_can_accept_from_the_lobby(): void
    {
        $room = $this->createRoom();
        $invite = $this->invite($room);

        Livewire::actingAs($this->friend)
            ->test('lobby.rooms', ['game' => $this->game])
            ->assertSee($room->name)
            ->assertSee('Accept')
            ->call('accept', $invite->id)
            ->assertRedirect(route('rooms.show', $room));

        $this->assertTrue($room->hasPlayer($this->friend));
    }

    public function test_host_invites_by_gamertag_in_the_room(): void
    {
        $room = $this->createRoom();

        Livewire::actingAs($this->host)
            ->test('rooms.show', ['room' => $room])
            ->set('username', 'nobody')
            ->call('invite')
            ->assertHasErrors('username')
            ->set('username', 'friend')
            ->call('invite')
            ->assertHasNoErrors()
            ->assertSet('username', '')
            ->assertSee('friend');

        $this->assertNotNull($room->pendingInviteFor($this->friend));
    }

    public function test_player_leaving_is_redirected_to_the_lobby(): void
    {
        $room = $this->createRoom();
        app(RespondToInvite::class)->accept($this->invite($room), $this->friend);

        Livewire::actingAs($this->friend)
            ->test('rooms.show', ['room' => $room])
            ->call('leave')
            ->assertRedirect(route('games.lobby', $this->game));
    }
}
