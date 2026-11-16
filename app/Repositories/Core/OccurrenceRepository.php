<?php

namespace App\Repositories\Core;

use App\Models\Condominium;
use App\Models\Occurrence;
use App\Models\Unit;
use App\Models\UnitResident;
use App\Models\User;
use App\Repositories\Contracts\OccurrenceInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OccurrenceRepository implements OccurrenceInterface
{
    public function list(Condominium $condominium, ?int $reportedBy = null): Collection
    {
        return $condominium->occurrences()->when($reportedBy !== null, fn ($query) => $query->where('reported_by', $reportedBy))
            ->orderByDesc('occurred_at')->orderByDesc('id')->get();
    }

    public function find(Condominium $condominium, int $id, ?int $reportedBy = null, bool $lock = false): Occurrence
    {
        $query = $condominium->occurrences()->whereKey($id)
            ->when($reportedBy !== null, fn ($query) => $query->where('reported_by', $reportedBy));
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    public function residentCondominiums(User $user): Collection
    {
        return Condominium::where('status', 'active')
            ->whereHas('condominiumUsers', fn (Builder $query) => $query->current()->where('user_id', $user->id))
            ->whereHas('units', fn (Builder $query) => $query->where('status', 'active')
                ->whereHas('unitResidents', fn (Builder $links) => $this->currentResidentLink($links, $user)))
            ->orderBy('name')->get(['id', 'name', 'status']);
    }

    public function residentUnits(User $user, Condominium $condominium): Collection
    {
        return Unit::where('condominium_id', $condominium->id)->where('status', 'active')
            ->whereHas('unitResidents', fn (Builder $query) => $this->currentResidentLink($query, $user))
            ->with('block:id,name')->orderBy('number')->get(['id', 'number', 'block_id', 'unit_type', 'status']);
    }

    public function hasCurrentMembership(User $user, Condominium $condominium): bool
    {
        return $user->condominiumUsers()->current()->where('condominium_id', $condominium->id)->exists();
    }

    public function residentHasUnit(int $residentId, int $unitId): bool
    {
        return UnitResident::where('resident_id', $residentId)->where('unit_id', $unitId)->exists();
    }

    private function currentResidentLink(Builder $query, User $user): void
    {
        $query->where('status', 'active')->whereDate('start_date', '<=', today())
            ->where(fn (Builder $dates) => $dates->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->whereHas('resident', fn (Builder $resident) => $resident->where('user_id', $user->id));
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

    public function addHistory(Occurrence $occurrence, array $data): void
    {
        $occurrence->history()->create($data);
    }

    public function history(Occurrence $occurrence): Collection
    {
        return $occurrence->history()->with('user:id,name')->orderBy('id')->get();
    }
}
