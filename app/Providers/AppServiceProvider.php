<?php

namespace App\Providers;

use App\Models\User;
use App\Repositories\Contracts\CondominiumInterface;
use App\Repositories\Core\CondominiumRepository;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use LogicException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CondominiumInterface::class, CondominiumRepository::class);
        $this->app->bind(\App\Repositories\Contracts\OccurrenceInterface::class, \App\Repositories\Core\OccurrenceRepository::class);
        $this->app->bind(\App\Repositories\Contracts\FinanceInterface::class, \App\Repositories\Core\FinanceRepository::class);
        $this->app->bind(\App\Repositories\Contracts\ResidentInterface::class, \App\Repositories\Core\ResidentRepository::class);
        $this->app->bind(\App\Repositories\Contracts\StructureInterface::class, \App\Repositories\Core\StructureRepository::class);
        $this->app->bind(\App\Repositories\Contracts\EmployeeInterface::class, \App\Repositories\Core\EmployeeRepository::class);
        $this->app->bind(\App\Repositories\Contracts\AccessManagementInterface::class, \App\Repositories\Core\AccessManagementRepository::class);
        $this->app->bind(\App\Repositories\Contracts\NotificationInterface::class, \App\Repositories\Core\NotificationRepository::class);
        $this->app->bind(\App\Repositories\Contracts\AuthenticationInterface::class, \App\Repositories\Core\AuthenticationRepository::class);
        $this->app->bind(\App\Repositories\Contracts\UserInterface::class, \App\Repositories\Core\UserRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-access', fn (User $user): bool => $user->is_system_admin && $user->email === User::SYSTEM_ADMIN_EMAIL);

        VerifyEmail::createUrlUsing(function ($user): string {
            $frontend = config('services.frontend_url');
            if (! is_string($frontend) || ! filter_var($frontend, FILTER_VALIDATE_URL)) {
                throw new LogicException('FRONTEND_URL precisa ser configurada para envio de e-mails.');
            }

            $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ], absolute: false);

            return rtrim($frontend, '/').'/auth/verificar-email?'.http_build_query(['link' => $link]);
        });

        VerifyEmail::toMailUsing(fn ($user, string $url) => (new MailMessage)
            ->subject('Verificação de e-mail')
            ->greeting('Olá!')
            ->line('Confirme seu e-mail para concluir a verificação da conta.')
            ->action('Verificar e-mail', $url)
            ->line('O link expira em 60 minutos.'));
    }
}
