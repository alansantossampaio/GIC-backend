<?php

namespace App\Services;

use App\Models\Condominium;
use App\Models\User;

class CondominiumManagementAccess
{
    public function canManage(User $user, Condominium $condominium): bool
    {
        if ($condominium->status !== 'active') {
            return false;
        }

        if ($user->can('manage-access')) {
            return true;
        }

        return $user->condominiumUsers()->current()
            ->where('condominium_id', $condominium->id)
            ->with('role')
            ->get()
            ->contains(fn ($membership) => in_array(mb_strtolower(trim($membership->role->name)), [
                'admin', 'administrador', 'síndico', 'sindico',
            ], true));
    }
}
