<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['condominium_id', 'category_id', 'unit_id', 'amount', 'due_date', 'direction', 'status'])]
class FinancialTransaction extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'date'];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinancialPayment::class, 'transaction_id');
    }
}
