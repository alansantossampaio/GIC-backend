<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\NotificationInterface;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    public function __construct(private NotificationInterface $notifications) {}

    public function forUser(User $user): Collection
    {
        return $this->notifications->forUser($user);
    }

    public function markRead(User $user, string $id): void
    {
        $this->notifications->markRead($user, $id);
    }
}
