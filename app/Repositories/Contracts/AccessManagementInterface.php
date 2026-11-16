<?php

namespace App\Repositories\Contracts;

use App\Models\Condominium;
use App\Models\CondominiumUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface AccessManagementInterface
{
    public function overview(): array;

    public function findUserByEmail(string $email): ?User;

    public function createUser(array $data): User;

    public function updateUser(User $user, array $data): void;

    public function createRole(array $data): Role;

    public function createPermission(array $data): Permission;

    public function syncRolePermissions(Role $role, array $permissionIds): Role;

    public function lockUser(int $id): User;

    public function lockCondominium(int $id): Condominium;

    public function activeMembershipExists(array $data): bool;

    public function createMembership(array $data): CondominiumUser;

    public function findRole(int $id): Role;

    public function revoke(CondominiumUser $membership): void;

    public function currentAccess(User $user): Collection;

    public function membershipsWithRoles(User $user): Collection;
}
