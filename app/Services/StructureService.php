<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\Block;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StructureService
{
    public function createBlock(Condominium $condominium, array $data): Block
    {
        return $condominium->blocks()->create($data);
    }

    public function createUnit(Condominium $condominium, array $data): Unit
    {
        return DB::transaction(function () use ($condominium, $data) {
            Condominium::whereKey($condominium->id)->lockForUpdate()->firstOrFail();

            $blockId = $data['block_id'] ?? null;
            if ($condominium->units()->where('number', $data['number'])->where('block_id', $blockId)->exists()) {
                throw ValidationException::withMessages(['number' => ['Já existe uma unidade com este número neste bloco ou setor.']]);
            }

            return $condominium->units()->create($data + ['status' => 'active']);
        });
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
