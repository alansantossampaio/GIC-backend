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

    /**
     * Cria um bloco em um condomínio específico
     * @param Condominium $condominium
     * @param array $data
     * @return Block
     */
    public function createBlock(Condominium $condominium, array $data): Block
    {
        return $this->structures->createBlock($condominium, $data);
    }
    /**
     * Cria uma unidade em um condomínio específico
     * @param Condominium $condominium
     * @param array $data
     * @return Unit
     */
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
    /**
     * Retorna os blocos de um condomínio específico
     * @param Condominium $condominium
     * @return Collection
     */
    public function getBlocks(Condominium $condominium): Collection
    {
        return $this->structures->getBlocks($condominium);
    }

    /**
     * Retorna as unidades de um condomínio específico
     * @param Condominium $condominium
     * @return Collection
     */
    public function getUnits(Condominium $condominium): Collection
    {
        return $this->structures->getUnits($condominium);
    }
}
