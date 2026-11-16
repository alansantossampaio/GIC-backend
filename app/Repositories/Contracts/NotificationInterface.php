<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface NotificationInterface
{
    public function forUser(User $user): Collection;

    public function markRead(User $user, string $id): void;
}
