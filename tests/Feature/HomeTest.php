<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_lists_active_games(): void
    {
        Game::factory()->create(['title' => 'Tic Tac Toe', 'slug' => 'tic-tac-toe']);
        Game::factory()->inactive()->create(['title' => 'Hidden Game', 'slug' => 'hidden-game']);

        $this->actingAs(User::factory()->create(['username' => 'player_one']))
            ->get('/')
            ->assertOk()
            ->assertSee('GAMES CATALOGUE')
            ->assertSee('TIC TAC TOE')
            ->assertSee(route('games.lobby', 'tic-tac-toe'))
            ->assertDontSee('HIDDEN GAME')
            ->assertSee('player_one');
    }

    public function test_catalogue_shows_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('No games available yet.');
    }
}
