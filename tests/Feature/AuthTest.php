<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="username"', false);
    }

    public function test_user_can_log_in_with_gamertag(): void
    {
        $user = User::factory()->create(['username' => 'player_one']);

        $this->post('/login', ['username' => 'player_one', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_log_in_with_email(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['username' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_wrong_password_shows_error_on_gamertag_field(): void
    {
        User::factory()->create(['username' => 'player_one']);

        $this->post('/login', ['username' => 'player_one', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_user_can_register_with_gamertag(): void
    {
        $this->post('/register', [
            'first_name' => 'Player',
            'last_name' => 'One',
            'username' => 'player_one',
            'email' => 'player@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['username' => 'player_one', 'first_name' => 'Player', 'last_name' => 'One']);
    }

    public function test_gamertag_must_be_unique(): void
    {
        User::factory()->create(['username' => 'player_one']);

        $this->post('/register', [
            'first_name' => 'Someone',
            'last_name' => 'Else',
            'username' => 'player_one',
            'email' => 'other@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('username');
    }

    public function test_logged_in_user_sees_home(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk();
    }
    public function test_last_name_is_required(): void
    {
        $this->post("/register", [
            "first_name" => "Player",
            "username" => "player_one",
            "email" => "player@example.com",
            "password" => "secret-password",
            "password_confirmation" => "secret-password",
        ])->assertSessionHasErrors("last_name");

        $this->assertGuest();
    }
}
