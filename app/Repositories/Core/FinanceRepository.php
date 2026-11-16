<?php

namespace App\Repositories\Core;

use App\Models\Condominium;
use App\Models\FinancialCategory;
use App\Models\FinancialPayment;
use App\Models\FinancialTransaction;
use App\Repositories\Contracts\FinanceInterface;
use Illuminate\Database\Eloquent\Collection;

class FinanceRepository implements FinanceInterface
{
    public function categories(Condominium $condominium): Collection
    {
        return $condominium->financialCategories()->orderBy('name')->orderBy('id')->get();
    }

    public function createCategory(Condominium $condominium, array $data): FinancialCategory
    {
        return $condominium->financialCategories()->create($data);
    }

    public function transactions(Condominium $condominium): Collection
    {
        return $condominium->financialTransactions()->with('payments')->orderByDesc('id')->get();
    }

    public function findTransaction(Condominium $condominium, int $id, bool $lock = false): FinancialTransaction
    {
        $query = $condominium->financialTransactions()->whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    public function createTransaction(Condominium $condominium, array $data): FinancialTransaction
    {
        return $condominium->financialTransactions()->create($data);
    }

    public function payments(FinancialTransaction $transaction): Collection
    {
        return $transaction->relationLoaded('payments')
            ? $transaction->payments
            : $transaction->payments()->orderBy('id')->get();
    }

    public function createPayment(FinancialTransaction $transaction, array $data): FinancialPayment
    {
        return $transaction->payments()->create($data);
    }

    public function updateStatus(FinancialTransaction $transaction, string $status): void
    {
        $transaction->update(['status' => $status]);
    }
}
