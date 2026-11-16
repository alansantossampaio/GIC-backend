<?php

namespace Tests\Feature;

use App\Models\Condominium;
use App\Models\CondominiumUser;
use App\Models\Resident;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\Contracts\CondominiumInterface;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_lists_use_the_condominium_repository(): void
    {
        $admin = User::factory()->create(['email' => User::SYSTEM_ADMIN_EMAIL]);
        $admin->forceFill(['is_system_admin' => true])->save();
        $condominium = new Condominium(['name' => 'A', 'status' => 'active']);
        $condominium->id = 42;
        $repository = $this->mock(CondominiumInterface::class);
        $repository->shouldReceive('activeForManagement')->twice()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([$condominium]));

        $this->actingAs($admin, 'web')->getJson('/api/resident-condominiums')->assertOk()->assertJsonPath('0.id', 42);
        $this->getJson('/api/employee-condominiums')->assertOk()->assertJsonPath('0.id', 42);
    }

    public function test_user_service_resolves_its_repository_contract(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(app(UserService::class)->getAllUsers()->contains('id', $user->id));
    }

    private function condominium(string $name): Condominium
    {
        return Condominium::create(['name' => $name, 'document' => $name, 'status' => 'active']);
    }

    private function member(Condominium $condominium, string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        CondominiumUser::create([
            'user_id' => $user->id, 'condominium_id' => $condominium->id, 'role_id' => $role->id,
            'status' => 'active', 'started_at' => now()->subDay(),
        ]);

        return $user;
    }

    public function test_local_manager_can_create_update_and_delete_a_resident_without_deleting_the_user(): void
    {
        $condominium = $this->condominium('A');
        $manager = $this->member($condominium, 'Síndico');
        $user = $this->member($condominium, 'Morador');
        $this->actingAs($manager, 'web');
        $block = $this->postJson("/api/condominiums/{$condominium->id}/blocks", ['name' => 'Torre A', 'code' => 'A'])->assertCreated()->json('id');
        $unit = $this->postJson("/api/condominiums/{$condominium->id}/units", ['number' => '101', 'unit_type' => 'Apartamento', 'block_id' => $block])->assertCreated()->json('id');
        $this->postJson("/api/condominiums/{$condominium->id}/units", ['number' => '101', 'unit_type' => 'Apartamento', 'block_id' => $block])->assertUnprocessable();
        $data = ['user_id' => $user->id, 'unit_id' => $unit, 'document' => '123', 'phone' => '555', 'relationship_type' => 'Inquilino', 'start_date' => '2026-01-01'];
        $id = $this->postJson("/api/condominiums/{$condominium->id}/residents", $data)->assertCreated()->json('id');
        $this->postJson("/api/condominiums/{$condominium->id}/residents", $data)->assertUnprocessable();
        $this->getJson("/api/condominiums/{$condominium->id}/residents/$id")->assertOk()->assertJsonPath('resident.user.id', $user->id);
        $this->getJson("/api/condominiums/{$condominium->id}/residents")->assertOk()->assertJsonCount(1, 'residents');
        $this->putJson("/api/condominiums/{$condominium->id}/residents/$id", [
            'document' => '123', 'phone' => '777', 'relationship_type' => 'Proprietário', 'start_date' => '2026-02-01',
        ])->assertOk()->assertJsonPath('resident.phone', '777');
        $this->deleteJson("/api/condominiums/{$condominium->id}/residents/$id")->assertOk();
        $this->assertDatabaseMissing('residents', ['user_id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_authorization_and_foreign_units_and_users_are_scoped(): void
    {
        $a = $this->condominium('A');
        $b = $this->condominium('B');
        $manager = $this->member($a, 'Admin');
        $foreign = $this->member($b, 'Morador');
        $unit = Unit::create(['condominium_id' => $b->id, 'number' => '1', 'unit_type' => 'Casa', 'status' => 'active']);
        $this->actingAs($manager, 'web');
        $this->getJson('/api/resident-condominiums')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $a->id);
        $this->getJson("/api/condominiums/{$b->id}/residents")->assertForbidden();
        $this->getJson("/api/condominiums/{$a->id}/residents")->assertOk()->assertJsonCount(1, 'users');
        $this->postJson("/api/condominiums/{$a->id}/residents", [
            'user_id' => $foreign->id, 'unit_id' => $unit->id, 'document' => '1', 'phone' => '1', 'relationship_type' => 'Morador', 'start_date' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $localUnit = Unit::create(['condominium_id' => $a->id, 'number' => '2', 'unit_type' => 'Casa', 'status' => 'active']);
        $this->postJson("/api/condominiums/{$a->id}/residents", [
            'user_id' => $foreign->id, 'unit_id' => $localUnit->id, 'document' => '1', 'phone' => '1', 'relationship_type' => 'Morador', 'start_date' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->actingAs($foreign, 'web')->getJson("/api/condominiums/{$b->id}/residents")->assertForbidden();
        $manager->condominiumUsers()->firstOrFail()->update(['ended_at' => now()->subSecond()]);
        $this->actingAs($manager, 'web')->getJson("/api/condominiums/{$a->id}/residents")->assertForbidden();
    }

    public function test_shared_resident_cannot_be_deleted_or_have_shared_data_changed_by_a_local_manager(): void
    {
        $a = $this->condominium('A');
        $b = $this->condominium('B');
        $manager = $this->member($a, 'Administrador');
        $user = $this->member($a, 'Morador');
        $resident = Resident::create(['user_id' => $user->id, 'document' => '123', 'phone' => '555']);
        $unitA = Unit::create(['condominium_id' => $a->id, 'number' => '1', 'unit_type' => 'Casa', 'status' => 'active']);
        $unitB = Unit::create(['condominium_id' => $b->id, 'number' => '1', 'unit_type' => 'Casa', 'status' => 'active']);
        $link = $resident->unitResidents()->create(['unit_id' => $unitA->id, 'relationship_type' => 'Morador', 'start_date' => '2026-01-01', 'status' => 'active']);
        $resident->unitResidents()->create(['unit_id' => $unitB->id, 'relationship_type' => 'Morador', 'start_date' => '2026-01-01', 'status' => 'active']);
        $this->actingAs($manager, 'web');
        $this->putJson("/api/condominiums/{$a->id}/residents/{$link->id}", [
            'document' => '123', 'phone' => '999', 'relationship_type' => 'Morador', 'start_date' => '2026-01-01',
        ])->assertUnprocessable();
        $this->deleteJson("/api/condominiums/{$a->id}/residents/{$link->id}")->assertUnprocessable();
        $this->assertDatabaseHas('residents', ['id' => $resident->id, 'phone' => '555']);
    }
}
