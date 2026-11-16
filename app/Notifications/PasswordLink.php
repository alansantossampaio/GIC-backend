<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use LogicException;

class PasswordLink extends Notification
{
    public function __construct(private string $token, private bool $invitation = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = config('services.frontend_url');
        if (! is_string($frontend) || ! filter_var($frontend, FILTER_VALIDATE_URL)) {
            throw new LogicException('FRONTEND_URL precisa ser configurada para envio de e-mails.');
        }

        $url = rtrim($frontend, '/').'/auth/redefinir-senha?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject($this->invitation ? 'Convite para acesso ao sistema' : 'Recuperação de senha')
            ->greeting('Olá!')
            ->line($this->invitation ? 'Você foi convidado para acessar o sistema.' : 'Recebemos uma solicitação para redefinir sua senha.')
            ->action($this->invitation ? 'Definir senha' : 'Redefinir senha', $url)
            ->line('O link expira em 60 minutos. Se você não esperava esta mensagem, ignore-a.');
    }
}
