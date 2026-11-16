<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\Resident;
use App\Models\UnitResident;
use App\Models\User;
use App\Repositories\Contracts\ResidentInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResidentService
{
    public function __construct(private ResidentInterface $residents) {}

    public function getResidents(Condominium $condominium): Collection
    {
        return $this->residents->getResidents($condominium);
    }

    public function createResident(User $user, array $data): UnitResident
    {
        return DB::transaction(function () use ($user, $data) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $resident = $this->residents->residentExists($user->id)
                ? Resident::where('user_id', $user->id)->firstOrFail()
                : $this->residents->createResident([
                    'user_id' => $user->id,
                    'document' => $data['document'],
                    'phone' => $data['phone'],
                ]);

            if ($resident->document !== $data['document'] || $resident->phone !== $data['phone']) {
                throw ValidationException::withMessages(['document' => ['Os dados não correspondem ao cadastro existente. Solicite a atualização ao administrador geral.']]);
            }

            if ($resident->unitResidents()->where('unit_id', $data['unit_id'])->where('status', 'active')->whereNull('end_date')->exists()) {
                throw ValidationException::withMessages(['unit_id' => ['O morador já está vinculado a esta unidade.']]);
            }

            return $this->residents->createUnitResident($resident, [
                'unit_id' => $data['unit_id'],
                'relationship_type' => $data['relationship_type'],
                'start_date' => $data['start_date'],
                'status' => 'active',
            ]);
        });
    }

    public function updateResident(Resident $resident, UnitResident $link, array $data): UnitResident
    {
        return DB::transaction(function () use ($resident, $link, $data) {
            $resident = Resident::whereKey($resident->id)->lockForUpdate()->firstOrFail();

            $otherCondominiums = $resident->unitResidents()
                ->whereHas('unit', fn ($query) => $query->where('condominium_id', '!=', $link->unit->condominium_id))
                ->exists();

            if ($otherCondominiums && ($resident->document !== $data['document'] || $resident->phone !== $data['phone'])) {
                throw ValidationException::withMessages(['document' => ['Dados compartilhados com outro condomínio só podem ser alterados pelo administrador geral.']]);
            }

            $this->residents->updateResident($resident, [
                'document' => $data['document'],
                'phone' => $data['phone'],
            ]);

            return $this->residents->updateUnitResident($link, [
                'relationship_type' => $data['relationship_type'],
                'start_date' => $data['start_date'],
            ]);
        });
    }

    public function deleteResident(UnitResident $link): void
    {
        DB::transaction(function () use ($link) {
            $resident = Resident::whereKey($link->resident_id)->lockForUpdate()->firstOrFail();

            if ($resident->unitResidents()->where('id', '!=', $link->id)->exists()
                || $resident->occurrences()->exists()
                || $resident->reservations()->exists()) {
                throw ValidationException::withMessages(['resident' => ['Não é possível excluir um morador com outros vínculos ou registros relacionados.']]);
            }

            $this->residents->deleteUnitResident($link);
            $this->residents->deleteResident($resident);
        });
    }
}
