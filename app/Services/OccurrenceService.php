<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\Occurrence;
use App\Models\User;
use App\Repositories\Contracts\OccurrenceInterface;
use Illuminate\Database\Eloquent\Collection;

class OccurrenceService
{
    public function __construct(private OccurrenceInterface $occurrences) {}

    public function list(Condominium $condominium): Collection
    {
        return $this->occurrences->list($condominium);
    }

    public function find(Condominium $condominium, int $id): Occurrence
    {
        return $this->occurrences->find($condominium, $id);
    }

    public function create(Condominium $condominium, User $user, array $data): Occurrence
    {
        $data['reported_by'] = $user->id;
        $data['status'] = 'open';

        return $this->occurrences->create($condominium, $data);
    }

    public function update(Condominium $condominium, int $id, array $data): Occurrence
    {
        return $this->occurrences->update($this->find($condominium, $id), $data);
    }
}
