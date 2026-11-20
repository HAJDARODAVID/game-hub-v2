<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Database\Seeders\GameSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameLobbyTest extends TestCase
{
    use RefreshDatabase;

    public function test_lobby_shows_game(): void
    {
        $this->seed(GameSeeder::class);

        $this->actingAs(User::factory()->create())
            ->get('/games/tic-tac-toe')
            ->assertOk()
            ->assertSee('Tic Tac Toe')
            ->assertSee('2 players');
    }

    public function test_lobby_requires_login(): void
    {
        $this->seed(GameSeeder::class);

        $this->get('/games/tic-tac-toe')->assertRedirect('/login');
    }

    public function test_inactive_or_unknown_game_is_404(): void
    {
        Game::factory()->inactive()->create(['slug' => 'hidden-game']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/games/hidden-game')->assertNotFound();
        $this->actingAs($user)->get('/games/does-not-exist')->assertNotFound();
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(GameSeeder::class);
        $this->seed(GameSeeder::class);

        $this->assertSame(1, Game::where('slug', 'tic-tac-toe')->count());
    }
}
