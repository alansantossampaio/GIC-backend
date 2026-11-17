<?php

namespace Tests\Feature;

use App\Models\Condominium;
use App\Models\CondominiumUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CondominiumTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $admin = User::factory()->create(['email' => User::SYSTEM_ADMIN_EMAIL]);
        $admin->forceFill(['is_system_admin' => true])->save();

        return $admin;
    }

    public function test_admin_manages_condominiums_and_validates_normalized_cnpjs(): void
    {
        $this->actingAs($this->administrator(), 'web');
        $data = ['name' => 'Condomínio A', 'document' => '04.252.011/0001-10'];
        $id = $this->postJson('/api/condominiums', $data)
            ->assertCreated()->assertJsonPath('document', '04252011000110')->assertJsonPath('status', 'active')
            ->assertJsonStructure(['unity_qtd'])->assertJsonPath('unity_qtd', null)->json('id');
        $this->postJson('/api/condominiums', $data)->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->postJson('/api/condominiums', ['name' => 'Inválido', 'document' => '00.000.000/0000-00'])
            ->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->postJson('/api/condominiums', ['name' => 'Inválido', 'document' => '04x252011000110'])
            ->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->putJson("/api/condominiums/$id", ['name' => 'Atualizado', 'document' => '00.000.000/e08g-12'])
            ->assertOk()->assertJsonPath('document', '00000000E08G12');
        $this->getJson("/api/condominiums/$id")->assertOk()->assertJsonPath('name', 'Atualizado');
        $this->patchJson("/api/condominiums/$id/status", ['status' => 'inactive'])->assertOk();
        $this->patchJson("/api/condominiums/$id/status", ['status' => 'active'])->assertOk();
        $this->assertDatabaseHas('condominiums', ['id' => $id, 'status' => 'active']);
    }

    public function test_contextual_access_requires_permission_current_membership_and_active_condominium(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Síndico', 'guard_name' => 'web']);
        $permission = Permission::create(['name' => 'condominios.editar']);
        $role->permissions()->attach($permission);
        $a = Condominium::create(['name' => 'A', 'document' => '04252011000110', 'status' => 'active']);
        $b = Condominium::create(['name' => 'B', 'document' => '11222333000181', 'status' => 'active']);
        $membership = CondominiumUser::create([
            'user_id' => $user->id, 'condominium_id' => $a->id, 'role_id' => $role->id,
            'status' => 'active', 'started_at' => now()->subDay(),
        ]);
        $this->actingAs($user, 'web');
        $this->getJson('/api/condominiums')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $a->id);
        $this->getJson("/api/condominiums/{$b->id}")->assertForbidden();
        $this->putJson("/api/condominiums/{$b->id}", ['name' => 'B', 'document' => $b->document])->assertForbidden();
        $this->postJson('/api/condominiums', ['name' => 'C', 'document' => $a->document])->assertForbidden();
        $this->patchJson("/api/condominiums/{$a->id}/status", ['status' => 'inactive'])->assertForbidden();
        $this->putJson("/api/condominiums/{$a->id}", ['name' => 'Novo A', 'document' => $a->document])->assertOk();

        $role->permissions()->detach($permission);
        $this->getJson('/api/condominiums')->assertJsonCount(0);
        $this->getJson("/api/condominiums/{$a->id}")->assertForbidden();
        $role->permissions()->attach($permission);
        $membership->update(['ended_at' => now()->subSecond()]);
        $this->getJson('/api/condominiums')->assertJsonCount(0);
        $membership->update(['ended_at' => null, 'started_at' => now()->addDay()]);
        $this->getJson('/api/condominiums')->assertJsonCount(0);
        $membership->update(['started_at' => now()->subDay()]);
        $a->update(['status' => 'inactive']);
        $this->getJson('/api/condominiums')->assertJsonCount(0);
        $this->getJson('/api/my-access')->assertJsonCount(0);
        $this->putJson("/api/condominiums/{$a->id}", ['name' => 'A', 'document' => $a->document])->assertForbidden();
        $a->update(['status' => 'active']);
        $this->getJson('/api/my-access')->assertJsonCount(1);
        $this->getJson('/api/condominiums')->assertJsonCount(1);
    }

    public function test_unit_quantity_is_validated_saved_and_returned_in_condominium_responses(): void
    {
        $this->actingAs($this->administrator(), 'web');
        $data = ['name' => 'Condomínio A', 'document' => '04.252.011/0001-10', 'unity_qtd' => 100];
        $id = $this->postJson('/api/condominiums', $data)
            ->assertCreated()->assertJsonPath('unity_qtd', 100)->json('id');
        $this->getJson('/api/condominiums')->assertOk()->assertJsonPath('0.unity_qtd', 100);
        $this->getJson("/api/condominiums/$id")->assertOk()->assertJsonPath('unity_qtd', 100);
        $this->putJson("/api/condominiums/$id", array_merge($data, ['unity_qtd' => 0]))
            ->assertOk()->assertJsonPath('unity_qtd', 0);
        $this->putJson("/api/condominiums/$id", ['name' => $data['name'], 'document' => $data['document']])
            ->assertOk()->assertJsonPath('unity_qtd', 0);
        $this->putJson("/api/condominiums/$id", array_merge($data, ['unity_qtd' => null]))
            ->assertOk()->assertJsonPath('unity_qtd', null);
        $this->getJson('/api/condominiums')->assertOk()->assertJsonPath('0.unity_qtd', null);
        foreach ([-1, 1.5, 2147483648, 'abc'] as $quantity) {
            $this->putJson("/api/condominiums/$id", array_merge($data, ['unity_qtd' => $quantity]))
                ->assertUnprocessable()->assertJsonValidationErrors('unity_qtd');
        }
    }

    public function test_admin_cannot_grant_access_to_inactive_condominium(): void
    {
        $this->actingAs($this->administrator(), 'web');
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Morador', 'guard_name' => 'web']);
        $condominium = Condominium::create(['name' => 'Inativo', 'document' => '04252011000110', 'status' => 'inactive']);
        $this->postJson('/api/admin/memberships', [
            'user_id' => $user->id, 'role_id' => $role->id, 'condominium_id' => $condominium->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('condominium_id');
        $this->assertDatabaseMissing('condominium_users', ['user_id' => $user->id]);
    }

    public function test_migration_rejects_noncanonical_or_duplicate_existing_documents(): void
    {
        $migration = require database_path('migrations/2026_11_13_160000_unique_condominium_document.php');
        $migration->down();

        try {
            foreach ([['04.252.011/0001-10'], ['04252011000110', '04252011000110']] as $documents) {
                foreach ($documents as $document) {
                    Condominium::create(['name' => 'Legado', 'document' => $document, 'status' => 'active']);
                }
                try {
                    $migration->up();
                    $this->fail('A migration deveria recusar documentos legados inválidos.');
                } catch (\RuntimeException $exception) {
                    $this->assertStringContainsString('Regularize os CNPJs', $exception->getMessage());
                }
                Condominium::query()->delete();
            }
            Condominium::create(['name' => 'Legado', 'document' => '04252011000110', 'status' => 'unknown']);
            try {
                $migration->up();
                $this->fail('A migration deveria recusar situações desconhecidas.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Regularize a situação', $exception->getMessage());
            }
        } finally {
            Condominium::query()->delete();
            $migration->up();
        }
    }
}
