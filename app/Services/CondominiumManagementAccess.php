<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\User;
use App\Repositories\Contracts\CondominiumInterface;
use Illuminate\Database\Eloquent\Collection;

class CondominiumManagementAccess
{
    public function __construct(private CondominiumInterface $condominiums) {}

    public function availableTo(User $user): Collection
    {
        return $this->condominiums->activeForManagement()
            ->filter(fn (Condominium $condominium) => $this->canManage($user, $condominium))
            ->values();
    }

    public function canManage(User $user, Condominium $condominium): bool
    {
        if ($condominium->status !== 'active') {
            return false;
        }

        if ($user->can('manage-access')) {
            return true;
        }

        return $this->condominiums->hasManagementMembership($user, $condominium);
    }
}
