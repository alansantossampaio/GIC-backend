<?php

namespace App\Repositories\Core;

use App\Models\Occurrence;
use App\Models\Unit;
use App\Repositories\Contracts\DashboardInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DashboardRepository implements DashboardInterface
{
    public function statistics(array $condominiumIds): array
    {
        $units = Unit::query()->whereIntegerInRaw('condominium_id', $condominiumIds)
            ->where('status', 'active')->selectRaw('condominium_id, count(*) as total')
            ->groupBy('condominium_id')->pluck('total', 'condominium_id');

        $residents = DB::table('unit_residents')
            ->join('units', 'units.id', '=', 'unit_residents.unit_id')
            ->whereIntegerInRaw('units.condominium_id', $condominiumIds)
            ->where('units.status', 'active')->where('unit_residents.status', 'active')
            ->whereDate('unit_residents.start_date', '<=', today())
            ->where(fn (Builder $query) => $query->whereNull('unit_residents.end_date')
                ->orWhereDate('unit_residents.end_date', '>=', today()));

        $residentsByCondominium = (clone $residents)
            ->selectRaw('units.condominium_id, count(distinct unit_residents.resident_id) as total')
            ->groupBy('units.condominium_id')->pluck('total', 'condominium_id');

        return [
            'units' => $units,
            'residents' => $residentsByCondominium,
            'residents_count' => $residents->distinct()->count('unit_residents.resident_id'),
            'active_occurrences_count' => Occurrence::whereIntegerInRaw('condominium_id', $condominiumIds)
                ->whereIn('status', ['open', 'in_progress'])->count(),
            'recent_occurrences' => Occurrence::whereIntegerInRaw('condominium_id', $condominiumIds)
                ->orderByDesc('occurred_at')->orderByDesc('id')->limit(5)
                ->get(['id', 'condominium_id', 'description', 'status', 'occurred_at']),
        ];
    }
}
