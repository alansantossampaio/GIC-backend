<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['occurrence_id', 'status'])]
class Warning extends Model
{
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(Occurrence::class);
    }
}
