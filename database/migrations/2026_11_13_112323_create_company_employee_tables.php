<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('document')->unique();
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('company_condominiums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained();
            $table->foreignId('condominium_id')->constrained('condominiums');
            $table->date('contract_start');
            $table->date('contract_end')->nullable();
            $table->string('status');
            $table->string('contract_reference')->nullable();
            $table->timestamps();
            $table->unique(['id', 'condominium_id']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained();
            $table->foreignId('company_id')->nullable()->constrained();
            $table->foreignId('condominium_id')->nullable()->constrained('condominiums');
            $table->string('name');
            $table->string('employment_type');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('company_condominiums');
        Schema::dropIfExists('companies');
    }
};
