<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CondominiumUser;
use App\Models\Role;
use App\Services\AccessManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccessManagementController extends Controller
{
    public function __construct(private AccessManagementService $access) {}

    public function index(): JsonResponse
    {
        return response()->json($this->access->overview());
    }

    public function invite(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = $this->access->invite($data);

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

        return response()->json($this->access->createRole($data), 201);
    }

    public function createPermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9._-]+$/', 'unique:permissions,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->access->createPermission($data), 201);
    }

    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);

        return response()->json($this->access->updateRolePermissions($role, $data['permission_ids']));
    }

    public function grant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'condominium_id' => ['required', 'integer', 'exists:condominiums,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        return response()->json($this->access->grant($data), 201);
    }

    public function revoke(CondominiumUser $membership): JsonResponse
    {
        if (! $this->access->revoke($membership)) {
            return response()->json(['message' => 'O acesso já está encerrado.'], 422);
        }

        return response()->json(['message' => 'Acesso encerrado.']);
    }

    public function myAccess(Request $request): JsonResponse
    {
        return response()->json($this->access->currentAccess($request->user()));
    }
}
