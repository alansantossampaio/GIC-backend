<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['occurrence_id', 'amount', 'due_date', 'status'])]
class Fine extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'due_date' => 'date'];
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }
}
