<?php

use App\Rules\Cnpj;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $documents = [];
        foreach (DB::table('condominiums')->select('id', 'document', 'status')->orderBy('id')->cursor() as $condominium) {
            if (! in_array($condominium->status, ['active', 'inactive'], true)) {
                throw new RuntimeException('Regularize a situação do condomínio para active ou inactive antes de executar esta migration. Condomínio ID: '.$condominium->id);
            }
            $normalized = Cnpj::normalize($condominium->document);
            if ($normalized === null || $normalized !== $condominium->document || isset($documents[$normalized])) {
                throw new RuntimeException('Regularize os CNPJs inválidos, formatados ou duplicados dos condomínios antes de executar esta migration. Condomínio ID: '.$condominium->id);
            }
            $documents[$normalized] = true;
        }

        Schema::table('condominiums', function (Blueprint $table) {
            $table->unique('document');
        });
    }

    public function down(): void
    {
        Schema::table('condominiums', function (Blueprint $table) {
            $table->dropUnique(['document']);
        });
    }
};
