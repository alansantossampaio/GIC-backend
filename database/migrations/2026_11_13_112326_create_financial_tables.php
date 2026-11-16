<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominium_id')->constrained('condominiums');
            $table->string('name');
            $table->string('type');
            $table->timestamps();
            $table->unique(['id', 'condominium_id']);
        });

        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominium_id')->constrained('condominiums');
            $table->foreignId('category_id');
            $table->foreignId('unit_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->string('direction');
            $table->string('status');
            $table->timestamps();
            $table->foreign(['category_id', 'condominium_id'])
                ->references(['id', 'condominium_id'])->on('financial_categories');
            $table->foreign(['unit_id', 'condominium_id'])
                ->references(['id', 'condominium_id'])->on('units');
        });

        Schema::create('financial_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('financial_transactions');
            $table->decimal('amount', 15, 2);
            $table->dateTime('paid_at');
            $table->string('payment_method');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_payments');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('financial_categories');
    }
};
