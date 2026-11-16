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

class OccurrenceFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function condominium(string $name, string $status = 'active'): Condominium
    {
        return Condominium::create(['name' => $name, 'document' => $name, 'status' => $status]);
    }

    private function member(Condominium $condominium, string $roleName = 'Síndico'): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        CondominiumUser::create([
            'user_id' => $user->id, 'condominium_id' => $condominium->id, 'role_id' => $role->id,
            'status' => 'active', 'started_at' => now()->subDay(),
        ]);

        return $user;
    }

    private function unit(Condominium $condominium): Unit
    {
        return Unit::create(['condominium_id' => $condominium->id, 'number' => '1', 'unit_type' => 'apartment', 'status' => 'active']);
    }

    public function test_occurrence_flow_validates_resident_and_keeps_authenticated_reporter(): void
    {
        $condominium = $this->condominium('A');
        $unit = $this->unit($condominium);
        $otherUnit = $this->unit($condominium);
        $user = $this->member($condominium);
        $resident = Resident::create(['user_id' => User::factory()->create()->id, 'document' => '1', 'phone' => '1']);
        UnitResident::create(['unit_id' => $unit->id, 'resident_id' => $resident->id, 'relationship_type' => 'owner', 'start_date' => '2026-01-01', 'status' => 'active']);
        $this->actingAs($user, 'web');
        $url = "/api/condominiums/{$condominium->id}/occurrences";
        $data = ['type' => 'complaint', 'description' => 'Ruído', 'occurred_at' => '2026-11-16 10:00:00', 'unit_id' => $unit->id, 'resident_id' => $resident->id, 'reported_by' => 999, 'status' => 'closed'];

        $id = $this->postJson($url, $data)->assertCreated()->assertJsonPath('reported_by', $user->id)->assertJsonPath('status', 'open')->json('id');
        $this->getJson($url)->assertOk()->assertJsonCount(1);
        $this->putJson("$url/$id", array_merge($data, ['status' => 'in_progress']))->assertOk()->assertJsonPath('status', 'in_progress')->assertJsonPath('reported_by', $user->id);
        $this->postJson($url, array_merge($data, ['unit_id' => $otherUnit->id]))->assertUnprocessable()->assertJsonValidationErrors('resident_id');
        $this->postJson($url, array_merge($data, ['unit_id' => []]))->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->postJson($url, array_merge($data, ['type' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_payments_update_balance_without_overpayment_or_float_inputs(): void
    {
        $condominium = $this->condominium('A');
        $this->actingAs($this->member($condominium), 'web');
        $base = "/api/condominiums/{$condominium->id}";
        $category = $this->postJson("$base/financial-categories", ['name' => 'Taxa', 'type' => 'income'])->assertCreated()->json('id');
        $transaction = ['category_id' => $category, 'direction' => 'income', 'amount' => '100.10', 'due_date' => '2026-12-01'];
        $url = "$base/financial-transactions";
        $id = $this->postJson($url, $transaction)->assertCreated()->assertJsonPath('balance', '100.10')->assertJsonPath('status', 'open')->json('id');
        $payment = ['amount' => '40.05', 'paid_at' => '2026-11-16 12:00:00', 'payment_method' => 'pix'];

        $this->postJson("$url/$id/payments", $payment)->assertCreated()->assertJsonPath('status', 'completed');
        $this->getJson("$url/$id")->assertOk()->assertJsonPath('paid_amount', '40.05')->assertJsonPath('balance', '60.05')->assertJsonPath('status', 'partial');
        $this->postJson("$url/$id/payments", array_merge($payment, ['amount' => '60.06']))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson("$url/$id/payments", array_merge($payment, ['amount' => '0.00']))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson("$url/$id/payments", array_merge($payment, ['amount' => 1.01]))->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson("$url/$id/payments", array_merge($payment, ['amount' => '60.05']))->assertCreated();
        $this->getJson("$url/$id")->assertOk()->assertJsonPath('balance', '0.00')->assertJsonPath('status', 'paid');
        $this->getJson("$url/$id/payments")->assertOk()->assertJsonCount(2);
        $this->postJson("$url/$id/payments", $payment)->assertUnprocessable();
        $this->postJson($url, array_merge($transaction, ['direction' => 'expense']))->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->postJson($url, array_merge($transaction, ['direction' => ['income']]))->assertUnprocessable()->assertJsonValidationErrors('direction');
        $this->postJson($url, array_merge($transaction, ['amount' => '100.001']))->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_records_and_related_entities_cannot_cross_condominium_boundaries(): void
    {
        $a = $this->condominium('A');
        $b = $this->condominium('B');
        $unitB = $this->unit($b);
        $manager = $this->member($a);
        $residentB = Resident::create(['user_id' => User::factory()->create()->id, 'document' => '2', 'phone' => '2']);
        UnitResident::create(['unit_id' => $unitB->id, 'resident_id' => $residentB->id, 'relationship_type' => 'owner', 'start_date' => '2026-01-01', 'status' => 'active']);
        $this->actingAs($this->member($b), 'web');
        $baseB = "/api/condominiums/{$b->id}";
        $categoryB = $this->postJson("$baseB/financial-categories", ['name' => 'Receita', 'type' => 'income'])->json('id');
        $transactionB = $this->postJson("$baseB/financial-transactions", ['category_id' => $categoryB, 'direction' => 'income', 'amount' => '2.00', 'due_date' => '2026-12-01'])->json('id');
        $occurrenceB = $this->postJson("$baseB/occurrences", ['type' => 'complaint', 'description' => 'Teste', 'occurred_at' => '2026-11-16'])->json('id');
        $this->app['auth']->forgetGuards();
        $this->actingAs($manager, 'web');
        $baseA = "/api/condominiums/{$a->id}";

        $this->getJson("$baseB/financial-transactions")->assertForbidden();
        $this->getJson("$baseB/occurrences")->assertForbidden();
        $this->getJson("$baseA/financial-transactions/$transactionB")->assertNotFound();
        $this->postJson("$baseA/financial-transactions/$transactionB/payments", ['amount' => '1.00', 'paid_at' => '2026-11-16', 'payment_method' => 'pix'])->assertNotFound();
        $this->getJson("$baseA/occurrences/$occurrenceB")->assertNotFound();
        $this->putJson("$baseA/occurrences/$occurrenceB", [])->assertNotFound();
        $this->postJson("$baseA/financial-transactions", ['category_id' => $categoryB, 'direction' => 'income', 'amount' => '1.00', 'due_date' => '2026-12-01'])->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->postJson("$baseA/occurrences", ['type' => 'complaint', 'description' => 'Teste', 'occurred_at' => '2026-11-16', 'unit_id' => $unitB->id])->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->postJson("$baseA/occurrences", ['type' => 'complaint', 'description' => 'Teste', 'occurred_at' => '2026-11-16', 'resident_id' => $residentB->id])->assertUnprocessable()->assertJsonValidationErrors('resident_id');
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->member($a, 'Morador'), 'web')->getJson("$baseA/occurrences")->assertForbidden();
        $this->getJson("$baseA/financial-categories")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAs($manager, 'web');
        $manager->condominiumUsers()->firstOrFail()->update(['ended_at' => now()->subSecond()]);
        $this->getJson("$baseA/financial-categories")->assertForbidden();
    }

    public function test_resident_can_create_and_edit_only_own_occurrences_with_history(): void
    {
        $condominium = $this->condominium('A');
        $unit = $this->unit($condominium);
        $otherUnit = $this->unit($condominium);
        $user = $this->member($condominium, 'Morador');
        $resident = Resident::create(['user_id' => $user->id, 'document' => '1', 'phone' => '1']);
        UnitResident::create(['resident_id' => $resident->id, 'unit_id' => $unit->id, 'relationship_type' => 'owner', 'start_date' => now()->subDay()->toDateString(), 'status' => 'active']);
        $manager = $this->member($condominium);
        $other = Occurrence::create(['condominium_id' => $condominium->id, 'reported_by' => $manager->id, 'type' => 'complaint', 'description' => 'Privado', 'status' => 'open', 'occurred_at' => now()]);
        $this->actingAs($user, 'web');
        $base = "/api/condominiums/{$condominium->id}/occurrences";
        $data = ['type' => 'complaint', 'description' => 'Ruído', 'occurred_at' => '2026-11-16 10:00:00', 'unit_id' => $unit->id];

        $this->getJson('/api/occurrence-condominiums')->assertOk()->assertJsonCount(1);
        $this->getJson("$base/context")->assertOk()->assertJsonPath('can_manage', false)->assertJsonCount(1, 'units')->assertJsonPath('units.0.id', $unit->id);
        $id = $this->postJson($base, $data)->assertCreated()->assertJsonPath('reported_by', $user->id)->assertJsonPath('resident_id', null)->json('id');
        $this->getJson($base)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $id);
        $this->getJson("$base/{$other->id}")->assertNotFound();
        $this->getJson("$base/{$other->id}/history")->assertNotFound();
        $this->putJson("$base/{$other->id}", $data)->assertNotFound();
        $this->postJson($base, array_merge($data, ['unit_id' => $otherUnit->id]))->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->postJson($base, array_merge($data, ['resident_id' => $resident->id]))->assertUnprocessable()->assertJsonValidationErrors('resident_id');
        $this->putJson("$base/$id", array_merge($data, ['status' => 'closed']))->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->putJson("$base/$id", array_merge($data, ['description' => 'Ruído atualizado', 'status' => null, 'resident_id' => null]))->assertOk()->assertJsonPath('status', 'open');
        $this->getJson("$base/$id/history")->assertOk()->assertJsonCount(2)->assertJsonPath('0.action', 'created')
            ->assertJsonPath('1.action', 'updated')->assertJsonPath('1.user.id', $user->id)
            ->assertJsonPath('1.before.description', 'Ruído')->assertJsonPath('1.after.description', 'Ruído atualizado');

        $this->app['auth']->forgetGuards();
        $this->actingAs($manager, 'web');
        $this->putJson("$base/$id", array_merge($data, ['description' => 'Revisada', 'status' => 'closed', 'resident_id' => $resident->id]))->assertOk();
        $this->getJson("$base/$id/history")->assertOk()->assertJsonCount(3)->assertJsonPath('2.user.id', $manager->id)
            ->assertJsonPath('2.after.status', 'closed');
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');
        $this->putJson("$base/$id", array_merge($data, ['resident_id' => null, 'status' => null]))->assertOk()
            ->assertJsonPath('status', 'closed')->assertJsonPath('resident_id', $resident->id);
    }

    public function test_resident_access_requires_current_membership_and_current_unit_link(): void
    {
        $a = $this->condominium('A');
        $b = $this->condominium('B');
        $unit = $this->unit($a);
        $user = $this->member($a, 'Morador');
        $resident = Resident::create(['user_id' => $user->id, 'document' => '1', 'phone' => '1']);
        $link = UnitResident::create(['resident_id' => $resident->id, 'unit_id' => $unit->id, 'relationship_type' => 'owner', 'start_date' => now()->subDay()->toDateString(), 'status' => 'active']);
        $this->actingAs($user, 'web');
        $base = "/api/condominiums/{$a->id}/occurrences";
        $data = ['type' => 'complaint', 'description' => 'Teste', 'occurred_at' => '2026-11-16'];

        $this->getJson("/api/condominiums/{$b->id}/occurrences")->assertForbidden();
        $link->update(['start_date' => now()->addDay()->toDateString()]);
        $this->getJson($base)->assertForbidden();
        $this->postJson($base, $data)->assertForbidden();
        $this->getJson('/api/occurrence-condominiums')->assertOk()->assertJsonCount(0);
        $link->update(['start_date' => now()->subDays(2)->toDateString(), 'end_date' => now()->subDay()->toDateString()]);
        $this->getJson($base)->assertForbidden();
        $link->update(['end_date' => null]);
        $user->condominiumUsers()->firstOrFail()->update(['ended_at' => now()->subSecond()]);
        $this->getJson($base)->assertForbidden();
        $this->postJson($base, $data)->assertForbidden();
    }
}
