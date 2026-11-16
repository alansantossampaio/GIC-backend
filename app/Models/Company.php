<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'document', 'status'])]
class Company extends Model
{
    public function companyCondominiums(): HasMany
    {
        return $this->hasMany(CompanyCondominium::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
