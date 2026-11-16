<?php

namespace App\Repositories\Contracts;

use App\Models\Condominium;
use App\Models\Resident;
use App\Models\UnitResident;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ResidentInterface
{
    public function getResidents(Condominium $condominium): Collection;

    public function availableUsers(Condominium $condominium, bool $isAdmin): Collection;

    public function findUser(int $id): User;

    public function lockUser(int $id): User;

    public function hasCurrentMembership(User $user, Condominium $condominium): bool;

    public function findResidentByUser(int $userId): Resident;

    public function lockResident(int $id): Resident;

    public function hasActiveUnitLink(Resident $resident, int $unitId): bool;

    public function hasOtherCondominiumLinks(Resident $resident, UnitResident $link): bool;

    public function hasOtherRecords(Resident $resident, UnitResident $link): bool;

    public function linkBelongsToCondominium(UnitResident $link, Condominium $condominium): bool;

    public function loadLink(UnitResident $link): UnitResident;

    public function residentForLink(UnitResident $link): Resident;

    public function createResident(array $data): Resident;

    public function createUnitResident(Resident $resident, array $data): UnitResident;

    public function updateResident(Resident $resident, array $data): Resident;

    public function updateUnitResident(UnitResident $link, array $data): UnitResident;

    public function deleteResident(Resident $resident): bool;

    public function deleteUnitResident(UnitResident $link): bool;

    public function residentExists(int $userId): bool;

    public function findUnitResidentForUpdate(int $id): UnitResident;
}
