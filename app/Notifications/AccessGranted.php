<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AccessGranted extends Notification
{
    public function __construct(private string $condominium, private string $role) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => "Você recebeu o perfil {$this->role} no condomínio {$this->condominium}."];
    }
}
