<?php

namespace App\Repositories\Core;

use App\Models\Block;
use App\Models\Condominium;
use App\Models\Unit;
use App\Repositories\Contracts\StructureInterface;
use Illuminate\Database\Eloquent\Collection;

class StructureRepository implements StructureInterface
{
    public function lockCondominium(Condominium $condominium): void
    {
        Condominium::whereKey($condominium->id)->lockForUpdate()->firstOrFail();
    }

    public function unitExists(Condominium $condominium, string $number, ?int $blockId): bool
    {
        return $condominium->units()->where('number', $number)->where('block_id', $blockId)->exists();
    }

    public function createBlock(Condominium $condominium, array $data): Block
    {
        return $condominium->blocks()->create($data);
    }

    public function createUnit(Condominium $condominium, array $data): Unit
    {
        return $condominium->units()->create($data);
    }

    public function getBlocks(Condominium $condominium): Collection
    {
        return $condominium->blocks()->orderBy('name')->get(['id', 'name', 'code']);
    }

    public function getUnits(Condominium $condominium): Collection
    {
        return $condominium->units()
            ->with('block:id,name')
            ->orderBy('number')
            ->get(['id', 'condominium_id', 'block_id', 'number', 'unit_type', 'status']);
    }
}
