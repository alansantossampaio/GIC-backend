<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\Occurrence;
use App\Models\User;
use App\Repositories\Contracts\DashboardInterface;

class DashboardService
{
    public function __construct(
        private CondominiumManagementAccess $access,
        private DashboardInterface $dashboard,
    ) {}

    public function summary(User $user): array
    {
        $condominiums = $this->access->availableTo($user);
        $statistics = $this->dashboard->statistics($condominiums->modelKeys());
        $names = $condominiums->pluck('name', 'id');

        return [
            'condominiums_count' => $condominiums->count(),
            'residents_count' => $statistics['residents_count'],
            'active_occurrences_count' => $statistics['active_occurrences_count'],
            'condominiums' => $condominiums->map(fn (Condominium $condominium) => [
                'id' => $condominium->id,
                'name' => $condominium->name,
                'units_count' => (int) ($statistics['units'][$condominium->id] ?? 0),
                'residents_count' => (int) ($statistics['residents'][$condominium->id] ?? 0),
            ])->values(),
            'recent_occurrences' => $statistics['recent_occurrences']->map(fn (Occurrence $occurrence) => [
                'id' => $occurrence->id,
                'condominium_name' => $names[$occurrence->condominium_id],
                'description' => $occurrence->description,
                'status' => $occurrence->status,
                'occurred_at' => $occurrence->occurred_at->toJSON(),
            ])->values(),
        ];
    }
}
