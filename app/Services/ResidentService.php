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

    public function availableUsers(User $actor, Condominium $condominium): Collection
    {
        return $this->residents->availableUsers($condominium, $actor->can('manage-access'));
    }

    public function createForCondominium(User $actor, Condominium $condominium, array $data): UnitResident
    {
        $user = $this->residents->findUser($data['user_id']);
        if (! $actor->can('manage-access') && ! $this->residents->hasCurrentMembership($user, $condominium)) {
            throw ValidationException::withMessages(['user_id' => ['O usuário não possui vínculo ativo com este condomínio.']]);
        }

        return $this->residents->loadLink($this->createResident($user, $data));
    }

    public function scopedLink(Condominium $condominium, UnitResident $link): UnitResident
    {
        abort_unless($this->residents->linkBelongsToCondominium($link, $condominium) && $link->status === 'active' && $link->end_date === null, 404);

        return $link;
    }

    public function showLink(Condominium $condominium, UnitResident $link): UnitResident
    {
        return $this->residents->loadLink($this->scopedLink($condominium, $link));
    }

    public function updateLink(UnitResident $link, array $data): UnitResident
    {
        $updated = $this->updateResident($this->residents->residentForLink($link), $link, $data);

        return $this->residents->loadLink($updated);
    }

    public function createResident(User $user, array $data): UnitResident
    {
        return DB::transaction(function () use ($user, $data) {
            $user = $this->residents->lockUser($user->id);

            $resident = $this->residents->residentExists($user->id)
                ? $this->residents->findResidentByUser($user->id)
                : $this->residents->createResident([
                    'user_id' => $user->id,
                    'document' => $data['document'],
                    'phone' => $data['phone'],
                ]);

            if ($resident->document !== $data['document'] || $resident->phone !== $data['phone']) {
                throw ValidationException::withMessages(['document' => ['Os dados não correspondem ao cadastro existente. Solicite a atualização ao administrador geral.']]);
            }

            if ($this->residents->hasActiveUnitLink($resident, $data['unit_id'])) {
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
            $resident = $this->residents->lockResident($resident->id);

            $otherCondominiums = $this->residents->hasOtherCondominiumLinks($resident, $link);

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
            $resident = $this->residents->lockResident($link->resident_id);

            if ($this->residents->hasOtherRecords($resident, $link)) {
                throw ValidationException::withMessages(['resident' => ['Não é possível excluir um morador com outros vínculos ou registros relacionados.']]);
            }

            $this->residents->deleteUnitResident($link);
            $this->residents->deleteResident($resident);
        });
    }
}
