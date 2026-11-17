<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'document', 'status', 'unity_qtd'])]
class Condominium extends Model
{
    protected $table = 'condominiums';

    protected function casts(): array
    {
        return ['unity_qtd' => 'integer'];
    }

    public function scopeEditableBy(Builder $query, User $user): void
    {
        $query->where('status', 'active')->whereHas('condominiumUsers', function (Builder $membership) use ($user) {
            $membership->current()->where('user_id', $user->id)
                ->whereHas('role.permissions', fn (Builder $permissions) => $permissions->where('name', 'condominios.editar'));
        });
    }

    public function condominiumUsers(): HasMany
    {
        return $this->hasMany(CondominiumUser::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function companyCondominiums(): HasMany
    {
        return $this->hasMany(CompanyCondominium::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class);
    }

    public function commonAreas(): HasMany
    {
        return $this->hasMany(CommonArea::class);
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function financialCategories(): HasMany
    {
        return $this->hasMany(FinancialCategory::class);
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }
}
