<?php

namespace App\Repositories\Core;

use App\Models\Condominium;
use App\Models\Resident;
use App\Models\UnitResident;
use App\Models\User;
use App\Repositories\Contracts\ResidentInterface;
use Illuminate\Database\Eloquent\Collection;

class ResidentRepository implements ResidentInterface
{
    public function availableUsers(Condominium $condominium, bool $isAdmin): Collection
    {
        $users = User::query()->orderBy('name');
        if (! $isAdmin) {
            $users->whereHas('condominiumUsers', fn ($query) => $query->current()->where('condominium_id', $condominium->id));
        }

        return $users->get(['id', 'name', 'email']);
    }

    public function findUser(int $id): User
    {
        return User::whereKey($id)->firstOrFail();
    }

    public function lockUser(int $id): User
    {
        return User::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function hasCurrentMembership(User $user, Condominium $condominium): bool
    {
        return $user->condominiumUsers()->current()->where('condominium_id', $condominium->id)->exists();
    }

    public function findResidentByUser(int $userId): Resident
    {
        return Resident::where('user_id', $userId)->firstOrFail();
    }

    public function lockResident(int $id): Resident
    {
        return Resident::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function hasActiveUnitLink(Resident $resident, int $unitId): bool
    {
        return $resident->unitResidents()->where('unit_id', $unitId)->where('status', 'active')->whereNull('end_date')->exists();
    }

    public function hasOtherCondominiumLinks(Resident $resident, UnitResident $link): bool
    {
        return $resident->unitResidents()
            ->whereHas('unit', fn ($query) => $query->where('condominium_id', '!=', $link->unit->condominium_id))
            ->exists();
    }

    public function hasOtherRecords(Resident $resident, UnitResident $link): bool
    {
        return $resident->unitResidents()->where('id', '!=', $link->id)->exists()
            || $resident->occurrences()->exists()
            || $resident->reservations()->exists();
    }

    public function linkBelongsToCondominium(UnitResident $link, Condominium $condominium): bool
    {
        return $link->unit()->where('condominium_id', $condominium->id)->exists();
    }

    public function loadLink(UnitResident $link): UnitResident
    {
        return $link->load(['resident.user:id,name,email', 'unit.block:id,name']);
    }

    public function residentForLink(UnitResident $link): Resident
    {
        return $link->resident;
    }

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
