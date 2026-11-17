<?php

use Brick\Math\BigDecimal;
use Brick\Math\Exception\RoundingNecessaryException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['financial_transactions', 'financial_payments'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                if (DB::getDriverName() === 'sqlite') {
                    $blueprint->string('amount', 19)->change();
                } else {
                    $blueprint->decimal('amount', 17, 4)->change();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['financial_transactions', 'financial_payments'] as $table) {
            foreach (DB::table($table)->select('amount')->cursor() as $row) {
                try {
                    BigDecimal::of((string) $row->amount)->toScale(2);
                } catch (RoundingNecessaryException) {
                    throw new RuntimeException('Não é possível reverter a precisão financeira enquanto existirem valores com mais de duas casas decimais.');
                }
            }
        }

        foreach (['financial_transactions', 'financial_payments'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('amount', 15, 2)->change();
            });
        }
    }
};
