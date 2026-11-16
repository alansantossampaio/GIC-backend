<?php

namespace App\Repositories\Core;

use App\Models\Condominium;
use App\Models\User;
use App\Repositories\Contracts\CondominiumInterface;
use Illuminate\Database\Eloquent\Collection;

class CondominiumRepository implements CondominiumInterface
{
    public function availableTo(User $user): Collection
    {
        $query = Condominium::query();
        if (! $user->can('manage-access')) {
            $query->editableBy($user);
        }

        return $query->orderBy('name')->get(['id', 'name', 'document', 'status']);
    }

    public function activeForManagement(): Collection
    {
        return Condominium::where('status', 'active')->orderBy('name')->get(['id', 'name', 'status']);
    }

    public function hasManagementMembership(User $user, Condominium $condominium): bool
    {
        return $user->condominiumUsers()->current()
            ->where('condominium_id', $condominium->id)
            ->with('role')
            ->get()
            ->contains(fn ($membership) => in_array(mb_strtolower(trim($membership->role->name)), [
                'admin', 'administrador', 'síndico', 'sindico',
            ], true));
    }

    public function hasEditableAccess(User $user, Condominium $condominium): bool
    {
        return Condominium::editableBy($user)->whereKey($condominium->id)->exists();
    }

    public function documentExists(string $document, ?int $exceptId = null): bool
    {
        return Condominium::where('document', $document)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public function findForUpdate(int $id): Condominium
    {
        return Condominium::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function create(array $data): Condominium
    {
        return Condominium::create($data);
    }

    public function update(Condominium $condominium, array $data): Condominium
    {
        $condominium->update($data);

        return $condominium;
    }
}
