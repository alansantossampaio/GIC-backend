<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface AuthenticationInterface
{
    public function createToken(User $user): string;

    public function updatePassword(User $user, string $password): void;
}
