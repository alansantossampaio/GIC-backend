<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    public const SYSTEM_ADMIN_EMAIL = 'admin@admin.com.br';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function resident(): HasOne
    {
        return $this->hasOne(Resident::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function condominiumUsers(): HasMany
    {
        return $this->hasMany(CondominiumUser::class);
    }

    public function reportedOccurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'reported_by');
    }

    public function requestedServiceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'requested_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_system_admin' => 'boolean',
        ];
    }
}
