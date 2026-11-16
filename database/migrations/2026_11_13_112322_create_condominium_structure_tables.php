<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('condominiums', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('document');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('condominium_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominium_id')->constrained('condominiums');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('role_id')->constrained();
            $table->string('status');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominium_id')->constrained('condominiums');
            $table->string('name');
            $table->string('code');
            $table->timestamps();
            $table->unique(['id', 'condominium_id']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condominium_id')->constrained('condominiums');
            $table->foreignId('block_id')->nullable();
            $table->string('number');
            $table->string('unit_type');
            $table->string('status');
            $table->timestamps();
            $table->unique(['id', 'condominium_id']);
            $table->foreign(['block_id', 'condominium_id'])
                ->references(['id', 'condominium_id'])->on('blocks');
        });

        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained();
            $table->string('document');
            $table->string('phone');
            $table->timestamps();
        });

        Schema::create('unit_residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained();
            $table->foreignId('resident_id')->constrained();
            $table->string('relationship_type');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_residents');
        Schema::dropIfExists('residents');
        Schema::dropIfExists('units');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('condominium_users');
        Schema::dropIfExists('condominiums');
        Schema::dropIfExists('roles');
    }
};
