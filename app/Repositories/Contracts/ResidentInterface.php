<?php

namespace App\Repositories\Contracts;

use App\Models\Condominium;
use App\Models\Resident;
use App\Models\UnitResident;
use Illuminate\Database\Eloquent\Collection;

interface ResidentInterface
{
    public function getResidents(Condominium $condominium): Collection;

    public function createResident(array $data): Resident;

    public function createUnitResident(Resident $resident, array $data): UnitResident;

    public function updateResident(Resident $resident, array $data): Resident;

    public function updateUnitResident(UnitResident $link, array $data): UnitResident;

    public function deleteResident(Resident $resident): bool;

    public function deleteUnitResident(UnitResident $link): bool;

    public function residentExists(int $userId): bool;

    public function findUnitResidentForUpdate(int $id): UnitResident;
}
