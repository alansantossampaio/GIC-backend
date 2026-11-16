<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['condominium_id', 'name', 'type'])]
class FinancialCategory extends Model
{
    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'category_id');
    }
}
