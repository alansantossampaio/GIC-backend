<?php

namespace App\Repositories\Core;

use App\Models\Condominium;
use App\Models\Occurrence;
use App\Repositories\Contracts\OccurrenceInterface;
use Illuminate\Database\Eloquent\Collection;

class OccurrenceRepository implements OccurrenceInterface
{
    public function list(Condominium $condominium): Collection
    {
        return $condominium->occurrences()->orderByDesc('occurred_at')->orderByDesc('id')->get();
    }

    public function find(Condominium $condominium, int $id): Occurrence
    {
        return $condominium->occurrences()->findOrFail($id);
    }

    public function create(Condominium $condominium, array $data): Occurrence
    {
        return $condominium->occurrences()->create($data);
    }

    public function update(Occurrence $occurrence, array $data): Occurrence
    {
        $occurrence->update($data);

        return $occurrence;
    }
}
