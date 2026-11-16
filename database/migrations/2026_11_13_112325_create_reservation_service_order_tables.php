<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('common_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominium_id')->constrained('condominiums');
            $table->string('name');
            $table->integer('capacity');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('common_area_id')->constrained();
            $table->foreignId('resident_id')->constrained();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominium_id')->constrained('condominiums');
            $table->foreignId('company_condominium_id')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('description');
            $table->string('priority');
            $table->string('status');
            $table->timestamps();
            $table->foreign(['company_condominium_id', 'condominium_id'])
                ->references(['id', 'condominium_id'])->on('company_condominiums');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('common_areas');
    }
};
