<?php

namespace Tests\Feature;

use App\Models\Condominium;
use App\Models\CondominiumUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccessGranted;
use App\Notifications\PasswordLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class IdentityAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->withHeader('Origin', 'http://localhost:3000');
    }

    public function test_only_the_initialized_administrator_can_manage_access(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->getJson('/api/admin/access')->assertForbidden();
        $this->postJson('/api/admin/roles', ['name' => 'Teste'])->assertForbidden();

        $user->forceFill(['is_system_admin' => true])->save();
        $this->getJson('/api/admin/access')->assertForbidden();
    }

    public function test_administrator_can_invite_and_manage_contextual_permissions(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['email' => User::SYSTEM_ADMIN_EMAIL]);
        $admin->forceFill(['is_system_admin' => true])->save();
        $this->actingAs($admin, 'web');
        $condominium = Condominium::create(['name' => 'A', 'document' => '1', 'status' => 'active']);
        $permission = Permission::create(['name' => 'occurrences.view']);
        $role = Role::create(['name' => 'Síndico', 'guard_name' => 'web']);

        $this->postJson('/api/admin/users/invite', ['name' => 'Pessoa', 'email' => 'person@example.com'])
            ->assertCreated();
        $user = User::where('email', 'person@example.com')->firstOrFail();
        Notification::assertSentTo($user, PasswordLink::class);
        $this->putJson("/api/admin/roles/{$role->id}/permissions", ['permission_ids' => [$permission->id]])->assertOk();
        $this->postJson('/api/admin/memberships', [
            'user_id' => $user->id, 'condominium_id' => $condominium->id, 'role_id' => $role->id,
        ])->assertCreated();
        Notification::assertSentTo($user, AccessGranted::class);
        $this->postJson('/api/admin/memberships', [
            'user_id' => $user->id, 'condominium_id' => $condominium->id, 'role_id' => $role->id,
        ])->assertUnprocessable();
        $this->putJson("/api/admin/roles/{$role->id}/permissions", ['permission_ids' => []])->assertOk();
        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_user_access_and_notifications_are_scoped_to_the_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $condominium = Condominium::create(['name' => 'A', 'document' => '1', 'status' => 'active']);
        $role = Role::create(['name' => 'Morador', 'guard_name' => 'web']);
        $role->permissions()->attach(Permission::create(['name' => 'occurrences.view']));
        CondominiumUser::create([
            'user_id' => $user->id, 'condominium_id' => $condominium->id, 'role_id' => $role->id,
            'status' => 'active', 'started_at' => now()->subDay(),
        ]);
        $user->notify(new AccessGranted('A', 'Morador'));
        $other->notify(new AccessGranted('B', 'Morador'));
        $otherNotification = $other->notifications()->firstOrFail();
        $this->actingAs($user, 'web');

        $this->getJson('/api/my-access')->assertOk()->assertJsonPath('0.role.permissions.0.name', 'occurrences.view');
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1);
        $this->postJson("/api/notifications/{$otherNotification->id}/read")->assertNotFound();
        $own = $user->notifications()->firstOrFail();
        $this->postJson("/api/notifications/{$own->id}/read")->assertOk();
        $this->assertNotNull($own->fresh()->read_at);
    }

    public function test_password_link_is_single_use_and_verifies_email(): void
    {
        $user = User::factory()->unverified()->create();
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'newpassword123', 'password_confirmation' => 'newpassword123'];

        $this->postJson('/api/reset-password', $data)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->postJson('/api/reset-password', $data)->assertUnprocessable();
    }

    public function test_invitation_contains_a_usable_one_time_password_link(): void
    {
        Notification::fake();
        config(['services.frontend_url' => 'http://localhost:3000']);
        $admin = User::factory()->create(['email' => User::SYSTEM_ADMIN_EMAIL]);
        $admin->forceFill(['is_system_admin' => true])->save();
        $this->actingAs($admin, 'web')->postJson('/api/admin/users/invite', [
            'name' => 'Convidado', 'email' => 'invite@example.com',
        ])->assertCreated();

        $invitee = User::where('email', 'invite@example.com')->firstOrFail();
        $url = null;
        Notification::assertSentTo($invitee, PasswordLink::class, function (PasswordLink $notification) use ($invitee, &$url): bool {
            $url = $notification->toMail($invitee)->actionUrl;

            return true;
        });
        $this->assertStringStartsWith('http://localhost:3000/auth/redefinir-senha?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/reset-password', [
            'email' => $query['email'], 'token' => $query['token'],
            'password' => 'newpassword123', 'password_confirmation' => 'newpassword123',
        ])->assertOk();
        $this->assertTrue($invitee->fresh()->hasVerifiedEmail());
    }

    public function test_signed_email_verification_requires_the_right_authenticated_user(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ], absolute: false);

        $this->actingAs($other, 'web')->getJson($url)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web')->getJson($url)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_existing_administrator_requires_explicit_promotion(): void
    {
        $user = User::factory()->create(['email' => User::SYSTEM_ADMIN_EMAIL]);
        $this->artisan('access:initialize-admin')->assertFailed();
        $this->assertFalse($user->fresh()->is_system_admin);
        $this->artisan('access:initialize-admin', ['--promote-existing' => true])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_system_admin);
    }

    public function test_administrator_initialization_reports_short_password_separately(): void
    {
        $this->artisan('access:initialize-admin')
            ->expectsQuestion('Senha do administrador (mínimo 12 caracteres)', 'short')
            ->expectsQuestion('Confirme a senha', 'short')
            ->expectsOutput('A senha deve ter pelo menos 12 caracteres.')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => User::SYSTEM_ADMIN_EMAIL]);
    }

    public function test_administrator_initialization_reports_mismatched_confirmation(): void
    {
        $this->artisan('access:initialize-admin')
            ->expectsQuestion('Senha do administrador (mínimo 12 caracteres)', 'long-password-1')
            ->expectsQuestion('Confirme a senha', 'long-password-2')
            ->expectsOutput('A confirmação da senha não corresponde à senha informada.')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => User::SYSTEM_ADMIN_EMAIL]);
    }

    public function test_administrator_initialization_accepts_a_confirmed_password(): void
    {
        $this->artisan('access:initialize-admin')
            ->expectsQuestion('Senha do administrador (mínimo 12 caracteres)', 'long-password-1')
            ->expectsQuestion('Confirme a senha', 'long-password-1')
            ->assertSuccessful();
        $this->assertTrue(User::where('email', User::SYSTEM_ADMIN_EMAIL)->firstOrFail()->is_system_admin);
    }
}
