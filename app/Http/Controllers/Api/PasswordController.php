<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordLink;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function requestLink(Request $request): JsonResponse
    {
        abort_unless($request->hasSession(), 403, 'Origem não autorizada.');
        $credentials = $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($credentials, function (User $user, string $token): void {
            $user->notify(new PasswordLink($token));
        });
        if ($status === Password::RESET_THROTTLED) {
            Log::info('Solicitação de recuperação de senha limitada pelo intervalo mínimo.');
        }

        return response()->json(['message' => 'Se a conta existir, enviaremos um link para o e-mail informado.']);
    }

    public function reset(Request $request): JsonResponse
    {
        abort_unless($request->hasSession(), 403, 'Origem não autorizada.');
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($credentials, function (User $user, string $password): void {
            $verified = $user->hasVerifiedEmail();
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
            event(new PasswordReset($user));
            if (! $verified) {
                event(new Verified($user));
            }
        });

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Link inválido ou expirado. Solicite outro link.'], 422);
        }

        return response()->json(['message' => 'Senha atualizada. Você já pode entrar.']);
    }
}
