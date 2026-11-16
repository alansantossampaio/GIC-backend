<?php

namespace App\Repositories\Core;

use App\Models\User;
use App\Repositories\Contracts\NotificationInterface;
use Illuminate\Database\Eloquent\Collection;

class NotificationRepository implements NotificationInterface
{
    public function forUser(User $user): Collection
    {
        return $user->notifications()->latest()->get(['id', 'data', 'read_at', 'created_at']);
    }

    public function markRead(User $user, string $id): void
    {
        $user->notifications()->findOrFail($id)->markAsRead();
    }
}
