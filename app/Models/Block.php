<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['condominium_id', 'name', 'code'])]
class Block extends Model
{
    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
