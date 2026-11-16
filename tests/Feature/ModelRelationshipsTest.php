<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\CommonArea;
use App\Models\Company;
use App\Models\CompanyCondominium;
use App\Models\Condominium;
use App\Models\CondominiumUser;
use App\Models\Employee;
use App\Models\FinancialCategory;
use App\Models\FinancialPayment;
use App\Models\FinancialTransaction;
use App\Models\Fine;
use App\Models\Occurrence;
use App\Models\Reservation;
use App\Models\Resident;
use App\Models\Role;
use App\Models\ServiceOrder;
use App\Models\Unit;
use App\Models\UnitResident;
use App\Models\User;
use App\Models\Warning;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_persist_and_resolve_their_relationships(): void
    {
        $user = User::create(['name' => 'User', 'email' => 'user@example.com', 'password' => 'secret']);
        $role = Role::create(['name' => 'Resident', 'guard_name' => 'web']);
        $condominium = Condominium::create(['name' => 'Condominium', 'document' => '1', 'status' => 'active']);
        $access = CondominiumUser::create([
            'condominium_id' => $condominium->id, 'user_id' => $user->id, 'role_id' => $role->id,
            'status' => 'active', 'started_at' => '2026-01-01 10:00:00',
        ]);
        $block = Block::create(['condominium_id' => $condominium->id, 'name' => 'A', 'code' => 'A']);
        $unit = Unit::create([
            'condominium_id' => $condominium->id, 'block_id' => $block->id,
            'number' => '1', 'unit_type' => 'apartment', 'status' => 'active',
        ]);
        $resident = Resident::create(['user_id' => $user->id, 'document' => '1', 'phone' => '1']);
        $occupancy = UnitResident::create([
            'unit_id' => $unit->id, 'resident_id' => $resident->id, 'relationship_type' => 'owner',
            'start_date' => '2026-01-01', 'status' => 'active',
        ]);
        $company = Company::create(['name' => 'Company', 'document' => '1', 'status' => 'active']);
        $contract = CompanyCondominium::create([
            'company_id' => $company->id, 'condominium_id' => $condominium->id,
            'contract_start' => '2026-01-01', 'status' => 'active',
        ]);
        $employee = Employee::create([
            'user_id' => $user->id, 'condominium_id' => $condominium->id,
            'name' => 'Employee', 'employment_type' => 'direct', 'status' => 'active',
        ]);
        $companyEmployee = Employee::create([
            'company_id' => $company->id, 'name' => 'Contractor', 'employment_type' => 'contracted', 'status' => 'active',
        ]);
        $occurrence = Occurrence::create([
            'condominium_id' => $condominium->id, 'unit_id' => $unit->id, 'resident_id' => $resident->id,
            'reported_by' => $user->id, 'description' => 'Occurrence', 'status' => 'open',
            'occurred_at' => '2026-01-01 10:00:00',
        ]);
        $fine = Fine::create(['occurrence_id' => $occurrence->id, 'amount' => '1234.50', 'due_date' => '2026-02-01', 'status' => 'open']);
        $warning = Warning::create(['occurrence_id' => $occurrence->id, 'status' => 'open']);
        $area = CommonArea::create(['condominium_id' => $condominium->id, 'name' => 'Hall', 'capacity' => 20, 'status' => 'active']);
        $reservation = Reservation::create([
            'common_area_id' => $area->id, 'resident_id' => $resident->id, 'start_at' => '2026-02-01 10:00:00',
            'end_at' => '2026-02-01 12:00:00', 'status' => 'active',
        ]);
        $order = ServiceOrder::create([
            'condominium_id' => $condominium->id, 'company_condominium_id' => $contract->id,
            'requested_by' => $user->id, 'description' => 'Service', 'priority' => 'normal', 'status' => 'open',
        ]);
        $category = FinancialCategory::create(['condominium_id' => $condominium->id, 'name' => 'Fee', 'type' => 'income']);
        $transaction = FinancialTransaction::create([
            'condominium_id' => $condominium->id, 'category_id' => $category->id, 'unit_id' => $unit->id,
            'amount' => '1234.50', 'due_date' => '2026-02-01', 'direction' => 'income', 'status' => 'open',
        ]);
        $payment = FinancialPayment::create([
            'transaction_id' => $transaction->id, 'amount' => '100.00', 'paid_at' => '2026-02-01 10:00:00',
            'payment_method' => 'transfer', 'status' => 'paid',
        ]);

        $singleRelations = [
            [$user, 'resident', $resident], [$user, 'employee', $employee],
            [$access, 'condominium', $condominium], [$access, 'user', $user], [$access, 'role', $role],
            [$block, 'condominium', $condominium], [$unit, 'condominium', $condominium], [$unit, 'block', $block],
            [$resident, 'user', $user], [$occupancy, 'unit', $unit], [$occupancy, 'resident', $resident],
            [$contract, 'company', $company], [$contract, 'condominium', $condominium],
            [$employee, 'user', $user], [$employee, 'condominium', $condominium], [$companyEmployee, 'company', $company],
            [$occurrence, 'condominium', $condominium], [$occurrence, 'unit', $unit], [$occurrence, 'resident', $resident],
            [$occurrence, 'reporter', $user], [$occurrence, 'fine', $fine], [$occurrence, 'warning', $warning],
            [$fine, 'occurrence', $occurrence], [$warning, 'occurrence', $occurrence], [$area, 'condominium', $condominium],
            [$reservation, 'commonArea', $area], [$reservation, 'resident', $resident],
            [$order, 'condominium', $condominium], [$order, 'companyCondominium', $contract], [$order, 'requester', $user],
            [$category, 'condominium', $condominium], [$transaction, 'condominium', $condominium],
            [$transaction, 'category', $category], [$transaction, 'unit', $unit], [$payment, 'transaction', $transaction],
        ];
        foreach ($singleRelations as [$model, $relation, $expected]) {
            $model->load($relation);
            $this->assertTrue($expected->is($model->$relation), get_class($model)."::{$relation}");
        }

        $collectionRelations = [
            [$user, 'condominiumUsers', $access], [$user, 'reportedOccurrences', $occurrence], [$user, 'requestedServiceOrders', $order],
            [$role, 'condominiumUsers', $access], [$condominium, 'condominiumUsers', $access],
            [$condominium, 'blocks', $block], [$condominium, 'units', $unit], [$condominium, 'companyCondominiums', $contract],
            [$condominium, 'employees', $employee], [$condominium, 'occurrences', $occurrence], [$condominium, 'commonAreas', $area],
            [$condominium, 'serviceOrders', $order], [$condominium, 'financialCategories', $category],
            [$condominium, 'financialTransactions', $transaction], [$block, 'units', $unit],
            [$unit, 'unitResidents', $occupancy], [$unit, 'occurrences', $occurrence], [$unit, 'financialTransactions', $transaction],
            [$resident, 'unitResidents', $occupancy], [$resident, 'occurrences', $occurrence], [$resident, 'reservations', $reservation],
            [$company, 'companyCondominiums', $contract], [$company, 'employees', $companyEmployee],
            [$contract, 'serviceOrders', $order], [$area, 'reservations', $reservation],
            [$category, 'financialTransactions', $transaction], [$transaction, 'payments', $payment],
        ];
        foreach ($collectionRelations as [$model, $relation, $expected]) {
            $model->load($relation);
            $this->assertTrue($model->$relation->contains($expected), get_class($model)."::{$relation}");
        }

        $this->assertNull($employee->company);
        $this->assertNull($companyEmployee->user);
        $this->assertNull($companyEmployee->condominium);
        $this->assertNull($access->ended_at);
        $this->assertInstanceOf(CarbonInterface::class, $access->started_at);
        $this->assertInstanceOf(CarbonInterface::class, $occupancy->start_date);
        $this->assertInstanceOf(CarbonInterface::class, $contract->contract_start);
        $this->assertInstanceOf(CarbonInterface::class, $occurrence->occurred_at);
        $this->assertInstanceOf(CarbonInterface::class, $reservation->end_at);
        $this->assertInstanceOf(CarbonInterface::class, $payment->paid_at);
        $this->assertSame('1234.50', $fine->fresh()->amount);
        $this->assertSame('1234.50', $transaction->fresh()->amount);
        $this->assertSame('100.00', $payment->fresh()->amount);
    }
}
