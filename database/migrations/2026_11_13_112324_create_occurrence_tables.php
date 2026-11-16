<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominium_id')->constrained('condominiums');
            $table->foreignId('unit_id')->nullable();
            $table->foreignId('resident_id')->nullable()->constrained();
            $table->foreignId('reported_by')->constrained('users');
            $table->string('description');
            $table->string('status');
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->foreign(['unit_id', 'condominium_id'])
                ->references(['id', 'condominium_id'])->on('units');
        });

        Schema::create('fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->unique()->constrained();
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('occurrence_id')->unique()->constrained();
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warnings');
        Schema::dropIfExists('fines');
        Schema::dropIfExists('occurrences');
    }
};
