<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Condominium;
use App\Models\CondominiumUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccessGranted;
use App\Notifications\PasswordLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccessManagementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'email_verified_at']),
            'condominiums' => Condominium::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'roles' => Role::with('permissions:id,name,description')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(),
            'memberships' => CondominiumUser::with(['user:id,name,email', 'condominium:id,name,status', 'role:id,name'])
                ->where('status', 'active')->orderByDesc('started_at')->get(),
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);
        $email = $request->input('email');
        
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data, $email) {
            $existing = User::where('email', $email)->first();
            
            if ($existing) {
                if ($existing->email_verified_at !== null) {
                    throw ValidationException::withMessages([
                        'email' => ['Este usuário já confirmou sua conta.']
                    ]);
                }
                $user = $existing;
                $user->update(['name' => $data['name']]);
            } else {
                $user = User::create($data + ['password' => Str::random(64)]);
            }
            
            $status = Password::sendResetLink(['email' => $user->email], function (User $user, string $token): void {
                $user->notify(new PasswordLink($token, invitation: true));
            });
            
            if ($status !== Password::RESET_LINK_SENT) {
                throw ValidationException::withMessages(['email' => ['Não foi possível enviar o convite.']]);
            }

            return $user;
        });

        return response()->json([
            'id' => $user->id, 
            'message' => 'Convite enviado.',
            'is_reinvite' => $user->wasRecentlyCreated === false
        ], 201);
    }

    public function createRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')],
        ]);

        return response()->json(Role::create($data + ['guard_name' => 'web']), 201);
    }

    public function createPermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9._-]+$/', 'unique:permissions,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(Permission::create($data), 201);
    }

    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permission_ids']);

        return response()->json($role->load('permissions'));
    }

    public function grant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'condominium_id' => ['required', 'integer', 'exists:condominiums,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $membership = DB::transaction(function () use ($data) {
            $user = User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            $condominium = Condominium::whereKey($data['condominium_id'])->lockForUpdate()->firstOrFail();
            if ($condominium->status !== 'active') {
                throw ValidationException::withMessages(['condominium_id' => ['O condomínio está inativo.']]);
            }
            $exists = CondominiumUser::where($data)->where('status', 'active')->exists();
            if ($exists) {
                throw ValidationException::withMessages(['role_id' => ['Este acesso já está ativo.']]);
            }

            $membership = CondominiumUser::create($data + ['status' => 'active', 'started_at' => now()]);
            $user->notify(new AccessGranted(
                $condominium->name,
                Role::findOrFail($data['role_id'])->name,
            ));

            return $membership;
        });

        return response()->json($membership, 201);
    }

    public function revoke(CondominiumUser $membership): JsonResponse
    {
        if ($membership->status !== 'active') {
            return response()->json(['message' => 'O acesso já está encerrado.'], 422);
        }

        $membership->update(['status' => 'inactive', 'ended_at' => now()]);

        return response()->json(['message' => 'Acesso encerrado.']);
    }

    public function myAccess(Request $request): JsonResponse
    {
        $memberships = $request->user()->condominiumUsers()
            ->current()
            ->whereHas('condominium', fn ($query) => $query->where('status', 'active'))
            ->with(['condominium:id,name', 'role.permissions:id,name,description'])
            ->get();

        return response()->json($memberships);
    }
}
