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

    public function findUserByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function createUser(array $data): User
    {
        return User::create($data);
    }

    public function updateUser(User $user, array $data): void
    {
        $user->update($data);
    }

    public function createRole(array $data): Role
    {
        return Role::create($data);
    }

    public function createPermission(array $data): Permission
    {
        return Permission::create($data);
    }

    public function syncRolePermissions(Role $role, array $permissionIds): Role
    {
        $role->permissions()->sync($permissionIds);

        return $role->load('permissions');
    }

    public function lockUser(int $id): User
    {
        return User::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function lockCondominium(int $id): Condominium
    {
        return Condominium::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function activeMembershipExists(array $data): bool
    {
        return CondominiumUser::where($data)->where('status', 'active')->exists();
    }

    public function createMembership(array $data): CondominiumUser
    {
        return CondominiumUser::create($data);
    }

    public function findRole(int $id): Role
    {
        return Role::findOrFail($id);
    }

    public function revoke(CondominiumUser $membership): void
    {
        $membership->update(['status' => 'inactive', 'ended_at' => now()]);
    }

    public function currentAccess(User $user): Collection
    {
        return $user->condominiumUsers()
            ->current()
            ->whereHas('condominium', fn ($query) => $query->where('status', 'active'))
            ->with(['condominium:id,name', 'role.permissions:id,name,description'])
            ->get();
    }

    public function membershipsWithRoles(User $user): Collection
    {
        $user->loadMissing('condominiumUsers.role');

        return $user->condominiumUsers;
    }
}
