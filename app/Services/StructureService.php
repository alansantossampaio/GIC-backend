<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\Block;
use App\Models\Unit;
use App\Repositories\Contracts\StructureInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StructureService
{
    public function __construct(private StructureInterface $structures) {}

    public function createBlock(Condominium $condominium, array $data): Block
    {
        return $this->structures->createBlock($condominium, $data);
    }

    public function createUnit(Condominium $condominium, array $data): Unit
    {
        return DB::transaction(function () use ($condominium, $data) {
            $this->structures->lockCondominium($condominium);

            $blockId = $data['block_id'] ?? null;
            if ($this->structures->unitExists($condominium, $data['number'], $blockId)) {
                throw ValidationException::withMessages(['number' => ['Já existe uma unidade com este número neste bloco ou setor.']]);
            }

            return $this->structures->createUnit($condominium, $data + ['status' => 'active']);
        });
    }

    public function getBlocks(Condominium $condominium): Collection
    {
        return $this->structures->getBlocks($condominium);
    }

    public function getUnits(Condominium $condominium): Collection
    {
        return $this->structures->getUnits($condominium);
    }
}
