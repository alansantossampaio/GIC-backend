<?php

namespace App\Services;

use App\Models\CondominiumUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccessGranted;
use App\Notifications\PasswordLink;
use App\Repositories\Contracts\AccessManagementInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccessManagementService
{
    public function __construct(private AccessManagementInterface $access) {}

    public function overview(): array
    {
        return $this->access->overview();
    }

    public function invite(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = $this->access->findUserByEmail($data['email']);
            if ($user) {
                if ($user->email_verified_at !== null) {
                    throw ValidationException::withMessages(['email' => ['Este usuário já confirmou sua conta.']]);
                }
                $this->access->updateUser($user, ['name' => $data['name']]);
            } else {
                $user = $this->access->createUser($data + ['password' => Str::random(64)]);
            }

            $status = Password::sendResetLink(['email' => $user->email], function (User $user, string $token): void {
                $user->notify(new PasswordLink($token, invitation: true));
            });
            if ($status !== Password::RESET_LINK_SENT) {
                throw ValidationException::withMessages(['email' => ['Não foi possível enviar o convite.']]);
            }

            return $user;
        });
    }

    public function createRole(array $data): Role
    {
        return $this->access->createRole($data + ['guard_name' => 'web']);
    }

    public function createPermission(array $data): Permission
    {
        return $this->access->createPermission($data);
    }

    public function updateRolePermissions(Role $role, array $permissionIds): Role
    {
        return $this->access->syncRolePermissions($role, $permissionIds);
    }

    public function grant(array $data): CondominiumUser
    {
        return DB::transaction(function () use ($data) {
            $user = $this->access->lockUser($data['user_id']);
            $condominium = $this->access->lockCondominium($data['condominium_id']);
            if ($condominium->status !== 'active') {
                throw ValidationException::withMessages(['condominium_id' => ['O condomínio está inativo.']]);
            }
            if ($this->access->activeMembershipExists($data)) {
                throw ValidationException::withMessages(['role_id' => ['Este acesso já está ativo.']]);
            }

            $membership = $this->access->createMembership($data + ['status' => 'active', 'started_at' => now()]);
            $user->notify(new AccessGranted($condominium->name, $this->access->findRole($data['role_id'])->name));

            return $membership;
        });
    }

    public function revoke(CondominiumUser $membership): bool
    {
        if ($membership->status !== 'active') {
            return false;
        }

        $this->access->revoke($membership);

        return true;
    }

    public function currentAccess(User $user): Collection
    {
        return $this->access->currentAccess($user);
    }
}
