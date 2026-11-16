<?php

namespace App\Repositories\Core;

use App\Models\Condominium;
use App\Models\User;
use App\Repositories\Contracts\CondominiumInterface;
use Illuminate\Database\Eloquent\Collection;

class CondominiumRepository implements CondominiumInterface
{
    /**
     * Retorna os condomínios disponíveis para um usuário
     * @param User $user
     * @return Collection
     */
    public function availableTo(User $user): Collection
    {
        $query = Condominium::query();
        if (! $user->can('manage-access')) {
            $query->editableBy($user);
        }

        return $query->orderBy('name')->get(['id', 'name', 'document', 'status']);
    }

    /**
     * Retorna os condomínios ativos para gerenciamento
     * @return Collection
     */
    public function activeForManagement(): Collection
    {
        return Condominium::where('status', 'active')->orderBy('name')->get(['id', 'name', 'status']);
    }

    /**
     * Verifica se um usuário possui vínculo de gerenciamento com um condomínio
     * @param User $user
     * @param Condominium $condominium
     * @return bool
     */
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

    /**
     * Verifica se um usuário possui acesso editável a um condomínio
     * @param User $user
     * @param Condominium $condominium
     * @return bool
     */
    public function hasEditableAccess(User $user, Condominium $condominium): bool
    {
        return Condominium::editableBy($user)->whereKey($condominium->id)->exists();
    }

    /**
     * Verifica se um documento de condomínio já existe, exceto um ID específico
     * @param string $document
     * @param int|null $exceptId
     * @return bool
     */
    public function documentExists(string $document, ?int $exceptId = null): bool
    {
        return Condominium::where('document', $document)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * Bloqueia um condomínio para atualização
     * @param int $id
     * @return Condominium
     */
    public function findForUpdate(int $id): Condominium
    {
        return Condominium::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Cria um condomínio com os dados fornecidos
     * @param array $data
     * @return Condominium
     */
    public function create(array $data): Condominium
    {
        return Condominium::create($data);
    }

    /**
     * Atualiza um condomínio existente com os dados fornecidos
     * @param Condominium $condominium
     * @param array $data
     * @return Condominium
     */
    public function update(Condominium $condominium, array $data): Condominium
    {
        $condominium->update($data);

        return $condominium;
    }
}
