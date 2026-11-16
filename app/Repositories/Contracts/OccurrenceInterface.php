<?php

namespace App\Repositories\Contracts;

use App\Models\Condominium;
use App\Models\Occurrence;
use Illuminate\Database\Eloquent\Collection;

interface OccurrenceInterface
{
    public function list(Condominium $condominium): Collection;

    public function find(Condominium $condominium, int $id): Occurrence;

    public function create(Condominium $condominium, array $data): Occurrence;

    public function update(Occurrence $occurrence, array $data): Occurrence;
}
