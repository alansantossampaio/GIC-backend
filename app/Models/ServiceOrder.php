<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['condominium_id', 'company_condominium_id', 'requested_by', 'description', 'priority', 'status'])]
class ServiceOrder extends Model
{
    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function companyCondominium(): BelongsTo
    {
        return $this->belongsTo(CompanyCondominium::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
