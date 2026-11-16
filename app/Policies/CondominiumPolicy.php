<?php

namespace App\Policies;

use App\Models\Condominium;
use App\Models\User;
use App\Repositories\Contracts\CondominiumInterface;

class CondominiumPolicy
{
    public function __construct(private CondominiumInterface $condominiums) {}

    public function view(User $user, Condominium $condominium): bool
    {
        return $user->can('manage-access') || $this->condominiums->hasEditableAccess($user, $condominium);
    }

    public function update(User $user, Condominium $condominium): bool
    {
        return $this->view($user, $condominium);
    }

    public function create(User $user): bool
    {
        return $user->can('manage-access');
    }

    public function changeStatus(User $user, Condominium $condominium): bool
    {
        return $user->can('manage-access');
    }
}
