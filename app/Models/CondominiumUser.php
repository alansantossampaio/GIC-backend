<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['condominium_id', 'user_id', 'role_id', 'status', 'started_at', 'ended_at'])]
class CondominiumUser extends Model
{
    public function scopeCurrent(Builder $query): void
    {
        $query->where('status', 'active')->where('started_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', now()));
    }

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
