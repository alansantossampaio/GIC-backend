<?php

namespace App\Repositories\Contracts;

use App\Models\Block;
use App\Models\Condominium;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;

interface StructureInterface
{
    public function lockCondominium(Condominium $condominium): void;

    public function unitExists(Condominium $condominium, string $number, ?int $blockId): bool;

    public function createBlock(Condominium $condominium, array $data): Block;

    public function createUnit(Condominium $condominium, array $data): Unit;

    public function getBlocks(Condominium $condominium): Collection;

    public function getUnits(Condominium $condominium): Collection;
}
