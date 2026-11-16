<?php

namespace App\Repositories\Contracts;

use App\Models\Condominium;
use App\Models\Occurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface OccurrenceInterface
{
    public function list(Condominium $condominium, ?int $reportedBy = null): Collection;

    public function find(Condominium $condominium, int $id, ?int $reportedBy = null, bool $lock = false): Occurrence;

    public function residentCondominiums(User $user): Collection;

    public function residentUnits(User $user, Condominium $condominium): Collection;

    public function hasCurrentMembership(User $user, Condominium $condominium): bool;

    public function residentHasUnit(int $residentId, int $unitId): bool;

    public function create(Condominium $condominium, array $data): Occurrence;

    public function update(Occurrence $occurrence, array $data): Occurrence;

    public function addHistory(Occurrence $occurrence, array $data): void;

    public function history(Occurrence $occurrence): Collection;
}
