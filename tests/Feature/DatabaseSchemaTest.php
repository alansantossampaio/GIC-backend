<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_erd_tables_are_created_with_their_columns(): void
    {
        $tables = [
            'users' => ['name', 'email', 'password', 'email_verified_at'],
            'roles' => ['name', 'guard_name'],
            'condominiums' => ['name', 'document', 'status'],
            'condominium_users' => ['condominium_id', 'user_id', 'role_id', 'status', 'started_at', 'ended_at'],
            'blocks' => ['condominium_id', 'name', 'code'],
            'units' => ['condominium_id', 'block_id', 'number', 'unit_type', 'status'],
            'residents' => ['user_id', 'document', 'phone'],
            'unit_residents' => ['unit_id', 'resident_id', 'relationship_type', 'start_date', 'end_date', 'status'],
            'companies' => ['name', 'document', 'status'],
            'company_condominiums' => ['company_id', 'condominium_id', 'contract_start', 'contract_end', 'status', 'contract_reference'],
            'employees' => ['user_id', 'company_id', 'condominium_id', 'name', 'employment_type', 'status'],
            'occurrences' => ['condominium_id', 'unit_id', 'resident_id', 'reported_by', 'description', 'status', 'occurred_at'],
            'fines' => ['occurrence_id', 'amount', 'due_date', 'status'],
            'warnings' => ['occurrence_id', 'status'],
            'common_areas' => ['condominium_id', 'name', 'capacity', 'status'],
            'reservations' => ['common_area_id', 'resident_id', 'start_at', 'end_at', 'status'],
            'service_orders' => ['condominium_id', 'company_condominium_id', 'requested_by', 'description', 'priority', 'status'],
            'financial_categories' => ['condominium_id', 'name', 'type'],
            'financial_transactions' => ['condominium_id', 'category_id', 'unit_id', 'amount', 'due_date', 'direction', 'status'],
            'financial_payments' => ['transaction_id', 'amount', 'paid_at', 'payment_method', 'status'],
        ];

        foreach ($tables as $table => $columns) {
            $this->assertTrue(Schema::hasColumns($table, array_merge(['id'], $columns)), $table);
        }
    }

    public function test_related_records_cannot_reference_another_condominium(): void
    {
        $first = DB::table('condominiums')->insertGetId(['name' => 'First', 'document' => '1', 'status' => 'active']);
        $second = DB::table('condominiums')->insertGetId(['name' => 'Second', 'document' => '2', 'status' => 'active']);
        $user = DB::table('users')->insertGetId(['name' => 'User', 'email' => 'user@example.com', 'password' => 'hash']);
        $block = DB::table('blocks')->insertGetId(['condominium_id' => $first, 'name' => 'A', 'code' => 'A']);
        $unit = DB::table('units')->insertGetId([
            'condominium_id' => $first, 'block_id' => $block, 'number' => '1', 'unit_type' => 'apartment', 'status' => 'active',
        ]);
        $company = DB::table('companies')->insertGetId(['name' => 'Company', 'document' => '1', 'status' => 'active']);
        $contract = DB::table('company_condominiums')->insertGetId([
            'company_id' => $company, 'condominium_id' => $first, 'contract_start' => '2026-01-01', 'status' => 'active',
        ]);
        $category = DB::table('financial_categories')->insertGetId([
            'condominium_id' => $first, 'name' => 'Category', 'type' => 'income',
        ]);

        $invalidRows = [
            ['units', ['condominium_id' => $second, 'block_id' => $block, 'number' => '2', 'unit_type' => 'apartment', 'status' => 'active']],
            ['occurrences', ['condominium_id' => $second, 'unit_id' => $unit, 'reported_by' => $user, 'description' => 'Test', 'status' => 'open', 'occurred_at' => '2026-01-01 10:00:00']],
            ['service_orders', ['condominium_id' => $second, 'company_condominium_id' => $contract, 'requested_by' => $user, 'description' => 'Test', 'priority' => 'normal', 'status' => 'open']],
            ['financial_transactions', ['condominium_id' => $second, 'category_id' => $category, 'amount' => '1.00', 'due_date' => '2026-01-01', 'direction' => 'income', 'status' => 'open']],
            ['financial_transactions', ['condominium_id' => $first, 'category_id' => $category, 'unit_id' => DB::table('units')->insertGetId(['condominium_id' => $second, 'number' => '3', 'unit_type' => 'house', 'status' => 'active']), 'amount' => '1.00', 'due_date' => '2026-01-01', 'direction' => 'income', 'status' => 'open']],
        ];

        foreach ($invalidRows as [$table, $values]) {
            try {
                DB::transaction(fn () => DB::table($table)->insert($values));
                $this->fail("Cross-condominium reference accepted in {$table}");
            } catch (QueryException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_resident_requires_a_unique_user(): void
    {
        $user = DB::table('users')->insertGetId(['name' => 'User', 'email' => 'resident@example.com', 'password' => 'hash']);
        DB::table('residents')->insert(['user_id' => $user, 'document' => '1', 'phone' => '1']);

        $this->expectException(QueryException::class);
        DB::table('residents')->insert(['user_id' => $user, 'document' => '2', 'phone' => '2']);
    }

    public function test_optional_relationships_and_same_condominium_references_are_accepted(): void
    {
        $condominium = DB::table('condominiums')->insertGetId(['name' => 'First', 'document' => '1', 'status' => 'active']);
        $user = DB::table('users')->insertGetId(['name' => 'User', 'email' => 'user@example.com', 'password' => 'hash']);
        $unit = DB::table('units')->insertGetId([
            'condominium_id' => $condominium, 'number' => '1', 'unit_type' => 'house', 'status' => 'active',
        ]);
        $category = DB::table('financial_categories')->insertGetId([
            'condominium_id' => $condominium, 'name' => 'Category', 'type' => 'income',
        ]);

        DB::table('occurrences')->insert([
            'condominium_id' => $condominium, 'unit_id' => $unit, 'reported_by' => $user,
            'description' => 'Test', 'status' => 'open', 'occurred_at' => '2026-01-01 10:00:00',
        ]);
        DB::table('service_orders')->insert([
            'condominium_id' => $condominium, 'requested_by' => $user,
            'description' => 'Test', 'priority' => 'normal', 'status' => 'open',
        ]);
        DB::table('financial_transactions')->insert([
            'condominium_id' => $condominium, 'category_id' => $category, 'unit_id' => $unit,
            'amount' => '1.00', 'due_date' => '2026-01-01', 'direction' => 'income', 'status' => 'open',
        ]);

        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('service_orders', 1);
        $this->assertDatabaseCount('occurrences', 1);
    }

    public function test_resident_cannot_reference_a_missing_user(): void
    {
        $this->expectException(QueryException::class);
        DB::table('residents')->insert(['user_id' => 999, 'document' => '1', 'phone' => '1']);
    }
}
