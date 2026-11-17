<?php

namespace Tests\Feature;

use App\Models\FinancialPayment;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class FinancialPrecisionMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_precision_migration_preserves_payments_and_refuses_lossy_rollback(): void
    {
        $condominium = DB::table('condominiums')->insertGetId(['name' => 'First', 'document' => '1', 'status' => 'active']);
        $category = DB::table('financial_categories')->insertGetId(['condominium_id' => $condominium, 'name' => 'Receita', 'type' => 'income']);
        $transaction = DB::table('financial_transactions')->insertGetId([
            'condominium_id' => $condominium, 'category_id' => $category, 'amount' => '100000.12',
            'due_date' => '2026-01-01', 'direction' => 'income', 'status' => 'partial',
        ]);
        DB::table('financial_payments')->insert([
            'transaction_id' => $transaction, 'amount' => '10.05', 'paid_at' => '2026-01-01 12:00:00',
            'payment_method' => 'pix', 'status' => 'completed',
        ]);
        $migration = require database_path('migrations/2026_11_17_150000_expand_financial_amount_precision.php');
        $migration->down();
        $migration->up();

        $this->assertSame('100000.1200', FinancialTransaction::findOrFail($transaction)->amount);
        $this->assertSame('10.0500', FinancialPayment::where('transaction_id', $transaction)->firstOrFail()->amount);
        DB::table('financial_payments')->where('transaction_id', $transaction)->update(['amount' => '0.0001']);
        try {
            $migration->down();
            $this->fail('A reversão não deve descartar casas decimais.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Não é possível reverter', $exception->getMessage());
        } finally {
            DB::table('financial_payments')->where('transaction_id', $transaction)->update(['amount' => '10.05']);
        }
    }
}
