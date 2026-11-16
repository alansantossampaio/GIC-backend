<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class InitializeAdministrator extends Command
{
    protected $signature = 'access:initialize-admin {--promote-existing : Promove a conta existente do administrador}';

    protected $description = 'Cria ou promove o administrador inicial do sistema';

    public function handle(): int
    {
        $email = User::SYSTEM_ADMIN_EMAIL;
        $user = User::where('email', $email)->first();

        if ($user && ! $this->option('promote-existing')) {
            $this->error('A conta já existe. Use --promote-existing para promovê-la explicitamente.');

            return self::FAILURE;
        }

        if (! $user) {
            if (! $this->input->isInteractive()) {
                $this->error('A criação exige uma senha informada interativamente.');

                return self::FAILURE;
            }

            $password = $this->secret('Senha do administrador (mínimo 12 caracteres)');
            $confirmation = $this->secret('Confirme a senha');
            if (! is_string($password) || mb_strlen($password) < 12) {
                $this->error('A senha deve ter pelo menos 12 caracteres.');

                return self::FAILURE;
            }

            if ($password !== $confirmation) {
                $this->error('A confirmação da senha não corresponde à senha informada.');

                return self::FAILURE;
            }

            $user = User::create(['name' => 'Administrador', 'email' => $email, 'password' => $password]);
        }

        $user->forceFill(['is_system_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        $this->info('Administrador inicial configurado.');

        return self::SUCCESS;
    }
}
