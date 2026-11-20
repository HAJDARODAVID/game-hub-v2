<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_games_catalogue_and_gamertag(): void
    {
        $user = User::factory()->create(['username' => 'player_one']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('GAMES CATALOGUE')
            ->assertSee('SCOTLAND YARD')
            ->assertSee('player_one');
    }
}
