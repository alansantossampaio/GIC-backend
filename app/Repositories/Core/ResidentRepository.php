<?php

namespace App\Repositories\Core;

use App\Models\Condominium;
use App\Models\Resident;
use App\Models\UnitResident;
use App\Repositories\Contracts\ResidentInterface;
use Illuminate\Database\Eloquent\Collection;

class ResidentRepository implements ResidentInterface
{
    public function getResidents(Condominium $condominium): Collection
    {
        return UnitResident::whereHas('unit', fn ($query) => $query->where('condominium_id', $condominium->id))
            ->where('status', 'active')->whereNull('end_date')
            ->with(['resident.user:id,name,email', 'unit.block:id,name'])
            ->orderByDesc('id')->get();
    }

    public function createResident(array $data): Resident
    {
        return Resident::create($data);
    }

    public function createUnitResident(Resident $resident, array $data): UnitResident
    {
        return $resident->unitResidents()->create($data);
    }

    public function updateResident(Resident $resident, array $data): Resident
    {
        $resident->update($data);

        return $resident;
    }

    public function updateUnitResident(UnitResident $link, array $data): UnitResident
    {
        $link->update($data);

        return $link;
    }

    public function deleteResident(Resident $resident): bool
    {
        return (bool) $resident->delete();
    }

    public function deleteUnitResident(UnitResident $link): bool
    {
        return (bool) $link->delete();
    }

    public function residentExists(int $userId): bool
    {
        return Resident::where('user_id', $userId)->exists();
    }

    public function findUnitResidentForUpdate(int $id): UnitResident
    {
        return UnitResident::whereKey($id)->lockForUpdate()->firstOrFail();
    }
}
