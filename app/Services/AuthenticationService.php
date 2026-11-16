<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\AuthenticationInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;

class AuthenticationService
{
    public function __construct(
        private AuthenticationInterface $authentication,
        private UserService $users,
    ) {}

    public function register(array $data): User
    {
        return $this->users->createUser($data);
    }

    public function token(User $user): string
    {
        return $this->authentication->createToken($user);
    }

    public function resetPassword(User $user, string $password): void
    {
        $verified = $user->hasVerifiedEmail();
        $this->authentication->updatePassword($user, $password);
        event(new PasswordReset($user));
        if (! $verified) {
            event(new Verified($user));
        }
    }
}
