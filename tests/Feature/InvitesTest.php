<?php

namespace Tests\Feature;

use App\Actions\Matches\StartMatch;
use App\Actions\Rooms\CloseRoom;
use App\Actions\Rooms\CreateRoom;
use App\Actions\Rooms\InvitePlayer;
use App\Actions\Rooms\RespondToInvite;
use App\Models\Game;
use App\Models\RoomInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvitesTest extends TestCase
{
    use RefreshDatabase;

    private Game $ticTacToe;
    private Game $otherGame;
    private User $me;
    private User $alice;
    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ticTacToe = Game::factory()->create(['slug' => 'tic-tac-toe', 'title' => 'Tic Tac Toe']);
        $this->otherGame = Game::factory()->create(['slug' => 'other', 'title' => 'Other Game']);
        $this->me = User::factory()->create(['username' => 'me']);
        $this->alice = User::factory()->create(['username' => 'alice']);
        $this->bob = User::factory()->create(['username' => 'bob']);
    }

    private function inviteMe(User $host, Game $game, string $name): RoomInvite
    {
        $room = app(CreateRoom::class)->handle($host, $game, $name);

        return app(InvitePlayer::class)->handle($room, $host, 'me');
    }

    public function test_page_lists_pending_invites_across_games(): void
    {
        $this->inviteMe($this->alice, $this->ticTacToe, 'Alice table');
        $this->inviteMe($this->bob, $this->otherGame, 'Bob table');

        $this->actingAs($this->me)
            ->get(route('invites.index'))
            ->assertOk()
            ->assertSee('Alice table')
            ->assertSee('Tic Tac Toe')
            ->assertSee('Bob table')
            ->assertSee('Other Game');
    }

    public function test_answered_cancelled_and_closed_invites_are_not_listed(): void
    {
        $declined = $this->inviteMe($this->alice, $this->ticTacToe, 'Declined room');
        app(RespondToInvite::class)->decline($declined, $this->me);

        $closed = $this->inviteMe($this->bob, $this->ticTacToe, 'Closed room');
        app(CloseRoom::class)->handle($closed->room, $this->bob);

        $this->assertSame(0, RoomInvite::pendingFor($this->me)->count());

        Livewire::actingAs($this->me)
            ->test('invites.list')
            ->assertSee('No pending invites.')
            ->assertDontSee('Declined room')
            ->assertDontSee('Closed room');
    }

    public function test_accept_joins_the_room(): void
    {
        $invite = $this->inviteMe($this->alice, $this->ticTacToe, 'Alice table');

        Livewire::actingAs($this->me)
            ->test('invites.list')
            ->call('accept', $invite->id)
            ->assertRedirect(route('rooms.show', $invite->room_id));

        $this->assertTrue($invite->room->hasPlayer($this->me));
    }

    public function test_decline_removes_the_invite_and_updates_the_badge(): void
    {
        $invite = $this->inviteMe($this->alice, $this->ticTacToe, 'Alice table');

        Livewire::actingAs($this->me)
            ->test('invites.list')
            ->assertSee('Alice table')
            ->call('decline', $invite->id)
            ->assertDontSee('Alice table')
            ->assertDispatched('invites-changed');
    }

    public function test_cannot_answer_someone_elses_invite(): void
    {
        $invite = $this->inviteMe($this->alice, $this->ticTacToe, 'Alice table');

        Livewire::actingAs($this->bob)
            ->test('invites.list')
            ->call('accept', $invite->id)
            ->assertForbidden();
    }

    public function test_badge_counts_pending_invites(): void
    {
        Livewire::actingAs($this->me)->test('invites.badge')->assertSet('count', 0)->assertDontSeeHtml('invites-badge__count');

        $this->inviteMe($this->alice, $this->ticTacToe, 'One');
        $this->inviteMe($this->bob, $this->otherGame, 'Two');

        Livewire::actingAs($this->me)
            ->test('invites.badge')
            ->assertSeeHtml('invites-badge__count')
            ->assertSee('2');
    }

    public function test_badge_is_in_the_navbar_and_menu_links_to_the_page(): void
    {
        $this->actingAs($this->me)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('invites.index'));
    }
}
