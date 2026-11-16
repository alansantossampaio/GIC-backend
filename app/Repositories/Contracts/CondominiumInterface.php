<?php

namespace App\Repositories\Contracts;

use App\Models\Condominium;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface CondominiumInterface
{
    /** @return Collection<int, Condominium> */
    public function availableTo(User $user): Collection;

    public function hasEditableAccess(User $user, Condominium $condominium): bool;

    public function documentExists(string $document, ?int $exceptId = null): bool;

    public function findForUpdate(int $id): Condominium;

    public function create(array $data): Condominium;

    public function update(Condominium $condominium, array $data): Condominium;
}
