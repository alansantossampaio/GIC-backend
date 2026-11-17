<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyCondominium;
use App\Models\Condominium;
use App\Models\CondominiumUser;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private function condominium(string $name, string $status = 'active'): Condominium
    {
        return Condominium::create(['name' => $name, 'document' => $name, 'status' => $status]);
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

    private function data(): array
    {
        return [
            'name' => 'Funcionário', 'cpf' => '123.456.789-01', 'type' => 'own',
            'position' => 'Porteiro', 'status' => 'active', 'email' => 'test@example.com',
            'phone' => '123456789', 'department' => 'Portaria', 'hire_date' => '2026-01-15',
        ];
    }

    public function test_manager_can_list_create_update_and_delete_employees(): void
    {
        $condominium = $this->condominium('A');
        $otherCondominium = $this->condominium('B');
        $syndic = $this->member($condominium, 'Síndico');
        $this->actingAs($syndic, 'web');
        $url = "/api/condominiums/{$condominium->id}/employees";

        $syndicFeatures = $this->getJson('/api/user/permissions')->assertOk()
            ->assertJsonPath('is_admin', true)->json('features');
        $this->app['auth']->forgetGuards();
        $admin = User::factory()->create(['email' => User::SYSTEM_ADMIN_EMAIL]);
        $admin->forceFill(['is_system_admin' => true])->save();
        $this->actingAs($admin, 'web');
        $this->assertSame($this->getJson('/api/user/permissions')->assertOk()->json('features'), $syndicFeatures);

        $this->app['auth']->forgetGuards();
        $this->actingAs($syndic, 'web');
        $this->getJson('/api/employee-condominiums')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $condominium->id);
        $this->getJson("/api/condominiums/{$otherCondominium->id}/employees")->assertForbidden();
        $id = $this->postJson($url, $this->data())->assertCreated()
            ->assertJsonPath('type', 'own')->assertJsonPath('hire_date', '2026-01-15')->json('id');
        $this->assertDatabaseHas('employees', ['id' => $id, 'employment_type' => 'direct']);
        $this->getJson($url)->assertOk()->assertJsonCount(1)->assertJsonPath('0.cpf', '123.456.789-01');
        $updated = array_merge($this->data(), ['type' => 'third-party', 'position' => 'Vigilante']);
        unset($updated['email'], $updated['phone'], $updated['department'], $updated['hire_date']);
        $this->putJson("$url/$id", $updated)->assertOk()->assertJsonPath('type', 'third-party')
            ->assertJsonPath('email', null)->assertJsonPath('position', 'Vigilante');
        $this->assertDatabaseHas('employees', ['id' => $id, 'employment_type' => 'contracted', 'email' => null]);
        $this->getJson("$url?type=own")->assertOk()->assertJsonCount(0);
        $this->getJson("$url?type=third-party")->assertOk()->assertJsonCount(1);
        $this->deleteJson("$url/$id")->assertOk();
        $this->assertDatabaseMissing('employees', ['id' => $id]);
    }

    public function test_access_is_scoped_to_active_condominiums_and_current_managers(): void
    {
        $a = $this->condominium('A');
        $b = $this->condominium('B');
        $inactive = $this->condominium('C', 'inactive');
        $manager = $this->member($a, 'Administrador');
        $foreignEmployee = Employee::create(['condominium_id' => $b->id, 'name' => 'Outro', 'employment_type' => 'direct', 'status' => 'active']);

        $this->getJson("/api/condominiums/{$a->id}/employees")->assertUnauthorized();
        $this->actingAs($manager, 'web');
        $this->getJson("/api/condominiums/{$b->id}/employees")->assertForbidden();
        $this->postJson("/api/condominiums/{$b->id}/employees", $this->data())->assertForbidden();
        $this->putJson("/api/condominiums/{$a->id}/employees/{$foreignEmployee->id}", $this->data())->assertNotFound();
        $this->deleteJson("/api/condominiums/{$a->id}/employees/{$foreignEmployee->id}")->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->member($a, 'Morador'), 'web')->getJson("/api/condominiums/{$a->id}/employees")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAs($manager, 'web');
        $manager->condominiumUsers()->firstOrFail()->update(['ended_at' => now()->subSecond()]);
        $this->getJson("/api/condominiums/{$a->id}/employees")->assertForbidden();

        $admin = User::factory()->create(['email' => User::SYSTEM_ADMIN_EMAIL]);
        $admin->forceFill(['is_system_admin' => true])->save();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin, 'web')->getJson('/api/employee-condominiums')->assertOk()->assertJsonCount(2);
        $this->getJson("/api/condominiums/{$inactive->id}/employees")->assertForbidden();
    }

    public function test_legacy_records_and_validation_are_handled_without_inventing_data(): void
    {
        $a = $this->condominium('A');
        $b = $this->condominium('B');
        $employee = Employee::create(['condominium_id' => $a->id, 'name' => 'Legado', 'employment_type' => 'direct', 'status' => 'active']);
        $company = Company::create(['name' => 'Empresa', 'document' => '123', 'status' => 'active']);
        CompanyCondominium::create(['company_id' => $company->id, 'condominium_id' => $b->id, 'contract_start' => '2026-01-01', 'status' => 'active']);
        $this->actingAs($this->member($a, 'Admin'), 'web');
        $url = "/api/condominiums/{$a->id}/employees";

        $this->getJson($url)->assertOk()->assertJsonPath('0.id', $employee->id)
            ->assertJsonPath('0.cpf', null)->assertJsonPath('0.position', null)->assertJsonPath('0.type', 'own');
        $this->postJson($url, array_merge($this->data(), ['cpf' => 'inválido']))->assertUnprocessable()->assertJsonValidationErrors('cpf');
        $this->postJson($url, array_merge($this->data(), ['company_id' => $company->id]))->assertUnprocessable()->assertJsonValidationErrors('company_id');
        CompanyCondominium::create(['company_id' => $company->id, 'condominium_id' => $a->id, 'contract_start' => '2026-01-01', 'status' => 'active']);
        $this->postJson($url, array_merge($this->data(), ['company_id' => $company->id]))->assertCreated()->assertJsonPath('company_id', $company->id);
    }
}
