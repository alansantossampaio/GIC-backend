<?php

namespace App\Repositories\Core;

use App\Models\Condominium;
use App\Models\CondominiumUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\AccessManagementInterface;
use Illuminate\Database\Eloquent\Collection;

class AccessManagementRepository implements AccessManagementInterface
{
    /**
     * Retorna uma visão geral dos usuários, condomínios, papéis, permissões e vínculos ativos
     * @return array
     */
    public function overview(): array
    {
        return [
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'email_verified_at']),
            'condominiums' => Condominium::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'roles' => Role::with('permissions:id,name,description')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(),
            'memberships' => CondominiumUser::with(['user:id,name,email', 'condominium:id,name,status', 'role:id,name'])
                ->where('status', 'active')->orderByDesc('started_at')->get(),
        ];
    }

    /**
     * Retorna um usuário específico pelo email
     * @param string $email
     * @return User|null
     */
    public function findUserByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Cria um usuário com os dados fornecidos
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        return User::create($data);
    }

    /**
     * Atualiza um usuário existente com os dados fornecidos
     * @param User $user
     * @param array $data
     * @return void
     */
    public function updateUser(User $user, array $data): void
    {
        $user->update($data);
    }

    /**
     * Cria um papel com os dados fornecidos
     * @param array $data
     * @return Role
     */
    public function createRole(array $data): Role
    {
        return Role::create($data);
    }

    /**
     * Cria uma permissão com os dados fornecidos
     * @param array $data
     * @return Permission
     */
    public function createPermission(array $data): Permission
    {
        return Permission::create($data);
    }

    /**
     * Sincroniza as permissões de um papel com os IDs fornecidos
     * @param Role $role
     * @param array $permissionIds
     * @return Role
     */
    public function syncRolePermissions(Role $role, array $permissionIds): Role
    {
        $role->permissions()->sync($permissionIds);

        return $role->load('permissions');
    }
    /**
     * Bloqueia um usuário para atualização
     * @param int $id
     * @return User
     */
    public function lockUser(int $id): User
    {
        return User::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Bloqueia um condomínio para atualização
     * @param int $id
     * @return Condominium
     */
    public function lockCondominium(int $id): Condominium
    {
        return Condominium::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Verifica se existe um vínculo ativo com os dados fornecidos
     * @param array $data
     * @return bool
     */
    public function activeMembershipExists(array $data): bool
    {
        return CondominiumUser::where($data)->where('status', 'active')->exists();
    }

    /**
     * Cria um vínculo com os dados fornecidos
     * @param array $data
     * @return CondominiumUser
     */
    public function createMembership(array $data): CondominiumUser
    {
        return CondominiumUser::create($data);
    }

    /**
     * Retorna um papel específico pelo ID
     * @param int $id
     * @return Role
     */
    public function findRole(int $id): Role
    {
        return Role::findOrFail($id);
    }

    /**
     * Revoga um vínculo específico
     * @param CondominiumUser $membership
     * @return void
     */
    public function revoke(CondominiumUser $membership): void
    {
        $membership->update(['status' => 'inactive', 'ended_at' => now()]);
    }
    /**
     * Retorna os acessos atuais de um usuário
     * @param User $user
     * @return Collection
     */
    public function currentAccess(User $user): Collection
    {
        return $user->condominiumUsers()
            ->current()
            ->whereHas('condominium', fn ($query) => $query->where('status', 'active'))
            ->with(['condominium:id,name', 'role.permissions:id,name,description'])
            ->get();
    }

    /**
     * Retorna os vínculos de um usuário com seus papéis
     * @param User $user
     * @return Collection
     */
    public function membershipsWithRoles(User $user): Collection
    {
        $user->loadMissing('condominiumUsers.role');

        return $user->condominiumUsers;
    }
}
