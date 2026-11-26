<?php

namespace Tests\Feature;

use App\Actions\Matches\MakeMove;
use App\Actions\Matches\StartMatch;
use App\Actions\Rooms\CreateRoom;
use App\Actions\Rooms\InvitePlayer;
use App\Actions\Rooms\RespondToInvite;
use App\Models\Game;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActiveMatchButtonTest extends TestCase
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
        $room = app(CreateRoom::class)->handle($this->host, $this->game, 'Game night');
        app(RespondToInvite::class)->accept(app(InvitePlayer::class)->handle($room, $this->host, 'friend'), $this->friend);

        return $room->fresh();
    }

    public function test_hidden_when_not_in_a_running_match(): void
    {
        $this->fullRoom();   // waiting room, no match yet

        Livewire::actingAs($this->host)
            ->test('matches.active-button')
            ->assertDontSeeHtml('active-match-btn');
    }

    public function test_links_both_players_back_to_their_running_match(): void
    {
        $room = $this->fullRoom();
        app(StartMatch::class)->handle($room, $this->host);

        foreach ([$this->host, $this->friend] as $player) {
            Livewire::actingAs($player)
                ->test('matches.active-button')
                ->assertSeeHtml('active-match-btn')
                ->assertSeeHtml(route('rooms.show', $room));
        }
    }

    public function test_hidden_for_others_and_after_the_match_ends(): void
    {
        $room = $this->fullRoom();
        $match = app(StartMatch::class)->handle($room, $this->host);

        Livewire::actingAs(User::factory()->create())
            ->test('matches.active-button')
            ->assertDontSeeHtml('active-match-btn');

        foreach ([0, 3, 1, 4, 2] as $i => $cell) {
            $match = app(MakeMove::class)->handle($match, $i % 2 === 0 ? $this->host : $this->friend, ['cell' => $cell]);
        }

        Livewire::actingAs($this->host)
            ->test('matches.active-button')
            ->assertDontSeeHtml('active-match-btn');
    }

    public function test_footer_invites_badge_hides_during_a_match_and_returns_after(): void
    {
        $room = $this->fullRoom();

        Livewire::actingAs($this->host)
            ->test('invites.badge', ['hideDuringMatch' => true])
            ->assertSeeHtml('invites-badge');

        $match = app(StartMatch::class)->handle($room, $this->host);

        Livewire::actingAs($this->host)
            ->test('invites.badge', ['hideDuringMatch' => true])
            ->assertDontSeeHtml('invites-badge');

        // The navbar copy (no hideDuringMatch) is not affected
        Livewire::actingAs($this->host)
            ->test('invites.badge')
            ->assertSeeHtml('invites-badge');

        foreach ([0, 3, 1, 4, 2] as $i => $cell) {
            $match = app(MakeMove::class)->handle($match, $i % 2 === 0 ? $this->host : $this->friend, ['cell' => $cell]);
        }

        Livewire::actingAs($this->host)
            ->test('invites.badge', ['hideDuringMatch' => true])
            ->assertSeeHtml('invites-badge');
    }

    public function test_shown_in_the_footer_on_other_pages_but_not_on_the_match_page_itself(): void
    {
        $room = $this->fullRoom();
        app(StartMatch::class)->handle($room, $this->host);

        $this->actingAs($this->host)->get(route('home'))
            ->assertOk()
            ->assertSee('active-match-btn', false);

        $this->actingAs($this->host)->get(route('rooms.show', $room))
            ->assertOk()
            ->assertDontSee('active-match-btn', false);
    }
}
