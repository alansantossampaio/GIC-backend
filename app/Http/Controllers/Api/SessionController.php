<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function __construct(private PermissionService $permissions) {}

    public function login(Request $request): JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json(['message' => 'Origem de autenticação não autorizada.'], 403);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['E-mail ou senha inválidos.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['message' => 'Autenticado com sucesso.']);
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($user->only(['id', 'name', 'email']) + [
            'email_verified' => $user->hasVerifiedEmail(),
            'can_manage_access' => $user->can('manage-access'),
        ]);
    }

    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'features' => $this->permissions->getAccessibleFeatures($user),
            'is_admin' => $this->permissions->isAdmin($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if (! $request->hasSession()) {
            return response()->json(['message' => 'Sessão indisponível.'], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}

