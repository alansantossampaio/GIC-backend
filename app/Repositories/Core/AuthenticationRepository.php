<?php

namespace App\Repositories\Core;

use App\Models\User;
use App\Repositories\Contracts\AuthenticationInterface;
use Illuminate\Support\Str;

class AuthenticationRepository implements AuthenticationInterface
{
    public function createToken(User $user): string
    {
        return $user->createToken('authToken')->plainTextToken;
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
    }
}
