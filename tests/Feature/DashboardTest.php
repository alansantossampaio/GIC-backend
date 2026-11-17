<?php

namespace Tests\Feature;

use App\Models\Condominium;
use App\Models\CondominiumUser;
use App\Models\Occurrence;
use App\Models\Resident;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitResident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_current_data_without_crossing_management_access(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(12, 0));
        $manager = User::factory()->create();
        $role = Role::create(['name' => 'Síndico', 'guard_name' => 'web']);
        $a = Condominium::create(['name' => 'A', 'document' => 'A', 'status' => 'active']);
        $b = Condominium::create(['name' => 'B', 'document' => 'B', 'status' => 'active']);
        $other = Condominium::create(['name' => 'C', 'document' => 'C', 'status' => 'active']);
        $inactive = Condominium::create(['name' => 'D', 'document' => 'D', 'status' => 'inactive']);
        foreach ([$a, $b, $inactive] as $condominium) {
            CondominiumUser::create([
                'user_id' => $manager->id, 'condominium_id' => $condominium->id, 'role_id' => $role->id,
                'status' => 'active', 'started_at' => now()->subDay(),
            ]);
        }

        $unitA = $this->unit($a, '1');
        $unitA2 = $this->unit($a, '2');
        $unitB = $this->unit($b, '1');
        $unitOther = $this->unit($other, '1');
        $unitInactive = $this->unit($a, '3', 'inactive');
        $shared = $this->resident();
        $this->link($shared, $unitA);
        $this->link($shared, $unitA2);
        $this->link($shared, $unitB);
        $this->link($this->resident(), $unitA, ['start_date' => today()->toDateString(), 'end_date' => today()->toDateString()]);
        $this->link($this->resident(), $unitA, ['start_date' => today()->addDay()->toDateString()]);
        $this->link($this->resident(), $unitA, ['end_date' => today()->subDay()->toDateString()]);
        $this->link($this->resident(), $unitA, ['status' => 'inactive']);
        $this->link($this->resident(), $unitInactive);
        $this->link($this->resident(), $unitOther);

        foreach (['open', 'in_progress', 'closed', 'open', 'in_progress', 'open'] as $index => $status) {
            $this->occurrence($a, $manager, $status, $index);
        }
        $this->occurrence($b, $manager, 'open', 6);
        $this->occurrence($other, $manager, 'open', 7);
        $this->occurrence($inactive, $manager, 'open', 8);

        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->actingAs($manager, 'web');
        $response = $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('condominiums_count', 2)
            ->assertJsonPath('residents_count', 2)
            ->assertJsonPath('active_occurrences_count', 6)
            ->assertJsonCount(2, 'condominiums')
            ->assertJsonPath('condominiums.0.name', 'A')
            ->assertJsonPath('condominiums.0.units_count', 2)
            ->assertJsonPath('condominiums.0.residents_count', 2)
            ->assertJsonPath('condominiums.1.name', 'B')
            ->assertJsonPath('condominiums.1.residents_count', 1)
            ->assertJsonCount(5, 'recent_occurrences')
            ->assertJsonPath('recent_occurrences.0.condominium_name', 'B')
            ->assertJsonPath('recent_occurrences.0.description', 'Ocorrência 6');
        foreach ($response->json('recent_occurrences') as $occurrence) {
            $this->assertContains($occurrence['condominium_name'], ['A', 'B']);
        }

        $membershipB = $manager->condominiumUsers()->where('condominium_id', $b->id)->firstOrFail();
        $membershipB->update(['ended_at' => now()->subSecond()]);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('condominiums_count', 1)
            ->assertJsonPath('active_occurrences_count', 5)->assertJsonCount(1, 'condominiums');

        $this->app['auth']->forgetGuards();
        $admin = User::factory()->create(['email' => User::SYSTEM_ADMIN_EMAIL]);
        $admin->forceFill(['is_system_admin' => true])->save();
        $this->actingAs($admin, 'web')->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('condominiums_count', 3)->assertJsonPath('residents_count', 3)
            ->assertJsonPath('active_occurrences_count', 7);
    }

    public function test_dashboard_does_not_expose_management_data_to_residents(): void
    {
        $condominium = Condominium::create(['name' => 'A', 'document' => 'A', 'status' => 'active']);
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Morador', 'guard_name' => 'web']);
        CondominiumUser::create([
            'user_id' => $user->id, 'condominium_id' => $condominium->id, 'role_id' => $role->id,
            'status' => 'active', 'started_at' => now()->subDay(),
        ]);
        $resident = Resident::create(['user_id' => $user->id, 'document' => '1', 'phone' => '1']);
        $this->link($resident, $this->unit($condominium, '1'));
        $this->occurrence($condominium, $user, 'open', 0);

        $this->actingAs($user, 'web')->getJson('/api/dashboard')->assertOk()->assertExactJson([
            'condominiums_count' => 0, 'residents_count' => 0, 'active_occurrences_count' => 0,
            'condominiums' => [], 'recent_occurrences' => [],
        ]);
    }

    private function unit(Condominium $condominium, string $number, string $status = 'active'): Unit
    {
        return Unit::create(['condominium_id' => $condominium->id, 'number' => $number, 'unit_type' => 'apartment', 'status' => $status]);
    }

    private function resident(): Resident
    {
        return Resident::create(['user_id' => User::factory()->create()->id, 'document' => '1', 'phone' => '1']);
    }

    private function link(Resident $resident, Unit $unit, array $attributes = []): void
    {
        UnitResident::create(array_merge([
            'resident_id' => $resident->id, 'unit_id' => $unit->id, 'relationship_type' => 'owner',
            'start_date' => today()->subDay()->toDateString(), 'status' => 'active',
        ], $attributes));
    }

    private function occurrence(Condominium $condominium, User $reporter, string $status, int $index): void
    {
        Occurrence::create([
            'condominium_id' => $condominium->id, 'reported_by' => $reporter->id, 'type' => 'complaint',
            'description' => "Ocorrência $index", 'status' => $status, 'occurred_at' => now()->addMinutes($index),
        ]);
    }
}
