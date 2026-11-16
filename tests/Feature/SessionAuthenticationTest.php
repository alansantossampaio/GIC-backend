<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_session_login_user_and_logout(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com', 'password' => 'secret123']);
        $this->withHeader('Origin', 'http://localhost:3000');

        $this->getJson('/api/user')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret123'])->assertOk();
        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/user')->assertOk()->assertJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'can_manage_access' => false,
        ]);
        $this->postJson('/api/logout')->assertOk();
        $this->assertGuest('web');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_invalid_credentials_do_not_authenticate(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com', 'password' => 'secret123']);
        $this->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_login_rejects_non_stateful_requests(): void
    {
        $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'secret123'])
            ->assertForbidden();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->withHeader('Origin', 'http://localhost:3000');

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/login', ['email' => 'user@example.com', 'password' => 'wrong'])
            ->assertTooManyRequests();
    }
}
